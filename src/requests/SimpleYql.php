<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleYql extends SimpleRequest
{
	/**
	 * @var string
	 */
	protected $host = 'query.yahooapis.com';
	
	/**
	 * @var int
	 */
	protected $port = 80;
	
	/**
	 * @var string
	 */
	protected $path = '/v1/public/yql';
	
	/**
	 * @var string
	 */
	protected $handleMethod = 'select';
	
	public function setupParams()
	{
	    parent::setupParams();
		$this->addSettable('path');
	}
	
	/**
	 * Make a generic select query.
	 * 
	 * @param string YQL table.
	 * @param array Options for the request including keys:
	 *     <ul>
	 *     <li>q (string) : Optional raw query which will override $table.</li>
	 *     <li>where (string) : Optional where clause.</li>
	 *     <li>format (string) : Optional format, default is jso.</li>
	 *     </ul>
	**/
	public function select($table, $options = array())
	{
		$this->setOptions($options);
		$params = $this->getOption('queryParams', array());
		
		// put together query
		$query = $this->getOption('q', 'select * from '.$table);
		$where = $this->getOption('where');
		$params['q'] = $query.($where ? ' where '.$where : '');
		
		// default to json
		if('' == $this->getValue($params, 'format')){
			$params['format'] = 'json';
		}
		
		$options['queryParams'] = $params;
		
		// TODO? SimpleUtil::removeKeys($options, array('where'), $returnValues = false);
		
		return parent::query($this->path, $options);
	}
	
	/**
	 * Helper method to get the number of results from the parsed response.
	 * 
	 * @param array Parsed JSON response.
	 * @return int Number of results.
	**/
	static public function getCount($resp)
	{
		return SimpleUtil::getItemByPath($resp, 'query.count', 0);
	}
	
	/**
	 * Helper method to get the results array from the parsed response.
	 * 
	 * @param array Parsed JSON response.
	 * @return mixed Results array.
	**/
	static public function getResults($resp)
	{
		return array_pop(SimpleUtil::getItemByPath($resp, 'query.results', array()));
	}
	
	/**
	 * Helper method to get the result type from the parsed response.
	 * 
	 * @param array Parsed JSON response.
	 * @return string Result type.
	**/
	static public function getResultType($resp)
	{
		return array_shift(array_keys(SimpleUtil::getItemByPath($resp, 'query.results', array())));
	}
}

