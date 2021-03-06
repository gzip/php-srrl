<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleClass
{    
    /**
     * @var array
     */
    private $options = array();
    
    /**
     * @var bool
     */
    protected $debug = false;
    
    /**
     * @var bool
     */
    protected $backtrace = false;
    
    /**
     * @var array
     */
    protected $settable = array('debug', 'backtrace');
    
    /**
     * @var array
     */
    protected $pushable = array('gettable', 'pushable', 'settable', 'setters');
    
    /**
     * @var array
     */
    protected $setters = array();
    
    /**
     * @var array
     */
    protected $gettable = array();
    
    /**
     * Constructor.
     * 
     * @param array Options.
    **/
    public function __construct($params = array())
    {
        // seed setters
        $this->setters['setters'] = $this->setSetter('setSetter');
        $this->addSetter('pushable', 'setStringOrArray');
        
        $this->setupParams();
        $this->setParams($params);
        
        $this->setup();
    }
    
    /**
     * Magic method used as getter/setter.
     * 
     * @param string Invoked method name.
     * @param array Arguments passed to the method.
    **/
    public function __call($method, $args)
    {
        $result = null;
        list($param, $action) = $this->parseAction($method);
        //error_log("$action $param");
        switch($action)
        {
            case 'has':
            case 'is':
                $result = (bool) $this->get($param);
            break;
            case 'enable':
            case 'disable':
                if(is_bool($this->get($param)))
                {
                    $result = $this->set($param, $action == 'enable' ? true : false);
                }
            break;
            case 'get':
                $result = $this->get($param);
            break;
            case 'set':
                $value = array_shift($args);
                $result = $this->set($param, $value);
            break;
            case 'add':
            case 'push':
                $value = array_shift($args);
                $key = array_shift($args); // key as optional second arg
                $result = $this->push($param, $value, isset($key) ? $key : null);
            break;
            default:
                $result = $this->handleCallDefault($method, $args);
        }
        return $result;
    }
    
    /**
     * Get the param and action for __call.
    **/
    protected function parseAction($method)
    {
        $pos = 0;
        $len = strlen($method);
        while($pos < $len && 96 < ord($method{$pos}) && ord($method{$pos}) < 123)
        {
            $pos++;
        }
        $action = substr($method, 0, $pos);
        $param = strtolower(substr($method, $pos, 1)).substr($method, $pos+1);
        
        return array($param, $action);
    }
    
    /**
     * Setup.
    **/
    public function setup()
    {
    }
    
    /**
     * Setup parameters.
    **/
    public function setupParams()
    {
    }
    
    /**
     * Handle unknown method.
     * 
     * @param string Method name.
     * @param string Method args.
    **/
    protected function handleCallDefault($method, $args)
    {
        error_log('Unknown method: '.get_class($this).'->'.$method);
        return null;
    }
    
    /**
     * Getter.
     * 
     * @param string Property name.
    **/
    public function get($name)
    {
        return $this->isGettable($name) ? $this->{$name} : null;
    }
    
    /**
     * Setter.
     * 
     * @param string Property name.
    **/
    public function set($name, $value)
    {
        $result = null;
        $value = $this->verifyParameterValue($name, $value);
        if(!is_null($value))
        {
            $this->{$name} = $value;
            $result = $value;
        } else {
            $this->log("Property '$name' is not settable.");
        }
        return $result;
    }
    
    /**
     * Pusher.
     * 
     * @param string Property name.
    **/
    protected function push($name, $value, $key)
    {
        $result = null;
        $name = isset($this->{$name.'s'}) ? $name.'s' : $name;
        if($this->isPushable($name))
        {
            if(!is_array($value) || !isset($value[0]))
            {
                $value = array($value);
            }
            
            if($key && count($value) > 1)
            {
                $key = null;
                $this->log("Pushing to property '$name' but discarding key '$key' passed with array value.");
            }
            
            foreach($value as $k=>$v)
            {
                $result = $this->verifyParameterValue($name, $v);
                if(!is_null($result))
                {
                    if(is_null($key))
                    {
                        $k = is_int($k) ? count($this->{$name}) : $k;
                    }
                    $this->{$name}[$k] = $result;
                }
                else
                {
                    $this->log("Ignoring invalid value pushed to property '$name'.");
                }
            }
        }
        else
        {
            $this->log("Property '$name' is not pushable.");
        }
        
        return $result;
    }
    
    /**
     * Verify a parameter against it's setter. The setter should return null for failure.
     * 
     * @param mixed Property name.
    **/
    protected function verifyParameterValue($param, $value)
    {
        if($this->isSettable($param))
        {
            if(isset($this->setters[$param])){
                $value = call_user_func($this->resolveCallable($this->setters[$param]), $value, $param);
            }
        } else {
            $value = null;
        }
        return $value;
    }
    
    /**
     * Check if a parameter is pushable.
     * 
     * @param string Property name.
    **/
    protected function isPushable($name)
    {
        return in_array($name, $this->pushable);
    }
    
    /**
     * Check if a parameter is gettable.
     * 
     * @param string Property name.
    **/
    protected function isGettable($name)
    {
        return $this->isSettable($name) || in_array($name, $this->gettable);
    }
    
    /**
     * Check if a parameter is settable.
     * 
     * @param string Property name.
    **/
    protected function isSettable($name)
    {
        return in_array($name, $this->settable) || $this->isPushable($name);
    }
    
    /**
     * Setter for setters.
    **/
    protected function setSetter($setter)
    {
        $result = $this->resolveCallable($setter);
        return empty($result) ? null : $result;
    }
    
    /**
     * Setter for booleans.
     * 
     * @param string Property name.
     * @param bool Property value.
    **/
    protected function setBoolean($value)
    {
        return is_bool($value) ? $value : null;
    }
    
    /**
     * Setter for arrays.
     * 
     * @param string Property name.
     * @param array Property value.
    **/
    protected function setArray($value)
    {
        return is_array($value) ? $value : null;
    }
    
    /**
     * Setter for strings.
     * 
     * @param string Property name.
     * @param array Property value.
    **/
    protected function setString($value)
    {
        return is_string($value) ? $value : null;
    }
    
    /**
     * Setter for strings.
     * 
     * @param string Property name.
     * @param array Property value.
    **/
    protected function setStringOrArray($value)
    {
        return $this->setString($value) || $this->setArray($value);
    }
    
    /**
     * Set public parameters.
     * 
     * @param array Array of public parameters, keyed by name.
    **/
    public function setParams($params = array())
    {
        if(is_array($params))
        {
            foreach($params as $key=>$value)
            {
                $this->set($key, $value);
            }
        }
    }
    
    /**
     * Set options as used with getOption.
     * 
     * @param array
     * @see SimpleClass::getOption
    **/
    protected function setOptions($options)
    {
        if(is_array($options))
        {
            $this->options = $options;
        }
    }
    
    /**
     * Convenience wrapper around SimpleUtil::getValue() for $this->options.
     * Meant to be used within methods which accept an array of arguments.
     *
     * Currently public for SimpleRequest::multiQuery
     * 
     * @param string Option name.
     * @param mixed Default value if name is not present.
     * @return bool Whether to allow empty values.
    **/
    protected function getOption($name, $default='', $allowEmpty = true)
    {
        return SimpleUtil::getValue($this->options, $name, $default, $allowEmpty);
    }
    
    /**
     * Determine if a value is callable by call_user_func.
     * 
     * @param mixed Procedural function name, or method name of current object, or array($objectinstance, $methodname), or singleton.
     * @return mixed Function or null on error.
    **/
    protected function resolveCallable($call)
    {
        if(is_string($call))
        {
            // if not a procedural function
            // then assign vars to check for a method in the current object
            if(!is_callable($call)){
                $call = array($this, $call);
            }
        }
        
        if(is_array($call))
        {
            if(
                count($call) == 2 &&
                is_object($call[0]) &&
                is_string($call[1]) &&
                // TODO: above clauses really needed?
                is_callable($call)
            ){
                // call is valid, do nothing
            }
            else
            {
                $call = null;
            }
        }
        
        return $call;
    }
    
    /**
     * Convenience wrapper around SimpleUtil::log.
     * 
     * @param (mixed) String to log or variable to dump.
     * @return (void)
    **/
    protected function log($msg)
    {
        if($this->getBacktrace())
        {
            if(!is_string($msg))
            {
                SimpleUtil::log($msg);
                $msg = '';
            }
            $stack = array_reverse(array_slice(debug_backtrace(), 1));
            foreach($stack as $details)
            {
                $msg .= "\n".$details['class'].$details['type'].$details['function'].'();'.(@$details['line'] ? ' on line '.$details['line'] : '');
            }
        }
        
        SimpleUtil::log($msg);
    }
}

