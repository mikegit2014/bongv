<?php
/**
*
*会员支付
**/
class SweeppayAction extends BaseAction{
	private $mch_id;
	private $appid;
	private $key;
	private $productid;
	private $timestamp;
	private $noncestr;
	private $qr_url;
	private $session_u_name;
	public function _initialize(){
		parent::_initialize();
		$this->get_pay_config();
		//处理用户名为空时，使用不为空的手机号或者邮箱
		$_uid = session('uid');
		$u_info_msg = M('Users')->where(array('id'=>intval($_uid)))->field('username,mp,email')->find();
		if(empty($u_info_msg['username'])){
			if(empty($u_info_msg['mp']) && !empty($u_info_msg['email'])){
				$this->session_u_name = $u_info_msg['email'];
			}else{
				$this->session_u_name = $u_info_msg['mp'];
			}
		}else{
			$this->session_u_name = $u_info_msg['username'];
		}
	}
	//会员充值页面
	public function index(){
		$uu_id = session('uid');
		if(empty($uu_id) || intval($uu_id) == 0){
			$url_tt = C('site_url').U('Home/Index/login');
			echo"<script type=\"text/javascript\">top.location.href=\"$url_tt\"</script>";
			exit;
		}
		$_uid = session('uid');
		$sql_data = M()->query('select ug.id,ug.name,u.viptime,u.createtime,u.last_auth from `tp_users` as u inner join `tp_user_group` as ug on u.gid = ug.id and u.id='.intval($_uid));
		$ugid = $sql_data[0]['id'];
		$uviptime = $sql_data[0]['viptime'];
		$upower = $sql_data[0]['name'];
		$u_where['status'] = 1;
		$u_where['name'] = array('neq','基础');
		$u_where['price'] = array('gt',0);
		$g_arr = M('User_group')->field('id,name,price,isdiscount,open_duration')->order('price asc')->where($u_where)->select();

		/*
		 * 单品通用户可以选择的版本 start
		 */
		$userInfo = M("Users")->where("id=".session('uid'))->find();
		if($userInfo['platform']==1){			
			$g_arr = M('User_group')->field('id,name,price,isdiscount,open_duration')->order('id asc')->where("(id = 6 or id=7) and status=1 and price>0")->select();			
		}
		
		/* 
		 * 单品通用户可以选择的版本end
		 */
		foreach ($g_arr as $key => $value) {
			// if($value['id'] == intval($ugid)){
			// 	$upower = $value['name'];
			// }
			$p_price[$value['id']] = $value['price'];
			$open_time[$value['id']] = unserialize($value['open_duration']);
			$g_p_name[$value['id']] = $value['name'];
		}
		arsort($p_price);
		$tmp = $p_price;
		$first = array_shift($tmp);
		$first_key = array_search($first,$p_price);
		$_data_arr = unserialize($sql_data[0]['last_auth']);
		foreach ( $_data_arr as $kd => $vd ) {
			// array_key_exists($vd['gid'], $g_p_name)  && 
			if($kd == 0 ){
				$temp_start_time = $uviptime;				
			}else{
				$temp_start_time = $temp_end_time;
			}
			$temp_end_time  = $temp_start_time + $vd['day_space'];
			
			$_str_show .= "<p>系统将自动为您在<span>".date('Y-m-d',$temp_start_time).'</span>切换回<span>'.$g_p_name[$vd['gid']].'</span>，到期时间为<span>'.date('Y-m-d',$temp_end_time)."</span></p><br/>";
		}
		$this->assign("platform",$userInfo['platform']);
		$this->assign('last_power',$_str_show);
		$this->assign('user',$this->session_u_name);
		if($ugid != 3){
			$div_expire = '<div class="recharge_D">到期时间：'.date('Y-m-d',$uviptime).'</div>';
			$this->assign('expire',$div_expire);
		}
		$this->assign('power',$upower);
		$this->assign('list',$g_arr);
		$this->assign('now_key',$first_key);
		$this->assign('now_open',$open_time[$first_key]);
		$this->assign('qr_url',$this->qr_url);

		$ccity = $this->get_province(array('upid'=>$res['province'] == 0 ? 1 : $res['province'],'level'=>2));	
		$this->assign('province',$this->get_province());
		$this->assign('city',$ccity);
		$this->assign('country',$this->get_province(array('upid'=>$res['city'] == 0 ? $ccity[0]['id'] : $res['city'],'level'=>3)));
		$this->display();
	}
	//日志页面
	public function log(){

		$uu_id = session('uid');
		if(empty($uu_id) || intval($uu_id) == 0){
			$url_tt = C('site_url').U('Home/Index/login');
			echo"<script type=\"text/javascript\">top.location.href=\"$url_tt\"</script>";
			exit;
		}

		$pay_status = $this->_get('status','trim,intval');
		if($pay_status == 1){
			$u_uid = session('uid');
			$u_arr = M('Users')->field('id,gid,viptime')->where(array('id'=>intval($u_uid)))->find();
			if(!empty($u_arr)){
				$ug_arr = M('User_group')->field('id,name')->where(array('id'=>$u_arr['gid']))->find();

				$tt = $u_arr + $ug_arr + $_SESSION;
				file_put_contents(RUNTIME_PATH.'membership_payment_cache/pay_tmp_info_success.php', "<?php \nreturn " . stripslashes(var_export($tt, true)) . ";", FILE_APPEND);
				session('gid',$ug_arr['id']);
				session('viptime',$u_arr['viptime']);
				session('gname',$ug_arr['name']);
			}
			
			$url_reback = C('site_url').U('/User/Index/index');
			echo"<script type=\"text/javascript\">top.location.href=\"$url_reback\"</script>";
			die();
		}

		$where_start1 = $this->_get('start_time','trim');
		$where_end1 = $this->_get('end_time','trim');

		$sp_where['pay'] = 1;
		$sp_where['uid'] = session('uid');

		if(empty($where_start1) && !empty($where_end1) && isset($where_end1) && isset($where_start1)){
			$this->error('起始时间不能为空!');
		}
		if(!empty($where_start1) && empty($where_end1) && isset($where_end1) && isset($where_start1)){
			$this->error('结束时间不能为空!');
		}

		if(!empty($where_start1) && !empty($where_end1) && isset($where_end1) && isset($where_start1)){
			$rule ='/^(([1-2][0-9]{3}-)((([1-9])|(0[1-9])|(1[0-2]))-)((([1-9])|(0[1-9])|([1-2][0-9])|(3[0-1]))))( ((([0-9])|(([0-1][0-9])|(2[0-3]))):(([0-9])|([0-5][0-9]))(:(([0-9])|([0-5][0-9])))?))?$/';  
        	preg_match($rule,$where_start1,$s_result);  
        	if(empty($s_result)){
 				$this->error('开始时间格式不正确!');       		
        	}
        	preg_match($rule,$where_end1,$e_result);  
			if(empty($e_result)){
 				$this->error('结束时间格式不正确!');       		
        	}        	

			if($where_start1 > $where_end1){
				$this->error('起始时间不能大于结束时间');
			}
			
			$where_start = strtotime($where_start1.' 00:00:00');
			$where_end = strtotime($where_end1.' 23:59:59');
			$sp_where['pay_time'] = array('BETWEEN',array($where_start,$where_end));
		} 

		$sp_where['uid'] = session('uid');
		$count	= M('Sweep_pay')->where($sp_where)->count();
		$Page   = new Page($count,10);

		$log_pay = M('Sweep_pay')->where($sp_where)->field('id,uname,total_fee,pay_time,exp_time,app_name,open_type,need_receipt,receipt_type,receipt_header,receipt_province,receipt_city,receipt_country,receipt_address,receipt_status')->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->select();

		/*$ccity = $this->get_province(array('upid'=>$res['province'] == 0 ? 1 : $res['province'],'level'=>2));	
		$this->assign('province',$this->get_province());
		$this->assign('city',$ccity);
		$this->assign('country',$this->get_province(array('upid'=>$res['city'] == 0 ? $ccity[0]['id'] : $res['city'],'level'=>3)));*/

		foreach ($log_pay as $k_l => $v_l) {
			if($v_l['receipt_status'] == 2){
				$pro_str_dis = "<select disabled name='province' class='Fwd_adsPs' onchange=show_city(this.value,'2',".$v_l['id'].",".$v_l['receipt_status'].");>";
				$pp_str_dis = $this->get_province();
				foreach ($pp_str_dis as $k_p_d => $v_p_d) {
					if($v_p_d['id'] == $v_l['receipt_province'])
						$pro_str_dis .= "<option value=".$v_p_d['id']." selected>".$v_p_d['name']."</option>";
					else
						$pro_str_dis .= "<option value=".$v_p_d['id'].">".$v_p_d['name']."</option>";
				}
				$pro_str_dis .= "</select>";				
			}else{
				$pro_str_ = "<select name='province' class='Fwd_adsPs' onchange=show_city(this.value,'2',".$v_l['id'].",".$v_l['receipt_status'].");>";
				$pp_str = $this->get_province();
				foreach ($pp_str as $k_p => $v_p) {
					if($v_p['id'] == $v_l['receipt_province'])
						$pro_str_ .= "<option value=".$v_p['id']." selected>".$v_p['name']."</option>";
					else
						$pro_str_ .= "<option value=".$v_p['id'].">".$v_p['name']."</option>";
				}
				$pro_str_ .= "</select>";
			}
			if($v_l['receipt_status'] == 2){
				$city_str_dis = "<select disabled name='city' class='Fwd_adsCs' id='city' onchange=show_city(this.value,'3',".$v_l['id'].",".$v_l['receipt_status'].")>";
				$cc_in_dis['upid'] = $v_l['receipt_province'];
				$cc_in_dis['level'] = 2;
				$cc_str_dis = $this->get_province($cc_in_dis);
				foreach ($cc_str_dis as $c_k_d => $c_p_d) {
					if($c_p_d['id'] == $v_l['receipt_city'])
						$city_str_dis .= "<option value=".$c_p_d['id']." selected>".$c_p_d['name']."</option>";
					else
						$city_str_dis .= "<option value=".$c_p_d['id'].">".$c_p_d['name']."</option>";
				}
				$city_str_dis .= "</select>";			
			}else{
				$city_str_ = "<select name='city' class='Fwd_adsCs' id='city' onchange=show_city(this.value,'3',".$v_l['id'].",".$v_l['receipt_status'].")>";
				$cc_in['upid'] = $v_l['receipt_province'];
				$cc_in['level'] = 2;
				$cc_str = $this->get_province($cc_in);
				foreach ($cc_str as $c_k => $c_p) {
					if($c_p['id'] == $v_l['receipt_city'])
						$city_str_ .= "<option value=".$c_p['id']." selected>".$c_p['name']."</option>";
					else
						$city_str_ .= "<option value=".$c_p['id'].">".$c_p['name']."</option>";
				}
				$city_str_ .= "</select>";
			}

			if($v_l['receipt_status'] == 2){
				$country_str_dis = "<select  name='country' disabled class='Fwd_adsAs' id='country'>";
				$cc_c_in_dis['upid'] = $v_l['receipt_city'];
				$cc_c_in_dis['level'] = 3;
				$cc_str_ount_dis = $this->get_province($cc_c_in_dis);
				foreach ($cc_str_ount_dis as $c_k_c_d => $c_p_c_d) {
					if($c_p_c_d['id'] == $v_l['receipt_country'])
						$country_str_dis .= "<option value=".$c_p_c_d['id']." selected>".$c_p_c_d['name']."</option>";
					else
						$country_str_dis .= "<option value=".$c_p_c_d['id'].">".$c_p_c_d['name']."</option>";
				}
				$country_str_dis .= "</select>";
			}else{
				$country_str_ = "<select name='country' class='Fwd_adsAs' id='country'>";
				$cc_c_in['upid'] = $v_l['receipt_city'];
				$cc_c_in['level'] = 3;
				$cc_str_ount = $this->get_province($cc_c_in);
				foreach ($cc_str_ount as $c_k_c => $c_p_c) {
					if($c_p_c['id'] == $v_l['receipt_country'])
						$country_str_ .= "<option value=".$c_p_c['id']." selected>".$c_p_c['name']."</option>";
					else
						$country_str_ .= "<option value=".$c_p_c['id'].">".$c_p_c['name']."</option>";
				}
				$country_str_ .= "</select>";
			}

		 	$log_pay[$k_l]['province'] = $pro_str_;
		 	$log_pay[$k_l]['city'] = $city_str_;
		 	$log_pay[$k_l]['country'] = $country_str_; 

		 	$log_pay[$k_l]['province_dis'] = $pro_str_dis;
		 	$log_pay[$k_l]['city_dis'] = $city_str_dis;
		 	$log_pay[$k_l]['country_dis'] = $country_str_dis; 
		}
		// echo $pro_str_.$city_str_.$country_str_;
		// $this->assign('province',$pro_str_);
		// $this->assign('city',$city_str_);
		// $this->assign('country',$country_str_);		

		// $this->assign('province_dis',$pro_str_dis);
		// $this->assign('city_dis',$city_str_dis);
		// $this->assign('country_dis',$country_str_dis);		

		$this->assign('w_type',$where_type);
		$this->assign('w_start',$where_start1);
		$this->assign('w_end',$where_end1);

		// dump($log_pay);
		$this->assign('list_log',$log_pay);
		$this->assign('page',$Page->show());		
		$this->display();
	}
	//版本之间对比
	public function compare(){
		/*
		$g_where['status'] = 1;
		$g_where['name'] = array('neq','基础');
		$upower = M('User_group')->where($g_where)->field('id,name,price,wechat_card_num,per_ids')->select();
		$all_function = M()
		            ->table('`tp_module_class` as mc')
		            ->where("mc.status = 1 and fc.status = 1 and fc.gid = 1")
		            ->join('inner join `tp_function` as fc on mc.id = fc.isserve')
		            ->field('mc.id as mid,mc.name as mname,fc.id as fid,fc.name as fname')->order('mc.sort asc,fc.id asc')->select();
		foreach ($all_function as $k_a => $v_a) {
			$f_info[$v_a['mid']][] = $v_a;
		}
		// print_r($upower);
		$this->assign('function',$f_info);
		$this->assign('power_info',$upower);
		*/
		$this->display();
	}
	public function get_open_data(){
		$gx_id = $this->_get('lxid','intval,trim');
		$open_data = M('User_group')->field('id,open_duration')->where(array('id'=>$gx_id))->find(); 
		if(empty($open_data)){
			echo json_encode(array('error'=>-1,'msg'=>'数据有误!'));
			exit;
		}else{
			$arr_open = unserialize($open_data['open_duration']);
			echo json_encode(array('error'=>1,'msg'=>$arr_open));
			exit;
		}
	}

