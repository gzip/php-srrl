<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleRouter extends SimpleClass
{
    /**
     * @var array
     */
    protected $routes = array();

    /**
     * @var string
     */
    protected $verb = 'GET';

    /**
     * Called by SimpleClass::__construct prior to setParams.
     *
     * @return void
     **/
    public function setupParams()
    {
        $this->addPushable('routes');
        $this->addSetter('routes', 'setRoute');
    }

    protected function setRoute($route)
    {
        return is_array($route) && isset($route['route']) ? $route : null;
    }

    public function addRoute($route)
    {
        $this->setOptions($route);
        $routeVal = $this->getOption('route');
        $type = $this->getOption('type', 'static');

        // handle /:foo/:bar syntax
        if($type === 'static' && strpos($routeVal, ':'))
        {
            preg_match_all('|/:([^/]+)|', $routeVal, $matches, PREG_SET_ORDER);
            if($matches)
            {
                $matchKeys = array();
                foreach($matches as $match)
                {
                    $routeVal = str_replace('/:'.$match[1], '/([^/]+)', $routeVal);
                    $matchKeys[] = $match[1];
                }

                $route['type'] = 'regex';
                $route['route'] = $routeVal;
                $route['matchKeys'] = $matchKeys;
            }
        }

        $this->pushRoute($route);
    }

    public function addRoutes($routes)
    {
        if(is_array($routes))
        {
            foreach($routes as $route){
                $this->addRoute($route);
            }
        }
    }

    public function route($path = null)
    {
        $this->verb = $_SERVER['REQUEST_METHOD'];

        if(is_null($path)){
            $path = $this->getPath();
        }

        $found = false;
        $accepts = null;
        foreach($this->routes as $route)
        {
            $this->setOptions($route);
            $type = $this->getOption('type', 'static');
            $value = $this->getOption('route', null);

            $verbs = $this->getOption('verbs', array($this->getOption('verb', 'GET')));
            $accepted = $verbs === '*' || in_array($this->verb, $verbs);
            $error = $accepted ? null : 405;

            if ($error && $this->getOption('continue', false)) {
                continue;
            }

            $accept = SimpleUtil::arrayVal($this->getOption('accept'));
            if($accept && $accepted)
            {
                if(is_null($accepts))
                {
                    $accepts = SimpleHttp::getAccept();
                }
                $accepted = count(array_diff($accept, $accepts));
                $error = $accepted ? null : 406;
            }

            $args = array_merge(
                $this->getOption('args', array()),
                array('verb'=>$this->verb, 'verbs'=>$verbs, 'route'=>$value, 'path'=>$path)
            );

            switch($type)
            {
                case 'static':
                    if($path === $value)
                    {
                        $found = true;
                    }
                break;
                case 'regex':
                    preg_match('#'.$value.'#', $path, $matches);
                    if($matches)
                    {
                        if(count($matches) > 1)
                        {
                            array_shift($matches);
                            $keys = $this->getOption('matchKeys');
                            $matches = $keys ? array_combine($keys, $matches) : $matches;
                            $args = array_merge($args, $matches);
                        }
                        $found = true;
                    }
                break;
                default:
                    $this->log('Unknown route type "'.$type.'".');
                break;
            }

            if($found)
            {
                return $error ? SimpleHttp::error($error) : $this->handleRoute($route, $args);
            }
        }

        return false;
    }

    public function handleRoute($route, $args = array())
    {
        $this->setOptions($route);
        $method = $this->getOption('method');
        $function = $this->getOption('function');
        if($method)
        {
            $obj = null;

            if( is_array($method) && 2 === count($method) &&
                is_object($method[0]) && is_string($method[1])){
                list($obj, $method) = $method;
            } else if (is_string($method)) {
                $obj = $this;
            }

            if($obj && is_callable( array($obj, $method) )){
                return $obj->$method($args);
            } else {
                $this->log('Unable to route "'.$path
                    .'" using uncallable method "'.$method
                    .'" in object of type "'.get_class($obj).'".'
                );
            }
        }
        else if($function)
        {
            if(is_callable($function)){
                return $function($args);
            } else {
                $this->log('Unable to route "'.$path.'" using uncallable function "'.$function.'".');
            }
        } else {
            $this->log('Unable to route "'.$path.'".');
        }
    }

    public function getPath()
    {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }
}

