<?php
header("Content-Type: text/html;charset=utf-8"); //设定字符集
set_time_limit(0); //防止脚本超时
require_once("velws.php"); //引入类库
use VelWS\WS; //使用命名空间

/* 实例化类时所有参数可选 */
$ws = new WS([
    "host" => "0.0.0.0", //监听主机
    "port" => 10880, //监听端口
    "logpath" => "", //用于指定日志记录文件，默认为空，即仅在控制台输出而不记录到文件。
    "open" => "velws_open", //有新连接打开时回调函数名，也可直接以默认名velws_open建立函数。
    "close" => "velws_close", //有连接关闭时回调函数名，也可直接以默认名velws_close建立函数。
    "recv" => "velws_recv" //收到消息时回调函数名，也可直接以默认名velws_recv建立函数。
]
);
$ws->start(); //启动服务器

/* 回调函数示例 */
function velws_open($cid){
    global $ws;
    //有新连接时触发
    //$cid返回新连接的客户端内部ID
}

function velws_close($cid){
    global $ws;
    //有连接断开时触发
    //$cid返回断开连接的客户端的内部ID
}

function velws_recv($msg,$cid){
    global $ws;
    //服务端收到来自客户端的消息时触发
    //$msg返回收到的内容
    //$cid返回客户端的内部ID
}

function customEvent($arg){
    //由服务器开发者创建的自定义事件
    //就像普通的函数一样创建，也可以拥有任意数量的参数
    //事件为单向触发，不支持返回及内容回传。它是服务器预先定义的动作集合。
}

/* 公开方法 */
function 公开方法(){ //示例中用此中文名函数包起来只是为了防止运行示例时自动调用下面的这些公开方法
    global $ws;
    $ws->send($cid,$msg);
    //服务端主动向客户端发送消息
    //$cid 目标客户端
    //$msg 要发送的消息
    
    $ws->broadcast($msg);
    //服务端对所有客户端发出广播
    //$msg 广播的内容

    $ws->echoMsg($type,$msg,$style);
    //向控制台输出日志 格式：[2022-01-05 24:24:24][Type] Message
    //$type 日志前缀 表示日志的类型 纯Cli环境可能无法显示中文字体，这是系统字体库的问题
    //$msg 要显示的信息
    //$style 可选 可设定输出的颜色，英文表示，因控制台限定，可用选择只有 red green yellow blue purple darkblue几种，也可设定clear，表示当前行输出之前清屏。
}

/* 公开属性 */
$ws->clients;
//保存所有客户端信息的对象
//关联数组类型
//Key 为用户内部ID
//Value 为用户的socket实例
