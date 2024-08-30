<?php
/* Copyright (c) 2013 Yahoo! Inc. All rights reserved.
Copyrights licensed under the MIT License. See the accompanying LICENSE file for terms. */

class SimplePageTest extends PHPUnit\Framework\TestCase
{
    protected $object;

    protected function setUp() : void
    {
        $this->object = new SimplePageProxy;
    }

    protected function tearDown() : void
    {
    }

    public function assertLog($msg)
    {
        $this->assertEquals($msg, array_pop($this->object->logs));
    }

    public function test__isset()
    {
        $this->object->addSettable(array('nameIndex', 'phase'));

        $this->object->setPhase('subtemplates');
        $this->assertFalse($this->object->__isset('foo'));

        $this->object->subtemplates = array('foo'=>'foo');
        $this->assertTrue($this->object->__isset('foo'));

        $this->object->setPhase('modules');
        $this->assertNull($this->object->__isset('foo'));

        $this->object->setPhase('fetch');
        $this->assertFalse($this->object->__isset('foo'));

        $this->object->setNameIndex(array('foo'=>'foo'));
        $this->assertTrue($this->object->__isset('foo'));

        // non-existant property/key
        $this->object->setPhase('foo');
        $this->assertFalse($this->object->__isset('foo'));

        // existing property
        $this->assertTrue($this->object->__isset('phase'));

        // existing key
        $this->object->setKey('foo', 'foo');
        $this->assertTrue($this->object->__isset('foo'));
    }

    public function test__get()
    {
        $this->object->addSettable(array('nameIndex', 'phase'));

        // unknown phase
        $this->object->setPhase('foo');
        $this->assertNull($this->object->__get('foo'));

        $this->object->setPhase('subtemplates');
        $this->object->subtemplates['foo'] = 'foo';
        $this->assertEquals('foo', $this->object->__get('foo'));
        $this->assertFalse(array_key_exists('foo', $this->object->subtemplates));

        $this->object->setPhase('modules');
        $this->assertEquals('foo', $this->object->__get('foo'));

        $this->object->setPhase('fetch');
        $this->assertNull($this->object->__get('foo'));

        $this->object->setPhase('finalize');
        $this->assertEmpty($this->object->__get('assets'));
        $this->assertEquals('finalize', $this->object->__get('phase'));
        $this->object->setKey('foo', 'foo');
        $this->assertEquals('foo', $this->object->__get('foo'));
    }

    public function testSetup()
    {
        $this->object->moduleRoot = array();
        $_SERVER['DOCUMENT_ROOT'] = __DIR__.'/../src';
        $this->object->setup();
        $this->assertTrue(count($this->object->moduleRoot) > 0);
        $this->assertNotEmpty($this->object->moduleRoot[0]);
    }

    public function testSetupParams()
    {
        // setupParams is called automatically in constructor so don't call it again
        $pushables = array_slice($this->object->getPushable(), 3);
        $this->assertEquals(array('moduleRoot'), $pushables);
        $settables = array_slice($this->object->getSettable(), 2);
        $this->assertEquals(array('meta', 'template', 'keys', 'subtemplates', 'modules'), $settables);

        $this->object->addGettable('setters');
        $setters = array_slice($this->object->getSetters(), 0);
        $this->assertTrue(array_key_exists('moduleRoot', $setters));
    }

    public function testExecuteModules()
    {
        $root = dirname(__FILE__);
        $this->object->moduleRoot[0] = $root;

        $this->object->modules = array(
            'mock'=>array('class'=>'SimpleModuleProxy'),
            'cached'=>array('class'=>'SimpleModuleCachedProxy'),
            'failed'=>array('class'=>'SimpleModuleFailedDataProxy'),
            'not_a_mod'=>array('class'=>'SimpleCacheProxy'),
            'not_a_class'=>array('class'=>'SimplyFooBarred')
        );

        $this->object->results = array(
            array('request'=>$this->object->newRequest(), 'module'=> new SimpleModuleProxy, 'name'=>'mock'),
            array('request'=>$this->object->newRequest(), 'module'=> new SimpleModuleCachedProxy, 'name'=>'cached')
        );

        $result = $this->object->executeModules();
        $this->assertEquals(5, count($result));
        $this->assertEquals(array(
            'Class SimpleModuleFailedDataProxy failed to return data.',
            'Class SimpleCacheProxy is not derived from SimpleModule, skipping.',
            'Module file SimplyFooBarred.php not found in path(s) '.$root
        ), $this->object->logs);

        $this->assertEquals(array (
            'mock' => 'rendered',
            'cached' => 'rendered',
            'failed' => null,
            'not_a_mod' => null,
            'not_a_class' => null
        ), $result);

        $this->assertEquals('data', $this->object->handleModule('mock'));
        $this->assertEquals('cached', $this->object->handleModule('cached'));
    }

