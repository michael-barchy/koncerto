<?php

namespace Koncerto;

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

        if ('/' !== substr($this->pathName, -1)) {
            $this->pathName .= '/';
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
     * @return string
     */
    public function getPathInfo()
    {
        return $this->pathName;
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
        $d = $this->src . '/Controller/';

        if (is_file($this->cache) && filemtime($d) < filemtime($this->cache)) {
            /** @var array<string, mixed> */
            $routes = (array)json_decode($this->cache, true);
            $this->routes = $routes;
        } else {
            $this->routes = array();
        }

        if (empty($this->routes)) {
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
