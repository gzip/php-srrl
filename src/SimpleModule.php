<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleModule extends SimpleClass
{
    /**
     * @var object
     */
    protected $page = null;
    
    /**
     * @var object
     */
    protected $setKeys = array();
    
    /**
     * @var bool
     */
    protected $final = true;
    
    /**
     * @var object
     */
    protected $cacheObject = null;
    
    /**
     * Called by SimpleClass::__construct prior to setParams.
     * 
     * @return void
     **/
    public function setupParams()
    {
        $this->addSettable('page');
        $this->addGettable(array('data', 'setKeys', 'final'));
    }
    
    /**
     * Setup the module.
     * 
     * @return bool False to fail the module.
    **/
    public function setup()
    {
        $this->html = new SimpleMarkup;
        return true;
    }
    
    /**
     * Get module cache.
     * 
     * @return object SimpleCache object.
    **/
    public function getCacheObject()
    {
        if(is_null($this->cacheObject))
        {
            $this->cacheObject = $this->initCacheObject();
        }
        return $this->cacheObject;
    }
    
    /**
     * Init module cache.
     * 
     * @return object SimpleCache object.
    **/
    protected function initCacheObject()
    {
        return null;
    }
    
    /**
     * Get the module's data.
     * 
     * @return mixed False to fail the module.
    **/
    public function getData()
    {
        return true;
    }
    
    /**
     * Render the module.
     * 
     * @return string Module content.
    **/
    public function render($data)
    {
        return $data;
    }
    
    /**
     * Return assets for this module.
     * 
     * @return array Assets.
    **/
    public function getAssets()
    {
        return array();
    }
    
    /**
     * Set the page title.
     * 
     * @return array Assets.
    **/
    protected function setPageTitle($title)
    {
        return $this->setPageKey('title', $title);
    }
    
    /**
     * Set the page title.
     * 
     * @return array Assets.
    **/
    protected function setPageKey($key, $value)
    {
        $this->setKeys[$key] = $value;
        return $this->page->setKey($key, $value);
    }
}

