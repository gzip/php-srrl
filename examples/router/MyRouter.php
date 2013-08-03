<?php

require_once 'MyPage.php';

class MyRouter extends SimpleRouter
{
    protected function page($title, $description, $subtemplate = '', $modules = array())
    {
        $page = new MyPage(array(
            'template'     => SRRL_ROOT.'/pages/page.tpl',
            'subtemplates' => array('content'=>$subtemplate),
            'moduleRoot'   => __DIR__.'/../modules',
            'modules'      => $modules,
            'keys' => array(
                'title' => $title,
                'description' => $description
            )
        ));
        
        /*
        $page->addAssets(array(
            array(
                'type'=>'js',
                'url'=>'/SimpleUtil.js'
            )
        ));
        */
        
        return $page->render();
    }
    
    public function index($args)
    {
        print $this->page('Index', 'Index page', '../pages/sub.tpl', array(
            'test-module1'=>array(
                'class'=>'TestYql'//,
                //'params'=>array('table'=>'')
            ),
            'test-module2'=>array(
                'class'=>'TestModule',
                'params'=>array('url'=>'/_inc/optionsValidate.js')
            )
        ));
    }
    
    public function other($args)
    {
        print $this->page('Other', '', 'Hello World!');
    }
}

