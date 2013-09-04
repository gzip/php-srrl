<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleApi extends SimpleRouter
{
    protected $db;
    protected $routes = array();
    protected $root = '/api';
    protected $sqlOptions = array();
    protected $primaryKey = 'id';
    private $route = null;
    
    public function setupParams()
    {
        parent::setupParams();
        $this->addSettable(array('sqlOptions', 'root'));
        $this->addPushable(array('routes'));
        $this->addGettable(array('db'));
    }
    
    function setup()
    {
        // TODO: error checking?
        $this->db = new SimpleSql($this->sqlOptions);
    }
    
    public function addResource($opts)
    {
        $name = SimpleUtil::getValue($opts, 'name');
        $plural = SimpleUtil::getValue($opts, 'plural', SimpleString::append($name, 's'));
        $idRegex = SimpleUtil::getValue($opts, 'idRegex', '([0-9]+)');
        $root = SimpleString::append($this->getRoot(), '/');
        
        $this->addRoute(array(
            array('route'=>$root . $plural, 'method'=>'resources', 'args'=>array('resource'=>$name),
                'verbs'=>array('GET', 'POST', 'PUT', 'DELETE', 'OPTIONS')),
            array('route'=>$root . $name . '/' . $idRegex, 'type'=>'regex', 'matchKeys'=>array('id'),
                  'regex'=>$idRegex,
                  'method'=>'resource', 'verbs'=>array('GET', 'PUT', 'DELETE', 'OPTIONS')),
            array('route'=>$root . $name, 'method'=>'resource', 'verbs'=>array('POST', 'OPTIONS')),
            array('route'=>$root, 'method'=>'routes')
        ));
    }
    
    // TODO change method name
    function routes($args)
    {
        $routes = $this->getRoutes();
        $resp = array('resources'=>array());
        foreach($routes as $route)
        {
            array_unshift($resp['resources'], array(
                'uri'=>SimpleHttp::getProtocol() .SimpleHttp::getHost() . $route['route'],
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
            $fields = SimpleUtil::getValue($_GET, 'fields');
            $fields = $fields ? explode(',', $fields) : null;
            $statement = $this->db->select(array('limit'=>$limit, 'prepare'=>true));
            // TODO: Allow search, $this->getQuery() return 'name LIKE :name', array('name'=>SimpleUtil::getValue($_GET,'name'))
            
            // execute sql and fetch results
            $statement->execute(array(':limit'=>$limit));
            $results = $statement->fetchAll(PDO::FETCH_ASSOC);
            
            $res = SimpleUtil::getValue($args, 'resource');
            $root = SimpleString::append($this->getRoot(), '/');
            foreach($results as &$result)
            {
                // supplement with uri if resource is known
                if($res) {
                    $id = SimpleUtil::getValue($result, $this->primaryKey);
                    if($id) {
                        // TODO $this->getUriBase()?
                        $result['uri'] = SimpleHttp::getProtocol() . SimpleHttp::getHost() .
                            $root . $res . '/' . $id;
                    }
                }
                
                // include specific fields if requested
                if(!empty($fields)) {
                    // TODO SimpleUtil::filterByKey($ar, $fields)
                    $result = array_intersect_key($result, array_fill_keys($fields, null));
                }
            }
            
            if($results)
            {
                $resp = array(
                    'count' => count($results),
                    'resources' => $results,
                    'meta' => array(
                        'links' => array(
                            array(
                                'rel' => 'next',
                                'href' =>
                                    SimpleHttp::getProtocol().
                                    SimpleHttp::getHost().
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
                    $resp['results'][] = $this->resource(array_merge($args, array($this->primaryKey=>$id, 'body'=>$resource)), false);
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
        $whereClause = $this->primaryKey.'='.$args['id'];
        switch($args['verb'])
        {
            case 'GET':
                $resp = $this->handleResource('select', array('where'=>$whereClause), $args);
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
                    'where'  => $whereClause
                ));
            break;
            case 'DELETE':
                $resp = $this->handleResource('delete', array('where'=>$whereClause));
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
                    $resp = array('resource' => $result->fetch(PDO::FETCH_ASSOC));
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
                    $resp = $this->handleResource('select', array('where'=>$this->primaryKey.'='.$id), $routeArgs);
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
            SimpleHttp::setAccessControl('Max-Age', 86400);
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

