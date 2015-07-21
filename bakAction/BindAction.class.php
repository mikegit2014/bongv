<?php
/**
*
*使用微信开发平台服务进行公众号绑定
**/
class BindAction extends BaseAction{
	private $ticket;
	private $appid;
	private $appsecret;
	private $com_access_token;
	private $pre_auth_code;
	private $open_token;
	private $encodingAesKey;
	private $cache_data;
	private $open_cache_file;
	private $cache_now_file;

	public function _initialize(){
		parent::_initialize();
		$this->open_cache_file = RUNTIME_PATH."auth_cache/open_com_access_token.php";
		$this->cache_now_file = RUNTIME_PATH.'share_jsapi_cache/access_token_now';
		$this->cache_data = include_once($this->open_cache_file);
		$this->get_open_appid();
		$this->ticket = $this->get_verify_ticket();
		$this->com_access_token = $this->get_com_access_token();
		$this->pre_auth_code = $this->get_preauthcode();
	}
	public function auto_add(){
		$this->bind_check(); //检测用户能绑定几个公众号
		// $auth_url = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid=wx46fe7ae472d71a77&pre_auth_code='.$this->pre_auth_code.'&redirect_uri=http://admint.bongv.com/User/Bind/get_info';
		$auth_url = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid='.$this->appid.'&pre_auth_code='.$this->pre_auth_code.'&redirect_uri='.C('site_url').'/User/Bind/get_wx_info';
		// echo "<script>window.open(\"$auth_url\");</script>";
		
			$tz_str = <<<EOF
<script>
    window.onload = function(){
    	if (/MSIE (\d+\.\d+);/.test(navigator.userAgent) || /MSIE(\d+\.\d+);/.test(navigator.userAgent)){
		    var referLink = document.createElement('a');
		    referLink.href = '$auth_url';
		    document.body.appendChild(referLink);
		    referLink.click();
		} else {
			window.location.href='$auth_url';
		}
	}	
</script>
EOF;
		echo $tz_str;
		// header('Location:'.$auth_url);
		exit;
	}
	//用户绑定公众号检测
	public function bind_check(){
		$userid = session('uid');
		if(session('pid') > 0 )
			$userid = session('pid');		
		$condition['id'] = $userid;
		$condition['status'] = 1;
		$user_info = D('Users')->getOneInfo($condition,'gid');
		$data = M('User_group')->field('wechat_card_num')->where(array('id'=>$user_info['gid']))->find();	
		$wxnum = D('Wxuser')->get_bind_num(array('uid'=>$userid));
		if($wxnum >= $data['wechat_card_num']){
			$ts = <<<EOF
<style>
.wd{
	width:50%;
	min-width:230px;
	height:190px;
	background-color:#FFF;
	background:rgba(255, 255, 255, 1) none repeat scroll 0 0 !important;/*实现FF背景透明，文字不透明*/
	filter:Alpha(opacity=100); /*实现IE背景透明*/
	margin:0 auto;
	margin-top:10%;
	border:1px solid #CCC;
	box-shadow:0px 0px 5px 5px #CCC ;
	}
.wd .line1{
	width:100%;
	height:80px;
	float:left;
	}
.wd .line1 .close{
	text-align:center;
	font-size:48px;
	line-height:60px;
	font-weight:bolder;
	height:60px;
	width:60px;
	margin:0 auto;
	color:#FFF;
	margin-top:20px;
	background-color:#506276;
	-moz-border-radius: 30px;      /* Gecko browsers */
    -webkit-border-radius: 30px;   /* Webkit browsers */
    border-radius:30px;            /* W3C syntax */
	}
.wd .line2{
	line-height:40px;
	width:100%;
	height:40px;
	float:left;
	margin-top:10px;
	text-align:center;
	font-size:26px;
	color:#506276;
	font-weight:bolder;
	}
.wd .line3{
	line-height:30px;
	width:100%;
	height:30px;
	float:left;
	text-align:center;
	font-size:13px;
	color:#666;
	}
</style>			
<div id="body_cn">
</div>
<script>
    window.onload = function(){

	var auto_time=3;
	var str="<div class='wd'>\
        			<div class='line1'>\
                		<div class='close'>√</div>\
            		</div>\
            		<div class='line2'>您绑定的公众号已达到上限</div>\
            		<div class='line3'>\
						<div>本页面将在<span id='jsf'>3</span>秒后自动关闭</div>\
            		</div>\
        		</div>";
	document.getElementById("body_cn").innerHTML=str;
	window.djs = setInterval(function(){
		auto_time = parseInt(auto_time)-1;
		document.getElementById('jsf').innerHTML = auto_time;
		if(auto_time == 0){
			window.opener=null;
			window.open('','_self');
			window.close();
		}	
		},1000);
	}	
</script>

EOF;
			echo $ts;
			// $this->error('您绑定的公众号已达到上限!',U('User/Index/index'));
			exit(-1);
		}
	}	
	public function get_wx_info(){
		//根据授权码,获取authorizer_access_token并写入文件
		parse_str($_SERVER['QUERY_STRING'], $wx_code);
		//使用完预授权,就清楚这个预授权码
		// $tt_data = include_once($this->open_cache_file);
		// unset($tt_data['pre_auth_code']);
		// unset($tt_data['pre_expires']);
		// file_put_contents($this->open_cache_file, "<?php \nreturn " . stripslashes(var_export($tt_data, true)) . ";", LOCK_EX);
		/*结束*/

		if(array_key_exists('auth_code', $wx_code)){
			$appid_hao = $this->get_authorizer_code($wx_code);
			/*结束*/
			// $auth = $this->return_token($appid_hao);
			// $detail = $this->get_detail_info($auth['authorizer_appid']);

			$detail = $this->get_detail_info($appid_hao);

			$wxinfo = M('Wxuser')->field('id')->where(array('wxid'=>$detail['authorizer_info']['user_name']))->find();
			if(!empty($wxinfo)){
			$ts = <<<EOF
<style>
.wd{
	width:50%;
	min-width:230px;
	height:190px;
	background-color:#FFF;
	background:rgba(255, 255, 255, 1) none repeat scroll 0 0 !important;/*实现FF背景透明，文字不透明*/
	filter:Alpha(opacity=100); /*实现IE背景透明*/
	margin:0 auto;
	margin-top:10%;
	border:1px solid #CCC;
	box-shadow:0px 0px 5px 5px #CCC ;
	}
.wd .line1{
	width:100%;
	height:80px;
	float:left;
	}
.wd .line1 .close{
	text-align:center;
	font-size:48px;
	line-height:60px;
	font-weight:bolder;
	height:60px;
	width:60px;
	margin:0 auto;
	color:#FFF;
	margin-top:20px;
	background-color:#506276;
	-moz-border-radius: 30px;      /* Gecko browsers */
    -webkit-border-radius: 30px;   /* Webkit browsers */
    border-radius:30px;            /* W3C syntax */
	}
.wd .line2{
	line-height:40px;
	width:100%;
	height:40px;
	float:left;
	margin-top:10px;
	text-align:center;
	font-size:26px;
	color:#506276;
	font-weight:bolder;
	}
.wd .line3{
	line-height:30px;
	width:100%;
	height:30px;
	float:left;
	text-align:center;
	font-size:13px;
	color:#666;
	}
</style>			
<div id="body_cn">
</div>
<script>
        window.onload = function(){    		

	var auto_time=3;
	var str="<div class='wd'>\
        			<div class='line1'>\
                		<div class='close'>X</div>\
            		</div>\
            		<div class='line2'>公众号已被绑定!</div>\
            		<div class='line3'>\
						<div>本页面将在<span id='jsf'>3</span>秒后自动关闭</div>\
            		</div>\
        		</div>";
	document.getElementById("body_cn").innerHTML=str;
	window.djs = setInterval(function(){
		auto_time = parseInt(auto_time)-1;
		document.getElementById('jsf').innerHTML = auto_time;
		if(auto_time == 0){
			window.opener=null;
			window.open('','_self');
			window.close();
		}	
		},1000);
		}	
</script>

EOF;
			echo $ts;				
				// $this->error('公众号已被绑定',U('/User/Index/index'));
				exit;
			}
			$new_data['uid'] = session('uid');
			$new_data['wxname'] = $detail['authorizer_info']['nick_name'];
			//verify_type_info 授权方认证类型，-1代表未认证，0代表微信认证，1代表新浪微博认证，2代表腾讯微博认证，3代表已资质认证通过但还未通过名称认证，4代表已资质认证通过、还未通过名称认证，但通过了新浪微博认证，5代表已资质认证通过、还未通过名称认证，但通过了腾讯微博认证
			// service_type_info 授权方公众号类型，0代表订阅号，1代表由历史老帐号升级后的订阅号，2代表服务号
			if($detail['authorizer_info']['service_type_info']['id'] == 2){//服务号
				switch ($detail['authorizer_info']['verify_type_info']['id']) {
					case '-1':
						$wxlb = 2;
						break;
					case '0':
						$wxlb = 3;
						break;
					case '1':
						$wxlb = 5;
						break;
					case '2':
						$wxlb = 6;
						break;
					case '3':
						$wxlb = 7;
						break;	
					case '4':
						$wxlb = 8;
						break;	
					case '5':
						$wxlb = 9;
						break;					
					default:
						$wxlb = 2;
						break;
				}
			}else{//订阅号
				switch ($detail['authorizer_info']['verify_type_info']['id']) {
					case '-1':
						$wxlb = 1;
						break;
					case '0':
						$wxlb = 4;
						break;
					case '1':
						$wxlb = 10;
						break;
					case '2':
						$wxlb = 11;
						break;
					case '3':
						$wxlb = 12;
						break;	
					case '4':
						$wxlb = 13;
						break;	
					case '5':
						$wxlb = 14;
						break;					
					default:
						$wxlb = 1;
						break;
				}
			}

			$new_data['winxintype'] = $wxlb;
			$new_data['appid'] = $detail['authorization_info']['authorizer_appid'];
			$new_data['wxid'] = $detail['authorizer_info']['user_name'];
			$new_data['weixin'] = $detail['authorizer_info']['alias'];

			$new_token = $this->weixin_newtoken($detail['authorizer_info']['user_name']);
			if(array_key_exists('head_img', $detail['authorizer_info'])){//在微信公众平台上设置头像了
				$img_p = $_SERVER['DOCUMENT_ROOT'].'/uploads/headimg/';
				$imgurl_head = '/uploads/headimg/'.md5($new_token).'.jpg';
				$this->save_img($detail['authorizer_info']['head_img'],$img_p,1,md5($new_token));
				$new_data['headerpic'] = $imgurl_head;
			}else{
				$new_data['headerpic'] = '/uploads/headimg/wx_mr.png';
			}
			$new_data['token'] = $new_token;
			$code_p = $_SERVER['DOCUMENT_ROOT'].'/uploads/qrcode/';
			$qrcodeimg = '/uploads/qrcode/'.md5($new_token).'.jpg';	
			$this->save_img($detail['authorizer_info']['qrcode_url'],$code_p,2,md5($new_token));

			// $new_data['qrcode_url'] = $qrcodeimg;
			if(!empty($wxinfo)){		
				$new_data['updatetime'] = time();
				M('Wxuser')->where(array('id'=>$wxinfo['id']))->save($new_data);
			}else{
				$new_data['tpltypeid'] = 1;
				$new_data['tpltypename'] = '120_index_pfs9';
				$new_data['tpllistid'] = 1;
				$new_data['tpllistname'] = 'yl_list';
				$new_data['tplcontentid'] = 1;
				$new_data['tplcontentname'] = 'ktv_content';
				$new_data['color_id'] = 0;
				$new_data['robot_status'] = 0;			
				$new_data['createtime'] = time();
				$new_data['updatetime'] = time();				
				$this->danpintong_info($new_token);
				M('Wxuser')->add($new_data);
			}
			$ts = <<<EOF
<style>
.wd{
	width:50%;
	min-width:230px;
	height:190px;
	background-color:#FFF;
	background:rgba(255, 255, 255, 1) none repeat scroll 0 0 !important;/*实现FF背景透明，文字不透明*/
	filter:Alpha(opacity=100); /*实现IE背景透明*/
	margin:0 auto;
	margin-top:10%;
	border:1px solid #CCC;
	box-shadow:0px 0px 5px 5px #CCC ;
	}
.wd .line1{
	width:100%;
	height:80px;
	float:left;
	}
.wd .line1 .close{
	text-align:center;
	font-size:48px;
	line-height:60px;
	font-weight:bolder;
	height:60px;
	width:60px;
	margin:0 auto;
	color:#FFF;
	margin-top:20px;
	background-color:#506276;
	-moz-border-radius: 30px;      /* Gecko browsers */
    -webkit-border-radius: 30px;   /* Webkit browsers */
    border-radius:30px;            /* W3C syntax */
	}
.wd .line2{
	line-height:40px;
	width:100%;
	height:40px;
	float:left;
	margin-top:10px;
	text-align:center;
	font-size:26px;
	color:#506276;
	font-weight:bolder;
	}
.wd .line3{
	line-height:30px;
	width:100%;
	height:30px;
	float:left;
	text-align:center;
	font-size:13px;
	color:#666;
	}
</style>			
<div id="body_cn">
</div>
<script>
	    window.onload = function(){
	var auto_time=5;
	var str="<div class='wd'>\
        			<div class='line1'>\
                		<div class='close'>√</div>\
            		</div>\
            		<div class='line2'>恭喜，授权成功</div>\
            		<div class='line3'>\
						<div>本页面将在<span id='jsf'>5</span>秒后自动关闭</div>\
            		</div>\
        		</div>";

	document.getElementById("body_cn").innerHTML=str;
	window.djs = setInterval(function(){
		auto_time = parseInt(auto_time)-1;
		document.getElementById('jsf').innerHTML = auto_time;
		if(auto_time == 0){
			window.opener=null;
			window.open('','_self');
			window.close();
		}	
		},1000);
		}	
</script>

EOF;
			echo $ts;
		}else{
			$this->auto_add();
			exit;
		}
	}
    //根据微信的原始id生成token，这样就能保证唯一性
    public function weixin_newtoken($wxid){
		$new_token = md5($wxid);
		$new_token = substr($new_token, 2,15);
        return $new_token;    	
    }
    public function save_img($img_url,$save_path,$i_flag,$file_name){
    	$save_path_o = $save_path.'origin_'.$file_name.'.jpg';
    	if(getimagesize($save_path) == false){
			$ch = curl_init();
	        $fp = fopen($save_path_o, 'wb');
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
	        //等比例压缩微信图片
	        if($i_flag == 1){ //微信头像
	        	resizeSaveImage($save_path_o,90,90,$save_path.$file_name,'.jpg');
	        }else{ //公众号的二维码
	        	resizeSaveImage($save_path_o,150,150,$save_path.$file_name,'.jpg');	        	
	        } 
    	}
    }
    //获取单品通信息
    private function danpintong_info($token){
        $id = session("uid");
        $user = M("Users")->where(array("id" => $id))->find();
        $member_id = $user['member_id'];
        if($member_id){
            //公司简介
            $conn=mysql_connect('192.168.6.74:3306','rostat','rostat-0409','wk_member_shard1');
            mysql_select_db("wk_member_shard1");
            mysql_query("set names utf8");
            $sql = "select * from member_basic where member_id = ".$member_id;
            $result = mysql_query($sql);
            $result = mysql_fetch_assoc($result);

            $params["token"] = $token;
            $params["isbranch"] = 0;
        	$params["name"] = $result["corporation_name"] == NULL ? "" : $result["corporation_name"];
            $params["shortname"] = $result["corporation_name"] == NULL ? "" : $result["corporation_name"];
            $params["address"] = $result["deal_in_address"] == NULL ? "" : $result["deal_in_address"];
            $params["intro"] = $result["summary"] == NULL ? "" : $result["summary"];
            $params["logourl"] = $result["logo"] == NULL ? "" : $result["logo"];;
            $params["catid"] = 0;
            $params["taxis"] = 0;
            $params["latitude"] = "0.00000";
            $params["longitude"] = "0.00000";
                
            $sql = "select mobile,telephone from member where member_id='".$member_id."'";
            $result = mysql_query($sql);
            $result = mysql_fetch_assoc($result);
                
            $params["mp"] = $result["mobile"] == NULL ? "" : $result["mobile"];
            $params["tel"] = $result["telephone"] == NULL ? "" : $result["telephone"];
            mysql_close($conn);
            $comid = M('Company')->field('id')->where(array('token'=>$token))->find();
            if(empty($comid)){
              	M("Company")->add($params);                	
            }else{
              	M('Company')->where(array('id'=>$comid['id']))->save($params);
            }
        }
    }

