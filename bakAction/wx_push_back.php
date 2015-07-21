<?php
	$xml = file_get_contents("php://input");
	if(!empty($xml)){
		$xml = new SimpleXMLElement($xml);
		$xml || exit;
		foreach ($xml as $key => $value) {
		  	$xml_data[$key] = strval($value);
		}
	}
	parse_str($_SERVER['QUERY_STRING'], $get_arr);
	if(!empty($xml_data)){
		$encrypt = $xml_data + $get_arr;
	}else{
		$encrypt = $get_arr;
	}

	/*在微信平台上,取消授权后,会请求这个文件 Array ( [AppId] => wx46fe7ae472d71a77 [CreateTime] => 1423554837 [InfoType] => unauthorized [AuthorizerAppid] => wx381b8f3d1e05ba84 ) */

	$conf_data = include_once('./Conf/config.php');
	$conf_data_info = include_once('./Conf/info.php');


	$appid = $conf_data['wxopen_appid'];
	$encode_key = $conf_data['wxopen_decy_key'];
	$newtoken = $conf_data['wxopen_token'];

	/*
	$encrypt['Encrypt'] = '2LJhVh/K2TnIY+0pXT4npPv9OyP1oz+nVXWe2qOF5CiuEtw8JQo28SuMObpHM9TH1x68fFjI5+cGCNeVXS7DrsNryv6pa+BRV+wEzinQm/tnsVQtTnV3o+C4Jzk0Y+ad+qQIQF553E0L8HtKd6KXkBFoRxdiCl6JMl7+XUMvYjM+T3Ld3y5wf7MXW4IGsd5zZ4FGcjuzHyw4sGbX0oKR+fzT0B7n1U9yxlx5JY9lrMbUeHpmPSBiPp+fWY6noCPswTyjz3oUjmMmENmDitIbpEatgattkzP339n1NG+pmH9FwQoB37mx1EN7tV4LKMWHOleQeHsz89MUy/QjxK2/ig==';
    $encrypt['timestamp'] = '1423554837';
    $encrypt['nonce']	= '349637074';
    $encrypt['msg_signature'] = 'a7c992b99e27a8508ba641a0843ea6285f5757bc';
    */
    
    if(array_key_exists('Encrypt', $encrypt)){
		$decry_arr = decrypt_wx_msg($newtoken,$encode_key,$appid,$encrypt);
		if(array_key_exists('InfoType', $decry_arr) && $decry_arr['InfoType'] == 'unauthorized' && !empty($decry_arr['AuthorizerAppid'])){ // 微信推送的取消授权信息
			$decry_arr['now'] = date('Y-m-d H:i:s',time());
			file_put_contents('./runtime/auth_cache/wx_push_encrypt_msg_calcel.php', "<?php \nreturn ".var_export($decry_arr, true) . ";", LOCK_EX);
			// header('Location:'.'http://admint.bongv.com/User/Bind/cancel_info/AuthorizerAppid/'.$decry_arr['AuthorizerAppid']);
			$url_get = $conf_data_info['site_url'].'/User/Bind/cancel_info/AuthorizerAppid/'.$decry_arr['AuthorizerAppid'];
			curlGet($url_get);
			echo 'success';
			die();
		}else{
			$encrypt['now'] = date('Y-m-d H:i:s',time());
			file_put_contents('./runtime/auth_cache/wx_push_encrypt_msg.php', "<?php \nreturn ".var_export($encrypt, true) . ";", LOCK_EX);
			echo 'success';
		}
	}
		/*使用新的公众号绑定后,微信发的信息一律都是密文的,因此需要解密*/
	function decrypt_wx_msg($token,$encodingAesKey,$appId,$encryptMsg){
		include_once('./app/Lib/ORG/WXBizMsgCrypt.class.php');
		$pc = new WXBizMsgCrypt($token, $encodingAesKey, $appId);	    
	    // 第三方收到公众号平台发送的消息
	    $encrypt_str = $encryptMsg['Encrypt'];
        $timeStamp = $encryptMsg['timestamp'];
        $nonce = $encryptMsg['nonce'];
        $msg_sign = $encryptMsg['msg_signature'];
	    $msg = '';
	    $errCode = $pc->decryptMsg_str($msg_sign, $timeStamp, $nonce, $encrypt_str, $msg);
	    if ($errCode == 0) {
			$xml_new = new SimpleXMLElement($msg);
			foreach ($xml_new as $tt => $vv) {
					$vv = trim($vv);
				  	$_data[$tt] = strval($vv);
			}			
			return $_data;
	    } else {
	        $this->error('解密失败!错误代码为:'.$errCode);
	    }
	}
	function curlGet($url){
        $ch = curl_init();
        $header = "Accept-Charset: utf-8";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)");
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $temp = curl_exec($ch);
        curl_close($ch);
        return $temp;
    }
?>
