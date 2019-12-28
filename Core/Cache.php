<?php

/**
 * 数据缓存类
 * 暂无介绍
 * @date    2019-11-28 19:44:01
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Cache.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Cache{
	const _name = 'ksaOS缓存处理类';
	/**
	 * 内存管理器 redis memcache等
	 * @param type $cmd set=写入 get=读取 del=删除 inc=增量插入 dec=减量插入
	 * @param type $key 键名
	 * @param type $value 键值
	 * @param type $ttl 过期时间（秒）
	 * @param type $pre 前缀名
	 * @return string
	 */
	public static function RAM($cmd='', $key='', $value='', $ttl = 0, $pre = ''){
		$obj = APP::$memory;
		APP::hook(__CLASS__ , __FUNCTION__);
		if($cmd == 'check') {
			return $obj->enable;
		} elseif($obj->enable && in_array($cmd, ['set', 'get', 'del', 'inc', 'dec'])) {
			APP::debug('memoryFunc',[$cmd,$key,$value,$ttl]);
			switch ($cmd) {
				case 'set': return $obj->set($key, $value, $ttl, $pre);
				case 'get': return $obj->get($key);
				case 'del': return $obj->del($key, $value);
				case 'inc': return $obj->inc($key, $value ? $value : 1);
				case 'dec': return $obj->dec($key, $value ? $value : -1);
			}
		}
		return null;
	}
	
	/**
	 * 文件缓存目录自动维护函数
	 * @param type $cmd auto=自动维护  set=新增一条缓存索引
	 * @param type $Key	当$cmd=set时必须传入缓存文件相对路径
	 * @param type $outTime 缓存文件过期时间戳
	 */
	public static function ___FileAuto($cmd='', $Key='', $outTime=0){
		
		$cacheDir = 'data/';
		$File = $cacheDir.'cachelist.php'; //缓存文件索引清单 便于自动清理
		if(is_file(ROOT.$File)){
			include ROOT.$File;
		}
		APP::hook(__CLASS__ , __FUNCTION__);
		$___CacheListData = $___CacheListData ? $___CacheListData : [];
		
		$isUpdateList = 0;
		//增加一个缓存文件索引
		if($cmd == 'set'){
			$outTime = intval($outTime);
			if(!isset($___CacheListData[$Key]) || $___CacheListData[$Key] != $outTime){
				$___CacheListData[$Key] = $outTime;
				$isUpdateList = 1;
			}
		//自动清理过期缓存文件
		}elseif($cmd =='auto'){
			foreach($___CacheListData as $k => $val){
				$val = intval($val);
				if($val < TIME){
					if(is_file(ROOT.$cacheDir.$k)){
						@unlink(ROOT.$cacheDir.$k);
					}
					unset($___CacheListData[$k]);
					$isUpdateList = 1;
				}
			}
		}
		if($isUpdateList){
			$data = '';
			foreach($___CacheListData as $k => $val){
				$data .= "\n\t'".$k."' => '".$val."',";
			}
			$data = "<?php\n//数据缓存清单文件！删除后KSAOS无法自动清理过期的缓存文件\n\nif(!defined('KSAOS')) {\nexit('Error.');\n}\n\n".'$___CacheListData=['.$data."\n".'];';
			@file_put_contents(ROOT.$File, $data);
		}
	}
	
	/**
	 * 文件缓存器
	 * @param string $cmd 缓存类型 set=写入 get=读取 del=删除
	 * @param string $Key 缓存键名
	 * @param string/array $data 缓存内容
	 * @param int $ttl 过期时间（秒）
	 * @return boolean true|false
	 */
	public static function File($cmd='', $Key='', $data='', $ttl = 0){
		$ttl = intval($ttl);
		$ttl = $ttl >0 ? (TIME + $ttl) : 0;
		$Key = md5($Key);
		$cacheDir = 'data/cache/sysdata/';
		$DirName = substr($Key,0,2).'/'; //取前两位字符作为目录名
		$FileName = $Key.'.php';
		
		$cacheDir = $cacheDir.$DirName;
		$File = ROOT.$cacheDir.$FileName;
		APP::File()->mkdir(ROOT.$cacheDir);
		APP::hook(__CLASS__ , __FUNCTION__);
		$cacheHeader = '<?php exit;?>';
		
		if($cmd =='set'){
			if($data){
				$data = ['data' => $data, 'time' => TIME, 'expire' => $ttl];
				$data = json_encode($data, JSON_UNESCAPED_UNICODE);
			}else{
				$data = '';
			}
			$data = $cacheHeader.$data;
			$putStatus = @file_put_contents($File, $data);
			APP::Cache()->___FileAuto('set', $DirName.$FileName, $ttl);
			if($putStatus !== false){
				return true;
			}else{
				return false;
			}
		}elseif($cmd =='get'){
			if (!is_file($File) || !is_readable($File)) {
				return false;
			}
			$data = @file_get_contents($File);
			
			$data = substr($data, strlen($cacheHeader));
			
			if (empty($data)){
				return false;
			}else{
				$data = json_decode($data, true);
				if(!$data || !$data['data'] || ($data['expire']>0 && $data['expire'] < TIME)){
					APP::Cache()->File('del',$Key);
					return false;
				}else{
					return $data['data'];
				}
			}
		}elseif($cmd =='del'){
			return @unlink($File) ? true : false;
		}
	}
}