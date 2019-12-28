<?php

/**
 * 
 * 暂无介绍
 * @date    2019-12-7 0:04:05
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Image.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Image{
	const _name = 'ksaOS图片处理类';
	
	//图片类型
	private $Ext='' , $width=0 ,$height=0, $Thumb_width = 0, $Thumb_height = 0, $source, $target, $Dom, $thumbDom;
	private $Imagick = 0;
	private $TypeExts = [1 => 'gif', 2 => 'jpg', 3 => 'png'];
	private $Q = 100;

	function __construct() {
		$this->Imagick = extension_loaded('imagick') ? true : false;
	}

	/**
	 * 缩略图与裁切
	 * @param type $imgPath 源图路径
	 * @param type $savePath 缩略图保存路径
	 * @param type $width 缩略宽度
	 * @param type $height 缩略高度
	 * @param type $Crop 是否裁切 1=是 0=否（如果有裁切坐标则强制裁切）
	 * @param type $Cx 裁切坐标X
	 * @param type $Cy 裁切坐标Y
	 * @return boolean
	 */
	public function Thumb($imgPath, $savePath, $width, $height) {
		$this->Info = APP::File()->PicInfo($imgPath);
		if(!$this->Info){
			return false;
		}

		$this->source = $imgPath;
		$this->target = $savePath;
		$this->Thumb_width = $width;
		$this->Thumb_height = $height;
		//图片的类型
		$this->Ext = $this->TypeExts[$this->Info['type']];
		//载入源图
		$this->loadSrc();

		$this->width = $this->Info['width'];
		$this->height = $this->Info['height'];

		if($this->Imagick){
			$this->_GD();
		}else{
			$this->_GD();
		}
		APP::hook(__CLASS__ , __FUNCTION__);
		return $this->_Save();
	}

	private function _IM(){
		list($Tratio, $ratio, $ThumbW, $ThumbH, $domW, $domH) = $this->SizeVal();

		$im = new Imagick();
		$im->readImage(realpath($this->source)); //读取源图

		$im->setImageCompressionQuality($this->Q); //设定图片质量
		$im->cropImage($this->width, $this->height, $this->Cx, $this->Cy); //创建缩略图画布
		if(!$im->writeImage($this->target)) { //写入缩略图
			$im->destroy();
			return false;
		}
		$im->readImage(realpath($this->target));
		$im->setImageCompressionQuality($this->Q);
		$im->thumbnailImage($ThumbW, $ThumbH,true);
		$im->resizeImage($ThumbW, $ThumbH);
		$im->setGravity(imagick::GRAVITY_CENTER );
		$im->extentImage($ThumbW, $ThumbH);

		if(!$im->writeImage($this->target)) {
			$im->destroy();
			return false;
		}
		$im->destroy();
		return true;
	}


	private function _GD(){
		list($Tratio, $ratio, $ThumbW, $ThumbH, $domW, $domH) = $this->SizeVal();

		if($ratio >= $Tratio) {
			$this->thumbDom = imagecreatetruecolor($this->Thumb_width,($this->Thumb_width)/$ratio);
			if($this->Ext == 'png') {
				$this->createWhiteImg($this->thumbDom);
			}
		}else{
			$this->thumbDom = imagecreatetruecolor(($this->Thumb_height)*$ratio,$this->Thumb_height);
			if($this->Ext == 'png') {
				$this->createWhiteImg($this->thumbDom);
			}
		}

		imagecopyresampled($this->thumbDom, $this->Dom, 0, 0, $this->Cx, $this->Cy, $ThumbW, $ThumbH, $domW, $domH);
		return true;
	}

	private function SizeVal(){
		//缩略图比例
		$Tratio = round($this->Thumb_width / $this->Thumb_height,4);
		//源图比例
		$ratio = round($this->width / $this->height,4);
		$ThumbW = $ThumbH = $domW = $domH = 0;

		if($ratio >= $Tratio) {
			$ThumbW = $this->Thumb_width;
			$ThumbH = ($this->Thumb_width)/$ratio;
			$domW =  $this->width;
			$domH = $this->height;
		}else{
			$ThumbW = ($this->Thumb_height)*$ratio;
			$ThumbH = $this->Thumb_height;
			$domW =  $this->width;
			$domH = $this->height;
		}
		return [$Tratio, $ratio, $ThumbW, $ThumbH, $domW, $domH];
	}


	private function _Save(){
		if($this->Ext =='png') {
			imagesavealpha($this->thumbDom, true);
		}

		if($this->Ext =='png') {
			imagepng($this->thumbDom,$this->target);
		}elseif($this->Ext =='gif') {
			imagegif($this->thumbDom,$this->target);
		}else{
			imagejpeg($this->thumbDom,$this->target);
		}
		//释放资源
		ImageDestroy($this->Dom);
		ImageDestroy($this->thumbDom);
		APP::hook(__CLASS__ , __FUNCTION__);
		return true;
	}


	/**
	 * 创建一张透明空白画布
	 * @param type $dom
	 */
	private function createWhiteImg($dom){
		imagefill($dom, 0, 0, imagecolorallocatealpha($dom, 255, 255, 255, 127));
	}

	/**
	 * 载入源图
	 */
	private function loadSrc() {
		switch($this->Ext){
			case 'jpg':
				$this->Dom = imagecreatefromjpeg($this->source);
				break;
			case 'gif':
				$this->Dom = imagecreatefromgif($this->source);
				break;
			case 'png':
				$this->Dom = imagecreatefrompng($this->source);
				break;
			default:
				break;
		}
	}

}