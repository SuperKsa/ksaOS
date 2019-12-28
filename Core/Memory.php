<?php

/**
 * RAM内存管理器
 * @desc    cr180内部核心框架
 * @date    2019-10-10 20:58:14
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Memory.php (KSAOS底层 / UTF-8)
 */
namespace ksaOS;
use Redis;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Memory {
	const _Name = 'RAM内存管理器';
	public $pre = '';
	private $config = [];
	
	public $enable = false;
	public $obj;
	
	/**
	 * 初始化
	 * 只能在底层调用
	 * @param type $config
	 * @return $this
	 */
	function Init($config) {
		if(!defined('___KSAOS_MEMORYINIT___')){
			define('___KSAOS_MEMORYINIT___',1);
			$this->pre = empty($config['pre']) ? substr(md5($_SERVER['HTTP_HOST']), 0, 6).'_' : $config['pre'];
			$this->config = $config['redis'];
			if($config['redis']) {
				$this->connect();
				APP::debug('MEMORYINIT',1);
			}
			APP::hook(__CLASS__ , __FUNCTION__);
		}
		
		return $this;
	}
	
	/**
	 * 连接组件
	 * 拥有自动重连
	 * @return boolean
	 * @throws \Exception
	 */
	function connect(){
		if($this->ping()){
			return true;
		}
		$config = $this->config;
		$this->enable = false;
		if(!$config || !$config['server'] || !$config['port']){
			throw new \Exception('Redis配置信息缺失');
		}
		
		try {
			$this->obj = new Redis();
			if($config['pconnect']) {
				$connect = @$this->obj->pconnect($config['server'], $config['port']);
			} else {
				$connect = @$this->obj->connect($config['server'], $config['port']);
			}
			if($connect){
				if($config['requirepass']) {
					$this->obj->auth($config['requirepass']);
				}
				if($config['serializer']){
					$this->obj->setOption(Redis::OPT_SERIALIZER, $config['serializer']);
				}
				$this->enable = true;
			}
			return true;
		} catch (\Exception $e) {
			throw new \Exception('Redis启动失败（检查服务器是否安装Redis组件）');
		}
		if(!$this->ping()){
			throw new \Exception('Redis连接失败，请检查连接信息并确认Redis是否启动');
		}
		
		return false;
	}
	
	public function ping(){
		return $this->obj && $this->obj->ping() ? true : false;
	}
	
	public function close(){
		APP::hook(__CLASS__ , __FUNCTION__);
		$this->obj = NULL;
	}
	
	/**
	 * 读取数据 一个和多个KEY
	 * @param type $key 支持多个键名 key 或者 [key1 , key2 ...]
	 * @return boolean
	 */
	function get($key) {
		if($this->connect()){
			if(is_array($key)) {
				return $this->getMultiple($key);
			}
			$key = $this->_key($key);
			$v = json_decode($this->obj->get($key), true);
			return $v[0];
		}
		return false;
	}

	/**
	 * 读取数据 多个KEY
	 * @param type $keys 只能是数组[key1 , key2 ...]
	 * @return boolean
	 */
	function getMultiple($keys) {
		if($this->connect()){
			$keys = $this->_key($keys);
			$result = $this->obj->getMultiple($keys);
			$index = 0;
			$newresult = [];
			foreach($keys as $key) {
				if($result[$index] !== false) {
					$v = json_decode($result[$index],true);
					$newresult[$key] = $v[0];
				}
				$index++;
			}
			unset($result);
			return $newresult;
		}
		return [];
	}
	
	/**
	 * 选择数据库
	 * @param type $db 默认DB=0
	 * @return boolean
	 */
	function select($db=0) {
		if($this->connect()){
			return $this->obj->select($db);
		}
		return false;
	}
	
	/**
	 * 写入数据
	 * 支持多维数组
	 * @param type $key
	 * @param type $value
	 * @param type $ttl
	 * @return boolean
	 */
	function set($key, $value, $ttl = 0) {
		
		if($this->connect()){
			$value = json_encode([$value], JSON_UNESCAPED_UNICODE);
			$key = $this->_key($key);
			if($ttl) {
				return $this->obj->setex($key, $ttl, $value);
			} else {
				return $this->obj->set($key, $value);
			}
		}
		return false;
	}
	
	/**
	 * 删除key
	 * @param type $key 支持多个键名 key 或者 [key1 , key2 ...]
	 * @return boolean
	 */
	function del($key) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->del($key);
		}
		return false;
	}

	function setMulti($arr, $ttl=0) {
		$str = 0;
		if($this->connect()){
			if(!is_array($arr)) {
				return false;
			}
			foreach($arr as $key => $v) {
				if($this->set($key, $v, $ttl)){
					$str ++;
				}
			}
		}
		return $str;
	}

	function inc($key, $step = 1) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->incr($key, $step);
		}
		return false;
	}

	function decr($key, $step = 1) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->decr($key, $step);
		}
		return false;
	}

	function getSet($key, $value) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->getSet($key, $value);
		}
		return false;
	}

	function sADD($key, $value) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->sADD($key, $value);
		}
		return false;
	}

	function sRemove($key, $value) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->sRemove($key, $value);
		}
		return false;
	}

	function sMembers($key) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->sMembers($key);
		}
		return false;
	}

	function sIsMember($key, $member) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->sismember($key, $member);
		}
		return false;
	}

	function keys($key) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->keys($key);
		}
		return false;
	}

	function expire($key, $second){
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->expire($key, $second);
		}
		return false;
	}

	function sCard($key) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->sCard($key);
		}
		return false;
	}

	function hSet($key, $field, $value) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->hSet($key, $field, $value);
		}
		return false;
	}

	function hDel($key, $field) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->hDel($key, $field);
		}
		return false;
	}

	function hLen($key) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->hLen($key);
		}
		return false;
	}

	function hVals($key) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->hVals($key);
		}
		return false;
	}

	function hIncrBy($key, $field, $incr){
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->hIncrBy($key, $field, $incr);
		}
		return false;
	}

	function hGetAll($key) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->hGetAll($key);
		}
		return false;
	}

	function sort($key, $opt) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->sort($key, $opt);
		}
		return false;
	}

	function exists($key) {
		if($this->connect()){
			$key = $this->_key($key);
			return $this->obj->exists($key);
		}
		return false;
	}

	function clear() {
		if($this->connect()){
			return $this->obj->flushAll();
		}
		return false;
	}
	
	private function _key($str) {
		$pre = $this->pre;
		if(is_array($str)) {
			foreach($str as &$val) {
				$val = $pre.$val;
			}
		} else {
			$str = $pre.$str;
		}
		return $str;
	}
}