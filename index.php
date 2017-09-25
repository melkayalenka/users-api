<?php

function __autoload($className){
	require_once "c/{$className}.php";
}

$api = explode('.', $_GET['q']);

$action = 'action_';
$action .= (isset($api[1])) ? $api[1] : 'index';

$controller = new C_Users();
$controller->getApiMethod($_SERVER['REQUEST_METHOD']);

