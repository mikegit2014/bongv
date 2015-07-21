<?php
/**
 * 功能：直达号接口
 * 
 **/
header("Content-type:text/html;charset=utf-8");
class BwBdzhidaAction extends Action {
	private $bdobj;
	private $apiappid;
	private $cache_url_data;
	private $access_token;
	private $email;
	private $phone;
	private $api_key;
	private $secret_key;
	private $scope;
	private $bdzd_cache;
	private $cache_file;
	function _initialize(){
		$this->cache_file = RUNTIME_PATH."baidu_cache/bdzd_access_token_file.php";
		$this->cache_url_data = RUNTIME_PATH.'baidu_cache/bdzd_md5_url_appid.php';
		$this->bdzd_cache = include_once($this->cache_file);		
		$this->get_config();
		$this->access_token = $this->get_access_token();
	}
	//POST提交的数据
	public function create_manage_bdzd(){

		file_put_contents(RUNTIME_PATH.'baidu_cache/user_twi_log.php', "<?php \nreturn " . stripslashes(var_export($_POST, true)) . ";", FILE_APPEND);
		// $p_flag_type = 2; //1:不需要是否需要资质2,需要资质
		$p_flag_type = $_POST['p_flag_type'];
		if(!isset($p_flag_type)){
			$p_flag_type = 2;
		}
		$verify = $this->verify_data($_POST,$p_flag_type);


		$this->apiappid = $verify['apiappid'];

		$param['app_name'] = remove_special_symbols(htmlspecialchars_decode($verify['app_name']));
		$param['app_query'] = htmlspecialchars_decode($verify['app_query']);
		$param['app_url'] = $verify['app_url'];
		$param['app_logo'] = $verify['app_logo'];
		$param['apiappid'] = $this->apiappid;

		$apisign = $verify['sign'];
		
		$bdconfig['input_charset'] = 'utf-8';
		$bdconfig['sign_type'] = 'MD5';
		$bdconfig['key'] = $this->get_apiappkey($this->apiappid);
		$bdobj = new BdzdCommon($bdconfig);
		$para_sign = $bdobj->build_user_sign($param);
		// echo $para_sign;exit;
		$sign_res = $this->md5Verify($apisign,$para_sign);
		if($sign_res != 1){
			$this->show_msg('签名错误!',5,$verify['return_url']);
			// $this->show_msg('签名错误!',5);
			exit;
		}

		$data_save = $verify;

		if($p_flag_type != 1){
			if(empty($verify['org_license_no']) || !array_key_exists('org_license_no', $verify)){
				$verify['org_license_no'] = mt_rand(1,1000).time().mt_rand(1,10000);
			}
			$img_array['org_license_img'] = $verify['org_license_img'];
			if(!empty($verify['org_remark'])){
				$img_array['org_remark'] = unserialize(htmlspecialchars_decode($verify['org_remark']));
			}
			// $img_array['keyword_remark'] = $verify['keyword_remark'];

			$img_array['man_legal_over_img'] = $verify['man_legal_over_img'];
			$img_array['man_legal_under_img'] = $verify['man_legal_under_img'];
			$img_array['man_legal_ensure'] = $verify['man_legal_ensure'];

			//把资质的图片保存到服务器
			foreach ($img_array as $ki => $vi) {
				if($ki == 'org_remark'){
					$tmp_org = array();
					foreach ($vi as $k_o => $v_o) {
						if(empty($v_o)){
							$tmp_org[$k_o] = '';
						}else{
							$p = pathinfo($v_o);
							$extname = $p['extension'];
							$imgfilename = md5($verify['org_license_no'].$ki.$k_o).'.'.$extname;
							$tmp_org[$k_o] = $this->save_aptitude_img($v_o,$imgfilename);
						}
					}
					$data_save['org_remark'] = serialize($tmp_org);
				}else{
					if(!empty($vi)){
						$p = pathinfo($vi);
						$extname = $p['extension'];
						$imgfilename = md5($verify['org_license_no'].$ki).'.'.$extname;
						$data_save[$ki] = $this->save_aptitude_img($vi,$imgfilename);
					}
				}
			}	
		}

		if(stripos($data_save['app_url'],'channel') === false && stripos($data_save['app_url'],'zhida') === false){

			if(stripos($data_save['app_url'], '?') === false){//没有找到？
				$data_save['app_url'] .= '?channel=zhida';
			}else{
				$data_save['app_url'] .= '&channel=zhida';
			} 
		}

		$data_save['email'] = C('bdzd_email');
		$data_save['phone'] = C('bdzd_phone');
		$data_save['app_status'] = 1;
		$data_save['createtime'] = time();
		$data_save['userid'] = trim($_POST['userid']);
		$data_save['username'] = trim($_POST['username']);
		//用户提交的数据入库

		file_put_contents(RUNTIME_PATH.'baidu_cache/user_twi_log.php', "<?php \nreturn " . stripslashes(var_export($data_save, true)) . ";", FILE_APPEND);

		$save_id = M('Bdzd_list')->add($data_save);
		if($save_id > 0){
			$return_back = $data_save['return_url'].'?status=1&custom_id='.$_POST['custom_id'].'&v_flag=create';
			header('Location:'.$return_back);
		}else{
			$return_back = $data_save['return_url'].'?status=-1&custom_id='.$_POST['custom_id'].'&v_flag=create';
			header('Location:'.$return_back);
		}
	}
	//直达号信息为提交之前修改信息
	public function modify_manage_bdzd(){

		file_put_contents(RUNTIME_PATH.'baidu_cache/user_twiedit_log.php', "<?php \nreturn " . stripslashes(var_export($_POST, true)) . ";", FILE_APPEND);
		// $p_flag_type = 2; //1:不需要是否需要资质2,需要资质
		$p_flag_type = $_POST['p_flag_type'];
		if(!isset($p_flag_type)){
			$p_flag_type = 2;
		}
		$verify = $this->verify_data($_POST,$p_flag_type);
		$this->apiappid = $verify['apiappid'];

		$param['app_name'] = remove_special_symbols(htmlspecialchars_decode($verify['app_name']));
		$param['app_query'] = htmlspecialchars_decode($verify['app_query']);
		$param['app_url'] = htmlspecialchars_decode($verify['app_url']);
		$param['app_logo'] = htmlspecialchars_decode($verify['app_logo']);
		$param['apiappid'] = $this->apiappid;
		// $param['edit_flag'] = intval(trim($_POST['edit_flag']));

		$apisign = $verify['sign'];
		
		$bdconfig['input_charset'] = 'utf-8';
		$bdconfig['sign_type'] = 'MD5';
		$bdconfig['key'] = $this->get_apiappkey($this->apiappid);
		$bdobj = new BdzdCommon($bdconfig);
		$para_sign = $bdobj->build_user_sign($param);
		$sign_res = $this->md5Verify($apisign,$para_sign);
		if($sign_res != 1){
			$this->show_msg('签名错误!',5,$verify['return_url']);
			exit;
		}

		$data_save = $verify;

		if(stripos($data_save['app_url'],'channel') === false && stripos($data_save['app_url'],'zhida') === false){

			if(stripos($data_save['app_url'], '?') === false){//没有找到？
				$data_save['app_url'] .= '?channel=zhida';
			}else{
				$data_save['app_url'] .= '&channel=zhida';
			} 

		}

		if($p_flag_type != 1){
			if(empty($verify['org_license_no']) || !array_key_exists('org_license_no', $verify)){
				$verify['org_license_no'] = mt_rand(1,1000).time().mt_rand(1,10000);
			}
			$img_array['org_license_img'] = $verify['org_license_img'];
			$img_array['app_logo'] = $verify['app_logo'];

			if(!empty($verify['org_remark'])){
				$img_array['org_remark'] = unserialize(htmlspecialchars_decode($verify['org_remark']));
			}
			// $img_array['keyword_remark'] = $verify['keyword_remark'];

			$img_array['man_legal_over_img'] = $verify['man_legal_over_img'];
			if(empty($verify['man_legal_under_img'])){
				$img_array['man_legal_under_img'] = $verify['man_legal_under_img'];
			}
			$img_array['man_legal_ensure'] = $verify['man_legal_ensure'];

			//把资质的图片保存到服务器
			foreach ($img_array as $ki => $vi) {
				if($ki == 'org_remark'){
					$tmp_org = array();
					foreach ($vi as $k_o => $v_o) {
						if(empty($v_o)){
							$tmp_org[$k_o] = '';
						}else{
							$p = pathinfo($v_o);
							$extname = $p['extension'];
							$imgfilename = md5($verify['org_license_no'].$ki.$k_o).'.'.$extname;
							$tmp_org[$k_o] = $this->save_aptitude_img($v_o,$imgfilename);
						}
					}
					$data_save['org_remark'] = serialize($tmp_org);
				}else{
					if(!empty($vi)){
						$p = pathinfo($vi);
						$extname = $p['extension'];
						$imgfilename = md5($verify['org_license_no'].$ki).'.'.$extname;
						$data_save[$ki] = $this->save_aptitude_img($vi,$imgfilename);
					}
				}
			}	
		}

		$data_save['email'] = C('bdzd_email');
		$data_save['phone'] = C('bdzd_phone');
		$data_save['app_status'] = 1;
		$data_save['updatetime'] = time();
		$data_save['userid'] = trim($_POST['userid']);
		$data_save['username'] = trim($_POST['username']);
		// $data_save['edit_flag'] = intval(trim($_POST['edit_flag']));
		//用户提交的数据入库

		file_put_contents(RUNTIME_PATH.'baidu_cache/user_twiedit_log.php', "<?php \nreturn " . stripslashes(var_export($data_save, true)) . ";", FILE_APPEND);

		$mo_where['id'] = intval(trim($_POST['id']));
		// print_r($data_save);exit;
		$save_id = M('Bdzd_list')->where($mo_where)->save($data_save);
		if($save_id !== false){
			$return_back = $data_save['return_url'].'?status=1&custom_id='.$_POST['custom_id'].'&v_flag=modify';
			header('Location:'.$return_back);
		}else{
			$return_back = $data_save['return_url'].'?status=-1&custom_id='.$_POST['custom_id'].'&id='.$mo_where['id'].'&v_flag=modify';
			header('Location:'.$return_back);
		}	
	}
	//检查直达词是否存在
	public function query_keyword(){
		$key = $this->_get('keyword','trim,htmlspecialchars_decode');
		$sign = $this->_get('sign','trim');
		$apiappid = $this->_get('apiappid','trim');

		$param['keyword'] = $key;
		$param['apiappid'] = $apiappid;
		
		$bdconfig['input_charset'] = 'utf-8';
		$bdconfig['sign_type'] = 'MD5';
		$bdconfig['key'] = $this->get_apiappkey($apiappid);
		$bdobj = new BdzdCommon($bdconfig);
		$para_sign = $bdobj->build_user_sign($param);
		$sign_res = $this->md5Verify($sign,$para_sign);
		if($sign_res != 1){
			echo json_encode(array('errno'=>-2,'msg'=>'签名错误!'));
			exit;
		}
		$k_url = 'https://openapi.baidu.com/rest/2.0/devapi/v1/lightapp/query/isonline?access_token='.$this->access_token.'&keyword='.$key;
		$k_res = curlGet($k_url);
		$k_status = json_decode($k_res,true);
		if($k_status['status'] == 1){
			// echo json_encode(array('errno'=>1,'msg'=>'已使用'));
			// exit;
			echo json_encode(array('msg'=>'该直达词不可以使用!','error'=>-2));
				die();
		}else{
			// echo json_encode(array('errno'=>-1,'msg'=>'未使用'));
			// exit;
			echo json_encode(array('msg'=>'该直达词可以使用!','error'=>1));
				die();
		}	
	}
	//查询直达号的状态
	public function query_status(){
		$m_id = $this->_get('app_id','trim,intval');
		$sign = $this->_get('sign','trim');
		$apiappid = $this->_get('apiappid','trim');
	
		$param['app_id'] = $m_id;
		$param['apiappid'] = $apiappid;
		
		$bdconfig['input_charset'] = 'utf-8';
		$bdconfig['sign_type'] = 'MD5';
		$bdconfig['key'] = $this->get_apiappkey($apiappid);
		$bdobj = new BdzdCommon($bdconfig);
		$para_sign = $bdobj->build_user_sign($param);
		$sign_res = $this->md5Verify($sign,$para_sign);
		if($sign_res != 1){
			echo json_encode(array('errno'=>-2,'msg'=>'签名错误!'));
			exit;
		}
		$list_info = M('Bdzd_list')->where(array('app_id'=>$m_id))->field('id,notify_url')->find();
		if(empty($list_info)){
			die('app_id有误!');
		}

		$bd_face = $this->query_status_bd($m_id);

		if(array_key_exists('status', $bd_face)){
			$list_res = M('Bdzd_list')->where(array('id'=>$list_info['id']))->save(array('app_status'=>$bd_face['status']));
			if($list_res !== false){
				echo json_encode($bd_face);
				die();
			}
		}else{
			echo json_encode($bd_face);
			die();
		}

	}
	//请求百度的查询url
	private function query_status_bd($app_id){
		$sta_url = 'https://openapi.baidu.com/rest/2.0/devapi/v1/lightapp/query/status/get';
		$sta_url .= '?access_token='.$this->access_token.'&app_id='.$app_id;
		$sta_json = curlGet($sta_url);
		$sta_arr = json_decode($sta_json,true);
		return $sta_arr;
		// if(array_key_exists('status',$sta_arr)){
		// 	if($sta_arr['status'] > 1){
		// 		return $sta_arr['status'];
		// 	}else{
		// 		return $sta_arr;
		// 	}
		// }
	}
	//下线一个商户所属的直达号
	public function offline_zhida(){
		$m_id = $this->_get('modify_app_id','trim,intval');
		$sign = $this->_get('sign','trim');
		$apiappid = $this->_get('apiappid','trim');
	
		$param['modify_app_id'] = $m_id;
		$param['apiappid'] = $apiappid;
		
		$bdconfig['input_charset'] = 'utf-8';
		$bdconfig['sign_type'] = 'MD5';
		$bdconfig['key'] = $this->get_apiappkey($apiappid);
		$bdobj = new BdzdCommon($bdconfig);
		$para_sign = $bdobj->build_user_sign($param);
		$sign_res = $this->md5Verify($sign,$para_sign);
		if($sign_res != 1){
			echo json_encode(array('errno'=>-2,'msg'=>'签名错误!'));
			exit;
		}
		// ,'app_status'=>4
		$list_info = M('Bdzd_list')->where(array('app_id'=>$m_id))->field('id,notify_url')->find();
		if(empty($list_info)){
			die('app_id有误!');
		}

		$o_res = $this->offline_bd($m_id);
		// $o_res['status'] = 1;
		if($o_res['status'] == 1){
			$offline = M('Bdzd_list')->where(array('id'=>$list_info['id']))->save(array('app_status'=>7));
			if($offline !== false){
				echo json_encode($o_res);
				die();
				// $s_url = $list_info['notify_url'].'?status=1&v_flag=offline';
				// curlGet($s_url);
			}
		}else{
			echo json_encode($o_res);
			die();
			// $s_url = $list_info['notify_url'].'?status=-1&v_flag=offline';
			// 	curlGet($s_url);
		}
	}
	//请求百度的下线url
	private function offline_bd($bd_app_id){
		$offline_url = 'https://openapi.baidu.com/rest/2.0/devapi/v1/lightapp/agent/offline';
		$o_data['offline_app_id'] = $bd_app_id;
		$o_data['access_token'] = $this->access_token;
		$off_res = curlPost($offline_url,$o_data);
		$off_data = json_decode($off_res,true);
		return $off_data;
		// if(array_key_exists('status', $off_data) && $off_data['status'] == 1){
		// 	return 1;
		// }else{
		// 	return -1;
		// }
	}
	//修改直达词
	public function modify_query_keyword(){
		$m_id = $this->_post('modify_app_id','trim,intval');
		$query = $this->_post('query','trim,htmlspecialchars_decode');
		$sign = $this->_post('sign','trim');
		$apiappid = $this->_post('apiappid','trim');
		$e_flag = $this->_post('edit_flag','trim,intval');
		$b_flag = $this->_post('bd_flag','trim,intval');
	
		$param['modify_app_id'] = $m_id;
		$param['query'] = $query;
		$param['apiappid'] = $apiappid;
		$param['edit_flag'] = $e_flag;
		$param['bd_flag'] = $b_flag;
		
		$bdconfig['input_charset'] = 'utf-8';
		$bdconfig['sign_type'] = 'MD5';
		$bdconfig['key'] = $this->get_apiappkey($apiappid);
		$bdobj = new BdzdCommon($bdconfig);
		$para_sign = $bdobj->build_user_sign($param);
		$sign_res = $this->md5Verify($sign,$para_sign);
		if($sign_res != 1){
			$this->show_msg('签名错误',3);
			exit;
			// echo json_encode(array('errno'=>-2,'msg'=>'签名错误!'));
			// exit;
		}
		$list_info = M('Bdzd_list')->where(array('app_id'=>$m_id))->field('id,notify_url,return_url,edit_flag')->find();
		if(empty($list_info)){
			die('app_id有误!');
		}


		switch ($b_flag) { //直接修改到百度
			case 2:
				$q_res = $this->modify_keyword($m_id,$query);
				if($q_res == 1){
					// $qu_res = M('Bdzd_list')->where(array('id'=>$list_info['id']))->save(array('app_query'=>$query,'app_status'=>2));
					// if($qu_res !== false){
					$s_url = $list_info['return_url'].'?status=1&v_flag=modify_word';
					header('Location:'.$s_url);
					// }
				}else{
					$s_url = $list_info['return_url'].'?status=-1&v_flag=modify_word';
					header('Location:'.$s_url);
				}
				break;
			
			default:
				if(empty($list_info['edit_flag'])){
					$k_str = $e_flag;
				}else{
					if(stripos($list_info['edit_flag'], (string)$e_flag) === false)
						$k_str = $list_info['edit_flag'].'#'.$e_flag;
					else
						$k_str = $list_info['edit_flag'];
				}
				$save_key_arr['app_query'] = $query;
				$save_key_arr['app_status'] = 101;
				$save_key_arr['edit_flag'] = $k_str;
				$save_key_arr['updatetime'] = time();
				$save_key_res = M('Bdzd_list')->where(array('id'=>$list_info['id']))->save($save_key_arr);
				if($save_key_res !== false){
					$s_url = $list_info['return_url'].'?status=1&v_flag=modify_word';
					header('Location:'.$s_url);
				}else{
					$s_url = $list_info['return_url'].'?status=-1&v_flag=modify_word';
					header('Location:'.$s_url);
				}
				break;
		}	
	}
	//请求修改直达词url
	private function modify_keyword($app_id,$query){
		$m_url = 'https://openapi.baidu.com/rest/2.0/devapi/v1/lightapp/agent/modify/queryinfo';
		$k_data['access_token'] = $this->access_token;
		$k_data['modify_app_id'] = $app_id;
		$k_data['query'] = $query;
		$k_json = curlPost($m_url,$k_data);
		$k_res = json_decode($k_json,true);
		if(array_key_exists('status', $k_res) && $k_res['status'] == 1){
			return 1;
		}else{
			return -1;
		}
	}
	//修改应用信息
	public function modify_zhida_info(){
		$m_id = $this->_post('modify_app_id','trim,intval');
		$app_url = $this->_post('app_url','trim,htmlspecialchars_decode');
		$app_name = $this->_post('app_name','trim,htmlspecialchars_decode');
		$app_desc = $this->_post('app_desc','trim,htmlspecialchars_decode');
		$app_logo = $this->_post('app_logo','trim,htmlspecialchars_decode');
		$e_flag = $this->_post('edit_flag','trim,intval');
		$b_flag = $this->_post('bd_flag','trim,intval');

		if(!empty($app_name) && isset($app_name)){
			$param['app_name'] = remove_special_symbols($app_name);
		}

		if(!empty($app_url) && isset($app_url)){
			$param['app_url'] = $app_url;
		}
		if(!empty($app_desc) && isset($app_desc)){
			$param['app_desc'] = $app_desc;
		}
		if(!empty($app_logo) && isset($app_logo)){
			$param['app_logo'] = $app_logo;
		}
		$sign = $this->_post('sign','trim');
		$apiappid = $this->_post('apiappid','trim');
	
		$param['modify_app_id'] = $m_id;
		$param['apiappid'] = $apiappid;
		$param['edit_flag'] = $e_flag;
		$param['bd_flag'] = $b_flag;
		$bdconfig['input_charset'] = 'utf-8';
		$bdconfig['sign_type'] = 'MD5';
		$bdconfig['key'] = $this->get_apiappkey($apiappid);
		$bdobj = new BdzdCommon($bdconfig);
		$para_sign = $bdobj->build_user_sign($param);
		$sign_res = $this->md5Verify($sign,$para_sign);
		if($sign_res != 1){
			$this->show_msg('签名错误',3);
			exit;
			// echo json_encode(array('errno'=>-2,'msg'=>'签名错误!'));
			// exit;
		}
		if(stripos($app_url,'channel') === false && stripos($app_url,'zhida') === false){

			if(stripos($app_url, '?') === false){//没有找到？
				$app_url .= '?channel=zhida';
			}else{
				$app_url .= '&channel=zhida';
			}
		}
		$param['app_url'] = $app_url;
		
		$list_info = M('Bdzd_list')->where(array('app_id'=>$m_id))->field('id,notify_url,return_url,edit_flag')->find();
		if(empty($list_info)){
			die('app_id有误!');
		}
		switch ($b_flag) {
			case 2:
				$q_res = $this->modify_info_bd($m_id,$param);
				if(is_array($q_res) && $q_res != -1){
					// $qu_res = M('Bdzd_list')->where(array('id'=>$list_info['id']))->save($q_res);
					// if($qu_res !== false){
					$s_url = $list_info['return_url'].'?status=1&v_flag=modify_info';
					header('Location:'.$s_url);
				}else{
					$s_url = $list_info['return_url'].'?status=-1&v_flag=modify_info';
					header('Location:'.$s_url);
				}
				break;
			default:
				$save_info_arr['app_name'] = remove_special_symbols($app_name);
				$save_info_arr['app_url'] = $app_url;
				$save_info_arr['app_desc'] = $app_desc;
				$save_info_arr['app_logo'] = $app_logo;

				if(empty($list_info['edit_flag'])){
					$k_str = $e_flag;
				}else{
					if(stripos($list_info['edit_flag'], (string)$e_flag) === false)
						$k_str = $list_info['edit_flag'].'#'.$e_flag;
					else
						$k_str = $list_info['edit_flag'];
				}
				$save_info_arr['edit_flag'] = $k_str;
				$save_info_arr['app_status'] = 101;
				$save_info_arr['updatetime'] = time();
				$save_key_res = M('Bdzd_list')->where(array('id'=>$list_info['id']))->save($save_info_arr);
				if($save_key_res !== false){
					$s_url = $list_info['return_url'].'?status=1&v_flag=modify_info';
					header('Location:'.$s_url);
				}else{
					$s_url = $list_info['return_url'].'?status=-1&v_flag=modify_info';
					header('Location:'.$s_url);
				}
				break;
		}

	}
	//请求百度修改应用信息的url
	private function modify_info_bd($m_id,$m_arr){
		$minfo_url = 'https://openapi.baidu.com/rest/2.0/devapi/v1/lightapp/agent/modify/appinfo';
		$minfo['access_token'] = $this->access_token;
		$minfo['modify_app_id'] = $m_id;
		$save_arr = array('app_name','app_url','app_desc','app_logo');
		foreach ($save_arr as $key => $value) {
		 	if(array_key_exists($value, $m_arr) && !empty($m_arr[$value])){
		 		if($value == 'app_desc'){
		 			$minfo['app_summary'] = $m_arr[$value];
		 		}else{
		 			$minfo[$value] = $m_arr[$value];
		 		}
		 		$ret[$value] = $m_arr[$value];
		 	}
		}
		$info_json = curlPost($minfo_url,$minfo);
		$info_arr = json_decode($info_json,true);
		if(array_key_exists('status', $info_arr) && $info_arr['status'] == 1){
			return $ret;
		}else{
			return -1;
		}
	}
	//修改商户行业分类
	public function modify_cat(){
		$m_id = $this->_post('app_id','trim,intval');
		$cat_id = $this->_post('qua_cat_id','trim');
		$sign = $this->_post('sign','trim');
		$apiappid = $this->_post('apiappid','trim');
		$e_flag = $this->_post('edit_flag','trim,intval');
		$b_flag = $this->_post('bd_flag','trim,intval');		
	
		$param['app_id'] = $m_id;
		$param['qua_cat_id'] = $cat_id;
		$param['apiappid'] = $apiappid;
		$param['edit_flag'] = $e_flag;
		$param['bd_flag'] = $b_flag;
		
		$bdconfig['input_charset'] = 'utf-8';
		$bdconfig['sign_type'] = 'MD5';
		$bdconfig['key'] = $this->get_apiappkey($apiappid);
		$bdobj = new BdzdCommon($bdconfig);
		$para_sign = $bdobj->build_user_sign($param);
		$sign_res = $this->md5Verify($sign,$para_sign);
		if($sign_res != 1){
			$this->show_msg('签名错误',3);
			exit;
		}
		$list_info = M('Bdzd_list')->where(array('app_id'=>$m_id))->field('id,notify_url,return_url,edit_flag')->find();
		if(empty($list_info)){
			die('app_id有误!');
		}

		switch ($b_flag) {
			case 2:
				$q_res = $this->modify_cat_bd($m_id,$cat_id);
				if($q_res['error_code'] == 108000){
					// $qu_res = M('Bdzd_list')->where(array('id'=>$list_info['id']))->save(array('qua_cat_id'=>$cat_id));
					// if($qu_res !== false){
					$s_url = $list_info['return_url'].'?status=1&v_flag=modify_bd_cat';
					header('Location:'.$s_url);
					// }
				}else{
					$s_url = $list_info['return_url'].'?status=-1&v_flag=modify_bd_cat';
					header('Location:'.$s_url);
				}
				break;
			default:
				$save_cat_arr['qua_cat_id'] = $cat_id;
				if(empty($list_info['edit_flag'])){
					$k_str = $e_flag;
				}else{
					if(stripos($list_info['edit_flag'], (string)$e_flag) === false)
						$k_str = $list_info['edit_flag'].'#'.$e_flag;
					else
						$k_str = $list_info['edit_flag'];
				}
				$save_cat_arr['app_status'] = 101;
				$save_cat_arr['edit_flag'] = $k_str;
				$save_cat_arr['updatetime'] = time();
				$save_key_res = M('Bdzd_list')->where(array('id'=>$list_info['id']))->save($save_cat_arr);
				if($save_key_res !== false){
					$s_url = $list_info['return_url'].'?status=1&v_flag=modify_bd_cat';
					header('Location:'.$s_url);
				}else{
					$s_url = $list_info['return_url'].'?status=-1&v_flag=modify_bd_cat';
					header('Location:'.$s_url);
				}
				break;
		}
			
	}
	//请求百度修改商户行业分类的url
	private function modify_cat_bd($m_id,$cat_id){
		$minfo_url = 'http://zhida.baidu.com/rest/2.0/devapi/v1/lightapp/agentstore/get/qua';
		$minfo['access_token'] = $this->access_token;
		$minfo['app_id'] = $m_id;
		$minfo['qua_cat_id'] = $cat_id;
		$info_json = curlPost($minfo_url,$minfo);
		$info_arr = json_decode($info_json,true);
		// print_r($info_arr);exit;
		if(array_key_exists('error_code', $info_arr) && $info_arr['error_code'] == 108000){
			return $info_arr;
		}else{
			return -1;
		}
	}
	//验证用户的参数是否合法
	public function verify_data($data,$flag = 1){
		$param['apiappid'] = 'appid';
		$param['sign'] = '签名';
		$param['custom_id'] = '服务商商户id';
		$param['return_url'] = array(1,'同步回调地址');
		$param['notify_url'] = array(1,'异步回调地址');
		$param['app_name'] = '应用名称';
		$param['app_logo'] = array(1,'应用logo');
		$param['app_query'] = '直达词';
		$param['app_url'] = array(1,'应用首页url');
		$param['app_desc'] = '应用描述';
		$param['qua_cat_id'] = '行业分类';
		if($flag != 1){//暂时去掉用户的资质信息
			$param['org_type'] = '组织类型'; //1,2
			if(empty($data['org_name']) && $data['org_private_buiness'] == 1 && array_key_exists('org_private_buiness', $data)){
				$param['org_private_buiness'] = '个体户';
			}else{
				$param['org_name'] = '企业名称或组织名称';
			}
			$param['org_license_no'] = '营业执照注册号或组织机构代码';
			$param['org_license_img'] = array(1,'营业执照扫描件或组织机构代码证扫描件');
			$param['org_licese_expire'] = '营业执照有效期或组织机构代码证有效期';
			$param['man_id_verify'] = '身份验证方式';
			$param['man_legal_name'] = '负责人姓名';
			$param['man_legal_type'] = '法定代表人证件类型'; // 1,2,3,4
			$param['man_legal_num'] = '负责人身份证号码或通行证号码或护照号码';
			$param['man_legal_over_img'] = array(1,'身份证扫描件正面或通行证扫描件正面或护照扫描件');
			// $param['man_legal_under_img'] = array(1,'身份证扫描件反面或通行证扫描件反面或护照扫描件');
			$param['man_legal_ensure'] = array(1,'保证函或企业授权书');
		}

		$new_arr = array();

		foreach($param as $key => $value) {
			if(array_key_exists($key, $data)){
				if(!empty($data[$key])){
					if(is_array($value) && $value[0] == 1){
						if(stripos($data[$key], 'http://') !== false ||  stripos($data[$key], 'https://') !== false){
							$new_arr[$key] = trim(htmlspecialchars_decode($data[$key]));
							continue; 	
						}else{
							$this->show_msg($value[1].'格式不正确',5);
							exit;	
						}
					}else{ 
						$new_arr[$key] = trim(htmlspecialchars_decode($data[$key]));
						continue; 
					}
				}else{
					if(is_array($value)){
						$value = $value[1];
					}					
					$this->show_msg($value.'不能为空',5);
					exit;
				}
			}else{
				if(is_array($value)){
					$value = $value[1];
				}
				$this->show_msg('缺失'.$value,5);
				exit;
			}

		}
		if($flag != 1){
			$new_arr['man_legal_under_img'] = $data['man_legal_under_img'];
			$new_arr['org_expire_long'] = $data['org_expire_long'];
			if(isset($data['org_private_buiness']))
				$new_arr['org_private_buiness'] = $data['org_private_buiness'];

			if($new_arr['man_legal_type'] != 4){
				if(array_key_exists('man_legal_under_img', $new_arr)){
					if(empty($new_arr['man_legal_under_img'])){
						$this->show_msg('身份证扫描件反面或通行证扫描件反面不能为空!',5);
						exit;
					}
				}else{
					$this->show_msg('缺失身份证扫描件反面或通行证扫描件反面',5);
					exit;
				}
			}


			if($new_arr['org_expire_long'] == 1){
				if(!array_key_exists('org_remark', $data)){
					$this->show_msg('缺失其他材料',5);
					exit;
				}else{
					if(empty($data['org_remark'])){
						$this->show_msg('其他材料不能为空!',5);
						exit;
					}else{
						$new_arr['org_remark'] = $data['org_remark'];
					}
				}
				$new_arr['org_licese_expire'] = 1;
			}else{
				if(preg_match('/^\d{4,}\-\d{1,2}\-\d{1,2}$/i',$new_arr['org_licese_expire']) == 0){
					$this->show_msg('营业执照有效期或组织机构代码证有效期格式不正确!',5);
					exit;
				}
				$new_arr['org_remark'] = $data['org_remark'];
			}
		}
		return $new_arr;
	}
	public function return_back_url(){
		file_put_contents(RUNTIME_PATH.'baidu_cache/bdzd_twwer_success.php', "<?php \nreturn " . stripslashes(var_export($_POST, true)) . ";", FILE_APPEND);
		echo "SUCCESS";
	}
	//根据appid获取appkey
	public function get_apiappkey($appid){
		$apikey = M('Bdzdapiconfig')->where(array('apikey'=>$appid,'apistatus'=>1))->getfield('apisecretkey');
		if(!empty($apikey)){
			return $apikey;
		}else{
			die('appid参数错误');
		}
	}
	//配置信息入库
	public function create_appid_appkey(){
		$bd['apikey'] = $this->create_noncestr(18);
		$bd['apisecretkey'] = $this->create_noncestr(32);
		$bd['apiname'] = '帮微正式';
		$bd['apistatus'] = 1;
		$bd['apiremark'] = '';
		// M('Bdzdapiconfig')->add($bd);
	}
	//生成随机的字符串
	public function create_noncestr( $length = 16 ) {  
		$chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ".time();  
		$str ="";  
		for ( $i = 0; $i < $length; $i++ )  {  
			$str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		}  
		return $str;  
	}
	//保存上传资质的图片
	public function save_aptitude_img($img_url,$file_name){
		$firstLetter=substr($this->apiappid,0,5);
		$savePath =  './uploads/'.$firstLetter.'/'.$this->apiappid.'/'.date('Y-m-d').'/';// 设置附件上传目录
		if (!file_exists($_SERVER['DOCUMENT_ROOT'].'/uploads')||!is_dir($_SERVER['DOCUMENT_ROOT'].'/uploads')){
			mkdir($_SERVER['DOCUMENT_ROOT'].'/uploads',0777);
		}
		$firstLetterDir=$_SERVER['DOCUMENT_ROOT'].'/uploads/'.$firstLetter;
		if (!file_exists($firstLetterDir)||!is_dir($firstLetterDir)){
			mkdir($firstLetterDir,0777);
		}
		if (!file_exists($firstLetterDir.'/'.$this->apiappid)||!is_dir($firstLetterDir.'/'.$this->apiappid)){
			mkdir($firstLetterDir.'/'.$this->apiappid,0777);
		}
		if (!file_exists($firstLetterDir.'/'.$this->apiappid.'/'.date('Y-m-d'))||!is_dir($firstLetterDir.'/'.$this->apiappid.'/'.date('Y-m-d'))){
			mkdir($firstLetterDir.'/'.$this->apiappid.'/'.date('Y-m-d'),0777);
		}		

		$save_path = $savePath.$file_name;
		if(stripos($img_url, $firstLetter) === false){
			$ch = curl_init();
	        $fp = fopen($save_path, 'wb');
	        curl_setopt($ch, CURLOPT_URL, $img_url);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	        curl_setopt($ch, CURLOPT_FILE, $fp);
	        curl_setopt($ch, CURLOPT_HEADER, 0);
	        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');        
	   		curl_setopt($ch, CURLOPT_TIMEOUT, 1000);

	        curl_exec($ch);
	        curl_close($ch);
	        fclose($fp);
       	    return C('site_url').substr($save_path,1);
    	}else{
	    	return $img_url;
    	}
	}
	//对比签名
	public function md5Verify($p_sign,$mysgin){
		if($mysgin == $p_sign) {
			return 1;
		}
		else {
			return -1;
		}
	}
	//读取直达号的配置
	public function get_config(){
		$this->api_key = C('bdzd_apikey');
		$this->secret_key = C('bdzd_secretkey');
		$this->email = C('bdzd_email');
		$this->phone = C('bdzd_phone');
		$this->scope = '';
	}
	//返回百度的access_token
	public function get_access_token(){
		if($this->bdzd_cache['expires'] < time()){
			$a_post['grant_type'] = 'client_credentials';
			$a_post['client_id'] = $this->api_key;
			$a_post['client_secret'] = $this->secret_key;
			$a_url = 'https://openapi.baidu.com/oauth/2.0/token';
			$acc_res = curlPost($a_url,$a_post);
			$res_json = json_decode($acc_res,true);
			if(array_key_exists('error', $res_json)){
				die('错误信息:'.$res_json['error'].'错误描述:'.$res_json['error_description']);
			}else{
				$access_arr['access_token'] = $res_json['access_token'];
				$access_arr['expires'] = time() + $res_json['expires_in'] - 100;
				$access_arr['refresh_token'] = $res_json['refresh_token'];
				$access_arr['session_key'] = $res_json['session_key'];
				$access_arr['session_secret'] = $res_json['session_secret'];
				file_put_contents($this->cache_file, "<?php \nreturn " . stripslashes(var_export($access_arr, true)) . ";", LOCK_EX);
				return $res_json['access_token'];
			}
		}else{
			return $this->bdzd_cache['access_token'];
		}
	}	
	//报错信息
	public function show_msg($msg,$second,$back_url = ''){
		if(empty($back_url)){
			$back_url = 'http://www.bongv.com';
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
			var auto_time=$second;
			var str="<div class='wd'>\
		        			<div class='line1'>\
		                		<div class='close'>√</div>\
		            		</div>\
		            		<div class='line2'>$msg</div>\
		            		<div class='line3'>\
								<div>本页面将在<span id='jsf'>$second</span>秒后自动跳转</div>\
		            		</div>\
		        		</div>";
			document.getElementById("body_cn").innerHTML=str;
			window.djs = setInterval(function(){
				auto_time = parseInt(auto_time)-1;
				document.getElementById('jsf').innerHTML = auto_time;
				console.log(auto_time);
				if(auto_time == 0){
					// window.opener=null;
					// window.open('','_top');
					// window.close();
					// self.close();
					document.getElementById("body_cn").innerHTML = '';
					window.clearInterval(window.djs);
					window.location.href = '$back_url';
				}	
				},1000);
			}	
		</script>

EOF;
	    echo $ts;
	}
	//根据url获取appid
	public function get_appid_from_url(){
		$url = $this->_get('from_url','htmlspecialchars_decode,urldecode,trim');
		$data_url = @include_once($this->cache_url_data);
		if(empty($data_url) || $data_url == 1){
			$where_bd['app_id'] = array('neq','');
			$where_bd['app_status'] = array('egt',2);
			$list_bd = M('Bdzd_list')->where($where_bd)->field('app_url,app_id')->select();
			$tmp_str = '';
			foreach ($list_bd as $k_bd => $v_bd) {
				// if(stripos($v_bd['app_url'],'/Store/cats/token') === false ){//帮微钻铺
				$md5_arr[md5($v_bd['app_url'])] = array('url'=>$v_bd['app_url'],'appid'=>$v_bd['app_id']);					
				/*}else{
					$tmp_wz = stripos($v_bd['app_url'], '/Store/cats/token/');
					$tmp_str = substr($v_bd['app_url'], 0,$tmp_wz).'.shtml?channel=zhida';
					$md5_arr[md5($tmp_str)] = array('url'=>$tmp_str,'appid'=>$v_bd['app_id']);
				}*/				
			}
			$data_url = $md5_arr;
			file_put_contents($this->cache_url_data, "<?php \nreturn " . stripslashes(var_export($md5_arr, true)) . ";", LOCK_EX );
		}
		$params = parse_url($url);
		// print_r($params);
		if(stripos($params['path'],'/Wap/Store/') === false){//不是帮微钻铺
			$cache_appid = $data_url[md5($url)]['appid'];
		}else{
			//申请时，使用的是index
			$params['path'] = str_replace('cats', 'index', $params['path']);
			$tmp_wz = stripos($params['path'], '/wecha_id/');
			if($tmp_wz !== false){
				$tmp_str = substr($params['path'], 0,$tmp_wz);
				$zupu_str = $params['scheme'].'://'.$params['host'].$tmp_str.'.shtml?channel=zhida';
			}else{
				$zupu_str = $params['scheme'].'://'.$params['host'].$params['path'].'?channel=zhida';
			}
			$cache_appid = $data_url[md5($zupu_str)]['appid'];
		}
		if(empty($cache_appid)){
			$return_data = array('error'=>-1,'appid'=>'','reques_url'=>$url); 
		}else{
			$return_data = array('error'=>0,'appid'=>$cache_appid,'reques_url'=>$url); 
		}
		echo $_REQUEST['callback'].'('.json_encode($return_data).')'; 
	}
	//定时查询审核中直达号的状态
	public function get_zhida_status(){
		$apid = $this->_get('apiid','trim');
		if(!empty($apid)){
			$zt_where['apiappid'] = $apid;
		}
		$zt_where['app_status'] = 2;
		$zt_where['app_id'] = array('neq','');
		$all_appids = M('Bdzd_list')->where($zt_where)->field('app_id')->select();
		// echo M('Bdzd_list')->getlastsql();exit;
		$hang = 0;
		foreach ($all_appids as $k_p => $v_p) {
			$bd_status = $this->query_status_bd($v_p['app_id']);
			if(array_key_exists('status', $bd_status) && $bd_status['status'] != 2){
				$s_data['app_status'] = $bd_status['status'];
				$s_data['reason'] = $bd_status['info'];
				$res_bd = M('Bdzd_list')->where(array('app_id'=>$v_p['app_id']))->save($s_data);
				if($res_bd !== false){
					$hang++;
				}
			}
		}
		if($hang > 0){
			echo json_encode('成功!');
			die();
		}
	}						
}