<?php

require_once '../../src/includes.php';

class ContactsApi extends SimpleApi
{
    protected $resource = 'contact';
    protected $resources = 'contacts';
    protected $routes = array
    (
        // TODO? $this->addResource('contact')
        array('route'=>'/api/contacts', 'method'=>'resources', 'verbs'=>array('GET', 'POST', 'PUT', 'DELETE', 'OPTIONS')),
        array('route'=>'/api/contact/([0-9]+)', 'method'=>'resource',
              'type'=>'regex', 'matchKeys'=>array('id'), 'verbs'=>array('GET', 'PUT', 'DELETE', 'OPTIONS')),
        array('route'=>'/api/contact', 'method'=>'resource', 'verbs'=>array('POST', 'OPTIONS')),
        array('route'=>'/api/', 'method'=>'routes')
    );
    
    protected $sqlOptions = array
    (
        'db'=>'test_db',
        'table'=>'contacts',
        'user'=>'test_user',
        'password'=>'testtest'
    );
    
    // TODO: implement accept vcard
}

$api = new ContactsApi;
// remove examples dir so we have cleaner routes
$uri = str_replace('/examples', '', $api->getPath());
//SimpleUtil::log($uri);
// normally we wouldn't pass the $uri arg
$api->route($uri);

/*
Host: simple.local
Content-Type: application/json

{"first_name": "gee","last_name": "zee","address": "123 st","city": "sb","state": "ca","phone": "805 805 8050"}
*/

