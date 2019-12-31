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
	
	function CoreInit(){}
	//$C[M]开始前
	function common(){}
	
	//$C[M] 至 $C[D]
	function Start(){}
	
	
	
	//模块前端被访问
	function view(){}
	
	//模块POST提交前
	function Post(){}
	
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
		self::OBJ()->Route()->Run();
		self::__End();
		/*
		$content = ob_get_contents();
		ob_end_clean();
		defined('GZIP') ? ob_start('ob_gzhandler') : ob_start();
		
		$staticUrl = $C['staticUrl'];
		
		$content = preg_replace_callback('/<(img|script|a|link)([^>]+)(src|href)="(.*?)"([^>]+)?>/i', function($a)use($staticUrl){
			if($a[4]){
				$r = $a[4];
				$s = parse_url($r);
				if(!$s['host']){
					$s['path'] = preg_replace('/^(\.\/|\/)/','',$s['path']);
					$r = $staticUrl.$s['path'].($s['query'] ? '?'.$s['query'] : '');
				}
				$a[4] = '="'.$r.'"';
			}
			unset($a[0]);
			$r = '<'.implode('',$a).'>';
			return $r;
		}, $content);
		
		echo $content;
		 */
	}
}
