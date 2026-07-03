<?php

namespace Koncerto;

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
