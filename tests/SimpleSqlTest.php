<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleSqlTest extends PHPUnit_Framework_TestCase
{
    protected $object;

    protected function setUp($options = array())
    {
        $this->sql = new SimpleSqlProxy(array_merge(array('db'=>'my_db', 'table'=>'my_table'), $options));
    }

    protected function tearDown()
    {
    }

    public function testSetupParams()
    {
        $this->sql->setupParams();
        $this->assertEquals('my_db', $this->sql->getDb());
        $this->assertEquals('my_table', $this->sql->getTable());
    }

    public function testQuery()
    {
        // coverage only
        $this->sql->query('SQL');
    }

    public function testExec()
    {
        // coverage only
        $this->sql->exec('FAIL');
    }

    public function testPrepare()
    {
        // coverage only
        $this->sql->prepare('SQL');
    }

    public function testSelect()
    {
        $this->assertEquals('SELECT * FROM `my_table`', $this->sql->select());
        $this->assertEquals('SELECT * FROM `foo_table`', $this->sql->select(
            array('table'=>'foo_table')
        ));
        $this->assertEquals('SELECT `foo`, `bar` FROM `my_table`', $this->sql->select(
            array('fields'=>array('foo', 'bar'))
        ));
        $this->assertEquals('SELECT `foo` AS `F`, `bar` AS `B` FROM `my_table`', $this->sql->select(
            array('fields'=>array('F'=>'foo', 'B'=>'bar'))
        ));
    }

    public function testInsert()
    {
        $this->assertEquals('INSERT INTO `my_table` (`F`,`B`) VALUES ("foo",NULL)', $this->sql->insert(
            array('fields'=>array('F'=>'foo', 'B'=>null))
        ));
        $this->assertEquals('INSERT INTO `my_table` (`F`,`B`) VALUES (:F,:B)', $this->sql->insert(
            array('fields'=>array('F'=>null, 'B'=>null), 'prepare'=>true)
        ));
        $this->assertEquals('INSERT INTO `my_table` VALUES ("foo","bar")', $this->sql->insert(
            array('fields'=>array('foo', 'bar'))
        ));
        $this->assertEquals('INSERT INTO `my_table` VALUES (?,?)', $this->sql->insert(
            array('fields'=>array('foo', 'bar'), 'prepare'=>true)
        ));
    }

    public function testUpdate()
    {
        $this->assertEquals('UPDATE `my_table` SET `F`="foo", `B`=NULL WHERE 1', $this->sql->update(
            array('fields'=>array('F'=>'foo', 'B'=>null), 'where'=>1)
        ));
        $this->assertEquals('UPDATE `my_table` SET `F`=:F, `B`=:B', $this->sql->update(
            array('fields'=>array('F'=>null, 'B'=>null), 'prepare'=>true)
        ));
        $this->assertEquals('UPDATE `my_table` SET `F`=?, `B`=?', $this->sql->update(
            array('fields'=>array('F', 'B'), 'prepare'=>true)
        ));
    }

    public function testDelete()
    {
        $this->assertEquals('DELETE FROM `my_table` WHERE 1 LIMIT 1', $this->sql->delete(
            array('limit'=>1, 'where'=>1)
        ));
    }

    public function testCreate()
    {
        $this->assertEquals('CREATE TABLE `my_table` (name varchar(40), age int(3))', $this->sql->create(
            array('fields'=>array('name varchar(40)', 'age int(3)'))
        ));
        $this->assertEquals('CREATE TABLE `my_table` (`name` varchar(40), `age` int(3))', $this->sql->create(
            array('fields'=>array('name'=>'varchar(40)', 'age'=>'int(3)'))
        ));
        $this->assertEquals('CREATE TABLE `my_table` (name varchar(40), age int(3))', $this->sql->create(
            array('fields'=>array('name varchar(40)', 'age int(3)'))
        ));
        $this->assertEquals('CREATE TABLE `my_table` (`name` varchar(40), `age` int(3))', $this->sql->create(
            array('fields'=>array('name'=>'varchar(40)', 'age'=>'int(3)'))
        ));
        $this->assertEquals('CREATE INDEX foo (`foo`, `bar`) ON `my_table`', $this->sql->create(
            array('fields'=>array('foo', 'bar'), 'index'=>'foo')
        ));
        $this->assertEquals('CREATE INDEX foo (`foo`, `bar`) ON `my_table`', $this->sql->create(
            array('fields'=>array('foo'=>1, 'bar'=>1), 'index'=>'foo')
        ));
        $this->assertEquals(false, $this->sql->create(
            array('index'=>'foo')
        ));
    }

    public function testDrop()
    {
        $this->assertEquals('DROP TABLE `my_table`', $this->sql->drop());
        $this->assertEquals('DROP TABLE `foo_table`', $this->sql->drop(
            array('table'=>'foo_table')
        ));
        $this->assertEquals('DROP INDEX `foo` ON `my_table`', $this->sql->drop(
            array('index'=>'foo')
        ));
    }

    public function testAlter()
    {
        $this->assertEquals('ALTER TABLE `my_table` MODIFY foo char(10)', $this->sql->alter(
            array('action'=>'modify', 'fields'=>array('foo'=>'char(10)'))
        ));
        
        $this->assertFalse($this->sql->alter(
            array('action'=>'modify', 'fields'=>array('foo'=>'char(10)', 'bar'=>'date'))
        ));
        
        $this->assertEquals('ALTER TABLE `my_table` ADD bar date', $this->sql->alter(
            array('action'=>'add', 'fields'=>array('bar'=>'date'))
        ));
        
        $this->assertEquals('ALTER TABLE `my_table` ADD (foo char(10), bar date)', $this->sql->alter(
            array('action'=>'add', 'fields'=>array('foo'=>'char(10)', 'bar'=>'date'))
        ));
        
        $this->assertFalse($this->sql->alter(
            array('action'=>'MODIPHY', 'fields'=>array('foo'=>'char(10)'))
        ));
        
        $this->assertEquals('ALTER TABLE `my_table` DROP COLUMN foo', $this->sql->alter(
            array('action'=>'drop', 'fields'=>array('COLUMN'=>'foo'))
        ));
        
        $this->assertFalse($this->sql->alter(
            array('action'=>'MODIFY')
        ));
    }

    public function testGetDsn()
    {
        $this->assertEquals('mysql:host=localhost;dbname=my_db', $this->sql->getDsn());
        
        $this->setUp(array(
            'db'=>'a_db', 'driver'=>'sqlite', 'password'=>'pass', 'port'=>8080, 'user'=>'u'
        ));
        $this->assertEquals('sqlite:host=127.0.0.1;port=8080;user=u;password=pass;dbname=a_db', $this->sql->getDsn());
    }

    public function testGetConnection()
    {
        $sql = new SimpleSql(array('user'=>'foo', 'password'=>'bar'));
        $this->assertNull($sql->getConnection());
    }

    public function testClose()
    {
        $this->sql->close();
    }
}

class SimpleSqlProxy extends SimpleSql
{
    function getConnection()
    {
        return new PdoMock;
    }
}

class PdoMock
{
    function quote($val)
    {
        return '"' . $val . '"';
    }
    
    function __call($method, $args)
    {
        if(in_array($method, array('exec', 'query', 'prepare', 'quote')))
        {
            $sql = $args[0];
            return strpos($sql, 'FAIL') !== false ? false : $sql;
        }
        else
        {
            error_log('Unexpected call to PdoMock::'.$method);
        }
    }
}