    public function testVerifyModule()
    {
        // already defined in this test, should return early
        $this->assertTrue($this->object->verifyModule('SimpleModuleProxy'));

        // existing module in class attribute
        $this->object->modules['mock'] = array('class'=>'SimpleModuleProxy');
        $this->assertTrue($this->object->verifyModule('mock'));

        // actual module
        $this->assertTrue($this->object->verifyModule('RssModule'));

        // non-existant module
        $this->assertNull($this->object->verifyModule('BarModule'));
        $this->assertLog('Module file BarModule.php not found in path(s) '.$this->object->moduleRoot[0]);

        // existant file, non-existant module
        $this->object->moduleRoot[0] = dirname(__FILE__);
        $filename = $this->object->moduleRoot[0].'/FooModule.php';
        file_put_contents($filename, '');
        $result = $this->object->verifyModule('FooModule');
        unlink($this->object->moduleRoot[0].'/FooModule.php');
        // assert after to make sure file is always deleted
        $this->assertNull($result);
        $this->assertLog('Class FooModule not found in path '.$filename);
    }

    public function testHandleModule()
    {
        $this->object->modules = array(
            'mock'=>array('class'=>'SimpleModuleProxy'),
            'cached'=>array('class'=>'SimpleModuleCachedProxy'),
            'not_a_mod'=>array('class'=>'SimpleCacheProxy')
        );

        $this->assertNull($this->object->handleModule('not_a_mod'));
        $this->assertLog('Class SimpleCacheProxy is not derived from SimpleModule, skipping.');

        $this->assertEquals('data', $this->object->handleModule('mock'));

        $this->assertEquals('cached', $this->object->handleModule('cached'));
    }

    public function testFetchAll()
    {
        $this->object->results = array(
            array('request'=>$this->object->newRequest(), 'name'=>'foo'),
            array('request'=>$this->object->newRequest(), 'name'=>'bar'),
            array('request'=>$this->object->newRequest(), 'name'=>'baz')
        );

        $this->assertNull($this->object->fetchAll());

        // nameIndex should be set up
        $this->assertEquals(array(
            'foo'=>array(0),
            'bar'=>array(1),
            'baz'=>array(2)
        ), $this->object->nameIndex);

        // check for new properties
        for ($i = 0; $i < count($this->object->results); $i++) {
            $this->assertTrue($this->object->results[$i]['fetched']);
            $this->assertEquals('rendered', $this->object->results[$i]['response']);
        }
    }

    public function testNewRequest()
    {
        $this->assertTrue(SimpleUtil::isObject($this->object->newRequest(true), 'SimpleRequest'));
    }

    public function testHandleModuleData()
    {
        $this->object->modules = array(
            'false'=>array('class'=>'SimpleModuleFailedDataProxy'),
            'array'=>array('class'=>'SimpleModuleDataArrayProxy')
        );

        // failed date results in false and logs an error
        $this->assertFalse($this->object->handleModuleData('false', new SimpleModuleFailedDataProxy));
        $this->assertLog('Class SimpleModuleFailedDataProxy failed to return data.');

        $this->assertEquals('~~array~~', $this->object->handleModuleData('array', new SimpleModuleDataArrayProxy));
        // should have an entry each for the SimpleRequest, curl handle, and a resource
        // 5 objects were passed but null and a mock object should be ignored
        $this->assertEquals(3, count($this->object->results));
        $this->assertTrue(SimpleUtil::isObject($this->object->results[0]['request'], 'SimpleRequest'));
        $this->assertTrue(
            SimpleUtil::isObject($this->object->results[1]['request'], 'CurlHandle') ||
            is_resource($this->object->results[1]['request'])
        );
        $this->assertTrue(is_resource($this->object->results[2]['request']));
    }

