<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimpleClassTest extends PHPUnit\Framework\TestCase
{
    /**
     * @var SimpleClass
     */
    protected $proxy;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() : void
    {
        $this->proxy = new SimpleClassProxy;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() : void
    {
    }

    public function readLog($msg)
    {
        $this->assertEquals($msg, array_pop($this->proxy->logs));
    }

    public function test__call()
    {
        $this->assertFalse($this->proxy->hasTestBool());
        $this->assertFalse($this->proxy->isTestBool());

        $this->proxy->enableTestBool();
        $this->assertTrue($this->proxy->isTestBool());

        $this->proxy->disableTestBool();
        $this->assertFalse($this->proxy->isTestBool());

        // property doesn't exist
        $this->assertNull($this->proxy->getTestDoesntExist());

        // property exists but is not gettable
        $this->assertNull($this->proxy->getTestNope());
        $this->proxy->setTestNope('bar');
        $this->assertNull($this->proxy->getTestNope());

        // gettable but not settable
        $this->proxy->setTestSet('baz');
        $this->assertEquals('test', $this->proxy->getTestSet());

        // setter is for string so it should ignore int
        $this->proxy->setTestStr(5);
        $this->assertEquals('test', $this->proxy->getTestStr());
        $this->readLog('Ignoring invalid value for testStr: 5');

        // normal set on a string
        $this->proxy->setTestStr('foo');
        $this->assertEquals('foo', $this->proxy->getTestStr());

        // enabling a non-boolean
        $this->proxy->enableTestStr();
        $this->assertEquals('foo', $this->proxy->getTestStr());
        $this->readLog('Ignoring enable/disable for parameter testStr which is not a boolean.');

        $this->proxy->addTestArray('foo');
        $this->assertEquals(array('foo'), $this->proxy->getTestArray());
        $this->proxy->pushTestArray('bar');

        // test set branch
        $this->assertEquals(array('foo', 'bar'), $this->proxy->getTestArray());
        $this->proxy->setTestArray('bat');
        $this->assertEquals(array('foo', 'bar', 'bat'), $this->proxy->getTestArray());

        // test set of pushable but not settable
        $this->proxy->setTestArray(array('baz'));
        $this->assertEquals(array('foo', 'bar', 'bat', 'baz'), $this->proxy->getTestArray());

        // test set of pushable/settable
        $this->proxy->pushSettable('testArray');
        $this->proxy->setTestArray(array('baz'));
        $this->assertEquals(array('baz'), $this->proxy->getTestArray());

        $this->proxy->fooBar();
        $this->readLog('Unknown method: SimpleClassProxy->fooBar');
    }

    public function testParseAction()
    {
        $this->assertEquals(array('bar', 'foo'), $this->proxy->parseActionProxy("fooBar"));
        $this->assertEquals(array('bar', ''), $this->proxy->parseActionProxy("Bar"));
        $this->assertEquals(array('fooBar', ''), $this->proxy->parseActionProxy("FooBar"));
    }

    public function testSetup()
    {
        $this->assertTrue($this->proxy->setup());
        $this->assertNull($this->proxy->setupParams());
    }

    public function testPush()
    {
        // plural property branch
        $this->proxy->pushTestDir(__DIR__);
        $this->assertEquals(array(__DIR__), $this->proxy->getTestDirs());

        // invalid value
        $this->proxy->pushTestArray(false);
        $this->readLog("Ignoring invalid value pushed to property 'testArray'.");

        // unpushable
        $this->proxy->pushTestStr(null);
        $this->readLog("Property 'testStr' is not pushable.");
    }

    public function testVerifyParameterValue()
    {
        // invalid value in subarray
        $this->proxy->pushTestArray(array('foo', array('bar', null, 'baz')));
        $this->assertEquals(array('foo', array('bar', 'baz')), $this->proxy->getTestArray());
        $this->readLog("Ignoring invalid value in testArray[1]: ");
    }

    public function testAddSetter()
    {
        $this->assertNull($this->proxy->addSetter('', function(){}));
        $this->assertNull($this->proxy->addSetter(null, function(){}));

        // 'foo' becomes $this->foo() which is callable through enter __call
        $this->assertTrue($this->proxy->addSetter('testStr', 'foo'));
        // but it will fail with null when executing validation
        $this->assertNull($this->proxy->setTestStr('foo'));
        $this->assertEquals('test', $this->proxy->getTestStr());

        $this->assertNull($this->proxy->addSetter('foo', function(){}));
        $this->readLog("Ignoring setter function for non-existant property 'foo'.");
    }

    public function testSetParams()
    {
        $params = array('testBool'=>true, 'testStr'=>'foo', 'testArray'=>array('baz', 'quuz'), 'bar'=>'bar');
        $this->proxy->setParams($params);
        $this->readLog("Ignoring set for invalid property bar.");
        $this->assertEquals($params['testStr'], $this->proxy->getTestStr());
        $this->assertEquals($params['testBool'], $this->proxy->getTestBool());
        $this->assertEquals($params['testArray'], $this->proxy->getTestArray());
    }

    public function testSetOptions()
    {
        $this->proxy->addGettable(array('options'));
        $opts = array('one'=>1, 'two'=>2);
        $this->proxy->setOptions($opts);
        $this->assertEquals($opts, $this->proxy->getOptions());
        $this->proxy->setOptions('foo');
        $this->assertEquals($opts, $this->proxy->getOptions());

        $this->assertEquals(1, $this->proxy->getOption('one'));
        $this->assertEquals(4, $this->proxy->getOption('three', 4));
    }

    public function testResolveCallable()
    {
        $this->assertNull($this->proxy->resolveCallable(array()));
        $this->assertEquals('is_callable', $this->proxy->resolveCallable('is_callable'));
        $this->assertNull($this->proxy->resolveCallable('foo', false));
    }

    public function testLog()
    {
        $this->proxy->enableBacktrace();
        $this->assertNull($this->proxy->log(array(), true));
    }
}


class SimpleClassProxy extends SimpleClass
{
    public    $logs      = array();
    protected $testArray = array();
    protected $testBool  = false;
    protected $testDirs  = array();
    protected $testStr   = 'test';
    protected $testSet   = 'test';
    protected $testNope  = 'nope';

    public function setupParams()
    {
        // coverage
        parent::setupParams();

        $this->addPushable(array('logs', 'testArray', 'testDirs'));
        $this->addSettable(array('testBool', 'testStr'));
        $this->addGettable(array('testSet'));

        $this->addSetter('testArray', 'setStringOrArray');
        $this->addSetter('testBool',  'setBoolean');
        $this->addSetter('testDirs',  'setDirectoryPath');
        $this->addSetter('testStr',   'setString');
    }

    // expose publicly for testing
    public function parseActionProxy($method)
    {
        return parent::parseAction($method);
    }

    // expose publicly for testing
    public function addSetter($name, $func)
    {
        return parent::addSetter($name, $func);
    }

    // expose publicly for testing
    public function setOptions($opts)
    {
        return parent::setOptions($opts);
    }

    // expose publicly for testing
    public function getOption($name, $default = '', $allowEmpty = true)
    {
        return parent::getOption($name, $default, $allowEmpty);
    }

    // expose publicly for testing
    public function resolveCallable($call, $resolveToThis = true)
    {
        return parent::resolveCallable($call, $resolveToThis);
    }

    public function log($msg, $parent = false)
    {
        if ($parent) {
            parent::log($msg);
        } else {
            $this->logs[] = $msg;
        }
    }
}
