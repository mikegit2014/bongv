<?php
namespace Home\Controller;
use Think\Controller;

class QrcodeController extends Controller {
	private $code;//验证码
	private $logotext;
	private $width = 600;//宽度
	private $height = 650;//高度
	private $img;//图形资源句柄
	private $font;//指定的字体
	private $fontsize = 15;//指定字体大小
	private $fontcolor;//指定字体颜色
	private $logo;
	private $red; //背景颜色
	private $green;
	private $blue;
	public  $content;
	public  $qrfile;
	public  $qrlogofile;
	public  $qrnewfile;
	public  $snvalue;
	private $wxp;
	//构造方法初始化
	public function __construct() {
	    $this->font = './Tool/fonts/simhei.ttf';//注意字体路径要写对，否则显示不了图片
	    $this->red = 13;
	    $this->green = 148;
	    $this->blue = 30;
	    // $this->logo = './Tool/img/bv.png'; //准备好的logo图片
	    $this->snvalue = $this->createSn();
	   	$this->content = 'http://dianlf.bongv.com/Home/Index/index/code_num/'.$this->snvalue;//二维码内容
		$this->qrfile = './Public/qrcodeImg/'.$this->snvalue.'.png';
		$this->qrlogofile = './Public/qrcodeImg/logo-'.$this->snvalue.'.png';
		$this->qrnewfile = './Public/qrcodeImg/board-'.$this->snvalue.'.png'; 
		$this->wxp = './Tool/img/wxp.png';//微信支付图片 
	}
	//生成sn码：
	private function createSn(){
		return 123;
		$chars = "123456789";
        $str = "";
        for ( $i = 0; $i < 15; $i++ )  {
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
	}
 	//生成字符
 	private function createCode() {
  		$this->code = 'sn:'.$this->snvalue;
  		$this->logotext = mb_convert_encoding('店立方','html-entities','UTF-8');
 	}
 	//生成背景
	private function createBg() {
  		$this->img = imagecreatetruecolor($this->width, $this->height);
  		$color = imagecolorallocate($this->img, $this->red,$this->green,$this->blue);
  		imagefilledrectangle($this->img,0,$this->height,$this->width,0,$color);
 	}
 	//生成文字
	private function createFont() {
   		$this->fontcolor = imagecolorallocate($this->img,mt_rand(0,2),mt_rand(0,2),mt_rand(0,2));
   		imagettftext($this->img,$this->fontsize,0,$this->width/14,$this->height/20,$this->fontcolor,$this->font,$this->code);
   		imagettftext($this->img,$this->fontsize,0,$this->width-250,$this->height-50,$this->fontcolor,$this->font,$this->logotext);
 	}
 	//生成二维码
 	private function createQrcode() {
 		//二维码
 		import('QRcode', './Tool/qrcode', '.php');
		$errorCorrectionLevel = 'M';//容错级别 
		$matrixPointSize = 10;//生成图片大小 
		//生成二维码图片 
		\Tool\qrcode\QRcode::png($this->content, $this->qrfile, $errorCorrectionLevel, $matrixPointSize, 2);
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
 		$wxpay = imagecreatefromstring(file_get_contents($this->wxp));
 		$wxWidth = imagesx($wxpay);
 		$wxHeight = imagesy($wxpay);
 		if($this->logo !== false){
 			$QR = imagecreatefromstring(file_get_contents($this->qrlogofile)); 
 		}else{
 			$QR = imagecreatefromstring(file_get_contents($this->qrfile));
 		}
 		$oriWidth = imagesx($QR);
 		$oriHeight = imagesy($QR);

	 	$scaleH = ($this->height - 180)/$oriHeight;
	 	$scaleW = ($this->width - 100)/$oriWidth;
 		//合并二维码
 		imagecopyresampled($this->img, $QR, 50,50, 0, 0, $scaleW*$oriWidth,$oriHeight*$scaleH, $oriWidth,$oriHeight);
 		//合并微信支付图片
		imagecopyresampled($this->img, $wxpay, $this->width-150, $this->height-120, 0, 0, $wxWidth,$wxHeight, $wxWidth,$wxHeight);
 	}
	//输出
	private function outPut() {
		if($this->saveInfoToBase() > 0){
	    	header('Content-type:image/png');
	    	imagepng($this->img);
	    	// imagepng($this->img,$this->qrnewfile);
   		    imagedestroy($this->img);
		}else{
			unlink($this->qrfile);
			unlink($this->qrlogofile);
		    imagedestroy($this->img);
   			die('二维码生成错误');
		}
	}
 	//对外生成
 	public function doimg() {
  		$this->createBg();
		$this->createCode();
		$this->createQrcode();
		$this->mergeQrcode();
		$this->createFont();
		$this->outPut();
 	}
 	//图片存储到远程服务器
 	private function saveImgToServer(){

 	}
 	//二维码信息入库
 	public function saveInfoToBase(){
 		return 1;
 		$params['qr_code_num'] = $this->snvalue;
 		$params['qr_code_url'] = str_replace('./Public/', '/Public/', $this->qrfile);
 		$params['billboard_img_url'] = str_replace('./Public/', '/Public/', $this->qrnewfile);
 		$params['create_time'] = time();
 		$params['status'] = 0;
 		$params['is_del'] = 0;
 		$insertid = D('ShopQrCode')->add($params);
 		if($insertid){
 			return 1;
 		}else{
 			return -1;
 		}
 	}
}
