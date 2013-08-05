<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleSql extends SimpleClass
{
    /**
     * @var string
     */
    protected $dsn = null;
    
    /**
     * @var string
     */
    protected $driver = 'mysql';
    
    /**
     * @var string
     */
    protected $host = 'localhost';
    
    /**
     * @var string
     */
    protected $port = null;
    
    /**
     * @var string
     */
    protected $user = null;
    
    /**
     * @var string
     */
    protected $password = null;
    
    /**
     * @var string
     */
    protected $db = null;
    
    /**
     * @var string
     */
    protected $persistent = false;
    
    /**
     * @var string
     */
    protected $connection = null;
    
    /**
     * @var string
     */
    protected $table = null;
    
    /**
     * Called by SimpleClass::__construct prior to setParams.
     * 
     * @return void
     **/
    public function setupParams()
    {
        $this->addSettable(array('db', 'driver', 'host', 'password', 'persistent', 'port', 'table', 'user'));
        //$this->addGettable(array());
    }
    
    public function query($sql)
    {
        return $this->action($sql);
    }
    
    public function exec($sql)
    {
        return $this->action($sql, 'exec');
    }
    
    public function prepare($sql)
    {
        return $this->action($sql, 'prepare');
    }
    
    public function getLastInsertId()
    {
        return $this->action(null, 'lastInsertId');
    }
    
    public function getErrorInfo()
    {
        return $this->action(null, 'errorInfo');
    }
    
    protected function action($options = null, $method = 'query')
    {
        $result = false;
        $pdo = $this->getConnection();
        if($pdo){
            try
            {
                $result = is_null($options) ? $pdo->{$method}() : $pdo->{$method}($options);
            }
            catch(Exception $e)
            {
                $this->log($e->getMessage());
            }
        }
        else
        {
            // log
        }
        return $result;
    }
    /*
    public function execute($statement, $values)
    {
        $result = false;
        if($statement instanceof PDOStatement){
            $result = $statement->execute($values);
        }
        else    
        {
            // log
        }
        return $result;
    }
    */
    
    public function select($options = array())
    {
        return $this->handleSql('select', $options);
    }
    
    public function insert($options)
    {
        return $this->handleSql('insert', $options);
    }
    
    public function update($options)
    {
        return $this->handleSql('update', $options);
    }
    
    public function delete($options)
    {
        return $this->handleSql('delete', $options);
    }
    
    public function create($options)
    {
        $this->setOptions($options);
        $table  = $this->getTableName();
        $index  = $this->getOption('index');
        $verb   = $index ? 'index' : 'create';
        $fields = SimpleString::wrap($this->handleFields($this->getOption('fields'), $verb), ' (', ')');
        if($fields)
        {
            if($index)
            {
                $query = 'CREATE INDEX '.$index.$fields.' ON '.$table;
            }
            else
            {
                $query = 'CREATE TABLE '.$table.$fields;
            }
            
            $result = $this->exec($query);
        }
        else
        {
            $result = false;
            // log
        }
        
        return $result;
    }
    
    public function drop($options = array())
    {
        $this->setOptions($options);
        $table  = $this->getTableName();
        $index  = SimpleString::wrap($this->getOption('index'), '`', '`');
        if($index)
        {
            $query = 'DROP INDEX '.$index.' ON '.$table;
        }
        else
        {
            $query = 'DROP TABLE '.$table;
        }
        
        return $this->exec($query);
    }
    
    public function alter($options)
    {
        $this->setOptions($options);
        $table  = $this->getTableName();
        $action  = strtoupper($this->getOption('action'));
        $fields  = $this->getOption('fields');
        
        $result = false;
        $supported = array('ADD', 'MODIFY', 'CHANGE', 'DROP');
        
        $fieldSql = SimpleString::buildParams($fields, '', ', ', ' ', null);
        
        if (count($fields) > 1) {
            if ($action === 'ADD') {
                $fieldSql = '(' . $fieldSql . ')';
            } else {
                return false;
            }
        }
        
        if($fields && in_array($action, $supported))
        {
            $query = 'ALTER TABLE '.$table.' '.$action.' '.$fieldSql;
            $result = $this->exec($query);
        }
        else
        {
            // log expected $fields to be an array and $action to be one of $supported
        }
        
        return $result;
    }
    
    protected function handleSql($verb, $options)
    {
        $this->setOptions($options);
        $method = 'exec';
        $prepare = $this->getOption('prepare', false);
        $table   = $this->getTableName();
        $fields  = $this->getOption('fields', '*');
        // TODO accept array for where?
        $where   = SimpleString::wrap($this->getOption('where'), ' WHERE ');
        $limit   = SimpleString::wrap($this->getOption('limit'), ' LIMIT ');
        $order   = '';
        $group   = '';
        
        switch($verb){
            case 'select':
                $method = 'query';
                $order = SimpleString::wrap($this->getOption('order'), ' ORDER BY ');
                $group = SimpleString::wrap($this->getOption('group'), ' GROUP BY ');
                $query = 'SELECT '.$this->handleFields($fields, $verb).' FROM '.$table;
            break;
            case 'insert':
                $query = 'INSERT INTO '.$table.$this->handleValues($fields, $verb, $prepare);
            break;
            case 'update':
                $query = 'UPDATE '.$table.' SET'.$this->handleValues($fields, $verb, $prepare);
            break;
            case 'delete':
                $query = 'DELETE FROM '.$table;
            break;
        }
        
        $query .= $where.$order.$group.$limit;
        
        //$this->log($query);
        
        return $prepare ? $this->prepare($query) : $this->{$method}($query);
    }
    
    protected function handleFields($fields, $verb = 'select')
    {
        $result = '';
        if(is_string($fields))
        {
           $result = $fields;
        }
        else if(is_array($fields))
        {
            foreach($fields as $key => $value)
            {
                $isNumeric = is_numeric($key);
                $result .= ($result ? ', ' : '');
                
                if($verb === 'select')
                {
                    $result .= '`'.$value.'`'.(is_numeric($key) ? '' : ' AS `'.$key.'`');
                }
                else if($verb === 'create')
                {
                    $result .= $isNumeric ? $value : '`'.$key.'` '.$value;
                }
                else if($verb === 'index')
                {
                    $result .= '`'.($isNumeric ? $value : $key).'`';
                }
            }
        }
        else
        {
            // log
        }
        
        return $result;
    }
    
    protected function handleValues($vals, $verb, $prepare = false)
    {
        $result = '';
        $values = '';
        if(is_array($vals))
        {
            $fields = $verb === 'update' ? array() : '';
            $isNumeric = array_key_exists(0, $vals);
            foreach($vals as $key => $val)
            {
                // handle prepared
                if($prepare)
                {
                    $value = $isNumeric ? '?' : ':'.$key;
                }
                // handle null
                else if(is_null($val))
                {
                    $value = 'NULL';
                }
                // escape normal value
                else
                {
                    $connection = $this->getConnection();
                    $value = $connection->quote($val);
                }
                
                $key = $prepare && $isNumeric ? '`'.$val.'`' : '`'.$key.'`';
                
                // insert
                if(is_string($fields))
                {
                    if(!$isNumeric){
                        $fields .= ($fields ? ',' : '').$key;
                    }
                    
                    $values .= ($values ? ',' : '').$value;
                }
                // update
                else
                {
                    $fields[$key] = $value;
                }
            }
            
            if(!empty($fields) || !empty($values))
            {
                // insert
                if(is_string($fields))
                {
                    $result = SimpleString::wrap($fields, ' (', ')').' VALUES ('.$values.')';
                }
                // update
                else
                {
                    $result = SimpleString::buildParams($fields, ' ', ', ', '=', null);
                }
            }
            
        }
        else
        {
            // log
        }
        
        return $result;
    }
    
    public function getDsn()
    {
        if(is_null($this->dsn))
        {
            if($this->port && $this->host === 'localhost'){
                $this->host = '127.0.0.1';
            }
            $port = SimpleString::wrap($this->port, ';port=');
            $user = SimpleString::wrap($this->user, ';user=');
            $pass = SimpleString::wrap($this->password, ';password=');
            $this->dsn = $this->driver.':host='.$this->host.$port.$user.$pass.';dbname='.$this->db;
        }
        
        return $this->dsn;
    }
    
    public function getConnection()
    {
        if(is_null($this->connection)){
            try {
                $this->connection = new PDO($this->getDsn(), $this->user, $this->password, array(
                    PDO::ATTR_PERSISTENT => $this->persistent
                ));
                //$this->connection->exec("SET CHARACTER SET utf8");
            } catch(Exception $e) {
                $this->log($e->getMessage());
            }
        }
        
        return $this->connection;
    }
    
    protected function getTableName()
    {
        return SimpleString::wrap($this->getOption('table', $this->table), '`', '`');
    }
    
    public function setAttribute($key, $value)
    {
        if(!is_null($this->connection)){
            $this->connection->setAttribute($key, $value);
        }
        else
        {
            // log
        }
        
        return $this->connection;
    }
    
    /*
    public function setAttributes($attributes)
    {
        if(is_array($attributes)){
            foreach($attributes as $key => $value){
                $this->setAttribute($key, $value);
            }
        }
        else
        {
            // log
        }
        
        return $this->connection;
    }
    */
    public function close()
    {
        if(!is_null($this->getConnection())){
            $this->connection = null;
        }
    }
}

