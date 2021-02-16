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
    public static $MOD = []; //url路由部分的模块参数信息 http://***/mod0/mod1/mod2...
	
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

	public static function Success($msg='', $data=[], $confirm=false, $url=''){
	    if(!$data){
            $data = ['success'=>1];
        }
	    self::Msg($msg, 1, $data, $confirm, $url);
    }

    /**
     * 全局提示类
     * 此处中断程序继续执行
     * @param string $msg 提示信息
     * @param int $success 提示类型 0=失败 1=成功
     * @param array $data 返回给前台的数据
     * @param bool $confirm 该消息是否需经过前台确认
     * @param string $url 是否需要跳转到指定URL
     */
    public static function Msg($msg='', $success=0, $data=[], $confirm=false, $url='', $isLogin=false){
        global $C;
        $data = is_array($data) ? $data : [];
        $success = $success ? $success : ($data['success'] ? $data['success'] : 0);
        if($C['ajax']){
            $user = [];
            foreach($C['user'] as $key => $value){
                if(in_array($key, ['uid','name','avatar','sex'])){
                    $user[$key] = $value;
                }
            }
            $dt = [
                'uid' => $C['uid'],
                'token' => $C['token'],
                'isLogin' => $isLogin,
                'user' => $user,
                'msg' => $msg,
                'success' => $success,
                'confirm' => $confirm,
                'locationUrl' => $url,
                'result' => $data
            ];
            echo json_encode($dt,JSON_UNESCAPED_UNICODE);
        }else{
            include template::show('common/msg');
        }
        exit;
    }
	
	/**
	 * 静态初始化本类
	 * Woker框架不能使用此函数 应该使用 __Run()
	 * 触发 new Service
	 */
	public static function Run($Fun=null){
		parent::hook(__CLASS__ , __FUNCTION__);
		
		global $C;
		self::__Run();
		
		if($Fun && gettype($Fun) =='object'){
			call_user_func($Fun);
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
