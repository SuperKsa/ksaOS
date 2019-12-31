<?php

/**
 * Class自动加载
 * 所有底层文件名首字母必须大写！！！
 * @date    2019-12-18 22:27:17
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Loader.php (ksaos / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Loader {
	const _name = 'ksaOS Autoload处理类';
	
	private static $_F;

	public static function register($load=''){
		// 注册系统自动加载
		spl_autoload_register($load ?: 'ksaOS\Loader::load', true, true);
	}
	public static function load($class='') {
		$spname = strtolower(__NAMESPACE__).'\\'; //当前命名空间
		//不是ksaOS的命名空间直接略过不处理
		if(substr(strtolower($class),0,6) != $spname){
			return false;
		}
		self::_ksaOSLoad($class);
	}
	
	private static function _ksaOSLoad($class=''){
		//处理ksaOS的路由
		$K = md5($class);
		//已标记过的class不再处理
		if(isset(self::$_F[$K])){
			return false;
		}
		
		$spname = strtolower(__NAMESPACE__).'\\'; //当前命名空间
		if(substr(strtolower($class),0,6) == $spname){
			$class = substr($class,6);
		}
		//命名空间转换为路径
		//如果命名空间有子级则每个命名空间首字母必须大写(除首字母外其余均小写)
		if(strpos($class, '\\') !== false){
			$cl = [];
			foreach(explode('\\', $class) as $i => $v){
				$v = strtolower($v);
				$cl[] = ucfirst($v);
			}
			$class = implode('/',$cl);
			unset($cl);
		//调用单类文件时 文件名首字母大写 其余均小写
		}else{
			$class = ucfirst(strtolower($class));
		}
		
		//文件名首字母必须大写
		$file = $class.'.php';
		$core = KSAOS.'Core/';
		$model = KSAOS.'Model/';
		$loadFile = NULL;
		//从Core核心层找文件
		if(is_file($core.$file)){
			$loadFile = $core.$file;
		//Core找不到再从Model扩展层找
		}elseif(is_file($model.$file)){
			$loadFile = $model.$file;
		}
		//有文件时加载文件 否则不处理
		if($loadFile){
			include_once $loadFile;
		}
		//标记该class已被处理过
		self::$_F[$K] = true;
	}


	/**
	 * 钩子函数
	 * @global array $_HOOK_
	 * @param type $evn 钩子位置（类名::函数名 如：APP::Run）不区分大小写
	 * @param type $file 钩子文件或者函数 （文件为相对根目录位置） 文件路径区分大小写
	 */
	static function Hook($evn='', $hook=''){
		global $_HOOK_;
		if($evn && $hook){
			$evn = strtolower($evn);
			if(!isset($_HOOK_[$evn])){
				$_HOOK_[$evn] = [];
			}
			$_HOOK_[$evn][] = $hook;
		}
		return $_HOOK_;
	}
}