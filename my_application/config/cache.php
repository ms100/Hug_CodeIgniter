<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$cache_group = 'default';

$config['default'] = [
    'adapter' => 'memcached',
    'key_prefix' => 'my_',
    'servers' => [
        [
            'hostname' => '127.0.0.1',
            'port' => '11211',
            'weight' => '1',
        ],
    ],
];
