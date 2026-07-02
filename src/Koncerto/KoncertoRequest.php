<?php

namespace Koncerto;

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

        $hasQueryString = array_key_exists('QUERY_STRING', $_SERVER);
        $queryString = $hasQueryString ? $_SERVER['QUERY_STRING'] : null;

        $this->pathName = is_string($pathInfo) ? $pathInfo : '';
        $this->pathName = is_string($requestUri) ? $requestUri : $this->pathName;

        if (!empty($queryString) && is_string($queryString)) {
            $this->pathName = str_replace('?' . $queryString, '', $this->pathName);
        }

        if ('/' !== substr($this->pathName, -1)) {
            $this->pathName .= '/';
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
