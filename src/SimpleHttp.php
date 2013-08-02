<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleHttp extends SimpleClass
{
    /**
     * Stores the request headers.
     * @var array
     */
    static protected $headers = null;
    
    /**
     * Stores sent headers for testing.
     * @var array
     */
    static protected $headersSent = array();
    
    /**
     * Stores the request body.
     * @var array
     */
    static protected $requestBody = null;
    
    /**
     * Called by SimpleClass::__construct prior to setParams.
     * 
     * @return void
     **/
    public function setupParams()
    {
        //$this->addSettable(array());
    }
    
    /**
     * Get the request body.
     * 
     * @param bool Attempt to parse if json, xml, or form encoded.
     * @param mixed Parse option, e.g. json_decode as array.
     * @return mixed The raw or parsed body.
     **/
    static public function getRequestBody($parse = true, $options = 1)
    {
        if(is_null(self::$requestBody))
        {
            $data = file_get_contents('php://input');
            if($parse)
            {
                if(true === $parse)
                {
                    list($contentType) = explode(';', self::getHeader('Content-Type'));
                    $parse = self::normalizeContentType($contentType);
                }
                
                switch($parse)
                {
                    case 'json':
                        $data = json_decode($data, $options);
                    break;
                    case 'xml':
                        $data = SimpleString::parseXml($data);
                        // TODO: turn into an array for consistency
                    break;
                    case 'form':
                        parse_str($data, $data);
                    break;
                    default:
                        // log
                }
            }
            
            self::$requestBody = $data;
        }
        
        return self::$requestBody;
    }

    /**
     * Get the request body.
     * 
     * @param string Header name.
     * @param string Header value.
     * @return bool Whether header was sent.
     **/
    static public function sendHeader($key, $value, $overwrite = true)
    {
        if(!headers_sent($file, $line)) {
            header($key . ': ' . $value, $overwrite);
            self::headersSent[$key] = $value;
            return true;
        } else {
            SimpleUtil::log('Unable to send header ' . $key . '. Headers alread sent in ' . $file . ' on line ' . $line . '.');
            return false;
        }
    }
    
    /**
     * @return string The header value or empty if not found.
     **/
    static public function getHeader($header = null)
    {
        $headers = self::getHeaders();
        return is_null($header) ? $headers : SimpleUtil::getValue($headers, $header);
    }
    
    /**
     * Get request headers.
     * 
     * @return array Headers
     **/
    static public function getHeaders()
    {
        if(is_null(self::$headers))
        {
            self::$headers = apache_request_headers();
        }
        
        return self::$headers;
    }
    
    /**
     * Get the request's Content-Type header.
     * 
     * @param bool Attempt to normalize to `json`, `xml`, or `form`.
     * @return string The content type.
     **/
    static public function getContentType($normalize = false)
    {
        return self::getHeader('Content-Type');
    }
    
    /**
     * Serve a content type header.
     * 
     * @param string The type. May be a valid content type or one of `json`, `xml`, or `form`.
     * @return void
     **/
    static public function setContentType($type)
    {
        return self::sendHeader('Content-Type', self::denormalizeContentType($type));
    }
    
    /**
     * Serve a content type header.
     * 
     * @param string Should be one of `Origin`, `Methods`, or `Headers`.
     * @param mixed String ot array value. The latter will be joined comma separated.
     **/
    static public function setAccessControl($type, $value)
    {
        return self::sendHeader('Access-Control-'.($type !== 'Max-Age' ? 'Allow-' : '').$type, $value);
    }
    
    /**
     * Get the request's Accept header as an array.
     * 
     * @param bool Attempt to normalize content types.
     * @return array Array of acceptable types.
     **/
    static public function getAccept($normalize = true)
    {
        $accept = explode(',', self::getHeader('Accept'));
        foreach($accept as $key=>$value)
        {
            $type = array_shift(explode(';', $value));
            $accept[$key] = $normalize ? self::normalizeContentType($type) : $type;
        }
        
        return $accept;
    }
    
    /**
     * Serve the headers required to trigger a file download.
     * 
     * @param string The filename.
     * @return void
     **/
    static public function sendFile($filename)
    {
        if(is_string($filename))
        {
            self::sendHeader('Pragma', 'public');
            self::sendHeader('Expires', '0');
            self::sendHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
            self::sendHeader('Cache-Control', 'private', false);
            self::sendHeader('Content-Disposition', 'attachment; filename="'.$filename.'";');
            self::sendHeader('Content-Transfer-Encoding', 'binary');
            //self::sendHeader('Content-Type', $filetype);
            //self::sendHeader('Content-Length', $filesize); 
        }
    }
    
    /**
     * Serve a cache control header.
     * 
     * @param int Expire time. The default 0 will result in a no-cache header.
     * @return void
     **/
    static public function setCache($time = 0)
    {
        if($time > 0)
        {
            self::sendHeader('Cache-Control', 'maxage='.$time);
            self::sendHeader('Expires', gmdate('D, d M Y H:i:s', time()+$time) . ' GMT');
        }
        else
        {
            self::sendHeader('Cache-Control', 'no-cache, must-revalidate');
            self::sendHeader('Expires', '0');
        }
    }
    
    /**
     * Serve a redirect header and halt execution.
     * 
     * @param string URL.
     * @param bool True for 302, false for 301 (default).
     * @return void
     **/
    static public function redirect($url, $temporary = false)
    {
        self::location($url, $temporary ? 302 : 301);
        exit;
    }
    
    /**
     * Serve a location header.
     * 
     * @param string URL.
     * @param int Status code.
     * @return void
     **/
    static public function location($url, $code = 301)
    {
        // TODO parse_url and ensure canonical
        self::sendHeader('Location', $url, true, $code);
    }
    
    /**
     * Test if the incoming request is over SSL.
     * 
     * @return bool
     **/
    static public function isSsl()
    {
        return SimpleUtil::getValue($_SERVER, 'HTTPS') === 'on' || $_SERVER['SERVER_PORT'] === '443';
    }
    
    /**
     * Serve an success header.
     * 
     * @param int 2xx status code.
     * @param string Optional message. Will use HTTP defaults if omitted.
     * @return void
     **/
    static public function success($status = 200, $msg = null)
    {
        if(is_null($msg))
        {
            switch($status)
            {
                case 200: $msg = 'OK'; break;
                case 201: $msg = 'Created'; break;
                case 202: $msg = 'Accepted'; break;
                case 204: $msg = 'No Content'; break;
            }
        }
        
        header($_SERVER['SERVER_PROTOCOL'].' '.$status.' '.$msg);
    }
    
    /**
     * Serve an error header and optionally exit.
     * 
     * @param int Status code.
     * @param string Optional error message. Will use self::getErrorMessage if omitted.
     * @param bool Halt further execution.
     * @return void
     * @see SimpleHttp::getErrorMessage
     **/
    static public function error($status = 404, $msg = null, $exit = true)
    {
        if(is_null($msg))
        {
            $msg = self::getErrorMessage($status);
        }
        
        header($_SERVER['SERVER_PROTOCOL'].' '.$status.' '.$msg);
        
        if($exit){
            exit($status.' '.$msg);
        }
    }
    
    /**
     * Look up error message by common HTTP codes.
     * 
     * @param int Status code.
     * @return string Error message.
     **/
    static public function getErrorMessage($status)
    {
        switch($status)
        {
            case 400: $msg = 'Bad Request'; break;
            case 401: $msg = 'Unauthorized'; break;
            case 403: $msg = 'Forbidden'; break;
            case 404: $msg = 'Not Found'; break;
            case 405: $msg = 'Method Not Allowed'; break;
            case 406: $msg = 'Not Acceptable'; break;
            case 500: $msg = 'Internal Server Error.'; break;
            case 501: $msg = 'Not Implemented'; break;
            case 503: $msg = 'Service Unavailable'; break;
            default:  $msg = 'Unknown Error'; break;
        }
        
        return $msg;
    }
    
    /**
     * Normalize content type to a more friendly format.
     * 
     * @param string Content type.
     * @return string Normalized type or $contentType if unknown
     **/
    static public function normalizeContentType($contentType)
    {
        switch($contentType)
        {
            case 'application/json':
            case 'text/x-json':
            case 'text/json':
                $contentType = 'json';
            break;
            case 'application/xhtml+xml':
            case 'application/xml':
            case 'text/xml':
                $contentType = 'xml';
            break;
            case 'application/x-www-form-urlencoded':
                $contentType = 'form';
            break;
            case 'application/rss+xml':
                $contentType = 'rss';
            break;
            case 'application/atom+xml':
                $contentType = 'atom';
            break;
        }
        
        return $contentType;
    }
    
    /**
     * Denormalize generic type to a valid content type.
     * 
     * @param string Generic type, either <code>json</code>, <code>xml</code>, or <code>form</code>.
     * @return string Content Type if known.
     **/
    static public function denormalizeContentType($type)
    {
        switch($type)
        {
            case 'json':
                $type = 'application/json';
            break;
            case 'xml':
                $type = 'application/xml';
            break;
            case 'form':
                $type = 'application/x-www-form-urlencoded';
            break;
            case 'rss':
                $contentType = 'application/rss+xml';
            break;
            case 'atom':
                $contentType = 'application/atom+xml';
            break;
        }
        
        return $type;
    }
}

if(!function_exists('apache_request_headers'))
{
    /**
     * Reproduce apache_request_headers if it doesn't exist.
     *
     * @ignore
     **/
    function apache_request_headers()
    {
        $headers = array();
        $match = 'HTTP_';
        $matchLength = strlen($match);
        
        foreach($_SERVER as $key=>$value)
        {
            if($match === substr($key, 0, $matchLength))
            {
                // e.g. turn HTTP_ACCEPT_CHARSET into Accept-Charset
                $key = implode('-', array_map('ucfirst', explode('_',
                    strtolower(substr($key, $matchLength))
                )));
                
                // store value with normalized key
                $headers[$key] = $value;
            }
        }
        
        return $headers;
    }
}

