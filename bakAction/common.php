<?php
function isAndroid(){
	if(strstr($_SERVER['HTTP_USER_AGENT'],'Android')) {
		return 1;
	}
	return 0;
}
	//权限验证函数
    function authcheck($rule,$uid,$gid,$relation='or',$t,$f='没有权限'){
            //判断当前用户UID是否在定义的超级管理员参数里
        if(in_array($uid,C('administrator')) || in_array(MODULE_NAME,C('RULE_MODULE'))){   
            return $t;    //如果是，则直接返回真值，不需要进行权限验证
        }else{
            //文件存在使用文件验证
            $oper_auth = include_once './uploads/ShareData/user_auth_conf.php';
            if(!empty($oper_auth)){
                $has_auth = $oper_auth[$gid][MODULE_NAME]['has'];
                if(in_array($rule,$has_auth) && !empty($has_auth))
                    return $t;
                else
                    return $f;
            }else{
                $auth=new Auth(); //使用thinkphp权限验证类
                return $auth->check($rule,$uid,$relation) ? $t : $f;
            }
        }
    } 
    function object_to_array($obj){
        $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
        foreach ($_arr as $key => $val)
        {
            $val = (is_array($val) || is_object($val)) ? object_to_array($val) : $val;
            $arr[$key] = $val;
        }
        return $arr;
    }
    function getip(){
        if ( getenv( "HTTP_CLIENT_IP" ) && strcasecmp( getenv( "HTTP_CLIENT_IP" ), "unknown" ) ){
            $ip = getenv( "HTTP_CLIENT_IP" );
        }else if ( getenv( "HTTP_X_FORWARDED_FOR" ) && strcasecmp( getenv( "HTTP_X_FORWARDED_FOR" ), "unknown" ) ){
            $ip = getenv( "HTTP_X_FORWARDED_FOR" );
        }else if (getenv( "REMOTE_ADDR" ) && strcasecmp( getenv( "REMOTE_ADDR" ), "unknown" ) ){
            $ip = getenv( "REMOTE_ADDR" );
        }else if ( isset( $_SERVER[ 'REMOTE_ADDR' ] ) && $_SERVER[ 'REMOTE_ADDR' ]
                                        && strcasecmp( $_SERVER[ 'REMOTE_ADDR' ], "unknown" ) ){
            $ip = $_SERVER[ 'REMOTE_ADDR' ];
        }else{
            $ip = "unknown";
        }
        if ( strpos( $ip, ',' ) ){
            $ipArr = explode( ',', $ip );
            $ip = $ipArr[ 0 ];
        }
        return $ip ;        
        // return $_SERVER['REMOTE_ADDR'];
    }
    /**
        *      等比例压缩图片
        * @param  String $src_imagename 源文件名        比如 “source.jpg”
        * @param  int    $maxwidth      压缩后最大宽度
        * @param  int    $maxheight     压缩后最大高度
        * @param  String $savename      保存的文件名    “d:save”
        * @param  String $filetype      保存文件的格式 比如 ”.jpg“
    */
    function resizeSaveImage($src_imagename,$maxwidth,$maxheight,$savename,$filetype){
        $im=imagecreatefromjpeg($src_imagename);
        $current_width = imagesx($im);
        $current_height = imagesy($im);

        if(($maxwidth && $current_width > $maxwidth) || ($maxheight && $current_height > $maxheight))
        {
            if($maxwidth && $current_width>$maxwidth)
            {
                $widthratio = $maxwidth/$current_width;
                $resizewidth_tag = true;
            }

            if($maxheight && $current_height>$maxheight)
            {
                $heightratio = $maxheight/$current_height;
                $resizeheight_tag = true;
            }

            if($resizewidth_tag && $resizeheight_tag)
            {
                if($widthratio<$heightratio)
                    $ratio = $widthratio;
                else
                    $ratio = $heightratio;
            }

            if($resizewidth_tag && !$resizeheight_tag)
                $ratio = $widthratio;
            if($resizeheight_tag && !$resizewidth_tag)
                $ratio = $heightratio;

            $newwidth = $current_width * $ratio;
            $newheight = $current_height * $ratio;

            if(function_exists("imagecopyresampled"))
            {
                $newim = imagecreatetruecolor($newwidth,$newheight);
                imagecopyresampled($newim,$im,0,0,0,0,$newwidth,$newheight,$current_width,$current_height);
            }
            else
            {
               $newim = imagecreate($newwidth,$newheight);
               imagecopyresized($newim,$im,0,0,0,0,$newwidth,$newheight,$current_width,$current_height);
            }

            $savename = $savename.$filetype;
            imagejpeg($newim,$savename);
            imagedestroy($newim);
        }
        else
        {
            $savename = $savename.$filetype;
            imagejpeg($im,$savename);
        }           
    }
    /**
     * 截取中文字符串
     */
    function msubstr($str, $start=0, $length, $charset="utf-8", $suffix=true){
        $len = abslength($str);
        if(function_exists("mb_substr")){
          if($suffix && $len > $length)
            return mb_substr($str, $start, $length, $charset)."...";
          else
            return mb_substr($str, $start, $length, $charset);
        }elseif(function_exists('iconv_substr')) {
        if($suffix && $len > $length)
           return iconv_substr($str,$start,$length,$charset)."...";
        else
           return iconv_substr($str,$start,$length,$charset);
        }
        $re['utf-8'] = "/[x01-x7f]|[xc2-xdf][x80-xbf]|[xe0-xef][x80-xbf]{2}|[xf0-xff][x80-xbf]{3}/";
        $re['gb2312'] = "/[x01-x7f]|[xb0-xf7][xa0-xfe]/";
        $re['gbk'] = "/[x01-x7f]|[x81-xfe][x40-xfe]/";
        $re['big5'] = "/[x01-x7f]|[x81-xfe]([x40-x7e]|xa1-xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("",array_slice($match[0], $start, $length));
        if($suffix) return $slice.'...';
        return $slice;
    }
    /**
     * 计算中文字符串的长度
     */
    function abslength($str,$charset='utf-8'){     
        $len=strlen($str);     
        $i=0; $j=0;    
        while($i<$len)     
        {     
            if(preg_match("/^[".chr(0xa1)."-".chr(0xf9)."]+$/",$str[$i]) && $charset== 'utf-8'){     
                $i+=3;  //注意TP中的编码都是utf-8，所以+3;如果是GBK改为+2  
            }elseif(strtoupper($charset) == 'GBK'){     
                $i+=2;     
            }else{
                $i+=1;
            }     
            $j++;  
        }  
        return $j;  
    }

    /*
    * 清除所有特殊符号
    */
    function remove_special_symbols($keyword){
        // $chr = mb_detect_encoding( $keyword );
        // $keyword = iconv( $chr, "UTF-8", $keyword );
        $preg = '/[^a-zA-Z\x{4e00}-\x{9fa5}0-9]+/u';
        //全局匹配
        $res = preg_match_all( $preg, $keyword, $match );
        //将匹配到的值进行元字符拆分
        if( $match[0] && is_array( $match[0] ) ){
            foreach( $match[0] as $key => $val ){
                $tempLength = mb_strlen( $val,'UTF-8' );
                if( $tempLength > 1 ){
                    for( $i=0; $i<$tempLength; $i++ ){
                        $match[0][] = iconv_substr( $val, $i, 1, 'UTF-8' );
                    }
                    unset( $match[0][$key] );
                }
            }
        }
        $keyword = str_replace( $match[0], '', $keyword );
        return $keyword;
        /*$keyword = urlencode($keyword);//将关键字编码
        $keyword = preg_replace("/(%7E|%60|%21|%40|%23|%24|%25|%5E|%26|%27|%2A|%28|%29|%2B|%7C|%EF%BF%A5|%5C|%3D|\-|_|%5B|%5D|%7D|%7B|%3B|%22|%3A|%3F|%3E|%3C|%2C|\.|%2F|%A3%BF|%A1%B7|%A1%B6|%A1%A2|%A1%A3|%A3%AC|%7D|%A1%B0|%A3%BA|%A3%BB|%A1%AE|%A1%AF|%A1%B1|%A3%FC|%A3%BD|%A1%AA|%A3%A9|%A3%A8|%A1%AD|%A3%A4|%A1%A4|%A3%A1|%E3%80%82|%EF%BC%81|%EF%BC%8C|%EF%BC%9B|%EF%BC%9F|%EF%BC%9A|%E3%80%81|%E2%80%A6%E2%80%A6|%E2%80%9D|%EF%BC%88|%EF%BC%89|%E3%80%8A|%E3%80%8B|%E2%80%9C|%E2%80%98|%E2%80%99|%E3%80%90|%E3%80%91|%EF%BC%8D|%EF%BC%8B|%EF%BD%9B|%EF%BD%9D|%26%23183%3B|%C2%B7|\+)+/",'',$keyword);
        $keyword=urldecode($keyword);//将过滤后的关键字解码
        return $keyword;*/        
    }
    //判断后缀名
    function pack_img_suffix($img){
        $pack = unpack('C2chars',$img);
        $type_code = intval($pack['chars1'].$pack['chars2']);
        switch ($type_code) {
            case 7790:
                $file_type = 'exe';
                break;
            case 7784:
                $file_type = 'midi';
                break;
            case 8075:
                $file_type = 'zip';
                break;
            case 8297:
                $file_type = 'rar';
                break;
            case 255216:
                $file_type = 'jpg';
                break;
            case 7173:
                $file_type = 'gif';
                break;
            case 6677:
                $file_type = 'bmp';
                break;
            case 13780:
                $file_type = 'png';
                break;
            default:
                $file_type = 'unknown';
                break;
        }
        return $file_type;
    }

    //循环创建文件夹
    function createDir_bv($path){
        if (!file_exists($path)){
            createDir_bv(dirname($path));
            mkdir($path, 0777);
        }
    }
    //判断是否是否纯汉字
    function utf8_str($str){  
        $mb = mb_strlen($str,'utf-8');  
        $st = strlen($str);  
        if($st == $mb)  
            return 1; //英文  
        if($st%$mb==0 && $st%3==0)  
            return 2; //汉字 
        return 3; //汉英组合  
    }     
    //加密函数与解密
    function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {   
        $flag = '52*#ufdo%36';
        $ckey_length = 4;   

        $key = md5($key ? $key : $flag);   
        $keya = md5(substr($key, 0, 16));   
        $keyb = md5(substr($key, 16, 16));   
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';   
          
        $cryptkey = $keya.md5($keya.$keyc);   
        $key_length = strlen($cryptkey);   
          
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;   
        $string_length = strlen($string);   
          
        $result = '';   
        $box = range(0, 255);   
          
        $rndkey = array();   
        for($i = 0; $i <= 255; $i++) {   
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);   
        }   
          
        for($j = $i = 0; $i < 256; $i++) {   
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;   
            $tmp = $box[$i];   
            $box[$i] = $box[$j];   
            $box[$j] = $tmp;   
        }   
          
        for($a = $j = $i = 0; $i < $string_length; $i++) {   
            $a = ($a + 1) % 256;   
            $j = ($j + $box[$a]) % 256;   
            $tmp = $box[$a];   
            $box[$a] = $box[$j];   
            $box[$j] = $tmp;   
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));   
        }   
          
        if($operation == 'DECODE') {   
            if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {   
                return substr($result, 26);   
            } else {   
                    return '';   
                }   
        } else {   
            return $keyc.str_replace('=', '', base64_encode($result));   
        }    
    } 
    //curl的get请求
    function curlGet($url){
        $ch = curl_init();
        $header = "Accept-Charset: utf-8";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)");
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $temp = curl_exec($ch);
        curl_close($ch);
        return $temp;
    }
    //curl 的post 请求
    function curlPost($p_url,$p_post){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $p_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.117 Safari/537.36');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $p_post);// http_build_query()
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        $output = curl_exec($ch);
        if($error=curl_error($ch)){  
            die('curl错误号:'.$error);  
        }
        curl_close($ch);
        return $output;
    }
    /*获取自定义表单字段信息*/
    function _createForms($token,$wechat_id,$set_id,$active_id = -1,$module){
        $where  = array('token'=>$token,'set_id'=>$set_id,'is_show'=>'1');
        $forms  = M('Custom_field')->where($where)->order('sort asc')->select();
        $f_where['token'] = $token;
        $f_where['wecha_id'] = $wechat_id;
        $f_where['set_id'] = $set_id;
        $f_where['activety'] = $active_id;
        $f_where['module'] = $module;
        $forms_value = M('Custom_info')->where($f_where)->getField('sub_info');
        $field_value = unserialize($forms_value);
        $str    = '<table width="100%" border="0" cellspacing="0" cellpadding="0" class="kuang">';
        $arr    = array();
        $len_arr = count($forms) - 1;
        foreach($forms as $key=>$value){
            $str    .= '<tr><th>';
            if($value['is_empty'] == 1)
                $str    .= "<i style='color:#DF1E93;display:inline;padding-right:5px;'>*</i>".$value['field_name'];
            else
                $str    .= $value['field_name'];
            $str    .= '</th><td>';
            $str    .= _getInput($value,$field_value[$value['field_id']]);
            if($len_arr == $key){
                $str    .= '</td></tr>';
            }else{
                 $str    .= '</td></tr><tr><td colspan="2"><hr/></td></tr>';
            }
            if($value['field_type'] == 'region'){
                $name_arr = explode('#', $value['item_name']);
                $arr[]   = array('id'=>$name_arr[0],'name'=>$value['field_name'],'type'=>$value['field_type'],'match'=>$value['field_match'],'is_empty'=>$value['is_empty'],'err_info'=>$value['err_info']);  //js验证信息
                $arr[]   = array('id'=>$name_arr[1],'name'=>$value['field_name'],'type'=>$value['field_type'],'match'=>$value['field_match'],'is_empty'=>$value['is_empty'],'err_info'=>$value['err_info']);  //js验证信息
            }else{
                $arr[]   = array('id'=>$value['item_name'],'name'=>$value['field_name'],'type'=>$value['field_type'],'match'=>stripslashes($value['field_match']),'is_empty'=>$value['is_empty'],'err_info'=>$value['err_info']);  //js验证信息
            }
        }
        $str    .= '</table>';
        // print_r($arr);
        return array('string'=>$str,'verify'=>$arr);
    }
    /*获取自定义表单*/
    function _getInput($value,$f_value){
        $input  = '';
        switch($value['field_type']){
            case 'text':
                if(stripos($value['field_name'],'手机')!== false){
                    $input  .= '<input type="tel" class="px" id="'.$value['item_name'].'" name="'.$value['item_name'].'" value="'.$f_value['value'].'">';
                }else{
                    $input  .= '<input type="text" class="px" id="'.$value['item_name'].'" name="'.$value['item_name'].'" value="'.$f_value['value'].'">';
                }
                break;
            case 'textarea':
                $input  .= '<textarea name="'.$value['item_name'].'" id="'.$value['item_name'].'" rows="4" cols="25">'.$f_value['value'].'</textarea>';
                break;
            case 'checkbox':
                $option = explode('|', $value['filed_option']);
                for($i=0;$i<count($option);$i++){
                    if(stripos($f_value['value'],$option[$i]) !== false){
                        $input  .= '<input type="checkbox" name="'.$value['item_name'].'[]" id="'.$value['item_name'].'" value="'.$option[$i].'" checked />'.$option[$i]."<br/>";
                    }else{
                        $input  .= '<input type="checkbox" name="'.$value['item_name'].'[]" id="'.$value['item_name'].'" value="'.$option[$i].'"  />'.$option[$i]."<br/>";
                    }
                }
                break;
            case 'radio':
                $option = explode('|', $value['filed_option']);
                for($i=0;$i<count($option);$i++){
                    if($option[$i] == $f_value['value']){
                        $input  .= '<input type="radio" name="'.$value['item_name'].'" id="'.$value['item_name'].'" value="'.$option[$i].'" checked />'.$option[$i]."<br/>";
                    }else{
                        if($i == 0){
                            $input  .= '<input type="radio" name="'.$value['item_name'].'" id="'.$value['item_name'].'" value="'.$option[$i].' " checked/>'.$option[$i]."<br/>";
                        }else{
                            $input  .= '<input type="radio" name="'.$value['item_name'].'" id="'.$value['item_name'].'" value="'.$option[$i].'"/>'.$option[$i]."<br/>";                            
                        }
                    }
                }
                break;
            case 'select':
                $input  .= '<select class="td_px" name="'.$value['item_name'].'" id="'.$value['item_name'].'"><option value="">请选择..</option>';
                $op_arr = explode('|',$value['filed_option']);
                $num    = count($op_arr);
                if($num > 0){
                    for($i=0;$i<$num;$i++){
                        if($op_arr[$i] == $f_value['value']){
                            $input  .= '<option selected value="'.$op_arr[$i].'">'.$op_arr[$i].'</option>';
                        }else{
                            $input  .= '<option value="'.$op_arr[$i].'">'.$op_arr[$i].'</option>';
                        }
                    }
                }
                $input  .='</select>';
                break;
            case 'date':
                $input  .= '<input type="date" class="px" name="'.$value['item_name'].'" id="'.$value['item_name'].'" value="'.date('Y-m-d',time()).'">';
                break;
            case 'region':
                $reg_name = explode('#', $value['item_name']);
                $all_province = get_province();
                $all_city = get_province(array('upid'=>$f_value['pro_value'] == 0 ? 1 : $f_value['pro_value'],'level'=>2));
                $province_str = '';
                $city_str = '';
                foreach ($all_province as $_p => $v_p) {
                    if($f_value['pro_value'] == $v_p['id']){
                        $province_str .= "<option selected value='".$v_p['id']."'>".$v_p['name']."</option>";
                    }else{
                       $province_str .= "<option value='".$v_p['id']."'>".$v_p['name']."</option>"; 
                    }
                }
                foreach ($all_city as $k_c => $v_c) {
                    if($f_value['city_value'] == $v_c['id']){
                        $city_str .= "<option selected value='".$v_c['id']."'>".$v_c['name']."</option>";
                    }else{
                       $city_str .= "<option value='".$v_c['id']."'>".$v_c['name']."</option>"; 
                    }
                }
                $input .= '<select class="td_px" name="'.$reg_name[0].'" onchange="show_city(this.value,2,\''.$reg_name[1].'\');">'.$province_str.'</select><select class="td_px" name="'.$reg_name[1].'" id="'.$reg_name[1].'">'.$city_str.'</select>';
                break;
        }
        return $input;
    }
    //获取省份列表
    function get_province($arr){
        $conditon['upid'] = $arr['upid'] == '' ? 0 : $arr['upid'];
        $conditon['level'] = $arr['level'] == '' ? 1 : $arr['level'];
        return M('City')->field('id,name,upid')->where($conditon)->select();
    }  
    /*获取留言内容*/
    function _getMessage($token,$module,$active_id,$limit){
        $m_where['token'] = $token;
        // $m_where['wechat_id'] = $wechat_id;
        $m_where['module'] = $module;
        $m_where['active_id'] = $active_id;
        $list =  M('Active_message')->where($m_where)->order('msg_time desc')->limit($limit)->select();
        // echo M('Active_message')->getlastsql();
        return $list;   
    }
    /*查询是否发表过留言*/
    function _getId($token,$wechat_id,$module,$active_id){
        $m_where['token'] = $token;
        $m_where['wechat_id'] = $wechat_id;
        $m_where['module'] = $module;
        $m_where['active_id'] = $active_id;
        return M('Active_message')->where($m_where)->getField('id');
    }
    function _getCount($token,$module,$active_id){
        $m_where['token'] = $token;
        $m_where['module'] = $module;
        $m_where['active_id'] = $active_id;
        return M('Active_message')->where($m_where)->count();
    }  
?>