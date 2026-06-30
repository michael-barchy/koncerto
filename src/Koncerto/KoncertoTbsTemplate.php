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
