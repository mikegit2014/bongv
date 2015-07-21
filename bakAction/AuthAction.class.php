<?php
/**
*  微信网页OAuth2.0授权基础类
**/
class AuthAction extends BaseAction {
    protected $token;
    protected $wechat_id;
    protected $appid;
    protected $appsecret;
    protected $id;
    protected $mulid_a;
    protected $scope;
    protected $siteUrl;
    protected $agent;
    protected $shareScript;
    protected $json_ticket_file;
    protected $json_access_file;
    protected $nowappid;
    protected $timestamp;
    protected $nonceStr;
    protected $signature;
    protected $jsapiTicket;
    protected $openid_all; 
    protected $access_token;
    protected $auth_Mark;
	/**
     * Initialize Method
     * 
     * @access public
     * @return void
     */
    public function _initialize () {
        parent::_initialize(); 
        $this->agent = $_SERVER['HTTP_USER_AGENT']; 
        if(!strpos($this->agent,"icroMessenger")) {
            echo '此功能只能在微信浏览器中使用';
            exit;
        }
        $this->token = $this->_request('token','trim');
        $this->id = $this->_request('id','trim');
        $this->mulid_a = $this->_request('mulid','trim,intval');
        $this->scope = $this->_request('scope','trim');
        $channel = $this->_get('channel');      

        if(IS_POST){
          $tmp_oppen = $this->_post('wechat_id','trim');
        }else{
          $tmp_oppen = $this->_get('wechat_id','trim');
        }

        if(empty($this->scope) || !isset($this->scope)){
            $this->scope = 'snsapi_base';
        } 
        if(MODULE_NAME == "Red_packet")
        {
        	$this->scope = 'snsapi_userinfo';
        }
        if ($this->token && !preg_match("/^[0-9a-zA-Z]{3,20}$/", $this->token)) {
            exit('error token');
        }
        $this->siteUrl = C('m_site_url');

        $this->get_appid();
		    // $current_url = $this->get_url();
        if(isset($channel) && $channel == 'shared'){
          $current_url = $this->siteUrl.'/'.GROUP_NAME.'/'.MODULE_NAME.'/'.ACTION_NAME.'/token/'.$this->token.'/id/'.$this->id."/scope/".$this->scope.'/channel/shared';
        }else{
          if(isset($this->id) && !empty($this->id) && empty($this->mulid_a)){
            $current_url = $this->siteUrl.'/'.GROUP_NAME.'/'.MODULE_NAME.'/'. ACTION_NAME.'/token/'.$this->token.'/id/'.$this->id."/scope/".$this->scope;
          }else{
            //自动回复
            $current_url = $this->siteUrl.'/'.GROUP_NAME.'/'.MODULE_NAME.'/'. ACTION_NAME.'/token/'.$this->token.'/mulid/'.$this->mulid_a."/scope/".$this->scope;
          }
        }
    	
        $this->auth_Mark = '/Wap/'.MODULE_NAME.'/'; 
//         session_destroy();
        $this->json_ticket_file = RUNTIME_PATH.'share_jsapi_cache/jsapi_ticket';
        $this->json_access_file = RUNTIME_PATH.'share_jsapi_cache/access_token';
        $this->openid_all = RUNTIME_PATH.'wechat_id_all_info.txt';

        /*
         * OAuth2.0受权
        */
        if(isset($_REQUEST["code"])){
          $code = htmlspecialchars($_REQUEST["code"]);
         
          //通过code换取网页授权access_token和用户信息
          $res = $this->wxGetTokenWithCode($code);
          
          if($res === false || $res === 40029 || empty($res["access_token"]))
          {
            if(isset($channel) && $channel == 'shared'){
	           $reloadurl = GROUP_NAME.'/'.MODULE_NAME.'/'.ACTION_NAME.'/token/'.$this->token.'/id/'.$this->id."/scope/".$this->scope.'/channel/shared';
            }else{
              $reloadurl = GROUP_NAME.'/'.MODULE_NAME.'/'.ACTION_NAME.'/token/'.$this->token.'/id/'.$this->id."/scope/".$this->scope;
            }
	          header('Location:'.$reloadurl);
	
	          file_put_contents ( $this->openid_all,'555code'.$res["openid"]."\r\n", FILE_APPEND );
	          die;
          }
          
          file_put_contents ( $this->openid_all,'2222'.$res["openid"]."\r\n", FILE_APPEND );
          session('access_token',$res["access_token"]);
          session('refresh_token',$res["refresh_token"]);
          session('openid',$res["openid"]);
          session('openidtime',time() + 7000);
          $this->wechat_id = $res['openid'];
          $this->access_token = $res['access_token'];
        }else{
          file_put_contents ( $this->openid_all,'5555--'.session('openidtime')."\r\n", FILE_APPEND );
          if(session('openidtime') <= time()){
            $sess_token = session('refresh_token');
            if(!empty($sess_token)){
              //请求微信之前把当前url写入cookie
              if( stripos( $_SERVER['HTTP_REFERER'] , $this->auth_Mark ) === false ){
                   setcookie( '___fff___', $_SERVER['HTTP_REFERER'], time()+30, '/' );
              }
              $res = $this->refreshToken($sess_token);
              if($res === false || $res === 40029){
                $this->error("受权失败");
              }else{
                if(!empty($res)){
                  session('access_token',$res["access_token"]);
                  session('refresh_token',$res["refresh_token"]);
                  session('openid',$res["openid"]);
                  session('openidtime',time() + 7000);
                  $this->wechat_id = $res['openid'];
                  $this->access_token = $res['access_token'];
                  file_put_contents ( $this->openid_all,'1---111'.$this->wechat_id."\r\n", FILE_APPEND );
                }
              }
            }else{
              //请求微信之前把当前url写入cookie    
              if( stripos( $_SERVER['HTTP_REFERER'] , $this->auth_Mark ) === false ){
                   setcookie( '___fff___', $_SERVER['HTTP_REFERER'], time()+30, '/' );
              }
              file_put_contents ( $this->openid_all,'111'."\r\n", FILE_APPEND );
              $this->wechatWebAuth($current_url);
            }
          }else{
            file_put_contents ( $this->openid_all,'4444'.session('openid')."\r\n", FILE_APPEND );
            $this->wechat_id = session('openid');
            $this->access_token = session('access_token');
          }
        }
        // $this->wechat_id = 'ov9CqjqA0rDKkUEvzzPElmb1i31g';
        // $this->wechat_id = 'oHt2pjgDL5YykrYf_5z3pvlpJO08';
        // $this->wechat_id = 'oN7icuN48nOuC1RvpCvryWwEaUm4';
        // $this->wechat_id = 'oESrjswNrGviwYtF5HP-Ex06G5KE';
        // $this->wechat_id = 'o1weet1oxpjswQ12QAkUS0Duvrn4';
        // $this->wechat_id = 'o-5vztyM7rHP4og4VZRlwkMAer3Y';
        // $this->wechat_id = 'oOtCmjmumBl8lIfRcQh12JyRRMyA';
        file_put_contents ( $this->openid_all,'userinfo----'.$this->scope."\r\n", FILE_APPEND );

		if($this->scope == "snsapi_userinfo")
		{
			
				if(!empty($this->wechat_id) && !empty($this->access_token)){
					
					
					$userInfo = $this->getUserInfo($this->access_token,$this->wechat_id);
					$data = json_decode($userInfo, true);
					file_put_contents ( $this->openid_all,'userinfo----'.$data['errcode']."\r\n", FILE_APPEND );
					
				 	switch ($data['errcode']) {
			            case '40029':
			                $this->error("受权失败");
			                break;
			            default:
			            	$u = M("Empoweruserinfo")->where(array('openid'=>$data['openid']))->find();
			            	$url = $data['openid'].$data['nickname'];
			            	if($u ==null)
			            	{
			            		$code_p = $_SERVER['DOCUMENT_ROOT'].'/uploads/empowerUserInfoImg/'.$url.".jpg";
			            		$this->save_img($data['headimgurl'],$code_p,'empowerUserInfoImg');
			            		$arr_data = array(
			            				'token'=>$this->token,
			            				'pid'=>$this->id,
			            				'data'=>time(),
			            				'nickname'=>$data['nickname'],
			            				'sex'=>$data['sex'],
			            				'openid'=>$data['openid'],
			            				'province'=>$data['province'],
			            				'city'=>$data['city'],
			            				'country'=>$data['country'],
			            				'headimgurl'=>$this->siteUrl."/uploads/empowerUserInfoImg/".$url.".jpg",
			            				'privilege'=>$data['privilege'],
			            				'unionid'=>$data['unionid']
			            		);
			            		M("Empoweruserinfo")->add($arr_data);
			            	}else
			            	{
			            		$code_p = $_SERVER['DOCUMENT_ROOT'].'/uploads/empowerUserInfoImg/'.$url.".jpg";
			            		$this->save_img($data['headimgurl'],$code_p,'empowerUserInfoImg');
			            		$arr_data = array(
			            				'token'=>$this->token,
			            				'pid'=>$this->id,
			            				'data'=>time(),
			            				'nickname'=>$data['nickname'],
			            				'sex'=>$data['sex'],
			            				'openid'=>$data['openid'],
			            				'province'=>$data['province'],
			            				'city'=>$data['city'],
			            				'country'=>$data['country'],
			            				'headimgurl'=>$this->siteUrl."/uploads/empowerUserInfoImg/".$url.".jpg",
			            				'privilege'=>$data['privilege'],
			            				'unionid'=>$data['unionid']
			            		);
			            		//添加头像到session中
			            		M("Empoweruserinfo")->save($arr_data);
			            	}	
				 }
			}
		}
		

        //分享需要的参数
        $this->jsapiTicket = $this->getJsApiTicket();
        $tmp_REQUEST_URI = $_SERVER[REQUEST_URI];
        // $tmp_REQUEST_URI = preg_replace( '/\/wecha_id\/[0-9a-zA-Z-_]*/i', '', $tmp_REQUEST_URI );
        $tick_url = "http://$_SERVER[HTTP_HOST]$tmp_REQUEST_URI";
        $this->timestamp = time();
        $this->nonceStr = $this->createNonceStr();
        file_put_contents ( $this->openid_all,'授权结束'.$this->wechat_id.'当前地址:'.$tick_url."\r\n", FILE_APPEND );
        // $wxusers = $this->get_now_appid();
        // $this->nowappid = $wxusers['appid'];
        $this->nowappid = $this->appid;

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$this->jsapiTicket&noncestr=$this->nonceStr&timestamp=$this->timestamp&url=$tick_url";
        $this->signature = sha1($string);
         //echo $string."<hr/>";
         //echo $tick_url;
        //结束
      $this->shareScript = <<<EOF
<script src="http://res.wx.qq.com/open/js/jweixin-1.0.0.js"></script>
<script>
    wx.config({
      debug: false,
      appId: "$this->nowappid",
      timestamp: $this->timestamp,
      nonceStr: "$this->nonceStr",
      signature: "$this->signature",
      jsApiList: [
        //'checkJsApi',
        'onMenuShareTimeline',
        'onMenuShareAppMessage',
        'onMenuShareQQ',
        'closeWindow', 
      ]
      });
          
     wx.ready(function () {
       //调用 API
       // wx.checkJsApi({
       //   jsApiList: [
       //     'checkJsApi',         
       //     'onMenuShareTimeline',
       //     'onMenuShareAppMessage',
       //     'onMenuShareQQ' 
       //   ],
       //   success: function (res) {
       //     alert(JSON.stringify(res));
       //   }
       // });
                    
        //分享给朋友
      wx.onMenuShareAppMessage({
          title: window.shareData.tTitle,
          desc: window.shareData.tContent,
          link: window.shareData.sendFriendLink,
          imgUrl: window.shareData.imgUrl,
          trigger: function (res) {
//            alert('用户点击发送给朋友');
          },
          success: function (res) {
            shareHandle('frined',window.shareData.sendFriendLink);
            if( isExitsFunction ('requestData') ){
              requestData( 'wxpy_share' );//分享到朋友
            }
          },
          cancel: function (res) {
//            alert('已取消');
          },
          fail: function (res) {
//            alert(JSON.stringify(res));
          }
     });

          
        //分享到朋友圈
      wx.onMenuShareTimeline({
          title: window.shareData.tTitle,
          link: window.shareData.timeLineLink,
          imgUrl: window.shareData.imgUrl,       
        trigger: function (res) {
//          alert('用户点击分享到朋友圈');
        },
        success: function (res) {
          shareHandle('frineds',window.shareData.timeLineLink);
          if( isExitsFunction ('requestData') ){
              requestData( 'wxpyq_share' );//分享到朋友圈
            }
        },
        cancel: function (res) {
//          alert('已取消');
        },
        fail: function (res) {
//          alert(JSON.stringify(res));
        }
      });

    
        //分享到qq
      wx.onMenuShareQQ({
          title: window.shareData.tTitle,
          desc: window.shareData.tContent,
          link: window.shareData.weiboLink,
          imgUrl: window.shareData.imgUrl,
        trigger: function (res) {
//          alert('用户点击分享到QQ');
        },
        complete: function (res) {
//          alert(JSON.stringify(res));
        },
        success: function (res) {
          shareHandle('QQ',window.shareData.weiboLink);
          if( isExitsFunction ('requestData') ){
              requestData( 'wxqq_share' );//分享到QQ
            }
        },
        cancel: function (res) {
//          alert('已取消');
        },
        fail: function (res) {
//          alert(JSON.stringify(res));
        }
      });
                    
     });
          
  wx.error(function(res){   
      // config信息验证失败会执行error函数，如签名过期导致验证失败，具体错误信息可以打开config的debug模式查看，也可以在返回的res参数中查看，对于SPA可以在这里更新签名。
      //alert(res.errMsg);    
  }); 

      function shareHandle(to,shareurl) {
        var submitData = {
          module: window.shareData.moduleName,
          moduleid: window.shareData.moduleID,
          token: '$this->token',
          wecha_id: '$this->wechat_id',
          url: shareurl,
          to:to
        }
      };
</script>
EOF;

$this->shareScript .= '<script>
 var version = new Date().getTime(), _url = "http://'.C("TONGJI_DOMAIN").'/tpl/static/tongji.js?"+version, _jqueryUrl = "http://'.C("TONGJI_DOMAIN").'/tpl/static/jquery.min.js";
if( typeof jQuery == "undefined" ){
  document.write( "\<script src="+_jqueryUrl+"\>\<\/script\>" );
}
document.write( "\<script src="+_url+"\>\<\/script\>" );

//是否存在指定函数 
function isExitsFunction(funcName) {
    try {
        if (typeof(eval(funcName)) == "function") {
            return true;
        }
    } catch(e) {}
    return false;
}
</script>';

        if(!empty($this->wechat_id)){
          $this->assign('shareScript', $this->shareScript);
        }

      $this->assign('module',MODULE_NAME);
        $this->assign('pushurl',$tick_url);
    }
    
