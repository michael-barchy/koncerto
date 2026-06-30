<?php

namespace Koncerto;

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
