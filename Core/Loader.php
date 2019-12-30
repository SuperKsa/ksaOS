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
		$cl = strtolower($class);
		$spname = strtolower(__NAMESPACE__).'\\'; //当前命名空间
		//不是ksaOS的命名空间直接略过不处理
		if(substr($cl,0,6) != $spname){
			return false;
		}
		$K = md5($class);
		if(isset(self::$_F[$K])){
			return false;
		}
		$S = $class;
		$F = '';
		$D = KSAOS.'Core/';
		if(strpos($S, '\\') !== false){
			if(strtolower(substr($S,0,6)) == $spname){
				$S = substr($S,6);
			}
			$S = explode('\\', $S);
			foreach($S as $i => $v){
				$v = strtolower($v);
				$S[$i] = ucfirst($v);
			}
			$S = implode('/',$S);
			$F = ucfirst($S).'.php';
		}else{
			$F = ucfirst(strtolower($class)).'.php';
		}
		if(is_file($D.$F)){
			include_once $D.$F;
			self::$_F[$K] = true;
			return true;
		}else{
			throw new \Exception('文件不存在：'.$D.$F);
		}
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