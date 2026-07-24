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

    /** @var string */
    private $src;

    /** @var string */
    private $tableCacheFile;

    /** @var array<string, array<string, array{name?: mixed, key?: mixed}>> */
    private $tableCache = array();

    /** @var string */
    private $entityCacheFile;

    /** @var array<string, array<string, array<string, mixed>>> */
    private $entityCache = array();

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

        /** @var string */
        $documentRoot = $koncerto->getDocumentRoot();

        /** @var array<string, string> */
        $autoload = $koncerto->getConfig('autoload');

        /** @var string $src */
        $src = $autoload['App\\'];
        $this->src = $documentRoot . '/' . $src;

        if (!is_dir('cache')) {
            mkdir('cache');
        }
        $this->tableCacheFile = 'cache/orm.json';
        $this->entityCacheFile = 'cache/entities.json';
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
        $em = $this;
        $entities = array_map(function ($row) use ($em, $entity) {
            return $em->hydrate($entity, $row);
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
     * @param class-string $entity
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
     * @param class-string $entity
     * @return string
     * @throws \Exception
     */
    private function getTableName($entity)
    {
        $f = str_replace('\\', '/', str_replace('App\\', $this->src . '/', $entity)) . '.php';
        $cache = $this->tableCacheFile;
        if (empty($this->tableCache) && is_file($cache) && filemtime($cache) > filemtime($f)) {
            /** @var array<string, array<string, array{name?: mixed, key?: mixed}>> */
            $json = (array)json_decode((string)file_get_contents($cache), true);
            $this->tableCache = $json;
        }

        $hasCache = array_key_exists($this->dsn, $this->tableCache);
        $hasCache = $hasCache && array_key_exists($entity, $this->tableCache[$this->dsn]);
        $hasTableName = $hasCache && array_key_exists('name', $this->tableCache[$this->dsn][$entity]);
        $hasTableName = $hasTableName && is_string($this->tableCache[$this->dsn][$entity]['name']);
        if ($hasCache && $hasTableName && filemtime($this->tableCacheFile) > filemtime($f)) {
            return $this->tableCache[$this->dsn][$entity]['name'];
        }

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
        $tableName = $hasTable ? $parsed[$key]['table'] : strtolower($ref->getShortName());

        $this->tableCache[$this->dsn][$entity]['name'] = $tableName;
        file_put_contents($this->tableCacheFile, json_encode($this->tableCache));

        return $tableName;
    }

    /**
     * @param class-string $entity
     * @return string
     * @throws \Exception
     */
    public function getTableKey($entity)
    {
        $f = str_replace('\\', '/', str_replace('App\\', $this->src . '/', $entity)) . '.php';
        $cache = $this->tableCacheFile;
        if (empty($this->tableCache) && is_file($cache) && filemtime($cache) > filemtime($f)) {
            /** @var array<string, array<string, array{name?: mixed, key?: mixed}>> */
            $json = (array)json_decode((string)file_get_contents($cache), true);
            $this->tableCache = $json;
        }

        $hasCache = array_key_exists($this->dsn, $this->tableCache);
        $hasCache = $hasCache && array_key_exists($entity, $this->tableCache[$this->dsn]);
        $hasTableKey = $hasCache && array_key_exists('key', $this->tableCache[$this->dsn][$entity]);
        $hasTableKey = $hasTableKey && is_string($this->tableCache[$this->dsn][$entity]['key']);
        if ($hasCache && $hasTableKey && filemtime($this->tableCacheFile) > filemtime($f)) {
            return $this->tableCache[$this->dsn][$entity]['key'];
        }

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
        $tableKey = $hasKey ? $parsed[$key]['key'] : 'id';

        $this->tableCache[$this->dsn][$entity]['key'] = $tableKey;
        file_put_contents($this->tableCacheFile, json_encode($this->tableCache));

        return $tableKey;
    }

    /**
     * @template T of object
     * @param class-string<T> $entity
     * @param array<string, mixed> $data
     * @return T
     * @throws \Exception
     */
    public function hydrate($entity, $data)
    {
        $props = $this->describe($entity);

        $entity = new $entity();

        foreach ($props as $name => $infos) {
            if (array_key_exists($name, $data)) {
                $value = $data[$name];
                if (is_array($infos) && array_key_exists('int', $infos) && is_numeric($value)) {
                    $value = intval($value);
                }
                if (is_array($infos) && array_key_exists('integer', $infos) && is_numeric($value)) {
                    $value = intval($value);
                }
                if (is_array($infos) && array_key_exists('float', $infos) && is_numeric($value)) {
                    $value = floatval($value);
                }
                if (is_array($infos) && array_key_exists('double', $infos) && is_numeric($value)) {
                    $value = floatval($value);
                }
                if (is_array($infos) && array_key_exists('bool', $infos)) {
                    $value = !empty($value);
                }
                if (is_array($infos) && array_key_exists('boolean', $infos)) {
                    $value = !empty($value);
                }
                $entity->{$name} = $value;
            }
        }

        return $entity;
    }

    /**
     * @param class-string $entity
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function describe($entity)
    {
        $f = str_replace('\\', '/', str_replace('App\\', $this->src . '/', $entity)) . '.php';
        $cache = $this->entityCacheFile;
        if (empty($this->entityCache) && is_file($cache) && filemtime($cache) > filemtime($f)) {
            /** @var array<string, array<string, array<string, mixed>>> */
            $json = (array)json_decode((string)file_get_contents($cache), true);
            $this->entityCache = $json;
        }

        $hasCache = array_key_exists($this->dsn, $this->entityCache);
        $hasCache = $hasCache && array_key_exists($entity, $this->entityCache[$this->dsn]);
        if ($hasCache && filemtime($this->entityCacheFile) > filemtime($f)) {
            return $this->entityCache[$this->dsn][$entity];
        }

        $desc = array();
        $ref = new \ReflectionClass($entity);
        $props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            if (false !== $prop->getDocComment()) {
                $desc[$prop->getName()] = KoncertoAnnotation::parseAnnotation($prop->getDocComment(), 'var');
            } else {
                $obj = new $entity();
                $desc[$prop->getName()] = gettype($prop->getValue($obj));
            }
        }

        $this->entityCache[$this->dsn][$entity] = $desc;
        file_put_contents($this->entityCacheFile, json_encode($this->entityCache));

        return $desc;
    }
}
