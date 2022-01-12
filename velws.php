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
  protected $sslfullchain = '';

  protected $server;
  protected $ssl;
  protected $changes;

  public $clients;
  
  public function __construct($options = []){
    foreach($options as $key => $val){
      $this->$key = $val;
    }
    if($this->sslfullchain != ""){
      $this->ssl = [
        'ssl' => [
          'local_cert'  => $this->sslfullchain,
          'disable_compression' => true,
          'verify_peer'         => false,
          'ssltransport' => $this->transport,
          'allow_self_signed' => true
        ]
      ];
    }else{
      $this->ssl = [
        'ssl' => [
          'local_cert'  => $this->sslcert,
          'local_pk'    => $this->sslkey,
          'disable_compression' => true,
          'verify_peer'         => false,
          'ssltransport' => $this->transport,
          'allow_self_signed' => true
        ]
      ];
    }
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
        if(preg_match("/^\/vel-trigger\s(.+?)(\?(.+?))?$/",$msg,$func)){
          $funcname = $func[1];
          if(isset($func[3])){
            $this->try_call_trigger_func($funcname,$func[3]);
          }else{
            $this->try_call_user_func($funcname);
          }
        }else{
          $this->try_call_user_func($this->recv,$msg,$cid);
        }
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
      foreach($args as $key => $arg){
        $jsonval = json_decode($arg);
        $args[$key] = (json_last_error() === JSON_ERROR_NONE)?$jsonval:$arg;
      }
      $this->tryWriteLog("User","Call user callback function: $func");
      return call_user_func_array($func,$args);
    }
    return false;
  }
  protected function try_call_trigger_func($func,$argArray){
    if(function_exists($func)){
      $args = json_decode($argArray);
      $this->tryWriteLog("User","Call user trigger function: $func");
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

class WSST {
  protected $sock;
  protected $headers;
  protected $sslc;
  protected $host;
  protected $port;

  public function __construct($host,$port,$certchain = ""){
    $this->host = $host;
    $this->port = $port;
    $ssl = [
      "ssl"=>[
        "verify_peer" => false,
        "allow_self_signed" => true,
        "local_cert" => $certchain,
        "ssltransport" => "tlsv1.3"
      ]
    ];

    $this->sslc = stream_context_create($ssl);
    
    $this->headers = "GET / HTTP/1.1\r\n".
    "Host: $host\r\n".
    "Upgrade: websocket\r\n".
    "Connection: Upgrade\r\n".
    "Sec-WebSocket-Key: " . base64_encode(md5(uniqid() . rand(1, 8192), true))."\r\n".
    "Sec-WebSocket-Version: 13\r\n\r\n";
  }

  protected function conn(){
    $this->sock = stream_socket_client("ssl://{$this->host}:{$this->port}",$errno,$errstr,ini_get("default_socket_timeout"),STREAM_CLIENT_CONNECT,$this->sslc);
    if(!$this->sock) echo $errno,$errstr;
    fwrite($this->sock,$this->headers);
    fread($this->sock,2000);
  }

  public function trigger($funcname) {
    $this->conn();
    $args = func_get_args();
    array_splice($args,0,1);
    $argsStr = json_encode($args);
    fwrite($this->sock,$this->encode("/vel-trigger $funcname?$argsStr"));
  }

  protected function encode($msg) {
    $frameHead = array();
		$frame = '';
		$payloadLength = strlen($msg);
		$frameHead[0] = 129;

		// set mask and payload length (using 1, 3 or 9 bytes)
		if ($payloadLength>65535)
		{
			$payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
			$frameHead[1] = 255;
			for ($i = 0; $i<8; $i++)
				$frameHead[$i + 2] = bindec($payloadLengthBin[$i]);

			// most significant bit MUST be 0 (close connection if frame too big)
			if ($frameHead[2]>127)
			{
				$this->close(1004);
				return false;
			}
		}
		elseif ($payloadLength>125)
		{
			$payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
			$frameHead[1] = 254;
			$frameHead[2] = bindec($payloadLengthBin[0]);
			$frameHead[3] = bindec($payloadLengthBin[1]);
		}
		else
			$frameHead[1] = $payloadLength + 128;

		// convert frame-head to string:
		foreach (array_keys($frameHead) as $i)
			$frameHead[$i] = chr($frameHead[$i]);
			// generate a random mask:
			$mask = array();
			for ($i = 0; $i<4; $i++)
				$mask[$i] = chr(rand(0, 255));

			$frameHead = array_merge($frameHead, $mask);
		$frame = implode('', $frameHead);
		// append payload to frame:
		for ($i = 0; $i<$payloadLength; $i++)
			$frame .=$msg[$i] ^ $mask[$i % 4];

		return $frame;
  }
}

class WST extends WSST {
  protected function conn(){
    $this->sock = stream_socket_client("tcp://{$this->host}:{$this->port}",$errno,$errstr,ini_get("default_socket_timeout"),STREAM_CLIENT_CONNECT);
    if(!$this->sock) echo $errno,$errstr;
    fwrite($this->sock,$this->headers);
    fread($this->sock,2000);
  }
}