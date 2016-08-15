<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$query_builder = true;
$active_group = 'test';

//主库
$db['test'] = array(
    'hostname' => '127.0.0.1',
    'port' => 3306,
    'username' => 'test',
    'password' => 'test',
    'database' => 'test',
    'dbdriver' => 'mysqli',
    'pconnect' => false,
    'db_debug' => false,
    'cache_on' => false,
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
    'encrypt' => false,
    'compress' => false,
    'stricton' => true,
    'master_slave' => true,//开启主从
    'auto_switchover' => true,//开启自动切换，开启主从后才有效
    'invalid_key_cache_time' => 60,//连接失败重试间隔秒数
);


//从库
$db['test']['db_slave'][] = array(
    'hostname' => '127.0.0.1',
    'port' => 3307,
    'username' => 'test',
    'password' => 'test',
    'database' => 'test',
    'dbdriver' => 'mysqli',
    'pconnect' => false,
    'db_debug' => false,
    'cache_on' => false,
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
    'encrypt' => false,
    'compress' => false,
    'stricton' => true,
);
