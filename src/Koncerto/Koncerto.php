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

            $classPath = 'phar://' === substr($classFile, 0, 7) ? $classFile : realpath($classFile);
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
