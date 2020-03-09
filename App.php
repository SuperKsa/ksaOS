<?php

/**
 * ksaOS核心驱动文件
 * 只能通过APP::__Run 或 APP::Run 初始化本类
 * @date    2019-12-18 22:22:47
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file core.php (ksaos / UTF-8)
 */
namespace ksaOS;
$C = $DEBUG = $_HOOK_ = [];

//ksaOS根路径
$__DIRS__ = str_replace('\\','/', dirname(__FILE__) );
define('ROOT', substr($__DIRS__, 0, -6).'/');
//KSAOS核心路径
define('KSAOS', $__DIRS__.'/');
//项目绝对路径
define('PATHS', ROOT.'App/');
unset($__DIRS__);
require_once KSAOS.'/Core/Loader.php';

//Class注册自动加载
Loader::register();
//错误处理
Error::register();


class APP extends Service{
	const _name = 'ksaOS触发驱动';

	protected static $_init;
	private static $_app;
	static $memory;
	static $DB;
	
	public static function OBJ() {
		return self::$_app;
	}

	public static function __Run() {
		if(!is_object(self::$_app)) {
			self::$_app = parent::_AP();
			self::$memory = self::$_app->_memory;
			self::$DB = self::$_app->_DB;
			parent::hook(__CLASS__ , __FUNCTION__);
		}
		return self::$_app;
	}
	
	//模块执行结束
	function __End(){
		self::$memory->close();
		self::$DB->close();
	}

    /**
     * 全局提示类
     * @param string $msg 提示信息
     * @param url $url 需要跳转的URL （为空时代表错误等级提示）
     * @param array $data 补充需要输出的数组
     */
    public static function Msg($msg='',$url='',$data=[]){
        global $C;

        $success = $url ? 1 : ($data['success'] ? $data['success'] : 0);
        if($C['ajax']){
            JSON($data,['msg'=>$msg,'success'=>$success,'locationUrl'=>$url]);
        }else{
            include template::show('common/msg');
        }
        exit;
    }
	
	/**
	 * 静态初始化本类
	 * Woker框架不能使用此函数 应该使用 __Run()
	 * 触发 new Service
	 * @return type
	 */
	public static function Run($Fun=false){
		parent::hook(__CLASS__ , __FUNCTION__);
		
		global $C;
		self::__Run();
		
		if(gettype($Fun) =='object'){
			$Fun();
		}
		self::Route()->Run();
		self::__End();
		$content = ob_get_contents();
		ob_end_clean();
		defined('GZIP') ? ob_start('ob_gzhandler') : ob_start();
		
		$staticUrl = $C['staticUrl'];
		$content = preg_replace('/(<([^>]+)=["|\'])index\.php\?R=/','$1'.$C['staticUrl'], $content);
		echo $content;
	}
}
