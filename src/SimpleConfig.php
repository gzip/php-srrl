<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

/**
  * Configuration class which can resolve settings based on specific criteria.
  *
  * @method string setCache(mixed $config) Set the cache config.
  * @method string setCriteria(array $criteria) Hash of criteria to match when looking up settings.
  * @method string setDir(string $dir) A directory where configuration is located. Subdirectories will be loaded recursively.
  * @method string setFile(string $file) A file to find configuration in.
  * @method string setFiles(array $files) An array of files to find configuration in.
  **/
/*
    * TODO: Accept an array of files and merge
    * Init behavior:
        1. Traverse array and build index $values of all config values which contain "matches"
        2. Create array $indices keyed on $key and subkeyed on $matchKey (which is built from "matches" array)
            - E.g. foo.bar.baz => (*=>n, c1:bar,c2:quuz=>n, c1:bat=>n), where "n" is an index in $values
            - Index "*" is the default value when criteria is not matched.
    * Lookup behavior:
        1. Check $indices for $key
            - Return $default if not found
            - Otherwise assign $indices[$key] to $settings
        2. Build $criteriaKey from $criteria
        3. Check for $settings[$criteriaKey] and assign index $n if found
            - Lookup $n in $values and return value
        4. Otherwise loop thru $settings
            - Build $matches array from $matchKey
            - Rank $criteria based on key order and assign $weights
            - Array diff $matches against $criteria
                - If there are remaining values then there's no match
                - Otherwise find best match based on $weights
        5. If nothing found then loop thru and look for partial key matches (in progress)
        6. Check for wildcard if nothing is found
        7. Cache result in $indices, $n=false/null if nothing found
*/
class SimpleConfig extends SimpleClass
{
    /**
     * @var string/array
     */
    protected $criteria = '*';
    
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
     * @method string setFiles2(array $files) An array of files to find configuration in.
     */
    protected $files = null;
    
    /**
     * @var array
     */
    protected $values = array();
    
    /**
     * @var array
     */
    protected $indices = array();
    
    /**
     * @var array
     */
    protected $arrayIndices = array();
    
    /**
     * @var object SimpleCache
     */
    protected $cache = null;
    
    /**
     * Called by SimpleClass::__construct prior to setParams.
     * 
     * @return void
     * @ignore
     **/
    public function setupParams()
    {
        $this->addSettable(array('cache', 'criteria', 'dir', 'file', 'files'));
    }
    
    /**
     * Read config files. Called automatically on instantation but must be called again if `dir`, `file`, or `files` is changed.
     * @return void
     **/
    function setup()
    {
        $files = array();
        
        if($this->dir && is_string($this->dir))
        {
            //if(SimpleFiles::checkDir($this->dir, false))
            {
                $files = SimpleFiles::readDir($this->dir, '/\.json$/', true);
            }
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
            // log
        }
        
        foreach($files as $file)
        {
            $json = file_get_contents($file);
            $conf = $json ? json_decode($json, 1) : false;
            $this->parseConf($conf);
        }
        //print '<pre>'.print_r($this->indices,1);
        //print '<pre>'.print_r($this->values,1);
    }
    
    /**
     * Look up a setting based on optional criteria.
     *
     * @param string They configuration key, e.g. `foo`, `foo.bar.bat`, etc.
     * @param mixed An optional default value to use if the setting isn't found.
     * @param mixed An optional array of criteria to match when lookup up the setting. Defaults to $this->getCriteria().
     * @mixed Setting value or default.
     **/
    function getSetting($key, $default = null, $criteria = null)
    {
        $matchKey = $this->buildMatchKey(is_null($criteria) ? $this->getCriteria() : $criteria);
        $values = SimpleUtil::getValue($this->indices, $key);
        $index  = SimpleUtil::getValue($values, $matchKey, null);
        //print_r("$key $matchKey ");
        
        if(is_null($index) && !empty($values))
        {
            $maxWeight = 0;
            $matches = array();
            foreach($values as $cr=>$idx)
            {
                if($cr == '*' && $maxWeight == 0)
                {
                    $index = $idx;
                    break;
                }
                else if(is_array($criteria))
                {
                    // get hash of criteria weights based on order, e.g. (key1=>0, key2=>1,...)
                    $keys = array_keys($criteria);
                    $weights = array_combine(array_values($keys), array_keys($keys));
                    
                    // diff current against passed criteria
                    $cr = $this->matchKeyToCriteria($cr);
                    $diff = array_diff_assoc($cr, $criteria);
                    
                    // empty diff indicates a match
                    if(empty($diff))
                    {
                        // calculate total weight of matched keys
                        $weight = 0;
                        $keys = array_intersect_key($criteria, $cr);
                        foreach($keys as $key=>$val)
                        {
                            $weight += $weights[$key] + 1;
                        }
                        
                        // assign index if a higher weight
                        if($weight >= $maxWeight)
                        {
                            $index = $idx;
                            $maxWeight = $weight;
                        }
                        //$matches[] = array('keys'=>$keys, 'index'=>$idx, 'weight'=>$weight);
                    };
                    // TODO: log $matches if debug
                }
            }
        }
        
        // look through partial key matches
        if(is_null($index))
        {
            $indices = array();
            foreach($this->indices as $idxKey=>$vals)
            {
                if(strpos($idxKey, $key) === 0 && $idxKey !== $key)
                {
                    //print "$idxKey: "; print_r($vals);
                    $val = SimpleUtil::getValue($vals, array($matchKey, '*'), null); // TODO: find partials
                    if(!is_null($val))
                    {
                        $indices[$idxKey] = $val;
                    }
                }
            }
            //print_r($indices);
        }
        
        return is_null($index) ? $default : SimpleUtil::getValue($this->values, $index);
    }
    
