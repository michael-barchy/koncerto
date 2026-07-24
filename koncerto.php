<?php

namespace Koncerto;

class Koncerto
{
    /** @var array<array-key, mixed> */
    private $config = array();

    /**
     * Initialize Koncerto using config as array/ini/json/php
     * @param string|array<array-key, mixed> $config
     */
    public function __construct($config = array())
    {
        if (is_string($config) && !empty($config) && !is_file($config)) {
            return;
        }

        $hasScriptFilename = array_key_exists('SCRIPT_FILENAME', $_SERVER) && is_string($_SERVER['SCRIPT_FILENAME']);
        $scriptFilename = $hasScriptFilename ? $_SERVER['SCRIPT_FILENAME'] : __FILE__;
        if (!array_key_exists('DOCUMENT_ROOT', $_SERVER) && $hasScriptFilename) {
            $_SERVER['DOCUMENT_ROOT'] = dirname($scriptFilename);
        }

        if (is_string($config) && '.ini' === strrchr($config, '.')) {
            $ini = (string)file_get_contents($config);
            foreach ($_SERVER as $k => $v) {
                if (is_array($v)) {
                    $v = json_encode($v);
                }
                /** @var string $v */
                $ini = str_replace('%' . $k . '%', $v, $ini);
            }
            $config = (array)parse_ini_string($ini, true);
        }

        if (is_string($config) && '.json' === strrchr($config, '.')) {
            $json = (string)file_get_contents($config);
            foreach ($_SERVER as $k => $v) {
                if (is_array($v)) {
                    $v = json_encode($v);
                }
                /** @var string $v */
                $json = str_replace('%' . $k . '%', $v, $json);
            }
            $config = (array)json_decode($json, true);
        }

        if (is_string($config) && '.php' === strrchr($config, '.')) {
            $config = (array)include($config);
        }

        if (is_string($config)) {
            $config = (array)json_decode($config, true);
        }

        $this->config = $config;
        $this->autoload();
    }

    /**
     * @return string
     */
    public function getDocumentRoot()
    {
        $root = $this->getConfig('documentRoot');

        if (null === $root || !is_string($root)) {
            $hasScript = array_key_exists('SCRIPT_FILENAME', $_SERVER);
            $script = $hasScript && is_string($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : __FILE__;
            $root = dirname($script);
        }

        return $root;
    }

    /**
     * @return void
     */
    private function autoload()
    {
        /** @var array<string, string> */
        $autoload = array_key_exists('autoload', $this->config) ? (array)$this->config['autoload'] : array();
        $root = $this->getDocumentRoot();
        spl_autoload_register(function ($class) use ($autoload, $root) {
            if (class_exists($class, false)) {
                return;
            }

            foreach ($autoload as $prefix => $src) {
                if (0 !== strpos($class, $prefix)) {
                    continue;
                }

                $classFile = sprintf(
                    '%s/%s/%s',
                    $root,
                    $src,
                    preg_replace('/\\\/', '/', str_replace($prefix, '', $class)) . '.php'
                );

                $classPath = 'phar://' === substr($classFile, 0, 7) ? $classFile : realpath($classFile);
                if (false !== $classPath && is_file($classPath)) {
                    require_once($classPath);
                }
            }
        });
    }

    /**
     * Get configuration key
     * @param string $key
     * @return mixed
     */
    public function getConfig($key)
    {
        if (!array_key_exists($key, $this->config)) {
            return null;
        }

        return $this->config[$key];
    }

    /**
     * Returns current request as KoncertoRequest
     * @return KoncertoRequest
     */
    public function request()
    {
        return new KoncertoRequest($this);
    }

    /**
     * Generate response for request
     * @param KoncertoRequest|null $request
     * @return string|null
     */
    public function response($request = null)
    {
        if (null === $request) {
            $request = $this->request();
        }

        $args = array();
        $match = $request->match($args);

        if (null === $match) {
            $response = $this->asset($request);

            if (null === $response) {
                return null;
            }

            $this->headers($response);

            return $response->getContent();
        }

        $parts = explode('::', $match);

        $class = array_shift($parts);
        if (empty($class)) {
            return null;
        }

        $method = array_shift($parts);
        if (empty($method)) {
            return null;
        }

        $o = new $class($this);
        $response = $o->$method($args);

        $this->headers($response);

        return $response->getContent();
    }

    /**
     * @param KoncertoResponse $response
     * @return void
     */
    private function headers($response)
    {
        header('Content-type: ' . $response->getContentType());
    }

    /**
     * @param KoncertoRequest $request
     * @return ?KoncertoResponse
     */
    private function asset($request)
    {
        $file = '.' . $request->getPathInfo(false);
        if ('/' === substr($file, -1)) {
            $file = substr($file, 0, strlen($file) - 1);
        }

        if (!is_file($file)) {
            $parts = explode('/', $file);
            array_shift($parts);
            array_shift($parts);
            $root = $this->getDocumentRoot();

            $file = $root . '/' . implode('/', $parts);
        }

        if (!is_file($file)) {
            return null;
        }

        $assetTypes = array(
            'text\/.*',
            'image\/.*',
            'video\/.*',
            'application\/.*'
        );
        $ext = substr((string)strrchr($file, '.'), 1);
        $contentType = function_exists('mime_content_type') ? mime_content_type($file) : 'application/' . $ext;
        if (false === $contentType) {
            $contentType = 'text/plain';
        }

        foreach ($assetTypes as $type) {
            if (preg_match('/' . $type . '/', $contentType)) {
                $response = new KoncertoResponse();
                $response->setContentType($contentType);
                $response->setContent((string)file_get_contents($file));

                return $response;
            }
        }

        return null;
    }
}


// src/Koncerto/KoncertoAnnotation.php

class KoncertoAnnotation
{
    /**
     * @param array{name: string} $params
     * @return array{name: string} $params
     */
    public static function route($params)
    {
        return $params;
    }