    public function save_img($img_url,$save_path,$dir){
    		$ch = curl_init();
    		$fp = fopen($save_path, 'wb');
    		curl_setopt($ch, CURLOPT_URL, $img_url);
    		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    		curl_setopt($ch, CURLOPT_FILE, $fp);
    		curl_setopt($ch, CURLOPT_HEADER, 0);
    		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    		curl_exec($ch);
    		curl_close($ch);
    		fclose($fp);
    }

  private function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  private function ticket_set_host($host_url){
    header('Location:'.$host_url);
  }

  private function getJsApiTicket() {
    // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
    $data = json_decode(file_get_contents($this->json_ticket_file));
    if ($data->expire_time < time()) {
      $accessToken = $this->getAccessToken();
      $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
      $res = json_decode($this->curlGetInfo($url));
      $ticket = $res->ticket;
      if ($ticket) {
        $data->expire_time = time() + 7000;
        $data->jsapi_ticket = $ticket;
        file_put_contents($this->json_ticket_file,json_encode($data),LOCK_EX);
        /*$fp = fopen($this->json_ticket_file, "w");
        fwrite($fp, json_encode($data));
        fclose($fp);*/
      }
    } else {
      $ticket = $data->jsapi_ticket;
    }

    return $ticket;
  }

  private function getAccessToken() {
    // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
    // $wxusers = $this->get_now_appid();
    $data = json_decode(file_get_contents($this->json_access_file));
    if ($data->expire_time < time()) {
      // $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$wxusers['appid']."&secret=".$wxusers['appsecret'];
      $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->appsecret;      
      $res = json_decode($this->curlGetInfo($url));
      $access_token = $res->access_token;
      if ($access_token) {
        $data->expire_time = time() + 7000;
        $data->access_token = $access_token;
        file_put_contents($this->json_access_file,json_encode($data),LOCK_EX);
        /*$fp = fopen($this->json_access_file, "w");
        fwrite($fp, json_encode($data));
        fclose($fp);*/
      }
    } else {
      $access_token = $data->access_token;
    }
    return $access_token;
  }


