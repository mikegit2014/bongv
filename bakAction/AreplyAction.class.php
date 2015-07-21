<?php
/**
 *关注回复
**/
class AreplyAction extends UserAction{
	private $areply;
	private $img_one;
	private $img_sub;
	private $areid;
	public $token;
	private $all_old;
	private $key;
	public function __construct(){
		parent::__construct();
		$this->key = 'Yes'; 
		$this->areply = D('Areply');
		$this->img_one = D('Img');
		$this->token = $_REQUEST['token'];
		if(empty($this->token)){
			echo "<script>top.location.href='/User/Index/index';</script>";
		}
		$areply = $this->areply->get_areply(array('token'=>$this->token,'keyword'=>$this->key));
		if(empty($areply)){
			$are_data['token'] = $this->token; 
			$are_data['content'] = '欢迎关注!';
			$are_data['uid'] = session('uid');
			// $are_data['uname'] = session('uname');
			$are_data['createtime'] = time();
			$are_data['updatetime'] = time();
			$are_data['keyword'] = $this->key;
			$are_data['home']  = 0;
			$this->areid = $this->areply->add_areply($are_data);
		}else{
			$this->areid = $areply['id'];
		}
		$this->assign('token',$this->token);	
		$this->img_sub = D('Send_message_extra_info');
	}
	public function index(){
		$where['token'] = $this->token;
		$where['keyword'] = $this->key;
		$res = $this->areply->get_areply($where,'id,home,content,token');
		switch ($res['home']) {
			case '2': // 单图文
				$showblock = 2;	
				break;
			case '3': //多图文
				$showblock = 3;
				// $this->assign('show',$showblock);
				// $this->display('auto');				
				break;			
			default: //文本回复
				$showblock = 0;
				// $this->display('auto');
				// $this->assign('show',$showblock);
				// $this->display('auto');
				break;
		}
		//单图文
		$where['flag'] = 1;
		$where['is_mul'] = 1;
		$where['keyword'] = $this->key;
		$data = $this->img_one->find_info($where,'*');
		$data['info'] = html_entity_decode($data['info']);
		$this->assign('info',$data);
		//多图文
		$where['flag'] = 1;
		$where['is_mul'] = 2;
		$where['keyword'] = $this->key;
		$data_first = $this->img_one->find_info($where,'id,pic,info,url,redirecturl,title,author,createtime');
		$data_sub = array();
		if(!empty($data_first['id'])){
			$data_sub = $this->img_sub->get_all(array('send_message_id'=>$data_first['id'],'tmp_flag' => 1),'id,title,author,mediasrc,pic,info,redirecturl','`order` asc');
			$this->all_old = array_keys($data_sub);
		}
		if(count($data_first) > 0){
			$data_first['info'] = html_entity_decode($data_first['info']);
			$this->assign('mulmas',$data_first);
		}
		if(count($data_sub) > 0){
			$this->assign('mulsub',$data_sub);
		}
		$this->assign('mulsubnum',count($data_sub));
		// print_r($data_sub);
		//文本		
		$this->assign('show',$showblock);
		$res['content'] = html_entity_decode($res['content']);
		$this->assign('areply',$res);
		$this->display('auto');	
	}
	public function insert(){
		$where['token'] = $this->token;
		$where['keyword'] = $this->key;
		$res = $this->areply->get_areply($where);
		$txt_content = $this->_post('content','trim');
		// print_r($txt_content);exit;
		$txt_content = msubstr($txt_content,0,600,'utf-8',false);  
		if(empty($txt_content)){
			$this->ajaxReturn(array('errno'=>-5,'error'=>'内容不能为空!'));
			exit;
		}
		if(empty($res)){
			$where['content'] = htmlentities($txt_content,ENT_QUOTES  |  ENT_IGNORE ,  "UTF-8" );		
			if(empty($where['content'])){
				$data_ret['errno'] = -1;
				$data_ret['error'] = '内容不能为空!';
				$data_ret['url'] = '';
				$this->ajaxReturn($data_ret);				
				// $this->error('内容必须填写!',U('Areply/index'));
				exit;
			}
			$where['createtime']=time();
			// $where['uname'] = session('uname');
			$where['uid'] = session('uid');
			$where['home'] = 0;
			$where['keyword'] = $this->key;
			$id = $this->areply->add_areply($where);
			if($id){
			    $this->ajaxReturn(array('errno'=>0,'error'=>'发布成功'));
			    exit;				
				// $this->success('发布成功',U('Areply/index'));
			}else{
			    $this->ajaxReturn(array('errno'=>-2,'error'=>'发布失败'));
			    exit;					
				// $this->error('发布失败',U('Areply/index'));
			}
		}else{
			// $where['id'] = $res['id'];
			// html_entity_decode
			$where['content'] = htmlentities($txt_content,ENT_QUOTES  |  ENT_IGNORE ,  "UTF-8" );
			if(empty($where['content'])){
				$data_ret['errno'] = -1;
				$data_ret['error'] = '内容不能为空!';
				$data_ret['url'] = '';
				$this->ajaxReturn($data_ret);	
				exit;
			}			
			$where['updatetime']=time();
			$where['uid'] = session('uid');
			// $where['uname'] = session('uname');
			$where['home'] = 0;
			if($this->areply->save_flag(array('id'=>$res['id']),$where) !== false ){
				$this->ajaxReturn(array('errno'=>0,'error'=>'发布成功'));
				exit;
				// $this->success('更新成功',U('Areply/index'));
			}else{
				$this->ajaxReturn(array('errno'=>-2,'error'=>'发布失败'));	
				exit;
				// $this->error('更新失败',U('Areply/index'));
			}
		}
	}
	public function areply_img(){
		if(empty($_POST)){
			$this->ajaxReturn(array('errno'=>-5,'error'=>'数据不能为空!'));
			exit;
		}
		$where['token'] = $this->token;
		$where['flag'] = 1;
		$where['is_mul'] = 1;
		$where['keyword'] = $this->key;
		$title = $this->_post('title','trim');
		$author = $this->_post('author','trim');
		$text = $this->_post('summer','trim');
		$pic = $this->_post('cover_addres','trim');
		$info = $this->_post('detail','trim');
		$url = $this->_post('ori_txt','trim,htmlspecialchars_decode');
		$net = $this->_post('net_addres','trim,htmlspecialchars_decode');
		if(empty($title)){
			$this->ajaxReturn(array('errno'=>-1,'error'=>'标题不能为空!'));
			exit;
		}else{
			$img_data['title'] =  msubstr($title,0,64,'utf-8',false);
		}
		if(empty($pic)){
			$this->ajaxReturn(array('errno'=>-1,'error'=>'封面不能为空!'));
			exit;
		}else{
			$img_data['pic'] = $pic;
		}
		if(empty($info) && empty($net)){
			$this->ajaxReturn(array('errno'=>-1,'error'=>'详情页与网络链接必填其一.'));
			exit;
		}
		if(!empty($info) && empty($net)){
			$img_data['info'] = htmlentities(msubstr($info,0,20000,'utf-8',false),ENT_QUOTES  |  ENT_IGNORE ,  "UTF-8" );
			$img_data['url'] = $url;
			$img_data['redirecturl'] = '';
		}
		if(empty($info) && !empty($net)){
			$img_data['redirecturl'] = $net;
			$img_data['info'] = '';
			$img_data['url'] = '';			
		}
		if(!empty($info) && !empty($net)){
			$img_data['info'] = htmlentities(msubstr($info,0,20000,'utf-8',false),ENT_QUOTES  |  ENT_IGNORE ,  "UTF-8" );
			$img_data['url'] = $url;
			$img_data['redirecturl'] = '';
		}
		// if(isset($author) && !empty($author)){
			$img_data['author'] = msubstr($author,0,10,'utf-8',false);
		// }
		// if(isset($text) && !empty($text)){
			$img_data['text'] = htmlentities(msubstr($text,0,200,'utf-8',false),ENT_QUOTES  |  ENT_IGNORE ,  "UTF-8" );
		// }
		$img_data['uptatetime'] = time();
		$img_data['uid'] = session('uid');
		// $img_data['uname'] = session('uname');
		$img_data['token'] = $this->token;
		$img_data['flag'] = 1;
		$img_data['is_mul'] = 1;
		$img_data['keyword'] = $this->key;
		$info = $this->img_one->find_info($where);
		if(empty($info)){
			$img_data['click'] = 0;
			$img_data['createtime'] = time();
			$lineid = $this->img_one->insert_info($img_data);
			$this->areply->save_flag(array('id'=>$this->areid),array('home'=>2));
		}else{
			$where['id'] = $info['id'];
			$lineid = $this->img_one->save_info($where,$img_data);
			$this->areply->save_flag(array('id'=>$this->areid),array('home'=>2));
		}
		if($lineid !== false){
			$this->ajaxReturn(array('errno'=>0,'error'=>'发布成功!'));
			exit;
		}else{
			$this->ajaxReturn(array('errno'=>-2,'error'=>'发布失败!'));
			exit;
		}			
	}
	//自动回复多图文
	public function areply_mul(){
		$params = $_REQUEST['mul_obj'];

		if(empty($params)){
			$this->ajaxReturn(array('errno'=>-5,'error'=>'数据不能为空!'));
			exit;
		}
		// ksort($params);
		//处理提交的数据
		if(count($params) < 2){
			$this->ajaxReturn(array('errno'=>-3,'error'=>'自动回复的多图文最少为两条'));
			exit;
		}
		if(count($params) > 8){
			$this->ajaxReturn(array('error'=>-3,'error'=>'自动回复的多图文最多为8条!='));
			exit;
		}
		// for($i = 0;$i < count($params);$i++)
		$i = 0;
		foreach ($params as $key => $value){
			$j = $i + 1;
			if(empty($value['mul_title']) && isset($value['mul_title'])){
				$this->ajaxReturn(array('errno'=>-1,'error'=>'第'.$j.'个图文的标题不能为空!'));
				exit;
			}else{
				$mul_data[$i]['title'] = msubstr(trim($value['mul_title']),0,64,'utf-8',false);
			}
			if(empty($value['mul_addres']) && isset($value['mul_addres'])){
				$this->ajaxReturn(array('errno'=>-1,'error'=>'第'.$j.'个图文的封面不能为空!'));
				exit;
			}else{
				$mul_data[$i]['pic'] = $value['mul_addres']; 
			}
			if(empty($value['mul_detail']) && isset($value['mul_detail']) && empty($value['mul_reback']) && isset($value['mul_reback'])){
				$this->ajaxReturn(array('errno'=>-1,'error'=>'第'.$j.'个图文的详情页与网络链接必填其一.'));
				exit;
			}
			if(!empty($value['mul_detail']) && empty($value['mul_reback'])){
				$mul_data[$i]['info'] = htmlentities(msubstr(trim($value['mul_detail']),0,20000,'utf-8',false),ENT_QUOTES  |  ENT_IGNORE ,  "UTF-8" );
				$mul_data[$i]['url'] = htmlspecialchars_decode(trim($value['mul_ori']));
				$mul_data[$i]['redirecturl'] = '';
			}
			if(empty($value['mul_detail']) && !empty($value['mul_reback'])){
				$mul_data[$i]['info'] = '';
				$mul_data[$i]['url'] = '';
				$mul_data[$i]['redirecturl'] = htmlspecialchars_decode(trim($value['mul_reback']));
			}
			// if(!empty($value['mul_author'])){
				$mul_data[$i]['author'] = msubstr(trim($value['mul_author']),0,10,'utf-8',false); 
			// }
			if(!empty($value['mul_detail']) && !empty($value['mul_reback'])){
				$mul_data[$i]['info'] = htmlentities(msubstr(trim($value['mul_detail']),0,20000,'utf-8',false),ENT_QUOTES  |  ENT_IGNORE ,  "UTF-8" );
				$mul_data[$i]['url'] = htmlspecialchars_decode(trim($value['mul_ori']));
				$mul_data[$i]['redirecturl'] = '';
			}
			$i++;
		}
		$mul_res_line = $this->mul_save_info($mul_data);
		if($mul_res_line == count($params)){
			$this->ajaxReturn(array('errno'=>0,'error'=>'发布成功!'));
			exit;
		}else{
			$this->ajaxReturn(array('errno'=>-2,'error'=>'发布失败!'));
			exit;
		}
	}
	//自动回复多图文入库
	private function mul_save_info($data){
		$mul_where['token'] = $this->token;
		$mul_where['is_mul'] = 2;
		$mul_where['flag'] = 1;		
		$mul_where['keyword'] = $this->key;		
	

		$mul_data['uid'] = session('uid');
		// $mul_data['uname'] = session('uname');
		$mul_data['token'] = $this->token;
		$mul_data['is_mul'] = 2;
		$mul_data['flag'] = 1;
		
		$first_data = array_shift($data);
		$mul_data = array_merge($mul_data,$first_data);
		
		$mul_id = $this->img_one->find_info($mul_where,'id,click');
		$total_line = 0;
		if(empty($mul_id)){//新增
			$mul_data['createtime'] = time();
			$mul_data['uptatetime'] = time();
			$mul_data['click'] = 0;	
			$mul_data['keyword'] = $this->key;		
			//插入主图文
			$mul_insert_id = $this->img_one->insert_info($mul_data);
			// $mul_insert_id = 209;
			if($mul_insert_id > 0){
				$total_line += 1;
				$this->areply->save_flag(array('id'=>$this->areid),array('home'=>3));
				//插入子图文
				$new_line = $this->sub_insert($data,$total_line,$mul_insert_id);
			}
			return $new_line;
		}else{//更新
			$mul_conditon['id'] = $mul_id['id'];
			$mul_data['uptatetime'] = time();
			$mul_data['click'] = $mul_id['click'];		
			$mul_line_id = $this->img_one->save_info($mul_conditon,$mul_data);
			$up_line = 0;
			//开启事务
			$this->img_one->startTrans();
			if($mul_line_id !== false){
				$up_line += 1;
				$this->areply->save_flag(array('id'=>$this->areid),array('home'=>3));
				$this->img_sub->save_info(array('send_message_id'=>$mul_id['id']),array('tmp_flag'=>'-1'));
				$new_line = $this->sub_insert($data,$up_line,$mul_id['id']);
				if($new_line >= 1){
					$this->img_sub->del_info(array('send_message_id'=>$mul_id['id'],'tmp_flag'=>'-1'));
				}
				//提交事务
				$this->img_one->commit();
			}else{
				//回滚事务
				$this->img_one->rollback();
			}
			return $new_line;
		}
	}
	//子图文入库公用
	private function sub_insert($sub_data,&$line,$firstid){
		// $total_line = 0;
		$xh = 1; //子图文排序
		foreach ($sub_data as $key => $value) {
			$mul_sub_data['send_message_id'] = $firstid;
			$mul_sub_data['create_time'] = time();
			$mul_sub_data['order'] = $xh;
			$mul_sub_data['tmp_flag'] = 1;
			$t_title = trim($value['title']);
			$t_author = trim($value['author']); 
			$t_pic = trim($value['pic']);
			$t_info = trim($value['info']);
			$t_redirect = trim($value['redirecturl']);
			if(!empty($t_title))
				$mul_sub_data['title'] = $t_title;
			// if(!empty($t_author))
				$mul_sub_data['author'] = $t_author;
			if(!empty($t_pic))
				$mul_sub_data['pic'] = $t_pic;
			if(!empty($t_info) && empty($t_redirect)){
				$mul_sub_data['info'] = $t_info;
				$mul_sub_data['mediasrc'] = trim($value['url']);
				$mul_sub_data['redirecturl'] = '';
			}
			if(!empty($t_redirect) && empty($t_info)){
				$mul_sub_data['info'] = '';
				$mul_sub_data['mediasrc'] = '';					
				$mul_sub_data['redirecturl'] = $t_redirect;
			}
			if(!empty($t_redirect) && !empty($t_info)){
				$mul_sub_data['info'] = $t_info;
				$mul_sub_data['mediasrc'] = trim($value['url']);
				$mul_sub_data['redirecturl'] = '';
			}
			$mul_sub_id = $this->img_sub->insert_info($mul_sub_data);
			// echo $this->img_sub->getlastsql()."<br/>";
			if($mul_sub_id > 0){
				$line += 1;
			}
			$xh++;
		}
		return $line;	
	}
}
?>