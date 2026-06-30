<?php

namespace Koncerto;

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
