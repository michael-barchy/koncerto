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
        $autoload = array_flip($autoload);
        $root = $this->getDocumentRoot();
        spl_autoload_register(function ($class) use ($autoload, $root) {
            if (class_exists($class, false)) {
                return;
            }

            $mapping = array_filter($autoload, function ($prefix) use ($class) {
                return 0 === strpos($class, $prefix);
            });

            $prefix = array_values($mapping);
            $prefix = array_shift($prefix);
            if (null === $prefix) {
                $prefix = '';
            }

            $mapping = array_flip($mapping);
            $src = array_shift($mapping);
            if (null === $src) {
                return;
            }

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

        $match = $request->match();

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
        $response = $o->$method();

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
        $file = '.' . $request->getPathInfo();
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
            'application\/json'
        );
        $contentType = mime_content_type($file);
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

        $e = new $engine();
        if (!$e instanceof KoncertoTemplate) {
            throw new \Exception('Invalid template engine');
        }

        return $e->render($template, $context);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getConfig($key)
    {
        $this->koncerto->getConfig($key);
    }

    /**
     * @return KoncertoRequest
     */
    public function getRequest()
    {
        return $this->koncerto->request();
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
        if (null == $js || !is_string($js)) {
            $js = '/impulsus/impulsus.js';
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
                "controller.targets[%s].set(%s);",
                json_encode($prop['name']),
                json_encode($this->{$propName})
            ));
        }
        $targetsJS = implode("\r\n            ", $targets);

        return $targetsJS;
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
                controller.on(%s, function () {
                    var state = {};
                    for (var key in controller.targets) {
                        state[key] = controller.targets[key].get();
                    }
                    impulsus.xhr(location.href, function (response) {
                        var state = JSON.parse(response);
                        if ('object' === typeof state) {
                            for (var key in state) {
                                if (key in controller.targets) {
                                    controller.targets[key].set(state[key]);
                                }
                            }
                        }
                    }, 'POST', %s + JSON.stringify(state), 'application/x-www-form-urlencoded');
                });
JS
                ,
                json_encode($action['name']),
                json_encode('_action=' . $actionName . '&_live=')
            ));
        }
        $eventsJS = implode("\r\n            ", $events);

        return $eventsJS;
    }
}


// src/Koncerto/KoncertoRequest.php

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
        $root = $_SERVER['DOCUMENT_ROOT'];

        /** @var string */
        $documentRoot = $koncerto->getDocumentRoot();

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

    public function __construct()
    {
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

        $this->tbs->LoadTemplate($template);
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