    /**
     * @param array{name: string} $params
     * @return array{name: string} $params
     */
    public static function liveProp($params)
    {
        return $params;
    }

    /**
     * @param array{name: string} $params
     * @return array{name: string} $params
     */
    public static function liveAction($params)
    {
        return $params;
    }

    /**
     * @param array{table?: string, key?: string} $params
     * @return array{table?: string, key?: string} $params
     */
    public static function entity($params)
    {
        return $params;
    }

    /**
     * @param ?string $file
     * @param ?class-string $className
     * @return array<string, mixed>
     */
    public static function parseClass($file, $className = null)
    {
        if (null !== $file && null === $className) {
            $nameSpace = basename(dirname($file));
            $className = 'App\\' . $nameSpace . '\\' . str_replace('.php', '', basename($file));
        }

        $parsed = array();

        if (null !== $className && class_exists($className, true)) {
            $ref = new \ReflectionClass($className);
            $methods = $ref->getMethods(\ReflectionProperty::IS_PUBLIC);
            foreach ($methods as $method) {
                $key = $className . '::' . $method->getName();
                $parsed[$key] = KoncertoAnnotation::parseAnnotation((string)$method->getDocComment());
            }
            $props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);
            foreach ($props as $prop) {
                $key = $className . '::' . $prop->getName();
                $parsed[$key] = KoncertoAnnotation::parseAnnotation((string)$prop->getDocComment());
            }
        }

        return $parsed;
    }

    /**
     * @param string $comment
     * @param string $prefix
     * @return array<string, mixed>
     */
    public static function parseAnnotation($comment, $prefix = 'see')
    {
        $parsed = array();
        $lines = preg_split('/(\n|\r)/', $comment);
        if (false === $lines) {
            $lines = explode('\n', $comment);
        }
        foreach ($lines as $line) {
            $line = trim($line);
            $line = (string)preg_replace('/(\n|\r)/', '', $line);
            $see = array();
            if (preg_match('/@' . $prefix . ' ([^ ]*)(.*)/', $line, $see)) {
                $parts = explode('::', $see[1]);
                $methodName = array_pop($parts);
                $parsed[$methodName] = (array)json_decode(trim($see[2]), true);
            }
        }

        return $parsed;
    }
}


// src/Koncerto/KoncertoApiController.php

use Koncerto\KoncertoAnnotation as K;

class KoncertoApiController extends KoncertoController
{
    /**
     * @see K::route() {"name": "/api/"}
     * @return KoncertoResponse
     */
    public function api()
    {
        return $this->json(array());
    }

    /**
     * @see K::route() {"name": "/api/docs/"}
     * @return KoncertoResponse
     */
    public function docs()
    {
        $response = new KoncertoResponse();
        $response->setContent('<h1>Under construction</h1>');

        return $response;
    }

