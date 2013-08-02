<?php

require_once '../../src/includes.php';
require_once 'MyRouter.php';

function dump($args)
{
    global $router;
    print_r($args);
    
    $data = $router->getAccept();
    print_r($data);
    
    $data = $router->getHeader();
    print_r($data);
    
    $data = SimpleHttp::getRequestBody();
    print_r($data);
}

$router = new MyRouter(array('routes'=>array(
    array('route'=>'/', 'function'=>'dump', 'verbs'=>array('POST', 'PUT')),
    array('route'=>'/', 'function'=>'dump', 'verbs'=>array('DELETE', 'HEAD', 'FOO'), 'args'=>array('method'=>'other')),
    array('route'=>'/', 'method'=>'index'),
    array('route'=>'/foo', 'method'=>'index')
)));

$router->addRoutes(array(
    array('route'=>'^/([^/]+)/?$', 'type'=>'regex', 'function'=>'dump', 'args'=>array('foo','bar')),
    array('route'=>'^/bar/([^/]+)/?$', 'type'=>'regex', 'function'=>function($path, $args){ error_log('lambda!'); }),
    array('route'=>'/examples/(.+)/?', 'matchKeys'=>array('page'), 'type'=>'regex', 'function'=>'dump')
));

//print_r($router->getRoutes());

$router->route();

