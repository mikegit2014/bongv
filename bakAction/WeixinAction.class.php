<?php
class WeixinAction extends Action {
	private $token;
	private $fun;
	private $data = array ();
	private $my = '帮微';
	public  $siteUrl;
	private $showUrl;
	public function _initialize(){
		$this->showUrl = C('WEBSHOW_URL');
	}
	public function writeLog($msg) {
		$logFile = date ( 'Y-m-d' ) . 'wangku.txt';
		if(is_array($msg)){
			file_put_contents($logFile,  stripslashes(var_export($msg, true))."\r\n" ,FILE_APPEND);
		}else{
			$msg = date ( 'Y-m-d H:i:s' ) . ' >>> ' . $msg . "\r\n";
			file_put_contents ( $logFile, $msg, FILE_APPEND );
		}
	}
	public function index() {
		$this->siteUrl = C ( 'site_url' );
		$this->token = $this->_get ( 'token', "htmlspecialchars" );
		$new_appid = $this->_get('appid','trim');


		if(!isset($this->token) && isset($new_appid) && !empty($new_appid)){
			$this->token = M('Wxuser')->where(array('appid'=>$new_appid))->getField('token');
		}
		if($new_appid == 'wx570bc396a51b8ff8'){
			$this->writeLog('微信测试账号--'.$this->token);
			$this->token = '9875b0da869f427';
		}

		if (! preg_match ( "/^[0-9a-zA-Z]{3,42}$/", $this->token )) {
			exit ( 'error token' );
		}

		$weixin = new Wechat ( $this->token , $new_appid);
		$data = $weixin->request ();
		$this->data = $weixin->request ();
		$this->my = C ( 'site_my' );
		$open = M ( 'Token_open' )->where ( array (
				'token' => $this->_get ( 'token' ) 
		) )->find ();
		$this->fun = $open ['queryname'];
		
		$this->writeLog ( "*************数据接收开始*********" );
		$this->writeLog ($data);

		list ( $content, $type ) = $this->reply ( $data ,$new_appid);
		
		if (empty($content)){
			
			//去掉微信默认发送位置，发送消息
			if(in_array($data['Event'],array('unsubscribe','LOCATION'))){
				$this->writeLog ("*************数据接收结束*********");
				echo '';
				exit;
			}else{
				if($this->areply_return('No') === false){
					echo "";
					exit;
				}
				list ( $nocontent, $notype ) = $this->areply_return('No');
				$this->writeLog ($nocontent);
				$this->writeLog ("*************数据接收结束*********");
				$weixin->response( $nocontent, $notype );
			}			
			/*
			if($this->areply_return('No') === false){
				echo "";
				exit;
			}
			list ( $nocontent, $notype ) = $this->areply_return('No');
			$this->writeLog ($nocontent);
			$this->writeLog ("*************数据接收结束*********");
			$weixin->response( $nocontent, $notype );
			*/
		}else{
			$this->writeLog ($content);
			$this->writeLog ("*************数据接收结束*********");
			$weixin->response($content, $type );
		}	
	}
	
