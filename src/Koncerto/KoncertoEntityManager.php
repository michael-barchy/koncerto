<?php

namespace Koncerto;

class KoncertoEntityManager
{
    /** @var Koncerto */
    private $koncerto;

    /** @var string */
    private $dsn;

    /** @var \PDO */
    private $connection;

    /**
     * @param Koncerto $koncerto
     * @param ?string $key
     * @throws \Exception
     */
    public function __construct($koncerto, $key = null)
    {
        $this->koncerto = $koncerto;

        if (null === $key) {
            $key = 'default';
        }

        $entityManagers = (array)$this->koncerto->getConfig('entityManager');

        if (!array_key_exists($key, $entityManagers)) {
            throw new \Exception(sprintf('EntityManager with key "%s" does not exist.', $key));
        }

        if (!is_string($entityManagers[$key])) {
            throw new \Exception(sprintf('EntityManager with key "%s" is not a valid DSN.', $key));
        }

        $this->dsn = $entityManagers[$key];
    }

    /**
     * @return \PDO
     */
    public function getConnection()
    {
        if (null === $this->connection) {
            $this->connection = new \PDO($this->dsn);
        }

        return $this->connection;
    }

    /**
     * @template T of object
     * @param class-string<T> $entity
     * @param array<string, mixed> $criteria
     * @return T[]
     * @throws \Exception
     */
    public function findAll($entity, $criteria = array())
    {
        $table = $this->getTableName($entity);

        $where = '';
        if (!empty($criteria)) {
            $where = ' WHERE ' . implode(' AND ', array_map(function ($key) {
                return sprintf('%s = :%s', $key, $key);
            }, array_keys($criteria)));
        }

        $prepare = $this->getConnection()->prepare(sprintf('SELECT * FROM %s%s', $table, $where));

        $stmt = $prepare->execute($criteria);

        if (false === $stmt) {
            throw new \Exception(sprintf('Failed to query table "%s".', $table));
        }

        $rows = (array)$prepare->fetchAll(\PDO::FETCH_ASSOC);
        $entities = array_map(function ($row) use ($entity) {
            return KoncertoEntityManager::hydrate($entity, $row);
        }, $rows);

        return $entities;
    }

    /**
     * @template T of object
     * @param class-string<T> $entity
     * @param mixed $id
     * @return ?T
     * @throws \Exception
    */
    public function find($entity, $id)
    {
        $entities = $this->findAll($entity, array('id' => $id));

        return array_shift($entities);
    }

    /**
     * @template T of object
     * @param class-string<T> $entity
     * @param T $object
     * @return ?T
     */
    public function persist($entity, $object)
    {
        $table = $this->getTableName($entity);
        $key = $this->getTableKey($entity);

        $data = (array)$object;
        $id = $data[$key];
        unset($data[$key]);
        $fields = array_keys($data);
        $placeholders = array_map(function ($field) {
            return ':' . $field;
        }, $fields);
        $update = array_map(function ($field) {
            return $field . ' = :' . $field;
        }, $fields);

        $sql = sprintf(
            'INSERT INTO %s(%s) VALUES(%s)',
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        if (!empty($id)) {
            $sql = sprintf(
                'UPDATE %s SET %s WHERE %s = :_key',
                $table,
                implode(',', $update),
                $key
            );
            $data['_key'] = $id;
        }

        $prepare = $this->getConnection()->prepare($sql);

        $stmt = $prepare->execute($data);

        if (false === $stmt) {
            throw new \Exception(sprintf('Failed to persist entity in table "%s".', $table));
        }

        if (null === $id) {
            $id = $this->getConnection()->lastInsertId();
        }

        return $this->find($entity, $id);
    }

    /**
     * @template T of object
     * @param class-string<T> $entity
     * @param mixed $id
     * @return boolean
     */
    public function remove($entity, $id)
    {
        $object = $this->find($entity, $id);

        if (null === $object) {
            throw new \Exception('Entity not found.');
        }

        $table = $this->getTableName($entity);
        $key = $this->getTableKey($entity);

        $data = (array)$object;
        $id = $data[$key];

        $sql = sprintf(
            'DELETE FROM %s WHERE %s = :_key',
            $table,
            $key
        );

        $prepare = $this->getConnection()->prepare($sql);

        $stmt = $prepare->execute(array('_key' => $id));

        if (false === $stmt) {
            throw new \Exception(sprintf('Failed to remove entity from table "%s".', $table));
        }

        return true;
    }

    /**
     * @template T of object
     * @param class-string<T> $entity
     * @return string
     * @throws \Exception
     */
    private function getTableName($entity)
    {
        $ref = new \ReflectionClass($entity);
        $docComment = $ref->getDocComment();

        if (false === $docComment) {
            throw new \Exception(sprintf('Entity "%s" does not have a valid doc comment.', $entity));
        }

        $parsed = KoncertoAnnotation::parseAnnotation($docComment);
        $key = 'entity()';
        if (!array_key_exists($key, $parsed) || !is_array($parsed[$key])) {
            throw new \Exception(sprintf('Entity "%s" does not have a valid @see K::entity() annotation.', $entity));
        }

        $hasTable = array_key_exists('table', $parsed[$key]) && is_string($parsed[$key]['table']);

        return $hasTable ? $parsed[$key]['table'] : strtolower($ref->getShortName());
    }

    /**
     * @template T of object
     * @param class-string<T> $entity
     * @return string
     * @throws \Exception
     */
    private function getTableKey($entity)
    {
        $ref = new \ReflectionClass($entity);
        $docComment = $ref->getDocComment();

        if (false === $docComment) {
            throw new \Exception(sprintf('Entity "%s" does not have a valid doc comment.', $entity));
        }

        $parsed = KoncertoAnnotation::parseAnnotation($docComment);
        $key = 'entity()';
        if (!array_key_exists($key, $parsed) || !is_array($parsed[$key])) {
            throw new \Exception(sprintf('Entity "%s" does not have a valid @see K::entity() annotation.', $entity));
        }

        $hasKey = array_key_exists('key', $parsed[$key]) && is_string($parsed[$key]['key']);

        return $hasKey ? $parsed[$key]['key'] : 'id';
    }

    /**
     * @template T of object
     * @param class-string<T> $entity
     * @param array<string, mixed> $data
     * @return T
     * @throws \Exception
     */
    public static function hydrate($entity, $data)
    {
        $ref = new \ReflectionClass($entity);
        $props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);

        $entity = new $entity();

        foreach ($props as $prop) {
            $name = $prop->getName();
            if (array_key_exists($name, $data)) {
                $entity->{$name} = $data[$name];
            }
        }

        return $entity;
    }
}
