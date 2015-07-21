<?php
class Wechat {

	private $data = array();
	private $wxcon = array();
	private $token = '';
	private $wxopen_id;
	private $wxopen_token;
	private $wxopen_decy_key;
	private $f_appid;

	public function __construct($token,$f_appid){
		// $this->auth($token) || exit;
		$this->wxopen_id = C('wxopen_appid');
		$this->wxopen_token = C('wxopen_token');
		$this->wxopen_decy_key = C('wxopen_decy_key');
		$this->f_appid = $f_appid;
		if(IS_GET){
			echo($_GET['echostr']);exit;
		} else {
			$xml = file_get_contents("php://input");

			file_put_contents('encyrpt_msg.txt', $xml.$_SERVER['REQUEST_URI']."\r\n",FILE_APPEND);
			// 使用开放平台的接口是，必须使用的。
			if(!empty($this->f_appid)){
				parse_str($_SERVER['QUERY_STRING'], $param);

				$appid = $this->wxopen_id;
				$encode_key = $this->wxopen_decy_key;
				$newtoken = $this->wxopen_token;
				
				$decry_xml = $this->decrypt_wx_msg($newtoken,$encode_key,$appid,$xml,$param);
				file_put_contents('encyrpt_msg.txt', $decry_xml."\r\n",FILE_APPEND);
				$xml = $decry_xml;
			}
			
			
			$xml = new SimpleXMLElement($xml);
			$xml || exit;
			
	        foreach ($xml as $key => $value) {
	        	$this->data[$key] = strval($value);
	        }
		}
		
	}


	/*使用新的公众号绑定后,微信发的信息一律都是密文的,因此需要解密*/
	public function decrypt_wx_msg($token,$encodingAesKey,$appId,$encryptMsg,$encry_param){
		$pc = new WXBizMsgCrypt($token, $encodingAesKey, $appId);

		$xml_tree = new DOMDocument();
		$xml_tree->loadXML($encryptMsg);
		$array_e = $xml_tree->getElementsByTagName('Encrypt');
		$encrypt = $array_e->item(0)->nodeValue;
		$timeStamp = $encry_param['timestamp'];
		$nonce = $encry_param['nonce'];
		$msg_sign = $encry_param['msg_signature'];

		$format = "<xml><ToUserName><![CDATA[toUser]]></ToUserName><Encrypt><![CDATA[%s]]></Encrypt></xml>";
		$from_xml = sprintf($format, $encrypt);

		// file_put_contents('encyrpt_msg.xt', "<?php \nreturn ".var_export($encry_param, true) . ";\r\n",FILE_APPEND);			
			
		// 第三方收到公众号平台发送的消息
		$msg = '';
		$errCode = $pc->decryptMsg($msg_sign, $timeStamp, $nonce, $from_xml, $msg);
		if ($errCode == 0) {
			// file_put_contents('encyrpt_msg.txt', $msg."\r\n",FILE_APPEND);			
			return $msg;
		} else {
			// print($errCode . "\n");
			return '';
			file_put_contents('encyrpt_msg.txt', $errCode."\r\n",FILE_APPEND);			
		}
	}

	/*公众平台发送的消息内容将为纯密文，公众账号回复的消息体也为纯密文,因此需要加密*/
	public function encrypt_wx_msg($token,$encodingAesKey,$appId,$text,$param){
		$pc = new WXBizMsgCrypt($token, $encodingAesKey, $appId);
		$encryptMsg = '';
		$timeStamp = $param['timeStamp'];
		$nonce = $param['nonce'];
		$errCode = $pc->encryptMsg($text, $timeStamp, $nonce, $encryptMsg);
		if ($errCode == 0) {
			// echo htmlentities($encryptMsg);
			return $encryptMsg;
		} else {
			// return '<xml></xml>';
			// file_put_contents(, data)
			return '';
			file_put_contents('encyrpt_msg.txt', $errCode."\r\n",FILE_APPEND);
		}

	}
	/*生成加密的nonce随机串*/
	public function set_nonce(){
		$rand_str = '012345789abcdefghijklmnopqrstuvwxyz';
		$max = strlen($rand_str) - 1;
		for ($i=0; $i<=10 ; $i++) { 
			$nonce .= $rand_str[mt_rand(0, $max)];
		}
		return $nonce;
	}
	/**
	 * 获取微信推送的数据
	 * @return array 转换为数组后的数据
	 */
	public function request(){
       	return $this->data;
	}