    /**
     * @see K::route() {"name": "/api/%s/"}
     * @param array<mixed>|null $args
     * @return KoncertoResponse
     */
    public function crud($args = array())
    {
        return $this->json(array('params' => $args));
    }
}


// src/Koncerto/KoncertoController.php

class KoncertoController
{
    /** @var Koncerto $koncerto */
    private $koncerto;

    /**
     * @param Koncerto $koncerto
     */
    public function __construct($koncerto)
    {
        $this->koncerto = $koncerto;
    }

    /**
     * @param ?string $key
     * @return KoncertoEntityManager
     */
    public function getEntityManager($key = null)
    {
        return new KoncertoEntityManager($this->koncerto, $key);
    }

    /**
     * @param mixed $data
     * @return KoncertoResponse
     */
    public function json($data)
    {
        $response = new KoncertoResponse();
        $response->setContentType('application/json');
        $response->setContent((string)json_encode($data));

        return $response;
    }

    /**
     * @param string $template
     * @param array<string, mixed> $context = array()
     * @return KoncertoResponse
     * @throws \Exception
     */
    public function render($template, $context = array())
    {
        $engine = $this->koncerto->getConfig('templateEngine');
        if (null === $engine || !is_string($engine) || !class_exists($engine)) {
            throw new \Exception('No template engine defined or template engine not found');
        }

        $e = new $engine($this->koncerto);
        if (!$e instanceof KoncertoTemplate) {
            throw new \Exception('Invalid template engine');
        }

        return $e->render($template, $context);
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig($key, $default = null)
    {
        $value = $this->koncerto->getConfig($key);

        return null === $value ? $default : $value;
    }

    /**
     * @return string
     */
    public function getDocumentRoot()
    {
        return $this->koncerto->getDocumentRoot();
    }

    /**
     * @return KoncertoRequest
     */
    public function getRequest()
    {
        return $this->koncerto->request();
    }
}


// src/Koncerto/KoncertoEntityManager.php

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


// src/Koncerto/KoncertoHereTemplate.php

class KoncertoHereTemplate implements KoncertoTemplate
{
    /** @var object */
    private $here;

    public function __construct()
    {
        if (!class_exists('HereTemplate\\HereTemplate')) {
            throw new \Exception('HereTemplate is not installed');
        }

        $this->here = new \HereTemplate\HereTemplate();
    }

    public function render($template, $context = array())
    {
        if (!method_exists($this->here, 'render')) {
            throw new \Exception('HereTemplate is not properly installed');
        }

        $content = $this->here->render($template, $context);
        $response = new KoncertoResponse();
        $response->setContent($content);

        return $response;
    }
}


// src/Koncerto/KoncertoImpulsusController.php

/**
 * Koncerto Impulsus Bridge
 */
class KoncertoImpulsusController extends KoncertoController
{
    /**
     * @param Koncerto $koncerto
     */
    public function __construct($koncerto)
    {
        parent::__construct($koncerto);
    }

    public function render($template, $context = array())
    {
        if (null !== $this->getRequest()->get('_live') && method_exists($this, 'postMount')) {
            $live = $this->getRequest()->get('_live');
            if (is_string($live)) {
                $data = (array)json_decode($live, true);
            } else {
                $data = (array)$live;
            }
            /** @var array<string, string> $data*/
            $this->postMount($data);
        }

        if (null !== $this->getRequest()->get('_action')) {
            $action = $this->getRequest()->get('_action');
            if (is_string($action) && method_exists($this, $action)) {
                return $this->$action();
            }
        }

        $response = parent::render($template, $context);
        $js = $this->getConfig('impulsus');
        /** @var string */
        $appPrefix = $this->getConfig('appPrefix', '');
        if (null === $js || !is_string($js)) {
            $js = $appPrefix . '/impulsus/impulsus.js';
        }

        $impulsus = sprintf(
            '<script type="text/javascript" src="%s"></script>',
            $js
        );

        $root = $this->getRequest()->getPathInfo();
        if ('/' === substr($root, -1)) {
            $root = substr($root, 0, strlen($root) - 1);
        }
        $baseHref = sprintf(
            '<base href=".%s" />',
            $root
        );

        $live = sprintf(
            '<script type="text/javascript" data-name="$live">%s</script>',
            $this->live()
        );

        $head = sprintf(
            "  %s\r\n  %s\r\n  %s\r\n</head>",
            $baseHref,
            $live,
            $impulsus
        );

        $content = $response->getContent();

        $content = str_replace(
            '</head>',
            $head,
            $content
        );

        $response->setContent($content);

        return $response;
    }

