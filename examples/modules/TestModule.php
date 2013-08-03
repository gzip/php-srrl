<?php

class TestModule extends SimpleModule
{
    protected $url = '/common/newWin.js';
    
    public function setupParams()
    {
        parent::setupParams();
        $this->addSettable('url');
    }
    
    public function getData()
    {
        $req = new SimpleRequest(array('host'=>'rtb.local', /*'debug'=>array('url', 'content_type', 'http_code')*/));
        $req->setupHandle($this->url);
        return $req;
    }
    
    public function render($data)
    {
        $html = $this->html;
        return  $html->pre($data).
                $html->form(
                       $html->input('text', 'foo').
                       $html->input('submit', 'submit', 'go'),
                   null, array('method'=>'POST'));
    }
    
    public function getAssets()
    {
        return array(
            array(
                'type'=>'css',
                'url'=>'/style.css'
            )
        );
    }
}

