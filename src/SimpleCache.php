<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

require_once 'SimpleFiles.php';

/** A general cache which uses the filesystem, APC, or Memcache.
    Example usage:

    $cache = new SimpleCache('uniqueName');

    // store values directly
    $cache->putVar('myVar', 'some value');
    print $cache->getVar('myVar');

    // or use start/end to wrap output
    if($cached = $cache->get()){
        print $cached;
    } else {
        $cache->start();
        // print some content
        $cache->end();
    }

    NOTE: Only file has been tested. Apc and Memcache are more than likely buggy.
  **/

class SimpleCache
{
    protected $type     = '';
    protected $name     = '';
    protected $enabled  = true;
    protected $clearKey = 'cc';
    protected $fileMask = 0646;
    protected $config  = array();
    protected $ttl      = 0;
    public    $memcache = null;

    public function __construct($name, $config = array())
    {
        $this->setName($name);
        $this->type = is_array($config) && isset($config['type']) ? $config['type'] : 'file';
        switch($this->type){
            case 'file':
                $this->initFileCache($config);
            break;
            case 'memcache':
                $this->initMemCache($config);
                $this->config['prefix'] = '';
            break;
            case 'apc':
                $this->config['prefix'] = '';
            break;
            default :
                $this->log("Type '{$config['type']}' is invalid, should be one of 'file', 'memcache', or 'apc'.");
                $this->enabled = false;
                return false;
        }
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    protected function getName($name = '')
    {
        return $this->config['prefix'].($name ? $name : $this->name).'.simple_cache';
    }

    public function put($contents)
    {
        return $this->putCache($this->getName(), $contents);
    }

    public function putVar($name, $value = '')
    {
        return $this->putCache($this->getName($name), $value);
    }

    public function get()
    {
        return $this->getCache($this->getName());
    }

    public function getVar($name)
    {
        return $this->getCache($this->getName($name));
    }

    public function start()
    {
        if($this->enabled){
            ob_start();
            return true;
        }
        return false;
    }

    public function end()
    {
        if($this->enabled){
            $contents = ob_get_contents();
            //ob_end_clean(); fatal??
            return $this->put($contents);
        }
        return false;
    }

    public function clear()
    {
        // if grouped clear entire group
        if(@$this->config['group']){
            return $this->clearGroup();
        }

        if($this->enabled){
            $name = $this->getName();
            switch($this->type){
                case 'file':
                    return @unlink($name);
                break;
                case 'memcache':
                    return $this->memcached->delete($name);
                break;
                case 'apc':
                    return apc_delete($name);
                break;
                default:
                    return false;
            }
        }
    }

    public function clearGroup()
    {
        if(!@$this->config['group'])
            return false;
        if($this->enabled){
            $name = $this->getName();
            switch($this->type){
                case 'file':
                    $this->clearCacheGroup($this->config['prefix']);
                break;
                case 'memcache':
                break;
                case 'apc':
                break;
                default:
                    return false;
            }
        }
    }


    protected function clearCacheGroup($group)
    {
        if(is_dir($group))
        {
            $files = scandir($group);
            // remove . and .. from array
            array_shift($files); array_shift($files);
            foreach($files as $file){
                $path = $group.$file;
                if(is_dir($path)){
                    return $this->clearCacheGroup($path.'/');
                } else {
                    @unlink($path);
                }
            }
        }
        //return @rmdir($group);
    }

    public function setGroup($group)
    {
        if(!is_string($group) || empty($group)){
            return false;
        }
        $this->config['group'] = true;
        if($this->enabled){
            switch($this->type){
                case 'file':
                    $group = $this->config['prefix'].$group.'/';
                    $dir = SimpleFiles::checkDir($group, true, $_SERVER['DOCUMENT_ROOT']);
                    if($dir){
                        $this->config['prefix'] = $group;
                        return true;
                    } else {
                        return false;
                    }
                break;
                case 'memcache':
                    $this->config['prefix'] = $group.'.';
                break;
                case 'apc':
                    $this->config['prefix'] = $group.'.';
                break;
                default:
                    $this->config['group'] = false;
                    return false;
            }
        }
    }

    public function setClearKey($name)
    {
        $this->clearKey = $name;
    }

    protected function isClearForced()
    {
        return $this->clearKey ? isset($_GET[$this->clearKey]) : false;
    }

    protected function putCache($name, $contents)
    {
        if(!is_string($name)){
            return false;
            $this->log('putCache failed - expected a string in \$name.');
        }
        $status = false;
        if($this->enabled)
        {
            $contents = serialize($contents);
            switch($this->type){
                case 'file':
                    SimpleFiles::writeFile($name, $contents, $this->fileMask);
                    /*
                    $dir = dirname($name);
                    if($dir != $this->config['prefix']){
                        SimpleFiles::checkDir(SimpleFiles::addSlash($dir), true, $_SERVER['DOCUMENT_ROOT']);
                    }
                    $status = file_put_contents($name, $contents);
                    chmod($name, $this->fileMask);
                    */
                break;
                case 'memcache':
                    $status = $this->memcached->set($name, $contents, $this->config['compress'], $this->ttl);
                break;
                case 'apc':
                    $status = apc_store($name, $contents, $this->ttl);
                break;
            }
        }
        return $status;
    }

    protected function getCache($name)
    {
        if(!is_string($name)){
            $this->log('getCache failed - expected a string in \$name.');
            return false;
        }
        $value = false;
        if($this->isClearForced()){
            $this->clear();
        } else if($this->enabled){
            switch($this->type){
                case 'file':
                    //if(is_file($name))
                    $valid = true;
                    if($this->ttl){
                        $mtime = filemtime($name);
                        $valid = $mtime && time() - $stat['mtime'] > $this->ttl;
                    }
                    $value = $valid && is_file($name) ? file_get_contents($name) : false;
                break;
                case 'memcache':
                    $value = $this->memcached->get($name, $this->config['compress']);
                break;
                case 'apc':
                    $value = apc_fetch($name);
                break;
            }
        }
        return $value ? unserialize($value) : false;
    }

    protected function initMemCache($config)
    {
        $defaultConfig = array('host'=>'127.0.0.1','port'=>11211, 'compress'=>0);
        if(is_array($config)){
            $this->config = array_merge($defaultConfig, $config);
        } else {
            $this->config = $defaultConfig;
        }

        if(is_null($this->memcache)){
            $this->memcache = new Memcache;
            $this->memcache->connect($this->config['host'], $this->config['port']);
        }
    }

    protected function initFileCache($config)
    {
        $dir = '';
        if(is_string($config)){
            $dir = $config;
        } else if(is_array($config)){
            $dir = isset($config['prefix']) ? $config['prefix'] : '/tmp/';
        }

        if($dir && SimpleFiles::checkDir($dir, true, $_SERVER['DOCUMENT_ROOT'])){
            $this->config['prefix'] = SimpleFiles::addSlash(realpath($dir));
            return true;
        } else {
            $this->config['prefix'] = '';
            return false;
        }
    }

    protected function log($str)
    {
        error_log('SimpleCache: '.$str);
    }
}

