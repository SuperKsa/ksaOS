<?php

/**
 * @title     Mysqli处理类 
 * @desc    cr180内部核心框架
 * @date    2019-10-10 20:58:14
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file mysqli.php (KSAOS底层 / UTF-8)
 */
namespace ksaOS\Db;
use PDO;

if(!defined('KSAOS')) {
	exit('Error.');
}


class Mysqls{
	public $pre;
	public $charset = 'utf8';
	public $version = '';
	public $querynum = 0;
	public $curID = 0;
	public $curlink;
	public $link = [];
	public $config = [];
	public $map = [];
	public $linkNum = 0; //连接次数

	
	public function set_config($config=[]) {
		$this->config = $config;
		$this->pre = $config['pre'];
		$this->charset = $config['charset'];
		if(!empty($this->config['map'])) {
			$this->map = $this->config['map'];
		}
		\ksaOS\APP::hook(__CLASS__ , __FUNCTION__);
	}

	public function connect($sid=1) {
		//检测连接
		if(isset($this->link[$sid]) && $this->ping($this->link[$sid])){
			return $this->link[$sid];
		}
		
		if(empty($this->config) || !$this->config['charset'] || !$this->config['pre']){
			return false;
		}
		$config = $this->config['server'][$sid];
		
		if(!$config || !$config['host'] || !$config['user']) {
			throw new \Exception('[DB] '.$sid.'号 数据库配置错误');
		}
		try {
			$this->curlink = new \PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['name'].';charset='.$this->config['charset'], $config['user'], $config['password'], [
				\PDO::ATTR_PERSISTENT => true
			]);
			if($this->curlink){
				$this->curlink->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$this->link[$sid] = $this->curlink;
				$this->curID = $sid;
				$this->linkNum ++;
				return $this->curlink;
			}
		} catch (\Exception $e) {
			throw new \Exception('[DB] '.$sid.'号 数据库配置错误:' . $e->getMessage(), 31);
		}
		return false;
	}
	
	public function close(){
		$this->curlink = NULL;
		$this->link = [];
	}


	private function ping($link){
		try {
			if($link && $link->getAttribute(PDO::ATTR_DRIVER_NAME) =='mysql' && $link->getAttribute(PDO::ATTR_SERVER_INFO)){
				return true;
			}
		} catch (\Exception $e) {
			return false;
		}
		return false;
	}
	
	public function table($tablename, $as='', $on='', $join='') {
		$defaultID = 1;
		$id = 0;
		if(!empty($this->map)) {
			foreach($this->map as $key => $value){
				if(!$id && in_array($tablename,$value)){
					$id = $key;
				}
			}
		}
  
		$id = $id ? $id : $defaultID;
        $this->curlink = $this->link[$id];
		if($this->ping($this->link[$id])) {
            return $this->pre.$tablename;
		}else{
            if($this->connect($id)){
                return $this->pre.$tablename;
            }
        }
		return false;
	}
	
	public function fetch_all($sql='', $silent=false) {
		$res = $this->query($sql);
		\ksaOS\APP::hook(__CLASS__ , __FUNCTION__);
		return  $res->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function fetch_first($sql='') {
		$res = $this->query($sql);
		\ksaOS\APP::hook(__CLASS__ , __FUNCTION__);
		return $res->fetch(PDO::FETCH_ASSOC);
	}

	public function fetch_count($sql='') {
		$res = $this->query($sql);
		\ksaOS\APP::hook(__CLASS__ , __FUNCTION__);
		return $res->fetchColumn();
	}
	public function insert($sql='', $insert_id=false){
		$this->query($sql);
		\ksaOS\APP::hook(__CLASS__ , __FUNCTION__);
		return $insert_id ? $this->curlink->lastInsertId() : true;
	}
	public function update($sql=''){
		$res = $this->query($sql);
		\ksaOS\APP::hook(__CLASS__ , __FUNCTION__);
		return $res->rowCount();
	}
	public function delete($sql=''){
		$res = $this->query($sql);
		\ksaOS\APP::hook(__CLASS__ , __FUNCTION__);
		return $res->rowCount();
	}
	public function query($sql='', $silent = false, $unbuffered = false) {
		$starttime = microtime(true);
		
		$db = $this->curlink;
		//检测连接并尝试重连
		if(!$this->ping($db)) {
			$db = $this->curlink = null; unset($this->link[$this->curID]);
			$db = $this->connect($this->curID);
		}
		\ksaOS\APP::hook(__CLASS__ , __FUNCTION__);
		$query = NULL;
		if($db){
			try{
				$query = $db->prepare($sql);
				$query->execute();
				
			} catch (\PDOException $e) {
				$code = $e->errorInfo[1] ? $e->errorInfo[1] : $e->getCode();
				if(!$silent) {
					throw new DbException($e->getMessage(), $code, $sql, $this->pre);
				}
				return false;
			}
		}
		\ksaOS\APP::debug('DBquery', [$sql, number_format((microtime(true) - $starttime), 6), debug_backtrace(), $this->curlink]);
		
		$this->querynum++;
		return $query;
	}

	public function version() {
		if(empty($this->version)) {
			$this->version = $this->fetch_count('select version()');
		}
		return $this->version;
	}

}
class DbException extends \Exception{
	public $sql = '';
	public $code = 0;
	public $pre = '';
	public $isDB = 1;
	function __construct($msg, $code, $sql='' , $pre=''){
		$this->sql = $sql;
		$this->pre = $pre;
		$this->code = $code;
		\ksaOS\APP::hook(__CLASS__ , __FUNCTION__);
		parent::__construct($msg,$code);
	}
}