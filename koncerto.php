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

        if (is_string($config) && '.ini' === strrchr($config, '.')) {
            $config = (array)parse_ini_file($config, true);
        }

        if (is_string($config) && '.json' === strrchr($config, '.')) {
            $json = (string)file_get_contents($config);
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
     * @return void
     */
    private function autoload()
    {
        /** @var array<string, string> */
        $autoload = array_key_exists('autoload', $this->config) ? (array)$this->config['autoload'] : array();
        $autoload = array_flip($autoload);
        spl_autoload_register(function ($class) use ($autoload) {
            if (class_exists($class, false)) {
                return;
            }

            $mapping = array_filter($autoload, function ($prefix) use ($class) {
                return 0 === strpos($class, $prefix);
            });
            $prefix = array_values($mapping);
            $prefix = $prefix[0];
            $mapping = array_flip($mapping);
            $src = array_shift($mapping);
            if (null === $src) {
                return;
            }

            $hasScript = array_key_exists('SCRIPT_FILENAME', $_SERVER);
            $script = $hasScript && is_string($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : __FILE__;
            $root = dirname($script);
            $classFile = sprintf(
                '%s/%s/%s',
                $root,
                $src,
                preg_replace('/\\\/', '/', str_replace($prefix, '', $class)) . '.php'
            );
            $classPath = realpath($classFile);
            if (false !== $classPath && is_file($classPath)) {
                require_once($classPath);
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

        $match = $request->match();
        if (null === $match) {
            return null;
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

        $o = new $class();
        $response = $o->$method();

        header('Content-type: ' . $response->getContentType());

        return $response->getContent();
    }
}




use ReflectionClass;

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
     * @param string $file
     * @return array<string, mixed>
     */
    public static function parseClass($file)
    {
        $nameSpace = basename(dirname($file));
        $className = 'App\\' . $nameSpace . '\\' . str_replace('.php', '', basename($file));

        include_once($file);

        $parsed = array();

        if (class_exists($className, false)) {
            $ref = new ReflectionClass($className);
            $methods = $ref->getMethods();
            foreach ($methods as $method) {
                $key = $className . '::' . $method->getName();
                $parsed[$key] = KoncertoAnnotation::parseAnnotation((string)$method->getDocComment());
            }
        }

        return $parsed;
    }

    /**
     * @param string $comment
     * @return array<string, mixed>
     */
    public static function parseAnnotation($comment)
    {
        $parsed = array();
        $lines = explode('\n', $comment);
        foreach ($lines as $line) {
            $line = trim($line);
            $see = array();
            if (preg_match('/@see ([^ ]*) (.*)/', $line, $see)) {
                $parts = explode('::', $see[1]);
                $methodName = array_pop($parts);
                $parsed[$methodName] = (array)json_decode($see[2], true);
            }
        }

        return $parsed;
    }
}




class KoncertoController
{
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
}




class KoncertoRequest
{
    /** @var string */
    private $src = '';

    /** @var string */
    private $pathName = '';

    /** @var array<string, mixed> */
    private $routes = array();

    /**
     * @param Koncerto $koncerto
     */
    public function __construct($koncerto)
    {
        $hasPathInfo = array_key_exists('PATH_INFO', $_SERVER);
        $pathInfo = $hasPathInfo ? $_SERVER['PATH_INFO'] : null;

        $hasRequestUri = array_key_exists('REQUEST_URI', $_SERVER);
        $requestUri = $hasRequestUri ? $_SERVER['REQUEST_URI'] : null;

        $this->pathName = is_string($pathInfo) ? $pathInfo : '';
        $this->pathName = is_string($requestUri) ? $requestUri : $this->pathName;

        if ('/' !== substr($this->pathName, -1)) {
            $this->pathName .= '/';
        }

        /** @var string */
        $root = $_SERVER['DOCUMENT_ROOT'];

        /** @var string */
        $documentRoot = $koncerto->getConfig('documentRoot');

        $path = str_replace($root, '', $documentRoot);

        if (0 === strpos($this->pathName, $path)) {
            $this->pathName = substr($this->pathName, strlen($path));
        }

        /** @var array<string, string> */
        $autoload = $koncerto->getConfig('autoload');

        /** @var string $src */
        $src = $autoload['App\\'];
        $this->src = $documentRoot . '/' . $src;
    }

    /**
     * Match request to route
     * @return ?string
     */
    public function match()
    {
        $path = $this->pathName;

        $match = array_filter($this->routes(), function ($route) use ($path) {
            /** @var array{name: string} $route */
            return $path === $route['name'];
        });

        $match = array_keys($match);
        $match = array_shift($match);

        return $match;
    }

    /**
     * @return array<string, string>
     */
    private function routes()
    {
        if (empty($this->routes)) {
            $routes = array();

            $d = $this->src . '/Controller/';
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

            $this->routes = $routes;
        }

        return $this->routes;
    }
}




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
