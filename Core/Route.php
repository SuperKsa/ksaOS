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

	static function Fun($str=null){
	    static $name = '';
	    if($str){
            $name = $str;
        }
	    return $name;
    }
	
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



		$loadFile = 0;
		$Dir = PATHS;
		$R = explode('/', $C['R']);
        //如果路由参数小于3个 则用index补充为固定三个
        for($i=0; $i<= 3-count($R); $i++){
            $R[] = 'index';
        }

        $Class = 'ksaOS\APP';
        $Loads = [];
        $classList = [];
        $FunK = 0;
		foreach($R as $key => $value){
            if(is_dir($Dir)){
                //前3层检查语言包/公共包
                if($key <3) {
                    //检测该目录下的语言包 并加载
                    if (is_file($Dir . '_lang.php')) {
                        $Loads[] = ['class'=>$Class, 'file'=>$Dir.'_lang.php'];
                    }
                    //检测该目录下的公共文件 并加载
                    if (is_file($Dir . '_common.php')) {
                        $Loads[] = ['class'=>$Class, 'file'=>$Dir.'_common.php'];
                    }
                    //检测该目录下的公共文件 并加载
                    if (is_file($Dir . '_function.php')) {
                        $Loads[] = ['class'=>$Class, 'file'=>$Dir.'_function.php'];
                    }
                }
                //查找路由脚本是否存在， 并载入
                if(is_file($Dir.$value.'.php')) {
                    $Loads[] = ['key'=>$key, 'class'=>$Class, 'file'=>$Dir.$value.'.php'];
                    $FunK = $key;
                }

                $Class .= '_'.$value;
                $classList[] = $Class;
                //第一层如果找不到目录则在model目录下查找
                if($key ===0 && !is_dir($Dir.$value)){
                    $Dir .= 'model/';
                }

                $Dir .= $value.'/';
			}
		}
		//提取路由已匹配到文件的后一个序列作为触发函数
        $FunK ++;
        $Fun = $R[$FunK] ? $R[$FunK] : 'index';
		//触发函数第一个字符如果是数字 则以index作为触发函数
        $Fun = is_numeric(substr($Fun,0,1)) ? 'index' : $Fun;
        self::Fun($Fun);
        unset($Fun);

		$isInit = 0;
		if($Loads){
            foreach($Loads as $value){
                include_once $value['file'];
            }

            //class逐级检查，取存在的最后一个new
            foreach($classList as $key => $value){
                if(!class_exists($value,false)){
                    unset($classList[$key]);
                }
            }

            $Class = end($classList);
            if($Class) {
                if (class_exists($Class, false)) {
                    $OBJ = new $Class;
                }
                if (method_exists($OBJ, 'common')) {
                    $OBJ->common();
                }
            }
            $Fun = self::Fun();
            //Fun不能为common 并且 是一个公开函数
			if($OBJ && $Fun && strtolower($Fun) !='common' && method_exists($OBJ, $Fun) && is_callable([$OBJ, $Fun])){
				$OBJ->$Fun();
                $isInit = 1;
			}
		}

		if(!$isInit){
		    //找不到初始化函数时执行回调
            if (method_exists($OBJ, 'NotFound')) {
                $OBJ->NotFound();
            }
            header('HTTP/1.1 404 Not Found');
            exit;
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
			if(!is_null($value)){
				$R[$k] = $value;
				$rk = 'R-'.$i;
				$C[$rk] = $value;
				$C['MOD'][] = $value;
				APP::$MOD[] = $value;
			}
		}
		$C['R'] =  implode('/',$R);
	}
}