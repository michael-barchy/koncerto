<?php

namespace Koncerto;

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
        if (!method_exists($this->tbs, 'LoadTemplate') || !method_exists($this->tbs, 'Show')) {
            throw new \Exception('TinyButStrong is not properly installed');
        }

        $this->tbs->LoadTemplate($template);
        $content = $this->tbs->Show();
        $response = new KoncertoResponse();
        $response->setContent($content);

        return $response;
    }
}