    public function testHandleModuleResult()
    {
        $this->object->nameIndex['SimpleModuleProxy'] = array(0);
        $mod = new SimpleModuleProxy;
        $this->object->results = array(
            array('module'=>$mod, 'request'=>'', 'response'=>'resp')
        );

        $this->assertEquals('resp', $this->object->handleModuleResult('SimpleModuleProxy'));

        $this->assertEquals(1, count($this->object->assets));
    }

    public function testHandleModuleCache()
    {
        $mod = new SimpleModuleCachedProxy();

        $result = $this->object->handleModuleCache($mod);
        $this->assertEquals(array($mod->cacheObject->get()['content'], $mod->assets), $result);
        $this->assertEquals($mod->setKeys, $this->object->keys);

        $result = $this->object->handleModuleCache($mod, $mod->data);
        $this->assertEquals(array($mod->data, $mod->assets), $result);
        $this->assertEquals(array(
            'content'=>$mod->data,
            'assets'=>$mod->assets,
            'keys'=>$mod->setKeys
        ), $mod->cacheObject->get());
    }

    public function testGetKey()
    {
        $this->object->keys = array('baz'=>'BAZ');
        $this->assertEmpty($this->object->getKey('foo'));
        $this->assertEquals('BAZ', $this->object->getKey('baz'));
    }

    public function testParseAsset()
    {
        $this->object->markup = new SimpleMarkup();

        $asset = array('type'=>'js', 'content'=>'window');
        $this->assertEquals('<script>window</script>', $this->object->parseAsset($asset, 'js'));

        $asset = array('type'=>'css', 'content'=>'*{display:none;}');
        $this->assertEquals('<style>*{display:none;}</style>', $this->object->parseAsset($asset, 'css'));

        $filename = 'tmp.txt';
        file_put_contents($filename, 'p{}');
        $asset = array('type'=>'css', 'file'=>$filename);
        $result = $this->object->parseAsset($asset, 'css');
        unlink($filename);
        $this->assertEquals('<style>p{}</style>', $result);

        $asset = array();
        $this->assertNull($this->object->parseAsset($asset, 'js'));
        $this->assertLog("Misconfigured asset: ".print_r($asset, 1));
    }

    public function testParseAssets()
    {
        $this->object->markup = new SimpleMarkup();
        $this->object->assets = array(
            array('type'=>'css', 'url'=>'/css/style.css', 'attrs'=>array('media'=>'print')),
            array('type'=>'js',  'url'=>'/js/script.css'),
            array('type'=>'blob', 'content'=>'<meta>'),
            array('type'=>'unk')
        );

        $result = $this->object->parseAssets();
        $this->assertLog('Unknown asset type "unk".');
        $this->assertEquals(join("\n", array(
            '<link rel="stylesheet" type="text/css" href="/css/style.css" media="print">',
            '<script src="/js/script.css"></script>',
            '<meta>'
        )), $result);
    }

    public function testResolveTemplate()
    {
        $this->assertNull($this->object->resolveTemplate('/foo', true));
        $this->assertLog('Unable to load template: ');

        $filename = 'tmp.tpl';
        $contents = '{value}';
        file_put_contents($filename, $contents);
        $result = $this->object->resolveTemplate($filename, true);
        unlink($filename);
        $this->assertEquals($contents, $result);
    }

    public function testRender()
    {
        $testDir = dirname(__FILE__);

        $mainTpl = $testDir.'/main.tpl';
        file_put_contents($mainTpl, '{{title}}<<sub>>[[bar]]');

        $subTpl = $testDir.'/sub.tpl';
        file_put_contents($subTpl, '[[foo]]<<nested>>{{description}}');

        $nestedTpl = $testDir.'/nested.tpl';
        file_put_contents($nestedTpl, ' <nested template> ');

        // note that render won't get called since we're using a HEAD request
        $curlHandle = curl_init('https://www.google.com/');
        curl_setopt($curlHandle, CURLOPT_NOBODY, true);

        $page = new SimplePage(array(
            'template'     => $mainTpl,
            'subtemplates' => array('sub'=> $subTpl, 'nested'=>$nestedTpl),
            'moduleRoot'   => $testDir,
            'modules'      => array(
                'foo' => array(
                    'class'  => 'SimpleModuleProxy',
                    'params' => array(
                        'settable' => array('data'),
                        'data' => '[nested module]'
                    )
                ),
                'bar' => array(
                    'class'  =>'SimpleModuleProxy',
                    'params' =>array(
                        'settable' => array('data'),
                        'response' => '[test!]',
                        'data' => new SimpleRequestProxy(array(
                            'settable' => array('handle'),
                            'handle' => $curlHandle
                            //'debug'=>true
                        ))
                    )
                )
            ),
            'keys' => array(
                'title' => 'Test... ',
                'description' => '...only a '
            )
        ));

        $result = $page->render();

        unlink($mainTpl);
        unlink($subTpl);
        unlink($nestedTpl);

        $this->assertEquals('Test... [nested module] <nested template> ...only a ', $result);
    }

}

