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
