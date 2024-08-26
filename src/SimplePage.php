<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimplePage extends SimpleClass
{
    /**
     * @var string
     */
    protected $meta = '';

    /**
     * @var string
     */
    protected $assets = array();

    /**
     * @var string
     */
    protected $template = '';

    /**
     * @var string
     */
    protected $subtemplates = array();

    /**
     * @var string
     */
    protected $phase = '';

    /**
     * @var array
     */
    protected $keys = array();

    /**
     * @var array
     */
    protected $modules = array();

    /**
     * @var array
     */
    protected $results = array();

    /**
     * @var array
     */
    protected $nameIndex = array();

    /**
     * @var array
     */
    protected $moduleRoot = array();

    /**
     * @var object
     */
    protected $markup = null;

    /**
     * Check protected params for Mustache.
     *
     * @return bool Is param present.
    **/
    public function __isset($name)
    {
        //$this->log("__isset $name");
        $result = false;
        switch($this->phase)
        {
            case 'subtemplates':
                $result = isset($this->subtemplates[$name]);
            break;
            case 'modules':
                $result = $this->verifyModule($name);
            break;
            case 'fetch':
                $result = isset($this->nameIndex[$name]);
            break;
            default:
                $result = isset($this->$name) || isset($this->keys[$name]);
        }
        return $result;
    }

    /**
     * Get protected params for Mustache.
     *
     * @return string Param value.
    **/
    public function __get($name)
    {
        //$this->log("__get $name");
        $result = null;
        switch($this->phase)
        {
            case 'subtemplates':
                $result = $this->resolveTemplate($this->subtemplates[$name]);
                unset($this->subtemplates[$name]);
            break;
            case 'modules':
                $result = $this->handleModule($name);
            break;
            case 'fetch':
                $result = $this->handleModuleResult($name);
            break;
            case 'finalize':
                if('assets' === $name)
                {
                    $result = $this->parseAssets();
                } else {
                    $result = SimpleUtil::getValue($this->keys, $name, $this->get($name));
                }
        }
        return $result;
    }

    /**
     * Called by SimpleClass::__construct prior to setParams.
     *
     * @return void
     **/
    public function setupParams()
    {
        $this->addSettable(array('meta', 'template', 'keys'));
        $this->addPushable(array('subtemplates', 'moduleRoot', 'modules'));
        $this->addSetter('moduleRoot', 'setDirectoryPath');
    }

    /**
     * Process module results.
     *
     * @return mixed Results
     **/
    protected function handleModuleResult($name)
    {
        $result = null;
        $indices = SimpleUtil::getValue($this->nameIndex, $name, array());
        foreach($indices as $i)
        {
            $res = SimpleUtil::getValue($this->results, $i);
            if($res)
            {
                // fetch phase will continue until all requests are final
                if(isset($res['request']) && false === $res['module']->isFinal())
                {
                    $result = $this->handleModuleData(
                        $name,
                        $res['module'],
                        SimpleUtil::getValue($res, 'response', '')
                    );
                }
                else
                {
                    list($result, $assets) = $this->handleModuleCache(
                        $res['module'],
                        SimpleUtil::getValue($res, 'response', '')
                    );
                    $this->addAssets($assets);
                }
            }
        }
        return $result;
    }

    /**
     * Setup.
     *
     * @param bool False for failure.
    **/
    public function setup()
    {
        if(!$this->moduleRoot){
            $this->setModuleRoot($_SERVER['DOCUMENT_ROOT'].'/modules');
        }

        $this->assets = $this->getAssets();
    }

    /**
     * Execute a single module and return the result.
     *
     * @param bool False for failure.
    **/
    public function executeModules()
    {
        foreach ($this->modules as $name => $mod)
        {
            if($this->verifyModule($name)) {
                $this->handleModule($name);
            }
        }

        $this->fetchAll();

        $results = array();
        foreach ($this->modules as $name => $mod)
        {
            $results[$name] = $this->handleModuleResult($name);
        }

        return $results;
    }

    /**
     * Include the class file and check if the class exists.
     *
     * @return bool Result of file inclusion and class check.
    **/
    protected function verifyModule($name)
    {
        $result = null;
        $config = SimpleUtil::getValue($this->modules, $name, array());
        $className = SimpleUtil::getValue($config, 'class', $name);

        $fileFound = false;
        foreach ($this->moduleRoot as $root)
        {
            $modulePath = $root.'/'.$className.'.php';
            if (is_file($modulePath)) {
                include_once $modulePath;
                $fileFound = $modulePath;
                break;
            }
        }

        if(class_exists($className)){
            $result = true;
        } else if ($fileFound === false) {
            $this->log('Module file '.$className.'.php not found in path(s) '.implode(', ', $this->moduleRoot));
        } else {
            $this->log('Class '.$className. ' not found in path '.$fileFound);
        }

        return $result;
    }

    /**
     * Instantiate and execute the module.
     *
     * @return string Result of execution.
    **/
    protected function handleModule($name)
    {
        $result = null;
        $config = SimpleUtil::getValue($this->modules, $name, array());
        $className = SimpleUtil::getValue($config, 'class', $name);
        $params = SimpleUtil::getValue($config, 'params', array());

        $mod = new $className(array_merge($params, array('page'=>$this, 'name'=>$name)));

        if(is_a($mod, 'SimpleModule'))
        {
            $result = $this->handleModuleData($name, $mod);
        } else {
            $this->log('Class '.$className. ' is not derived from SimpleModule, skipping.');
        }

        return $result;
    }

    /**
     * Instantiate and execute the module.
     *
     * @return string Result of execution.
    **/
    protected function handleModuleData($name, $mod, $data = null)
    {
        $modClass = get_class($mod);
        list($result, $assets) = $this->handleModuleCache($mod);

        if(is_null($result))
        {
            $data = $mod->getData($data);
            if(false === $data)
            {
                $this->log('Class ' . $modClass . ' failed to return data.');
                $result = false;
            }
            else if(is_array($data))
            {
                for($d=0, $dl=count($data); $d<$dl; $d++)
                {
                    $check = $this->checkForRequestData($mod, $data[$d], $name);
                    // any valid handle should create a fetch
                    $result = $result ? $result : $check;
                }
            }
            else
            {
                $result = $this->checkForRequestData($mod, $data, $name);
            }

            if(is_null($result))
            {
                list($result, $assets) = $this->handleModuleCache($mod, $data);
            }
        }

        $this->addAssets($assets);

        return $result;
    }

    /**
     * Check if data is a request and handle accordingly.
     *
     * @return string Result of check.
    **/
    protected function checkForRequestData($mod, $data, $name)
    {
        $result = null;

        if(SimpleUtil::isObject($data, 'SimpleRequest') || SimpleUtil::isObject($data, 'CurlHandle') || is_resource($data))
        {
            $this->results[] = array('module'=>$mod, 'request'=>$data, 'name'=>$name);
            $result = '~~'.$name.'~~';
        }
        else if(false === $mod->isFinal())
        {
            //$result = '~~'.$name.'~~';
        }

        return $result;
    }

    /**
     * Instantiate and execute the module.
     * This method will execute twice, first w/o $data, then with $data if cache was empty.
     *
     * @return string Result of execution.
    **/
    protected function handleModuleCache($mod, $data = null)
    {
        // execute module if there's no data
        $hasData = !is_null($data) && $data !== false;
        $content = $hasData ? $mod->render($data) : null;
        $assets  = $hasData ? $mod->getAssets() : null;

        // check if module is cached
        $cacheObj  = $mod->getCacheObject();
        $cacheable = SimpleUtil::isObject($cacheObj, 'SimpleCache');

        // handle cache
        if($cacheable)
        {
            // populate cache if data is present
            if($hasData)
            {
                $cacheValue = array('content'=>$content);
                if($assets){ $cacheValue['assets'] = $assets; }
                $keys = $mod->getSetKeys();
                if($keys){ $cacheValue['keys'] = $keys; }
                $cacheObj->put($cacheValue);
            }
            // otherwise fetch cache
            else
            {
                $cache = $cacheObj->get();
                if($cache)
                {
                    $content = SimpleUtil::getValue($cache, 'content', null);
                    $assets = SimpleUtil::getValue($cache, 'assets', null);
                    $keys = SimpleUtil::getValue($cache, 'keys', null);
                    if($keys)
                    {
                        foreach($keys as $key=>$value)
                        {
                            $this->setKey($key, $value);
                        }
                    }
                }
            }
        }

        return array($content, $assets);
    }

    /**
     * Fetch all web service data in parallel.
     *
     * @return string Param value.
    **/
    protected function fetchAll()
    {
        $requestObjs = array();
        foreach($this->results as $id=>$result)
        {
            if(true !== SimpleUtil::getValue($result, 'complete'))
            {
                // $this->log($id.': '.$result['name'].', '.get_class($result['module']).', '.
                // $result['module']->getUrl().', '.$result['request']->getHandle());
                $requestObjs[$id] = $result['request'];
                $this->results[$id]['complete'] = true;
            }
        }

        $request = new SimpleRequest();
        $handles = $request->multiQuery($requestObjs);
        //$this->log($handles);

        if(!empty($handles))
        {
            $this->nameIndex = array();
            foreach($handles as $id=>$response)
            {
                $result = &$this->results[$id];
                $result['response'] = $response;

                // add index to lookup, use an array in case there are multi requests
                $this->nameIndex[$result['name']][] = $id;

                // log if debug is enabled
                if(SimpleUtil::isObject($result['request'], 'SimpleRequest'))
                {
                    $result['request']->logResult();
                }
            }
        }
        //$this->log($this->nameIndex);
    }

    /**
     * Set a key which is referenced in the template.
    **/
    public function setKey($key, $value)
    {
        $this->keys[$key] = $value;
    }

    /**
     * Get a key.
    **/
    public function getKey($key)
    {
        return SimpleUtil::getValue($this->keys, $key);
    }

    /**
     * Return assets for this page.
     *
     * @return array Assets.
    **/
    public function getAssets()
    {
        return array();
    }

    /**
     * Add assets.
     *
     * @param array Array of asset configurations.
    **/
    public function addAssets($assets)
    {
        if(is_array($assets))
        {
            foreach($assets as $asset)
            {
                $this->assets[] = $asset;
            }
        }
    }

    /**
     * Parse assets config into markup.
     *
     * @return string Markup for assets.
    **/
    protected function parseAssets()
    {
        $markup = array();
        foreach($this->assets as $asset)
        {
            $tag = null;
            $type = SimpleUtil::getValue($asset, 'type');
            switch($type)
            {
                case 'js':
                case 'css':
                    $tag = $this->parseAsset($asset, $type);
                break;
                case 'blob':
                    $tag = SimpleUtil::getValue($asset, 'content');
                break;
                default:
                    $this->log('Unknown asset type "'.$type.'".');
                break;
            }
            if($tag){
                $markup[] = $tag;
            }
        }

        return implode("\n", array_unique($markup));
    }

    /**
     * Parse asset config into markup.
     *
     * @return string Asset markup.
    **/
    protected function parseAsset($asset, $type)
    {
        $result = null;
        $attrs = SimpleUtil::getValue($asset, 'attrs');
        switch(true)
        {
            case $url = SimpleUtil::getValue($asset, 'url'):
                if($type == 'css'){
                    $attrs = $this->markup->buildAttrs($attrs, array('rel'=>'stylesheet', 'type'=>'text/css', 'href'=>$url));
                    $result = $this->markup->link(SimpleMarkup::NO_VALUE, null, $attrs);
                } else {
                    $attrs = $this->markup->buildAttrs($attrs, array('src'=>$url));
                    $result = $this->markup->script(SimpleMarkup::NO_VALUE, null, $attrs);
                }
            break;
            case $file = SimpleUtil::getValue($asset, 'file'):
                $content = file_get_contents($file);
                // fallthru
            case isset($content) || $content = SimpleUtil::getValue($asset, 'content'):
                $tag = $type == 'css' ? 'style' : 'script';
                $result = $this->markup->$tag($content, null, $attrs);
            break;
            default:
                $this->log('Misconfigured asset:');
                $this->log($asset);
            break;
        }
        return $result;
    }

    /**
     * Get template contents from file or string.
     *
     * @return string Template.
    **/
    protected function resolveTemplate($template)
    {
        $template = realpath($template);
        if(is_file($template)){
            $template = file_get_contents($template);
        }
        return $template;
    }

    /**
     * Execute a phase of the page.
     *
     * @return string Full page render.
    **/
    protected function executePhase($template, $phase, $unescaped, $open = '{{', $close = '}}')
    {
        $this->phase = $phase;
        return SimpleString::renderTemplate(($unescaped ? $open.'%UNESCAPED'.$close : '').$template, $this, array($open, $close));
    }

    /**
     * Render the page.
     *
     * @return string Full page render.
    **/
    public function render()
    {
        $this->markup = new SimpleMarkup;

        $template = $this->resolveTemplate($this->template);

        while(false !== strpos($template, '<<'))
        {
            $template = $this->executePhase($template, 'subtemplates', true, '<<','>>');
        }

        $template = $this->executePhase($template, 'modules', true, '[[',']]');

        while(count($this->results) && false !== strpos($template, '~~'))
        {
            $this->fetchAll();
            $template = $this->executePhase($template, 'fetch', true, '~~','~~');
        }

        return $this->executePhase($template, 'finalize', false);
    }
}

