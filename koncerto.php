<?php

require_once('tinybutstrong/tbs_class.php');

/**
 * koncerto framework
 * requires PHP>=5.3
 */

if ('cli' === php_sapi_name()) {
    Koncerto::cli($argv);
} else {
    Koncerto::response();
}

class Koncerto
{
    /** @var ?KoncertoRequest */
    private static $request = null;

    /** @var KoncertoController[]|null */
    private static $controllers = null;

    /**
     * Run commands
     * @param string[] args
     * @return void
     */
    public static function cli($args)
    {
        if ('serve' === $args[1]) {
            $port = '8080';
            if (isset($args[2])) {
                $port = $args[2];
            }
            exec(sprintf(
                'php -S localhost:%s koncerto.php &',
                $port
            ));
        }
    }

    /**
     * Get information about the current request
     * @return KoncertoRequest
     */
    public static function request()
    {
        if (null === static::$request) {
            static::$request = new KoncertoRequest();
        }

        if (null === static::$controllers) {
            static::$controllers = static::loadControllers();
        }

        return static::$request;
    }

    /**
     * @return KoncertorController[]
     */
    private static function loadControllers()
    {
        $controllers = array();
        $controllerPath = '_controller/';
        $controllerDir = \opendir($controllerPath);

        while ($controllerFile = \readdir($controllerDir)) {
            if (\is_file($controllerPath . $controllerFile) && '.php' === \strrchr($controllerFile, '.')) {
                require_once($controllerPath . $controllerFile);
                $controllerClass = str_replace('.php', '', $controllerFile);
                if (!class_exists($controllerClass)) {
                    throw new Exception(sprintf(
                        'Controller file %s does not include controller class %s',
                        $controllerPath . $controllerFile,
                        $controllerClass
                    ));
                }
                \array_push($controllers, new $controllerClass());
            }
        }

        return $controllers;
    }

    /**
     * @return KoncertoControllers[]|null
     */
    public static function getControllers()
    {
        return static::$controllers;
    }

    /**
     * Treat request and send response
     * @return void
     */
    public static function response()
    {
        $response = static::request()->getResponse();

        $headers = $response->getHeaders();
        foreach ($headers as $headerKey => $headerValue) {
            \header(\sprintf('%s: %s', $headerKey, $headerValue));
        }

        $content = $response->getContent();

        if (null !== $content) {
            echo $content;
        }
    }

    /**
     * @return clsTinyButStrong
     */
    public static function tbs()
    {
        $tbs = new clsTinyButStrong();
        $tbs->ObjectRef = array();
        $tbs->ObjectRef['app'] = new Koncerto();
        $tbs->ObjectRef['form'] = new KoncertoForm();
        $tbs->MethodsAllowed = true;

        return $tbs;
    }
}

class KoncertoRequest
{
    /**
     * @return KoncertoResponse
     */
    public function getResponse()
    {
        $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $_SERVER['SCRIPT_NAME'];
        $router = new KoncertoRouter();
        list($controller, $controllerAction) = $router->match($pathInfo);

        return $controller->$controllerAction();
    }
}

class KoncertoResponse
{
    /** var array<string, string> */
    private $headers = array();
    /** var mixed */
    private $content = null;

    /**
     * @return arrray<string, string>
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array<string, string> $headers
     * @return KoncertoResponse
     */
    public function setHeaders($headers)
    {
        $this->headers = \array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * @return ?string
     */
    public function getContent()
    {
        if (is_string($this->content)) {
            return $this->content;
        }

        return null;
    }

    /**
     * @param mixed $content
     * @return KoncertoResponse
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }
}

class KoncertoRouter
{
    /** @var array<string, array{controller: KoncertoController, controllerAction: string}> */
    private $cache = array();

    /**
     * @param string $url
     * @return array{KoncertoController, string}
     */
    public function match($url)
    {
        if (isset($this->cache[$url])) {
            return array(
                $this->cache[$url]['controller'],
                $this->cache[$url]['controllerAction']
            );
        }

        $controllers = Koncerto::getControllers();
        foreach ($controllers as $controller) {
            $controllerClass = \get_class($controller);
            $rc = new \ReflectionClass($controllerClass);
            $methods = $rc->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                $doc = $method->getDocComment();
                $lines = \explode("\n", $doc);
                foreach ($lines as $line) {
                    $line = \trim(\preg_replace("/\r\t/", ' ', $line));
                    $line = \trim(\preg_replace("/\* /", ' ', $line));
                    $tokens = explode(' ', $line);
                    if ('@internal' === $tokens[0] && \count($tokens) > 1) {
                        \array_shift($tokens);
                        $json = \implode(' ', $tokens);
                        $internal = \json_decode($json, true, 3, JSON_THROW_ON_ERROR);
                        if (isset($internal['route'])) {
                            $route = $internal['route'];
                            if (isset($route['name'])) {
                                $this->cache[$route['name']] = array(
                                    'controller' => $controller,
                                    'controllerAction' => $method->getName()
                                );
                                if ($url === $route['name']) {
                                    return array($controller, $method->getName());
                                }
                            }
                        }
                    }
                }
            }
        }

        throw new Exception(sprintf('No controller found for route %s', $url));
    }
}

class KoncertoController
{
    /**
     * @param string $templatePath
     * @param array<string, mixed> $context
     * @param array<string, string>|null $headers
     * @return KoncertoResponse
     */
    public function render($templatePath, $context = array(), $headers = null)
    {
        $templateFile = sprintf('_templates/%s', $templatePath);
        if (!\is_file($templateFile)) {
            throw new Exception(sprintf('Template %s not found', $templateFile));
        }

        $tbs = Koncerto::tbs();
        $tbs->VarRef = $context;
        $tbs->LoadTemplate($templateFile);

        $response = new KoncertoResponse();

        if (null !== $headers) {
            $response->setHeaders(($headers));
        }

        return $response->setContent($tbs->Show());
    }
}

class KoncertoForm
{
    /**
     * @param string $name
     * @return string
     */
    public function row($name)
    {
        $args = \func_get_args();
        \array_shift($args);

        $json = \implode(', ', $args);
        $args = \json_decode($json, true);
        $args['name'] = $name;

        $tbs = Koncerto::tbs();
        $tbs->Source = '[onload;file=_templates/_form.tbs.html;getpart=row]';
        $tbs->LoadTemplate(null);
        $tbs->MergeField('row', $args);
        foreach ($args as $key => $arg) {
            if (is_array($arg)) {
                $tbs->MergeBlock($key, 'array', $arg);
            }
        }
        $tbs->Show(TBS_NOTHING);

        return $tbs->Source;
    }
}
