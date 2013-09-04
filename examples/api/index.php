<?php

require_once '../../src/includes.php';

class ContactsApi extends SimpleApi
{
    function setup()
    {
        $this->setSqlOptions(array
        (
            'db'=>'test_db',
            'table'=>'contacts',
            'user'=>'test_user',
            'password'=>'testtest'
        ));
        
        $this->addResource(array(
            'name'=>'contact'
        ));
        
        parent::setup();
    }
    // TODO: implement accept vcard
}

$api = new ContactsApi(array('root'=>'/examples/api'));
$api->route();

/*
Host: simple.local
Content-Type: application/json

{"first_name": "gee","last_name": "zee","address": "123 st","city": "sb","state": "ca","phone": "805 805 8050"}
*/

