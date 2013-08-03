<?php

require_once '../../src/includes.php';

// create l10n instance using the strings dir and accept an optional lang param
$strings = new SimpleL10n(array('dir'=>'strings', 'lang'=>SimpleUtil::getValue($_GET, 'lang', 'en-GB')));

// get an optional name and strip any malicious tags
$name = strip_tags(SimpleUtil::getValue($_GET, 'name', 'John Doe'));

print $strings->getString('HELLO_WORLD')."\n";
print $strings->getString('GREETING', array('name'=>$name))."\n";
print $strings->getString('FAREWELL', array('name'=>'for now'))."\n";

