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
     * 给图片添加水印
     *
     * @param $source string 原图路径
     * @param $toPath string 保存路径
     * @param $watermark string 水印png路径
     * @param $pos       int 水印位置 按下面的数字对应位置
     *                   7 8 9
     *                   4 5 6
     *                   1 2 3
     * @param int $transparency 设置水印透明度，范围0-100
     *
     * @return bool|string
     */
    public static function Watermark_Img($source='', $savePath='', $watermark='', $pos=3, $transparency = 40){
        if(!$source || !is_file($source)){
            return false;
        }
        // 加载水印和照片
        $stamp = imagecreatefrompng($watermark);
        $extension = substr($source, strrpos($source, '.') + 1);
        if ($extension == 'jpg') {
            $im = imagecreatefromjpeg($source);
        } else if ($extension == 'png') {
            $im = imagecreatefrompng($source);
        } else if ($extension == 'gif') {
            $im = imagecreatefromgif($source);
        } else {
            return false;
        }
        
        // 设置水印的边距并获取水印图像的高度/宽度
        $marge_right = 10; // 设置水印右边距
        $marge_bottom = 10; // 设置水印底边距
        $sx = imagesx($stamp); // 获取水印图像的宽度
        $sy = imagesy($stamp); // 获取水印图像的高度
        
        // 将水印图像复制到我们的照片上，使用边距偏移和照片宽度来计算水印的位置。
        switch ($pos) {
            case 1:
                $x = $marge_right;
                $y = imagesy($im) - $sy - $marge_bottom;
                break;
            case 2:
                $x = (imagesx($im) - $sx) / 2;
                $y = imagesy($im) - $sy - $marge_bottom;
                break;
            case 3:
                $x = imagesx($im) - $sx - $marge_right;
                $y = imagesy($im) - $sy - $marge_bottom;
                break;
            case 4:
                $x = $marge_right;
                $y = (imagesy($im) - $sy) / 2;
                break;
            case 5:
                $x = (imagesx($im) - $sx) / 2;
                $y = (imagesy($im) - $sy) / 2;
                break;
            case 6:
                $x = imagesx($im) - $sx - $marge_right;
                $y = (imagesy($im) - $sy) / 2;
                break;
            case 7:
                $x = $marge_right;
                $y = $marge_bottom;
                break;
            case 8:
                $x = (imagesx($im) - $sx) / 2;
                $y = $marge_bottom;
                break;
            case 9:
                $x = imagesx($im) - $sx - $marge_right;
                $y = $marge_bottom;
                break;
            default:
                return false;
        }
        //透明度合并水印和图像
        imagecopymerge($im, $stamp, $x, $y, 0, 0, imagesx($stamp), imagesy($stamp), $transparency);
        
        $isSave = self::IM_saveImg($im, $savePath);
        imagedestroy($im); // 释放内存
        imagedestroy($stamp); // 释放内存
        return $isSave ? $savePath : false;
    }
    
    /**
     * 根据路径在浏览器中输出图片
     * @param $source
     *
     * @return false|void
     */
    public static function Show($source){
        $extension = substr($source, strrpos($source, '.') + 1);
        if ($extension == 'jpg') {
            $im = imagecreatefromjpeg($source);
        } else if ($extension == 'png') {
            $im = imagecreatefrompng($source);
        } else if ($extension == 'gif') {
            $im = imagecreatefromgif($source);
        } else {
            return false;
        }
        /** http请求响应类型设置为 image/png 以便直接显示为图片 */
        header('Content-Type:image/'.$extension);
        imagepng($im);//输入图片到浏览器或者文件
        imagedestroy($im); // 释放内存
    }
    
    /**
     * IM保存图片
     * @param $im
     * @param $savePath
     *
     * @return false|void
     */
    public static function IM_saveImg($im, $savePath){
        // 保存图片并释放内存
        $extension = pathinfo($savePath, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'jpg':
                return imagejpeg($im, $savePath, 100);
            case 'jpeg':
                return imagejpeg($im, $savePath, 100);
            case 'png':
                return imagepng($im, $savePath, 9);
            case 'gif':
                return imagegif($im, $savePath);
            default:
                return false;
        }
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
		[$Tratio, $ratio, $ThumbW, $ThumbH, $domW, $domH] = $this->SizeVal();

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
		[$Tratio, $ratio, $ThumbW, $ThumbH, $domW, $domH] = $this->SizeVal();

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