    /**
     * @return string
     */
    private function live()
    {
        if (method_exists($this, 'postMount')) {
            $this->postMount();
        }

        $targetsJS = $this->targets();
        $commonJS = $this->common();
        $eventsJS = $this->events();

        return <<<JS


window.addEventListener('impulsus:ready', function () {
    var root = document.querySelector('html');
    if (root) {
        root.setAttribute('data-controller', '\$live');
    }
});

window.addEventListener('impulsus:controller', function (event) {
    (function (impulsus) {
        if (impulsus) {
            var models = Array.prototype.slice.call(document.querySelectorAll('[data-model]'));
            models.forEach((function(model) {
                model.setAttribute('data--live-target', model.getAttribute('data-model'));
            }));

            impulsus.controller(function (controller) {
{$targetsJS}
{$commonJS}
{$eventsJS}
            }, event);
        }
    })(window.Impulsus);
});


JS;
    }

    /**
     * @return string
     */
    private function targets()
    {
        $className = get_class($this);
        $props = array();
        $ref = new \ReflectionClass($className);
        $f = $ref->getFileName();
        if (false !== $f) {
            $parsed = KoncertoAnnotation::parseClass($f);
            foreach ($parsed as $classProp => $annotations) {
                $parts = explode('::', $classProp);
                $propName = array_pop($parts);
                /** @var array<array-key, mixed> $annotations */
                if (!empty($propName) && array_key_exists('liveProp()', $annotations)) {
                    $props[$propName] = $annotations['liveProp()'];
                }
            }
        }

        $targets = array();
        foreach ($props as $propName => $prop) {
            array_push($targets, sprintf(
                "            controller.targets[%s].set(%s);",
                json_encode($prop['name']),
                json_encode($this->{$propName})
            ));
        }
        $targetsJS = implode("\r\n", $targets);

        return $targetsJS;
    }

    /**
     * @return string
     */
    private function common()
    {
        $hasBootstrap = array_key_exists('BOOTSTRAP', $_SERVER) && is_string($_SERVER['BOOTSTRAP']);
        $bootstrap = $hasBootstrap ? $_SERVER['BOOTSTRAP'] : '';

        return <<<JS
            var controllerEvent = function(trigger, actionName) {
                controller.on(trigger, function (param) {
                    var state = {};
                    var updateState = function() {
                        for (var key in controller.targets) {
                            var attr = controller.targets[key].attr('data-model-attr');
                            if (attr) {
                                state[key] = controller.targets[key].attr(attr);
                            } else {
                                state[key] = controller.targets[key].get();
                            }
                        }
                    };
                    updateState();
                    var bootstrap = '{$bootstrap}';
                    var route = '{$this->getRequest()->getPathInfo()}';
                    var params = '&' + [
                        '_param=' + encodeURIComponent(param),
                        '_live=' + encodeURIComponent(JSON.stringify(state))
                    ].join('&');
                    var url = new URL(location.href);
                    impulsus.xhr(url.toString(), function (response) {
                        var state = JSON.parse(response);
                        if ('object' === typeof state) {
                            for (var key in state) {
                                if (key in controller.targets) {
                                    var attr = controller.targets[key].attr('data-model-attr');
                                    var value = state[key];
                                    if (Array.isArray(value) || 'object' === typeof value) {
                                        var selector = '[data-model="' + key + '"] [data-model-' + key + ']';
                                        var subModels = Array.prototype.slice.call(document.querySelectorAll(selector));
                                        subModels.forEach(function(subModel) {
                                            var dataModel = subModel.getAttribute('data-model-' + key);
                                            subModel.setAttribute('data--live-target-' + key, dataModel);
                                        });
                                        if (attr) {
                                            controller.targets[key].attr('data--live-attr-' + key, attr);
                                        }
                                        controller.targets[key].refreshTargets();
                                        controller.targets[key].merge(value);
                                        updateState();
                                    } else {
                                        if (attr) {
                                            controller.targets[key].attr(attr, value);
                                        } else {
                                            controller.targets[key].set(value);
                                        }
                                    }
                                }
                            }
                        }
                    }, 'POST', actionName + params, 'application/x-www-form-urlencoded');
                });
            };
JS;
    }

