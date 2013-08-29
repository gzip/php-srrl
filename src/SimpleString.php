<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

/* Mustache.php (https://github.com/bobthecow/mustache.php) is used under
the MIT License (https://github.com/bobthecow/mustache.php/blob/master/LICENSE). */
include_once 'lib/Mustache.php';

class SimpleString
{
    /**
     * Render a template from an object using Mustache.
     * 
     * @param (string) Template.
     * @param (object) Object.
    **/
    static public function renderTemplate($template, $obj, $delims = null, $pragmas = array())
    {
        $m = new Mustache(null, null, null, array('delimiters'=>$delims, 'pragmas'=>$pragmas));
        return $m->render($template, $obj);
    }
    
    /**
     * Add params to a string.
     * 
     * @param (string) String to append to.
     * @param (string) Prefix to add when params aren't empty.
     * @param (string) Separator to use between params.
     * @param (string) Character to use for assignment.
     * @param (string) Function to apply to the parameter values.
     * @return (string) String of params.
    **/
    static public function buildParams($params, $prefix = '', $separator = '&', $assignment = '=', $encode = 'rawurlencode')
    {
        $result = is_string($params) ? trim($params) : '';
        
        if(is_array($params))
        {
            foreach($params as $key=>$value)
            {
                if($encode)
                {
                    $value = is_array($encode) ? call_user_func($encode, $value) : $encode($value);
                }
                // TODO: encode key
                if ($key || $value) {
                    $result .= ($result ? $separator : '') . (is_numeric($key) ? '' : $key) .
                        ($key && $value ? $assignment : '') . $value;
                }
            }
        }
        
        return ($result ? $prefix : '').$result;
    }
    
    /**
     * Wrap a non-empty value with a prefix and suffix.
     * 
     * @param (string) The string to wrap.
     * @param (string) String prefix.
     * @param (string) String suffix.
     * @param (bool) Duplicate prefix and suffix if already present.
     * @return (string) Empty or string wrapped in prefix and suffix.
     * @static
    **/
    static public function wrap($value, $prefix = '', $suffix = '', $dupe = true)
    {
        if(!empty($value))
        {
            return
                ($dupe || substr($value, 0, strlen($prefix)) !== $prefix ? $prefix : '')
                . $value .
                ($dupe || substr($value, -strlen($suffix)) !== $suffix ? $suffix : '');
        }
        return '';
    }
    
    /**
     * Prefix a non-empty value.
     * 
     * @param (string) The string to prepend to.
     * @param (string) String prefix.
     * @param (bool) Duplicate prefix if already present.
     * @return (string) Empty or prefixed string.
     * @static
    **/
    static public function prepend($value, $prefix, $dupe = false)
    {
        return self::wrap($value, $prefix, '', $dupe);
    }
    
    /**
     * Suffix a non-empty value.
     * 
     * @param (string) The string to append to.
     * @param (string) String suffix.
     * @param (bool) Duplicate suffix if already present.
     * @return (string) Empty or suffixed string.
     * @static
    **/
    static public function append($value, $suffix, $dupe = false)
    {
        return self::wrap($value, '', $suffix, $dupe);
    }
    
    /**
     * Trim and remove extraneous space (including tab and newline).
     * 
     * @param (string) Any string.
     * @return (string) String with continuous chunks of whitespace reduced to a single space.
     * @static
    **/
    static public function reduceWhitespace($str)
    {
        return preg_replace('/[\n\r\t ]+/', ' ', trim($str));
    }
    
    /**
     * Escape HTML entities.
     * 
     * @param (string) String to escape.
     * @param (bool) Double encode.
     * @return (string) Escaped string.
     **/
    static public function escape($str, $double = false)
    {
        return htmlentities($str, ENT_QUOTES, 'utf-8', $double);
    }
}

