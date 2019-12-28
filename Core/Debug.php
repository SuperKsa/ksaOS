<?php

/**
 * 
 * 暂无介绍
 * @date    2019-12-15 21:28:23
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Debug.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Debug{
	const _name = 'ksaOS debug处理类';
	
	/**
	 * debug调试函数
	 * @global type $C
	 * @global type $DEBUG
	 * @param type $value
	 * @param type $dump
	 */
	public static function debug($value=NULL,$dump=0){
		if($value === NULL){
			global $C, $DEBUG;
			foreach($DEBUG['DBquery'] as $key => $val){
				unset($val[2],$val[3],$val[4]);
				$DEBUG['DBquery'][$key] = $val;
			}
			self::End('Other');
			self::End('ALL');
			$value = ['DEBUG'=>$DEBUG,'SERVER'=>$_SERVER,'C'=>$C,'已定义的函数'=>get_defined_functions()];
		}
		echo '<pre>';
		if($dump){
			var_dump($value);
		}else{
			print_r($value);
		}
		echo '</pre>';
		exit;
	}
	/**
	 * 给$DEBUG变量增加数据
	 * @global \ksaOS\type $DEBUG
	 * @param type $key 键名
	 * @param type $value 值
	 * @return boolean
	 */
	public function set($key='',$value=''){
		if(defined('DEBUGS') && DEBUGS && $key && $value) {
			global $DEBUG;
			$DEBUG[$key][] = $value;
			return true;
		}
		return false;
	}


	/**
	 * 调试模式开始函数
	 * 例子：
	 * APP::debug()->Start('members:login');
	 * ...
	 * APP::debug()->End('members:login');
	 * 
	 * @global type $DEBUG
	 * @param string $name
	 * @return type
	 */
	public static function Start($name = 'ALL'){
		if(!defined('DEBUGS') || !DEBUGS) {
			return;
		}
		global $DEBUG;
		$key = 'system';
		if(!$name) {$name = 'ALL';}
		if(strpos($name,':')) {
			list($key, $name) = explode(':', $name);
		}
		if(!isset($DEBUG['memory'])) {
			$DEBUG['memory'] = [];
		}
		if(!isset($DEBUG['memory'][$key])) {
			$DEBUG['memory'][$key] = [];
			$DEBUG['memory'][$key]['sum'] = 0; //总类模块数量
		}
		$DEBUG['memory'][$key][$name]['times'] = 0; //总消耗时间
		$DEBUG['memory'][$key][$name]['start'] = microtime(true); //开始时间
		$DEBUG['memory'][$key][$name]['start_memory_get_usage'] = memory_get_usage(); //PHP内存量
		$DEBUG['memory'][$key][$name]['start_memory_get_peak_usage'] = memory_get_peak_usage(); //分配给 PHP 内存的峰值
	}

	/**
	 * 调试模式结束函数
	 * @global type $DEBUG
	 * @param type $name
	 * @return string
	 */
	public static function End($name = 'ALL') {

		if(!defined('DEBUGS') || !DEBUGS) {
			return;
		}
		global $DEBUG;
		$key = 'system';
		if(strpos($name,':')) {
			list($key, $name) = explode(':', $name);
		}
		if(isset($DEBUG['memory'][$key][$name]['start'])) {
			$DEBUG['memory'][$key][$name]['end'] = microtime(true);//程序结束时间
			$DEBUG['memory'][$key][$name]['times'] = round(($DEBUG['memory'][$key][$name]['end'] - $DEBUG['memory'][$key][$name]['start']), 7).'s';//程序最终执行时间
			$DEBUG['memory'][$key]['sum'] ++;
			$DEBUG['memory'][$key][$name]['stop_memory_get_usage'] = memory_get_usage(); //结束时的内存用量
			$DEBUG['memory'][$key][$name]['memory'] = round((memory_get_usage() - $DEBUG['memory'][$key][$name]['start_memory_get_usage']) / 1024,2).' kb'; //内存总使用量
			$DEBUG['memory'][$key][$name]['stop_memory_get_peak_usage'] = memory_get_peak_usage();
		}
		return $DEBUG['memory'][$key][$name];
	}
}