# VelWS
一个简单易用的Websocket服务端库，支持ws与wss
## 使用方法
### 编写服务器文件
1. 此项目只是一个类库，不能直接使用，你应该建立一个真正的服务器文件。
2. **添加防止超时的代码，非常重要，不添加服务器会在一定时间后因为超时而被关闭**
3. 在服务器文件中引入类库
4. 使用命名空间 VelWS\WS使用WS类 或者 VelWS\WSS使用WSS类
5. 实例化类
6. 调用start方法开始监听

#### 示例代码WSS
```php
header("Content-Type: text/html;charset=utf-8"); //设定字符集
set_time_limit(0); //防止脚本超时
require_once("velws.php"); //引入类库
use VelWS\WSS; //使用命名空间

/* 实例化类时所有参数可选 通常你只需要设定端口与证书路径*/
$ws = new WSS([
    "host" => "0.0.0.0", //监听主机
    "port" => 10443, //监听端口
    "logpath" => "", //用于指定日志记录文件，默认为空，即仅在控制台输出而不记录到文件
    "open" => "velws_open", //有新连接打开时回调函数名，也可直接以默认名velws_open建立函数
    "close" => "velws_close", //有连接关闭时回调函数名，也可直接以默认名velws_close建立函数
    "recv" => "velws_recv", //收到消息时回调函数名，也可直接以默认名velws_recv建立函数
    "transport" => "tlsv1.3", //传输协议
    "sslcert" => __DIR__ . '/cert/cert.pem', //ssl公钥文件路径
    "sslkey" => __DIR__ . '/cert/key.pem', //ssl私钥文件路径
    "sslfullchain" => __DIR__ . '/cert/certchain.pem' //ssl完整链，传此参数可替代分开的sslcert与sslkey，此参数优先于其它证书设定
]
);
$ws->start(); //启动服务器
```
####  - 通常实际使用的简化代码
```php
header("Content-Type: text/html;charset=utf-8"); //设定字符集
set_time_limit(0); //防止脚本超时
require_once("velws.php"); //引入类库
use VelWS\WSS; //使用命名空间

/*通常你只需要设定端口与证书链*/
$ws = new WSS(["port" => 10880,"sslfullchain" => __DIR__."/cert/fullchain.pem"]);
$ws->start(); //启动服务器
```
#### 示例代码WS
```php
header("Content-Type: text/html;charset=utf-8"); //设定字符集
set_time_limit(0); //防止脚本超时
require_once("velws.php"); //引入类库
use VelWS\WS; //使用命名空间

/* 实例化类时所有参数可选  通常你只需要设定端口*/
$ws = new WS([
    "host" => "0.0.0.0", //监听主机
    "port" => 10880, //监听端口
    "logpath" => "", //用于指定日志记录文件，默认为空，即仅在控制台输出而不记录到文件
    "open" => "velws_open", //有新连接打开时回调函数名，也可直接以默认名velws_open建立函数
    "close" => "velws_close", //有连接关闭时回调函数名，也可直接以默认名velws_close建立函数
    "recv" => "velws_recv" //收到消息时回调函数名，也可直接以默认名velws_recv建立函数
]
);
$ws->start(); //启动服务器
```
####  - 通常实际使用的简化代码
```php
header("Content-Type: text/html;charset=utf-8"); //设定字符集
set_time_limit(0); //防止脚本超时
require_once("velws.php"); //引入类库
use VelWS\WS; //使用命名空间

/*通常你只需要设定端口*/
$ws = new WS(["port" => 10880]);
$ws->start(); //启动服务器
```
### 运行服务器文件
因为一般的网站服务器都具有强制超时的限制，通常不能够长时间运行一个文件，且浏览器开启服务器文件大概率会导致服务器无法真正关闭，也无法正常输出日志，因为分页会处于假死状态。因此服务器文件应该在控制台通过PHP命令直接运行，而不通过Apache等网站服务器。例如
```zsh
user@user-pc ~ % php /web/path/server.php
```
如果服务器运行正常，你应该能看到（在控制台中代码具有颜色）
```zsh
VelWS WSS Server v1.0 by VelHLKJ
  [2022-01-10 12:13:08][System] WSS Server Listening 0.0.0.0 10443.
```
### 系统事件处理
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
### 外部事件调用
为了方便从服务器外部的PHP调用发送推送等功能，VelWS提供了一种方式来调用**事先被定义好的**自定义事件，这些事件在书写服务器文件时写在其中，在服务器执行过程中不可变动，并且是单向的触发。
提供此功能的目的是解决以往的一个问题，以往服务器端想要在发生数据变更时及时的通知客户端就需要不停的轮询数据库，这无疑会增加数据库的负担，因此基于事件触发的更新是一个优秀的解决方案，而此功能正是在这方面可以大显身手。例如当一个内容更新时，我们可以在处理内容的数据库更新同时利用此功能向WS服务器触发一个通知事件，这样数据的监控基于接口的触发，接口的触发基于前端的用户事件，及时性与性能都得到了最佳的保障。

#### WSS服务器触发方法
```php
require_once("velws.php"); //引入类库
use VelWS\WSST; //使用命名空间

//初始化WSST类，需要传递网址（不带http wss ws等协议前缀），端口号，与SSL证书的链文件路径（fullchain）。
$ws = new WSST("localhost",10443,__DIR__."/cert/certchain.pem");
$ws->trigger("customEvent",arg1,arg2,.....); //触发事件，第一个参数是事件名，也就是定义在服务器文件中的函数名，之后的参数为事件的参数，可以有任意多个。
```
#### WS服务器触发方法
```php
require_once("velws.php"); //引入类库
use VelWS\WST; //使用命名空间

//初始化WSST类，需要传递网址（不带http wss ws等协议前缀），端口号。
$ws = new WST("localhost",10443);
$ws->trigger("customEvent",arg1,arg2,.....); //触发事件，第一个参数是事件名，也就是定义在服务器文件中的函数名，之后的参数为事件的参数，可以有任意多个。
```
