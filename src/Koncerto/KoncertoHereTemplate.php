<?php

namespace Koncerto;

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