    /**
    *  微信auth2.0 授权时，用到的appid和appsecret
    **/
    private function get_appid(){
        $this->appid = C('wx_appID');
        $this->appsecret = C('wx_appsecret');
    }
    /**
   	* 微信auth2.0 受权
   	* @param string $redirct_url 授权后返回url
   	* @param string $scope 应用授权作用域，snsapi_base （不弹出授权页面，直接跳转，只能获取用户openid），snsapi_userinfo （弹出授权页面，可通过openid拿到昵称、性别、所在地。并且，即使在未关注的情况下，只要用户授权，也能获取其信息）
   	**/
    private function wechatWebAuth($redirct_url = ""){
    	$redirct_url = urlencode($redirct_url);
    	$wxurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$this->appid."&redirect_uri=".$redirct_url."&response_type=code&scope=".$this->scope."&state=STATE#wechat_redirect";
    	header('Location:'.$wxurl);
      die();
    }
    
    /**
     * 拉取用户信息(需scope为 snsapi_userinfo)
     * 如果网页授权作用域为snsapi_userinfo，则此时开发者可以通过access_token和openid拉取用户信息了。
     * @param string $redirct_url 授权后返回url
     * @param string $scope 应用授权作用域，snsapi_base （不弹出授权页面，直接跳转，只能获取用户openid），snsapi_userinfo （弹出授权页面，可通过openid拿到昵称、性别、所在地。并且，即使在未关注的情况下，只要用户授权，也能获取其信息）
     **/
    private function getUserInfo($access_token,$openid){
    	$redirct_url = urlencode($redirct_url);
    	$wxurl = "https://api.weixin.qq.com/sns/userinfo?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
    	return $this->curlGetInfo($wxurl);
    }
    /**
    * 刷新Token
    * @param string $code refresh_token refresh_token
    **/
    private function refreshToken($refresh_token) {
        if(empty($refresh_token)){
            return false;
        }
        $url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=' .$this->appid. '&grant_type=refresh_token&refresh_token=' . $refresh_token;
        $Token = $this->curlGetInfo($url);
        $data = json_decode($Token, true);
        switch ($data['errcode']) {
            case '40029':
                return 40029;
                break;
        }
        return $data;
    }
    /**
   	* 通过code换取网页授权access_token和用户信息(微信auth2.0 受权)
   	* @param string $code wechatWebAuth 返回的code
   	**/
    private function wxGetTokenWithCode($code){
    	if(!isset($code)){
    		return false;
    	}
    	$url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->appid."&secret=".$this->appsecret."&code=".$code."&grant_type=authorization_code";
    	$Token = $this->curlGetInfo($url);
        $data = json_decode($Token, true);
        switch ($data['errcode']) {
            case '40029':
                return 40029;
                break;
        }
        return $data;
    }



    /**
     * get weixin access_token
     * @param wxid
     * @return array
     */ 
    protected function get_weixin_access_token() {
        if(S("wx_access_token")){
            return S("wx_access_token");
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->appsecret;
        $Token = $this->curlGetInfo($url);
        $data = json_decode($Token, true);
        if($data['errcode']){
            // Log::write('平台获取access_token错误'.$Token);
            return $data['errcode'];
        }
        S('wx_access_token',$data["access_token"],6000);
        return $data["access_token"];

    }

    //curl抓取网页
    private function curlGetInfo($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         
        $info = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Errno'.curl_error($ch);
        }
        
        return $info;
    }
    /**
     * 获取当前页面完整URL地址
     */
    private function get_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }
    /**
    * 获取当前公众号的appid
    */
    private function get_now_appid(){
        $nowappid = M('Wxuser')->where(array('token'=>$this->token))->field('appid,appsecret')->find();
        if(empty($nowappid)){
            $this->error('该公众号未设置appid!');
            exit;
        }else{
            return $nowappid;
        }
    }
}