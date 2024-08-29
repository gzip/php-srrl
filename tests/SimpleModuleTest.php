<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleModuleTest extends PHPUnit\Framework\TestCase
{
    /**
     * @var SimpleModule
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() : void
    {
        $this->object = new SimpleModuleBasicProxy;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() : void
    {
    }

    public function testSetupParams()
    {
        // setupParams is called automatically in constructor so don't call it again
        $gettables = $this->object->getGettable();
        $this->assertEquals(array('data', 'setKeys', 'final'), $gettables);
        $settables = array_slice($this->object->getSettable(), 2);
        $this->assertEquals(array('page', 'name', 'cacheKey', 'cacheDir'), $settables);
    }

    public function testSetup()
    {
        $this->assertTrue($this->object->setup());
    }

    public function testGetCacheObject()
    {
        // null if cacheDir and cacheKey are not set
        $this->assertNull($this->object->getCacheObject());

        $this->object->setCacheDir(__DIR__);
        $this->object->setCacheKey('mod');
        $cache = $this->object->getCacheObject();
        $this->assertEquals('SimpleCache', get_class($cache));
    }

    public function testGetData()
    {
        $this->assertTrue($this->object->getData());
    }

    public function testRender()
    {
        $this->assertEquals('foo', $this->object->render('foo'));
    }

    public function testGetAssets()
    {
        $this->assertEquals(array(), $this->object->getAssets());
    }

    public function testSetPageTitle()
    {
        $this->object->setPage(new SimplePageMock);
        $this->assertEquals('SimplePageMock', get_class($this->object->getPage()));
        $this->assertEquals('title:foo', $this->object->setPageTitle('foo'));
    }
}

class SimpleModuleBasicProxy extends SimpleModule
{
    public function setPageTitle($title)
    {
        return parent::setPageTitle($title);
    }
}

class SimplePageMock
{
    public function setKey($key, $value)
    {
        return "$key:$value";
    }
}
