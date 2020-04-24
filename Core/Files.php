<?php

/**
 * Files文件操作类
 *  暂无介绍
 * @date    2019-11-28 22:29:42
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Files.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Files{
	const _name = 'ksaOS文件处理类';
	/**
	 * 文件路径格式化为安全路径
	 * 涉及与用户交互的文件操作必须使用该函数进行一次过滤，防止路径越权
	 * 主要处理路径中:
	 *	非首次出现的 ./ (如././aa/./)
	 *	所有的 ../ (如 ../aa/../)
	 * @param string $path 需要格式化的路径地址
	 * @return string
	 */
	public static function path($path=''){
		if(strpos($path,'\\') !== false){
			$path = str_replace('\\','/',$path);
		}
		$path = preg_replace('/\.\./','.',$path);
		$path = preg_replace('/\/\//','/',$path);
		$path = preg_replace('/\/\.\//','/',$path);
		if(strpos($path, '//') !== false || strpos($path, '../') !== false || strpos($path, '..') !== false){
			$path = path($path);
		}
		if($path){
            $path = rtrim($path,'/').'/';
            return $path;
        }
		return NULL;
	}
	
	/**
	 * 目录创建函数
	 * @param string $dir 需要创建的路径 如果没有则一直创建至末端目录
	 * @param string $mode
	 * @return boolean
	 */
	public static function mkdir($dir='', $mode = 0777){
		if(!is_dir($dir)) {
			self::mkdir(dirname($dir), $mode);
			@mkdir($dir, $mode);
		}
		return true;
	}

    /**
     * 返回路径中的目录路径
     * @param string $dir 路径地址（可以包含文件名）
     * @param int $back 返回目录需要前进的层级次数
     * @return bool|string 返回目录路径 最后有/
     */
	public static function dir($dir='', $back=0){
		if($dir){
            $back ++;
		    for($i=0; $i<$back; $i++){
                $dir = pathinfo($dir)['dirname'];
            }
			if($dir){
			    return str_replace(ROOT,'',$dir).'/';
            }
		}
		return false;
	}


	/**
	 * 判断是否是一个目录名称
	 * 目录名不能包含：\<>/:*?"|'
	 * @param string $name
	 * @return boolean/name 成功返回目录名称
	 */
	public static function dirName($name=''){
		return !preg_match('/\\<>\/:\*\?"\|\'/', $name) ? $name : false;
	}

	/**
	 * 获取路径中的文件名
	 * @param string $file 文件名或路径
	 * @param int $isext 是否带后缀 1=是 0=否
	 * @return string
	 */
	public static function name($file, $isext=1){
		$info = pathinfo($file);
		$file = $info['basename'];
		if(!$isext){
			$file = $info['filename'];
		}
		return $file;
	}
	
	/**
	 * 获取指定目录下的文件列表
	 * @param string $path 绝对路径
	 * @param array/string $ext 搜索规则(字符限制：a-z0-9_-*.) 如：'php' || 'js' || 'a_*.php' || ['a_*.php','a_*_list.php','js','css']
	 * @return array 文件名和目录名数组（仅名称不带路径）
	 */
	public static function dirs($path='',$ext=[]){
		$ext = $ext ? $ext : ['*'];
		if(!is_array($ext)){
			$ext = [$ext];
		}
		$list = [];
		if(is_dir($path)){
			foreach($ext as $e){
				$e = trim(preg_replace('/[^a-z0-9\*\_\-\.]/i', '', $e));
				if($e){
					if(strpos($e,'.') === false){
						$e = '*.'.$e;
					}
					$dt = glob($path.'/'.strtolower($e));
					foreach($dt as $value){
						$value = str_replace($path,'',$value);
						$list[] = self::name($value);
					}
				}
			}
		}
		return $list;
	}
	
	/*
	 * 读取指定文件内容
	 */
	public static function contents($file){
		if(is_file($file)){
			return @file_get_contents($file);
		}
		return '';
	}

    /**
     * 获取文件名中的后缀名
     * @param string $file 获取文件名中的后缀名
     * @param int $remove 移除后缀（1=是 0=否） 默认=0
     * @return string 返回后缀名或者file
     */
	public static function Ext($file='', $remove=0){
	    if($file) {
            if ($remove) {
                $file = explode('.', $file);
                array_pop($file);
                return implode('', $file);
            } else {
                $ext = explode('.', $file);
                $ext = end($ext);
                $ext = strtolower($ext);
                //后缀名只支持字母与数字
                if (!preg_match('/[^a-z0-9]/', $ext)) {
                    return $ext;
                }
            }
        }
	}
	
	/**
	 * 获取本地图片信息
	 * 也可用于判断是否为图片
	 * @param string $src 本地路径
	 * @return array 是图片则输出数据 否则为空数组
	 */
	public static function picInfo($src=''){
		$Mimes = ['image/png','image/x-png','image/jpg','image/jpe','image/jpeg','image/pjpeg','image/gif','image/webp','image/*'];
		if(is_file($src)){
			$info = (array)getimagesize($src);
			if($info[0]>0 && $info[1]>0 && in_array($info['mime'],$Mimes)){
				return [
					'width' => $info[0] ? $info[0] : 0,
					'height' => $info[1] ? $info[1] : 0,
					'type' => $info[2] ? $info[2] : 0,
					'bits' => $info['bits'] ? $info['bits'] : 0,
					'mime' => $info['mime'] ? $info['mime'] : 0,
				];
			}
		}
		return [];
	}
	
	public static function getContent($file){
		$content = '';
		if(($fp = @fopen($file, 'rb'))) {
			while (!feof($fp)) {
				$content .= @fread($fp, 1024 * 1024);
			}
			fclose($fp);
		}else{
			$content = file_get_contents($file);
		}
		
		return $content;
	}
	
	/**
	 * 获取图片处理组件
	 * @return string GD/IM
	 */
	public static function getImgDrive(){
		if(extension_loaded('gd')){
			return 'GD';
		}elseif(extension_loaded('imagick')){
			return 'IM';
		}else{
			return NULL;
		}
	}
}
