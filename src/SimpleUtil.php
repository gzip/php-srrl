<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleUtil
{
    /**
     * 
     * Convenience method to get a value from an array with a default value.
     * 
     * @param (hash) Array to check for $key.
     * @param (string/array) Argument key or array of keys (where first available is returned).
     * @param (string) Default value when key isn't found.
     * @return (mixed) Argument value or default value if not set.
     * @static
    **/
    static public function getValue($args, $key, $default = '', $allowEmpty = true)
    {
        if(is_array($key))
        {
            $def = "\0";
            foreach($key as $k)
            {
                $value = self::getValue($args, $k, $def, $allowEmpty);
                if($value !== $def)
                {
                    return $value;
                }
            }
            return $default;
        }
        return is_array($args) && array_key_exists($key, $args) && ($allowEmpty || $args[$key]) ? $args[$key] : $default;
    }
    
    /**
     * Test if a variable is an object and optionally if it's of a certain class.
     * 
     * @return bool
    **/
    static public function isObject($obj, $class = null)
    {
        return is_object($obj) && is_null($class) || is_a($obj, $class);
    }
    
    /**
     * Get an item by path in an array or object.
     * 
     * @param (array/object) Array or object to check for $path.
     * @param (string) Dot separated path to look for item.
     * @param (mixed) Default value to use if item isn't found by $path.
     * @return (mixed)
    **/
    static public function getItemByPath($node, $path, $default = '')
    {
        if(!is_string($path)){ return $default; }
        
        $isObject = is_object($node);
        $parts = explode('.', $path);
        $depth = count($parts);
        
        if(!$isObject && !is_array($node))
        {
            return $default;
        }
        
        foreach($parts as $index=>$part)
        {
            if(($isObject && isset($node->$part)) || array_key_exists($part, $node))
            {
                $piece = $isObject ? $node->$part : $node[$part];
                if($index + 1 == $depth)
                {
                    $value = $piece;
                }
                else
                {
                    $node  = $piece;
                }
            }
            else
            {
                $value = $default;
                break;
            }
        }
        
        return $value;
    }
    
    /**
     * Set an item by path in an array or object.
     * 
     * @param (array/object) Array or object to set an item by $path.
     * @param (string) Dot separated path used to set item.
     * @param (mixed) Value used to set item.
     * @return (bool) False on error, otherwise true.
    **/
    static public function setItemByPath(&$node, $path, $value)
    {
        $isObject = is_object($node);
        $parts = is_string($path) ? explode('.', $path) : array();
        $depth = count($parts);
        
        if($depth)
        {
            foreach($parts as $index=>$part)
            {
                $isLeaf = $index == $depth-1;
                if($isLeaf)
                {
                    if($isObject)
                    {
                        $node->$part = $value;
                    }
                    else
                    {
                        $node[$part] = $value;
                    }
                }
                else
                {
                    if($isObject)
                    {
                        if(!isset($node->$part))
                        {
                            $node->$part = new stdClass;
                        }
                        $node = &$node->$part;
                    }
                    else
                    {
                        if(!isset($node[$part]))
                        {
                            $node[$part] = array();
                        }
                        $node = &$node[$part];
                    }
                }
            }
        }
        else
        {
            return false;
        }
        
        return true;
    }
    
    /**
     * Return the resource ID.
     * 
     * @return (string) ID.
    **/
    static public function getResourceId($resource)
    {
        $result = null;
        if(is_resource($resource)) // get_resource_type($handle)
        {
            $raw = (string) $resource;
            $result = substr($raw, strrpos($raw, '#') + 1);
        }
        return (int)$result;
    }
    
    /**
     * Return an array.
     * 
     * @return (array) Result.
    **/
    static public function arrayVal($arg)
    {
        return is_array($arg) && is_int(key($arg)) ? $arg : array($arg);
    }
    
    /**
     * Utility function to log an error or variable.
     * 
     * @param (mixed) String to log or variable to dump.
     * @param (bool) False to override dump of variables using print_r.
     * @return (void)
     * @static
    **/
    static public function log($log, $dump = null)
    {
        if(is_null($dump))
        {
            $dump = is_scalar($log) ? false : true;
        }
        
        if(is_bool($log))
        {
            $log = $log ? 'bool(TRUE)' : 'bool(FALSE)';
        }
        
        error_log($dump ? print_r($log, 1) : $log);
    }
}

