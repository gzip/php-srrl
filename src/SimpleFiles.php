<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleFiles
{
    function __construct()
    {
    
    }
    
    static function readDir($dir, $regex = '', $recursive = true)
    {
        $files = array();
        $handle = opendir($dir);
        
        if($handle)
        {
            $files = array();
            while(($file = readdir($handle)) !== false)
            {
                if($file == '.' || $file == '..')
                {
                    continue;
                }
                
                // prepend dir
                $file = self::addSlash($dir).$file;
                
                if(is_dir($file))
                {
                    if($recursive)
                    {
                        $files = array_merge($files, self::readDir($file, $regex, $recursive));
                    }
                }
                else
                {
                    if(empty($regex))
                    {
                        $include = true;
                    } else {
                        $include = preg_match($regex, $file);
                    }
                    if($include){ $files[] = realpath($file); }
                }
            }
    
            closedir($handle);
        }
        
        return $files;
    }
    
    static function checkDir($dir, $create = true, $restrict = null)
    {
        if(is_dir($dir))
        {
            if(!is_null($restrict) && strpos(realpath($dir), $restrict) !== 0){
                SimpleUtil::log("$dir is not located under $restrict.");
                return false;
            }
            if(is_writable($dir)){
                return true;
            } else {
                SimpleUtil::log("$dir exists but is not writable.");
                return 0;
            }
        } else if($create && mkdir($dir, 0777, true)){
            return true;
        } else {
            SimpleUtil::log("$dir is not a directory and could not be created.");
            return false;
        }
    }
    
    /**
     * Include a file and return the contents.
     * 
     * @param string Path to file.
     * @param array Hash of vars to set prior to inclusion.
     * @return string Result of file execution.
    **/
    static function executeFile($path, $vars = array())
    {
        ob_start();
        
        // populate any expected vars
        foreach($vars as $key=>$value)
        {
            $$key = $value;
        }
        
        @include $path;
        $result = ob_get_contents();
        ob_end_clean();
        
        return $result;
    }
    
    /**
     * Read a file.
     * 
     * @param string Path to file.
     * @param array Options for read.
     * @return string/handle.
    **/
    static function readFile($path, $options = null)
    {
        // TODO: support file() and fopen() via $options['method'] = 'file'
        return file_get_contents($path);
    }
    
    /**
     * Write a file with optional content and mask.
     * 
     * @param string Path to file.
     * @param string File contents.
     * @param int Optional file mask.
     * @param const See file_put_contens $flags.
     * @return bool True on success.
    **/
    static function writeFile($path, $content = '', $mask = null, $flags = 0)
    {
        $dir = dirname($path);
        $result = true === self::checkDir(self::addSlash($dir), true, $_SERVER['DOCUMENT_ROOT']) ? true : false;
        
        if($result)
        {
            $result = false === file_put_contents($path, $content, $flags) ? false : true;
            if($result && $mask)
            {
                $result = chmod($path, $mask);
            }
        }
        
        return $result;
    }
    
    static function addSlash($path)
    {
        return substr($path, -1) === '/' ? $path : $path.'/';
    }
}

