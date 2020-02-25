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
		
		if(!$C['R']){
			return false;
		}
		if(defined('ROUTE_INIT___')){
			return ROUTE_INIT___;
		}
		define('ROUTE_INIT___', true);
		
		$Class = 'ksaOS\APP';
		$Fun = $C['R-1'];
		
		$loadFile = 0;
		$Dir = PATHS;
		$R = explode('/', $C['R']);
		$Rn = count($R);
		$Fun = $Rn >3 ? array_pop($R) : end($R);
		$Loads = [];
        $upDir = '';

        foreach($R as $key => $value){
            if($key >2){
                unset($R[$key]);
            }
        }
        //如果路由参数小于3个 则用index补充为固定三个
        for($i=0; $i<3-$Rn; $i++){
            $R[] = 'index';
        }
		foreach($R as $key => $value){

            if(is_dir($Dir)){
                //前3层检查语言包/公共包
                if($key <3) {
                    //检测该目录下的语言包 并加载
                    if (is_file($Dir . '_lang.php')) {
                        $Loads[] = $Dir.'_lang.php';
                    }
                    //检测该目录下的公共文件 并加载
                    if (is_file($Dir . '_common.php')) {
                        $Loads[] = $Dir.'_common.php';
                    }
                }
                if(is_file($Dir.$value.'.php')) {
                    $Loads[] = $Dir.$value.'.php';
                }
                $Class .= '_'.$value;
                //第一层如果找不到目录则在model目录下查找
                if($key ===0 && !is_dir($Dir.$value)){
                    $Dir .= 'model/';
                }

                $Dir .= $value.'/';
			}
		}
		$__M_FunInit = 0;
		if($Loads){
            foreach($Loads as $value){
                include_once $value;
            }

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

			if($OBJ && $Fun && method_exists($OBJ, $Fun)){
				$OBJ->$Fun();
				$__M_FunInit = 1;
			}else{
				throw new \Exception('错误的参数：'.$C['R'].' / CLASS:'.$Class);
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
		$R = trim($_GET['R'],'/ ');
		$R = preg_replace('/\/\//', '/', $R);
		$R = explode('/',$R);
		//R参数安全过滤只允许字母、数字、下划线、横杠
		$i = 0;
		foreach($R as $k => $value){
			$i ++;
			$value = urldecode(trim($value));
			$value = preg_replace('/[^a-z0-9_\-]/i','',$value);
			if($value){
				$R[$k] = $value;
				$rk = 'R-'.$i;
				$C[$rk] = $value;
				$C['MOD'][] = $value;
			}
		}
		$C['R'] =  implode('/',$R);
	}
}