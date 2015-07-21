<?php
class DiymenAction extends UserAction {
	public $thisWxUser;
	public $token;
	public $t_id;
	public function _initialize() {
		parent::_initialize ();
		$this->token = trim($_REQUEST['token']);
		$this->saveInfo = D("Diymen_saveinfo");
		if(empty($this->token)){
			$this->error('参数为空!',U('User/Index/info'));
			exit;
		}
		$where['token'] = $this->token;
		$this->thisWxUser = M ( 'Wxuser' )->where ( $where )->field('id,winxintype,appid,appsecret')->find ();
			
		if(empty($this->thisWxUser)){
			$this->error('参数错误',U('User/Index/info'));
			exit;
		}
				
		if($this->thisWxUser['winxintype'] == 1){
			$this->error('未认证的订阅号,没有自定义菜单的权限',U('/User/Function/info',array('token'=>$this->token,'id'=>$this->thisWxUser['id'])));
			exit;
		}
		/*
		if (empty($this->thisWxUser['appid']) || empty($this->thisWxUser['appsecret'])) {
			$this->error('请填写appid和appsecret',U('/User/Index/edit',array('token'=>$this->token,'id'=>$this->thisWxUser['id'])));
		}
		*/
		
		$this->t_id = $this->thisWxUser['id'];
		$this->assign('id',$this->t_id);
		$this->assign('token',$this->token);
	}
	
	
	/*
	 * 生成效果
	*/
	public function showEffect(){
	
		$mainMenu = D("Diymen_menu")->where("parentid=0 and token='".$this->token."'")->order(" sort asc")->select();
		$menuAll = array();
			
		foreach($mainMenu as $v){
			if($v['pid']==0){
				$v["pid"] = $v["url"];
			}
			$menuChild["parent"] = $v;
			$child = D("Diymen_menu")->where("parentid=".$v["id"]." and token='".$this->token."'")->order(" sort desc")->select();
			$childArr = array();
			foreach($child as $p){
				$p["pid"] = $p["pid"] ? $p["pid"] : $p["url"];
				$childArr[] = $p;
			}
			$menuChild["child"] = $childArr;
			$menuChild["count"] = count($childArr);
	
			$menuAll[] = $menuChild;
	
		}
		$menuCount = count($menuAll);	
		$this->assign("menuAll",$menuAll);
		$this->assign("menuCount",$menuCount);
		$this->display("showEffect");
	}
	