	////////使用模式二,先调用统一下单的接口，根据返回的code_url,生成二维码
	public function mode_To_create_order(){
		$uu_id = session('uid');
		if(empty($uu_id) || intval($uu_id) == 0){
			if(IS_AJAX){
				echo json_encode(array('msg'=>-1,'qr_url'=>''));
				die();
			}else{
				$url_tt = C('site_url').U('Home/Index/login');
				echo"<script type=\"text/javascript\">top.location.href=\"$url_tt\"</script>";
				exit;
			}
		}
		$arr_data = explode('#', $_POST['arr']);
		$total_m = explode('-', $arr_data[0]);
		$sur_m = explode('-',$arr_data[1]);
		//查询数据库,不能使用前端传过来的数据
		$data_group = M('User_group')->field('id,name,price,open_duration')->where(array('id'=>intval($total_m[0])))->find();
		if(empty($data_group)){
			if(IS_AJAX){
				echo json_encode(array('msg'=>'参数有误','qr_url'=>''));
				die();
			}else{
				$this->error('参数有误!');
				exit;
			}
		}else{
			$t_money = $sur_m[1] * $data_group['price'];
			$v_money = 0;
			$vip_arr = unserialize($data_group['open_duration']);
			foreach ($vip_arr as $k_v => $v_v) {
				if($v_v['open_time'] == $sur_m[1]){
					$v_money = $v_v['vip_price'];
				}
			}
			$f_money =  intval($t_money) - (intval($t_money) - intval($v_money));
			if($f_money <= 0 ){
				if(IS_AJAX){
					echo json_encode(array('msg'=>'参数有误','qr_url'=>''));
					die();
				}else{
					$this->error('参数有误!');
					exit;
				}
			}
			$total_m[0] = $data_group['id'];
			$total_m[1] = $data_group['name'];
			$total_m[2] = $data_group['price'];
			$sur_m[2] = $v_money;		 
		}

		// $f_money = 0.01;
		switch ($arr_data[3]) {
			case 1: //微信
				$paras = $this->create_wx_qrcode($total_m,$sur_m,$f_money,$file_name);
				if(is_array($paras)){
					$paras['mch_key'] = $this->key;
					$paras['pay'] = 0; 
					// $paras['qrcode_url'] = C('site_url').'/'.$file_name;
					$paras['qrcode_url'] = $paras['code_url'];
					$paras['code_type'] = $arr_data[3];//微信扫描支付
					$paras['createtime'] = $this->timestamp;
					$paras['pay_time'] = $this->timestamp;
					$paras['exp_time'] = $this->timestamp;

					$paras['total_fee'] = $f_money;
					$paras['uid'] = session('uid');
					$paras['uname'] = $this->session_u_name;
					$paras['app_type'] = $total_m[0];
					$paras['app_name'] = $total_m[1]; 
					$paras['open_type'] = $sur_m[1];
					$paras['need_receipt'] = $arr_data[4];
					if($arr_data[4] == 1 && !empty($arr_data[5])){
						$n_info = explode('-', $arr_data[5]);
						$paras['receipt_type'] = $n_info[0] == 1 ? 1 : 2;
						$paras['receipt_header'] = $n_info[1];
						$paras['receipt_province'] = $n_info[2];
						$paras['receipt_city'] = $n_info[3];
						$paras['receipt_country'] = $n_info[4];
						$paras['receipt_address'] = $n_info[5];
					}else{
						$paras['receipt_type'] = '';
						$paras['receipt_header'] = '';
						$paras['receipt_province'] = '';
						$paras['receipt_city'] = '';
						$paras['receipt_country'] = '';
						$paras['receipt_address'] = '';				
					}			
				}
				$results = M("Sweep_pay")->add($paras);
				if($results >=1 ){
					// $this->qr_url = C('site_url')."/uploads/payqrcode/".$paras['product_id'].".png";
					$this->qr_url = $paras['code_url'];
					echo json_encode(array('qr_url'=>$this->qr_url,'sid'=>$results));
					exit;
				}
				break;
			case 2://支付宝二维码支付
				$this->alipay_qrcode_pay();
				break;
			case 3://支付宝
				$this->alipay_puter_pay($total_m,$sur_m,$arr_data,$f_money);
				break;
			case 4://财付通
				$this->tenpay_puter_pay($total_m,$sur_m,$arr_data,$f_money);
				break;
			case 5://百度钱包
				$this->baidupay_puter_pay($total_m,$sur_m,$arr_data,$f_money);
				break;															
			default:
				die('错误请求!');
				break;		
		}
	}
	private function create_wx_qrcode($total_m,$sur_m,$f_money,$file_name){

		$pro_id = mt_rand(1,1000000).$this->timestamp;
		$file_name = "uploads/payqrcode/".$pro_id.".png";	
			
		$paras['appid'] = $this->appid;
		$paras['mch_id'] = $this->mch_id;
		$paras['nonce_str'] = $this->create_noncestr(30);
		$paras['body'] = $total_m[1].' '.$f_money.'/元 '.$sur_m[1].'个月';
		$paras['out_trade_no'] = mt_rand(1,10000).time().mt_rand(1,1000);
		$paras['total_fee'] = $f_money*100;
		$paras['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];
		$paras['notify_url'] = C('site_url').'/User/Sweeppay/get_wx_notifyPay';
		$paras['trade_type'] = 'NATIVE';
		$paras['product_id'] = $pro_id;

		$format_str = $this->formatBizQueryParaMap($paras,false)."&key=".$this->key;
		$result_ = strtoupper(md5($format_str));
		$paras["sign"] = $result_;//签名

		$str_xml = $this->arrayToXml($paras);

		$result_xml = $this->postXmlCurl($str_xml,'https://api.mch.weixin.qq.com/pay/unifiedorder',60);

		$result_arr = $this->xmlToArray($result_xml);

		file_put_contents(RUNTIME_PATH.'membership_payment_cache/pay_tmp_info.php', "<?php \nreturn " . stripslashes(var_export($result_arr, true)) . ";", FILE_APPEND);

		if(array_key_exists('return_code', $result_arr) && $result_arr['return_code']=='SUCCESS'){
			// $fName = QRcode::png($result_arr['code_url'],$file_name,"L","4");
			// if(file_exists($file_name) && getimagesize($file_name)){
			// 	$paras['prepay_id'] = $result_arr['prepay_id'];
			// 	$paras['code_url'] = $result_arr['code_url'];
			// 	return $paras;
			// }else{
			// 	die('生成二维码有误!');
			// }
			$paras['prepay_id'] = $result_arr['prepay_id'];
			$paras['code_url'] = $this->url_create_qrcodeimg($result_arr['code_url']);
			return $paras;			
		}
	}
	//调用系统的生成二维码的接口
	private function url_create_qrcodeimg($qr_url){
		$code_url = C('qr_site_url')."?g=Api&m=qrcode&a=qr";
		$code_arr['type'] = 'http';
		$code_arr['t'] = json_encode(array('http'=>$qr_url));
		$res_json = curlPost($code_url,$code_arr);
		$res_data = json_decode($res_json,true);
		if($res_data['error_code'] != '200'){
			die('二维码生成错误!');
		}else{
			return $res_data['qr_url'];
		}
	}	
	////////使用模式二///////

	//支付宝二维码支付
	public function alipay_qrcode_pay(){
		die('暂时不支持支付宝扫码支付');
	}
	//支付宝电脑支付
	public function alipay_puter_pay($total_m,$sur_m,$arr_data,$f_money){
		// $f_money = 0.01;
        //支付类型
        $payment_type = "1";
        //必填，不能修改
        //服务器异步通知页面路径
        $notify_url = C('site_url').'/User/Sweeppay/alipay_notify_pay';
        //需http://格式的完整路径，不能加?id=123这类自定义参数

        //页面跳转同步通知页面路径
        $return_url = C('site_url').'/User/Sweeppay/alipay_return_pay';
        //需http://格式的完整路径，不能加?id=123这类自定义参数，不能写成http://localhost/
        //卖家支付宝帐户
        $seller_email = C('alipay_seller_email');
        //必填

        //商户订单号
        $out_trade_no = mt_rand(1,100000).time().mt_rand(0,10000);
        //商户网站订单系统中唯一订单号，必填

        //订单名称
        $subject = $total_m[1].' '.$f_money.'/元 '.$sur_m[1].'个月';
        //必填

        //付款金额
        $total_fee = $f_money;
        //必填

        //订单描述

        $body = $total_m[1].' '.$f_money.'/元 '.$sur_m[1].'个月';
        //商品展示地址
        $show_url = C('site_url').'/User/Sweeppay/index';
        //需以http://开头的完整路径，例如：http://www.商户网址.com/myorder.html

        //防钓鱼时间戳
        $anti_phishing_key = "";
        //若要使用请调用类文件submit中的query_timestamp函数

        //客户端的IP地址
        $exter_invoke_ip = getip();
        //非局域网的外网IP地址，如：221.0.0.1

		//构造要请求的参数数组，无需改动
		$parameter = array(
			"service" => "create_direct_pay_by_user",
			"partner" => trim(C('alipay_partner')),
			"payment_type"	=> $payment_type,
			"notify_url"	=> $notify_url,
			"return_url"	=> $return_url,
			"seller_email"	=> $seller_email,
			"out_trade_no"	=> $out_trade_no,
			"subject"	=> $subject,
			"total_fee"	=> $total_fee,
			"body"	=> $body,
			'seller_id' => trim(C('alipay_partner')),
			"show_url"	=> $show_url,
			// "anti_phishing_key"	=> $anti_phishing_key,
			// "exter_invoke_ip"	=> $exter_invoke_ip,
			"_input_charset"	=> trim(strtolower(C('alipay_input_charset')))
		);

		$alipay_config['partner'] = C('alipay_partner');
		$alipay_config['key'] = C('alipay_key_new');
		$alipay_config['sign_type'] = C('alipay_sign_type');
		$alipay_config['input_charset'] = C('alipay_input_charset');
		$alipay_config['cacert'] = C('alipay_cacert');
		$alipay_config['transport'] = C('alipay_transport'); 

		$alipaySubmit = new AlipaySubmit($alipay_config);
		$m_url = 'https://mapi.alipay.com/gateway.do?';
		$m_data = $alipaySubmit->buildRequestForm($parameter,"post", "确认",2);

		parse_str($m_data,$url_params);

		$alipay_data['appid'] = '';
		$alipay_data['mch_id'] = C('alipay_partner');
		$alipay_data['nonce_str'] = 'create_direct_pay_by_user';
		$alipay_data['product_id'] = '';
		$alipay_data['body'] = $body;
		$alipay_data['out_trade_no'] = $out_trade_no;
		$alipay_data['total_fee'] = $total_fee;
		$alipay_data['spbill_create_ip'] = getip();
		$alipay_data['prepay_id'] = '';
		$alipay_data['code_url'] = '';
		$alipay_data['sign'] = $url_params['sign'];
		$alipay_data['mch_key'] = C('alipay_key_new');
		$alipay_data['pay'] = 0;
		$alipay_data['qrcode_url'] = '';
		$alipay_data['code_type'] = 3;
		$alipay_data['createtime'] = time();
		$alipay_data['pay_time'] = time();
		$alipay_data['exp_time'] = time();

		$alipay_data['uid'] = session('uid');
		$alipay_data['uname'] = $this->session_u_name;
		$alipay_data['app_type'] = $total_m[0];
		$alipay_data['app_name'] = $total_m[1]; 
		$alipay_data['open_type'] = $sur_m[1];
		$alipay_data['need_receipt'] = $arr_data[4];
		if($arr_data[4] == 1 && !empty($arr_data[5])){
			$n_info = explode('-', $arr_data[5]);
			$alipay_data['receipt_type'] = $n_info[0] == 1 ? 1 : 2;
			$alipay_data['receipt_header'] = $n_info[1];
			$alipay_data['receipt_province'] = $n_info[2];
			$alipay_data['receipt_city'] = $n_info[3];
			$alipay_data['receipt_country'] = $n_info[4];
			$alipay_data['receipt_address'] = $n_info[5];
		}else{
			$alipay_data['receipt_type'] = '';
			$alipay_data['receipt_header'] = '';
			$alipay_data['receipt_province'] = '';
			$alipay_data['receipt_city'] = '';
			$alipay_data['receipt_country'] = '';
			$alipay_data['receipt_address'] = '';				
		}
		$s_data_res = M('Sweep_pay')->add($alipay_data);
		$ali_url = $m_url.$m_data;
		if($s_data_res > 0){
			header('Location:'.$ali_url);
		}
		//这是原始的支付宝支付
		// $html_text = $alipaySubmit->buildRequestForm($parameter,"post", "确认");
		// $tt['html'] = $html_text;
		// file_put_contents('alipaypay_tmp_info.php', "<?php \nreturn " . stripslashes(var_export($tt, true)) . ";", FILE_APPEND);
		// echo $html_text;
	}
	public function alipay_notify_pay(){
		$notify_data = $_POST;
		
		file_put_contents(RUNTIME_PATH.'membership_payment_cache/alipaypay_tmp_info.php', "<?php \nreturn " . stripslashes(var_export($notify_data, true)) . ";", FILE_APPEND);

		$notify_flag = array('TRADE_SUCCESS','TRADE_FINISHED');
		if(array_key_exists('trade_status', $notify_data) && in_array($notify_data['trade_status'],$notify_flag)){
			$notify_data['createtime'] = time();

			$sweep_where['mch_id'] = $notify_data['seller_id'];
			$sweep_where['out_trade_no'] = $notify_data['out_trade_no'];
			$sweep_where['pay'] = 0;
			$sweep_where['nonce_str'] = 'create_direct_pay_by_user';
			$sweep_where['code_type'] = 3;
			$notify_res = $this->common_save_info($sweep_where,'Alipay_pay_success',$notify_data);
			if($notify_res == 1){
				echo 'success';
				exit;	
			}
		}
	}
	public function alipay_return_pay(){
		file_put_contents(RUNTIME_PATH.'membership_payment_cache/alipaypay_tmp_info.php', "<?php \nreturn " . stripslashes(var_export($_GET, true)) . ";", FILE_APPEND);

		$ts = <<<EOF
<script>
    window.onload = function(){ 
		window.opener=null;
		window.open('','_self');
		window.close();
	}	
</script>
EOF;
	echo $ts;	
	exit;		
	}
	//财付通电脑支付
	public function tenpay_puter_pay($total_m,$sur_m,$arr_data,$f_money){
		// $f_money = 0.01;

		$tenpay_partner = '1222882401'; //财付通商户号
		$tenpay_key = 'cdc0d9d5379cebadedd4cb3cc6b09754';	 //财付通密钥	
		/* 获取提交的订单号 */
		$out_trade_no = mt_rand(1,100000).time().mt_rand(0,10000);
		/* 获取提交的商品名称 */
		$product_name = $total_m[1].' '.$f_money.'/元 '.$sur_m[1].'个月';
		/* 获取提交的备注信息 */
		$remarkexplain = '帮微营销平台会员支付';
		/* 支付方式 */
		$trade_mode = 1;
		// .",备注:".$remarkexplain
		$desc = $product_name;

		/* 商品价格（包含运费），以分为单位 */
		$total_fee = $f_money*100;

		$ten_return_url = C('site_url').'/User/Sweeppay/tenpay_return_pay';
		$ten_notify_url = C('site_url').'/User/Sweeppay/tenpay_notify_pay';

		/* 创建支付请求对象 */
		$reqHandler = new RequestHandler();
		$reqHandler->init();
		$reqHandler->setKey($tenpay_key);
		$reqHandler->setGateUrl("https://gw.tenpay.com/gateway/pay.htm");

		//----------------------------------------
		//设置支付参数 
		//----------------------------------------
		$reqHandler->setParameter("partner", $tenpay_partner);
		$reqHandler->setParameter("out_trade_no", $out_trade_no);
		$reqHandler->setParameter("total_fee", $total_fee);  //总金额
		$reqHandler->setParameter("return_url", $ten_return_url);
		$reqHandler->setParameter("notify_url", $ten_notify_url);
		$reqHandler->setParameter("body", $desc);
		//用户ip
		$reqHandler->setParameter("spbill_create_ip", $_SERVER['REMOTE_ADDR']);//客户端IP
		$reqHandler->setParameter("fee_type", "1");               //币种

		//系统可选参数
		$reqHandler->setParameter("sign_type", "MD5");  	 	  //签名方式，默认为MD5，可选RSA
		$reqHandler->setParameter("service_version", "1.0"); 	  //接口版本号
		$reqHandler->setParameter("input_charset", "utf-8");   	  //字符集
		$reqHandler->setParameter("sign_key_index", "1");    	  //密钥序号
		$reqHandler->setParameter("trade_mode",$trade_mode);              //交易模式（1.即时到帐模式，2.中介担保模式，3.后台选择（卖家进入支付中心列表选择））

		$reqUrl = $reqHandler->getRequestURL();

		parse_str($reqUrl,$url_params);

		$tenpay_data['appid'] = '';
		$tenpay_data['mch_id'] = $tenpay_partner;
		$tenpay_data['nonce_str'] = 'gw.tenpay.com';
		$tenpay_data['product_id'] = '';
		$tenpay_data['body'] = $desc;
		$tenpay_data['out_trade_no'] = $out_trade_no;
		$tenpay_data['total_fee'] = $f_money;
		$tenpay_data['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];
		$tenpay_data['prepay_id'] = '';
		$tenpay_data['code_url'] = '';
		$tenpay_data['sign'] = $url_params['sign'];
		$tenpay_data['mch_key'] = $tenpay_key;
		$tenpay_data['pay'] = 0;
		$tenpay_data['qrcode_url'] = '';
		$tenpay_data['code_type'] = 4;
		$tenpay_data['createtime'] = time();
		$tenpay_data['pay_time'] = time();
		$tenpay_data['exp_time'] = time();

		$tenpay_data['uid'] = session('uid');
		$tenpay_data['uname'] = $this->session_u_name;
		$tenpay_data['app_type'] = $total_m[0];
		$tenpay_data['app_name'] = $total_m[1]; 
		$tenpay_data['open_type'] = $sur_m[1];
		$tenpay_data['need_receipt'] = $arr_data[4];
		if($arr_data[4] == 1 && !empty($arr_data[5])){
			$n_info = explode('-', $arr_data[5]);
			$tenpay_data['receipt_type'] = $n_info[0] == 1 ? 1 : 2;
			$tenpay_data['receipt_header'] = $n_info[1];
			$tenpay_data['receipt_province'] = $n_info[2];
			$tenpay_data['receipt_city'] = $n_info[3];
			$tenpay_data['receipt_country'] = $n_info[4];
			$tenpay_data['receipt_address'] = $n_info[5];
		}else{
			$tenpay_data['receipt_type'] = '';
			$tenpay_data['receipt_header'] = '';
			$tenpay_data['receipt_province'] = '';
			$tenpay_data['receipt_city'] = '';
			$tenpay_data['receipt_country'] = '';
			$tenpay_data['receipt_address'] = '';				
		}
		$_data_res = M('Sweep_pay')->add($tenpay_data);
		if($_data_res > 0){
			//请求的URL
			header('Location:'.$reqUrl);
		}
	}
	public function tenpay_notify_pay(){
		$ten_arr = $_GET;

		file_put_contents(RUNTIME_PATH.'membership_payment_cache/tenpay_tmp_info_success.php', "<?php \nreturn " . stripslashes(var_export($_GET, true)) . ";", FILE_APPEND);

		if(array_key_exists('trade_state', $ten_arr) && $ten_arr['trade_state'] == 0){
			$ten_arr['createtime'] = time();

			$sweep_where['mch_id'] = $ten_arr['partner'];
			$sweep_where['out_trade_no'] = $ten_arr['out_trade_no'];
			$sweep_where['pay'] = 0;
			$sweep_where['nonce_str'] = 'gw.tenpay.com';
			$sweep_where['code_type'] = 4;

			$notify_res = $this->common_save_info($sweep_where,'Tenpay_pay_success',$ten_arr);
			if($notify_res == 1){
				echo 'Success';
				exit;	
			}
		}
	}
	public function tenpay_return_pay(){
		file_put_contents(RUNTIME_PATH.'membership_payment_cache/tenpay_tmp_info_success.php', "<?php \nreturn " . stripslashes(var_export($_GET, true)) . ";", FILE_APPEND);
		$ts = <<<EOF
<script>
    window.onload = function(){ 
		window.opener=null;
		window.open('','_self');
		window.close();
	}	
</script>
EOF;
	echo $ts;	
	exit;
	}

	public function baidupay_puter_pay1($total_m,$sur_m,$arr_data,$f_money){
		$f_money = 1;
		$bd_p_url = 'https://wallet.baidu.com/api/0/pay/0/direct';
		$bd_p_arr['service_code'] = 1;
		$bd_p_arr['sp_no'] = '1000000134';
		$bd_p_arr['order_create_time'] = date('YMDhis',time());
		$bd_p_arr['order_no'] = mt_rand(1,100000).time();
		$bd_p_arr['goods_name'] = 1;
		$bd_p_arr['total_amount'] = $f_money;
		$bd_p_arr['currency'] = 1;
		$bd_p_arr['return_url'] = C('site_url').'/User/Sweeppay/bd_pay_return_url';
		$bd_p_arr['page_url'] = C('site_url').'/User/Sweeppay/bd_pay_page_url';
		$bd_p_arr['pay_type'] = 1;
		$bd_p_arr['input_charset'] = 1;
		$bd_p_arr['version'] = 2;
		$bd_p_arr['sign'] = 
		$bd_p_arr['sign_method'] = 1;
		
	}
	public function bd_pay_return_url(){
		file_put_contents(RUNTIME_PATH.'membership_payment_cache/bd_tmp_info_success.php', "<?php \nreturn " . stripslashes(var_export($_REQUEST, true)) . ";", FILE_APPEND);

	}
	public function bd_pay_page_url(){
		file_put_contents(RUNTIME_PATH.'membership_payment_cache/bd_tmp_info_success.php', "<?php \nreturn " . stripslashes(var_export($_REQUEST, true)) . ";", FILE_APPEND);

	}

	//百度钱包支付
	public function baidupay_puter_pay($total_m,$sur_m,$arr_data,$f_money){
		$f_money = 0.01;
		$bd_p_url = 'http://app.baidu.com/store/submitorder';
		$bd_p_data['app_id'] = 1000000134;
		$bd_p_data['amount'] = $f_money;
		// $bd_p_data['message'] = ;
		$bd_p_data['message'] = '帮微营销平台会员支付';
		$bd_p_data['parameters'] = urlencode('1230612');
		$bd_item['price'] = $f_money;
		$bd_item['count'] = 1;
		$bd_item['description'] = '您的选择是:'.$total_m[1].' '.$f_money.'/元 '.$sur_m[1].'个月';
		$bd_item['vitid'] = mt_rand(1,1000).time().mt_rand(1,100000);
		$bd_p_data['items'] = json_encode($bd_item); 
		$bd_p_data['pay_type'] = 1;
		$bd_p_data['sandbox'] = 1;
		// $json_res = curlPost($bd_p_url,$bd_p_data);
		// print_r($json_res);exit;
		$form_str = $this->createFormStr($bd_p_data,$bd_p_url);
		echo $form_str;
	}
	//根据数据生成form数据
	private function createFormStr($f_data,$bd_p_url){
		$sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='".$bd_p_url." method='POST'>";
		while (list ($key, $val) = each ($f_data)) {
	        $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
	    }
		//submit按钮控件请不要含有name属性
	    $sHtml = $sHtml."<input type='submit' value='提交' style='display:none;'></form>";
		$sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";
		return $sHtml;
	}
	//微信支付成功以后，异步请求的函数
	public function get_wx_notifyPay(){
		$xml_pay = file_get_contents("php://input");
		$post_arr = simplexml_load_string($xml_pay, 'SimpleXMLElement', LIBXML_NOCDATA);
		$postObj = object_to_array($post_arr);
		$postObj['str_xml'] = $xml_pay;
		file_put_contents(RUNTIME_PATH.'membership_payment_cache/pay_tmp_info_success.php', "<?php \nreturn " . stripslashes(var_export($postObj, true)) . ";", FILE_APPEND);
		if(is_array($postObj) && !empty($postObj) && array_key_exists('return_code', $postObj) && $postObj['return_code'] == 'SUCCESS'){
			$no_arr = $postObj;
			$no_arr['createtime'] = time();

			$sweep_where['appid'] = $postObj['appid'];
			$sweep_where['mch_id'] = $postObj['mch_id'];
			$sweep_where['out_trade_no'] = $postObj['out_trade_no'];
			$sweep_where['nonce_str'] = $postObj['nonce_str'];
			$sweep_where['pay'] = 0;
			$sweep_where['code_type'] = 1;

			$notify_res = $this->common_save_info($sweep_where,'Sweep_pay_success',$no_arr);
			if($notify_res == 1){
				echo 'SUCCESS';
				exit;	
			}
		}
	}
	public function query_pay_state(){
		$q_id = $this->_get('s_id','trim,intval');
		$pay_state = M('Sweep_pay')->where(array('id'=>$q_id))->getField('pay');
		if($pay_state != 0){
			echo json_encode(true);
		}else{
			echo json_encode(false);
		}
	}
	private function common_save_info($condition,$table,$s_data){
		M('Sweep_pay')->startTrans();

		$pay_res = M('Sweep_pay')->where($condition)->field('id,uid,open_type,app_type,app_name,createtime')->find();

		if(!empty($pay_res)){
			
			$exp_tt =  strtotime("+".$pay_res['open_type']."months",$pay_res['createtime']);

			$pay_info = M('Sweep_pay')->where(array('id'=>$pay_res['id']))->save(array('pay'=>1,'pay_time'=>time(),'exp_time'=>$exp_tt));

			$u_info = M('Users')->where(array('id'=>$pay_res['uid']))->field('gid,viptime,last_auth,pay_time')->find();

			if(!empty($u_info)){
				//如果当前的权限与选择的权限相同
				if($u_info['gid'] == $pay_res['app_type']){
					$u_data['gid'] = $pay_res['app_type'];				
					$vip_time = strtotime("+".$pay_res['open_type']."months",$u_info['viptime']);
					$u_data['viptime'] = $vip_time;
					$u_data['pay_time'] = time();
				}else{//不相同时，重新记录到期时间，并保留更改之前的权限
					$u_data['gid'] = $pay_res['app_type'];				
					$vip_time = strtotime("+".$pay_res['open_type']."months",$pay_res['createtime']);
					$u_data['viptime'] = $vip_time;
					//记录当前的权限和到期时间
					if(empty($u_info['last_auth']) || $u_info['last_auth'] == null){
						$pp_time = $u_info['pay_time'];
						$vip_days = $u_info['viptime'] - time();
						$last_auth[] = array('gid'=>$u_info['gid'],'pay_time'=>$pp_time,'viptime'=>$u_info['viptime'],'day_space'=>$vip_days);
					}else{
						$vip_days = $u_info['viptime'] - time();
						$new_info = array('gid'=>$u_info['gid'],'pay_time'=>$u_info['pay_time'],'viptime'=>$u_info['viptime'],'day_space'=>$vip_days);
						$last_auth = unserialize($u_info['last_auth']);
						array_unshift($last_auth,$new_info);
					}
					$u_data['last_auth'] = serialize($last_auth); 
					$u_data['pay_time'] = time();//购买时间
				}
				$u_info_res = M('Users')->where(array('id'=>$pay_res['uid']))->save($u_data);
				$succ_res = M($table)->add($s_data);

				$tt['table'] = $table;
				$tt['sweep_sql'] = M('Sweep_pay')->getlastsql();
				$tt['users_sql'] = M('Users')->getlastsql();
				$tt[$table.'_sql'] = M($table)->getlastsql();

				$tt['res'] = array('sweep_res'=>$pay_info,'users_res'=>$u_info_res,$table.'_res'=>$succ_res);
				$tt = $tt + $u_data;

				file_put_contents(RUNTIME_PATH.'membership_payment_cache/all_pay_success.php', "<?php \nreturn " . stripslashes(var_export($tt, true)) . ";", FILE_APPEND);

				if($pay_info !== false && $u_info_res !== false && $succ_res > 0){
					M('Sweep_pay')->commit();
					return 1;
				}else{
					M('Sweep_pay')->rollback();
					return -1;
				}				
			}
		}
	}
	public function get_pay_config(){
		$this->mch_id = '1220461901';
		// $this->appid = C('wx_appID');
		$this->appid = "wxc773d89e1cd6fd19";
		$this->key = '32993426f38b3ec1314a6b4e5dfd2cc4';
		$this->productid = mt_rand(1,1000000).time();
		$this->noncestr = $this->create_noncestr(25);
		$this->timestamp = time();
	}
	public function create_noncestr( $length = 16 ) {  
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";  
		$str ="";  
		for ( $i = 0; $i < $length; $i++ )  {  
			$str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		}  
		return $str;  
	}
	public function formatBizQueryParaMap($paraMap, $urlencode){
		$buff = "";
		ksort($paraMap);

		foreach ($paraMap as $k => $v){
			if($urlencode){
				$v = urlencode($v);
			}
			// $buff .= strtolower($k) . "=" . $v . "&";
			$buff .= $k . "=" . $v . "&";
		}
		$reqPar;
		if (strlen($buff) > 0) {
			$reqPar = substr($buff, 0, strlen($buff)-1);
		}
		return $reqPar;
	}
	public function arrayToXml($arr){
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
        	 if (is_numeric($val))
        	 {
        	 	$xml.="<".$key.">".$val."</".$key.">"; 

        	 }
        	 else
        	 	$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";  
        }
        $xml.="</xml>";
        return $xml; 
    }

	/**
	 * 	作用：以post方式提交xml到对应的接口url
	 */
	public function postXmlCurl($xml,$url,$second=60){
        //初始化curl        
       	$ch = curl_init();
		//设置超时
		curl_setopt($ch, "http://www.baidu.con", $second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//运行curl
        $data = curl_exec($ch);
		curl_close($ch);
		//返回结果
		if($data)
		{
			curl_close($ch);
			return $data;
		}
		else 
		{ 
			$error = curl_errno($ch);
			echo "curl出错，错误码:$error"."<br>"; 
			echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";
			curl_close($ch);
			return false;
		}
	}

	public function xmlToArray($xml){		
        //将XML转为array        
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);		
		return $array_data;
	}
	//城市列表
	public function get_city(){
		$arr['upid'] = $this->_get('id','intval');
		$arr['level'] = $this->_get('level','intval');		
		$city = $this->get_province($arr);
		echo json_encode($city);
	}	
	//获取省份列表
	private function get_province($arr){
		$conditon['upid'] = $arr['upid'] == '' ? 0 : $arr['upid'];
		$conditon['level'] = $arr['level'] == '' ? 1 : $arr['level'];
		return M('City')->field('id,name,upid')->where($conditon)->select();
	}
	//更改用户的发票地址
	public function save_receipt(){
		$logid = $this->_post('log_id','trim,intval');
		$log_str = $this->_post('log_data','trim,htmlspecialchars_decode');
		parse_str($log_str,$log_parm);
		$r_type = intval(trim($log_parm['nra_type']));
		$r_head = trim($log_parm['receipt_head']);
		$r_province = intval(trim($log_parm['province']));
		$r_city = intval(trim($log_parm['city']));
		$r_country = intval(trim($log_parm['country']));
		$r_address = trim($log_parm['receipt_address']);
		if(empty($r_head)){
			echo json_encode(array('errno'=>-1,'msg'=>'发票抬头不能为空!'));
			exit;
		}else{
			$s_data['receipt_header'] = $r_head;
		}
		if(empty($r_address)){
			echo json_encode(array('errno'=>-1,'msg'=>'发票详细地址不能为空!'));
			exit;
		}else{
			$s_data['receipt_address'] = $r_address;
		}
		$s_data['receipt_type'] = $r_type;
		$s_data['receipt_province'] = $r_province;
		$s_data['receipt_city'] = $r_city;
		$s_data['receipt_country'] = $r_country;
		$one_arr = M('Sweep_pay')->where(array('id'=>$logid))->field('id')->find();
		if(empty($one_arr)){
			echo json_encode(array('errno'=>-1,'msg'=>'参数错误不能修改!'));
			exit;
		}else{
			$res_s = M('Sweep_pay')->where(array('id'=>$one_arr['id']))->save($s_data);
			if($res_s !== false){
				echo json_encode(array('errno'=>1,'msg'=>'发票信息修改成功!'));
				exit;
			}else{
				echo json_encode(array('errno'=>-1,'msg'=>'发票信息修改失败!'));
				exit;
			}
		}	
	}
}
?>