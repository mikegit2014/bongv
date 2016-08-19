# bongv
bongv_bak
add bak files

服务端与客户端通信加密方法
由服务端提供加密时使用的秘钥key(key=809ac23d31fe1fa9,iv=44ace1eedceenxcq)、签名使用的签名key(1c79e1fa963cd90cc0be99b20a18faeb)及appid(b96bKi3dqE)

1. 对数据（数组）进行json后生成字符串 str ,

2. 16位随机字符串 + str + appid(10) 生成字符串str1后在使用base64进行加密,然后再使用aes,进行加密 cryptstr,在进行base64加密 64str

3. 验证这种结构 encrypt=64str&nonce=生成签名的随机字符串×tamp=生成签名的时间戳&signkey=签名key  ,生成签名（sha1）,把生成的签名转成大写

4. 最后转成json格式输出
 [encrypt] => fmqSTm69eJIFzq9owxARKZv4TPj3fvJFG9l1ujx/jpVbEkCdumUreLNT0Qqqywc+I+slkeOJcBc3xHSBVdF2jV8+OL+Zj1NUTWUdUw9k= 
 [sign] => 640e78ffa14f08ac3784c226300f744c3b30e848 
 [timestamp] => 1463036339 
 [nonce] => z4Z5uXuRBBLSc0vp

 解密方式：post四个参数
 1. 先进行验证签名，签名生成方式如上
 2. 先对密文base解密,在使用aes进行解密
 3. 解密后,需要去掉前面16位随机字符串及末尾的10位appid,
 4. 得到原始数据 json 格式



//加密返回给app端的数据
/*
* $cryptKey 加密使用的key
* $signKey  签名使用的key
* $appid    项目的标示
* $cryptData 待加密的数据( array / string ……)
* $json 返回加密后的json格式
*/
function encryptData($cryptKey,$cryptIv,$signKey,$appid,$cryptData){
    import('MCrypt','./Tool/qrcode/','.php');
    $obj = new \Tool\qrcode\MCrypt($cryptKey,$cryptIv,$signKey,$appid);
    $nonce = $obj->getRandomStr();
    $time = time();
    $json = $obj->encrypt(json_encode($cryptData),$time,$nonce);
    return json_encode($json);
}
//解密app端传的参数
/*
* $cryptKey 加密使用的key
* $signKey  签名使用的key
* $appid    项目的标示
* $decryData 待解密的数据
* $json 返回解密后的数据
*/
function decryptData($cryptKey,$cryptIv,$signKey,$appid,$decryData = array() ){
    import('MCrypt','./Tool/qrcode/','.php');
    $obj = new \Tool\qrcode\MCrypt($cryptKey,$cryptIv,$signKey,$appid);
    $str = $obj->decrypt($decryData['sign'],$decryData['timestamp'],$decryData['nonce'],$decryData['encrypt']);
    if( $str == -100 ){
        return array('errno'=>-100,'errmsg'=>'签名错误');
    }elseif($str == -200){
        return array('errno'=>-200,'errmsg'=>'appid不一致');
    }
    $data = json_decode($str,true);
    return $data;
}