	/**
	 * * 响应微信发送的信息（自动回复）
	 * @param  string $to      接收用户名
	 * @param  string $from    发送者用户名
	 * @param  array  $content 回复信息，文本信息为string类型
	 * @param  string $type    消息类型
	 * @param  string $flag    是否新标刚接受到的信息
	 * @return string          XML字符串
	 */
	public function response($content, $type = 'text', $flag = 0){
		/* 基础数据 */
		$this->data = array(
			'ToUserName'   => $this->data['FromUserName'],
			'FromUserName' => $this->data['ToUserName'],
			'CreateTime'   => NOW_TIME,
			'MsgType'      => $type,
		);

		/* 添加类型数据 */
		$this->$type($content);

		/* 添加状态 */
		$this->data['FuncFlag'] = $flag;

		/* 转换数据为XML */
		$xml = new SimpleXMLElement('<xml></xml>');
		$this->data2xml($xml, $this->data);
		$xml_str = $xml->asXML();
		// 使用微信开放平台时或微信平台：消息加解密方式->安全模式，必须开启这个
		if(!empty($this->f_appid)){
			$appId = $this->wxopen_id;
			$encodingAesKey = $this->wxopen_decy_key;
			$newtoken = $this->wxopen_token;
			$param['timeStamp'] = time();
			$param['nonce'] = $this->set_nonce();
			$encry_xml = $this->encrypt_wx_msg($newtoken,$encodingAesKey,$appId,$xml_str,$param);		
			// file_put_contents('encyrpt_msg.txt', $encry_xml."\r\n",FILE_APPEND);
			$xml_str = $encry_xml;
		}
		
		// exit($xml->asXML());
		exit($xml_str);
	}

	/**
	 * 回复文本信息
	 * @param  string $content 要回复的信息
	 */
	private function text($content){
		$this->data['Content'] = $content;
	}

	/**
	 * 回复音乐信息
	 * @param  string $content 要回复的音乐
	 */
	private function music($music){
		list(
			$music['Title'], 
			$music['Description'], 
			$music['MusicUrl'], 
			$music['HQMusicUrl']
		) = $music;
		$this->data['Music'] = $music;
	}

	/**
	 * 回复图文信息
	 * @param  string $news 要回复的图文内容
	 */
	private function news($news){
		$articles = array();
		foreach ($news as $key => $value) {
			list(
				$articles[$key]['Title'],
				$articles[$key]['Description'],
				$articles[$key]['PicUrl'],
				$articles[$key]['Url']
			) = $value;
			if($key >= 9) { break; } //最多只允许10调新闻
		}
		$this->data['ArticleCount'] = count($articles);
		$this->data['Articles'] = $articles;
	}

	private function transfer_customer_service($data){
		$this->data['Content'] = $data;
	}

	
    private function data2xml($xml, $data, $item = 'item') {
        foreach ($data as $key => $value) {
            /* 指定默认的数字key */
            is_numeric($key) && $key = $item;

            /* 添加子元素 */
            if(is_array($value) || is_object($value)){
                $child = $xml->addChild($key);
                $this->data2xml($child, $value, $item);
            } else {
            	if(is_numeric($value)){
            		$child = $xml->addChild($key, $value);
            	} else {
            		$child = $xml->addChild($key);
	                $node  = dom_import_simplexml($child);
				    $node->appendChild($node->ownerDocument->createCDATASection($value));
            	}
            }
        }
    }

   
	private function auth($token){
		/*
		$signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];	
        		
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		
		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}*/
		return true;
	}

}
