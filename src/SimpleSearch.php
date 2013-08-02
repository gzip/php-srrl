<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleSearch
{
    // Stop words taken from http://kobesearch.cpan.org/htdocs/Lingua-StopWords/Lingua/StopWords/EN.pm.html and supplemented
    static public $stopWords = array(
        'i\'m', 'im', 'you\'re', 'he\'s', 'she\'s', 'it\'s', 'we\'re', 'they\'re', 'i\'ve', 'you\'ve', 'we\'ve', 'they\'ve',
        'i\'d', 'you\'d', 'he\'d', 'she\'d', 'we\'d', 'they\'d', 'i\'ll', 'you\'ll', 'he\'ll', 'she\'ll', 'we\'ll',
        'they\'ll', 'isn\'t', 'aren\'t', 'wasn\'t', 'weren\'t', 'hasn\'t', 'haven\'t', 'hadn\'t',
        'doesn\'t', 'don\'t', 'didn\'t', 'won\'t', 'wouldn\'t', 'shan\'t', 'shouldn\'t', 'can\'t',
        'cannot', 'couldn\'t', 'mustn\'t', 'let\'s', 'that\'s', 'who\'s', 'what\'s', 'here\'s',
        'there\'s', 'when\'s', 'where\'s', 'why\'s', 'how\'s',
        'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', 'your', 'yours', 'yourself',
        'yourselves', 'he', 'him', 'his', 'himself', 'she', 'her', 'hers', 'herself', 'it', 'its',
        'itself', 'they', 'them', 'their', 'theirs', 'themselves', 'what', 'which', 'who', 'whom',
        'this', 'that', 'these', 'those', 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has',
        'had', 'having', 'do', 'does', 'did', 'doing', 'would', 'should', 'could', 'ought', 'a', 'an', 'the', 'and', 'but', 'if', 'or',
        'because', 'as', 'until', 'while', 'of', 'at', 'by', 'for', 'with', 'about', 'against', 'between',
        'into', 'through', 'during', 'before', 'after', 'above', 'below', 'to', 'from', 'up', 'down', 'in',
        'out', 'on', 'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when',
        'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such',
        'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'we', 'very',
        'etc', 'can', 'us', 'ive', 'upon', 'let', 'unto', 'every'
    );
    
    static public $stopPatterns = array();

    /**
     * Identifies the keywords in a blob of text.
     * 
     * @param (string) The text to identify keywords in.
     * @return (bool) Whether to alphabetize the keywords before returning.
     * @access public
     * @static
    **/
    static public function getKeywords($text, $alphabetize = true)
    {
        $keywords =
            array_map('strtolower',
                array_unique(
                    explode(' ', self::removeStopWords($text))
                )
            );
        
        if($alphabetize){
            sort($keywords);
        }
        
        return $keywords;
    }
    
    /**
     * Leverages YQL to identify the keyphrases in a blob of text.
     * 
     * @param (string) The text to identify keyphrases in.
     * @return (bool) Whether to alphabetize the keywords before returning.
     * @access public
     * @static
    **/
    static public function getKeyPhrases($text, $alphabetize = true)
    {
        // TODO: use SimpleYql
        $text = str_replace('"', '', $text);
        $yql = new SimpleRequest(array('host'=>'query.yahooapis.com'/*, 'debug'=>1*/));
        $resp = $yql->query('/v1/public/yql', array(
            'queryParams' => array(
                'q' => 'select * from search.termextract where context="'.$text.'"',
                'format' => 'json'
            ),
            'parseResponse' => true
        ));
        
        $keyphrases = array();
        if($resp)
        {
            $query = $resp['query'];
            if($query['count'])
            {
                   $keyphrases = $query['results']['Result'];
               }
           }
        
        if($alphabetize){
            sort($keyphrases);
        }
        
        return $keyphrases;
    }
    
    /**
     * Removes stop words from a blob of text.
     * 
     * @param (string) The text to remove stop words from.
     * @access public
     * @static
    **/
    static public function removeStopWords($text)
    {
        if(empty(self::$stopPatterns))
        {
            foreach(self::$stopWords as $word)
            {
                self::$stopPatterns[] = '/\\b'.$word.'\\b/im';
            }
            self::$stopPatterns[] = '/\'/';
        }
        
        return SimpleString::normalizeWhitespace(
            preg_replace('/[^a-zA-Z0-9\' ]/', ' ',
                preg_replace(self::$stopPatterns, '', $text)
            )
        );
    }
}

