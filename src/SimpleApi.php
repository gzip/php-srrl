<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleApi extends SimpleRouter
{
    protected $db;
    protected $routes = null;
    // TODO: should this really be configurable? it's an annoyance in YQL
    protected $resource = 'resource';
    protected $resources = 'resources';
    protected $sqlOptions = array();
    private $route = null;
    
    public function setupParams()
    {
        parent::setupParams();
        $this->addSettable(array('routes', 'resource', 'resources', 'sqlOptions'));
        $this->addGettable(array('db'));
    }
    
    function setup()
    {
        // TODO: error checking? (can set after construct) sqlOptions; resource || routes
        $this->db = new SimpleSql($this->sqlOptions);
        // TODO: setup routes based on $resource
    }
    
    function routes($args)
    {
        $routes = $this->getRoutes();
        $resp = array('endpoints'=>array());
        foreach($routes as $route)
        {
            array_unshift($resp['endpoints'], array(
                // TODO: SimpleHttp::getHost
                'uri'=>'http://'.$_SERVER['HTTP_HOST'].'/examples'.$route['route'],
                'methods'=>SimpleUtil::getValue($route, 'verbs', array('GET'))
            ));
        }
        
        $this->respond($resp);
    }
    
    protected function getBody()
    {
        return SimpleHttp::getRequestBody();
    }
    
    function resources($args)
    {
        if($args['verb'] === 'GET')
        {
            // TODO Move to sql->prepare
            //$this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
            $limit = min(100, intval(SimpleUtil::getValue($_GET, 'count', 10)));
            $page = min(1, intval(SimpleUtil::getValue($_GET, 'page', 1)));
            $statement = $this->db->select(array('limit'=>$limit, 'prepare'=>true));
            // TODO: Allow search, $this->getQuery() return 'name LIKE :name', array('name'=>SimpleUtil::getValue($_GET,'name'))
            $statement->execute(array(':limit'=>$limit));
            
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            if($result)
            {
                $resp = array(
                    'count' => count($result),
                    $this->resources => $result,
                    'meta' => array(
                        'links' => array(
                            array(
                                'rel' => 'next',
                                'href' =>
                                    (SimpleHttp::isSsl() ? 'https' : 'http') . '://'.
                                    SimpleUtil::getValue($_SERVER, 'HTTP_HOST').
                                    $this->getPath().
                                    SimpleString::buildParams(array(
                                        'page' => $page+1,
                                        'count' => $limit
                                    ), '?')
                            )
                        )
                    )
                );
            }
            else
            {
                $resp = array('count'=>0, 'results'=>array());
                //$resp = $this->getErrorResp(404);
            }
        }
        else
        {
            $data = $this->getBody();
            if(SimpleUtil::isArray($data))
            {
                $resp = array('count'=>count($data), 'results'=>array());
                foreach($data as $resource)
                {
                    // TODO: Test POST
                    $id = SimpleUtil::getValue($resource, 'id'); unset($resource['id']);
                    $resp['results'][] = $this->resource(array_merge($args, array('id'=>$id, 'body'=>$resource)), false);
                }
            }
            else
            {
                $resp = $this->getErrorResp(400, 'Expected an array of objects.');
            }
        }
        
        $this->respond($resp);
    }
    
    function resource($args, $respond = true)
    {
        switch($args['verb'])
        {
            case 'GET':
                $resp = $this->handleResource('select', array('where'=>'id='.$args['id']), $args);
                $this->setAccessControlHeaders();
            break;
            case 'POST':
                $resp = $this->handleResource('insert', array(
                    'fields' => SimpleUtil::getValue($args, 'body', $this->getBody())
                ));
            break;
            case 'PUT':
                $resp = $this->handleResource('update', array(
                    'fields' => SimpleUtil::getValue($args, 'body', $this->getBody()),
                    'where'  => 'id='.$args['id']
                ));
            break;
            case 'DELETE':
                $resp = $this->handleResource('delete', array('where'=>'id='.$args['id']));
            break;
            case 'OPTIONS':
                $methods = $args['verbs'];
                $resp = array('methods'=>$methods);
                $this->setAccessControlHeaders(false, $methods);
            break;
        }
        
        if($respond)
        {
            $this->respond($resp);
        }
        else
        {
            return $resp;
        }
    }
    
    protected function handleResource($action, $options, $routeArgs)
    {
        $result = $this->db->{$action}($options);
        
        if(is_int($result) || $result === false)
        {
            $resp = array('result'=>array('rows'=>$result));
        }
        
        switch($action)
        {
            case 'select':
                if($result)
                {
                    $resp = array($resource => $result->fetch(PDO::FETCH_ASSOC));
                }
                if(empty($resp))
                {
                    $resp = $this->getErrorResp(404);
                }
            break;
            case 'insert':
                if($result)
                {
                    $id = $this->db->getLastInsertId();
                    SimpleHttp::location(SimpleString::wrap($routeArgs['path'], '', '/', false) . $id);
                    SimpleHttp::success(201);
                    $resp = $this->handleResource('select', array('where'=>'id='.$id), $routeArgs);
                }
                else
                {
                    $resp = $this->getErrorResp(400);
                }
            break;
            case 'update':
                if($result)
                {
                    SimpleHttp::success(202);
                    $resp = $this->handleResource('select', array('where'=>$options['where']), $routeArgs);
                }
                else
                {
                    $resp = $this->getErrorResp(404);
                }
            break;
            case 'delete':
                if($result)
                {
                    SimpleHttp::success(204);
                    $resp = null;
                }
                else
                {
                    $resp = $this->getErrorResp(404);
                }
            break;
        }
        
        return $resp;
    }
    
    protected function setAccessControlHeaders($simple = true, $methods = null)
    {
        SimpleHttp::setAccessControl('Domain', '*');
        if(!$simple)
        {
            SimpleHttp::setAccessControl(count($methods) > 1 ? 'Methods' : 'Method', $methods);
            SimpleHttp::setAccessControl('Headers', array('Content-Type'));
            SimpleHttp::setAccessControl('Mex-Age', 86400);
        }
    }
    
    protected function getErrorResp($code, $detail = '')
    {
        SimpleHttp::error($code, null, false);
        return array('error'=>array('msg'=>SimpleHttp::getErrorMessage($code), 'code'=>$code, 'detail'=>$detail));
    }
    
    protected function respond($resp)
    {
        SimpleHttp::setContentType('json');
        // TODO: support callback param
        // TODO: vary based on Accept, example only?
        print json_encode($resp);
    }
}