    /**
     * @return string
     */
    private function events()
    {
        $className = get_class($this);
        $actions = array();
        $ref = new \ReflectionClass($className);
        $f = $ref->getFileName();
        if (false !== $f) {
            $parsed = KoncertoAnnotation::parseClass($f);
            foreach ($parsed as $classProp => $annotations) {
                $parts = explode('::', $classProp);
                $propName = array_pop($parts);
                /** @var array<array-key, mixed> $annotations */
                if (!empty($propName) && array_key_exists('liveAction()', $annotations)) {
                    $actions[$propName] = $annotations['liveAction()'];
                }
            }
        }

        $events = array();
        foreach ($actions as $actionName => $action) {
            array_push($events, sprintf(
                <<<JS
                controllerEvent(%s, %s);
JS
                ,
                json_encode($action['name']),
                json_encode('_action=' . $actionName)
            ));
        }
        $eventsJS = implode("\r\n", $events);

        return $eventsJS;
    }
}


// src/Koncerto/KoncertoRequest.php

class KoncertoRequest
{
    /** @var string */
    private $src;

    /** @var string */
    private $pathName;

    /** @var array<string, mixed> */
    private $routes = array();

    /** @var string */
    private $cache;

    /**
     * @param Koncerto $koncerto
     */
    public function __construct($koncerto)
    {
        $hasPathInfo = array_key_exists('PATH_INFO', $_SERVER) && is_string($_SERVER['PATH_INFO']);
        $pathInfo = $hasPathInfo ? $_SERVER['PATH_INFO'] : null;

        $hasRequestUri = array_key_exists('REQUEST_URI', $_SERVER) && is_string($_SERVER['REQUEST_URI']);
        $requestUri = $hasRequestUri ? $_SERVER['REQUEST_URI'] : null;

        $hasQueryString = array_key_exists('QUERY_STRING', $_SERVER) && is_string($_SERVER['QUERY_STRING']);
        $queryString = $hasQueryString ? $_SERVER['QUERY_STRING'] : null;

        $isQueryString = is_string($queryString) && is_string($requestUri);
        $requestUri = $isQueryString ? str_replace('?' . $queryString, '', $requestUri) : $requestUri;

        $this->pathName = is_string($pathInfo) ? $pathInfo : '';
        $this->pathName = is_string($requestUri) ? $requestUri : $this->pathName;

        if (!empty($queryString)) {
            $this->pathName = str_replace('?' . $queryString, '', $this->pathName);
        }

        if (null !== $this->get('_route') && is_string($this->get('_route'))) {
            $this->pathName = $this->get('_route');
        }

        $appPrefix = $koncerto->getConfig('appPrefix');
        if (is_string($appPrefix) && 0 === strpos($this->pathName, $appPrefix)) {
            $this->pathName = substr($this->pathName, strlen($appPrefix));
            $_SERVER['APP_PREFIX'] = $appPrefix;
        }

        /** @var string */
        $documentRoot = $koncerto->getDocumentRoot();

        /** @var string */
        $root = array_key_exists('DOCUMENT_ROOT', $_SERVER) ? $_SERVER['DOCUMENT_ROOT'] : $documentRoot;

        $path = str_replace($root, '', $documentRoot);

        if (0 === strpos($this->pathName, $path)) {
            $this->pathName = substr($this->pathName, strlen($path));
        }

        /** @var array<string, string> */
        $autoload = $koncerto->getConfig('autoload');

        /** @var string $src */
        $src = $autoload['App\\'];
        $this->src = $documentRoot . '/' . $src;

        if (!is_dir('cache')) {
            mkdir('cache');
        }
        $this->cache = 'cache/routes.json';
    }

    /**
     * @param boolean $asPath
     * @return string
     */
    public function getPathInfo($asPath = true)
    {
        $path = $this->pathName;
        if ($asPath && '/' !== substr($this->pathName, -1)) {
            $path .= '/';
        }

        return $path;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        if (array_key_exists($key, $_REQUEST)) {
            return $_REQUEST[$key];
        }

        return null;
    }

