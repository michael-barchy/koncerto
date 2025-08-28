<?php

class DocumentationController extends KoncertoController
{
    /**
     * @internal {"route":{"name":"/documentation"}}
     * @return KoncertoResponse
     */
    public function index()
    {
        return $this->render('documentation.tbs.html', [
            'page_title' => 'Documenation'
        ]);
    }
}