class SimplePageProxy extends SimplePage
{
    // capture logs here
    public $logs = array();

    // change access to public for mocking
    public $subtemplates = array();
    public $nameIndex = array();
    public $results = array();
    public $assets = array();
    public $keys = array();
    public $modules = array();
    public $moduleRoot = array(__DIR__.'/../src/modules');
    public $markup = null;

    public function executePhase($template, $phase, $unescaped, $open = '{{', $close = '}}')
    {
        return $template;
    }

    public function handleModuleResult($name)
    {
        return parent::handleModuleResult($name);
    }

    public function verifyModule($name)
    {
        return parent::verifyModule($name);
    }

    public function handleModule($name)
    {
        if($name === 'foo') {
            return SimpleUtil::getValue($this->modules, $name, $name);
        } else {
            return parent::handleModule($name);
        }
    }

    public function handleModuleData($name, $mod, $data = null)
    {
        return parent::handleModuleData($name, $mod, $data);
    }

    public function handleModuleCache($mod, $data = null)
    {
        return parent::handleModuleCache($mod, $data);
    }

    public function fetchAll()
    {
        return parent::fetchAll();
    }

    public function newRequest($parent = false)
    {
        return $parent ? parent::newRequest() : new SimpleRequestProxy;
    }

    public function parseAssets()
    {
        return parent::parseAssets();
    }

    public function parseAsset($asset, $type)
    {
        return parent::parseAsset($asset, $type);
    }

    public function resolveTemplate($template, $parent = false)
    {
        if($parent || is_file($template)) {
            return parent::resolveTemplate($template);
        } else {
            return SimpleUtil::getValue($this->subtemplates, $template, $template);
        }
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

class SimpleModuleDataArrayProxy extends SimpleModuleProxy
{
    public function setup()
    {
        $this->data = array(
            new SimpleRequestProxy,
            curl_init(),
            fopen(__FILE__, 'r'), // prior to php 8 it would expect a curl resource
            null,
            new SimpleCacheProxy('foo')
        );
    }
}

class SimpleModuleFailedDataProxy extends SimpleModuleProxy
{
    public $data = false;
}

class SimpleModuleCachedProxy extends SimpleModuleProxy
{
    public $data = 'cache';
    public $cacheObject = null;
    public $setKeys = array('foo'=>'FOO', 'bar'=>'BAR');
    public function setup()
    {
        $this->cacheObject = new SimpleCacheProxy('foo');
        $this->cacheObject->put(array(
            'content'=>'cached',
            'assets'=>$this->assets,
            'keys'=>$this->setKeys
        ));
    }
}

class SimpleModuleRequestProxy extends SimpleModuleProxy
{
    public function setup()
    {
        $this->data = new SimpleRequestProxy();
    }
}

class SimpleModuleProxy extends SimpleModule
{
    public $assets = array(array('type'=>'css', 'url'=>'/css/style.css'));
    public $cacheObject = null;
    public $setKeys = array();
    public $keys = array();
    public $data = 'data';
    public $params = array();

    public function getData()
    {
        return $this->data;
    }

    public function render($data)
    {
        return $data;
    }

    public function getAssets()
    {
        return $this->assets;
    }

    public function getCacheObject()
    {
        return $this->cacheObject;
    }

    public function getSetKeys()
    {
        return $this->setKeys;
    }
}

class SimpleCacheProxy extends SimpleCache
{
    public $cacheData = null;

    public function __construct($name, $config = array())
    {
    }

    public function get()
    {
        return $this->cacheData;
    }

    public function put($val)
    {
        $this->cacheData = $val;
    }
}

class SimpleRequestProxy extends SimpleRequest
{
    public $handle = null;
    public $response = 'rendered';
    public function multiQuery($objs)
    {
        $results = array();
        foreach ($objs as $o=>$obj)
        {
            $results[] = $obj->response;
        }

        return $results;
    }
}