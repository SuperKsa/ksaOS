<?php

/**
 * 
 * 暂无介绍
 * @date    2019-12-18 22:48:04
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Service.php (ksaos / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

//是否开启GZIP
define('GZIP', function_exists('ob_gzhandler'));

//开启GET/POST/COOKIE预转义并给常量结果
define('GPC', function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc());

//当前时间戳 格林威治
define('TIME', time());

class Service{
	protected $_memory;
	protected $_DB;
	private $config = [];
	private $__Init = 0;
	
	function __construct(){
		
		if(!$this->__Init){
			$this->_config();
			$this->hook(__CLASS__ , __FUNCTION__);
			$this->Init();
			$this->__Init = 1;
		}
		return $this;
	}
	
	static function &_AP() {
		static $object;
		if(empty($object)) {
			$object = new self();
		}
		return $object;
	}
	
	private static function _HookRun($hook=NULL){
		if(!$hook){
			return false;
		}
		
		if(is_object($hook)){
			$hook();
		}elseif(is_array($hook)){
			foreach($hook as $val){
				self::_HookRun($val);
			}
		}elseif(is_string($hook)){
			if(!is_file(PATHS.$hook)){
				throw new \Exception('钩子文件不存在：'.$hook);
			}
			@include PATHS.$hook;
		}
	}


	public static function hook($class='',$fun=''){
		global $_HOOK_;
		
		$evn = strtolower($class.'::'.$fun);
		if(strpos($evn,'ksaos\\') === 0){
			$evn = substr($evn,6);
		}
		$hook = isset($_HOOK_[$evn]) ? $_HOOK_[$evn] : [];
		if($evn && $hook){
			foreach($hook as $value){
				self::_HookRun($value);
			}
		}
	}


	/**
	 * 初始化底层
	 * @return $this
	 */
	private function Init(){
		if(defined('__KSAOS_SERVICE_INIT__')){
			return;
		}
		$this->hook(__CLASS__ , __FUNCTION__);
		define('__KSAOS_SERVICE_INIT__', 1);
		
		//建立缓冲区 socket框架不做缓冲
		if(!defined('__BIGER__')){
			ob_start(defined('GZIP') && GZIP ? 'ob_gzhandler' : null);
		}
		define('DEBUGS', !defined('__BIGER__') && $this->config['debug'] ? true : false);
		$this->debug()->start('ALL');
		$this->debug()->start('Core');
		
		
		$this->_FunctionCore()->_setDefine()->_RAM()->__Db()->__Event()->__Setting()->__user()->__End();	
		return $this;
	}
	/**
	 * 载入配置文件
	 * @return $this
	 * @throws \Exception
	 */
	private function _config(){
		$file = ROOT.'config.php';
		if(!is_file($file)){
			throw new \Exception('配置文件config.php丢失');
		}
		@include_once ROOT.'config.php';
		$this->config = (array)$config;
		
		define('KSAOS_DB_PRE', $config['db']['pre'] ? $config['db']['pre'] : '');
		unset($config);
		return $this;
	}
	/**
	 * 载入核心配置文件
	 * @return $this
	 * @throws \Exception
	 */
	private function _FunctionCore(){
		$this->hook(__CLASS__ , __FUNCTION__);
		//载入核心库
		$file = 'common.php';
		if(!is_file(KSAOS.$file)){
			throw new \Exception('系统核心库common不存在：'.$file);
		}
		@require_once (KSAOS.$file);
		return $this;
	}
	/**
	 * 设置常量
	 * @return $this
	 */
	private function _setDefine(){
		
		//模板目录绝对路径
		define('TPLDIR', ROOT.$this->config['TPLDIR']);
		//是否是windows服务器
		define('ISWIN', strpos(strtolower(php_uname()),'windows') !== false ? true : false);
		//常量 HTTPS状态
		define('HTTPS', (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443 || (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off')) ? 1 : 0);
		//cookie前缀
		define('COOKIEPRE', $this->config['cookie']['pre']);
		//cookie路径
		define('COOKIEPATH', $this->config['cookie']['path']);
		//cookie作用域
		define('COOKIEDOMAIN', $this->config['cookie']['domain']);
		//加密函数混淆密钥
		define('ENCODEKEY', $this->config['CodeKEY']);
		
		$this->hook(__CLASS__ , __FUNCTION__);
		return $this;
	}
	/**
	 * 初始化内存管理器
	 * @return $this
	 */
	private function _RAM(){
		$this->_memory = (new Memory())->Init($this->config['memory']);
		$this->hook(__CLASS__ , __FUNCTION__);
		return $this;
	}
	/**
	 * 数据库连接开始
	 */
	private function __Db() {
		if(!$this->config['db']){
			throw new \Exception('请配置数据库信息');
		}
		$this->_DB = new DB();
		$this->_DB->init($this->config['db']);
		unset($this->config['db']['server']);
		$this->hook(__CLASS__ , __FUNCTION__);
		return $this;
	}
	
	
	/**
	 * 全局变量与config初始化 $C变量为全局时使用
	 * @global type $C
	 */
	private function __Event() {
		global $C;
		$C = [
			
			//路由参数
			'R' => '',
			'M' => '',
			'O' => '',
			'D' => '',
			'MOD' => [],
			
			
			'uid' => 0, //当前用户ID
			'token' => '',//当前用户的活动凭证
			'IP' => IP(), //当前用户IP地址
			'port' => (isset($_SERVER['REMOTE_PORT']) ? $_SERVER['REMOTE_PORT'] : ''), //当前用户IP端口
			'useragent' => (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
			'user' => [], //当前用户基本信息，如果用户未登录，数组为空
			'time' => time(), //底层开始时间 格林威治
			
			'HTTPS' => HTTPS,
			'ajax' => isset($_GET['ajax']) && $_GET['ajax'] ? 1 : 0, //是否是AJAX请求
			

			'siteurl' => '',//当前程序根绝对地址 http://www.xxx.com/
			'pathurl' => '', //当前页面相对地址 
			'staticUrl' => $this->config['staticUrl'] ? $this->config['staticUrl'] : '',//静态资源目录访问地址
			'staticHash' => $this->config['statichash'] ? $this->config['statichash'] : time(),//静态资源缓存hash
			'picurl' => $this->config['picUrl'],//图片域名地址
			'gzip' => 0,  //GZIP是否开启 1=开启

			'starttime' => microtime(true),//初始化完成时间 

			'config' => $this->config,

			'cookie' => [], //浏览器cookies
			'sessoin' => [], //Session
			'setting' => [],
			'CSSLIST' => [],
		];
		
		//如果服务器开启了魔术引号 则过滤掉字符串中的反斜杠
		if(defined('GPC') && GPC) {
			$_GET = cstripslashes($_GET);
			$_POST = cstripslashes($_POST);
			$_COOKIE = cstripslashes($_COOKIE);
		}

		//如果是POST提交，则将POST数据转到GET变量中
		if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST)) {
			$_GET = array_merge($_GET, $_POST);
		}

		//读取cookie
		$prelength = strlen($this->config['cookie']['pre']);
		foreach($_COOKIE as $key => $val) {
			if(substr($key, 0, $prelength) == $this->config['cookie']['pre']) {
				$C['cookie'][substr($key, $prelength)] = $val;
			}
		}
		$C['pathurl'] = isset($_SERVER['REQUEST_URI']) ? trim($_SERVER['REQUEST_URI'],'/') : '';
		
		$siteurl = explode('/',$_SERVER['SCRIPT_NAME']);
		array_pop($siteurl);
		$siteurl = implode('/',$siteurl);
		if($this->config['domain']){
			$host = $this->config['domain'];
		}else{
			$host = $_SERVER['HTTP_HOST'];
		}
		$siteurl = chtmlspecialchars('http'.(HTTPS ? 's' : '').'://'.$host.$siteurl.'/');
		$C['siteurl'] = $siteurl;
		if(!$C['staticUrl']){
			$C['staticUrl'] = $siteurl;
		}
		unset($siteurl,$host);
		
		if(!(strpos($C['picurl'],'http') ===0)){
			$C['picurl'] = $C['siteurl'].$C['picurl'];
		}
		$C['sid']  = cookies('sid');
		$this->var = $C;
		unset($C['config']['db'], $C['config']['memory']);//全局变量删除数据库配置信息
		
		//初始化路由
		$this->Route()->init();
		$this->hook(__CLASS__ , __FUNCTION__ , 'end');
		return $this;
	}
	
	private function __setting() {
		global $C;
		$setting = DB('setting')->fetch_all('skey');
		if(isset($setting['CDN_url']) && $setting['CDN_url']){
			$C['staticUrl'] = $setting['CDN_url'];
		}
		$C['setting'] = $setting;
		$C['TITLE'] = isset($setting['sitename']) ? $setting['sitename'] : '';
		return $this;
	}
	
	/**
	 * 用户自动登录
	 * @global type $C
	 * @return type
	 */
	private function __user() {
		//如果是worker类框架 则直接略过用户识别
		if(defined('__BIGER__')){
			return $this;
		}
		global $C;
		$token = isset($_SERVER['HTTP_TOKEN']) && $_SERVER['HTTP_TOKEN'] ? $_SERVER['HTTP_TOKEN'] : '';
		if(!$token && isset($C['cookie']['token'])){
			$token = $C['cookie']['token'];
		}
		if($token){
			User::isLogin($token);
		}
		return $this;
	}
	
	/**
	 * 底层结束
	 * @global type $C
	 * @return $this
	 */
	private function __End() {
		global $C;
		//删除敏感全局字段
		unset($C['config']);
		$this->debug()->end('Core');
		$this->debug()->start('Other');
		$this->hook(__CLASS__ , __FUNCTION__);
		return $this;
	}
	
	
	
	public static function Date(){
		return new Dates();
	}
	public static function debug($key='',$value=''){
		$new = new Debug();
		if($key && $value){
			$new->set($key,$value);
		}
		return $new;
	}
	public static function Curl(){
		return new Curls();
	}
	public static function Error($e, $code=0) {
		new Errors($e, $code);
	}
	public static function document(){
		return new Documents();
	}
	public static function File(){
		return new Files();
	}
	public static function upload($Mod='',$file=''){
		return (new Upload())->Save($Mod,$file);
	}
	public static function Route(){
		return new Route();
	}
	public static function image(){
		return new Image();
	}
	public static function page($count, $url=NULL, $perpage=10, $max=5, $isjump=1){
		$class = new Page();
		return $class->init($count, $url, $perpage, $max, $isjump);
	}
	public static function Attach(){
		return new Attach();
	}
	
	/**
	 * 缓存类
	 * 直接调用返回Cache对象 使用参数默认使用RAM 参考RAM参数介绍
	 * @param type $cmd
	 * @param type $key
	 * @param type $value
	 * @param type $ttl
	 * @param type $pre
	 * @return \ksaOS\Cache
	 */
	public static function Cache($cmd=NULL, $key='', $value='', $ttl = 0, $pre = ''){
		if($cmd){
			return Cache::RAM($cmd, $key, $value, $ttl, $pre);
		}else{
			return new Cache();
		}
	}
	public static function template($file='',$tplDir=''){
		$Class = new template();
		return $Class->replace($file, $tplDir);
	}
}