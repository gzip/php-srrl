<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleRequest extends SimpleClass
{
    /**
     * @var string
     */
    protected $protocol = 'http';

    /**
     * @var string
     */
    protected $host = null;

    /**
     * @var int
     */
    protected $port = 80;

    /**
     * @var Curl handle
     */
    protected $handle = null;

    /**
     * @var Response
     */
    protected $response = null;

    /**
     * @var bool
     */
    protected $parse = false;

    /**
     * @var bool
     */
    protected $final = true;

    /**
     * @var string
     */
    protected $handleMethod = 'query';

    /**
     * @var array
     */
    protected $logger = array();

    /**
     * @var array
     */
    protected $constants = array('options'=>array(), 'errors'=>array(), 'multi'=>array());

    /**
     * @var array
     * @see https://www.php.net/manual/en/function.curl-getinfo.php#refsect1-function.curl-getinfo-returnvalues
     */
    protected $debugDetails = array('url', 'content_type', 'http_code', 'connect_time', 'total_time', 'speed_download', 'download_content_length');

    /**
     * Called by SimpleClass::__construct prior to setParams.
     *
     * @return void
     **/
    public function setupParams()
    {
        $this->addSettable(array('host', 'protocol', 'port', 'url'));
        $this->addSetter('url', 'parseUrl');
        $this->addPushable(array('debugDetails'));
        $this->addGettable(array('handle', 'parse'));
    }

    /**
     * Get the protocol host and port.
     *
     * E.g. 'http://foo.com:25'
     *
     * @return string Host URL built from $this->protocol.'://'.getenv($this->host).':'.$this->port
     **/
    public function getBase()
    {
        return $this->protocol.'://'.$this->host.(80 == $this->port ? '' : ':'.$this->port);
    }

    /**
     * Parse URL into its components (protocol, host, port path) and set relevant properties.
     *
     * @return array URL components
     **/
    public function parseUrl($url)
    {
        $components = parse_url($url);
        foreach (array('scheme'=>'protocol', 'host'=>'host', 'port'=>'port') as $key=>$dest) {
            if (isset($components[$key])) {
                $this->set($dest, $components[$key]);
            }
        }

        return $components;
    }

    /**
     * Make a generic request.
     *
     * @param string API path without host or base, or full URL which will be parsed.
     * @param array Options for the request including keys:
     *     <ul>
     *     <li>queryParams (array) : Key value pairs appended to the URL as query paramaters.</li>
     *     <li>matrixParams (array) : Key value pairs appended to the URL as matrix paramaters.</li>
     *     <li>postData (string/array) : POST data to be sent along with the request (uses CURLOPT_POSTFIELDS).</li>
     *     <li>putData (string/array) : PUT data to be sent along with the request (uses CURLOPT_POSTFIELDS).</li>
     *     <li>curlOptions (array) : Array of Curl options as passed to curl_setopt_array.</li>
     *     <li>executeHandle (bool) : False will return the Curl handle before executing it.</li>
     *     <li>keepAlive (int) : Time to keep the connection alive in milliseconds.</li>
     *     <li>parseResponse (bool) : Parse the response based on content type.
     *         JSON will be go through json_decode and XML will go through simple_xml_parse_string.</li>
     *     <li>parseOption (mixed) : Arguments to pass to the decoder used in parseResponse.</li>
     *     </ul>
    **/
    public function query($path, $options = array())
    {
        // handle raw url
        if (strpos($path, '://') !== false)
        {
            $components = $this->parseUrl($path);
            $path = SimpleUtil::getValue($components, 'path');
            $query = SimpleUtil::getValue($components, 'query');
            if (!empty($query)) {
                parse_str($query, $query);
                $options['queryParams'] = array_merge(SimpleUtil::getValue($options, 'queryParams', array()), $query);
            }
        }

        $this->setOptions($options);

        // store parse flag for access in multiQuery
        $this->parse = $this->getOption('parseResponse');

        $headers = array();

        // handle keep alive headers is presentt
        $ka = $this->getOption('keepAlive');
        if($ka && is_int($ka))
        {
            $headers[] = 'Connection: keep-alive';
            $headers[] = 'Keep-Alive: '.$ka;
        }

        // tack on any matrix params
        $path .= $this->buildMatrixParams($this->getOption('matrixParams'));

        // tack on any query params
        $path .= $this->buildQueryParams($this->getOption('queryParams'), $path);

        // set curl options
        $curlOptions = array();

        // handle put or post data
        if(isset($options['putData']))
        {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $curlOptions[CURLOPT_POSTFIELDS] = $this->buildQueryParams($options['putData']);
        }
        else if(isset($options['postData']))
        {
            $curlOptions[CURLOPT_POST] = 1;
            $curlOptions[CURLOPT_POSTFIELDS] = $this->buildQueryParams($options['postData']);
        }

        // increase timeouts for debug
        if(!$this->debug)
        {
            $curlOptions[CURLOPT_CONNECTTIMEOUT] = $this->getOption('connectTimeout', 1);
            $curlOptions[CURLOPT_FAILONERROR] = true;
        }
        else
        {
            $curlOptions[CURLOPT_TIMEOUT] = 10;
            $curlOptions[CURLOPT_CONNECTTIMEOUT] = 5;
        }

        $curlOptions[CURLOPT_RETURNTRANSFER] = true;

        // append any custom curl options, overriding any previous values
        $additionalOptions = $this->getOption('curlOptions');
        if(is_array($additionalOptions))
        {
            $curlOptions = $this->mergeOptions($curlOptions, $additionalOptions);
        }

        // add headers
        $curlOptions[CURLOPT_HTTPHEADER] = $headers;

        // create handle and set options
        $url = $this->getBase() . $path;
        $curlHandle = curl_init($url);
        curl_setopt_array($curlHandle, $curlOptions);

        // save additional log info if debug is enabled
        if($this->debug)
        {
            if(!empty($curlOptions))
            {
                $resolvedOptions = array();
                $optionConstants = $this->getCurlConstants('options');

                foreach($curlOptions as $constant=>$value)
                {
                    if (isset($optionConstants[$constant])) {
                        $resolvedOptions[$optionConstants[$constant]] =
                            is_bool($value) ? ($value ? 'true' : 'false') : $value;
                    } else {
                        $this->log("$constant=>$value");
                    }
                }

                $this->logger['curl_options'] = print_r($resolvedOptions, 1);
            }
        }

        // store handle
        $this->handle = $curlHandle;

        // assign handle to result or execute it
        if($this->getOption('execute', true))
        {
            $result = $this->execute();
        }
        else // get the same result with setupHandle()
        {
            $result = $curlHandle;
        }

        return $result;
    }

    /**
     * Wrapper around query() to return a handle. Equivalent to passing 'execute' => false in $options.
     *
     * @param string API without host or base.
     * @param array Options.
     * @see query
    **/
    public function setupHandle($path, $options = array())
    {
        $result = false;
        if(is_array($options)){
            $options['execute'] = false;
            $result = $this->{$this->handleMethod}($path, $options);
        }
        return $result;
    }

    /**
     * Wrapper around setupHandle() to return a request object instead of a curl handle.
     *
     * @param string API without host or base.
     * @param array Options.
     * @see query
    **/
    public function setupRequest($path, $options = array())
    {
        $this->setupHandle($path, $options);
        return $this;
    }

    /**
     * Log results if present.
     *
     * @return void
    **/
    public function execute()
    {
        // init, set options, and execute
        $result = curl_exec($this->handle);

        // optionally parse the response further
        if($this->getOption('parseResponse'))
        {
            $result = $this->parseResponse($result);
        }

        $this->logResult();

        return $result;
    }

    /**
     * Fetch all web service data in parallel.
     *
     * @param array Array of SimpleRequest objects or raw resources.
     * @return array Results
    **/
    public function multiQuery(Array $queries)
    {
        //$this->log($queries);
        $results = false;
        if(count($queries))
        {
            // init curl multi and add handles
            $keys = array();
            $multi = curl_multi_init();
            foreach($queries as $key=>$query)
            {
                $isRequest = is_a($query, 'SimpleRequest');
                $isResource = is_resource($query);          // prior to php 8
                $isCurlHandle = is_a($query, 'CurlHandle'); // new in php 8

                if($isRequest || $isResource || $isCurlHandle)
                {
                    // get handle if it's a SimpleRequest
                    $handle = $isRequest ? $query->getHandle() : $query;

                    // store key to return results in same order
                    $id = SimpleRequest::getResourceId($handle);
                    $keys[$id] = array('key'=>$key, 'request'=>($isRequest ? $query : false));
                    curl_multi_add_handle($multi, $handle);
                }
            }

            // execute handles
            $results = array();
            do {
                $status = curl_multi_exec($multi, $executing);
                $info = curl_multi_info_read($multi);
                if($info)
                {
                    $handle = $info['handle'];
                    $id = SimpleRequest::getResourceId($handle);

                    $key = $keys[$id]['key'];
                    $request = $keys[$id]['request'];

                    switch($info['result'])
                    {
                        case CURLE_OK:
                            $content = curl_multi_getcontent($handle);
                            if (SimpleUtil::isObject($request, 'SimpleRequest') && $request->getParse()) {
                                $results[$key] = $this->parseResponse($content, $request->getHandle());
                            } else {
                                $results[$key] = $content;
                            }
                        break;
                        default:
                            $results[$key] = false;
                    }
                }
            } while ($info || $executing);
        }
        //$this->log($results);

        return $results;
    }

    /**
     * Get the resource id of a curl handle.
     * (In PHP 8 the handle changed from a resource object to the opaque class CurlHandle, so we need to cover both cases.)
     *
     * @param obj Resource object or CurlHandle.
     * @see https://php.watch/versions/8.0/resource-CurlHandle
     * @return int Id
    **/
    static public function getResourceId($res)
    {
        return is_resource($res) ? SimpleUtil::getResourceId($res) : (int) $res;
    }

    /**
     * Parse the raw header string in an HTTP response.
     *
     * @param string Raw header string.
     * @return array Indexed by header name.
    **/
    static public function parseHeaders($rawHeaders)
    {
        $headers = array();
        $lines = explode("\n", $rawHeaders);

        // get response header first if set
        if (isset($lines[0]) && strpos($lines[0], "HTTP") === 0) {
            $headers[0] = array_shift($lines);
        }

        foreach ($lines as $line)
        {
            if (strpos($line, ':')) {
                list($key, $value) = explode(":", $line, 2);
                $headers[$key] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * Build matrix params from an array or string.
     *
     * @param array/string Parameters.
     * @return string Parameter string.
    **/
    public function buildMatrixParams($params)
    {
        return SimpleString::buildParams($params, ';', ';');
    }

    /**
     * Build query params from an array or string.
     *
     * @param array/string Parameters.
     * @return string  Parameter string.
    **/
    public function buildQueryParams($params, $value = '')
    {
        $prefix = '';
        if(is_string($value)){
            $prefix = strpos($value, '?') ? '&' : '?';
        }
        return SimpleString::buildParams($params, $prefix);
    }

    /**
     * Do further parsing on the response before returning.
     *
     * @param string Result of query.
     * @param object Curl handle.
     * @return mixed Result of parse.
    **/
    protected function parseResponse($content, $handle = null)
    {
        $result = false;
        if(is_null($handle)){ $handle = $this->handle; }

        $types = explode(';', (string)curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
        $contentType = array_shift($types);

        switch(SimpleHttp::normalizeContentType($contentType))
        {
            case 'json':
                $result = json_decode($content, $this->getOption('parseOption', 1));
            break;
            case 'xml':
                $result = SimpleString::parseXml($content);
            break;
            case 'form':
                parse_str($content, $result);
            break;
            default:
                // log?
                $result = $content;
        }

        return $result;
    }

    /**
     * Integer index safe array_merge.
     *
     * @param array First array.
     * @param array Second array.
     * @return array New array or false on error.
    **/
    protected function mergeOptions($a,$b)
    {
        if(!is_array($a) || !is_array($b)){
            return false;
        }

        foreach($b as $key=>$value){
            $a[$key] = $value;
        }

        return $a;
    }

    /**
     * Get an associative array of grouped curl constants.
     *
     * @return array
    **/
    public function getCurlConstants($group = null)
    {
        $curlConstants = SimpleUtil::getValue(get_defined_constants(true), 'curl');
        foreach($curlConstants as $name=>$constant)
        {
            if (strpos($name, 'CURLOPT_') === 0) {
                $this->constants['options'][$constant] = $name;
            } else if (strpos($name, 'CURLE_') === 0) {
                $this->constants['errors'][$constant] = $name;
            } else if (strpos($name, 'CURLM_') === 0) {
                $this->constants['multi'][$constant] = $name;
            }
        }

        //ksort($curlConstants);
        //$this->log($curlConstants);
        //$this->log($this->constants);

        return empty($group) ? $this->constants : $this->constants[$group];
    }

    /**
     * Log results if present.
     *
     * @return void
    **/
    public function logResult()
    {
        // log only if enabled
        if(!empty($this->debug))
        {
            // get curl info
            $info = curl_getinfo($this->handle);

            // reduce info if specified
            if(!empty($this->debugDetails)){
                $info = array_intersect_key($info, array_fill_keys($this->debugDetails, null));
            }

            // add curl error if present
            $err = curl_errno($this->handle);
            if($err){
                $info['curl_error'] = $this_constants['errors'][$err];
            }

            // tack on info from logger
            $info['curl_options'] = $this->logger['curl_options'];

            // log it
            $this->log(SimpleString::buildParams($info, "\n  ", "\n  ", ': ', null));
        }
    }
}
