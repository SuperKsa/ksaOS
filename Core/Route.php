<?php

/**
 * 路由处理层
 * 注：R参数不完整情况下将会使用DEF默认值
 * 必须规则：
 *	x.php?R=cms/article/index
 * 转化为：
 *	$C['R'] = cms/article/index
 *	$C['M'] = cms
 *	$C['O'] = article
 *	$C['D'] = index
 * 
 * 在项目入口中提前默认某个值用法：
 *	Route::deft('O','article');
 * 
 * @date    2019-12-4 3:54:26
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Route.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Route{
	
	const _name = 'ksaOS URL路由处理类';
	
	const DEF = [
		'M'=>'index', //模型 默认值
		'O'=>'index', //功能 默认值
		'D'=>'index' //动作 默认值
	];
	
	/**
	 * 入口文件执行函数
	 * @global type $C
	 * @param type $ModelName 对应绑定的模块目录 默认model
	 */
	public static function Run($ModelName='model'){
		global $C;
		
		if(!$C['M']){
			return false;
		}
		if(defined('ROUTE_INIT___')){
			return ROUTE_INIT___;
		}
		define('ROUTE_INIT___', true);
		
		$Class = 'ksaOS\APP';
		$Fun = $C['D'];
		
		$loadFile = 0;
		$Dir = PATHS;
		$R = explode('/',$C['R']);
		$Fun = count($R) >3 ? array_pop($R) : end($R);
		$Loads = [];
		$upValue = '';
		foreach($R as $key => $value){
			if(is_file($Dir.$upValue.$value.'.php')){
				$Loads[] = $Dir.$upValue.$value.'.php';
				include_once $Dir.$upValue.$value.'.php';
				if($key == 0){
					$Dir .= $ModelName.'/';
				}
				$Dir .= $value.'/';
				$Class .= '_'.$value;
				$upValue .= $value.'_';
			}
		}
		$__M_FunInit = 0;
		if($Loads){
			if(class_exists($Class,false)){
				$OBJ = new $Class;
			}
			if(method_exists($OBJ, 'common')){
				$OBJ->common();
			}
			if(method_exists($OBJ, 'commonPost')){
				$OBJ->commonPost();
			}
			if(method_exists($OBJ, 'commonView')){
				$OBJ->commonView();
			}
			if(!method_exists($OBJ, $Fun)){
				$Fun = 'index';
			}
			if(method_exists($OBJ, $Fun)){
				$OBJ->$Fun();
				$__M_FunInit = 1;
			}else{
				Msg('错误的参数：'.$C['R']);
			}
		}
		
		if(!$__M_FunInit){
			throw new \Exception('错误的访问：'.$Class,404);
		}
	}
	
	/**
	 * 初始化（核心底层调用）
	 * @global type $C
	 */
	public function init(){
		global $C;
		if(!isset($_GET['R'])){
			return false;
		}
		$C['R'] = trim($_GET['R'],'/ ');
		$C['R'] = preg_replace('/\/\//', '/', $C['R']);
		$R = explode('/',$C['R']);
		//R参数安全过滤只允许字母、数字、下划线、横杠
		foreach($R as $k => $value){
			$value = urldecode(trim($value));
			$value = preg_replace('/[^a-z0-9_\-]/i','',$value);
			$R[$k] = $value ? $value : 'index';
		}
		for($i=0;$i<3;$i++){
			if(!isset($R[$i])){
				$R[$i] = 'index';
			}
		}
		$C['M'] = isset($R[0]) && $R[0] ? $R[0] : self::DEF['M']; //模型 无请求默认
		$C['O'] = isset($R[1]) && $R[1] ? $R[1] : self::DEF['O']; //功能 无请求默认
		$C['D'] = isset($R[2]) && $R[2] ? $R[2] : self::DEF['D'];//动作 无请求默认
		$C['R'] =  implode('/',$R);
	}
	
	/**
	 * 默认某个值
	 * @global type $C
	 * @param type $K 可传：M O D
	 * @param type $str 对应值内容（只允许大小写+数字+下划线）
	 * @return type
	 */
	public static function deft($K='', $str=NULL){
		if(isset(self::DEF[$K])){
			global $C;
			if(!$C[$K] || $C[$K] === self::DEF[$K]){
				return self::___set($K, $str);
			}
		}
	}
	
	private function ___set($K='', $str=NULL){
		if(in_array($K,['M','O','D'])){
			global $C;
			if($str !== NULL){
				$str = preg_replace('/[^a-z0-9_]/','',$str);
				if($str){
					$C[$K] = $str;
					$C['R'] =  $C['M'].'/'.$C['O'].'/'.$C['D'];
				}
			}
			return $C[$K];
		}
	}
}