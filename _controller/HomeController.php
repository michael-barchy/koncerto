<?php

class HomeController extends KoncertoController
{
    /**
     * @internal {"route":{"name":"/"}}
     * @return KoncertoResponse
     */
    public function index()
    {
        return $this->render('home.tbs.html', [
            'page_title' => 'Welcome'
        ]);
    }
}