    protected function isMatchArray($conf)
    {
        return is_array(SimpleUtil::getValue($conf, 0)) && array_key_exists('matches', $conf[0]);
    }
    
    protected function buildMatchKey($matches)
    {
        $matchKey = null;
        if(is_array($matches))
        {
            $matchKey = '';
            ksort($matches);
            foreach($matches as $key=>$value)
            {
                $matchKey .= ($matchKey?',':'') . $key.':'.$value;
            }
        }
        else if(is_string($matches))
        {
            if('*' === $matches) // TODO: preg_match raw key?
            {
                $matchKey = $matches;
            }
        }
        
        if(is_null($matchKey))
        {
            $this->log('Unexpected criteria format.');
        }
        
        return $matchKey;
    }
    
    protected function matchKeyToCriteria($key)
    {
        $criteria = array();
        $pairs = explode(',', $key);
        foreach($pairs as $pair)
        {
            list($key, $value) = explode(':', $pair);
            $criteria[$key] = $value;
        }
        return $criteria;
    }
    
    protected function parseConf(&$conf, $parentKey = '', $parentMatches = null)
    {
        if(is_array($conf))
        {
            foreach($conf as $key=>$value)
            {
                // build conf key
                if(is_int($key)){ $key = ''; }
                $confKey = $parentKey.($parentKey && $key ? '.' : '').$key;
                
                $matches = SimpleUtil::getValue($value, 'matches');
                if(is_array($matches) && is_array($parentMatches))
                {
                    $matches = array_merge($matches, $parentMatches);
                }
                
                if($matches)
                {
                    $matchKey = $this->buildMatchKey($matches);
                    if($matchKey)
                    {
                        unset($value['matches']);
                        foreach($value['values'] as $subKey=>$subValue)
                        {
                            // reset index
                            $idx = null;
                            
                            // append to conf key
                            $subConfKey = $confKey.($confKey ? '.' : '').$subKey;
                            
                            // special treatment for arrays
                            if(is_array($subValue))
                            {
                                // don't store submatch arrays
                                if($this->isMatchArray($subValue))
                                {
                                    continue;
                                }
                                
                                // merge and continue if there's already a match
                                $idx = SimpleUtil::getValue(SimpleUtil::getValue($this->indices, $subConfKey), $matchKey, null);
                                if($idx)
                                {
                                    $this->values[$idx] = array_merge($this->values[$idx], $subValue);
                                    continue;
                                }
                                
                                // serialize and store index in separate array to avoids dupes
                                $serialized = serialize($subValue);
                                $idx = SimpleUtil::getValue($this->arrayIndices, $serialized, null);
                                if(is_null($idx))
                                {
                                    $this->arrayIndices[$serialized] = count($this->values);
                                }
                                
                                // parse recursively
                                $this->parseConf($subValue, $subConfKey, $matches);
                            }
                            
                            // search for value and assign index accordingly
                            if(is_null($idx))
                            {
                                $idx = array_search($subValue, $this->values, true);
                                if(false === $idx)
                                {
                                    $idx = count($this->values);
                                    $this->values[$idx] = $subValue;
                                }
                            }
                            
                            $this->indices[$subConfKey][$matchKey] = $idx;
                        }
                    }
                }
                else if(is_array($value))
                {
                    $this->parseConf($value, $confKey, $parentMatches);
                }
            }
        }
        else
        {
            $this->log('Unexpected configuration format.');;
        }
    }
}

