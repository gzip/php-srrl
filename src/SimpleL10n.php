<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleL10n extends SimpleClass
{
    /**
     * @var string
     */
    protected $lang = 'en-US';
    
    /**
     * @var array
     */
    protected $strings = array();
    
    /**
     * @var string
     */
    protected $dir = '';
    
    /**
     * @var string
     */
    protected $file = '';
    
    /**
     * @var array
     */
    protected $files = null;
    
    /**
     * @var object SimpleCache
     */
    protected $cache = null;
    
    /**
     * Called by SimpleClass::__construct prior to setParams.
     * 
     * @return void
     **/
    public function setupParams()
    {
        $this->addSettable(array('cache', 'criteria', 'dir', 'file', 'files', 'lang'));
    }
    
    /**
     * Setup.
     * 
     * @return (void).
    **/
    public function setup()
    {
        $files = array();
        
        // TODO: Repeated in SimpleConfig, move into SimpleFiles::resolveFileArg($file = null, $files = null, $dir = null, $filter = null)
        if($this->dir && is_string($this->dir))
        {
            $files = SimpleFiles::readDir($this->dir, '/\.json$/', true);
        }
        else if(is_array($this->files))
        {
            $files = $this->files;
        }
        else if($this->file && is_string($this->file))
        {
            $files = array($this->file);
        }
        else
        {
            $this->log('No strings found.');
        }
        
        foreach($files as $file)
        {
            $json = file_get_contents($file);
            $strings = $json ? json_decode($json, 1) : false;
            $this->parseStrings($strings);
        }
        //print '<pre>'.print_r($this->strings,1);
    }
    
    /**
     * Store strings in a lookup table based on language.
     * 
     * @param (array) Strings to parse.
     * @return (void).
    **/
    protected function parseStrings($strings)
    {
        if(is_array($strings))
        {
            // fallback lang, e.g. "en" for "en-US"
            $fbLang = substr($this->lang, 0, strpos($this->lang, '-'));
            
            // assign strings from lang, fallback, or wildcard
            $strs = SimpleUtil::getValue($strings, array($this->lang, $fbLang, '*'), array());
            foreach($strs as $key=>$value)
            {
                $this->strings[$key] = $value;
            }
        }
        else
        {
            $this->log('Unexpected strings file format.');;
        }
    }
    
    /**
     * Get a string by key.
     * 
     * @param (string) String's key.
     * @param (array) Dynamic replacements replaced by Mustache.
     * @param (string) Default value if $key isn't found.
     * @return (string) Translated string.
    **/
    function getString($key, $replacements = array(), $default = '')
    {
        $value = SimpleUtil::getValue($this->strings, $key, $default);
        return empty($replacements) || !$value ? $value : SimpleString::renderTemplate($value, $replacements);
    }
}

