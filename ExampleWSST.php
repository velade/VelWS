<?php
header("Content-Type: text/html;charset=utf-8"); //设定字符集
require_once("velws.php"); //引入类库
use VelWS\WSST; //使用命名空间

$ws = new WSST("localhost",10443,__DIR__."/cert/certchain.pem");
$ws->trigger("customEvent","1");