	/*获取开放平台的配置信息*/
	public function get_open_appid(){
		$this->appid = C('wxopen_appid');
		$this->appsecret = C('wxopen_appsecret');
		$this->open_token = C('wxopen_token');
		$this->encodingAesKey = C('wxopen_decy_key');
	}
	/*获取component_verify_ticket*/
	public function get_verify_ticket(){

        $encrypt_arr = include_once(RUNTIME_PATH.'auth_cache/wx_push_encrypt_msg.php');
        /*
        $encrypt_arr['Encrypt'] = 'qIiHYhn1bMwS0go+7VD25BSEf1D5JtAbd2m3SzkQ6fRehQB3KgSUMVknvUVuM5qFN+uYO48YxWuEEWrBTY3MA4E1eVa7yvjlNvRF3wgZUJOcQgLG5KmPzkZ5Di+DhtD7JjYPrHhUI+MUABK2zqgiN/swrDbHCDzAs7jLhOzIROf8PRZ9NTlST2JOrN02ae7SLsM3UMpDdBRdVgSVYdHmm4m2VPCvH/TtPqXBRKMC5oufhfuMSwXYKJE+fRa1Acijt26oihxyLGkfcdjDAO3/PAxAU651AzSPy4wOUeq7xFJbPIvS/8NMVOV2glEsHTDG9vwEJfr+tp11ToRhXzULgJ8U52hYoqaWS5tm9slM+Nyb+0jODEhkvrp6tShkAWlD2MvX+klMG5F6l5ekfiA5fshW2Z2jcIaB4mFvBMLNC47dlwzEkNarVEwWkpfUz/zzezm14h8pNY+a6hSszmikKw==';
        $encrypt_arr['timestamp'] = '1423105209';
        $encrypt_arr['nonce'] = '1734721505';
        $encrypt_arr['msg_signature'] = '1064ffe3f45976f8cc8b046aa3ef612a92783990';
        */
        if(empty($encrypt_arr)){
        	die('数据为空!');
        }else{
        	$encrypt = $encrypt_arr['Encrypt'];
        	$timeStamp = $encrypt_arr['timestamp'];
        	$nonce = $encrypt_arr['nonce'];
        	$msg_sign = $encrypt_arr['msg_signature'];
        	$pc = new WXBizMsgCrypt($this->open_token, $this->encodingAesKey, $this->appid);	    
	        // 第三方收到公众号平台发送的消息
	        $msg = '';
	        $errCode = $pc->decryptMsg_str($msg_sign, $timeStamp, $nonce, $encrypt, $msg);
	        // $errCode = $pc->decryptMsg($msg_sign, $timeStamp, $nonce, $from_xml, $msg);
	        if ($errCode == 0) {
		        $xml_tree = new DOMDocument();
		        $xml_tree->loadXML($msg);
		        $array_e = $xml_tree->getElementsByTagName('ComponentVerifyTicket');
		        $veriyticket = $array_e->item(0)->nodeValue;
		        return $veriyticket;
	        } else {
	            $this->error('解密失败!错误代码为:'.$errCode);
	        }
    	}
	}
	/*获取component_access_token
	*	参数1,开放平台的appid
	*   参数2,开放平台的appsecret
	*   参数3,微信每隔10分钟推送的ComponentVerifyTicket 需解密
	**/
	public function get_com_access_token(){
		if($this->cache_data['com_expires'] < time() || !array_key_exists('com_expires', $this->cache_data)){
			$p_data['component_appid'] = $this->appid;
			$p_data['component_appsecret'] = $this->appsecret;
			$p_data['component_verify_ticket'] = $this->ticket;
			$p_url = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
			$curl_res = curlPost($p_url,json_encode($p_data));
			$com_access_token = json_decode($curl_res,true);
			if(array_key_exists('errcode', $com_access_token)){
				die('token错误代码为:'.$com_access_token['errcode'].'错误信息为:'.$com_access_token['errmsg']);
			}else{
				//$com_access_token['expires_in'] = 7200s;
				$file_arr['component_access_token'] = $com_access_token['component_access_token'];
				$file_arr['com_expires'] = time() + 7100;
				if(!empty($this->cache_data)){
					$new_arr = $file_arr + $this->cache_data;
				}else{
					$new_arr = $file_arr;
				}
				file_put_contents($this->open_cache_file, "<?php \nreturn " . stripslashes(var_export($new_arr, true)) . ";", LOCK_EX);
				return $com_access_token['component_access_token'];
			}
		}else{
			return $this->cache_data['component_access_token'];
		}
	}
	/*获取预授权码*/
	public function get_preauthcode(){
		// if($this->cache_data['pre_expires'] < time()){
			$pre_data['component_appid'] = $this->appid;
			$pre_url = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token='.$this->com_access_token;
			$pre_res = curlPost($pre_url,json_encode($pre_data));
			$pre_code = json_decode($pre_res,true);
			if(array_key_exists('errcode', $pre_code)){
				die('code错误代码为:'.$pre_code['errcode'].'错误信息为:'.$pre_code['errmsg']);
			}else{
				//$pre_code['expires_in'] = 1800s;
				$pre_file['pre_auth_code'] = $pre_code['pre_auth_code'];
				$pre_file['pre_expires'] = time() + 1700;
				if(!empty($this->cache_data)){
					$new_arr = $pre_file + $this->cache_data;
				}else{
					$new_arr = $pre_file;
				}
				// file_put_contents($this->open_cache_file, "<?php \nreturn " . stripslashes(var_export($new_arr, true)) . ";", LOCK_EX);
				return $pre_code['pre_auth_code'];
			}
		// }else{
		// 	return $this->cache_data['pre_auth_code'];
		// }
	}
	/*根据授权码换取授权公众号的授权信息*/
	public function get_authorizer_code($code){
		$a_post['component_appid'] = $this->appid;
		$a_post['authorization_code'] = $code['auth_code'];
		$a_url = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token='.$this->com_access_token;
		$curl_code = curlPost($a_url,json_encode($a_post));
		$auth_arr = json_decode($curl_code,true);
		if(array_key_exists('errcode', $auth_arr)){
			//如果报错，不暂停程序，再次刷新，数据就可以获取到。
			echo "<script type=\"text/javascript\">window.location.reload(true);</script>";
			// die('authorizer错误代码为:'.$auth_arr['errcode'].'错误信息为:'.$auth_arr['errmsg']);
		}else{
			$auth['wx_auth_code'] = $code['auth_code'];
			$auth['auth_expires'] = time() + ($code['expires_in'] - 50);
			$auth['authorizer_appid'] = $auth_arr['authorization_info']['authorizer_appid'];
			$auth['authorizer_access_token'] = $auth_arr['authorization_info']['authorizer_access_token'];
			$auth['authorizer_expire'] = time() + ($auth_arr['authorization_info']['expires_in'] - 50);
			$auth['authorizer_refresh_token'] = $auth_arr['authorization_info']['authorizer_refresh_token'];
			$auth['func_info'] = serialize($auth_arr['authorization_info']['func_info']);
			//写入文件
			// $authorization_file = RUNTIME_PATH.'auth_cache/authorization_info_cache.php';
			// file_put_contents($authorization_file, "<?php \nreturn " . var_export($auth,true). ";",LOCK_EX);
			//写入数据库
			$code_res = M('Auth_code')->field('id')->where(array('authorizer_appid'=>$auth['authorizer_appid']))->find();
			if(empty($code_res)){
				M('Auth_code')->add($auth);
			}else{
				M('Auth_code')->where(array('id'=>$code_res['id']))->save($auth);
			}
			// return true;
			return $auth['authorizer_appid'];
		}	
	}
	/*返回auth的token*/
	public function return_token($need_appid){
		// $authorization_file = RUNTIME_PATH.'auth_cache/authorization_info_cache.php';
		// $auth_token = include_once($authorization_file);
		$old_seret = M('Wxuser')->where(array('appid'=>$need_appid))->getField('appsecret');
		if(empty($old_seret)){
			$auth_token = M('Auth_code')->where(array('authorizer_appid'=>$need_appid))->find();
			if($auth_token['authorizer_expire'] < time()){
				if(empty($auth_token['authorizer_access_token'])){
					$this->auto_add();
					exit;
				}
				$data = $this->refresh_authorizer_token($auth_token['authorizer_appid'],$auth_token['authorizer_refresh_token']);
				$new_auth_token['authorizer_access_token'] = $data['authorizer_access_token'];
				$new_auth_token['authorizer_expire'] = time() + ( $data['expires_in'] - 50);
				$new_auth_token['authorizer_refresh_token'] = $data['authorizer_refresh_token'];
				// file_put_contents($authorization_file, "<?php \nreturn " . var_export($auth_token,true). ";",LOCK_EX);
				M('Auth_code')->where(array('id'=>$auth_token['id']))->save($new_auth_token);
				return array('authorizer_access_token'=>$data['authorizer_access_token'],'authorizer_appid'=>$auth_token['authorizer_appid']);
			}else{
				return array('authorizer_access_token'=>$auth_token['authorizer_access_token'],'authorizer_appid'=>$auth_token['authorizer_appid']);
			}
		}else{
			$data = json_decode(file_get_contents($this->cache_now_file));
		    if ($data->expire_time < time()) {
		      $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$need_appid."&secret=".$old_seret;
		      $res = json_decode(curlGet($url));
		      $access_token = $res->access_token;
		      if ($access_token) {
		        $data->expire_time = time() + 7000;
		        $data->access_token = $access_token;
		        file_put_contents($this->cache_now_file,json_encode($data),LOCK_EX);
		        return $access_token;
		      }
		    }else{
		    	return $data->access_token;
		    }
		}
	}