	private function robotFun($data) {
		$this->writeLog ($data );
		$keyworld = iconv ( "utf-8", "gb2312", $data );
		$content = file_get_contents ( "http://dev.skjqr.com/api/weixin.php?email=1798124454@qq.com&appkey=e10dce4f9c2b11377927509af2308a1e&msg=" . $keyworld );
		$content = str_replace ( "[msg]", "", $content );
		$content = str_replace ( "[/msg]", "", $content );
		$this->writeLog ( $content );
		return $content;
	}
	private function reply($data ,$appid) { // 如果菜单的点击事件 统一数据格式
		$this->behaviordata (  );
		if ('CLICK' == $data ['Event']) {
			$data ['Content'] = $data ['EventKey'];
			$this->data ['Content'] = $data ['EventKey'];
		}
		if ('MASSSENDJOBFINISH' == $data ['Event']) {
			$msg_id = $data ['MsgID'];
			$reachcount = $data ['SentCount'];
			$errorreachcount = $data ['ErrorCount'];
			$totalreachcount = $data ['FilterCount'];
			
			$update_sql = "update tp_send_message set `reachcount` = `reachcount` + $reachcount, `errorreachcount` = `errorreachcount` + $errorreachcount, `totalreachcount` = `totalreachcount` + $totalreachcount where msg_id='$msg_id'";
			mysql_query( $update_sql );
		}
		// 消息类型为语音
		if ('voice' == $data ['MsgType']) {
			$data ['Content'] = $data ['Recognition'];
			$this->data ['Content'] = $data ['Recognition'];
		}
		// 消息类型为图片
		if ('image' == $data ['MsgType']) {
			return $this->other ();
		}

		if($appid == 'wx570bc396a51b8ff8'){
			/*开放平台全网发布时,根据微信的信息进行回复*/
			if ($data ['Content'] == 'TESTCOMPONENT_MSG_TYPE_TEXT') {
				return array(
						'TESTCOMPONENT_MSG_TYPE_TEXT_callback',
						'text' 
				);
			}
			if ('LOCATION' == $data ['Event']) {
				return array (
						'LOCATIONfrom_callback',
						'text' 
				);
			}
			if(stristr($data['Content'],'QUERY_AUTH_CODE') !== false){
				$tmp_str = explode(":", $data['Content']);
				return array (
						$tmp_str[1].'_from_api',
						'text' 
				);
			}	
		}

		
		if ($data ['Event'] == 'SCAN') {
			$data ['Content'] = $this->getRecognition ( $data ['EventKey'] );
			$this->data ['Content'] = $data ['Content'];
		}
		// 关注事件
		if ('subscribe' == $data ['Event']) {
			// 用户关注 model 为 follow
			// $this->behaviordata ( 'follow', '1' );
			// 更新或者保存用户信息
			//只有微信认证过的公众号才能有调取用户信息的权限
			$wx_type = M('Wxuser')->where(array('appid'=>$appid))->field('winxintype,appid,appsecret')->find();
			if(in_array($wx_type['winxintype'],array(3,4))){
				$this->addUserInfo($appid,$wx_type['appsecret']);
			}
			$this->requestdata ( 'follownum' );
			
			//检测是否是通过年假活动的关注者 Jerry 2015-01-26
			if( $this->token == '7a2ab1f444a4ea3' ){//移众传媒号测试，正式号 网库互通
				$activityResult = $this -> activitySubscribeEvent();
				if( !empty( $activityResult ) ){
					return $activityResult;
				}
			}
			
			if(empty($data['EventKey'])){ //正常关注
				return $this->areply_return('Yes');
			}else{//扫码活动二维码时，出现的二维码
				$eventKey = explode('_',$data['EventKey']);
				return $this->scan_areplay($eventKey[1]);
			}
			
			//return $this->areply_return('Yes');

		} elseif ('unsubscribe' == $data ['Event']) {
			// 取消关注
			$this->requestdata ( 'unfollownum' );
			// 删除用户信息
			$this->delUserInfo();
			
			//监测取消关注者是否参与过年假活动 Jerry 2015-01-26
			if( $this->token == '7a2ab1f444a4ea3' ){//移众传媒号测试, 正式号 网库互通
				$this -> activityUnsubscribeEvent();
			}
		}
		
		if ($data ['Content'] == 'wechat ip') {
			return array (
					$_SERVER ['REMOTE_ADDR'],
					'text' 
			);
		}
		
		if (! (strpos ( $this->fun, 'api' ) === FALSE) && $data ['Content']) {
			$apiData = M ( 'Api' )->where ( array (
					'token' => $this->token,
					'status' => 1 
			) )->select ();
			foreach ( $apiData as $apiArray ) {
				if (! (strpos ( $data ['Content'], $apiArray ['keyword'] ) === FALSE)) {
					$api ['type'] = $apiArray ['type'];
					$api ['url'] = $apiArray ['url'];
					break;
				}
			}
			if ($api != false) {
				$vo ['fromUsername'] = $this->data ['FromUserName'];
				$vo ['Content'] = $this->data ['Content'];
				$vo ['toUsername'] = $this->token;
				if ($api ['type'] == 2) {
					$apidata = $this->api_notice_increment ( $api ['url'], $vo );
					return array (
							$apidata,
							'text' 
					);
				} else {
					$xml = file_get_contents ( "php://input" );
					$apidata = $this->api_notice_increment ( $api ['url'], $xml );
					header ( "Content-type: text/xml" );
					exit ( $apidata );
					return false;
				}
			}
		}
		if (! (strpos ( $data ['Content'], '附近' ) === FALSE)) {
			$this->recordLastRequest ( $data ['Content'] );
			$return = $this->fujin ( array (
					str_replace ( '附近', '', $data ['Content'] ) 
			) );
		} elseif (! (strpos ( $data ['Content'], '公交' ) === FALSE)) {
			$return = $this->gongjiao ( explode ( '公交', $data ['Content'] ) );
		} elseif (! (strpos ( $data ['Content'], '域名' ) === FALSE)) {
			$return = $this->yuming ( str_replace ( '域名', '', $data ['Content'] ) );
		} else {
			if (strtolower ( substr ( $data ['Content'], 0, 3 ) ) == "yyy") {
				$key = "摇一摇";
				$yyyphone = substr ( $data ['Content'], 3, 11 );
			} elseif (substr ( $data ['Content'], 0, 2 ) == "##") {
				$key = "微信墙";
				$wallmessage = substr_replace ( $data ['Content'], "", 0, 2 );
			} else
				$key = $data ['Content'];
			$datafun = explode ( ',', $this->fun );
			$tags = $this->get_tags ( $key );
			$back = explode ( ',', $tags );

			if(trim($key) == '绑定'){ //绑定成功,发送绑定,发送消息。
				return array (
					'帮微-专业的微信服务',
					'text' 
				);
			}

			if ($key == '首页' || $key == 'home') {
				return $this->home ();
			}
		}
		if (! empty ( $return )) {
			if (is_array ( $return )) {
				return $return;
			} else {
				return array (
						$return,
						'text' 
				);
			}
		} else {
			if (! (strpos ( $key, 'cheat' ) === FALSE)) {
				$arr = explode ( ' ', $key );
				$datas ['lid'] = intval ( $arr [1] );
				$lotteryPassword = $arr [2];
				$datas ['prizetype'] = intval ( $arr [3] );
				$datas ['intro'] = $arr [4];
				$datas ['wecha_id'] = $this->data ['FromUserName'];
				$thisLottery = M ( 'Lottery' )->where ( array (
						'id' => $datas ['lid'] 
				) )->find ();
				if ($lotteryPassword == $thisLottery ['parssword']) {
					$rt = M ( 'Lottery_cheat' )->add ( $datas );
					if ($rt) {
						return array (
								'设置成功',
								'text' 
						);
					}
					return array (
							'设置失败:未知原因',
							'text' 
					);
				} else {
					return array (
							'设置失败:密码不对',
							'text' 
					);
				}
			}
			if ($this->data ['Location_X']) {
				$this->recordLastRequest ( $this->data ['Location_Y'] . ',' . $this->data ['Location_X'], 'location');
				return $this->map ( $this->data ['Location_X'], $this->data ['Location_Y'] );
			}
			if(!(strpos($key, '开车去') === FALSE)||!(strpos($key, '坐公交') === FALSE)||!(strpos($key, '步行去') === FALSE)){
				$this->recordLastRequest($key);
				//查询是否有一分钟内的经纬度
				$user_request_model=M('User_request');
				$loctionInfo=$user_request_model->where(array('token'=>$this->_get('token'),'msgtype'=>'location','uid'=>$this->data['FromUserName']))->find();
				if ($loctionInfo&&intval($loctionInfo['time']>(time()-60))){
					$latLng=explode(',',$loctionInfo['keyword']);
					return $this->map($latLng[1],$latLng[0]);
				}
				return array('请发送您所在的位置(对话框右下角点击＋号，然后点击“位置”)','text');
			}
			$key = trim( $key );//Jerry 2014-12-29
			switch ($key) {
				case '首页' :
				case 'home' :
					return $this->home ();
					break;
				case '主页' :
					return $this->home ();
					break;
				case '地图' :
					return $this->companyMap ();
				case '最近的' :
					$this->recordLastRequest($key);
					//查询是否有一分钟内的经纬度
					$user_request_model=M('User_request');
					$loctionInfo=$user_request_model->where(array('token'=>$this->_get('token'),'msgtype'=>'location','uid'=>$this->data['FromUserName']))->find();
					if ($loctionInfo&&intval($loctionInfo['time']>(time()-60))){
						$latLng=explode(',',$loctionInfo['keyword']);
						return $this->map($latLng[1],$latLng[0]);
					}
					return array('请发送您所在的位置(对话框右下角点击＋号，然后点击“位置”)','text');
					break;
				case '帮助' :
					return $this->help ();
					break;
				case 'help' :
					return $this->help ();
					break;
				case '会员卡' :
					return $this->member ();
					break;
				case '会员' :
					return $this->member ();
					break;
				case '3g相册' :
					return $this->xiangce ();
					break;
				case '相册' :
					return $this->xiangce ();
					break;
				case '微秀' :
					return $this->weshow ();
					break;
				case '年假' :
					$pro = M ( 'Img' )->where ( array (
							'keyword' => '年假',
							'token' => $this->token 
					) )->find ();
					
					//获取年假活动信息
					$activityInfo = M( 'Activity_publisher' ) -> where( array( 'token'=>$this->token, 'name' => '年假活动') ) -> find();
					if( !empty( $activityInfo ) ){
						return array (
								array (
										array (
												$pro ['title'],
												strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $pro ['text'] ) ) ),
												$pro ['pic'],
												C( 'm_site_url' ) . U( '/Wap/Activity/makeNjFrom', array( 'token'=> $this->token, 'aid'=> $activityInfo['id'], 'uid'=>$this->data ['FromUserName'] ) )
										)
								),
								'news'
						);
					}
					break;
				case '订餐' :
					$pro = M ( 'reply_info' )->where ( array (
							'infotype' => 'Dining',
							'token' => $this->token 
					) )->find ();
					return array (
							array (
									array (
											$pro ['title'],
											strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $pro ['info'] ) ) ),
											$pro ['picurl'],
											((((C ( 'site_url' ) . '/index.php?g=Wap&m=Dining&a=index&dining=1&token=') . $this->token) . '&wecha_id=') . $this->data ['FromUserName']) 
									) 
							),
							'news' 
					);
					break;
				case '留言' :
					$pro = M ( 'reply_info' )->where ( array (
							'infotype' => 'message',
							'token' => $this->token 
					) )->find ();
					if ($pro) {
						return array (
								array (
										array (
												$pro ['title'],
												strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $pro ['info'] ) ) ),
												$pro ['picurl'],
												C ( 'site_url' ) . '/index.php?g=Wap&m=Reply&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
										) 
								),
								'news' 
						);
					} else {
						return array (
								array (
										array (
												'留言板',
												'在线留言',
												rtrim ( C ( 'site_url' ), '/' ) . '/tpl/Wap/default/common/css/style/images/ly.jpg',
												C ( 'site_url' ) . '/index.php?g=Wap&m=Reply&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
										) 
								),
								'news' 
						);
					}
					break;		
				case '微信墙' :
					// 判断商家是否开启
					$yyy = M ( 'Wewall' )->where ( array (
							'isact' => '1',
							'token' => $this->token 
					) )->find ();
					$welog = array ();
					if ($yyy == false) {
						return array (
								'目前商家未开启微信墙活动',
								'text' 
						);
					}
					// 进入开启状态处理 step1 检查是否需要生成sn码抽奖
					$openid = $this->data ['FromUserName'];
					$exs = M ( 'Wewalllog' )->where ( array (
							'openid' => $openid,
							'token' => $this->token 
					) )->find ();
					/*
					 * if ($yyy['iflottery'] == '1' && $exs == false) { $welog['sncode'] = $this->sncode(); }
					 */
					$welog ['content'] = $wallmessage;
					$welog ['uid'] = $yyy ['id'];
					$welog ['token'] = $this->token;
					$welog ['updatetime'] = time ();
					$welog ['ifsent'] = '0';
					$welog ['ifscheck'] = '0';
					if ($yyy ['ifcheck'] == '0') {
						$welog ['ifcheck'] = '1';
					} else {
						$welog ['ifcheck'] = '0';
					}
					if ($exs == false) {
						$welog ['openid'] = $openid;
						M ( 'Wewalllog' )->add ( $welog );
						$sncode = $welog ['sncode'];
					} else {
						M ( 'Wewalllog' )->where ( array (
								'openid' => $openid,
								'token' => $this->token 
						) )->save ( $welog );
						$sncode = $exs ['sncode'];
					}
					if ($yyy ['iflottery'] == '1') {
						return array (
								'上墙成功！获得sn号码为[' . $sncode . '],请留意抽奖环节哦',
								'text' 
						);
					} else {
						return array (
								'上墙成功！祝君万事如意',
								'text' 
						);
					}
				case '摇一摇' :
					$yyy = M ( 'Shake' )->where ( array (
							'isopen' => '1',
							'token' => $this->token 
					) )->find ();
					if ($yyy == false) {
						return array (
								'目前没有正在进行中的摇一摇活动',
								'text' 
						);
					}
					if (! preg_match ( "/^1[3|4|5|8][0-9]\d{4,8}$/", $yyyphone )) {
						return array (
								'输入错误，请输入yyy加您的手机号码，例如yyy13647810523',
								'text' 
						);
					}
					$url = C ( 'site_url' ) . U ( 'Wap/Toshake/index', array (
							'token' => $this->token,
							'phone' => $yyyphone,
							'wecha_id' => $this->data ['FromUserName'] 
					) );
					return array (
							'<a href="' . $url . '">点击进入刺激的现场摇一摇活动</a>',
							'text' 
					);
				
				case '全景' :
					$pro = M ( 'reply_info' )->where ( array (
							'infotype' => 'panorama',
							'token' => $this->token 
					) )->find ();
					if ($pro) {
						return array (
								array (
										array (
												$pro ['title'],
												strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $pro ['info'] ) ) ),
												$pro ['picurl'],
												C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
										) 
								),
								'news' 
						);
					} else {
						return array (
								array (
										array (
												'360°全景看车看房',
												'通过该功能可以实现3D全景看车看房',
												rtrim ( C ( 'site_url' ), '/' ) . '/tpl/User/52jscn/common/images/panorama/360view.jpg',
												C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
										) 
								),
								'news' 
						);
					}
					break;
				default :
					return $this->keyword ( $key );
			}
		}
	}
	
	public function btshow($sceneid_bigint){
				
				   $host   = C('BTSHOW_SYSTEM_DB_HOST');
				   $name   = C('BTSHOW_SYSTEM_DB_NAME');
				   $user   = C('BTSHOW_SYSTEM_DB_USER');
				   $pwd    = C('BTSHOW_SYSTEM_DB_PWD');
				   $pre    = C('BTSHOW_SYSTEM_DB_PREFIX');
				   
				 
				   $pid    = $sceneid_bigint;
				   
				   
				   
				   $conn   = mysql_connect($host,$user,$pwd);
				   
				    if (!$conn){
						return '';
					}
				   
				   mysql_select_db($name,$conn);
		
				   mysql_query ('set names UTF8');
				   
				   $sql_account        = "select * from `".$pre."publicaccount` where `id`=".$pid;
				   
				   $result_account     = mysql_query($sql_account);
         
				   $res_account        = mysql_fetch_assoc($result_account);
				   
				   if(!empty($res_account)){
					   //++++++++++++++++++++++++++
					   $sql        = "select * from `".$pre."scene` where `sceneid_bigint`=".$res_account['sceneid'];
				   
				   $result = mysql_query($sql);
         
				   $res = mysql_fetch_assoc($result);
	
				   mysql_close($conn);
				   
				   if(!empty($res)){
					   //-----------
					   $data ['title']  = $res['token_title'];
							$data ['info']   = $res['token_intro'];
		           	     	$data ['url']    = $this->showUrl.'/v-'.$res['scenecode_varchar'].'?token='.$res['userid_int'];
		                    $data ['picurl'] = $this->showUrl.'/Uploads/'.$res['token_thumb'];
		
		           	     	
		           	     	return array (
		           	     			array (
		           	     					array (
		           	     							$data ['title'],
		           	     							$data ['info'],
		           	     							$data ['picurl'],
		           	     							$data ['url']
		           	     					)
		           	     			),
		           	     			'news'
		           	     	);
					   //------------
					   
				   }
					   
					   //++++++++++++++++++++++++++
				   }
				   
				   
				   
				   
				   
				   
				   
	}
	
	private function xiangce() {
		// $this->behaviordata ( 'album', '', '1' );
		$photo = M ( 'Photo' )->where ( array (
				'token' => $this->token,
				'status' => 1 
		) )->find ();
		$data ['title'] = $photo ['title'];
		$data ['keyword'] = $photo ['info'];
		$data ['url'] = rtrim ( C ( 'site_url' ), '/' ) . U ( 'Wap/Photo/index', array (
				'token' => $this->token,
				'wecha_id' => $this->data ['FromUserName'] 
		) );
		$data ['picurl'] = $photo ['picurl'] ? $photo ['picurl'] : rtrim ( C ( 'site_url' ), '/' ) . '/tpl/static/images/yj.jpg';
		return array (
				array (
						array (
								$data ['title'],
								$data ['keyword'],
								$data ['picurl'],
								$data ['url'] 
						) 
				),
				'news' 
		);
	}
	private function weshow() {
		// $this->behaviordata ( 'weshow', '', '1' );
		
		// ---zr -start
		$start = strtotime ( date ( 'Y-m-d' ) );
		
		$arr_data = array (
				'token' => $this->token,
				'status' => 1 
		);
		
		$arr_data ['startTime'] = array (
				'ELT',
				$start 
		);
		$arr_data ['endTime'] = array (
				'EGT',
				$start 
		);
		
		$photo = M ( 'Weshow' )->where ( $arr_data )->order ( 'startTime asc,id desc' )->find ();
		
		if($photo){
		//-------------
		$data ['title'] = $photo ['share_title'];
		$data ['keyword'] = $photo ['share_con'];
		$data ['url'] = rtrim ( C ( 'm_site_url' ), '/' ) . U ( 'Wap/Weshow/index', array (
				'token' => $this->token,
				'wecha_id' => $this->data ['FromUserName'],
				'pid' => $photo ['id'] 
		) );
		$data ['picurl'] = $photo ['picurl'] ? $photo ['picurl'] : rtrim ( C ( 'site_url' ), '/' ) . '/tpl/static/images/yj.jpg';
		return array (
				array (
						array (
								$data ['title'],
								$data ['keyword'],
								$data ['picurl'],
								$data ['url'] 
						) 
				),
				'news' 
		);
		//-------------
		}else{
			
			return array (
					'无此图文信息,没有默认微秀或默认微秀已经过期！请提醒商家，重新设定关键词！',
					'text'
			);
			
		}
		// --- end
	}
	private function companyMap() {
		import ( "Home.Action.MapAction" );
		$mapAction = new MapAction ();
		return $mapAction->staticCompanyMap ();
	}
	private function shenhe($name) {
		// $this->behaviordata ( 'usernameCheck', '', '1' );
		$name = implode ( '', $name );
		if (empty ( $name )) {
			return '正确的审核帐号方式是：审核+帐号';
		} else {
			$user = M ( 'Users' )->field ( 'id' )->where ( array (
					'username' => $name 
			) )->find ();
			if ($user == false) {
				return '主人' . $this->my . "提醒您,您还没注册吧\n正确的审核帐号方式是：审核+帐号,不含+号";
			} else {
				$up = M ( 'users' )->where ( array (
						'id' => $user ['id'] 
				) )->save ( array (
						'status' => 1,
						'viptime' => strtotime ( "+1 day" ) 
				) );
				if ($up != false) {
					return '主人' . $this->my . '恭喜您,您的帐号已经审核,您现在可以登陆平台测试功能啦!';
				} else {
					return '服务器繁忙请稍后再试';
				}
			}
		}
	}
	private function huiyuanka($name) {
		return $this->member ();
	}
	private function member() {
		$card = M ( 'member_card_create' )->where ( array (
				'token' => $this->token,
				'wecha_id' => $this->data ['FromUserName'] 
		) )->find ();
		$cardInfo = M ( 'member_card_set' )->where ( array (
				'token' => $this->token 
		) )->find ();
		// $this->behaviordata ( 'Member_card_set', $cardInfo ['id'] );
		$reply_info_db = M ( 'Reply_info' );
		if ($card) {
			$where_member = array (
					'token' => $this->token,
					'infotype' => 'membercard' 
			);
			$memberConfig = $reply_info_db->where ( $where_member )->find ();
			if (! $memberConfig) {
				$memberConfig = array ();
				$memberConfig ['picurl'] = rtrim ( C ( 'site_url' ), '/' ) . '/tpl/static/images/vip.jpg';
				$memberConfig ['title'] = '会员卡,省钱，打折,促销，优先知道,有奖励哦';
				$memberConfig ['info'] = '尊贵vip，是您消费身份的体现,会员卡,省钱，打折,促销，优先知道,有奖励哦';
			}
			$data ['picurl'] = $memberConfig ['picurl'];
			$data ['title'] = $memberConfig ['title'];
			$data ['keyword'] = $memberConfig ['info'];
			if (! $memberConfig ['apiurl']) {
				$data ['url'] = rtrim ( C ( 'site_url' ), '/' ) . U ( 'Wap/Card/index', array (
						'token' => $this->token,
						'wecha_id' => $this->data ['FromUserName'] 
				) );
			} else {
				$data ['url'] = str_replace ( '{wechat_id}', $this->data ['FromUserName'], $memberConfig ['apiurl'] );
			}
		} else {
			$where_unmember = array (
					'token' => $this->token,
					'infotype' => 'membercard_nouse' 
			);
			$unmemberConfig = $reply_info_db->where ( $where_unmember )->find ();
			if (! $unmemberConfig) {
				$unmemberConfig = array ();
				$unmemberConfig ['picurl'] = rtrim ( C ( 'site_url' ), '/' ) . '/tpl/static/images/member.jpg';
				$unmemberConfig ['title'] = '申请成为会员';
				$unmemberConfig ['info'] = '申请成为会员，享受更多优惠';
			}
			$data ['picurl'] = $unmemberConfig ['picurl'];
			$data ['title'] = $unmemberConfig ['title'];
			$data ['keyword'] = $unmemberConfig ['info'];
			if (! $unmemberConfig ['apiurl']) {
				$data ['url'] = rtrim ( C ( 'site_url' ), '/' ) . U ( 'Wap/Card/index', array (
						'token' => $this->token,
						'wecha_id' => $this->data ['FromUserName'] 
				) );
			} else {
				$data ['url'] = str_replace ( '{wechat_id}', $this->data ['FromUserName'], $unmemberConfig ['apiurl'] );
			}
		}
		return array (
				array (
						array (
								$data ['title'],
								$data ['keyword'],
								$data ['picurl'],
								$data ['url'] 
						) 
				),
				'news' 
		);
	}
	private function taobao($name) {
		$name = array_merge ( $name );
		$data = M ( 'Taobao' )->where ( array (
				'token' => $this->token 
		) )->find ();
		if ($data != false) {
			if (strpos ( $data ['keyword'], $name )) {
				$url = $data ['homeurl'] . '/search.htm?search=y&keyword=' . $name . '&lowPrice=&highPrice=';
			} else {
				$url = $data ['homeurl'];
			}
			return array (
					array (
							array (
									$data ['title'],
									$data ['keyword'],
									$data ['picurl'],
									$url 
							) 
					),
					'news' 
			);
		} else {
			return '商家还未及时更新淘宝店铺的信息,回复帮助,查看功能详情';
		}
	}
	private function choujiang($name) {
		$data = M ( 'lottery' )->field ( 'id,keyword,info,title,starpicurl' )->where ( array (
				'token' => $this->token,
				'status' => 1,
				'type' => 1 
		) )->order ( 'id desc' )->find ();
		if ($data == false) {
			return array (
					'暂无抽奖活动',
					'text' 
			);
		}
		$pic = $data ['starpicurl'] ? $data ['starpicurl'] : rtrim ( C ( 'site_url' ), '/' ) . '/tpl/User/52jscn/common/images/img/activity-lottery-start.jpg';
		$url = rtrim ( C ( 'site_url' ), '/' ) . U ( 'Wap/Lottery/index', array (
				'type' => 1,
				'token' => $this->token,
				'id' => $data ['id'],
				'wecha_id' => $this->data ['FromUserName'] 
		) );
		return array (
				array (
						array (
								$data ['title'],
								$data ['info'],
								$pic,
								$url 
						) 
				),
				'news' 
		);
	}
	private function keyword($key) {
		$like ['keyword'] = $key;
		$like ['token'] = $this->token;
		$like ['absolute'] = '1';
		$data = M ( 'keyword' )->where ( $like )->order ( 'id desc' )->find ();
		
		if ($data == false) {
			$like ['keyword'] = array (
					'like',
					'%' . $key . '%' 
			);
			$like ['token'] = $this->token;
			$like ['absolute'] = '0';
			$data = M ( 'keyword' )->where ( $like )->order ( 'id desc' )->find ();
		}
		if ($data != false) {
			// $this->behaviordata ( $data ['module'], $data ['pid'] );
			
			switch ($data ['module']) {
				/***自定义菜单微信回复start***/
				/**自定义菜单文本回复**/
				case 'Diymen_text':	
					$textWhere['id'] = $data['pid'];
					$textWhere['token'] = $this->token;
					$textback = M("Diymen_saveinfo")->field("info")->where($textWhere)->find();															
					$txt_content = html_entity_decode( htmlspecialchars_decode($textback['info']) );					
					$txt_content = str_replace(array('<p>','</p>'), '', $txt_content);
					return array (
							trim($txt_content),
							'text'
					);
					break;
					/**自定义菜单单图文回复**/
				case 'Diymen_imgtext':
					$imgtext_db = M('Diymen_saveinfo');
					$imgtextwhere['token'] = $this->token;
					$imgtextWhere['id'] = $data['pid'];
					$imgtextdata = $imgtext_db->field('id,pic,skipUrl,title,originalUrl,detailedinfo,info')->where($imgtextwhere)->find();
					
					if(empty($imgtextdata['detailedinfo']) && !empty($imgtextdata['skipUrl'])){
						$url = $imgtextdata['skipUrl'];
						if(stristr($url,'http://') === false){
							$url = 'http://'.$url;
						}
					}else{
						$url = rtrim ( C ( 'm_site_url' ), '/' ) . U ( 'Wap/ImgText/index', array (
								'token' => $this->token,
								'id' =>$imgtextdata ['id'],
								'type'=>"Diymen_saveinfo",
								'wecha_id' => $this->data ['FromUserName']
						) );
					}
					
					$return[]  = array (
							msubstr($imgtextdata ['title'],0,150,'utf-8',false),
							html_entity_decode( htmlspecialchars_decode($imgtextdata ['info'])),
							$imgtextdata ['pic'],
							$url,
					);
					return array (
							$return,
							'news'
					);
					break;
					/**自定义菜单多图文回复**/
				case 'Diymen_imgtexts':
					$imgtexts_db = M('Diymen_saveinfo');
						
					$imgtextswhere['token'] = $this->token;
					$imgtextswhere['id'] = $data["pid"];
					$imgtextswhere['pid'] = 0;
					$imgtextsdata = $imgtexts_db->field('id,pic,pid,skipUrl,title,originalUrl,detailedinfo')->where($imgtextswhere)->find();
						
					$sub_data = $imgtexts_db->field('id,pic,pid,skipUrl,title,originalUrl,detailedinfo')->where('pid='.$imgtextsdata['id'])->select();
					
					if(empty($imgtextsdata['detailedinfo']) && !empty($imgtextsdata['skipUrl'])){
						$url_f = $imgtextsdata['skipUrl'];
						if(stristr($url_f,'http://') === false){
							$url_f = 'http://'.$url_f;
						}
					}else{
						$url_f = rtrim ( C ( 'm_site_url' ), '/' ) . U ( 'Wap/ImgText/index', array (
								'token' => $this->token,
								'id' => $imgtextsdata['id'],
								'type'=>"Diymen_saveinfo",
								'wecha_id' => $this->data ['FromUserName']
						) );
					}
					$returnmul[]  = array (
							msubstr($imgtextsdata['title'],0,192,'utf-8',false),
							'',
							$imgtextsdata['pic'],
							$url_f,
					);
					foreach ($sub_data as $skey => $svalue) {
						if(empty($svalue['detailedinfo']) && !empty($svalue['skipUrl'])){
							$url_s = $svalue['skipUrl'];
							if(stristr($url_s,'http://') === false){
								$url_s = 'http://'.$url_s;
							}
						}else{
							$url_s = rtrim ( C ( 'm_site_url' ), '/' ) . U ( 'Wap/ImgText/index', array (
									'token' => $this->token,
									'id' => $svalue ['id'],
									'type'=>"Diymen_saveinfo",
									'wecha_id' => $this->data ['FromUserName']
							) );
						}
						$returnmul[] = array(msubstr($svalue['title'],0,192,'utf-8',false),'',$svalue['pic'],$url_s);
					}
					
					return array (
							$returnmul,
							'news'
					);
					break;
					/**自定义菜单图片回复**/
				case 'Diymen_picture':
					$pic_db=M('Diymen_saveinfo');
					$picwhere['token'] = $this->token;
					$picwhere['id'] = $data["pid"];
					$pictureArr = $pic_db->field("title,pic")->where($picwhere)->find();
					
					$return[]  = array (
							msubstr($pictureArr['title'],0,150,'utf-8',false),
							'',
							$pictureArr['pic'],
							'',
					);
					return array(
							$return,
							'news'
					);
					break;
					/**自定义菜单声音回复**/
				case 'Diymen_voice':
					$voice_db = M("Diymen_saveinfo");
					$voicewhere['token'] = $this->token;
					$voicewhere['id'] = $data["pid"];
					$voiceArr = $voice_db->field('id,title,voice,info')->where($voicewhere)->find();
									
					$return  = array (
							msubstr($voiceArr['title'],0,90,'utf-8',false),
							html_entity_decode( htmlspecialchars_decode($voiceArr['info'])),														
							'',
							$voiceArr['voice'],
					);	
									
					return array(
							$return,
							'music'
					);
					break;
					/***自定义菜单微信回复end***/
				case 'ServiceUser' : //客服配置
					return array (
						'亲,有什么问题要咨询?',
						'transfer_customer_service', 
					);
					break;
				case 'Img' :
					$this->requestdata ( 'imgnum' );
					$img_db = M ( $data ['module'] );
					$back = $img_db->field ( 'id,text,pic,url,title' )->limit ( 9 )->order ( 'id desc' )->where ( $like )->select ();
					$idsWhere = 'id in (';
					$comma = '';
					foreach ( $back as $keya => $infot ) {
						$idsWhere .= $comma . $infot ['id'];
						$comma = ',';
						if ($infot ['url'] != false) {
							if (! (strpos ( $infot ['url'], 'http' ) === FALSE)) {
								$url = $this->getFuncLink ( html_entity_decode ( $infot ['url'] ) );
							} else {
								$url = $this->getFuncLink ( $infot ['url'] );
							}
						} else {
								$url = rtrim ( C ( 'm_site_url' ), '/' ) . U ( 'Wap/Img/index', array (
									'token' => $this->token,
									'id' => $infot ['id'],
									'wecha_id' => $this->data ['FromUserName'] 
								) );
						}
						$return [] = array (
								$infot ['title'],
								$this->handleIntro ( $infot ['text'] ),
								$infot ['pic'],
								$url 
						);
					}
					$idsWhere .= ')';
					// if ($back) {
					// 	$img_db->where ( $idsWhere )->setInc ( 'click' );
					// }
					return array (
							$return,
							'news' 
					);
					break;
				case 'Host' :
					$this->requestdata ( 'other' );
					$host = M ( 'Host' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$host ['name'],
											$host ['info'],
											$host ['ppicurl'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Host&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									) 
							),
							'news' 
					);
					break;
				
				case 'Wifi' :
					$this->requestdata ( 'other' );
					$pro = M ( 'Wifi' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$pro ['title'],
											strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $pro ['info'] ) ) ),
											$pro ['picurl'],
											$pro ['url'] 
									) 
							),
							'news' 
					);
					break;
				
				case 'Jiudian' :
					$this->requestdata ( 'other' );
					$pro = M ( 'yuyue' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$pro ['title'],
											strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $pro ['info'] ) ) ),
											$pro ['topic'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Jiudian&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&id=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									) 
							),
							'news' 
					);
					break;
				
				case 'Yiliao' :
					$this->requestdata ( 'other' );
					$pro = M ( 'yuyue' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$pro ['title'],
											strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $pro ['info'] ) ) ),
											$pro ['topic'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Yiliao&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&id=' . $data ['pid'] 
									) 
							),
							'news' 
					);
					break;
				
				case 'Jiaoyu' :
					return $this->jiaoyu ( $data ['pid'] );
				case 'Hunqing' :
					return $this->hunqing ( $data ['pid'] );
				case 'Zhengwu' :
					return $this->zhengwu ( $data ['pid'] );
				case 'wuye' :
					return $this->wuye ( $data ['pid'] );
				case 'Meirong' :
					return $this->meirong ( $data ['pid'] );
				case 'Lvyou' :
					return $this->Lvyou ( $data ['pid'] );
				case 'Jianshen' :
					return $this->jianshen ( $data ['pid'] );
				case 'Ktv' :
					return $this->ktv ( $data ['pid'] );
				case 'Jiuba' :
					return $this->jiuba ( $data ['pid'] );
				case 'Zhuangxiu' :
					return $this->zhuangxiu ( $data ['pid'] );
				case 'Huadian' :
					return $this->huadian ( $data ['pid'] );
				
				case 'Estate' :
					$this->requestdata ( 'other' );
					$Estate = M ( 'Estate' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$Estate ['title'],
											$Estate ['estate_desc'],
											$Estate ['cover'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Estate&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
									),
									array (
											'楼盘介绍',
											$Estate ['estate_desc'],
											$Estate ['house_banner'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Estate&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									),
									array (
											'专家点评',
											$Estate ['estate_desc'],
											$Estate ['cover'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Estate&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									),
									array (
											'楼盘3D全景',
											$Estate ['estate_desc'],
											$Estate ['banner'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									),
									array (
											'楼盘动态',
											$Estate ['estate_desc'],
											$Estate ['house_banner'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									) 
							),
							'news' 
					);
					break;
				case 'Reservation' :
					$this->requestdata ( 'other' );
					$rt = M ( 'Reservation' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$rt ['title'],
											$rt ['info'],
											$rt ['picurl'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Reservation&a=index&rid=' . $data ['pid'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
									) 
							),
							'news' 
					);
					break;
				case 'Text' :
					$this->requestdata ( 'textnum' );
					$info = M ( $data ['module'] )->order ( 'id desc' )->find ( $data ['pid'] );
					if ($info) {
						M ( $data ['module'] )->where ( array (
								'id' => $data ['pid'] 
						) )->setInc ( 'click' );
					}
					return array (
							htmlspecialchars_decode ( str_replace ( '{wechat_id}', $this->data ['FromUserName'], $info ['text'] ) ),
							'text' 
					);
					break;
				case 'Product' :	
					//新微店: Jerry 2014-12-10
					$this->requestdata('other');
					$infos = M('Product')->limit(9)->order('id desc')->where($like)->select();
					if ($infos){
						$return=array();
						foreach ($infos as $info){
							if (!$info['groupon']){
								$url = C ( 'ZUANPU_DOMAIN' ) . U( 'Wap/Store/product', array( 'token'=>$this->token,'wecha_id'=>$this->data['FromUserName'], 'id' => $info['id'] ) );
							}else {
								$url = C ( 'ZUANPU_DOMAIN' ) . U( 'Wap/Groupon/product', array( 'token'=>$this->token,'wecha_id'=>$this->data['FromUserName'], 'id' => $info['id'] ) );
							}
							$return[]=array($info['name'],$this->handleIntro(strip_tags(htmlspecialchars_decode($info['intro']))),$info['logourl'],$url);
						}
					}
					return array (
							$return,
							'news' 
					);
					break;
				case 'Groupons' ://msubstr	
					//新微店: Jerry 2014-12-29
					$this->requestdata('other');
					$infos = M('Product')->limit(9)->order('id desc')->where($like)->select();
					if ($infos){
						$return=array();
						foreach ($infos as $info){
							if (!$info['groupon']){
								$url = C ( 'ZUANPU_DOMAIN' ) . U( 'Wap/Store/product', array( 'token'=>$this->token,'wecha_id'=>$this->data['FromUserName'], 'id' => $info['id'] ) );
							}else {
								$url = C ( 'ZUANPU_DOMAIN' ) . U( 'Wap/Groupon/product', array( 'token'=>$this->token,'wecha_id'=>$this->data['FromUserName'], 'id' => $info['id'] ) );
							}
							$return[]=array($info['name'],$this->handleIntro(strip_tags(htmlspecialchars_decode($info['intro']))),$info['logourl'],$url);
						}
					}
					return array (
							$return,
							'news' 
					);
					break;
				case 'Selfform' :
					$this->requestdata ( 'other' );
					$pro = M ( 'Selfform' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$pro ['name'],
											strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $pro ['intro'] ) ) ),
											$pro ['logourl'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Selfform&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&id=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									) 
							),
							'news' 
					);
					break;
				case 'Panorama' :
					$this->requestdata ( 'other' );
					$pro = M ( 'Panorama' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$pro ['name'],
											strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $pro ['intro'] ) ) ),
											$pro ['frontpic'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=item&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&id=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									) 
							),
							'news' 
					);
					break;
				case 'Wedding' :
					$this->requestdata ( 'other' );
					$wedding = M ( 'Wedding' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$wedding ['title'],
											strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $wedding ['word'] ) ) ),
											$wedding ['coverurl'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Wedding&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&id=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									),
									array (
											'查看我的祝福',
											strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $wedding ['word'] ) ) ),
											$wedding ['picurl'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Wedding&a=check&type=1&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&id=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									),
									array (
											'查看我的来宾',
											strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $wedding ['word'] ) ) ),
											$wedding ['picurl'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Wedding&a=check&type=2&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&id=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									) 
							),
							'news' 
					);
					break;
				case 'Vote' :
					$this->requestdata ( 'other' );
					$Vote = M ( 'Vote' )->where ( array (
							'id' => $data ['pid'] 
					) )->field('id,title,picurl,info,statdate,enddate,status')->order ( 'id DESC' )->find ();

					if(empty($Vote)){
						return array('输入错误,该活动不存在!',
						'text');
					}
					if(time() < $Vote['statdate']){
						return array('活动未开始,敬请期待!',
								'text');
					}
					if(time() > $Vote['enddate']){
						return array('您来晚了亲.活动已结束!',
								'text');
					}
					if($Vote['status'] == 0){
						return array('活动未开始,敬请期待!',
								'text');
					}
					$vote_desc = str_replace ( array('&hellip;','&mdash;','&lsquo;','&ldquo;','&rdquo;','&#039;','&rsquo;'), array('...','—','\'','"','"','’','‘'),strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $Vote ['info'] ) ) ));
					$vote_desc = msubstr($vote_desc,0,200);
					$p_url = rtrim ( C ( 'm_site_url' ), '/' ).U('/Wap/Vote/index',array('token'=>$this->token,'id'=>$Vote['id']));
					return array (
							array (
									array (
											$Vote ['title'],
											$vote_desc,
											$Vote ['picurl'],
											$p_url
									) 
							),
							'news' 
					);
					break;
				case 'Greeting_card' :
					$this->requestdata ( 'other' );
					$Vote = M ( 'Greeting_card' )->where ( array (
							'id' => $data ['pid'] 
					) )->order ( 'id DESC' )->find ();
					return array (
							array (
									array (
											$Vote ['title'],
											str_replace ( array (
													' ',
													'br /',
													'&',
													'gt;',
													'lt;' 
											), '', strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $Vote ['info'] ) ) ) ),
											$Vote ['picurl'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Greeting_card&a=index&id=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									) 
							),
							'news' 
					);
					break;
				
				case 'Heka' :
					$this->requestdata ( 'other' );
					$pro = M ( 'heka' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$pro ['title'],
											strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $pro ['sinfo'] ) ) ),
											$pro ['topic'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Heka&a=index&id=&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&id=' . $data ['pid'] 
									) 
							),
							'news' 
					);
					break;
				
				case 'Lottery' :
					$this->requestdata ( 'other' );
					$info = M ( 'Lottery' )->find ( $data ['pid'] );
					if ($info == false || $info ['status'] == 3) {
						return array (
								'活动可能已经结束或者被删除了',
								'text' 
						);
					}
					switch ($info ['type']) {
						case 1 :
							$model = 'Lottery';
							break;
						case 2 :
							$model = 'Guajiang';
							break;
						case 3 :
							$model = 'Coupon';
							break;
						case 4 :
							$model = 'LuckyFruit';
							break;
						case 5 :
							$model = 'GoldenEgg';
							break;
					}
					$id = $info ['id'];
					$type = $info ['type'];
					if ($info ['status'] == 1) {
						$picurl = $info ['starpicurl'];
						$title = $info ['title'];
						$id = $info ['id'];
						$info = $info ['info'];
					} else {
						$picurl = $info ['endpicurl'];
						$title = $info ['endtite'];
						$info = $info ['endinfo'];
					}
					$url = C ( 'site_url' ) . U ( 'Wap/' . $model . '/index', array (
							'token' => $this->token,
							'type' => $type,
							'wecha_id' => $this->data ['FromUserName'],
							'id' => $id,
							'type' => $type 
					) );
					return array (
							array (
									array (
											$title,
											$info,
											$picurl,
											$url 
									) 
							),
							'news' 
					);
				case 'Carowner' :
					$this->requestdata ( 'other' );
					$thisItem = M ( 'Carowner' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$thisItem ['title'],
											str_replace ( array (
													' ',
													'br /',
													'&',
													'gt;',
													'lt;' 
											), '', strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $thisItem ['info'] ) ) ) ),
											$thisItem ['head_url'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Car&a=owner&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&id=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
									) 
							),
							'news' 
					);
					break;
				case 'Carowner' :
					$this->requestdata ( 'other' );
					$thisItem = M ( 'Carowner' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$thisItem ['title'],
											str_replace ( array (
													' ',
													'br /',
													'&',
													'gt;',
													'lt;' 
											), '', strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $thisItem ['info'] ) ) ) ),
											$thisItem ['head_url'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Car&a=owner&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] 
									) 
							),
							'news' 
					);
					break;
				case 'Carset' :
					$this->requestdata ( 'other' );
					$thisItem = M ( 'Carset' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					$news = array ();
					array_push ( $news, array (
							$thisItem ['title'],
							'',
							$thisItem ['head_url'],
							$thisItem ['url'] ? $thisItem ['url'] : C ( 'site_url' ) . '/index.php?g=Wap&m=Car&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] 
					) );
					array_push ( $news, array (
							$thisItem ['title1'],
							'',
							$thisItem ['head_url_1'],
							$thisItem ['url1'] ? $thisItem ['url1'] : C ( 'site_url' ) . '/index.php?g=Wap&m=Car&a=brands&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] 
					) );
					array_push ( $news, array (
							$thisItem ['title2'],
							'',
							$thisItem ['head_url_2'],
							$thisItem ['url2'] ? $thisItem ['url2'] : C ( 'site_url' ) . '/index.php?g=Wap&m=Car&a=salers&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] 
					) );
					array_push ( $news, array (
							$thisItem ['title3'],
							'',
							$thisItem ['head_url_3'],
							$thisItem ['url3'] ? $thisItem ['url3'] : C ( 'site_url' ) . '/index.php?g=Wap&m=Car&a=CarReserveBook&addtype=drive&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] 
					) );
					array_push ( $news, array (
							$thisItem ['title4'],
							'',
							$thisItem ['head_url_4'],
							$thisItem ['url4'] ? $thisItem ['url4'] : C ( 'site_url' ) . '/index.php?g=Wap&m=Car&a=owner&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] 
					) );
					array_push ( $news, array (
							$thisItem ['title6'],
							'',
							$thisItem ['head_url_6'],
							$thisItem ['url6'] ? $thisItem ['url6'] : C ( 'site_url' ) . '/index.php?g=Wap&m=Car&a=showcar&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] 
					) );
					return array (
							$news,
							'news' 
					);
					break;
				case 'medicalSet' :
					$thisItem = M ( 'Medical_set' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$thisItem ['title'],
											str_replace ( array (
													' ',
													'br /',
													'&',
													'gt;',
													'lt;' 
											), '', strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $thisItem ['info'] ) ) ) ),
											$thisItem ['head_url'],
											C ( 'site_url' ) . '/index.php?g=Wap&m=Medical&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] 
									) 
							),
							'news' 
					);
					break;
				case 'Research' :
					$thisItem = M ( 'Research' )->where ( array (
							'id' => $data ['pid'] 
					) )->find ();
					return array (
							array (
									array (
											$thisItem ['title'],
											$thisItem ['description'],
											$thisItem ['logourl'],
											$this->siteUrl . U ( 'Wap/Research/index', array (
													'reid' => $data ['pid'],
													'token' => $this->token,
													'wecha_id' => $this->data ['FromUserName'] 
											) ) 
									) 
							),
							'news' 
					);
					break;
				case 'Weshow' : // zhangr - 20141022
					$pid = $data ['pid'];
					// ---zr -start
					$start = strtotime ( date ( 'Y-m-d' ) );
					
					$arr_data = array (
							'id' => $pid,
							'token' => $this->token,
							'status' => 1
					);
					
					$default_data = array (
							'id' => $pid,
							'token' => $this->token,
							'status' => 1
					);
					
					$default = M ( 'Weshow' )->where ( $default_data )->find ();
					
					if(empty($default)){
						$text  = '无此图文信息,请提醒商家，重新设定关键词';
					}else{
						$temp_t  = mktime();
						if($temp_t>$default['endTime']){
							$text  = '无此图文信息,请提醒商家，关键词已经过期';
						}else{
							$text  = '无此图文信息,请提醒商家，关键词还没有开启';
						}
					}
					
					$arr_data ['startTime'] = array (
							'ELT',
							$start
					);
					$arr_data ['endTime'] = array (
							'EGT',
							$start
					);
					
					$photo = M ( 'Weshow' )->where ( $arr_data )->find ();
					
					if($photo){
					//-------------
					$data ['title'] = $photo ['share_title'];
					$data ['keyword'] = $photo ['share_con'];
					$data ['url'] = rtrim ( C ( 'm_site_url' ), '/' ) . U ( 'Wap/Weshow/index', array (
							'token' => $this->token,
							'wecha_id' => $this->data ['FromUserName'],
							'pid' => $photo ['id'] 
					) );
					$data ['picurl'] = $photo ['picurl'] ? $photo ['picurl'] : rtrim ( C ( 'site_url' ), '/' ) . '/tpl/static/images/yj.jpg';
					return array (
							array (
									array (
											$data ['title'],
											$data ['keyword'],
											$data ['picurl'],
											$data ['url'] 
									) 
							),
							'news' 
					);
					//-------------
					}else{
				  		return array (
									$text,
									'text' 
							);
				  	}
					break;
				case 'Guajiang' : // zr - 20141104 - 刮奖
					
				   $pid = $data ['pid'];
				   $where = array (
						   'id' => $pid
				   );
				  $Lottery = M ( 'Lottery' )->where ( $where )->find ();
				  if($Lottery['joinnum']>0){
				  	$text  = $Lottery['endtite'];
				  }else{
				  	$text  = '该活动还没有开始,敬请期待！';
				  }
				  if($Lottery['status']==1){
				  	  //----------------------
				  	$data ['title'] = $Lottery ['title'];
				  	$data ['keyword'] = $Lottery ['info'];
				  	$data ['url'] = rtrim ( C ( 'site_url' ), '/' ) . U ( 'Wap/Guajiang/index', array (
				  			'token' => $this->token,
				  			'wecha_id' => $this->data ['FromUserName'],
				  			'id' => $Lottery ['id']
				  	) );
				  	$data ['picurl'] = $Lottery ['starpicurl'];
				  	return array (
				  			array (
				  					array (
				  							$data ['title'],
				  							$data ['keyword'],
				  							$data ['picurl'],
				  							$data ['url']
				  					)
				  			),
				  			'news'
				  	);
				  	//---------------------------
				  }else{
				  	return array (
				  			$text,
				  			'text'
				  	);
				  }

				   break;
				  case 'LuckyFruit' : // zr - 20141104 - 幸运水果机
				  		
				  	$pid = $data ['pid'];
				  	$where = array (
				  			'id' => $pid
				  	);
				  	$Lottery = M ( 'Lottery' )->where ( $where )->find ();
				  	
				  	if($Lottery['joinnum']>0){
				  		$text  = $Lottery['endtite'];
				  	}else{
				  		$text  = '该活动还没有开始,敬请期待！';
				  	}
				    
				  	if($Lottery['status']==1){
				  		//-------------------------
				  		$data ['title'] = $Lottery ['title'];
				  		$data ['keyword'] = $Lottery ['info'];
				  		$data ['url'] = rtrim ( C ( 'site_url' ), '/' ) . U ( 'Wap/LuckyFruit/index', array (
				  				'token' => $this->token,
				  				'wecha_id' => $this->data ['FromUserName'],
				  				'id' => $Lottery ['id']
				  		) );
				  		$data ['picurl'] = $Lottery ['starpicurl'];
				  		return array (
				  				array (
				  						array (
				  								$data ['title'],
				  								$data ['keyword'],
				  								$data ['picurl'],
				  								$data ['url']
				  						)
				  				),
				  				'news'
				  		);
				  		//-------------------------
				  	}else{
				  		return array (
									$text,
									'text' 
							);
				  	}

				  	break;
				  case 'Coupon' : // zr - 20141104 - 优惠券
				  	
				  		$pid = $data ['pid'];
				  		$where = array (
				  				'id' => $pid
				  		);
				  		$Lottery = M ( 'Lottery' )->where ( $where )->find ();
				  	    
				  		if($Lottery['joinnum']>0){
				  			$text  = $Lottery['endtite'];
				  		}else{
				  			$text  = '该活动还没有开始,敬请期待！';
				  		}
				  		
				  		if($Lottery['status']==1){
				  			//-------------------------
				  			$data ['title'] = $Lottery ['title'];
				  			$data ['keyword'] = $Lottery ['info'];
				  			$data ['url'] = rtrim ( C ( 'site_url' ), '/' ) . U ( 'Wap/Coupon/index', array (
				  					'token' => $this->token,
				  					'wecha_id' => $this->data ['FromUserName'],
				  					'id' => $Lottery ['id']
				  			) );
				  			$data ['picurl'] = $Lottery ['starpicurl'];
				  			return array (
				  					array (
				  							array (
				  									$data ['title'],
				  									$data ['keyword'],
				  									$data ['picurl'],
				  									$data ['url']
				  							)
				  					),
				  					'news'
				  			);
				  			//-------------------------
				  		}else{
				  			return array (
				  					$text,
				  					'text'
				  			);
				  		}
				  	
				  		break;
				  		case 'GoldenEgg' : // zr - 20141104 - 砸金蛋
				  				
				  			$pid = $data ['pid'];
				  			$where = array (
				  					'id' => $pid
				  			);
				  			$Lottery = M ( 'Lottery' )->where ( $where )->find ();
				  			
				  			if($Lottery['joinnum']>0){
				  				$text  = $Lottery['endtite'];
				  			}else{
				  				$text  = '该活动还没有开始,敬请期待！';
				  			}
				  			
				  			if($Lottery['status']==1){
				  				//-------------------------
				  				$data ['title'] = $Lottery ['title'];
				  				//$data ['keyword'] = html_entity_decode ($Lottery ['info']);
								$data ['keyword'] = str_replace("<br>", "\n", str_replace("&nbsp;", " ", $Lottery ['info']));
				  				$data ['url'] = rtrim ( C ( 'site_url' ), '/' ) . U ( 'Wap/GoldenEgg/index', array (
				  						'token' => $this->token,
				  						'wecha_id' => $this->data ['FromUserName'],
				  						'id' => $Lottery ['id']
				  				) );
				  				$data ['picurl'] = $Lottery ['starpicurl'];
				  				return array (
				  						array (
				  								array (
				  										$data ['title'],
				  										$data ['keyword'],
				  										$data ['picurl'],
				  										$data ['url']
				  								)
				  						),
				  						'news'
				  				);
				  				//-------------------------
				  			}else{
				  				return array (
				  						$text,
				  						'text'
				  				);
				  			}
				  				
				  			break;
				case 'Lottery' : // zr - 20141104 - 幸运大转盘
				  			
				  				$pid = $data ['pid'];
				  				$where = array (
				  						'id' => $pid
				  				);
				  				$Lottery = M ( 'Lottery' )->where ( $where )->find ();
				  				if($Lottery['joinnum']>0){
				  					$text  = $Lottery['endtite'];
				  				}else{
				  					$text  = '该活动还没有开始,敬请期待！';
				  				}
				  				if($Lottery['status']==1){
				  					//-------------------------
				  					$data ['title'] = $Lottery ['title'];
				  					$data ['keyword'] = $Lottery ['info'];
				  					$data ['url'] = rtrim ( C ( 'site_url' ), '/' ) . U ( 'Wap/Lottery/index', array (
				  							'token' => $this->token,
				  							'wecha_id' => $this->data ['FromUserName'],
				  							'id' => $Lottery ['id']
				  					) );
				  					$data ['picurl'] = $Lottery ['starpicurl'];
				  					return array (
				  							array (
				  									array (
				  											$data ['title'],
				  											$data ['keyword'],
				  											$data ['picurl'],
				  											$data ['url']
				  									)
				  							),
				  							'news'
				  					);
				  					//-------------------------
				  				}else{
				  					return array (
				  							$text,
				  							'text'
				  					);
				  				}
				  			
				  				break;
				case 'yunshow' : // zr - 20141211 - 场景魔方
			       $pid = $data ['pid'];
			    	
		           $tablename = "`ims_izc_lightbox_list`";
		           $wename    = "`ims_wechats`";
		           
		           $sql   = "select * from ".$tablename." where `id`=".$pid." limit 1";
		           $yunshow = M()->query($sql);
		           
			    	
		           if($yunshow[0]){
		           	    
		           	     $weid    = $yunshow[0]['weid'];
		           	     $wsql    = "select * from ".$wename." where `weid`=".$weid." limit 1";
		           	     $wechat  = M()->query($wsql);
		           	     if(isset($wechat[0]) && ($wechat[0]['token']==$this->token)){
		           	     	//-------------------------
		           	     	//------------------------- zr 20150107
		           	     	$data ['title'] = html_entity_decode ($yunshow[0]['reply_title']);
							$test_info   = html_entity_decode ($yunshow[0]['reply_description']);
							$test_info   = str_replace("&#039;","'",$test_info);
$data ['info'] =  <<<Eof
	{$test_info}
Eof;
		           	     	$data ['url'] = rtrim ( C ( 'site_url' ), '/' ) ."/show/mobile.php?act=module&id=".$yunshow[0]['id']."&name=mf&do=show&weid=".$yunshow[0]['weid']."&token=".$this->token."&wecha_id=".$this->data ['FromUserName'];
		       
							$import_url  = strstr($yunshow[0]['reply_thumb'],"http://");
		           	     	if($import_url!=''){
		           	     		$data ['picurl'] = $yunshow[0]['reply_thumb'];
		           	     	}else{
		           	     		if(stripos($yunshow[0]['reply_thumb'],'source')!== false){
			                          $data ['picurl'] = rtrim ( C ( 'm_site_url' ), '/' )."/show/".$yunshow[0]['reply_thumb'];
			                    }else{
			                          $data ['picurl'] = rtrim ( C ( 'm_site_url' ), '/' )."/show/resource/attachment/".$yunshow[0]['reply_thumb'];
			                    }
		           	     	}
		           	     	
		           	     	return array (
		           	     			array (
		           	     					array (
		           	     							$data ['title'],
		           	     							$data ['info'],
		           	     							$data ['picurl'],
		           	     							$data ['url']
		           	     					)
		           	     			),
		           	     			'news'
		           	     	);
		           	     }else{
		           	     	return array (
		           	     			'该微秀还没有开启，敬请期待！',
		           	     			'text'
		           	     	);
		           	     }
		           	//-------------------------
		           }else{
		           	  return array (
		           			'该微秀还没有开启，敬请期待！',
		           			'text'
		           	  );
		           }
				  	  break;
				case 'tbshow' : // zr - 20150612 - 新微秀
				   $host   = C('BTSHOW_SYSTEM_DB_HOST');
				   $name   = C('BTSHOW_SYSTEM_DB_NAME');
				   $user   = C('BTSHOW_SYSTEM_DB_USER');
				   $pwd    = C('BTSHOW_SYSTEM_DB_PWD');
				   $pre    = C('BTSHOW_SYSTEM_DB_PREFIX');
				   
				 
				   $pid = $data ['pid'];
				   
				   
				   
				   $conn   = mysql_connect($host,$user,$pwd);
				   
				    if (!$conn){
						return '';
					}
				   
				   mysql_select_db($name,$conn);
		
				   mysql_query ('set names UTF8');
				   
				   $sql_account        = "select * from `".$pre."publicaccount` where `id`=".$pid;
				   
				   $result_account     = mysql_query($sql_account);
         
				   $res_account        = mysql_fetch_assoc($result_account);
				   
				   if(!empty($res_account)){
					   //++++++++++++++++++++++++++
					   $sql        = "select * from `".$pre."scene` where `sceneid_bigint`=".$res_account['sceneid'];
				   
				   $result = mysql_query($sql);
         
				   $res = mysql_fetch_assoc($result);
	
				   mysql_close($conn);
				   
				   if(!empty($res)){
					   //-----------
					   $data ['title']  = $res['token_title'];
							$data ['info']   = $res['token_intro'];
		           	     	$data ['url']    = $this->showUrl.'/v-'.$res['scenecode_varchar'].'?token='.$res['userid_int'];
		                    $data ['picurl'] = $this->showUrl.'/Uploads/'.$res['token_thumb'];
		
		           	     	
		           	     	return array (
		           	     			array (
		           	     					array (
		           	     							$data ['title'],
		           	     							$data ['info'],
		           	     							$data ['picurl'],
		           	     							$data ['url']
		           	     					)
		           	     			),
		           	     			'news'
		           	     	);
					   //------------
					   
				   }
					   
					   //++++++++++++++++++++++++++
				   }
				  	  break; 	  
				case 'huashow' : // zr - 20141211 - 场景应用
			       $pid = $data ['pid'];
			    	
		           $tablename = "`ims_huabao`";
		           $wename    = "`ims_wechats`";
		           
		           $sql   = "select * from ".$tablename." where `id`=".$pid." limit 1";
		           $huashow = M()->query($sql);
			    	
		           if($huashow[0]){
		           	       $weid    = $huashow[0]['weid'];
		           	       $wsql    = "select * from ".$wename." where `weid`=".$weid." limit 1";
		           	       $wechat  = M()->query($wsql);
		           	   if(isset($wechat[0]) && ($wechat[0]['token']==$this->token)){
		           	       	//-------------------------
		           	       	$data ['title'] = $huashow[0]['title'];
							
							$test_info   = html_entity_decode ($huashow[0]['content']);
		           	       	$test_info   = str_replace("&#039;","'",$test_info);
$data ['info'] =  <<<Eof
	{$test_info}
Eof;
		           	       	$data ['url'] = rtrim ( C ( 'site_url' ), '/' ) ."/show/mobile.php?act=module&do=detail&weid=".$huashow[0]['weid']."&id=".$huashow[0]['id']."&name=Definition&token=".$this->token."&wecha_id=".$this->data ['FromUserName'];
		           	       	
							//---------- zr - 20150104
							$import_url  = strstr($huashow[0]['thumb'],"http://");
		           	     	if($import_url!=''){
		           	     		$data ['picurl'] = $huashow[0]['thumb'];
		           	     	}else{
		           	     		$data ['picurl'] = rtrim ( C ( 'm_site_url' ), '/' ) ."/show/resource/attachment/".$huashow[0]['thumb'];
		           	     	}
							
							//---------- zr - 20150104
		           	       	return array (
		           	       			array (
		           	       					array (
		           	       							$data ['title'],
		           	       							$data ['info'],
		           	       							$data ['picurl'],
		           	       							$data ['url']
		           	       					)
		           	       			),
		           	       			'news'
		           	       	);
		           	       	//-------------------------
		           	    }else{
		           	    	    return array (
		           	    			'该微秀还没有开启，敬请期待！',
		           	    			'text'
		           	    	   );
		           	       }
		           }else{
		           	  return array (
		           			'该微秀还没有开启，敬请期待！',
		           			'text'
		           	  );
		           }
				  	  break;
					case 'Problem' : //一站到底
						$p_where['id'] = $data['pid'];
						$problem = M('Problem_game')->where($p_where)->find();
						if(empty($problem)){
							return array('输入错误,该活动不存在!',
								'text');
						}
						if(time() < $problem['start_time']){
							return array('活动未开始,敬请期待!',
								'text');
						}
						if(time() > $problem['end_time']){
							return array('您来晚了亲.活动已结束!',
								'text');
						}
						$p_q_where['token'] = $this->token;
						$p_q_where['problem_id'] = $problem['id'];
						$p_question = M('Problem_question')->field('id')->where($p_q_where)->find();
						if(empty($p_question)){
							return array('活动未设置问题,请联系管理员.','text');
						}
						$p_url = rtrim ( C ( 'm_site_url' ), '/' ).U('/Wap/Problem/index',array('token'=>$this->token,'id'=>$problem['id']));
						$p_arr[] = array(
							$problem['title'],
							$problem['explain'],
							$problem['logo_pic'],
							$p_url
							);
						return array (
							$p_arr,
							'news' 
						);			
					break;
					case 'Custom' : //微预约
						$p_where['set_id'] = $data['pid'];
						$custom = M('Custom_set')->where($p_where)->find();
						if(empty($custom)){
							return array('输入错误,该活动不存在!',
								'text');
						}
						$p_url = rtrim ( C ( 'm_site_url' ), '/' ).U('/Wap/Custom/index',array('token'=>$this->token,'id'=>$data['pid']));
						$p_arr[] = array(
							$custom['title'],
							$custom['intro'],
							$custom['top_pic'],
							$p_url
							);
						return array (
							$p_arr,
							'news' 
						);			
					break;
				case 'Red_packet' :
							$info = M('Red_packet')->field('id,title,msg_pic,desc,info')->order('id desc')->where(array('id'=>$data['pid']))->find();
							$p_url = rtrim ( C ( 'm_site_url' ), '/' ).U('/Wap/Red_packet/index',array('token'=>$this->token,'id'=>$info['id'],'scope'=>'snsapi_userinfo'));
							$p_arr[] = array(
								$info['title'],
								$info['desc'],
								$info['msg_pic'],
								$p_url
							);
							return array (
								$p_arr,
								'news' 
							);	
					break;	
				case 'Reply_info' :
					//Jerry 2014-12-29 钻铺回复关键字自定义后的逻辑
					//1.获取回复表的配置信息
					$replyInfo = M('Reply_info') -> where( array( 'id' => $data['pid'], 'token' => $data['token'] ) ) -> find();
					if( !empty( $replyInfo ) ){
						//2.回复配置内容存在, 根据回复配置的类型，配置回复的数据
						$infoType = strtolower( trim( $replyInfo['infotype'] ) );
						switch ( $infoType ){
							//钻铺
							case 'shop':
								$url = C ( 'ZUANPU_DOMAIN' ) . U( 'Wap/Store/index', array( 'token'=>$this->token,'wecha_id'=>$this->data['FromUserName'] ) );
								if ( $replyInfo['apiurl'] ){
									$url = str_replace( '&amp;', '&', $replyInfo['apiurl'] );
								}
								break;
							//团购
							case 'groupon':
								$url = C ( 'ZUANPU_DOMAIN' ) . U( 'Wap/Groupon/grouponIndex', array( 'token'=>$this->token,'wecha_id'=>$this->data['FromUserName'] ) );
								if ( $replyInfo['apiurl'] ){
									$url = str_replace( '&amp;', '&',$replyInfo['apiurl'] );
								}
								break;
						}
						
						//3.回复数据组装
						return array (
							array (
								array (
									$replyInfo ['title'],
									strip_tags ( html_entity_decode ( htmlspecialchars_decode ( $replyInfo ['info'] ) ) ),
									$replyInfo ['picurl'],
									$url
								)
							),
								'news'
						);
						
						break;
					}				
				default :
					echo '';
			}
		} else {
			echo '';
		}
	}
	private function getFuncLink($u) {
		$urlInfos = explode ( ' ', $u );
		switch ($urlInfos [0]) {
			default :
				$url = str_replace ( array (
						'{wechat_id}',
						'{siteUrl}',
						'&amp;' 
				), array (
						$this->data ['FromUserName'],
						C ( 'site_url' ),
						'&' 
				), $urlInfos [0] );
				break;
			case '刮刮卡' :
				$Lottery = M ( 'Lottery' )->where ( array (
						'token' => $this->token,
						'type' => 2,
						'status' => 1 
				) )->order ( 'id DESC' )->find ();
				$url = C ( 'site_url' ) . U ( 'Wap/Guajiang/index', array (
						'token' => $this->token,
						'wecha_id' => $this->data ['FromUserName'],
						'id' => $Lottery ['id'] 
				) );
				break;
			case '大转盘' :
				$Lottery = M ( 'Lottery' )->where ( array (
						'token' => $this->token,
						'type' => 1,
						'status' => 1 
				) )->order ( 'id DESC' )->find ();
				$url = C ( 'site_url' ) . U ( 'Wap/Lottery/index', array (
						'token' => $this->token,
						'wecha_id' => $this->data ['FromUserName'],
						'id' => $Lottery ['id'] 
				) );
				break;
			case '商家订单' :
				$url = C ( 'site_url' ) . '/index.php?g=Wap&m=Host&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $urlInfos [1] . '&sgssz=mp.weixin.qq.com';
				break;
			case '优惠券' :
				$Lottery = M ( 'Lottery' )->where ( array (
						'token' => $this->token,
						'type' => 3,
						'status' => 1 
				) )->order ( 'id DESC' )->find ();
				$url = C ( 'site_url' ) . U ( 'Wap/Coupon/index', array (
						'token' => $this->token,
						'wecha_id' => $this->data ['FromUserName'],
						'id' => $Lottery ['id'] 
				) );
				break;
			case '水果机' :
				$Lottery = M ( 'Lottery' )->where ( array (
						'token' => $this->token,
						'type' => 4,
						'status' => 1 
				) )->order ( 'id DESC' )->find ();
				$url = C ( 'site_url' ) . U ( 'Wap/LuckyFruit/index', array (
						'token' => $this->token,
						'wecha_id' => $this->data ['FromUserName'],
						'id' => $Lottery ['id'] 
				) );
				break;
			case '万能表单' :
				$url = C ( 'site_url' ) . U ( 'Wap/Selfform/index', array (
						'token' => $this->token,
						'wecha_id' => $this->data ['FromUserName'],
						'id' => $urlInfos [1] 
				) );
				break;
			case '会员卡' :
				$url = C ( 'site_url' ) . U ( 'Wap/Card/vip', array (
						'token' => $this->token,
						'wecha_id' => $this->data ['FromUserName'] 
				) );
				break;
			case '首页' :
				$url = rtrim ( C ( 'site_url' ), '/' ) . '/index.php?g=Wap&m=Index&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'];
				break;
			case '团购' :
				//微店旧的url: Jerry 2014-12-10
			
				//新微店模块的url： Jerry 2014-12-10
				$url = rtrim ( C ( 'ZUANPU_DOMAIN' ), '/' ) . U( 'Wap/Groupon/grouponIndex', array( 'token'=>$this->token,'wecha_id'=>$this->data['FromUserName'] ) );
				break;
			case '商城' :
				//微店旧的url: Jerry 2014-12-10				
				//新微店模块的url: Jerry 2014-12-10
				$url = rtrim ( C ( 'ZUANPU_DOMAIN' ), '/' ) . U( 'Wap/Store/index', array( 'token'=>$this->token,'wecha_id'=>$this->data['FromUserName'] ) );
				break;
			case '订餐' :
				$url = rtrim ( C ( 'site_url' ), '/' ) . '/index.php?g=Wap&m=Product&a=dining&dining=1&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'];
				break;
			case '相册' :
				$url = rtrim ( C ( 'site_url' ), '/' ) . '/index.php?g=Wap&m=Photo&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'];
				break;
			case '网站分类' :
				$url = C ( 'site_url' ) . U ( 'Wap/Index/lists', array (
						'token' => $this->token,
						'wecha_id' => $this->data ['FromUserName'],
						'classid' => $urlInfos [1] 
				) );
				break;
			case 'LBS信息' :
				if ($urlInfos [1]) {
					$url = C ( 'site_url' ) . U ( 'Wap/Company/map', array (
							'token' => $this->token,
							'wecha_id' => $this->data ['FromUserName'],
							'companyid' => $urlInfos [1] 
					) );
				} else {
					$url = C ( 'site_url' ) . U ( 'Wap/Company/map', array (
							'token' => $this->token,
							'wecha_id' => $this->data ['FromUserName'] 
					) );
				}
				break;
			case 'DIY宣传页' :
				$url = C ( 'site_url' ) . '/index.php/show/' . $this->token;
				break;
			case '婚庆喜帖' :
				$url = C ( 'site_url' ) . U ( 'Wap/Wedding/index', array (
						'token' => $this->token,
						'wecha_id' => $this->data ['FromUserName'],
						'id' => $urlInfos [1] 
				) );
				break;
			case '投票' :
				$url = C ( 'm_site_url' ) . U ( 'Wap/Vote/index', array (
						'token' => $this->token,
						'id' => $urlInfos [1] 
				) );
				break;
		}
		return $url;
	}
	function jiaoyu($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Jiaoyu&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'培训机构介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Jiaoyu&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'学院印象',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Jiaoyu&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'教学相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'教育动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	function hunqing($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Hunqing&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'婚庆公司介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Hunqing&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'客户印象',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Hunqing&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'婚庆相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'婚庆动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	function zhengwu($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Zhengwu&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'政务部门介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Zhengwu&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'市民印象点评',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Zhengwu&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'政务相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'政务动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	function wuye($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Wuye&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'物业公司介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Wuye&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'业主印象',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Wuye&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'物业相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'物业动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	function meirong($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Meirong&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'美容机构介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Meirong&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'客人印象',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Meirong&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'美容相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'美容动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	function lvyou($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Lvyou&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'旅游区介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Lvyou&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'游客点评',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Lvyou&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'风景相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'旅游动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	function jianshen($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Jianshen&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'俱乐部介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Jianshen&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'专家点评',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Jianshen&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'健身相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'健身动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	function ktv($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Ktv&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'经营主体介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Ktv&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'客人印象',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Ktv&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'宣传相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'KTV动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	function jiuba($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Jiuba&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'经营主体介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Jiuba&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'会员印象',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Jiuba&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'酒吧相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'酒吧动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	function zhuangxiu($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Zhuangxiu&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'装修公司介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Zhuangxiu&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'客户印象',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Zhuangxiu&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'装修相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'装修动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	function huadian($pid) {
		$this->requestdata ( 'other' );
		$Estate = M ( 'Estate' )->where ( array (
				'id' => $pid 
		) )->find ();
		return array (
				array (
						array (
								$Estate ['title'],
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Huadian&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'经营主体介绍',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Huadian&a=index&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'客人印象',
								$Estate ['estate_desc'],
								$Estate ['cover'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Huadian&a=impress&&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'花店相册',
								$Estate ['estate_desc'],
								$Estate ['banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Panorama&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						),
						array (
								'花店动态',
								$Estate ['estate_desc'],
								$Estate ['house_banner'],
								C ( 'site_url' ) . '/index.php?g=Wap&m=Index&a=lists&classid=' . $Estate ['classify_id'] . '&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&hid=' . $data ['pid'] . '&sgssz=mp.weixin.qq.com' 
						) 
				),
				'news' 
		);
		break;
	}
	private function home() {
		return $this->shouye ();
	}
	private function other() {
		$this->writeLog ( $data ['Content'] );
		$other = M ( 'Other' )->where ( array (
				'token' => $this->token 
		) )->find ();
		if ($other == false) {
			return array (
					'',
					'' 
			);
		} else {
			if (empty ( $other ['keyword'] )) {
				return array (
						$other ['info'],
						'text' 
				);
			} else {
				$img = M ( 'Img' )->field ( 'id,text,pic,url,title' )->limit ( 5 )->order ( 'id desc' )->where ( array (
						'token' => $this->token,
						'keyword' => array (
								'like',
								'%' . $other ['keyword'] . '%' 
						) 
				) )->select ();
				if ($img == false) {
					return array (
							'无此图文信息,请提醒商家，重新设定关键词',
							'text' 
					);
				}
				foreach ( $img as $keya => $infot ) {
					if ($infot ['url'] != false) {
						if (! (strpos ( $infot ['url'], 'http' ) === FALSE)) {
							$url = $this->getFuncLink ( html_entity_decode ( $infot ['url'] ) );
						} else {
							$url = $this->getFuncLink ( $infot ['url'] );
						}
					} else {
						$url = rtrim ( C ( 'site_url' ), '/' ) . U ( 'Wap/Index/content', array (
								'token' => $this->token,
								'id' => $infot ['id'],
								'wecha_id' => $this->data ['FromUserName'] 
						) );
					}
					$return [] = array (
							$infot ['title'],
							$infot ['text'],
							$infot ['pic'],
							$url 
					);
				}
				return array (
						$return,
						'news' 
				);
			}
		}
	}
	private function shouye($name) {
		$home = M ( 'Home' )->where ( array (
				'token' => $this->token 
		) )->find ();
		$this->requestdata ( '3g' );
		// $this->behaviordata ( 'home', '', '1' );
		if ($home == false) {
			return array (
					'商家未做首页配置，请稍后再试',
					'text' 
			);
		} else {
			$imgurl = $home ['picurl'];
			if ($home ['apiurl'] == false) {
				if (! $home ['advancetpl']) {
					$url = rtrim ( C ( 'site_url' ), '/' ) . '/index.php?g=Wap&m=Index&a=index&token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com';
				} else {
					$url = rtrim ( C ( 'site_url' ), '/' ) . '/cms/index.php?token=' . $this->token . '&wecha_id=' . $this->data ['FromUserName'] . '&sgssz=mp.weixin.qq.com';
				}
			} else {
				$url = $home ['apiurl'];
			}
		}
		return array (
				array (
						array (
								$home ['title'],
								$home ['info'],
								$imgurl,
								$url 
						) 
				),
				'news' 
		);
	}
	private function kuaidi($data) {
		$data = array_merge ( $data );
		$str = file_get_contents ( 'http://www.weinxinma.com/api/index.php?m=Express&a=index&name=' . $data [1] . '&number=' . $data [0] );
		return $str;
	}
	private function langdu($data) {
		$data = implode ( '', $data );
		$mp3url = 'http://www.apiwx.com/aaa.php?w=' . urlencode ( $data );
		return array (
				array (
						$data,
						'点听收听',
						$mp3url,
						$mp3url 
				),
				'music' 
		);
	}
	private function jiankang($data) {
		if (empty ( $data ))
			return '主人，' . $this->my . "提醒您\n正确的查询方式是:\n健康+身高,+体重\n例如：健康170,65";
		$height = $data [1] / 100;
		$weight = $data [2];
		$Broca = ($height * 100 - 80) * 0.7;
		$kaluli = 66 + 13.7 * $weight + 5 * $height * 100 - 6.8 * 25;
		$chao = $weight - $Broca;
		$zhibiao = $chao * 0.1;
		$res = round ( $weight / ($height * $height), 1 );
		if ($res < 18.5) {
			$info = '您的体形属于骨感型，需要增加体重' . $chao . '公斤哦!';
			$pic = 1;
		} elseif ($res < 24) {
			$info = '您的体形属于圆滑型的身材，需要减少体重' . $chao . '公斤哦!';
		} elseif ($res > 24) {
			$info = '您的体形属于肥胖型，需要减少体重' . $chao . '公斤哦!';
		} elseif ($res > 28) {
			$info = '您的体形属于严重肥胖，请加强锻炼，或者使用我们推荐的减肥方案进行减肥';
		}
		return $info;
	}
	private function fujin($keyword) {
		$keyword = implode ( '', $keyword );
		if ($keyword == false) {
			return $this->my . "很难过,无法识别主人的指令,正确使用方法是:输入【附近+关键词】当" . $this->my . '提醒您输入地理位置的时候就OK啦';
		}
		$data = array ();
		$data ['time'] = time ();
		$data ['token'] = $this->_get ( 'token' );
		$data ['keyword'] = $keyword;
		$data ['uid'] = $this->data ['FromUserName'];
		$re = M ( 'Nearby_user' );
		$user = $re->where ( array (
				'token' => $this->_get ( 'token' ),
				'uid' => $data ['uid'] 
		) )->find ();
		if ($user == false) {
			$re->data ( $data )->add ();
		} else {
			$id ['id'] = $user ['id'];
			$re->where ( $id )->save ( $data );
		}
		return "主人【".$this->my."】已经接收到你的指令\n请发送您的地理位置(对话框右下角点击＋号，然后点击“位置”)给我哈";
	}
	private function recordLastRequest($key, $msgtype = 'text') {
		$rdata = array ();
		$rdata ['time'] = time ();
		$rdata ['token'] = $this->_get ( 'token' );
		$rdata ['keyword'] = $key;
		$rdata ['msgtype'] = $msgtype;
		$rdata ['uid'] = $this->data ['FromUserName'];
		$user_request_model = M ( 'User_request' );
		$user_request_row = $user_request_model->where ( array (
				'token' => $this->_get ( 'token' ),
				'msgtype' => $msgtype,
				'uid' => $rdata ['uid'] 
		) )->find ();
		if (! $user_request_row) {
			$user_request_model->add ( $rdata );
		} else {
			$rid ['id'] = $user_request_row ['id'];
			$user_request_model->where ( $rid )->save ( $rdata );
		}
	}
	function map($x,$y){
		if (C('baidu_map')){
			$transUrl='http://api.map.baidu.com/ag/coord/convert?from=2&to=4&x='.$x.'&y='.$y;
			$json=Http::fsockopenDownload($transUrl);
			if($json==false){
				$json=file_get_contents($transUrl);
			}
			$arr=json_decode($json,true);
			$x=base64_decode($arr['x']);
			$y=base64_decode($arr['y']);
		}else {
			$amap=new amap();
			$lact=$amap->coordinateConvert($y,$x,'gps');
			$x=$lact['latitude'];
			$y=$lact['longitude'];
		}
		$user_request_model=M('User_request');
		$urWhere=array('token'=>$this->_get('token'),'msgtype'=>'text','uid'=>$this->data['FromUserName']);
		$urWhere['time']=array('gt',time()-5*60);
		$user_request_row=$user_request_model->where($urWhere)->find();
		if(empty($user_request_row)){
			return array('','text');
		}
		if(!(strpos($user_request_row['keyword'], '附近') === FALSE)){
			$user=M('Nearby_user')->where(array('token'=>$this->_get('token'),'uid'=>$this->data['FromUserName']))->find();
			$keyword=$user['keyword'];
			$radius=2000;
			if (C('baidu_map')){
				$map=new baiduMap($keyword,$x,$y);
				$str=$map->echoJson();
				$array=json_decode($str);
				$map=array();
				foreach($array as $key=>$vo){
					$map[]=array($vo->title,$key,rtrim($this->siteUrl,'/').'/tpl/static/images/home.jpg',$vo->url);
				}
				if ($map){
					return array($map,'news');
				}else {
					$str=file_get_contents($this->siteUrl.'/map.php?keyword='.urlencode($keyword).'&x='.$x.'&y='.$y);
					$array=json_decode($str);
					$map=array();
					foreach($array as $key=>$vo){
						$map[]=array($vo->title,$key,rtrim($this->siteUrl,'/').'/tpl/static/images/home.jpg',$vo->url);
					}
					if ($map){
						return array($map,'news');
					}else{
						return array('附近信息无法调出，请稍候再试一下（关键词'.$keyword.',坐标：'.$x.'-'.$y.')','text');
					}
				}
			}else {
				$amamp=new amap();
				return $amamp->around($x,$y,$keyword,$radius);
			}
		}else{
			import ( "Home.Action.MapAction" );
			$mapAction = new MapAction ();
			// $mapAction=new Maps($this->token);
			if(!(strpos($user_request_row['keyword'], '开车去') === FALSE)||!(strpos($user_request_row['keyword'], '坐公交') === FALSE)||!(strpos($user_request_row['keyword'], '步行去') === FALSE)){
				if (!(strpos($user_request_row['keyword'], '步行去') === FALSE)){
					$companyid=str_replace('步行去','',$user_request_row['keyword']);
					if(!$companyid){
						$companyid=1;
					}
					return $mapAction->walk($x,$y,$companyid);
				}
				if (!(strpos($user_request_row['keyword'], '开车去') === FALSE)){
					$companyid=str_replace('开车去','',$user_request_row['keyword']);
					if(!$companyid){
						$companyid=1;
					}
					return $mapAction->drive($x,$y,$companyid);
				}
				if (!(strpos($user_request_row['keyword'], '坐公交') === FALSE)){
					$companyid=str_replace('坐公交','',$user_request_row['keyword']);
					if(!$companyid){
						$companyid=1;
					}
					return $mapAction->bus($x,$y,$companyid);
				}
			}else {
				switch ($user_request_row['keyword']){
					default:
						return $this->companyMap();
						break;
					case '最近的':
						return $mapAction->nearest($x,$y);
						break;
				}
			}
		}
	}
	private function suanming($name) {
		$name = implode ( '', $name );
		if (empty ( $name )) {
			return '主人' . $this->my . '提醒您正确的使用方法是[算命+姓名]';
		}
		$data = require_once (CONF_PATH . 'suanming.php');
		$num = mt_rand ( 0, 80 );
		return $name . "\n" . trim ( $data [$num] );
	}
	private function yinle($name) {
		$name = implode ( '', $name );
		$url = 'http://httop1.duapp.com/mp3.php?musicName=' . $name;
		$str = file_get_contents ( $url );
		$obj = json_decode ( $str );
		return array (
				array (
						$name,
						$name,
						$obj->url,
						$obj->url 
				),
				'music' 
		);
	}
	private function geci($n) {
		$name = implode ( '', $n );
		@$str = 'http://api.ajaxsns.com/api.php?key=free&appid=0&msg=' . urlencode ( '歌词' . $name );
		$json = json_decode ( file_get_contents ( $str ) );
		$str = str_replace ( '{br}', "\n", $json->content );
		return str_replace ( 'mzxing_com', '52jscn', $str );
	}
	private function yuming($n) {
		$name = implode ( '', $n );
		@$str = 'http://api.ajaxsns.com/api.php?key=free&appid=0&msg=' . urlencode ( '域名' . $name );
		$json = json_decode ( file_get_contents ( $str ) );
		$str = str_replace ( '{br}', "\n", $json->content );
		return str_replace ( 'mzxing_com', '52jscn', $str );
	}
	private function tianqi($n) {
		
		/*
		 * $name = implode('', $n); if ($name == '') { $name = '滨州'; }@$url = ('http://api.map.baidu.com/telematics/v3/weather?location=' . urlencode($name)) . '&output=json&ak=5slgyqGDENN7Sy7pw29IUvrZ'; $weatherJson = file_get_contents($url); $weather = json_decode($weatherJson, true); $re = $weather['error']; if ($re == 0) { $map = array(); $map1 = $weather['results']['0']['weather_data']; foreach ($map1 as $key => $vo) { $map[] = array((($vo['date'] . $vo['weather']) . $vo['wind']) . $vo['temperature'], '', $vo['dayPictureUrl'], ''); } $arr1 = array(($weather['date'] . $weather['results']['0']['currentCity']) . '最近天气预报', '', '', ''); array_unshift($map, $arr1); return array($map, 'news'); } else { $map = '亲,您确定您输入的天气是正确的么，输入正确的方式是：城市+天气 例如：济南天气'; return $map; }
		 */
		$name = implode ( '', $n );
		@$str = 'http://api.map.baidu.com/telematics/v3/weather?location=' . urlencode ( $name ) . '&output=json&ak=5slgyqGDENN7Sy7pw29IUvrZ';
		$json = json_decode ( file_get_contents ( $str ) );
		$str = $json->date . ' ' . $name . '天气' . "\n";
		$item = $json->results;
		$json = $item [0];
		$item = $json->weather_data;
		foreach ( $item as $key => $aa ) {
			$str = $str . $aa->date . ' ' . $aa->weather . ' ' . $aa->wind . ' ' . $aa->temperature . "\n";
		}
		return $str;
	}
	private function shouji($n) {
		$name = implode ( '', $n );
		@$str = 'http://api.ajaxsns.com/api.php?key=free&appid=0&msg=' . urlencode ( '归属' . $name );
		$json = json_decode ( file_get_contents ( $str ) );
		$str = str_replace ( '{br}', "\n", $json->content );
		return str_replace ( 'mzxing_com', '52jscn', $n );
	}
	private function shenfenzheng($n) {
		$n = implode ( '', $n );
		if (count ( $n ) > 1) {
			$this->error_msg ( $n );
			return false;
		}
		;
		$str1 = file_get_contents ( 'http://www.youdao.com/smartresult-xml/search.s?jsFlag=true&type=id&q=' . $n );
		$array = explode ( ':', $str1 );
		$array [2] = rtrim ( $array [4], ",'gender'" );
		$str = trim ( $array [3], ",'birthday'" );
		if ($str !== iconv ( 'UTF-8', 'UTF-8', iconv ( 'UTF-8', 'UTF-8', $str ) ))
			$str = iconv ( 'GBK', 'UTF-8', $str );
		$str = '【身份证】 ' . $n . "\n" . '【地址】' . $str . "\n 【该身份证主人的生日】" . str_replace ( "'", '', $array [2] );
		return $str;
	}
	private function gongjiao($data){

		$data=array_merge($data);
		if(count($data)<2){ $this->error_msg() ;return false;};
		if (trim($data[0]) == '' or trim($data[1]) == '') {
			return '公交车查询格式为：上海公交774';
                }
		$json=file_get_contents("http://www.twototwo.cn/bus/Service.aspx?format=json&action=QueryBusByLine&key=5da453b2-b154-4ef1-8f36-806ee58580f6&zone=".$data[0]."&line=".$data[1]);
		$data=json_decode($json);
		//线路名
		$xianlu=$data->Response->Head->XianLu;
		//验证查询是否正确
		$xdata=get_object_vars($xianlu->ShouMoBanShiJian);
		$xdata=$xdata['#cdata-section'];
		$piaojia=get_object_vars($xianlu->PiaoJia);
		$xdata=$xdata.' -- '.$piaojia['#cdata-section'];
		$main=$data->Response->Main->Item->FangXiang;
		//线路-路经
		$xianlu=$main[0]->ZhanDian;
		$str="【本公交途经】\n";
		for($i=0;$i<count($xianlu);$i++){
			$str.="\n".trim($xianlu[$i]->ZhanDianMingCheng);
		}
		return $str;
	}

	private function huoche($data, $time = '') {
		$data = array_merge ( $data );
		$data [2] = date ( 'Y', time () ) . $time;
		if (count ( $data ) != 3) {
			$this->error_msg ( $data [0] . '至' . $data [1] );
			return false;
		}
		;
		$time = empty ( $time ) ? date ( 'Y-m-d', time () ) : date ( 'Y-', time () ) . $time;
		$json = file_get_contents ( "http://www.twototwo.cn/train/Service.aspx?format=json&action=QueryTrainScheduleByTwoStation&key=5da453b2-b154-4ef1-8f36-806ee58580f6&startStation=" . $data [0] . "&arriveStation=" . $data [1] . "&startDate=" . $data [2] . "&ignoreStartDate=0&like=1&more=0" );
		if ($json) {
			$data = json_decode ( $json );
			$main = $data->Response->Main->Item;
			if (count ( $main ) > 10) {
				$conunt = 10;
			} else {
				$conunt = count ( $main );
			}
			for($i = 0; $i < $conunt; $i ++) {
				$str .= "\n 【编号】" . $main [$i]->CheCiMingCheng . "\n 【类型】" . $main [$i]->CheXingMingCheng . "\n【发车时间】:　" . $time . ' ' . $main [$i]->FaShi . "\n【耗时】" . $main [$i]->LiShi . ' 小时';
				$str .= "\n----------------------";
			}
		} else {
			$str = '没有找到 ' . $name . ' 至 ' . $toname . ' 的列车';
		}
		return $str;
	}
	private function fanyi($name) {
		$name = array_merge ( $name );
		$url = "http://openapi.baidu.com/public/2.0/bmt/translate?client_id=kylV2rmog90fKNbMTuVsL934&q=" . $name [0] . "&from=auto&to=auto";
		$json = Http::fsockopenDownload ( $url );
		if ($json == false) {
			$json = file_get_contents ( $url );
		}
		$json = json_decode ( $json );
		$str = $json->trans_result;
		if ($str [0]->dst == false)
			return $this->error_msg ( $name [0] );
		$mp3url = 'http://www.apiwx.com/aaa.php?w=' . $str [0]->dst;
		return array (
				array (
						$str [0]->src,
						$str [0]->dst,
						$mp3url,
						$mp3url 
				),
				'music' 
		);
	}
	private function caipiao($name) {
		$name = array_merge ( $name );
		$url = "http://api2.sinaapp.com/search/lottery/?appkey=0020130430&appsecert=fa6095e113cd28fd&reqtype=text&keyword=" . $name [0];
		$json = Http::fsockopenDownload ( $url );
		if ($json == false) {
			$json = file_get_contents ( $url );
		}
		$json = json_decode ( $json, true );
		$str = $json ['text'] ['content'];
		return $str;
	}
	private function mengjian($name) {
		$name = array_merge ( $name );
		if (empty ( $name ))
			return '周公睡着了,无法解此梦,这年头神仙也偷懒';
		$data = M ( 'Dream' )->field ( 'content' )->where ( "`title` LIKE '%" . $name [0] . "%'" )->find ();
		if (empty ( $data ))
			return '周公睡着了,无法解此梦,这年头神仙也偷懒';
		return $data ['content'];
	}
	function gupiao($name) {
		$url = "http://api2.sinaapp.com/search/stock/?appkey=0020130430&appsecert=fa6095e113cd28fd&reqtype=text&keyword=" . $name [1];
		$json = Http::fsockopenDownload ( $url );
		if ($json == false) {
			$json = file_get_contents ( $url );
		}
		$json = json_decode ( $json, true );
		$str = $json ['text'] ['content'];
		return $str;
	}
	function getmp3($data) {
		$obj = new getYu ();
		$ContentString = $obj->getGoogleTTS ( $data );
		$randfilestring = 'mp3/' . time () . '_' . sprintf ( '%02d', rand ( 0, 999 ) ) . ".mp3";
		return rtrim ( C ( 'site_url' ), '/' ) . $randfilestring;
	}
	function xiaohua() {
		$str = 'http://api.ajaxsns.com/api.php?key=free&appid=0&msg=' . urlencode ( '笑话' );
		$json = json_decode ( file_get_contents ( $str ) );
		$str = str_replace ( '{br}', "\n", $json->content );
		return str_replace ( 'mzxing_com', '52jscn', $str );
	}
	private function chat() {
		// $this->behaviordata ( 'chat', '' );
		$date = $this->data;
		$token = $this->token;
		$openID = $date ['FromUserName'];
		$wxuser = M ( 'Wechat_group_list' )->where ( $where = array (
				'token' => $token,
				'openid' => $openID
		) )->find ();
		if ($wxuser) {
			$wehcat = M ( 'wehcat_member_enddate' )->where ( array (
					'token' => $token,
					'openid' => $openID
			) )->find ();
			if ($wehcat) {
				if ($wehcat ['wchat_status'] == '0' || $wehcat ['wchat_status'] == null) {
					//帮助列表
					return   '';
				}
				if ($wehcat ['wchat_status'] == 1) {
					// 等待接入区
					$this->writeLog ( "**********************selectKf*****start" );
					$uid = $this->selectKf();
					$this->writeLog ( "**********************selectKf*****start".$uid );
					if($uid==0){
						// 有客服在线，无可以自动分配的客服人员 ，反馈等待序列的人数，以及等待时间预测。
						return  '您好，客服处于忙碌状态，请先描述您的问题，谢谢！';
					}
					if($uid==1){
						// 没有在线客服，反馈帮助列表
						return   "您好，客服现在不在线，请先描述您的问题，客服人员上线会及时解答，谢谢！";
					}
					if($uid==2){
						// 没有在线客服，反馈帮助列表
						return   "您好，客服处于忙碌状态，请先描述您的问题，谢谢！";
					}
					$susers = M ('service_user')->where ( array (
							'id' =>$uid,
					) )->find();
					$suconfig = M('suconfig')->where(array('suid'=>$uid))->find();
					if($suconfig==null||empty($suconfig)){
						$content = '我是'.$susers['name'].',欢迎来信';
					}else{
						if(empty($suconfig['auto_reply'])){
							$content = '我是'.$susers['name'].',欢迎来信';
						}else{
							$content = $suconfig['auto_reply'];
						}
					}
					$this->writeLog ( "**********************selectKf*****start.content".$content );
					return   $content;
				}
			}
		}else{
			$this->writeLog ( "*************数据接收开始########没有粉丝信息" );
		}
	}
	private function selectKf() {
		$date = $this->data;
		$token = $this->token;
		$susers = M ('service_user')->where ( array (
				'token' => $token,
				'su_status' => '1',
				'status'=>'0'
		))->select ();
		if ($susers) {
			foreach ( $susers as $key => $value ) {
				$counts = M ( 'wehcat_member_enddate' )->where ( array (
						'uid' => $value ['id'],
						'token' => $token,
						'wchat_status' => '2' 
				) )->count();
				$result [$key] ['uid'] = $value ['id'];
				$result [$key] ['uids'] = $counts;
			}
			$minNums = 5;
			foreach ( $result as $key => $value ) {
				if ($value ['uids'] < 5) {
					$minNums = $value ['uids'];
					$uid = $value ['uid'];
				}
			}
			// 记录
			if(empty($uid)){
				return 0;
			}
			M ('wehcat_member_enddate')->where ( array (
					'token' => $token,
					'openid' => $date ['FromUserName'] 
				))->save ( array (
					'uid' => $uid,
					'wchat_status' => '2' ,
					'joinUpDate'=>time()
			));
				$wechat_member_infos = M ( 'Wechat_member_info' )->where ($data )->order ('enddata desc' )->select ();
				if ($wechat_member_infos) {
					$wechat_member_info = $wechat_member_infos [0];
					M ('Wechat_member_info')->where ($wechat_member_info)->save ( array (
							'endjoindata' => time (),
							'uid'=>$uid
						));
				}
			return $uid;
		}else{
			$users = M ('service_user')->where ( array (
					'token' => $token,
					'su_status' => '2',
					'status'=>'0'
			))->select();
			if(empty($users)){
				return 2;
			}else{
				return 1;
			}
		}
	}
	private function xiaohuangji() {
		$where ['token'] = $this->token;
		$d = M ( 'wxuser' )->where ( $where )->order ( 'createtime desc' )->find ();
		$robot_status = $d ['robot_status'];
		$this->writeLog ( "robot_status=" . $robot_status . "token=" . $where1 ['token'] );
		if ($robot_status == "1") {
			// 访问机器人方法
			$content = $this->robotFun ( $this->data ['Content'] );
			return $content;
			/* list($content, $type) = array($content,'text'); */
		} else {
			return null;
		}
	}
	/*
	 * private function chat($name) { $function = M('Function')->where(array( 'funname' => 'liaotian' ))->find(); if (!$function['status']) { return ''; } $this->requestdata('textnum'); $check = $this->user('connectnum'); if ($check['connectnum'] != 1) { return C('connectout'); } if (!(strpos($name, '你是') === FALSE)) { return '咳咳，我是只能微信机器人'; } if ($name == "你叫什么" || $name == "你是谁") { return '咳咳，我是聪明与智慧并存的美女，主人你可以叫我' . $this->my . ',人家刚交男朋友,你不可追我啦'; } $str = 'http://api.ajaxsns.com/api.php?key=free&appid=0&msg=' . urlencode ($name); $json = Http::fsockopenDownload($str); if ($json == false) { $json = file_get_contents($str); } $json = json_decode($json, true); $str = str_replace('小薇', $this->my, str_replace('提示：', $this->my . '提醒您:', str_replace('{br}', "\n", $json['content']))); return $str; }
	 */
	private function fistMe($data) {
		if ('event' == $data ['MsgType'] && 'subscribe' == $data ['Event']) {
			return $this->help ();
		}
	}
	private function help() {
		// $this->behaviordata ( 'help', '', '1' );
		$data = M ( 'Areply' )->where ( array (
				'token' => $this->token 
		) )->find ();
		$this->writeLog ( $this->token );
		return array (
				preg_replace ( "/(\015\012)|(\015)|(\012)/", "\n", $data ['content'] ),
				'text' 
		);
	}
	private function error_msg($data) {
		return '没有找到' . $data . '相关的数据';
	}
	private function user($action, $keyword = '') {
		$user = M ( 'Wxuser' )->field ( 'uid' )->where ( array (
				'token' => $this->token 
		) )->find ();
		$usersdata = M ( 'Users' );
		$dataarray = array (
				'id' => $user ['uid'] 
		);
		$users = $usersdata->field ( 'gid,diynum,connectnum,activitynum,viptime' )->where ( array (
				'id' => $user ['uid'] 
		) )->find ();
		$group = M ( 'User_group' )->where ( array (
				'id' => $users ['gid'] 
		) )->find ();
		if ($users ['diynum'] < $group ['diynum']) {
			$data ['diynum'] = 1;
			if ($action == 'diynum') {
			}
		}
		if ($users ['connectnum'] < $group ['connectnum']) {
			$data ['connectnum'] = 1;
			if ($action == 'connectnum') {
				$usersdata->where ( $dataarray )->setInc ( 'connectnum' );
			}
		}
		if ($users ['viptime'] > time ()) {
			$data ['viptime'] = 1;
		}
		return $data;
	}
	private function requestdata($field) {
		$data ['year'] = date ( 'Y' );
		$data ['month'] = date ( 'm' );
		$data ['day'] = date ( 'd' );
		$data ['token'] = $this->token;
		$mysql = M ( 'Requestdata' );
		$check = $mysql->field ( 'id' )->where ( $data )->find ();
		if ($check == false) {
			$data ['time'] = time ();
			$data [$field] = 1;
			$mysql->add ( $data );
		} else {
			$mysql->where ( $data )->setInc ( $field );
		}
	}
	private function behaviordata($field = '', $id = '', $type = '') {
		$data ['token'] = $this->token;
		$data ['openid'] = $this->data ['FromUserName'];
		if(empty($field)){
			$data['model'] = $this->data['MsgType'];
		}else{
			$data ['model'] = $field;
		}
		switch ($this->data['MsgType']) {
				case 'image':
					$data ['keyword'] = $this->data ['PicUrl'];
					break;
				case 'event':
					$data ['keyword'] = $this->data ['Event'].'#'.$this->data['EventKey'];
					break;
				case 'voice':
					$data['keyword'] = $this->data['MediaId'];
					break;
				case 'video':
					$data['keyword'] = $this->data['media_id'];
					break;
				case 'shortvideo':
					$data['keyword'] = $this->data['media_id'];
					break;
				case 'location':
					$data['keyword'] = $this->data['Location_X'].'#'.$this->data['Location_Y'];
					break;
				case 'link':
					$data['keyword'] = $this->data['Url'];
					break;
				default:
					$data ['keyword'] = $this->data ['Content'];
					break;
		}		
		/*if (! $data ['keyword']) {
			$data ['keyword'] = '用户关注';
		}*/

		$mysql = M ( 'Behavior' );
		$check = $mysql->field ( 'id' )->where ( $data )->find ();
		$this->updateMemberEndTime ( $data ['openid'] );
		if ($check == false) {
			$data ['date'] = time () ;
			if ($id != false) {
				$data ['fid'] = $id;
			}
			if ($type != false) {
				$data ['type'] = 1;
			}
			$data['num'] = 1;
			$data ['enddate'] = time ();
			$mysql->add ( $data );
		} else {
			$mysql->where ( $data )->setInc ( 'num' );
			$mysql->where ( $data )->save (array('enddate'=>time()));
		}
	}
	private function updateMemberEndTime($openid) {
		$mysql = M ( 'Wehcat_member_enddate' );
		$id = $mysql->field ( 'id' )->where ( array (
				'openid' => $openid 
		) )->find ();
		$data ['enddate'] = time ();
		$data ['openid'] = $openid;
		$data ['token'] = $this->token;
		if ($id == false) {
			$mysql->add ( $data );
		} else {
			$data ['id'] = $id ['id'];
			$mysql->save ( $data );
		}
	}
	private function baike($name) {
		$name = implode ( '', $name );
		$name_gbk = iconv ( 'utf-8', 'gbk', $name );
		$encode = urlencode ( $name_gbk );
		$url = 'http://baike.baidu.com/list-php/dispose/searchword.php?word=' . $encode . '&pic=1';
		$get_contents = $this->httpGetRequest_baike ( $url );
		$get_contents_gbk = iconv ( 'gbk', 'utf-8', $get_contents );
		preg_match ( "/URL=(\S+)'>/s", $get_contents_gbk, $out );
		$real_link = 'http://baike.baidu.com' . $out [1];
		$get_contents2 = $this->httpGetRequest_baike ( $real_link );
		preg_match ( '#"Description"\scontent="(.+?)"\s\/\>#is', $get_contents2, $matchresult );
		if (isset ( $matchresult [1] ) && $matchresult [1] != "") {
			return htmlspecialchars_decode ( $matchresult [1] );
		} else {
			return "抱歉，没有找到与“" . $name . "”相关的百科结果。";
		}
	}
	private function getRecognition($scend_id) {
		$GetDb = D ( 'Recognition' );
		$re_where['scene_id'] = $scend_id;
		$re_where['token']  = $this->token;
 		$re_where['status'] = 0;
		//$re_where['code_url'] = $ticket;
		$data = $GetDb->field ('keyword,id')->where ( $re_where )->find ();
		if (!empty($data)) {
			$GetDb->where ( array ('id' => $data['id'] ) )->setInc ('attention_num');
			return $data ['keyword'];
		} else {
			return false;
		}
	}
	private function api_notice_increment($url, $data) {
		$ch = curl_init ();
		$header = "Accept-Charset: utf-8";
		if (strpos ( $url, '?' )) {
			$url .= '&token=' . $this->token;
		} else {
			$url .= '?token=' . $this->token;
		}
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
		curl_setopt ( $curl, CURLOPT_HTTPHEADER, $header );
		curl_setopt ( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible;MSIE 5.01;Windows NT 5.0)' );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		$tmpInfo = curl_exec ( $ch );
		if (curl_errno ( $ch )) {
			return false;
		} else {
			return $tmpInfo;
		}
	}
	private function httpGetRequest_baike($url) {
		$headers = array (
				"User-Agent: Mozilla/5.0 (Windows NT 5.1;rv:14.0) Gecko/20100101 Firefox/14.0.1",
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
				"Accept-Language: en-us,en;q=0.5",
				"Referer: http://www.baidu.com/" 
		);
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
		$output = curl_exec ( $ch );
		curl_close ( $ch );
		if ($output === FALSE) {
			return "cURL Error: " . curl_error ( $ch );
		}
		return $output;
	}
	/**
	 * 更新或获取粉丝的信息
	 * */
	private function addUserInfo($appid_new,$secret) {
		$access_token = $this->_getAccessToken ($appid_new);
		$url2 = 'https://api.weixin.qq.com/cgi-bin/user/info?openid=' . $this->data ['FromUserName'] . '&access_token=' . $access_token;
		$classData = json_decode ( $this->curlGet ( $url2 ) );

		file_put_contents('Wxuserinfo.php', "<?php \nreturn " . var_export($classData, true) . ";", FILE_APPEND);

		if ($classData->subscribe == 1) {
			$data ['nickname'] = str_replace ( "'", '', $classData->nickname );
			$data ['sex'] = $classData->sex;
			$data ['city'] = $classData->city;
			$data ['province'] = $classData->province;
			$data ['headimgurl'] = $classData->headimgurl;
			$data ['subscribe_time'] = $classData->subscribe_time;
			$data ['openid'] = $this->data ['FromUserName'];
			$url3 = 'https://api.weixin.qq.com/cgi-bin/groups/getid?access_token=' . $access_token;
			$json = json_decode ( $this->curlGet ( $url3, 'post', '{"openid":"' . $data ['openid'] . '"}' ) );
			$data ['g_id'] = $json->groupid;
			$data ['token'] = $this->token;
			$user = M ( 'wechat_group_list' )->where ( array (
					'openid' => $data ['openid'],
					'token' => $data ['token'] 
			) )->find ();
			if ($user) {
				M ( 'wechat_group_list' )->where ( array (
						'openid' => $data ['openid'],
						'token' => $data ['token'] 
				) )->save ( $data );
			} else {
				M ( 'wechat_group_list' )->data ( $data )->add ();
			}
			$fp = fopen ( 'user.txt', 'w' );
			$content = var_export ( $data, TRUE );
			fwrite ( $fp, $content );
			fclose ( $fp );
		}
	}
	/**
	 * 删除粉丝信息
	 */
	private function delUserInfo() {
		$data ['token'] = $this->token;
		$data ['openid'] = $this->data ['FromUserName'];
		M('wechat_group_list' )->where ($data )->delete();
	}
	private function _getAccessToken($appid) {
		$where = array (
				'token' => $this->token 
		);
		$this->thisWxUser = M ( 'Wxuser' )->where ( $where )->find ();
		if(empty($this->thisWxUser['appsecret'])){ //使用微信开放平台绑定公众号号没有appsecret
			$bind = A('User/Bind');
			$tmp_token = $bind->return_token($this->thisWxUser['appid']);
			return $tmp_token['authorizer_access_token'];
		}else{
			$url_get = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->thisWxUser ['appid'] . '&secret=' . $this->thisWxUser ['appsecret'];
			$json = json_decode ( $this->curlGet ( $url_get ) );
			if (! $json->errmsg) {
			} else {
				$fp = fopen ( 'wrong.txt', 'w' );
				fwrite ( $fp, $json->errmsg );
				fwrite ( $fp, '\n' );
				fwrite ( $fp, $this->token );
				fwrite ( $fp, '\n' );
				fwrite ( $fp, time () );
				fclose ( $fp );
				$this->error ( '获取access_token发生错误：错误代码' . $json->errcode . ',微信返回错误信息：' . $json->errmsg );
			}
			return $json->access_token;
		}
	}
	private function curlGet($url, $method = 'get', $data = '') {
		$ch = curl_init ();
		$header = "Accept-Charset: utf-8";
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, strtoupper ( $method ) );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
		curl_setopt ( $curl, CURLOPT_HTTPHEADER, $header );
		curl_setopt ( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible;MSIE 5.01;Windows NT 5.0)' );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		$temp = curl_exec ( $ch );
		return $temp;
	}
	private function get_tags($title, $num = 10) {
		vendor ( 'Pscws.Pscws4', '', '.class.php' );
		$pscws = new PSCWS4 ();
		$pscws->set_dict ( CONF_PATH . 'etc/dict.utf8.xdb' );
		$pscws->set_rule ( CONF_PATH . 'etc/rules.utf8.ini' );
		$pscws->set_ignore ( true );
		$pscws->send_text ( $title );
		$words = $pscws->get_tops ( $num );
		$pscws->close ();
		$tags = array ();
		foreach ( $words as $val ) {
			$tags [] = $val ['word'];
		}
		return implode ( ',', $tags );
	}
	public function handleIntro($str) {
		$search = array (
				'"' 
		);
		$replace = array (
				'"' 
		);
		return str_replace ( $search, $replace, $str );
	}
	//关注自动回复 和 无匹配自动回复
	private function areply_return($key = 'Yes'){
		$autoare = D('Areply')->get_areply(array('token'=>$this->token,'keyword'=>$key),'home,keyword,content');
		if(empty($autoare)){
			return false;
		}
		$txt_content = html_entity_decode( htmlspecialchars_decode($autoare['content']) );
		$txt_content = str_replace(array('<p>','</p>'), '', $txt_content);
		switch ($autoare['home']) {
			case 2: //单图文
				$img_db = D('Img');
			    $imgwhere['token'] = $this->token;
			   	$imgwhere['flag'] = 1;
				$imgwhere['is_mul'] = 1;
				$imgwhere['keyword'] =$key;
				$imgdata = $img_db->find_info($imgwhere,'id,text,pic,url,title,author,redirecturl,info,createtime');
				if(empty($imgdata['info']) && !empty($imgdata['redirecturl'])){
					$url = $imgdata['redirecturl'];
					if(stristr($url,'http://') === false){
						$url = 'http://'.$url;
					}
				}else{
		 			$url = rtrim ( C ( 'm_site_url' ), '/' ) . U ( 'Wap/Img/index', array (
									'token' => $this->token,
									'id' => $imgdata ['id'],
									'wecha_id' => $this->data ['FromUserName'] 
							) );
					}
				$return[]  = array (
						msubstr($imgdata ['title'],0,36,'utf-8',false),
						html_entity_decode( htmlspecialchars_decode($imgdata ['text']) ),
						$imgdata ['pic'],
						$url,
				);
				return array (
					$return,
					'news' 
				);						
				break;
			case 3: //多图文
				$img_db = D('Img');
			    $mul_img = D('Send_message_extra_info');
			    $mulwhere['token'] = $this->token;
			   	$mulwhere['flag'] = 1;
				$mulwhere['is_mul'] = 2;
				$mulwhere['keyword'] = $key;
				$muldata = $img_db->find_info($mulwhere,'id,pic,url,title,author,redirecturl,info');
				$sub_data = $mul_img->get_all(array('send_message_id'=>$muldata['id'],'tmp_flag' => 1),'id,title,author,mediasrc,pic,info,redirecturl','`order` asc');
				if(empty($muldata['info']) && !empty($muldata['redirecturl'])){
					$url_f = $muldata['redirecturl'];
					if(stristr($url_f,'http://') === false){
						$url_f = 'http://'.$url_f;
					}
				}else{
					$url_f = rtrim ( C ( 'm_site_url' ), '/' ) . U ( 'Wap/Img/index', array (
									'token' => $this->token,
									'id' => $muldata ['id'],
									'wecha_id' => $this->data ['FromUserName'] 
							) );
				}
				$returnmul[]  = array (
					msubstr($muldata['title'],0,30,'utf-8',false),
					'',
					$muldata['pic'],
					$url_f,
				);
				foreach ($sub_data as $skey => $svalue) {
					if(empty($svalue['info']) && !empty($svalue['redirecturl'])){
						$url_s = $svalue['redirecturl'];
						if(stristr($url_s,'http://') === false){
							$url_s = 'http://'.$url_s;
						}
					}else{
						$url_s = rtrim ( C ( 'm_site_url' ), '/' ) . U ( 'Wap/Img/index', array (
						'token' => $this->token,
						'mulid' => $svalue ['id'],
						'wecha_id' => $this->data ['FromUserName']
						) );
					}
						$returnmul[] = array(msubstr($svalue['title'],0,24,'utf-8',false),'',$svalue['pic'],$url_s);
				}

				return array (
					$returnmul,
					'news' 
				);							
				break;				
			default:
				return array (
					trim($txt_content),
					'text' 
				);
			break;
		}
	}
	
	/*
	 * 功能：年假活动关注事件处理
	 * 作者：Jerry
	 * 日期：2015-01-23 
	 */
	private function activitySubscribeEvent(){
		$resultArray = array();

		//1.检测活动是否过期
		$activityData = $this -> getNjActivityInfo();
		$endTime = $activityData['end_time'];
		$startTime = $activityData['start_time'];
		$currTime = time();
		if( $currTime <= $endTime ){
			if( $currTime >= $startTime ){
				//2.查看该关注者是否已贡献过时间
				$devoteUserEntity = M( 'Activity_devote_user' );
				$visitorCheckWhere = array(
						'token' => $activityData['token'],
						'aid' => $activityData['id'],
						'openid' => $this->data ['FromUserName'],
						'subscribe_status' => 0
				);
				
				//回访关注
				$visitorCreatorEntity = M( 'Activity_visitor_creator_relation' );
				$vistorCreatorData = $visitorCreatorEntity -> where( array( 'token'=>$activityData['token'], 'aid'=>$activityData['id'], 'openid'=>$this->data ['FromUserName'] ) )->find();
				if( !empty( $vistorCreatorData ) ){
					$visitorCheckWhere['uid'] = $vistorCreatorData['uid'];
				}
				
				$devoteUser = $devoteUserEntity->where( $visitorCheckWhere )->find();
				if( !empty( $devoteUser ) ){
					//老用户重新关注, 取第一次关注时的贡献时间
					$devoteTime = $devoteUser['devote_minute'];
				}else{
					//新用户关注, 随机分配贡献时间
					$devoteTime = rand( 1, 10 );
				}

				//3.组织通过年假活动关注回复内容
				//如此下的链接方法，处理当前访客和增年假的逻辑
				$showNjListUrl = C('m_site_url') . U( '/Wap/Activity/njSubscribeEventProc', array( 'token'=>$activityData['token'], 'aid'=>$activityData['id'], 'openid'=>$this->data ['FromUserName'], 'devoteTime'=>$devoteTime ) );
				// $replayContent = "恭喜您关注成功！！！！！  您将为您的朋友增加年假{$devoteTime}分钟，请点击<a href='$showNjListUrl'>查看详情</a>，完成添加活动。提示：需点击查看详情，所增加时间才会作数。活动期间取消关注，时间也会消失哦。";
				$replayContent = "感谢您关注网库互通。若您正在参与攒年假活动，请点击<a href='$showNjListUrl'>查看详情</a>查看您为您朋友、亲人增加的年假时间（您需要点击查看详情，所增加时间才会作数）。若您是网库员工，请回复\"年假\"参与攒年假活动。";
				$replayContent = trim( html_entity_decode( htmlspecialchars_decode( $replayContent ) ) );
				$resultArray = array (
					$replayContent,
					'text'
				);

			// }else{
			// 	//活动未开始
			// 	$replayContent = "恭喜您关注成功！！！！！ 攒年假活动开始时间：".date( 'Y-m-d H:i:s', $startTime );
			}

		}
		
		return $resultArray;
	}
	//处理微信的扫码带参数二维码,关注公众号回复内容
	public function scan_areplay($scene_id){
		$r_where['token'] = $this->token;
		$r_where['scene_id'] = $scene_id;
		$r_where['status'] = 0;
		$m_flag = M('Recognition')->where($r_where)->getField('module');
		switch ($m_flag) {
			case 'bvshow':
				return $this->btshow($scene_id);
				break;
			default:
				return '';
				break;
		}
	}	
	//年假活动取消关注者的响应事件处理函数 Jerry 2015-01-26
	private function activityUnsubscribeEvent( $data ){
		//1.检测活动是否有效：存在该活动且该活动已开始且未结束
		$activityData = $this -> getNjActivityInfo();
		$endTime = $activityData['end_time'];
		$startTime = $activityData['start_time'];
		$currTime = time();
		if( !empty( $activityData ) && $currTime >= $startTime && $currTime <= $endTime ){
			//1.查看该用户是否参与过年假活动且贡献过时间
			$devoteUserEntity = M( 'Activity_devote_user' );
			$creatorEntity = M( 'Activity_creator' );
			
			$devoteWhere = array(
					'token' => $activityData['token'],
					'aid' => $activityData['id'],
					'openid' => $this->data ['FromUserName'],
					'subscribe_status' => 1
			);
			
			$devoteUser = $devoteUserEntity -> where( $devoteWhere ) -> select();
			if( !empty( $devoteUser ) ){
				foreach( $devoteUser as $key => $value ){
					//2.查看活动的创建是否存在
					$creatorWhere = array(
							'aid' => $value['aid'],
							'token' => $value['token'],
							'wecha_id' => $value['uid']
					);
					$creatorData = $creatorEntity -> where( $creatorWhere ) -> field( array( 'id', 'total_minute', 'total_follower' ) ) -> find();
					if( !empty( $creatorData ) ){
						//更新已获得的年假总时间和贡献总人数
						$updateData = array(
								'id' => $creatorData['id'],
								'total_minute' => $creatorData['total_minute'] - $value['devote_minute'],
								'total_follower' => $creatorData['total_follower'] - 1
						);
						$creatorEntity -> save( $updateData );
					
						//3.更改活动参与者关注状态
						$devoteUpdate = array(
								'id' => $value['id'],
								'subscribe_status' => 0
						);
						$devoteUserEntity -> save( $devoteUpdate );
					}
				}
			}
		}
	}
	
	//获取活动信息 Jerry 2015-01-23
	private function getNjActivityInfo(){
		//判断活动是否过期
		$where = array(
			'token' => $this->token,
			'id' => 1
		);
		$activityData = M( 'Activity_publisher' )->where( $where )-> find();
		$activityData['end_time'] = strtotime( $activityData['end_date'] . ' 23:59:59' );
		$activityData['start_time'] = strtotime( $activityData['start_date'] . ' 00:00:00' );
		return $activityData;
	}
}
?>