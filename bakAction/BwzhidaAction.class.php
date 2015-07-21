<?php
	/*
	*百度直达号
	*/
header("Content-type:text/html;charset=utf-8");
class BwzhidaAction extends UserAction{
	private $access_token;
	private $api_key;
	private $secret_key;
	public function _initialize(){
		parent::_initialize();
		//调用公共生成的token
		$interface = A('Api/BwBdzhida');
		$this->access_token = $interface->get_access_token();
		$this->api_key = 'EJGSJYSu291RombWoR';
		$this->secret_key = 'O14lqXlR6j1D19wJ7JtU694t1ZVGA7x7';
		$this->assign('strore_uid',session('uid')); 
	}
	public function index(){
		$s_flag = $this->_get('local_flag','trim,intval');
		$condition['apiappid'] = $this->api_key;
		$condition['userid'] = session('uid');
		$count = M('Bdzd_list')->where($condition)->count();
		$Page       = new Page($count,10);// 实例化分页类 传入总记录数
		// 进行分页数据查询 注意page方法的参数的前面部分是当前的页数使用 $_GET[p]获取
		$nowPage = isset($_GET['p'])?$_GET['p']:1;
		$show   = $Page->show();// 分页显示输出
		$list = M('Bdzd_list')->where($condition)->field('id,app_name,app_query,app_desc,app_status,org_name,app_id,reason,app_url')->order('createtime desc')->limit($Page->firstRow.','.$Page->listRows)->select();
		foreach ($list as $k_s => $v_s) {
			$url_flag = parse_url($v_s['app_url']);
			$sub_domain = explode('.',$url_flag['host']);
			if(!in_array($sub_domain[1],array('99114','bongv','zg114zs'))){
				$list[$k_s]['v_flag'] = 1;
			}
		}
		$this->assign('list',$list);
		$this->assign('page',$show);// 赋值分页输出
		$this->assign('s_flag',$s_flag);
		$this->display();
	}
	//新增
	public function add(){
		if(IS_POST){
			$validate = $this->valid_data($_POST);
			$_POST['org_licese_expire'] = $validate['org_expire_year'].'-'.$validate['org_expire_month'].'-'.$validate['org_expire_day'];
			$_POST['org_license_img'] = C('site_url').$validate['org_license_img'];
			if(!empty($_REQUEST['org_remark'])){
				foreach ($_REQUEST['org_remark'] as $k_r => $v_r) {
					if(!empty($v_r))
						$tmp_remk[$k_r] = C('site_url').$v_r;
					else
						$tmp_remk[$k_r] = $v_r;
				}
				$_POST['org_remark'] = htmlspecialchars_decode(serialize($tmp_remk));
			}
			$_POST['man_legal_over_img'] = C('site_url').$validate['man_legal_over_img'];
			$_POST['man_legal_under_img'] = C('site_url').$validate['man_legal_under_img'];
			$_POST['man_legal_ensure'] = C('site_url').$validate['man_legal_ensure'];
			$a_logo = C('site_url').$_POST['app_logo'];
			$_POST['apiappid'] = $this->api_key;
			$_POST['p_flag_type'] = 2;

			$arr['apiappid'] = $this->api_key;
			$a_name = $this->_post('app_name','trim,htmlspecialchars_decode');
			$a_query = $this->_post('app_query','trim,htmlspecialchars_decode');
			$a_url = $this->_post('app_url','trim,htmlspecialchars_decode');
			$a_desc = $this->_post('app_desc','trim,htmlspecialchars_decode');
			$a_cat_id = $this->_post('qua_cat_id','trim');

			$content_flag = utf8_str($a_query);
			switch ($content_flag) {
				case 1:
					if(abslength($a_query) > 20 || abslength($a_query) < 2){
						$this->error('直达词的长度不在2-20个英文字母/数字');
						exit;
					}
					break;
				case 2:
					if(abslength($a_query) > 10 || abslength($a_query) < 2){
						$this->error('直达词的长度不在2-10个汉字');
						exit;
					}
					break;				
				default:
					if(abslength($a_query) > 10 || abslength($a_query) < 2){
						$this->error('直达词的长度超过限制');
						exit;
					}
					break;
			}
			if(abslength($a_url) <= 0 || abslength($a_url) > 255){
				$this->error('站点名称:输入内容长度不在1-255个字符内');
				exit;
			}
			if(stripos($a_url, 'http://') === false || stripos($a_url, 'https://')){
				$this->error('站点URL:必须已http://或者https://开头!');
				exit;
			}
			if(abslength($a_name) <= 0 || abslength($a_name) > 15){
				$this->error('站点名称:输入内容长度不在1-15个字符内');
				exit;
			}
			if(abslength($a_desc) < 20 || abslength($a_desc) > 200){
				$this->error('站点简介:输入内容长度不在20-200个字符内');
				exit;
			}

			$m_img_1 = getcwd().str_replace('/', DIRECTORY_SEPARATOR, $_POST['app_logo']);
			$img_size = ceil(filesize($m_img_1) / 1000) . "k"; //获取文件大小
			if($img_size > 300){
				$this->error('站点logo大小不能大于300K');
				exit;
			}
			if(empty($a_logo)){
				$this->error('站点logo必须不能为空!');
				exit;	
			}
			$logo_info_img = getcwd().$_POST['app_logo'];
			$logo_info = getimagesize($logo_info_img);
			// $logo_info = getimagesize($a_logo);
			$array_mime = array('image/jpeg','image/png','image/gif');
			if(!in_array($logo_info['mime'],$array_mime)){
			   	$this->error('站点logo格式必须是：png、jpg、gif');
				exit;
			}
			list($width, $height) = $logo_info;
			if($width < 90 && $height < 90){
			   	$this->error('站点logo大小不能小于90x90!');
				exit;
			}
			if($logo_info == false){
	    		$this->error('站点logo不能正常打开!');
	    		exit;
			}

			if(empty($a_cat_id)){
	    		$this->error('所属行业,不能为空!');
	    		exit;
			}

			$_POST['app_name'] = remove_special_symbols($a_name);
			$_POST['app_query'] = remove_special_symbols($a_query);

			//重新组装钻铺的url
			if(stripos($a_url,'/Wap/Store') >= 0){
				$ym_url = substr($a_url, 0,stripos($a_url, '/Wap')+1);
				if(preg_match( '/\/token\/([0-9a-z\-\_]+)[\.\/\b]?/i', $a_url,$matchs )){
					$a_url = $ym_url.'Wap/Store/index/token/'.$matchs[1].'.shtml';
				}
			}

			$_POST['app_url'] = $a_url;
			$_POST['app_desc'] = $a_desc;
			$_POST['app_logo'] = $a_logo;
			$_POST['qua_cat_id'] = $a_cat_id == '' ? '248' : $a_cat_id;
			$_POST['userid'] = session('uid');
			$_POST['username'] = session('uname');

			$arr['app_name'] = remove_special_symbols($a_name);
			$arr['app_query'] = remove_special_symbols($a_query);
			$arr['app_url'] = $a_url;
			$arr['app_logo'] = $a_logo;


			$_POST['sign'] = $this->create_sign($arr,$this->secret_key);
			$_POST['return_url'] = C('site_url').'/User/Bwzhida/return_url';
			$_POST['notify_url'] = C('site_url').'/User/Bwzhida/notify_url';
			$_POST['custom_id'] = mt_rand(1,1000).time().time(1,10000);
			$p_a_url = C('site_url').'/Api/BwBdzhida/create_manage_bdzd';
			ksort($_POST);
			// curlPost($p_a_url,$_POST);
			// dump($_POST);exit;
			echo $this->buildRequestForm($_POST,$p_a_url);
			// $insert_id = M('Bdzd_list')->add($_POST);
		}else{
			$s_flag = $this->_get('local_flag','trim,intval');
			$this->assign('s_flag',$s_flag);
			$cat_arr_data = include_once(RUNTIME_PATH.'/baidu_cache/bdzd_cat.php');
			$this->assign('cat_data',$cat_arr_data);
			$this->display();
		}
	}
	//查询直达词是否被占用
	public function exists_word(){
		$word = $this->_get('word','trim');
		$edit_type = $this->_get('edit_v','trim,intval');
		// $word = '桂林旅游';
		$sign_str = 'apiappid='.$this->api_key.'&keyword='.$word.'&key='.$this->secret_key;
		$sign = strtoupper(md5($sign_str));
		$g_url = C('site_url').'/Api/BwBdzhida/query_keyword/keyword/'.$word.'/apiappid/'.$this->api_key.'/sign/'.$sign;
		//先查询数据库是否有重复的直达词
		$is_exists = M('Bdzd_list')->where(array('app_query'=>$word))->field('id')->select();
		if(count($is_exists) > 1){
            echo json_encode(array('msg'=>'该直达词不可以使用!','error'=>-1));
            die();
        }elseif(count($is_exists) == 1){
            if($is_exists[0]['id'] == $edit_type && !empty($edit_type)){
               	header('Location:'.$g_url);
                exit;
            }else{
                echo json_encode(array('msg'=>'该直达词不可以使用!','error'=>-1));
                exit;
            }
        }else{
            header('Location:'.$g_url);
            exit;
        }
	}
	//查询状态
	public function query_status(){
		$appid = $this->_get('appid','trim,intval');
		$q_data['app_id'] = $appid;
		$q_data['apiappid'] = $this->api_key;
		$sign = $this->create_sign($q_data,$this->secret_key);
		$g_url = C('site_url').'/Api/BwBdzhida/query_status/app_id/'.$appid.'/sign/'.$sign.'/apiappid/'.$this->api_key;
		header('Location:'.$g_url);
		exit;
	}
	//下线直达号
	public function offline_zd(){
		$appid = $this->_get('appid','trim,intval');
		$q_data['modify_app_id'] = $appid;
		$q_data['apiappid'] = $this->api_key;
		$sign = $this->create_sign($q_data,$this->secret_key);
		$g_url = C('site_url').'/Api/BwBdzhida/offline_zhida/modify_app_id/'.$appid.'/sign/'.$sign.'/apiappid/'.$this->api_key;
		header('Location:'.$g_url);
		exit;
	}
	//修改
	public function edit(){
		if(IS_POST){
			$p_flag = $this->_post('v_flag','trim,intval');
			$p_id = $this->_post('id','trim,intval');
			$p_appid = $this->_post('app_id','trim,intval');
			switch ($p_flag) {
				case '3': //修改直达词
					$word = $this->_post('app_query','trim,htmlspecialchars_decode');
					$content_flag = utf8_str($word);
					switch ($content_flag) {
						case 1:
							if(abslength($word) > 20 || abslength($word) < 2){
								$this->error('直达词的长度不在2-20个英文字母/数字');
								exit;
							}
							break;
						case 2:
							if(abslength($word) > 10 || abslength($word) < 2){
								$this->error('直达词的长度不在2-10个汉字');
								exit;
							}
							break;				
						default:
							if(abslength($word) > 10 || abslength($word) < 2){
								$this->error('直达词的长度超过限制');
								exit;
							}
							break;
					}


					$p_k_data['modify_app_id'] = $p_appid;
					$p_k_data['query'] = msubstr($word,0,11);
					$p_k_data['apiappid'] = $this->api_key;
					$p_k_data['edit_flag'] = 3;
					$p_k_data['bd_flag'] = 1;
					$p_k_data['sign'] = $this->create_sign($p_k_data,$this->secret_key);
					$p_k_url = C('site_url').'/Api/BwBdzhida/modify_query_keyword';
					echo $this->buildRequestForm($p_k_data,$p_k_url);
					break;
				case '4'://修改应用信息
					$a_url = $this->_post('app_url','trim,htmlspecialchars_decode');
					$a_name = $this->_post('app_name','trim,htmlspecialchars_decode');
					$a_desc = $this->_post('app_desc','trim,htmlspecialchars_decode');
					$a_logo = $this->_post('app_logo','trim,htmlspecialchars_decode');

					// $a_logo = C('site_url').$_POST['app_logo'];
					if(abslength($a_url) <= 0 || abslength($a_url) > 255){
						$this->error('站点名称:输入内容长度不在1-255个字符内');
						exit;
					}
					if(stripos($a_url, 'http://') === false || stripos($a_url, 'https://')){
						$this->error('站点URL:必须已http://或者https://开头!');
						exit;
					}
					if(abslength($a_name) <= 0 || abslength($a_name) > 15){
						$this->error('站点名称:输入内容长度不在1-15个字符内');
						exit;
					}
					if(abslength($a_desc) < 20 || abslength($a_desc) > 200){
						$this->error('站点简介:输入内容长度不在20-200个字符内');
						exit;
					}
					
					if(stripos($a_logo,C('site_url')) !== false){
						$a_logo = str_replace(C('site_url'), '', $a_logo);
					}
					$m_img_1 = getcwd().str_replace('/', DIRECTORY_SEPARATOR, $a_logo);
					$img_size = ceil(filesize($m_img_1) / 1000) . "k"; //获取文件大小
					if($img_size > 300){
						$this->error('站点logo大小不能大于300K');
						exit;
					}
					if(empty($a_logo)){
						$this->error('站点logo必须不能为空!');
						exit;	
					}
					$logo_info_img = getcwd().$a_logo;
					$logo_info = getimagesize($logo_info_img);
					// print_r($logo_info);exit;
					// $logo_info = getimagesize($a_logo);
					$array_mime = array('image/jpeg','image/png','image/gif');
					if(!in_array($logo_info['mime'],$array_mime)){
					   	$this->error('站点logo格式必须是：png、jpg、gif');
						exit;
					}
					list($width, $height) = $logo_info;
					if($width < 90 && $height < 90){
					   	$this->error('站点logo大小不能小于90x90!');
						exit;
					}
					if($logo_info == false){
			    		$this->error('站点logo不能正常打开!');
			    		exit;
					}

					//重新组装钻铺的url
					if(stripos($a_url,'/Wap/Store') >= 0){
						$ym_url = substr($a_url, 0,stripos($a_url, '/Wap')+1);
						if(preg_match( '/\/token\/([0-9a-z\-\_]+)[\.\/\b]?/i', $a_url,$matchs )){
							$a_url = $ym_url.'Wap/Store/index/token/'.$matchs[1].'.shtml';
						}
					}	
					$p_i_arr['app_url'] = $a_url;
					
					if(!empty($a_name) && isset($a_name)){
						$p_i_arr['app_name'] = msubstr(remove_special_symbols($a_name),0,15);
					}
					if(!empty($a_desc) && isset($a_desc)){
						$p_i_arr['app_desc'] = msubstr($a_desc,0,200);
					}
					if(!empty($a_logo) && isset($a_logo)){
						$p_i_arr['app_logo'] = C('site_url').$a_logo;
					}
					// print_r($p_i_arr);exit;
					$p_i_arr['modify_app_id'] = $p_appid;
					$p_i_arr['apiappid'] = $this->api_key;
					$p_i_arr['edit_flag'] = 4;
					$p_i_arr['bd_flag'] = 1;
					$p_i_arr['sign'] = $this->create_sign($p_i_arr,$this->secret_key);
					$p_k_url = C('site_url').'/Api/BwBdzhida/modify_zhida_info';
					echo $this->buildRequestForm($p_i_arr,$p_k_url);
					break;
				case '5'://修改分类
					$cat_id = $this->_post('qua_cat_id','trim');
					if(empty($cat_id)){
						$this->error('所属行业不能为空!');
					}
					$p_c_data['app_id'] = $p_appid;
					$p_c_data['qua_cat_id'] = $cat_id;
					$p_c_data['apiappid'] = $this->api_key;
					$p_c_data['edit_flag'] = 5;
					$p_c_data['bd_flag'] = 1;
					$p_c_data['sign'] = $this->create_sign($p_c_data,$this->secret_key);
					$p_c_url = C('site_url').'/Api/BwBdzhida/modify_cat';
					echo $this->buildRequestForm($p_c_data,$p_c_url);			
					break;
				case '6'://未提交前的修改
					$validate = $this->valid_data($_POST);
					$_POST['org_licese_expire'] = $validate['org_expire_year'].'-'.$validate['org_expire_month'].'-'.$validate['org_expire_day'];
					$_POST['org_license_img'] = C('site_url').$validate['org_license_img'];
					if(stripos($_POST['org_license_img'],C('site_url')) !== false){
						$_POST['org_license_img'] = C('site_url').str_replace(C('site_url'), '', $_POST['org_license_img']);
					}  
					if(!empty($_REQUEST['org_remark'])){

						foreach ($_REQUEST['org_remark'] as $k_r => $v_r) {
							if(stripos($v_r,C('site_url')) !== false){
								$v_r = C('site_url').str_replace(C('site_url'), '', $v_r);
							}else{
								if(!empty($v_r)){
									$v_r = C('site_url').$v_r;
								}else{
									$v_r = '';
								}
							}
							$tmp_remk[$k_r] = $v_r;
						}
						$_POST['org_remark'] = htmlspecialchars_decode(serialize($tmp_remk));
						
					}
					$_POST['man_legal_over_img'] = C('site_url').$validate['man_legal_over_img'];
					if(stripos($_POST['man_legal_over_img'],C('site_url')) !== false){
						$_POST['man_legal_over_img'] = C('site_url').str_replace(C('site_url'), '', $_POST['man_legal_over_img']);
					}

					$_POST['man_legal_under_img'] = C('site_url').$validate['man_legal_under_img'];

					if(stripos($validate['man_legal_under_img'],C('site_url')) !== false){
						$_POST['man_legal_under_img'] = C('site_url').str_replace(C('site_url'), '', $validate['man_legal_under_img']);
					}

					$_POST['man_legal_ensure'] = C('site_url').$validate['man_legal_ensure'];

					if(stripos($_POST['man_legal_ensure'],C('site_url')) !== false){
						$_POST['man_legal_ensure'] = C('site_url').str_replace(C('site_url'), '', $_POST['man_legal_ensure']);
					}

					$a_logo = C('site_url').$_POST['app_logo'];
					$_POST['apiappid'] = $this->api_key;
					$_POST['p_flag_type'] = 2;
					$_POST['org_private_buiness'] = $validate['org_private_buiness'];
					$_POST['org_expire_long'] = $validate['org_expire_long'];

					$arr['apiappid'] = $this->api_key;
					$a_name = $this->_post('app_name','trim,htmlspecialchars_decode');
					$a_query = $this->_post('app_query','trim,htmlspecialchars_decode');
					$a_url = $this->_post('app_url','trim,htmlspecialchars_decode');
					$a_desc = $this->_post('app_desc','trim,htmlspecialchars_decode');
					$a_cat_id = $this->_post('qua_cat_id','trim');

					$content_flag = utf8_str($a_query);
					switch ($content_flag) {
						case 1:
							if(abslength($a_query) > 20 || abslength($a_query) < 2){
								$this->error('直达词的长度不在2-20个英文字母/数字');
								exit;
							}
							break;
						case 2:
							if(abslength($a_query) > 10 || abslength($a_query) < 2){
								$this->error('直达词的长度不在2-10个汉字');
								exit;
							}
							break;				
						default:
							if(abslength($a_query) > 10 || abslength($a_query) < 2){
								$this->error('直达词的长度超过限制.');
								exit;
							}
							break;
					}
					if(abslength($a_url) <= 0 || abslength($a_url) > 255){
						$this->error('站点名称:输入内容长度不在1-255个字符内');
						exit;
					}
					if(stripos($a_url, 'http://') === false || stripos($a_url, 'https://')){
						$this->error('站点URL:必须已http://或者https://开头!');
						exit;
					}
					if(abslength($a_name) <= 0 || abslength($a_name) > 15){
						$this->error('站点名称:输入内容长度不在1-15个字符内');
						exit;
					}
					if(abslength($a_desc) < 20 || abslength($a_desc) > 200){
						$this->error('站点简介:输入内容长度不在20-200个字符内');
						exit;
					}

					if(stripos($a_logo,C('site_url')) !== false){
						$a_logo = str_replace(C('site_url'), '', $a_logo);
					}
					$m_img_1 = getcwd().str_replace('/', DIRECTORY_SEPARATOR, $a_logo);
					$img_size = ceil(filesize($m_img_1) / 1000) . "k"; //获取文件大小
					if($img_size > 300){
						$this->error('站点logo大小不能大于300K');
						exit;
					}
					if(empty($a_logo)){
						$this->error('站点logo必须不能为空!');
						exit;	
					}
					$logo_info_img = getcwd().$a_logo;
					$logo_info_img = str_replace('/', DIRECTORY_SEPARATOR, $logo_info_img);
					// echo $logo_info_img;
					$logo_info = getimagesize($logo_info_img);
					// print_r($logo_info);exit;
					$array_mime = array('image/jpeg','image/png','image/gif');
					if(!in_array($logo_info['mime'],$array_mime)){
					   	$this->error('站点logo格式必须是：png、jpg、gif');
						exit;
					}
					list($width, $height) = $logo_info;
					if($width < 90 && $height < 90){
					   	$this->error('站点logo大小不能小于90x90!');
						exit;
					}
					if($logo_info == false){
			    		$this->error('站点logo不能正常打开!');
			    		exit;
					}

					if(empty($a_cat_id)){
						$this->error('所属行业不能为空!');
			    		exit;
					}

					$_POST['app_name'] = remove_special_symbols($a_name);
					$_POST['app_query'] = remove_special_symbols($a_query);

					//重新组装钻铺的url
					if(stripos($a_url,'/Wap/Store') >= 0){
						$ym_url = substr($a_url, 0,stripos($a_url, '/Wap')+1);
						if(preg_match( '/\/token\/([0-9a-z\-\_]+)[\.\/\b]?/i', $a_url,$matchs )){
							$a_url = $ym_url.'Wap/Store/index/token/'.$matchs[1].'.shtml';
						}
					}

					$_POST['app_url'] = $a_url;
					$_POST['app_desc'] = $a_desc;
					$_POST['qua_cat_id'] = $a_cat_id == '' ? '248' : $a_cat_id;
					$_POST['userid'] = session('uid');
					$_POST['username'] = session('uname');
					// $_POST['edit_flag'] = 6;

					$arr['app_name'] = remove_special_symbols($a_name);
					$arr['app_query'] = remove_special_symbols($a_query);
					$arr['app_url'] = $a_url;
					// $arr['edit_flag'] = 6;
					

					if(!empty($a_logo) && isset($a_logo)){
						$_POST['app_logo'] = C('site_url').$a_logo;
						$arr['app_logo'] = C('site_url').$a_logo;
					}

					$_POST['sign'] = $this->create_sign($arr,$this->secret_key);
					$_POST['return_url'] = C('site_url').'/User/Bwzhida/return_url';
					$_POST['notify_url'] = C('site_url').'/User/Bwzhida/notify_url';
					$_POST['custom_id'] = trim($_POST['custom_id']);
					$p_a_url = C('site_url').'/Api/BwBdzhida/modify_manage_bdzd';
					ksort($_POST);
					// print_r($_POST);exit;
					echo $this->buildRequestForm($_POST,$p_a_url);

					break;							
				default:
					die('参数有误!');
					break;
			}
		}else{
			$v_flag = $this->_get('v_flag','trim,intval');
			// $app_id = $this->_get('appid','trim');
			$bl_id = $this->_get('id','trim,intval');
			$b_where['id'] = $bl_id;
			// $b_where['apiappid'] = $this->api_key;
			$one_info = M('Bdzd_list')->field('id,app_name,app_logo,app_query,app_url,app_desc,qua_cat_id,app_id,custom_id,org_type,org_name,org_private_buiness,org_license_no,org_license_img,org_licese_expire,org_remark,man_id_verify,man_legal_name,man_legal_type,man_legal_num,man_legal_over_img,man_legal_under_img,man_legal_ensure')->where($b_where)->find();
			$cat_arr_data = include_once(RUNTIME_PATH.'/baidu_cache/bdzd_cat.php');

			foreach ($cat_arr_data as $k_c => $v_c) {
				foreach ($v_c as $k_s => $v_s) {
					if($v_s['id'] == $one_info['qua_cat_id']){
						$cat_str = $k_c.'/'.$v_s['val'];
					}
				}
			}
			$tmp_ser_rem = unserialize(htmlspecialchars_decode($one_info['org_remark']));

			$split_date = explode('-', $one_info['org_licese_expire']);
			$one_info['exp_year'] = $split_date[0];
			$one_info['exp_month'] = $split_date[1];
			$one_info['exp_day'] = $split_date[2];
			$one_info['org_remark'] = $tmp_ser_rem;
			// print_r($one_info);
			$this->assign('remark_count',count($tmp_ser_rem));
			$this->assign('cat_data',$cat_arr_data);
			$this->assign('cat_str',$cat_str);
			$this->assign('v_flag',$v_flag);
			$this->assign('info',$one_info);
			$this->assign('id',$one_info['id']);
			$this->assign('app_id',$one_info['app_id']);
			$this->display('add');
		}
	}
	//删除 
	public function del_zd(){
		$z_id = $this->_get('id','trim,intval');
		$p_page = $this->_get('p','trim,intval');
		$bak_url = U('/User/Bwzhida/index');
		if(isset($p_page))
			$bak_url = U('User/Bwzhida/index',array('p'=>$p_page));
		$d_res = M('Bdzd_list')->where(array('id'=>$z_id))->delete();
		if($d_res){
			$this->success('删除成功!',$bak_url);
		}else{
			$this->error('删除失败!');
		}
	}
	public function buildRequestForm($build_form,$p_url){
			//返回html
			$sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='".$p_url."' method='POST'>";
			while (list ($key, $val) = each ($build_form)) {
	            $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
	        }

			//submit按钮控件请不要含有name属性
	        $sHtml = $sHtml."<input type='submit' style='display:none;' value='提交'></form>";
			
			$sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";
			
			return $sHtml;		
	}
	private function create_sign($data,$apikey){
		ksort($data);
		while (list ($key, $val) = each ($data)) {
			$arg.=$key."=".$val."&";
		}
		$arg = $arg.'key='.$apikey;
		return strtoupper(md5($arg));
	}
	public function return_url(){
		file_put_contents(RUNTIME_PATH.'baidu_cache/bdzd_twwer_success.php', "<?php \nreturn " . stripslashes(var_export($_REQUEST, true)) . ";", FILE_APPEND);
		
		$v_flag = $this->_get('v_flag');
		$v_status = $this->_get('status','trim,intval');
		$v_cus_id = $this->_get('custom_id');
		$v_f_id = $this->_get('id','trim,intval');
		switch ($v_flag) {
			case 'create':
				if($v_status == 1){
                   $this->success('新增成功!',U('/User/Bwzhida/index'));
				}else{
					$this->error('新增失败!',U('/User/Bwzhida/add'));
				}
				break;
			case 'modify':
				if($v_status == 1){
                   $this->success('修改成功!',U('/User/Bwzhida/index'));
				}else{
					$this->error('修改失败!',U('/User/Bwzhida/'));
				}			
				break;
			case 'modify_word'://修改直达词
				if($v_status == 1){
                   $this->success('修改成功!',U('/User/Bwzhida/index'));
				}else{
					$this->error('修改失败!',U('/User/Bwzhida/add'));
				}
				break;
			case 'modify_info'://修改详细信息
				if($v_status == 1){
                   $this->success('修改成功!',U('/User/Bwzhida/index'));
				}else{
					$this->error('修改失败!',U('/User/Bwzhida/add'));
				}
				break;
			case 'modify_bd_cat':////修改分类
				if($v_status == 1){
                   $this->success('修改成功!',U('/User/Bwzhida/index'));
				}else{
					$this->error('修改失败!',U('/User/Bwzhida/edit/',array('id'=>$v_f_id)));
				}
				break;	
			default:
				die('参数错误!');
				break;
		}

		// echo "SUCCESS";
	}
	public function notify_url(){
		file_put_contents(RUNTIME_PATH.'baidu_cache/bdzd_twwer_success.php', "\n<?php \nreturn " . stripslashes(var_export($_REQUEST, true)) . ";", FILE_APPEND);

		// echo "SUCCESS";
	}
	//单独针对没有资质的提交方法
	public function bd_add(){
		if(IS_POST){
			$_POST['apiappid'] = 'XTXUYq5Hpodi2S7mxP';
			$_POST['custom_id'] = mt_rand(1,10000).time();
			$_POST['app_logo'] = C('site_url').$_POST['app_logo'];
			$_POST['org_license_no'] = mt_rand(1,10000).time().mt_rand(1,10000);
			$_POST['email'] = C('bdzd_email');
			$_POST['phone'] = C('bdzd_phone');
			$_POST['createtime'] = time();
			$insert_id = M('Bdzd_list')->add($_POST);
			if($insert_id > 0){
				$this->success('添加成功',U('/User/Bwzhida/bd_add'));
			}else{
				$this->error('添加失败!');
			}
		}else{
			$cat_arr_data = include_once(RUNTIME_PATH.'/baidu_cache/bdzd_cat.php');
			$this->assign('cat_data',$cat_arr_data);
			$this->display();
		}
	}
	//查询多条直达号的状态
	public function query_mul_status(){
		/*$ms_where['apiappid'] = $this->api_key;
		$ms_where['app_id'] = array('neq','');
		$ms_where['app_status'] = 2;
		$all_info = M('Bdzd_list')->field('id,app_id,app_status')->where($ms_where)->find();
		foreach ($all_info as $key => $value) {
			$q_data['app_id'] = $value['app_id'];
			$q_data['apiappid'] = $this->api_key;
			$sign = $this->create_sign($q_data,$this->secret_key);
			$g_url = C('site_url').'/Api/BwBdzhida/query_status/app_id/'.$value['app_id'].'/sign/'.$sign.'/apiappid/'.$this->api_key;
			header('Location:'.$g_url);
		}*/
		$g_url = C('site_url').'/Api/BwBdzhida/get_zhida_status/apiid/'.$this->api_key;
		header('Location:'.$g_url);
	}
	//验证form数据
	public function valid_data($form_arr){
		$org_name = trim($form_arr['org_name']);
		$org_private_buiness = trim($form_arr['org_private_buiness']);
		$org_license_no = trim($form_arr['org_license_no']);
		$org_license_img = trim($form_arr['org_license_img']);
		$org_expire_long = intval(trim($form_arr['org_expire_long']));
		$org_expire_year = trim($form_arr['org_expire_year']);
		$org_expire_month = trim($form_arr['org_expire_month']);
		$org_expire_day = trim($form_arr['org_expire_day']);
		$man_legal_name = trim($form_arr['man_legal_name']);
		$man_legal_num = trim($form_arr['man_legal_num']);
		$man_legal_over_img = trim($form_arr['man_legal_over_img']);
		$man_legal_under_img = trim($form_arr['man_legal_under_img']);
		$man_legal_ensure = trim($form_arr['man_legal_ensure']);
		$man_legal_type = intval(trim($form_arr['man_legal_type']));

		if(empty($org_name) && empty($org_private_buiness)){
			$this->error('企业名称或组织名称不能为空!');
		}
		if(!empty($org_name)){
			if(abslength($org_name) > 100){
				$this->error('企业名称或组织名称字数太长');
			}else{
				$return_arr['org_name'] = $org_name;
			}
		}
		if(array_key_exists('org_private_buiness', $form_arr) && empty($org_name) && !empty($org_private_buiness) && $org_private_buiness == 1){
			$return_arr['org_private_buiness'] = 1;
		}else{
			$return_arr['org_private_buiness'] = 0;
		}
		if(empty($org_license_no)){
			$this->error('营业执照注册号或组织机构代码不能为空!');
		}else{
			$return_arr['org_license_no'] = $org_license_no;
		}
		if(empty($org_license_img)){
			$this->error('营业执照扫描件或组织机构代码证扫描件不能为空!');
		}else{
			$return_arr['org_license_img'] = $org_license_img;
		}
		if(!isset($org_expire_long) || empty($org_expire_long)){
			if(empty($org_expire_year)){
				$this->error('营业执照有效期年份不能为空!');
			}else{
				$return_arr['org_expire_year'] = $org_expire_year;
			}
			if(empty($org_expire_month)){
				$this->error('营业执照有效期月份不能为空');
			}else{
				$return_arr['org_expire_month'] = $org_expire_month;
			}
			if(empty($org_expire_day)){
				$this->error('营业执照有效期日份不能为空!');
			}else{
				$return_arr['org_expire_day'] = $org_expire_day;
			}
		}else{
			$return_arr['org_expire_long'] = 1;
		}
		if(empty($man_legal_name)){
			$this->error('法定代表人姓名或运营者姓名不能为空!');
		}else{
			$return_arr['man_legal_name'] = $man_legal_name;
		}
		if(empty($man_legal_num)){
			$this->error('法定代表人证件号码或运营者证件号码不能为空!');
		}else{
			$return_arr['man_legal_num'] = $man_legal_num;
		}
		if(empty($man_legal_over_img)){
			$this->error('法定代表人或运营者身份扫描件正面不能为空!');
		}else{
			$return_arr['man_legal_over_img'] = $man_legal_over_img;
		}
		if(empty($man_legal_under_img) && $man_legal_type != 4){
			$this->error('法定代表人或运营者身份扫描件反面不能为空!');
		}else{
			$return_arr['man_legal_under_img'] = $man_legal_under_img;
		}
		if(empty($man_legal_ensure)){
			$this->error('保证函或企业授权书不能为空!');
		}else{
			$return_arr['man_legal_ensure'] = $man_legal_ensure;
		}
		
		if(in_array($man_legal_type,array(1,2,3,4))){
			$return_arr['man_legal_type'] = $man_legal_type;
		}else{
			$return_arr['man_legal_type'] = 1;
		}
		return $return_arr;
	}
}
?>