<?php
header("Content-Type: text/html;charset=utf-8"); //设定字符集
require_once("velws.php"); //引入类库
use VelWS\WST; //使用命名空间

$ws = new WST("localhost",10880);
$ws->trigger("customEvent","1");