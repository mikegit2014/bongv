<?php

header("Content-type: text/html; charset=utf-8");

ini_set('display_errors', '1');

ini_set('date.timezone', 'Asia/Shanghai');

error_reporting(E_ALL ^ E_NOTICE);



if (get_magic_quotes_gpc()) {

 function stripslashes_deep($value){

  $value = is_array($value) ?

  array_map('stripslashes_deep', $value) :

  stripslashes($value);

  return $value;

 }

 $_POST = array_map('stripslashes_deep', $_POST);

 $_GET = array_map('stripslashes_deep', $_GET);

 $_COOKIE = array_map('stripslashes_deep', $_COOKIE);

}

define('APP_NAME', 'cms');

define('CONF_PATH','./Conf/');

define('TMPL_PATH','./tpl/');
define('RES_TMPL_PATH','/tpl/');
define('STATIC_TMPL_PATH','/tpl/');

$GLOBALS['_beginTime'] = microtime(TRUE);

define('MEMORY_LIMIT_ON',function_exists('memory_get_usage'));

define('CORE','./ThinkPHP/');

if(MEMORY_LIMIT_ON) $GLOBALS['_startUseMems'] = memory_get_usage();

define('APP_PATH','./app/');

define('RUNTIME_PATH','./runtime/');

define('APP_DEBUG',false);

define('THINK_PATH','./ThinkPHP/');

$runtime = defined('MODE_NAME')?'~'.strtolower(MODE_NAME).'_runtime.php':'~runtime.php';

if(!APP_DEBUG && is_file(RUNTIME_FILE)) {

    require RUNTIME_FILE;

}else{

    require THINK_PATH.'Common/runtime.php';

}

?>