	/*通过请求返回token*/
	public function http_token(){
		$need_appid = $this->_get('appid','trim');
		if(empty($need_appid)){
			echo json_encode(array('error'=>-1,'msg'=>'appid为空!'));
			exit;
		}
		$old_seret = M('Wxuser')->where(array('appid'=>$need_appid))->getField('appsecret');
		if(empty($old_seret)){
			$auth_token = M('Auth_code')->where(array('authorizer_appid'=>$need_appid))->find();
			if(empty($auth_token)){
				echo json_encode(array('error'=>-2,'msg'=>'查询数据为空!'));
				exit;
			}
			if($auth_token['authorizer_expire'] < time()){
				$data = $this->refresh_authorizer_token($auth_token['authorizer_appid'],$auth_token['authorizer_refresh_token']);
				$new_auth_token['authorizer_access_token'] = $data['authorizer_access_token'];
				$new_auth_token['authorizer_expire'] = time() + ( $data['expires_in'] - 50);
				$new_auth_token['authorizer_refresh_token'] = $data['authorizer_refresh_token'];
				M('Auth_code')->where(array('id'=>$auth_token['id']))->save($new_auth_token);
				echo json_encode(array('error'=>0,'authorizer_access_token'=>$data['authorizer_access_token'],'authorizer_appid'=>$auth_token['authorizer_appid']));
				exit;
			}else{
				echo json_encode(array('error'=>0,'authorizer_access_token'=>$auth_token['authorizer_access_token'],'authorizer_appid'=>$auth_token['authorizer_appid']));
				exit;
			}
		}else{
			$data = json_decode(file_get_contents($this->cache_now_file));
		    if ($data->expire_time < time()) {
		      $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$need_appid."&secret=".$old_seret;
		      $res = json_decode(curlGet($url));
		      $access_token = $res->access_token;
		      if ($access_token) {
		        $data->expire_time = time() + 7000;
		        $data->access_token = $access_token;
		        file_put_contents($this->cache_now_file,json_encode($data),LOCK_EX);
		        echo json_encode(array('error'=>0,'authorizer_access_token'=>$access_token,'authorizer_appid'=>$need_appid));
		        exit;
		      }
		    }else{
		    	echo json_encode(array('error'=>0,'authorizer_access_token'=>$data->access_token,'authorizer_appid'=>$need_appid));
		    	exit;
		    }
		}
	}	

