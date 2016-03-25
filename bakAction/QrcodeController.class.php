<?php
namespace Home\Controller;
use Think\Controller;

class QrcodeController extends Controller {
	private $code;//验证码
	private $logotext;
	private $width = 2446;//宽度
	private $height = 3041;//高度
	private $img;//图形资源句柄
	private $font;//指定的字体
	private $fontsize = 12;//指定字体大小
	private $fontcolor;//指定字体颜色
	private $logo;
	private $red; //背景颜色
	private $green;
	private $blue;
	public  $content;
	public  $qrfile;
	public  $qrlogofile;
	public  $qrnewfile;
	public  $qrnewfilesmall;
	public  $snvalue;
	private $wxp;
	//构造方法初始化
	public function __construct() {
	    $this->font = './Tool/fonts/Arial.ttf';//注意字体路径要写对，否则显示不了图片
	    $this->red = 237;
	    $this->green = 122;
	    $this->blue = 36;
	    // $this->logo = './Tool/img/bv.png'; //准备好的logo图片
	    $this->snvalue = $this->createSn();
	   	$this->content = C('USER_PAY_DOMAIN').'/Home/Index/index?code_num='.$this->snvalue;//二维码内容
		$this->qrfile = './Public/qrcodeImg/'.$this->snvalue.'.png';
		$this->qrlogofile = './Public/qrcodeImg/logo-'.$this->snvalue.'.png';
		$this->qrnewfile = './Public/qrcodeImg/board-'.$this->snvalue.'.png'; 
		$this->qrnewfilesmall = './Public/qrcodeImg/boardsmall-'.$this->snvalue.'.png'; 
		$this->wxp = './Tool/img/template.jpg';//微信支付图片 
	}
	//生成sn码：
	private function createSn(){
		$chars = "123456789";
        $str = "";
        for ( $i = 0; $i <= 12; $i++ )  {
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
	}
 	//生成字符
 	private function createCode() {
  		$this->code = 'NO.'.$this->snvalue;
  		$this->logotext = mb_convert_encoding('店立方','html-entities','UTF-8');
 	}
 	//生成背景
	private function createBg() {
  		/*$this->img = imagecreatetruecolor($this->width, $this->height);
  		$color = imagecolorallocate($this->img, $this->red,$this->green,$this->blue);
  		imagefilledrectangle($this->img,0,$this->height,$this->width,0,$color);*/
 	}
 	private function createBgSmall($width,$height) {
  		$this->img = imagecreatetruecolor($width, $height);
  		$color = imagecolorallocate($this->img, $this->red,$this->green,$this->blue);
  		imagefilledrectangle($this->img,0,$height,$width,0,$color);
 	}
 	//生成文字
	private function createFont() {
   		$this->fontcolor = imagecolorallocate($this->img,mt_rand(0,1),mt_rand(0,1),mt_rand(0,1));
   		imagettftext($this->img,$this->fontsize,0,243,535,$this->fontcolor,$this->font,$this->code);
   		// imagettftext($this->img,$this->fontsize,0,$this->width-250,$this->height-50,$this->fontcolor,$this->font,$this->logotext);
 	}
 	private function createFontSmall($width,$height) {
   		$this->fontcolor = imagecolorallocate($this->img,mt_rand(0,2),mt_rand(0,2),mt_rand(0,2));
   		imagettftext($this->img,$this->fontsize,0,243,535,$this->fontcolor,$this->font,$this->code);
   		// imagettftext($this->img,$this->fontsize,0,$width-200,$height-50,$this->fontcolor,$this->font,$this->logotext);
 	}
 	//生成二维码
 	private function createQrcode() {
 		//二维码
 		import('QRcode', './Tool/qrcode', '.php');
		$errorCorrectionLevel = 'M';//容错级别 
		$matrixPointSize = 10;//生成图片大小 
		//生成二维码图片 
		if(!file_exists($this->qrfile)){
			\Tool\qrcode\QRcode::png($this->content, $this->qrfile, $errorCorrectionLevel, $matrixPointSize, 2);
		}
		if($this->logo !== FALSE) { 
		 	$QR = imagecreatefromstring(file_get_contents($this->qrfile)); 
		 	$logoRes = imagecreatefromstring(file_get_contents($this->logo)); 

		 	$QR_width = imagesx($QR);//二维码图片宽度 
		 	$QR_height = imagesy($QR);//二维码图片高度 
		 	$logo_width = imagesx($logoRes);//logo图片宽度 
		 	$logo_height = imagesy($logoRes);//logo图片高度 
		 	
		 	$logo_qr_width = $QR_width / 3; 
		 	$scale = $logo_width/$logo_qr_width; 
		 	$logo_qr_height = $logo_height/$scale; 
		 	$from_width = ($QR_width - $logo_qr_width) / 2; 
		 	//重新组合图片并调整大小 
		 	imagecopyresampled($QR, $logoRes, $from_width, $from_width, 0, 0, $logo_qr_width, 
		 	$logo_qr_height, $logo_width, $logo_height); 
			//输出图片 
			imagepng($QR, $this->qrlogofile);
		}  
 	}
 	//底图与二维码合并
 	private function mergeQrcode(){
 		$this->img = imagecreatefromstring(file_get_contents($this->wxp));
 		// $wxpay = imagecreatefromstring(file_get_contents($this->wxp));
 		// $wxWidth = imagesx($wxpay);
 		// $wxHeight = imagesy($wxpay);
 		if($this->logo !== false){
 			$QR = imagecreatefromstring(file_get_contents($this->qrlogofile)); 
 		}else{
 			$QR = imagecreatefromstring(file_get_contents($this->qrfile));
 		}
 		$oriWidth = imagesx($QR);
 		$oriHeight = imagesy($QR);

	 	$scaleH = 0.8;
	 	$scaleW = 0.8;
 		//合并二维码
 		imagecopyresampled($this->img, $QR, 166,207, 0, 0, $scaleW*$oriWidth,$oriHeight*$scaleH, $oriWidth,$oriHeight);
 		//合并微信支付图片
		// imagecopyresampled($this->img, $wxpay, $this->width-150, $this->height-120, 0, 0, $wxWidth,$wxHeight, $wxWidth,$wxHeight);
 	}
 	private function mergeQrcodeSmall($width,$height){
 		$this->img = imagecreatefromstring(file_get_contents($this->wxp));
 		// $wxpay = imagecreatefromstring(file_get_contents($this->wxp));
 		// $wxWidth = imagesx($wxpay);
 		// $wxHeight = imagesy($wxpay);
 		if($this->logo !== false){
 			$QR = imagecreatefromstring(file_get_contents($this->qrlogofile)); 
 		}else{
 			$QR = imagecreatefromstring(file_get_contents($this->qrfile));
 		}
 		$oriWidth = imagesx($QR);
 		$oriHeight = imagesy($QR);

	 	$scaleH = 0.8;
	 	$scaleW = 0.8;
 		//合并二维码
 		imagecopyresampled($this->img, $QR, 166,207, 0, 0, $scaleW*$oriWidth,$oriHeight*$scaleH, $oriWidth,$oriHeight);
 		//合并微信支付图片
		// imagecopyresampled($this->img, $wxpay, $width-120, $height-120, 0, 0, $wxWidth,$wxHeight, $wxWidth,$wxHeight);
 	}
	//输出
	private function outPut($filename) {
		if($this->saveInfoToBase() > 0){
	    	/*header('Content-type:image/png');
	    	imagepng($this->img);*/
	    	imagepng($this->img,$filename);
   		    imagedestroy($this->img);
   		    echo '生成成功';
		}else{
			unlink($this->qrfile);
			unlink($this->qrlogofile);
		    imagedestroy($this->img);
   			die('二维码生成错误');
		}
	}
	//生成二维码
	public function createQrcodeImage(){
		$this->createQrcode();
		$this->createCode();
		$this->doimg();
		// $this->doimgSmall();
	}
	//导出二维码
	public function exportQrcodeImage(){
		$list = D('ShopQrCode')->where(array('status'=>0,'is_del'=>0))->order('create_time desc')->field('qr_code_num,qr_code_url,paster_img_url,billboard_img_url,FROM_UNIXTIME(create_time) as ctime')->select();
		$title = array('qr_code_num'=>'门店sn码','ctime'=>'生成时间','qr_code_url'=>'支付二维码','paster_img_url'=>'贴纸二维码','billboard_img_url'=>'水牌二维码');
		$this->exportexcel($list,$title,'二维码列表');
	}
	public function exportexcel($data=array(),$title=array(),$filename='report'){ 
        import('PHPExcel','./Tool/qrcode/PHPExcel','.php');
        import('Excel5','./Tool/qrcode/PHPExcel/PHPExcel/Reader','.php');
        // Create new PHPExcel object
        $objPHPExcel = new \Tool\qrcode\PHPExcel\PHPExcel();
        $objPHPExcel->getActiveSheet()->getRowDimension(1)->setRowHeight(25);
        //set font size bold    
        $objPHPExcel->getActiveSheet()->getDefaultStyle()->getFont()->setSize(10);    
        $objPHPExcel->getActiveSheet()->getStyle('A1:H1')->getFont()->setBold(true);               
        //  合并单元格  
        $objPHPExcel->getActiveSheet()->mergeCells('A1:H1'); 
        $objPHPExcel->getActiveSheet()->setCellValue('A1', $filename);
        $letter_arr = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        $h = 1;
        for($j=0;$j<count($data);$j++){
            $let = 0;
            foreach ($title as $k_t => $v_t) {
                $objPHPExcel->setActiveSheetIndex(0)    
                        ->setCellValue($letter_arr[$let].'2',$v_t);//循环写标题
                $val = $data[$j][$k_t];
                if(in_array($k_t, array('qr_code_url','paster_img_url','billboard_img_url'))){
                	$objPHPExcel->getActiveSheet(0)->setCellValue($letter_arr[$let].($h+2), $val);
                	if(!empty($val)){
                		$val = C('SITE_DOMAIN').$val;
                    	$objPHPExcel->getActiveSheet(0)->getCell($letter_arr[$let].($h+2))->getHyperlink()->setUrl($val);
                	}
                }
                $objPHPExcel->getActiveSheet(0)->setCellValue($letter_arr[$let].($h+2),' '.$val);
                $objPHPExcel->getActiveSheet()->getColumnDimension($letter_arr[$let])->setWidth(25);           
                $let++;
            }
            $objPHPExcel->getActiveSheet()->getStyle('A1:'.$letter_arr[$let].'2')->getFont()->setBold(true);

            $objPHPExcel->getActiveSheet()->getRowDimension($h)->setRowHeight(22); 
            $h++;
            $objPHPExcel->getActiveSheet()->getRowDimension(($h+1))->setRowHeight(22);
        }
        $newH = $h+1;
        $objPHPExcel->getActiveSheet()->getStyle('A1'.':'.$letter_arr[$let-1].$newH)->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN); 

        // Rename sheet    
        $objPHPExcel->getActiveSheet()->setTitle($filename);
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet    
        $objPHPExcel->setActiveSheetIndex(0);    
            
        header('Content-Type: application/vnd.ms-excel; charset="UTF-8"');
        header("Content-type:application/octet-stream");
        header("Accept-Ranges:bytes");
        header('Content-Disposition: attachment; filename='.$filename.'.xls');
        header("Content-Transfer-Encoding: binary");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
        header("Pragma: no-cache");
        header("Expires: 0"); 
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5'); 
        $objWriter->save('php://output'); 
    
        //ob_end_clean();//清除缓冲区,避免乱码  
        //ob_clean();       
        exit;
    }
 	//对外生成--big
 	private function doimg() {
		// $this->createBg();
		$this->mergeQrcode();
		$this->createFont();
		$this->outPut($this->qrnewfile);
 	}
 	//对外生成--small
 	private function doimgSmall(){
		// $this->createBgSmall(300,400);
		$this->mergeQrcodeSmall(300,400);
		$this->createFontSmall(300,400);
		$this->outPut($this->qrnewfilesmall); 		
 	}
 	//二维码信息入库
 	public function saveInfoToBase(){
 		$params['qr_code_num'] = $this->snvalue;
 		$has = D('ShopQrCode')->where($params)->getField('id');
 		$params['qr_code_url'] = str_replace('./Public/', '/Public/', $this->qrfile);
 		$params['billboard_img_url'] = str_replace('./Public/', '/Public/', $this->qrnewfile);
 		$params['paster_img_url'] = str_replace('./Public/', '/Public/', $this->qrnewfilesmall);
 		$params['create_time'] = time();
 		$params['status'] = 0;
 		$params['is_del'] = 0;
 		if(empty($has)){
 		$insertid = D('ShopQrCode')->add($params);
	 		if($insertid){
	 			return 1;
	 		}else{
	 			return -1;
	 		}
 		}else{
 			return 1;
 		}
 	}
}
