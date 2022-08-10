<?php

require_once("src/Engine.php");
require_once("src/Orm.php");

use TuplesOrm\Orm;
use TuplesOrm\Db;

Db::setConnection("default"     , 'mysql:host=127.0.0.1;dbname=tuples_master'   , 'root', '');
Db::setConnection("secundary"   , 'mysql:host=127.0.0.1;dbname=tuples_hr'       , 'root', '');

print_r(Db::$configs);

$test = Orm::table("cuenta")->findAll();
//print_r($test);

$test2 = Orm::table("empleado", "secundary")->findAll();
print_r($test2);