	/*
	 生成自定义菜单
	*/
	public function menu_create(){
	
		if (IS_GET) {
				
			if(empty($this->thisWxUser['appsecret'])){
				
				//使用微信开放平台上的绑定接口,没有appsercet;
				$bind = A('User/Bind');
				$tmp_token = $bind->return_token($this->thisWxUser['appid']);
			}else{
				$url_get = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->thisWxUser ['appid'] . '&secret=' . $this->thisWxUser ['appsecret'];
				$json = json_decode ( $this->curlGet ( $url_get ) );
			}
				
			if (!empty($json->errcode)) {
				$this->error ( '获取access_token发生错误：错误代码'.$json->errcode);
			}
	
			/*
			 * 查询主菜单
			*  */
			$data = '{"button":[';
			$mainWhere = array ('tp_diymen_menu.token' => $this->token,'tp_diymen_menu.parentid' => 0);
				
			$class = M ("Diymen_menu")->join(' tp_diymen_saveinfo Ds on tp_diymen_menu.pid=Ds.id')->field('tp_diymen_menu.menuname,tp_diymen_menu.url,Ds.keyword,tp_diymen_menu.id')->where ($mainWhere)->order ( 'tp_diymen_menu.sort asc' )->select();
				
			$kcount = count($class);
			if($kcount>3){
				$this->error("一级菜单不能超过3个");
				exit;
			}
			$k = 1;
			foreach ( $class as $key => $vo ) {
	
				$data .= '{';
				/*
				 * 查询该主菜单做包含的子菜单
				*/
				$childWhere =  array ('tp_diymen_menu.token' => $this->token,'tp_diymen_menu.parentid' => $vo ['id']);
	
				$c = M ("Diymen_menu")->join(' tp_diymen_saveinfo Ds on tp_diymen_menu.pid=Ds.id')->field('tp_diymen_menu.menuname,tp_diymen_menu.url,Ds.keyword')->where ($childWhere )->limit(5)->order ( 'tp_diymen_menu.sort desc' )->select();
	
				$count = M ("Diymen_menu")->join(' tp_diymen_saveinfo Ds on tp_diymen_menu.pid=Ds.id')->field('tp_diymen_menu.menuname,tp_diymen_menu.url,Ds.keyword')->where ($childWhere )->limit(5)->count();
			
				// 子菜单
				$vo ['url'] = str_replace (array('&amp;'), array ('&'), $vo ['url'] );
	
				if($c){
					$data .= '"name":"' . $vo ['menuname'] . '","sub_button":[';
				}else{
					$data .= $vo ['url'] ? '"type":"view","name":"' . $vo ['menuname'] . '","url":"' . $vo ['url'] . '"' : '"type":"click","name":"' . $vo ['menuname'] . '","key":"' . $vo ['keyword'] . '"';
					
				}
				$i = 1;
				foreach ( $c as $voo ) {
					$voo ['url'] = str_replace (array('&amp;'), array ('&'), $voo ['url']);
						
					if ($i == $count) {
						if ($voo ['url']) {
							$data .= '{"type":"view","name":"' . $voo ['menuname'] . '","url":"' . $voo ['url'] . '"}';
						} else {
							$data .= '{"type":"click","name":"' . $voo ['menuname'] . '","key":"' . $voo ['keyword'] . '"}';
						}
					} else {
						if ($voo ['url']) {
							$data .= '{"type":"view","name":"' . $voo ['menuname'] . '","url":"' . $voo ['url'] . '"},';
						} else {
							$data .= '{"type":"click","name":"' . $voo ['menuname'] . '","key":"' . $voo ['keyword'] . '"},';
						}
					}
					$i ++;
				}
				if ($c != false) {
					$data .= ']';
				}
					
				if ($k == $kcount) {
					$data .= '}';
				} else {
					$data .= '},';
				}
				$k ++;
			}
				
			$data .= ']}';
				
				
			if(empty($json) && !empty($tmp_token)){
							
				$new_token = $tmp_token['authorizer_access_token'];
				file_get_contents ( 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=' . $new_token );
				$url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $new_token;	
			}else{				
				file_get_contents ( 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=' . $json->access_token );
				$url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $json->access_token;
			}
					
			$rt = $this->api_notice_increment ( $url, $data );			
			if ($rt ['rt'] == false) {
				$this->error ( '操作失败,curl_error:' . $rt ['errorno'] );
			} else {
				$this->success ( '操作成功' );
			}
			exit ();
		}else{
			$this->error("非法操作");
		}
	}
	
	/* 数组倒序 */
	public function sortMenu($infoName){
	
		$number = count($infoName);
		if($number>=1){
			$newMenu = array();
			for($i=$number-1;$i>=0;$i--){
				$newMenu[] = $infoName[$i];
			}
		}
		return $newMenu;
	}
	/*
	 * 合并菜单名和菜单素材
	*/
	public function merageArr($menuName,$material,$num){
		global $id;
		$menu = array();
		$j = 0;
		foreach($menuName as $k=>$v){			
			$bool = is_numeric($material[$k]);
			if($k==0){
				if($bool){
					$t = array("menuname"=>$v,"pid"=>$material[$k],"parentid"=>0,"sort"=>$num,"token"=>$this->token);
				}else{
					$t = array("menuname"=>$v,"url"=>$material[$k],"parentid"=>0,"sort"=>$num,"token"=>$this->token);					
				}
	
				$id = D("Diymen_menu")->add($t);
			}else{
				$j++;				
				if($bool){
					$t = array("menuname"=>$v,"pid"=>$material[$k],"parentid"=>$id,"sort"=>$j,"token"=>$this->token);					
				}else{
					$t = array("menuname"=>$v,"url"=>$material[$k],"parentid"=>$id,"sort"=>$j,"token"=>$this->token);
				}	
				D("Diymen_menu")->add($t);
			}
		}
	}
	
	/* 
	 * 限制菜单名称长度
	 */
	public function menuLen($menuName,$str){
		foreach($menuName as $key=>$name){
			$len = $this->strlen($name);
			if($key==0){
				if($len<1 || $len>8){
					$this->error("第".$str."个一级菜单的名称不符合规则","/User/Diymen/main_menu/token/".$this->token);
					///$this->success("添加成功","/User/Diymen/main_menu/token/".$this->token)
					exit;
				}	
			}else if($len<1 || $len>16){				
				$this->error("第".$str."个一级菜单中的二级菜单的名称不符合规则","/User/Diymen/main_menu/token/".$this->token);
				exit;					
			}
		}
		return true; 
	}
	
	/*
	 * 添加菜单
	*/
	public function add_menu(){
		
		$menuName= $_REQUEST['infoName'];
		$materia = $_REQUEST['materia'];
			
		$xh = 0;
		foreach($menuName as $key=>$val){
			$menuNameAll[$xh] = $val;
			$materialAll[$xh] = $materia[$key];		
			$xh++;
		}
				
		//组装第一个一级菜单
		$menuName1 = $this->sortMenu($menuNameAll[0]);		
		$material1 = $this->sortMenu($materialAll[0]);
				
		//限制第一个一级菜单及其子菜单菜单名称长度
		$this->menuLen($menuName1,"一");
		
		//组装第二个一级菜单
		$menuName2 = $this->sortMenu($menuNameAll[1]);
		$material2 = $this->sortMenu($materialAll[1]);
		
		
		//限制第二个一级菜单及其子菜单菜单名称长度
		$this->menuLen($menuName2,"二");
	
		//组装第三一级菜单
		$menuName3 = $this->sortMenu($menuNameAll[2]);
		$material3 = $this->sortMenu($materialAll[2]);
				
		//限制第三个一级菜单及其子菜单菜单名称长度
		$this->menuLen($menuName3,"三");
	
		//清除此公众号菜单信息
		D("Diymen_menu")->where("token='".$this->token."'")->delete();
					
		//插入第一个一级菜单及其子菜单的数据
		$this->merageArr($menuName1,$material1,$t=1);
		//插入第二个一级菜单及其子菜单的数据
		$this->merageArr($menuName2,$material2,$t=2);		
		//插入第三个一级菜单及其子菜单的数据
		$this->merageArr($menuName3,$material3,$t=3);
					
		$this->success("添加成功","/User/Diymen/main_menu/token/".$this->token);
		
	}
	public function editmenu(){
		$id = $this->_post("id");			
		$saveInfo = D("Diymen_saveinfo")->where("id=".$id)->find();
		$str = '';	
		switch ($saveInfo["type"]){
			case 'text':
				$str .=	"<li class='post'>";
				$str .=	"<div class='preview'>";
				$str .=	"<div class='w270 sh-div'>";
				$str .=	"<div class='preview-con title_p te-center'><span>";
				$str .=	"<span>";
				$str .=	"<p id='text_info'>".$saveInfo['info']."</p>";
				$str .=	"<div class='border-g'>";
				$str .=	"<a id='textedit'  href=\"javascript:edit('text',".$saveInfo['id'].");\" class='edit-img'></a>";
				$str .=	 "<a href=\"javascript:frame_delete('text');\" class='delete-img'></a>";
				$str .=	 "</div>";
				$str .=  "</span>";
				$str .=	  "</div>";
				$str .= "</div>";
				$str .= "</div>";
				$str .=	 "</li>";
				$str .= "|text";
				echo $str ;
				break;
			case 'imgtext':
				$str .=  "<li class='post'>";
				$str .= "<div class='preview'>";
				$str .= "<div class='w270'>";
				$str .= "<div class='preview-con title_p te-center'>";
				$str .= "<h1 class='title-h'  id='img_text_title'>".$saveInfo['title']."</h1>";
				$str .=  "<a href='javascript:void(0);'  id='img_text_pic'><img src='".$saveInfo["pic"]."'></a>";
				$str .=  "<p id='img_text_info'>".$saveInfo['info']."</p>";
				$str .= "<div class='border-g'>";
				$str .=	 "<a id='textImgedit' href=\"javascript:edit('imgtext',".$saveInfo['id'].");\" class='edit-img'></a>";
				$str .=  "<a href=\"javascript:frame_delete('imgtext');\" class='delete-img'></a>";
				$str .= "</div>";
				$str .=  "</div>";
				$str .=	 "</div>";
				$str .=   "</div>";
				$str .=  "</li>";
				$str .= "|imgtext";
				echo $str;
				break;
			case 'imageTexts':
				$str = $this->textImgShow($id);
				$strArr = explode("|",$str);
				$strs = $strArr[0]."|imageTexts";
				echo $strs;
				break;
			case 'picture':
				$str .="<li class='post'>";
				$str .= "<div class='preview'>";
				$str .= "<div class='w270'>";
				$str .= "<div class='preview-con title_p te-center'>";
				$str .= "<h1 class='title-h' id='img_title'>".$saveInfo['title']."</h1>";
				$str .= "<a href='javascript:void(0);' id='img_pic'><img src='".$saveInfo['pic']."'></a>";
				$str .= "<div class='border-g'>";
				$str .= "<a id='pictureEdit' href=\"javascript:edit('picture',".$saveInfo['id'].");\" class='edit-img'></a>";
				$str .= "<a href=\"javascript:frame_delete('picture');\" class='delete-img'></a>";
				$str .= "</div>";
				$str .= "</div>";
				$str .= "</div>";
				$str .= "</div>";
				$str .= "</li>";
				$str .= "|picture";
				echo $str;
				break;
			case 'voice':
				$str .= "<li class='post'>";
				$str .= "<div class='preview'>";
				$str .= "<div class='w270'>";
				$str .= "<div class='preview-con title_p te-center'>";
				$str .= "<div style='min-height:150px;line-height:25px; padding:10px;text-align:left;'><h1 id='voice_title' class='title-h'>".$saveInfo['title']."</h1></div>";
				$str .=  "<div class='border-g'>";
				$str .=  "<a id='voiceEdit' href=\"javascript:edit('voice',".$saveInfo['id'].");\" class='edit-img'></a>";
				$str .=  "<a href=\"javascript:frame_delete('voice');\" class='delete-img'></a>";
				$str .=  "</div>";
				$str .= "</div>";
				$str .= "</div>";
				$str .= "</div>";
				$str .= "</li>";
				echo $str."|voice";
				break;
		}
	
	}
	
	
	/*
	 * 计算字符串长度
	*/
	public function strLen($str){
		$length = mb_strlen($str,'utf-8');
		$count =0;
		for($i=0;$i<$length;$i++){
			if(ord(mb_substr($str, $i, 1, 'utf-8'))> 0xa0){
				$count += 2;
			}else{
				$count += 1;
			}
		}
		return $count;
	}
	/* 
	 * 多图文入库前处理
	 */
	public function saveBeforeEdit($params,$keyword){
		if(count($params) > 8){
			$this->error('自动回复的多图文最多为8条!');
			exit;
		}
		$i = 0;
		foreach ($params as $key => $value){
			$j = $i + 1;
				
			if(empty($value['mul_title'])){
				$this->error('第'.$j.'个图文的标题不能为空!');
				exit;
			}else{
					
				$mul_data[$i]['title'] = msubstr(trim($value['mul_title']),0,64,'utf-8',false);
			}
			if(empty($value['mul_addres'])){
				$this->error('第'.$j.'个图文的封面不能为空!');
				exit;
			}else{
					
				$mul_data[$i]['pic'] = $value['mul_addres'];
			}
			if( empty($value['mul_detail']) && empty($value['mul_reback']) ){
				$this->error('第'.$j.'个图文的详情页与网络链接必填其一.');
				exit;
			}
				
			$mul_data[$i]['token'] = $this->token;
			$mul_data[$i]['keyword'] = $keyword;
			$mul_data[$i]['updatetime'] = time();
			$mul_data[$i]['author'] = msubstr(trim($value['mul_author']),0,10,'utf-8',false);
			$mul_data[$i]['type'] = $this->saveInfo->getType(4);
	
			if(!empty($value['mul_detail']) && empty($value['mul_reback'])){
				$mul_data[$i]['detailedinfo'] = htmlentities(msubstr(trim($value['mul_detail']),0,20000,'utf-8',false),ENT_QUOTES,"UTF-8" );
				$mul_data[$i]['originalUrl'] = trim($value['mul_ori']);
				$mul_data[$i]['skipUrl'] = '';
			}
	
			if(empty($value['mul_detail']) && !empty($value['mul_reback'])){
				$mul_data[$i]['detailedinfo'] = '';
				$mul_data[$i]['originalUrl'] = '';
				$mul_data[$i]['skipUrl'] = trim($value['mul_reback']);
			}
	
			if(!empty($value['mul_detail']) && !empty($value['mul_reback'])){
				$mul_data[$i]['detailedinfo'] = htmlentities(msubstr(trim($value['mul_detail']),0,20000,'utf-8',false),ENT_QUOTES,"UTF-8" );
				$mul_data[$i]['originalUrl'] = trim($value['mul_ori']);
				$mul_data[$i]['skipUrl'] = '';
			}
			$i++;
		}
		return $mul_data;
	}
	/* 
	 * 文本列表页
	 */
	public function textList(){
	
		$textDiymenArr = M("diymen_saveinfo")->where("pid=0 and type='text' and token='".$this->token."'")->select();
		$this->assign("textDiymenArr",$textDiymenArr);
	
		$this->display("textList");
	}
	/*
	 * 单图文列表页
	*/
	public function textImgList(){
		$textImgArr = M("Diymen_saveinfo")->where("pid=0 and type='imgtext' and token='".$this->token."'")->select();
		$this->assign("textImgList",$textImgArr);
		$this->display("textImgList");
	}
	/* 
	 * 多图文列表页
	 */
	public function textImgsList(){
		$textImgArr = M("Diymen_saveinfo")->where("pid=0 and type='imageTexts' and token='".$this->token."'")->select();
	
		$this->assign("textImgList",$textImgArr);
		$imgTextsInfo = array();
		foreach($textImgArr as $v){
			$p_one["parent"] = $v;
			$image_where= array("pid"=>$v['id']);
			$imageTextChild = D("Diymen_saveinfo")->where($image_where)->order(" childorder asc")->select();
			$p_one["child"] =  $imageTextChild;
			$imgTextsInfo[] = $p_one;
		}
	
		$this->assign("imgTextsInfo",$imgTextsInfo);
		$this->display("textImgsList");
	}
	/* 
	 * 图片列表页
	 */
	public function imgList(){
		$ImgArr = M("Diymen_saveinfo")->where("pid=0 and type='picture' and token='".$this->token."'")->select();
		$this->assign("ImgList",$ImgArr);
		$this->display("imgList");
	}
	/* 语音列表页 */
	public function voiceList(){
		$voiceArr = M("Diymen_saveinfo")->where("pid=0 and type='voice' and token='".$this->token."'")->select();
		$this->assign("voiceList",$voiceArr);
		$this->display("voiceList");
	}
	/* 编辑多图文 */
	public function editImgTexts(){
		if(IS_POST){
			$keyword = $_POST["keyword"];
			$id = $_POST["id"];
				
			$params = $_REQUEST['mul_obj'];
				
			$mul_data = $this->saveBeforeEdit($params,$keyword);
				
			$frist_data = array_shift($mul_data);
			//更新
			$this->saveInfo->editFristOne($frist_data,$id);
			//删除字图文
			$this->saveInfo->delSonImags($id);
			//重新插入字图文
			$total_line = 0;
			if($id){
				$total_line += 1;
				//插入子图文
				$new_line = $this->sub_insert($mul_data,$total_line,$id);
					
				if($new_line == count($params)){
						
					$str = $this->textImgShow($id);
					echo $str;
					exit;
				}else{
					$this->error('修改失败!');
					exit;
				}
			}
				
				
		}else{
			$imgTextsId = $this->_get("imgTextsId");
			//详细内容
			$allArr = $this->saveInfo->selectAllInfo($imgTextsId,$this->token);
			$arrTotal = $allArr;
			//左侧第一张图片
			$mulmas =  array_shift($arrTotal);
			//标志Id
			$number = count($allArr) - 1;
				
			$this->assign("number",$number);
				
			$this->assign("mulmas",$mulmas);
				
			//其余图片
			$this->assign("mulsub",$arrTotal);
	
			$this->assign("allArr",$allArr);
		}
		$this->display("editImgTexts");
	}
	
	//添加多图文
	public function areply_mul(){
		$params = $_REQUEST['mul_obj'];
		$mul_data = $this->saveBeforeEdit($params);
		$mul_res = $this->mul_save_info($mul_data);
		$mul_res = explode("|",$mul_res);
		$mul_res_line = $mul_res[0];
		$id = $mul_res[1];
	
		if($mul_res_line == count($params)){
	
			$str = $this->textImgShow($id);
			echo $str;
			exit;
		}else{
			$this->error('发布失败!');
			exit;
		}
	}
	/*
	 * 组装多图文显示
	*/
	public function textImgShow($lastId){
	
		$imagetextsInfo = D("Diymen_saveinfo")->where("id=".$lastId)->find();
		$this->assign("textId",$imagetextsInfo['id']);
		$p_one["parent"] = $imagetextsInfo;
		$image_where= array("pid"=>$imagetextsInfo['id']);
		$imageTextChild = D("Diymen_saveinfo")->where($image_where)->order(" childorder asc")->select();
		$p_one["child"] =  $imageTextChild;
	
		$str = '';
		$str .= "<li class='post'><div class='preview'><div class='w270'><div class='preview-con title_p te-center'>";
		$str .= "<input type='hidden' class='check-input' id='imgtexts_id_29' value='29' name='introduction' style='display: inline;'>";
		$str .= "<h1 class='title-h'>".$p_one['parent']['title']."</h1>";
		$str .= "<a href='#'>";
		$str .= "<img src='".$p_one['parent']['pic']."' class='more-pic-text'>";
		$str .= "</a>";
		foreach($p_one['child'] as $list){
			$str .= "<div class='bg-a-s'>";
			$str .= "<h2 class='fl'>".$list['title']."</h2>";
			$str .= "<a class='fr' href='javascript:;'>";
			$str .= "<img src='".$list['pic']."'. class='more-pic-text-small'>";
			$str .= "</a>";
			$str .= "</div>";
		}
	
		$str .= "<div class='border-g'>";
		$str .="<div></div>";
		$str .= "<a href='javascript:editImgTexts(".$p_one['parent']['id'].");' class='edit-img'></a>";
		$str .= "<a href=\"javascript:frame_delete('imagetexts');\" class='delete-img'></a>";
		$str .= "</div>";
		$str .= "</div>";
		$str .= "</div>";
		$str .= "</div>";
		$str .= "</li>";
		$str .= "|".$lastId;
		return $str;
	}
	
	public function conTextimgs(){
		$lastId = $this->_post("Id");
		$str = $this->textImgShow($lastId);
		echo $str;
	}
	
	//多图文入库
	private function mul_save_info($data){
	
		/*检测关键词是否重复tp_keyword */
	
		$keyword_str = $this->saveInfo->getDiymenKeyWord();
		$keyWordCondition['token'] = $this->token;
		$keyWordCondition['keyword'] = $keyword_str;
		$res = M('Keyword')->where($keyWordCondition)->find();
		if($res){
			$this->error("同一IP请求过于频繁");
			exit;
		}
	
		/* 获取多图文中主图文信息 */
		$mul_data['token'] = $this->token;
		$mul_data['updatetime'] = time();
		$mul_data['createtime'] = time();
		$first_data = array_shift($data);
		$mul_data = array_merge($mul_data,$first_data);
		$mul_data["keyword"] = $keyword_str;
		//插入主图文
		$mul_insert_id = $this->saveInfo->insert_info($mul_data);
	
		/*
		 * 关键词插入向tp_keyword
		*/
		$dataKeyWord["pid"] = $mul_insert_id;
		$dataKeyWord["absolute"] = 1;
		$dataKeyWord["module"] = MODULE_NAME."_imgtexts";
		$dataKeyWord["token"] = $this->token;
		$dataKeyWord["keyword"] = $keyword_str;
		$keywordId = M('Keyword')->add($dataKeyWord);
	
		$total_line = 0;
		if($mul_insert_id && $keywordId){
			$total_line += 1;
			//插入子图文
			$new_line = $this->sub_insert($data,$total_line,$mul_insert_id);
		}
		return $new_line."|".$mul_insert_id;
	}
	
	//子图文入库公用
	private function sub_insert($sub_data,&$line,$firstid){
		// $total_line = 0;
		$xh = 1; //子图文排序,入库
		foreach ($sub_data as $key => $value) {
				
			$mul_sub_data['token'] = $this->token;
			$mul_sub_data['pid'] = $firstid;
			$mul_sub_data['type'] = $this->saveInfo->getType(4);
			$mul_sub_data['childorder'] = $xh;
			$mul_sub_data['updatetime'] = $mul_sub_data['createtime'] = time();
			$mul_sub_data['keyword'] = $this->saveInfo->getDiymenKeyWord();
			$mul_sub_data['title'] = trim($value['title']);
			$mul_sub_data['author'] = trim($value['author']);
			$mul_sub_data['pic'] = trim($value['pic']);
				
			$t_info = trim($value['detailedinfo']);
			$t_redirect = trim($value['skipUrl']);
	
			if(!empty($t_info) && empty($t_redirect)){
				$mul_sub_data['detailedinfo'] = $t_info;
				$mul_sub_data['originalUrl'] = trim($value['originalUrl']);
				$mul_sub_data['skipUrl'] = '';
			}
			if(!empty($t_redirect) && empty($t_info)){
				$mul_sub_data['detailedinfo'] = '';
				$mul_sub_data['originalUrl'] = '';
				$mul_sub_data['skipUrl'] = $t_redirect;
			}
			if(!empty($t_redirect) && !empty($t_info)){
				$mul_sub_data['info'] = $t_info;
				$mul_sub_data['mediasrc'] = trim($value['originalUrl']);
				$mul_sub_data['redirecturl'] = '';
			}
	
			$mul_sub_id = $this->saveInfo->insert_info($mul_sub_data);
				
			if($mul_sub_id > 0){
				$line += 1;
			}
			$xh++;
		}
		return $line;
	}
	/* 
	 * 删除多图文记录
	 */
	public function delImgTexts(){
	
		$deleteId = $this->_get('deleteId');
		if($deleteId){
			$this->saveInfo->where("id='".$deleteId."' or pid='".$deleteId."'")->delete();
			M("Keyword")->where("pid='".$deleteId."' and token='".$this->token."' and module='".MODULE_NAME."_imgtexts'")->delete();
			$textImgArr = M("Diymen_saveinfo")->where("pid=0 and type='imageTexts' and token='".$this->token."'")->select();
			$this->assign("textImgList",$textImgArr);
			$imgTextsInfo = array();
			foreach($textImgArr as $v){
				$p_one["parent"] = $v;
				$image_where= array("pid"=>$v['id']);
				$imageTextChild = D("Diymen_saveinfo")->where($image_where)->order(" childorder asc")->select();
				$p_one["child"] =  $imageTextChild;
				$imgTextsInfo[] = $p_one;
			}
			$this->assign("imgTextsInfo",$imgTextsInfo);
			$this->display("textImgsList");
		}
			
	}
	
	
	public function beforeTexts($infos){
		$str_len = mb_strlen($infos,"utf8");
		if(!$infos){$this->error("详情不能为空");exit;}
		if($str_len>600){$this->error("文本详情不能超过600字");exit;}
	}
	public function beforeImgText(){
		//标题检测
		$title = trim($this->_post("title"));
		$this->checkblank($name="标题",$title);
		$this->checkLen($name="标题",$title,50);
		$img_data["title"] = $title;
		//作者检测
		$author = trim($this->_post("name"));
		$this->checkblank($name="作者",$author);
		$this->checkLen($name="作者",$author,50);
		$img_data["author"] = $author;
		//简介检测
		$brief = trim($this->_post('brief'));
		$this->checkblank($name="简介",$brief);
		$this->checkLen($name="简介",$brief,200);
		$img_data["info"] = $brief;
		//封面地址
		$pic = $this->_post('pic');
		$this->checkblank($name="封面地址",$pic);
		$img_data["pic"] = $pic;
		/*
		 * 详情页编辑和网络连接辩解选其一
		*/
		$info = $this->_post("detailedInfo");
		$url = $this->_post("originalUrl");
	
		$net = $this->_post("skipUrl");
	
		if(empty($info) && empty($net)){
			$this->error("详情页与网络链接必填其一.");
			exit;
		}
	
		if(!empty($info) && empty($net)){
			$img_data['detailedinfo'] = htmlentities(msubstr($info,0,20000,'utf-8',false),ENT_QUOTES  |  ENT_IGNORE ,  "UTF-8" );
			$img_data['originalUrl'] = $url;
			$img_data['skipUrl'] = '';
		}
		if(empty($info) && !empty($net)){
			$img_data['skipUrl'] = $net;
			$img_data['detailedinfo'] = '';
			$img_data['originalUrl'] = '';
		}
		if(!empty($info) && !empty($net)){
			$img_data['detailedinfo'] = htmlentities(msubstr($info,0,20000,'utf-8',false),ENT_QUOTES  |  ENT_IGNORE ,  "UTF-8" );
			$img_data['originalUrl'] = $url;
			$img_data['skipUrl'] = '';
		}
		return $img_data;
	
	}
	
	public function checkLen($name,$value,$length){
		$vLen = mb_strlen($value,"utf8");
		if($vLen>$length){$this->error($name."不超过".$length."个字");exit;}
	}
	
	public function checkblank($name,$value){
		if(!$value){$this->error($name."不能为空");exit;}
	}
	/* 
	 * 添加除多图文以外的记录
	 */
	public function addAllKind(){
	
		$sikpadd = $this->_post('sikpadd');
		$keyword_str = $this->saveInfo->getDiymenKeyWord();
		$keyWordCondition['token'] = $this->token;
		$keyWordCondition['keyword'] = $keyword_str;
		$res = M('Keyword')->where($keyWordCondition)->find();
		if($res){
			$this->error("同一IP请求过于频繁");
			exit;
		}
	
		switch($sikpadd){
			case 1:
				$infos = $this->_post("infos");
	
				$this->beforeTexts($infos);
				$createtime = $updatetime = time();
				$data = array('keyword'=>$keyword_str,'token'=>$this->token,'keywordstatus'=>1,'info'=>$infos,'type'=>'text','createtime'=>$createtime,'updatetime'=>$updatetime);
				$textId = $this->saveInfo->add($data);
				if($textId){
					M("keyword")->add(array('keyword'=>$keyword_str,'pid'=>$textId,'token'=>$this->token,'module'=>'Diymen_text','absolute'=>'1'));
						
					$infoArr = array("info"=>$infos,"textId"=>$textId);
					$strJSON = json_encode($infoArr);
					echo $strJSON;
					die();
				}else{
					$this->error("添加失败");
					exit;
				}
				break;
			case 2:
				$data = $this->beforeImgText();
				$strJSON = json_encode($data);
	
				$data["keyword"] = $keyword_str;
				$data["token"] = $this->token;
				$data["createtime"] = $data["updatetime"] = time();
				$data["type"] = "imgtext";
				$imgId = $this->saveInfo->add($data);
	
				if($imgId){
					M("keyword")->add(array('keyword'=>$keyword_str,'pid'=>$imgId,'token'=>$this->token,'module'=>'Diymen_imgtext','absolute'=>'1'));
						
					$infoArr = array("imgId"=>$imgId,"title"=>$data["title"],"info"=>$data["info"],"pic"=>$data["pic"]);
					$strJSON = json_encode($infoArr);
					echo $strJSON;
					die();
				}else{
					$this->error("添加失败");exit;
				}
				break;
			case 4:
				$data = array();
				$data["pic"] = $this->_post("pic");
				$data["title"] = $this->_post("title");
				$data["keyword"] = $keyword_str;
				$data["token"] = $this->token;
				$data["createtime"] = $data["updatetime"] = time();
				$data["type"] = "picture";
				$imgId = $this->saveInfo->add($data);
				if($imgId){
					M("keyword")->add(array('keyword'=>$keyword_str,'pid'=>$imgId,'token'=>$this->token,'module'=>'Diymen_picture','absolute'=>'1'));
						
					$infoArr = array("id"=>$imgId,"pic"=>$data["pic"],"title"=>$data["title"]);
					$strJSON = json_encode($infoArr);
					echo $strJSON;
					die();
				}else{
					$this->error("添加失败");exit;
				}
				break;
			case 5:
				$data = array();
				$data["voice"] = $this->_post("voice");
				$data["title"] = $this->_post("title");
				$data["info"] = $this->_post("info");
				$data["keyword"] = $keyword_str;
				$data["token"] = $this->token;
				$data["createtime"] = $data["updatetime"] = time();
				$data["type"] = "voice";
				$voiceId = $this->saveInfo->add($data);
	
				if($voiceId){
					M("keyword")->add(array('keyword'=>$keyword_str,'pid'=>$voiceId,'token'=>$this->token,'module'=>'Diymen_voice','absolute'=>'1'));
					$infoArr = array("id"=>$voiceId,"title"=>$data["title"]);
					$strJSON = json_encode($infoArr);
					echo $strJSON;
					die();
				}else{
					$this->error("添加失败");exit;
				}
	
				break;
			default:
				$this->error("请求错误",U("/User/Diymen/index"));
		}
	}
	/*
	 * 编辑除多图文以外的记录
	*/
	public function editAllKind(){
		$status = $_GET['status'];
		$id = $_GET['keyId'];
		$where = array('type'=>$status,'id'=>$id);
	
		$diymen_info  =  $this->saveInfo->where($where)->find();
		switch($status){
			case "text":
				$this->assign('info',$diymen_info);
				$this->assign('sikpadd',1);//标示修改
				$this->display("editText");
				break;
			case "imgtext":
				$this->assign("imgInfo",$diymen_info);
				$this->assign("sikpadd",2);
				$this->display("editImgText");
				break;
			case "picture":
				$this->assign("picInfo",$diymen_info);
				$this->assign("sikpadd",4);
				$this->display("editImg");
				break;
			case "voice":
				$this->assign("voiceInfo",$diymen_info);
				$this->assign("sikpadd",5);
				$this->display("editAddss");
				break;
			default:
				$this->error("请求错误");
				break;
	
		}
	
	}
	
	public function updateAllKind(){
	
		$updatetime = time();
		$sikpadd = $this->_post("sikpadd");
			
		switch($sikpadd){
			case 1:
	
				$infos = $this->_post("infos");
				$id = $this->_post("id");
				$this->beforeTexts($infos);
				$data = array('info'=>$infos,'updatetime'=>$updatetime);
				$this->saveInfo->where("id=".$id)->save($data);
					
				$infoArr = array("info"=>$infos,"textId"=>$id);
				$strJSON = json_encode($infoArr);
				echo $strJSON;
				break;
			case 2:
				$id = $this->_post("id");
				$data = $this->beforeImgText();
				$data["updatetime"] = time();
				$this->saveInfo->where("id=".$id)->save($data);
	
				$infoArr = array("imgId"=>$id,"title"=>$data["title"],"info"=>$data["info"],"pic"=>$data["pic"]);
				$strJSON = json_encode($infoArr);
				echo $strJSON;
				break;
			case 4:
				$data = array();
				$id = $this->_post("id");
				$data["pic"] = $this->_post("pic");
				$data["updatetime"] = time();
				$data["title"] = $this->_post("title");
				$this->saveInfo->where("id=".$id)->save($data);
	
				$infoArr = array("id"=>$id,"pic"=>$data["pic"],"title"=>$data["title"]);
				$strJSON = json_encode($infoArr);
				echo $strJSON;
				break;
			case 5:
				$data = array();
				$id = $this->_post("id");
				$data["voice"] = $this->_post("voice");
				$data["updatetime"] = time();
				$data["title"] = $this->_post("title");
				$data["info"] = $this->_post("info");
				$this->saveInfo->where("id=".$id)->save($data);
	
				$infoArr = array("id"=>$id,"title"=>$data["title"]);
				$strJSON = json_encode($infoArr);
				echo $strJSON;
				break;
		}
	
	}
	/*
	 * 删除除多图文以外的记录
	*/
	public function delAllKind(){
		$status = $_GET['status'];
		$keyId = $_GET['keyId'];
		$where = array('type'=>$status,'id'=>$keyId);
		$this->saveInfo->where($where)->delete();
		
		// 确定跳转链接		
		switch ($status){
			case 'text':
				$kWhere = array("pid"=>$keyId,"module"=>"Diymen_text","token"=>$this->token);
				$keywordId = M("keyword")->where($kWhere)->delete();
				$textDiymenArr = M("diymen_saveinfo")->where("pid=0 and type='text' and token='".$this->token."'")->select();
				$this->assign("textDiymenArr",$textDiymenArr);
				$this->display("textList");
				break;
			case 'imgtext':
				$kWhere = array("pid"=>$keyId,"module"=>"Diymen_imgtext","token"=>$this->token);
				$keywordId = M("keyword")->where($kWhere)->delete();
				$textImgArr = M("Diymen_saveinfo")->where("pid=0 and type='imgtext' and token='".$this->token."'")->select();
				$this->assign("textImgList",$textImgArr);
				$this->display("textImgList");
				break;
			case 'picture':
				$kWhere = array("pid"=>$keyId,"module"=>"Diymen_picture","token"=>$this->token);
				$keywordId = M("keyword")->where($kWhere)->delete();
				$ImgArr = M("Diymen_saveinfo")->where("pid=0 and type='picture' and token='".$this->token."'")->select();
				$this->assign("ImgList",$ImgArr);
				$this->display("imgList");
				break;
			case 'voice':
				$kWhere = array("pid"=>$keyId,"module"=>"Diymen_voice","token"=>$this->token);
				$keywordId = M("keyword")->where($kWhere)->delete();
				$voiceArr = M("Diymen_saveinfo")->where("pid=0 and type='voice' and token='".$this->token."'")->select();
				$this->assign("voiceList",$voiceArr);
				$this->display("voiceList");
				break;
		}
	
	}
	
	/*  各种素材添加页面 */
	public function addText()
	{
		$sikpadd = $_GET['sikpadd'];
		$this->assign("sikpadd",$sikpadd);
		$this->assign("token",$this->token);
		switch($sikpadd){
			case 1:
				$this->display();
				break;
			case 2:
				$this->display("addImgText");
				break;
			case 3:
				$this->display("addImgTexts");
				break;
			case 4:
				$this->display("addImg");
				break;
			case 5:
				$this->display("addss");
				break;
		}
	}
	/* 
	 * 自定义菜单主页面
	 */
	public function main_menu(){
	
		$mainMenu = D("Diymen_menu")->where("parentid=0 and token='".$this->token."'")->order(" sort asc")->select();
		$menuAll = array();
			
		foreach($mainMenu as $v){
			if($v['pid']==0){
				$v["pid"] = $v["url"];
			}
			$menuChild["parent"] = $v;
			$child = D("Diymen_menu")->where("parentid=".$v["id"]." and token='".$this->token."'")->order(" sort desc")->select();
			$childArr = array();
			foreach($child as $p){				
				$p["pid"] = $p["pid"] ? $p["pid"] : $p["url"];
				$childArr[] = $p;
			}
			$menuChild["child"] = $childArr;
			$menuChild["count"] = count($childArr);
			
			$menuAll[] = $menuChild;
			
		}
		$menuCount = count($menuAll);
	
		$this->assign("menuAll",$menuAll);
		$this->assign("menuCount",$menuCount);
		
		$this->display("main_menu");
	}

	
	// 自定义菜单配置
	public function index() {
		if (IS_POST) {
			/*
			$_POST ['token'] = $_SESSION ['token'];
			if ($data == false) {
				if($this->all_insert ( 'Diymen_set' )){
					$this->success('操作成功',U(MODULE_NAME.'/index'));
				}else{
					$this->error('操作失败!');					
				}
			} else {
				$_POST ['id'] = $data ['id'];
				if($this->all_save ( 'Diymen_set','')){
					$this->success('操作成功',U(MODULE_NAME.'/index'));
				}else{
					$this->error('操作失败!');
				}
			}
			M ( 'Wxuser' )->where ( array (
					'token' => $this->token 
			) )->save ( array (
					'appid' => trim ( $this->_post ( 'appid' ) ),
					'appsecret' => trim ( $this->_post ( 'appsecret' ) ) 
			) );
			*/
		} else {
			$class = M ( 'Diymen_class' )->where ( array (
					'token' => $this->token,
					'pid' => 0 
			) )->order ( 'sort asc' )->select ();
			foreach ( $class as $key => $vo ) {
				$c = M ( 'Diymen_class' )->where ( array (
						'token' => $this->token,
						'pid' => $vo ['id'] 
				) )->order ( 'sort asc' )->select ();
				$class [$key] ['class'] = $c;
			}
			$this->assign ( 'class', $class );
			$this->display ();
		}
	}
	public function class_add() {
		if (IS_POST) {
			$diy = D('Diymen_class');
			$result = M ( 'Diymen_class' )->where ( array (
					'token' => $this->token,
					'pid' => $this->_post('pid','intval,trim')
			) )->select();
			if(!empty($result)){
				$count = count($result);
				if($_POST ['pid']=='0'){
					if($count>=3){
						$this->error('您添加的菜单数量超出限制，请删除不需要的菜单项');
					}else{
						// $this->all_insert ( 'Diymen_class', '/index' );
						if($diy->create()===false){
							$this->error($diy->getError());
						}else{
							if($diy->add()){
								$this->success('操作成功',U('/User/Diymen/index',array('token'=>$this->token,'id'=>$this->t_id)));
							}else{
								$this->error('操作失败');
							}					
						}
					}
				}else{
					if($count>=5){
						$this->error('您添加的菜单数量超出限制，请删除不需要的菜单项');
					}else{
						// $this->all_insert ( 'Diymen_class', '/index' );
						if($diy->create()===false){
							$this->error($diy->getError());
						}else{
							if($diy->add()){
								$this->success('操作成功',U('/User/Diymen/index',array('token'=>$this->token,'id'=>$this->t_id)));
							}else{
								$this->error('操作失败');
							}					
						}					
					}
				}
			}else{
				// $this->all_insert ( 'Diymen_class', '/index' );
				if($diy->create()===false){
					$this->error($diy->getError());
				}else{
					if($diy->add()){
						$this->success('操作成功',U('/User/Diymen/index',array('token'=>$this->token,'id'=>$this->t_id)));
					}else{
						$this->error('操作失败');
					}					
				}			
			}
		} else {
			$class = M ( 'Diymen_class' )->where ( array (
					'token' => $this->token,
					'pid' => 0 
			) )->order ( 'sort desc' )->select ();
			$this->assign ( 'class', $class );
			$this->display ();
		}
	}
	public function class_del() {
		$class = M ( 'Diymen_class' )->where ( array (
				'token' => $this->token,
				'pid' => $this->_get ( 'id','trim,intval') 
		) )->order ( 'sort desc' )->find ();
		if ($class == false) {
			$back = M ( 'Diymen_class' )->where ( array (
					'token' => $this->token,
					'id' => $this->_get ( 'id' ,'trim,intval') 
			) )->delete ();
			if ($back == true) {
				$this->success ( '删除成功' );
			} else {
				$this->error ( '删除失败' );
			}
		} else {
			$this->error ( '请删除该分类下的子分类' );
		}
	}
	private function changeStruts() {
		$editclass = M ( 'Diymen_class' )->where ( array (
				'id' => $this->_get ( 'id' ,'trim,intval') 
		) )->find ();
		$sortvalue = array (
				"1",
				"2",
				"3" 
		);
		$class = M ( 'Diymen_class' )->where ( array (
				'token' => $this->token,
				'pid' => 0 
		) )->order ( 'sort asc' )->select (); 
		$arrlength = count ( $class );
		for($x = 0; $x < $arrlength; $x ++) {
			if ($class [$x] ['sort'] == "1" || $class [$x] ['sort'] == "2" || $class [$x] ['sort'] == "3") {
				$location = array_search ( $class [$x] ['sort'], $sortvalue );
				array_splice ( $sortvalue, $location, 1 );
			}
		}
		return $sortvalue;
	}
	public function class_edit() {
		if (IS_POST) {
			$_POST ['id'] = $this->_get ( 'id' );
			$result = M ( 'Diymen_class' )->where ( array (
					'token' => $this->token,
					'sort' => $_POST ['sort'],
					'pid' => $_POST ['pid'],
					'id' => array (
							"neq",
							$this->_get ( 'id' ,'trim,intval') 
					) 
			) )->find ();
			if (! empty ( $result )) {
				$resultOld = M ( 'Diymen_class' )->where ( array (
						'id' => $this->_get ( 'id' ,'intval,trim') 
				) )->find ();
				$result ['sort'] = $resultOld ['sort'];
				$Diymen_class = M ( "Diymen_class" );
				$Diymen_class->save ( $result );
			}
			// $this->all_save ( 'Diymen_class', '/index?id=' . $this->_get ( 'id' ) );
			if(D('Diymen_class')->create()===false){
				$this->error(D('Diymen_class')->getError());
			}else{
				if(D('Diymen_class')->save() !== false){
					$this->success('操作成功',U('/User/Diymen/index',array('token'=>$this->token)));
				}else{
					$this->error('操作失败');
				}					
			}
		} else {
			$data = M ( 'Diymen_class' )->where ( array (
					'token' => $this->token,
					'id' => $this->_get ( 'id' ,'intval,trim') 
			) )->find ();
			if ($data == false) {
				$this->error ( '您所操作的数据对象不存在！' );
			} else {
				$class = M ( 'Diymen_class' )->where ( array (
						'token' => $this->token,
						'pid' => 0 
				) )->order ( 'sort desc' )->select ();
				$this->assign ( 'class', $class );
				$this->assign ( 'show', $data );
			}
			$this->display ();
		}
	}
	public function class_send() {
		if (IS_GET) {
			if(empty($this->thisWxUser['appsecret'])){
			//使用微信开放平台上的绑定接口,没有appsercet;
				$bind = A('User/Bind');
				$tmp_token = $bind->return_token($this->thisWxUser['appid']);
			}else{
				$url_get = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->thisWxUser ['appid'] . '&secret=' . $this->thisWxUser ['appsecret'];
				$json = json_decode ( $this->curlGet ( $url_get ) );
			}

			if (!empty($json->errcode)) {
				$this->error ( '获取access_token发生错误：错误代码'.$json->errcode);
			}

			$data = '{"button":[';
			$class = M ( 'Diymen_class' )->where ( array (
					'token' => $this->token,
					'pid' => 0,
					'is_show' => 1 
			) )->limit ( 3 )->order ( 'sort desc' )->select (); 
			$kcount = count($class);
			$k = 1;
			foreach ( $class as $key => $vo ) {
				// 主菜单
				$data .= '{"name":"' . $vo ['title'] . '",';
				$c = M ( 'Diymen_class' )->where ( array (
						'token' => $this->token,
						'pid' => $vo ['id'],
						'is_show' => 1 
				) )->limit ( 5 )->order ( 'sort desc' )->select ();
				$count = M ( 'Diymen_class' )->where ( array (
						'token' => $this->token,
						'pid' => $vo ['id'],
						'is_show' => 1 
				) )->limit ( 5 )->order ( 'sort desc' )->count ();
				// 子菜单
				$vo ['url'] = str_replace ( array (
						'&amp;' 
				), array (
						'&' 
				), $vo ['url'] );
				if ($c != false) {
					$data .= '"sub_button":[';
				} else {
					if (! $vo ['url']) {
						$data .= '"type":"click","key":"' . $vo ['keyword'] . '"';
					} else {
						$data .= '"type":"view","url":"' . $vo ['url'] . '"';
					}
				}
				$i = 1;
				foreach ( $c as $voo ) {
					$voo ['url'] = str_replace ( array (
							'&amp;' 
					), array (
							'&' 
					), $voo ['url'] );
					if ($i == $count) {
						if ($voo ['url']) {
							$data .= '{"type":"view","name":"' . $voo ['title'] . '","url":"' . $voo ['url'] . '"}';
						} else {
							$data .= '{"type":"click","name":"' . $voo ['title'] . '","key":"' . $voo ['keyword'] . '"}';
						}
					} else {
						if ($voo ['url']) {
							$data .= '{"type":"view","name":"' . $voo ['title'] . '","url":"' . $voo ['url'] . '"},';
						} else {
							$data .= '{"type":"click","name":"' . $voo ['title'] . '","key":"' . $voo ['keyword'] . '"},';
						}
					}
					$i ++;
				}
				if ($c != false) {
					$data .= ']';
				}
				
				if ($k == $kcount) {
					$data .= '}';
				} else {
					$data .= '},';
				}
				$k ++;
			}
			$data .= ']}';
			if(empty($json) && !empty($tmp_token)){
			$new_token = $tmp_token['authorizer_access_token'];
			file_get_contents ( 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=' . $new_token );
			$url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $new_token;
			}else{
				file_get_contents ( 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=' . $json->access_token );
				$url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $json->access_token;
			}
			$rt = $this->api_notice_increment ( $url, $data );
			if ($rt ['rt'] == false) {
				$this->error ( '操作失败,curl_error:' . $rt ['errorno'] );
			} else {
				$this->success ( '操作成功' );
			}
			exit ();
		} else {
			$this->error ( '非法操作' );
		}
	}
	function api_notice_increment($url, $data) {
		$ch = curl_init ();
		$header = "Accept-Charset: utf-8";
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
		curl_setopt ( $curl, CURLOPT_HTTPHEADER, $header );
		curl_setopt ( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)' );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		$tmpInfo = curl_exec ( $ch );
		$errorno = curl_errno ( $ch );
		
		if ($errorno) {
			return array (
					'rt' => false,
					'errorno' => $errorno 
			);
		} else {
			$js = json_decode ( $tmpInfo, 1 );
						
			if ($js ['errcode'] == '0') {
				return array (
						'rt' => true,
						'errorno' => 0 
				);
			} else {
				$this->error ( '发生错误：错误代码' . $js ['errcode'] . ',微信返回错误信息：' . $js ['errmsg'] );
			}
		}
	}
	function curlGet($url) {
		$ch = curl_init ();
		$header = "Accept-Charset: utf-8";
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "GET" );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
		curl_setopt ( $curl, CURLOPT_HTTPHEADER, $header );
		curl_setopt ( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)' );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		$temp = curl_exec ( $ch );
		return $temp;
	}
}
?>