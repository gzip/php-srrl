<?php

class SimpleUtilTest extends PHPUnit\Framework\TestCase
{
    public function testGetValue()
    {
        $ar = array('foo'=>'FOO', 'bar'=>'BAR', 'empty'=>'');
        $this->assertEquals('FOO', SimpleUtil::getValue($ar, 'foo'));
        $this->assertEquals('FOO', SimpleUtil::getValue($ar, array('baz', 'foo')));
        $this->assertEquals('BAZ', SimpleUtil::getValue($ar, 'baz', 'BAZ'));
        $this->assertEquals('NONE', SimpleUtil::getValue($ar, 'empty', 'NONE', false));
        $this->assertEquals('NONE', SimpleUtil::getValue($ar, array('baz', 'empty'), 'NONE', false));
        $this->assertEquals('', SimpleUtil::getValue(false, 'baz'));
    }

    public function testIsObject()
    {
        $obj = new StdClass;
        $this->assertTrue(SimpleUtil::isObject($obj));
        $this->assertTrue(SimpleUtil::isObject($obj, 'StdClass'));
        $this->assertFalse(SimpleUtil::isObject($obj, 'Class'));
        $this->assertFalse(SimpleUtil::isObject(''));
    }

    public function testGetItemByPath()
    {
        $ar = array('foo'=>'FOO', 'bar'=>array('baz'=>'BAZ'));
        $obj = json_decode('{"foo":"FOO","bar":{"baz":"BAZ"}}');
        $this->assertEquals('FOO', SimpleUtil::getItemByPath($ar, 'foo'));
        $this->assertEquals('FOO', SimpleUtil::getItemByPath($obj, 'foo'));
        $this->assertEquals('BAZ', SimpleUtil::getItemByPath($ar, 'bar.baz'));
        $this->assertEquals('BAZ', SimpleUtil::getItemByPath($obj, 'bar.baz'));
        $this->assertEquals('QUUX', SimpleUtil::getItemByPath($ar, 'qux', 'QUUX'));
        $this->assertEquals('QUUX', SimpleUtil::getItemByPath($obj, 'qux', 'QUUX'));
        $this->assertEquals('QUUX', SimpleUtil::getItemByPath(false, 'qux', 'QUUX'));
    }

    public function testSetItemByPath()
    {
        $ar = array();
        $obj = new StdClass;
        SimpleUtil::setItemByPath($ar, 'foo', 'FOO');
        $this->assertEquals('FOO', $ar['foo']);
        SimpleUtil::setItemByPath($obj, 'foo', 'FOO');
        $this->assertEquals('FOO', $obj->foo);
        SimpleUtil::setItemByPath($ar, 'bar.baz', 'BAZ');
        $this->assertEquals('BAZ', $ar['bar']['baz']);
        SimpleUtil::setItemByPath($obj, 'bar.baz', 'BAZ');
        $this->assertEquals('BAZ', $obj->bar->baz);
        $this->assertFalse(SimpleUtil::setItemByPath($obj, null, ''));
    }

    public function testGetResourceId()
    {
        $res = fopen(__FILE__, 'r');
        $this->assertIsInt(SimpleUtil::getResourceId($res));
        $this->assertNotEquals(0, SimpleUtil::getResourceId($res));
        $this->assertEquals(0, SimpleUtil::getResourceId(false));
        fclose($res);
    }

    public function testArrayVal()
    {
        $this->assertIsArray(SimpleUtil::arrayVal(array()));
        $this->assertIsArray(SimpleUtil::arrayVal(1));
        $this->assertEquals(array(array('foo'=>'bar')), SimpleUtil::arrayVal(array('foo'=>'bar')));
    }

    public function testLog()
    {
        $this->assertNull(SimpleUtil::log(1));
        SimpleUtil::log(array());
        SimpleUtil::log(false);
    }
}
