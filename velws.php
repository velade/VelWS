<?php
namespace VelWS;

class WSS
{
  protected $host = "0.0.0.0";
  protected $port = 10443;
  protected $open = "velws_open";
  protected $close = "velws_close";
  protected $recv = "velws_recv";
  protected $transport = "tlsv1.3";
  protected $sslcert = __DIR__ . '/cert/cert.pem';
  protected $sslkey = __DIR__ . '/cert/key.pem';

  protected $server;
  protected $ssl;
  protected $changes;

  public $clients;
  
  public function __construct($options = []){
    foreach($options as $key => $val){
      $this->$key = $val;
    }
    $this->ssl = [
      'ssl' => [
        'local_cert'  => $this->sslcert,
        'local_pk'    => $this->sslkey,
        'disable_compression' => true,
        'verify_peer'         => false,
        'ssltransport' => $this->transport
      ]
    ];
  }

  protected function conn(){
    echo "\033cVelWS WSS Server v1.0 by VelHLKJ".PHP_EOL."  ";
    $ssl_context = stream_context_create($this->ssl);
    $this->tryWriteLog("Init","Creating SSL context.");
    $this->tryWriteLog("Init","Creating WSS server.");
    if(!$this->server = stream_socket_server("{$this->transport}://{$this->host}:{$this->port}",$errno,$errstr,STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,$ssl_context)){
      $this->echoMsg("System","Server startup failed! - $errstr ($errno)","red");
      die();
    }
    $this->clients[0] = $this->server;
    $this->echoMsg("System","WSS Server Listening {$this->host} {$this->port}.","green");
  }

  public function start(){
    $this->conn();
    while (true) {
      $this->changes = $this->clients;
      $write = NULL;
      $except = NULL;
      stream_select($this->changes,$write,$except,NULL); //等待消息
      if(in_array($this->server,$this->changes)){
        $_cid = $this->handshake();
        $this->try_call_user_func($this->open,$_cid);
        $_foundSocket = array_search($this->server,$this->changes);
        unset($this->changes[$_foundSocket]);
      }
      foreach($this->changes as $cid => $change){
        $buffer = stream_get_contents($change);
        if($buffer == false){
          $this->echoMsg("System","Client $cid is disconnected.","yellow");
          @fclose($change);
          unset($this->clients[$cid]);
          $this->try_call_user_func($this->close,$cid);
          continue;
        }
        $msg = $this->decode($buffer);
        $this->try_call_user_func($this->recv,$msg,$cid);
      }
    }
    fclose($this->server);
  }

  protected function handshake() {
    $client = stream_socket_accept($this->server);
    $_cid = date("YmdHis").rand(10000,99999);
    $this->clients[$_cid] = $client;
    stream_set_blocking($client,true);
    $headers = fread($client,1500);
    preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $match);
    $key = $match[1];
    $acceptkey = base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));
    $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n".
    "Upgrade: websocket\r\n".
    "Connection: Upgrade\r\n".
    "WebSocket-Origin: {$this->host}\r\n".
    "WebSocket-Location: wss://{$this->host}:{$this->port}\r\n".
    "Sec-WebSocket-Version: 13\r\n" .
    "Sec-WebSocket-Accept: $acceptkey\r\n\r\n";
    fwrite($client,$upgrade);
    $this->echoMsg("System","Client {$_cid} connected.","yellow");
    stream_set_blocking($client,false);
    return $_cid;
  }

  protected function encode($msg) {
    $b1 = 0x80 | (0x1 & 0x0f);
    $length = strlen($msg);
    if($length <= 125)
        $header = pack('CC', $b1, $length);
    elseif($length > 125 && $length < 65536)
        $header = pack('CCn', $b1, 126, $length);
    elseif($length >= 65536)
        $header = pack('CCNN', $b1, 127, $length);
    return $header.$msg;
  }

  protected function decode($buffer) {
    $length = @ord($buffer[1]) & 127;
    if($length == 126) {
      $masks = substr($buffer, 4, 4);
      $data = substr($buffer, 8);
    }elseif($length == 127) {
      $masks = substr($buffer, 10, 4);
      $data = substr($buffer, 14);
    }else {
      $masks = substr($buffer, 2, 4);
      $data = substr($buffer, 6);
    }
    $buffer = "";
    for ($i = 0; $i < strlen($data); ++$i) {
      $buffer .= $data[$i] ^ $masks[$i % 4];
    }
    return $buffer;
  }

  public function send($cid,$content) {
    $_sock = $this->clients[$cid];
    if(fwrite($_sock,$this->encode($content)) === false){
      $this->echoMsg("System","Client $cid is disconnected.","yellow");
      @fclose($_sock);
      unset($this->clients[$cid]);
      unset($this->changes[$cid]);
      $this->try_call_user_func($this->close,$cid);
      return false;
    }
    $this->echoMsg("System","Sent a message to $cid");
    return true;
  }

  public function broadcast($content){
    $this->echoMsg("System",">>>>>Start broadcast");
    foreach ($this->clients as $cid => $_sock) {
      if($cid != 0){
        $this->send($cid,$content);
      }
    }
    $this->echoMsg("System","<<<<<Broadcast finished");
  }

  protected function try_call_user_func($func){
    if(function_exists($func)){
      $args = func_get_args();
      array_splice($args,0,1);
      $this->tryWriteLog("User","Call user callback function: $func");
      return call_user_func_array($func,$args);
    }
    return false;
  }

  public function echoMsg($type,$message,$style = ""){
    $color = "\033[0m";
    switch (strtolower($style)){
      case "red":
        $color = "\033[31m";
        break;
      case "green":
        $color = "\033[32m";
        break;
      case "yellow":
        $color = "\033[33m";
        break;
      case "blue":
        $color = "\033[34m";
        break;
      case "purple":
        $color = "\033[35m";
        break;
      case "darkgreen":
        $color = "\033[36m";
        break;
      case "clear":
        $color = "\033c";
        break;
    }
    $_time = date("Y-m-d H:i:s");
    echo "{$color}[$_time][$type] $message\033[0m".PHP_EOL."  ";
    $this->tryWriteLog($type,$message);
  }

  public function tryWriteLog($type,$message){
    if($this->logpath !== ""){
      $_logfile = fopen($this->logpath,"a");
      $_time = date("Y-m-d H:i:s");
      fwrite($_logfile,"[$_time][$type] $message".PHP_EOL);
    }
  }
}

class WS extends WSS {
  protected $transport = "tcp";
  protected function conn(){
    echo "\033cVelWS WS Server v1.0 by VelHLKJ".PHP_EOL."  ";
    $this->tryWriteLog("Init","Creating WS server.");
    if(!$this->server = stream_socket_server("{$this->transport}://{$this->host}:{$this->port}",$errno,$errstr,STREAM_SERVER_BIND | STREAM_SERVER_LISTEN)){
      $this->echoMsg("System","Server startup failed! - $errstr ($errno)","red");
      die();
    }
    $this->clients[0] = $this->server;
    $this->echoMsg("System","WS Server Listening {$this->host} {$this->port}.","green");
  }
}