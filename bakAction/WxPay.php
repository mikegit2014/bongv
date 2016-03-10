<?php
namespace Tool\qrcode;

class WxPay{
	function __construct(){

	} 
	function pay($url,$obj,$key){
		$obj['nonce_str'] = $this->create_noncestr();
		$stringA = $this->formatQueryParaMap($obj,false);
		$stringSignTemp = $stringA . "&key=" .$key;
		$sign = strtoupper(md5($stringSignTemp));
		$obj['sign'] = $sign;
		$postXml = $this->arrayToXml($obj);
		$responseXml = $this->curl_post_ssl($url,$postXml);
		return $responseXml;
	}
	function create_noncestr($len = 32){
		$rand_str = '012345789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$max = strlen($rand_str) - 1;
		for ($i=0; $i<=$len ; $i++) { 
			$nonce .= $rand_str[mt_rand(0, $max)];
		}
		return $nonce;
	}
	function formatQueryParaMap($paraMap,$urlencode){
		$buff = "";
		ksort($paraMap);
		foreach ($paraMap as $k => $v) {
			if(null != $v && "null" != $v && "sign" != $k){
				if($urlencode){
					$v = urlencode($v);
				}
				$buff .= $k . "=" .$v ."&";
			}
		}
		$reqPar;
		if(strlen($buff) > 0){
			$reqPar = substr($buff,0,strlen($buff)-1);
		}
		return $reqPar;
	}
	//生成xml
    function arrayToXml($arr){
    	$xml = "<xml>";
    	foreach ($arr as $key => $val) {
    		if (is_numeric($val)){
    			$xml .= "<".$key.">".$val."</".$key.">";
    		}else{
    			$xml .= "<".$key."><![CDATA[".$val."]]></".$key.">";
    		}
    	}
    	$xml .="</xml>";
    	return $xml;
    }
    //发送提现请求
    public function curl_post_ssl($url,$vars,$second=30){
    	$ch = curl_init();
	    curl_setopt($ch, CURLOPT_TIMEOUT, $second);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

	    curl_setopt($ch, CURLOPT_SSLCERT, '.'.DIRECTORY_SEPARATOR.'Tool'.DIRECTORY_SEPARATOR.'cert'.DIRECTORY_SEPARATOR.'apiclient_cert.pem');
	    curl_setopt($ch, CURLOPT_SSLKEY, '.'.DIRECTORY_SEPARATOR.'Tool'.DIRECTORY_SEPARATOR.'cert'.DIRECTORY_SEPARATOR.'apiclient_key.pem');
	    curl_setopt($ch, CURLOPT_CAINFO, '.'.DIRECTORY_SEPARATOR.'Tool'.DIRECTORY_SEPARATOR.'cert'.DIRECTORY_SEPARATOR.'rootca.pem');

	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $vars); 
	    $output = curl_exec($ch);
	    echo 'abc--';
	    print_r($output);exit;
	    if($output){
	    	curl_close($ch);
	    	return $output;
	    }else{
	    	$error = curl_error($ch);
	    	curl_close($ch);
	    	return false;
	    }
	}
}
