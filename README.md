# VelWS
一个简单易用的Websocket服务端库，支持ws与wss
## 使用方法
### 启动
1. 此项目只是一个类库，不能直接使用，你应该建立一个真正的服务器文件。
2. 在服务器文件中引入类库
3. 使用命名空间 VelWS\WS使用WS类或者VelWS\WSS使用WSS类
4. 实例化类
5. 调用start方法开始监听

#### 示例代码WSS
```php
header("Content-Type: text/html;charset=utf-8"); //设定字符集
require_once("velws.php"); //引入类库
use VelWS\WSS; //使用命名空间

/* 实例化类时所有参数可选 通常你只需要设定端口与证书路径*/
$ws = new WSS([
    "host" => "0.0.0.0", //监听主机
    "port" => 10443, //监听端口
    "logpath" => "", //用于指定日志记录文件，默认为空，即仅在控制台输出而不记录到文件。
    "open" => "velws_open", //有新连接打开时回调函数名，也可直接以默认名velws_open建立函数。
    "close" => "velws_close", //有连接关闭时回调函数名，也可直接以默认名velws_close建立函数。
    "recv" => "velws_recv", //收到消息时回调函数名，也可直接以默认名velws_recv建立函数。
    "transport" => "tlsv1.3", //传输协议
    "sslcert" => __DIR__ . '/cert/cert.pem', //ssl公钥文件路径
    "sslkey" => __DIR__ . '/cert/key.pem' //ssl私钥文件路径
]
);
$ws->start(); //启动服务器
```
#### 示例代码WS
```php
header("Content-Type: text/html;charset=utf-8"); //设定字符集
require_once("velws.php"); //引入类库
use VelWS\WS; //使用命名空间

/* 实例化类时所有参数可选  通常你只需要设定端口*/
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
```
### 事件处理
现在服务器已经开启了，但是仅仅这样服务器只有处理用户基本请求的能力，对于接收发送信息都没有任何的处理动作，因此我们需要对于不同的事件给出响应的处理代码，在上面的参数中设定了open close recv三个事件对应的函数名，如果你没有在选项中设置，那么他们默认分别是 velws_open velws_close velws_recv。你只需要声明对应的全局函数即可。

#### 示例代码
```php
/* 回调函数示例 */
function velws_open($cid){
    global $ws;
    //有新连接时触发
    //$cid 返回新连接的客户端内部ID
}

function velws_close($cid){
    global $ws;
    //有连接断开时触发
    //$cid 返回断开连接的客户端的内部ID
}

function velws_recv($msg,$cid){
    global $ws;
    //服务端收到来自客户端的消息时触发
    //$msg 返回收到的内容
    //$cid 返回客户端的内部ID
}
```
### 公开方法
我们也提供公开的方法来进行一些操作
```php
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
  //$msg 要显示的信息 纯Cli环境可能无法显示中文字体，这是系统字体库的问题
  //$style 可选 可设定输出的颜色，英文表示，因控制台限定，可用选择只有 red green yellow blue purple darkblue几种，也可设定clear，表示当前行输出之前清屏。
```

### 公开属性
```php
$ws->clients;
  //保存所有客户端信息的对象
  //关联数组类型
  //Key 为用户内部ID
  //Value 为用户的socket实例
```