	/*刷新authorizer_access_token*/
	public function refresh_authorizer_token($authorizer_appid,$authorizer_refresh_token){
		$r_post['component_appid'] = $this->appid;
		$r_post['authorizer_appid'] = $authorizer_appid;
		$r_post['authorizer_refresh_token'] = $authorizer_refresh_token;
		$r_url = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.$this->com_access_token;
		$refresh_res = curlPost($r_url,json_encode($r_post));
		$refresh_arr = json_decode($refresh_res,true);
		if(array_key_exists('errcode', $refresh_arr)){
			die('refresh_authorizer错误代码为:'.$refresh_arr['errcode'].'错误信息为:'.$refresh_arr['errmsg']);
		}else{
			return $refresh_arr;
		}
	}
	/*读取公众号基本信息的信息*/
	public function get_detail_info($authorizer_appid){
		$i_post['component_appid'] = $this->appid;
		$i_post['authorizer_appid'] = $authorizer_appid;
		$i_url = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token='.$this->com_access_token;
		$info = curlPost($i_url,json_encode($i_post));
		$info_arr = json_decode($info,true);
		if(array_key_exists('errcode', $info_arr)){
			die('api_get_authorizer_info错误代码为:'.$info_arr['errcode'].'错误信息为:'.$info_arr['errmsg']);
		}else{
			return $info_arr;
		}		
	}
	/*根据微信推送的取消授权信息,删除数据库的记录*/
	public function cancel_info(){
		$cancel_appid = $this->_get('AuthorizerAppid','trim');
		M('Wxuser')->where(array('appid'=>$cancel_appid))->limit(1)->delete();
		M('Auth_code')->where(array('authorizer_appid'=>$cancel_appid))->limit(1)->delete();
		// header('Location:'.U('/User/Index/info'));
		// header('Location:'.U('/User/Index/index'));
		$new_url = U('/User/Index/index');
		echo "<script type=\"text/javascript\">window.top.location.href=\"$new_url\"</script>";
		die();
	}
}
?>