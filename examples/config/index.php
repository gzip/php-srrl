<?php

require_once '../../src/includes.php';

$conf = new SimpleConfig(array('dir'=>'conf'));

print '<pre>';
print_r($conf->getSetting('foo', null, array('foo'=>'bar','bat'=>'baz')))."\n";
print_r($conf->getSetting('ar', null, array('c1'=>'bat')))."\n";
print_r($conf->getSetting('foo', null, array('foo'=>'bar')))."\n";
print_r($conf->getSetting('foo', null, array('c1'=>'bat')))."\n";
print_r($conf->getSetting('foo.bar.bat', null, array('c1'=>'quz')))."\n";
print_r($conf->getSetting('foo.bar.bat', null, array('c2'=>'quz', 'c1'=>'quz')))."\n";
print_r($conf->getSetting('all.modules'))."\n";
print_r($conf->getSetting('assets.js.util', null, array('mode'=>''))."\n");
print_r($conf->getSetting('assets.js.util', null, array('mode'=>'min'))."\n");
print_r($conf->getSetting('assets.js.util', null, array('mode'=>'debug'))."\n");
print_r($conf->getSetting('assets.js.util', null, array('mode'=>'foo'))."\n");
print_r($conf->getSetting('/assets/js/SimpleUtil.js', null, array('mode'=>'min'))."\n");
print_r($conf->getSetting('foo.bar.nada', 'default')."\n");
print_r($conf->getSetting('foo.quz.baz')."\n");
print_r($conf->getSetting('foo.quz.baz', null, array('quz'=>'bat'))."\n");
$conf->setCriteria(array('quz'=>'bar'));
print_r($conf->getSetting('foo.quz.baz')."\n");
print '</pre>';