    /**
     * Match request to route
     * @param array<mixed>|null $args
     * @return ?string
     */
    public function match(&$args = array())
    {
        $path = $this->pathName;

        $routes = array_filter($this->routes(), function ($route) use ($path) {
            /** @var array{name: string} $route */
            if (is_integer(strpos($route['name'], '%'))) {
                $format = str_replace('%s', '%[^/]', $route['name']) . '%n';
                $params = (array)sscanf($path, $format);
                $params = array_filter($params);
                $len = intval(array_pop($params));

                return $len === strlen($path) && !empty($params);
            }

            return $path === $route['name'];
        });

        $match = array_keys($routes);

        $route = array_shift($routes);
        /** @var array{name: string}|null $route */
        if (null !== $route && is_integer(strpos($route['name'], '%'))) {
            $format = str_replace('%s', '%[^/]', $route['name']) . '%n';
            $args = (array)sscanf($path, $format);
            $len = intval(array_pop($args));
            $args = $len === strlen($path) && !empty($args) ? $args : array();
        }

        $match = array_shift($match);

        return $match;
    }

    /**
     * @return array<string, string>
     */
    private function routes()
    {
        $d = $this->src . '/Controller/';

        if (is_file($this->cache) && is_dir($d) && filemtime($d) < filemtime($this->cache)) {
            /** @var array<string, mixed> */
            $routes = (array)json_decode($this->cache, true);
            $this->routes = $routes;
        } else {
            $this->routes = array();
        }

        if (empty($this->routes) && is_dir($d)) {
            $routes = array();

            $controllers = scandir($d);

            foreach ($controllers as $controller) {
                if ('.' !== substr($controller, 0, 1) && is_file($d . $controller)) {
                    $parsed = KoncertoAnnotation::parseClass($d . $controller);
                    foreach ($parsed as $classMethod => $annotations) {
                        /** @var array<array-key, mixed> $annotations */
                        if (array_key_exists('route()', $annotations)) {
                            $routes[$classMethod] = $annotations['route()'];
                        }
                    }
                }
            }

            $parsed = KoncertoAnnotation::parseClass(null, 'Koncerto\\KoncertoApiController');
            foreach ($parsed as $classMethod => $annotations) {
                /** @var array<array-key, mixed> $annotations */
                if (array_key_exists('route()', $annotations)) {
                    $routes[$classMethod] = $annotations['route()'];
                }
            }

            $this->routes = $routes;
            file_put_contents($this->cache, json_encode($routes));
        }


        return $this->routes;
    }
}


// src/Koncerto/KoncertoResponse.php

class KoncertoResponse
{
    /** @var string */
    private $content;

    /** @var string */
    private $contentType = 'text/html';

    /**
     * @param string $content
     * @return KoncertoResponse
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

   /**
     * @param string $contentType
     * @return KoncertoResponse
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }
}


// src/Koncerto/KoncertoTbsTemplate.php

class KoncertoTbsTemplate implements KoncertoTemplate
{
    /** @var object */
    private $tbs;

    /** @var Koncerto */
    private $koncerto;

    /**
     * @param Koncerto $koncerto
     */
    public function __construct($koncerto)
    {
        $this->koncerto = $koncerto;

        if (!class_exists('clsTinyButStrong')) {
            throw new \Exception('TinyButStrong is not installed');
        }

        $this->tbs = new \clsTinyButStrong();
    }

    public function render($template, $context = array())
    {
        if (
            !method_exists($this->tbs, 'LoadTemplate') ||
            !method_exists($this->tbs, 'MergeField') ||
            !method_exists($this->tbs, 'MergeBlock') ||
            !method_exists($this->tbs, 'Show') ||
            !defined('TBS_NOTHING') ||
            !property_exists($this->tbs, 'Source')
        ) {
            throw new \Exception('TinyButStrong is not properly installed');
        }

        $this->tbs->LoadTemplate($this->koncerto->getDocumentRoot() . '/' . $template);
        foreach ($context as $k => $v) {
            if (is_array($v)) {
                $this->tbs->MergeBlock($k, 'array', $v);
            } else {
                $this->tbs->MergeField($k, $v);
            }
        }
        $this->tbs->Show(TBS_NOTHING);
        $content = $this->tbs->Source;
        $response = new KoncertoResponse();
        $response->setContent($content);

        return $response;
    }
}


// src/Koncerto/KoncertoTemplate.php

interface KoncertoTemplate
{
    /**
     * @param string $template
     * @param array<string, mixed> $context = array()
     * @return KoncertoResponse
     * @throws \Exception
     */
    public function render($template, $context = array());
}
