<?php

/**
 * workerman框架支持
 * 用法与wokerman一致
 * $this=底层
 * 
class Woker1 extends \ksaOS\Biger{
	protected $socket = ''; //当前需要创建的连接
	private $TcpCon = [];
	public function onWorkerStart($worker){
		.....
	}
}
 * @date    2019-12-20 22:20:02
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Biger.php (ksaos / UTF-8)
 */
namespace ksaOS;
require_once KSAOS.'/Core/Workerman/Autoloader.php';
\Workerman\Worker::$logFile = ROOT.'data/cache/workerman.log';

class Biger{
	const _name = 'KSAOS框架';
	protected $debug = 1; //是否开启日志输出

	protected $socket = '';
	protected $id = NULL;
	
	protected $count = 1; // 启动1个进程对外提供服务
	protected $protocol = NULL; //设置当前Worker实例的协议类。
	protected $transport = NULL; //设置当前Worker实例所使用的传输层协议，目前只支持3种(tcp、udp、ssl)。不设置默认为tcp。
	protected $reusePort = false; //允许多个无亲缘关系的进程监听相同的端口，并且由系统内核做负载均衡，决定将socket连接交给哪个进程处理，避免了惊群效应，可以提升多进程短连接应用的性能。
	
	protected $connections, $stdoutFile, $pidFile, $logFile, $user, $reloadable ,$daemonize, $globalEvent = NULL;
	
	protected $listen = 0;
			
	function __construct(){
		
		if(!defined('__BIGER__')){
			define('__BIGER__', 1);
			APP::__Run();
		}
		$this->_this_run();
	}
	
	protected function onWorkerStart($worker){}
	protected function onConnect($con){}
	protected function onMessage($con, $data=NULL){}
	protected function onClose($con){}
	protected function onWorkerReload($worker){}
	protected function onBufferFull($con){}
	protected function onBufferDrain($con){}
	protected function onError($con, $code, $msg){}
	
	private function _this_run(){
		$this->_Worker = new \Workerman\Worker($this->socket);
		$Worker = $this->_Worker;
		if(!is_null($this->id)){ $Worker->id = $this->id; }
		$Worker->name = self::_name;
		if(!is_null($this->count)){ $Worker->count = $this->count; }
		if(!is_null($this->protocol)){ $Worker->protocol = $this->protocol; }
		if(!is_null($this->transport)){ $Worker->transport = $this->transport; }
		if(!is_null($this->reusePort)){ $Worker->reusePort = $this->reusePort; }
		if(!is_null($this->connections)){ $Worker->connections = $this->connections; }
		if(!is_null($this->stdoutFile)){ $Worker->stdoutFile = $this->stdoutFile; }
		if(!is_null($this->pidFile)){ $Worker->pidFile = $this->pidFile; }
		if(!is_null($this->user)){ $Worker->user = $this->user; }
		if(!is_null($this->reloadable)){ $Worker->reloadable = $this->reloadable; }
		if(!is_null($this->daemonize)){ $Worker->daemonize = $this->daemonize; }
		if(!is_null($this->globalEvent)){ $Worker->globalEvent = $this->globalEvent; }
		$Worker->onWorkerStart = function($worker){
			echo "\n■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■\n";
			echo "■■■■■■■■■■■■■■■■ {$worker->name}启动中 ■■■■■■■■■■■■■■■■\n";
			echo "■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■\n\n\n";
			$this->onWorkerStart($worker);
			if(!$this->debug){
				$this->TimerAdd(1,function(){
					$u = round(memory_get_usage() / 1024/1024,4).'MB';
					echo '正在运行 时间：'.date('Y-m-d H:i:s')." / 内存消耗：{$u}\n";
				});
			}
			echo "\n■■■■■■■■■■■■■■■■ {$worker->name}运行中 ■■■■■■■■■■■■■■■■\n";
		};
		$Worker->onConnect = function($con){
			$this->onConnect($con);
		};
		$Worker->onMessage = function($con){
			$this->onMessage($con);
		};
		$Worker->onClose = function($con){
			$this->onClose($con);
			$this->TimerDelAll();
			echo "\n■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■\n";
			echo "■■■■■■■■■■■■■■■■■ 结束运行 ■■■■■■■■■■■■■■■■■■\n";
			echo "■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■\n";
		};
		$Worker->onWorkerReload = function($con){
			$this->onWorkerReload($con);
		};
		$Worker->onBufferFull = function($con){
			$this->onBufferFull($con);
		};
		$Worker->onBufferDrain = function($con){
			$this->onBufferDrain($con);
		};
		$Worker->onError = function($con){
			$this->onError($con);
		};
		if($this->listen){
			$Worker->listen();
		}
	}
	function __destruct(){
		
	}
	
	/**
	 * 输出消息到控制台
	 * @param type $m
	 */
	protected function Msg($m=''){
		if(!$this->debug){
			return;
		}
		$u = round(memory_get_usage() / 1024/1024,4).'MB';
		echo '[ksaOS] '.APP::Date()->times().': '.$m."\t内存:{$u}\n";
	}
	
	public static function RunAll(){
		$cl = strtolower(__CLASS__);
		//获取所有class类 并从中初始化Biger开头的class
		foreach(get_declared_classes() as $value){
			$val = strtolower($value);
			if(strpos($val,$cl) === 0 && $val != $cl){
				new $value();
			}
		}
		//如果没有初始化本类就直接调用方法时给定一个错误
		if(defined('__BIGER__')){
			\Workerman\Worker::runAll();
		}
	}
	
	public static function StopAll(){
		//如果没有初始化本类就直接调用方法时给定一个错误
		if(defined('__BIGER__')){
			\Workerman\Worker::stopAll();
		}
	}
	
	public static function TimerAdd($time_interval, $func, $args = [], $persistent = true){
		return \Workerman\Lib\Timer::add($time_interval, $func, $args, $persistent);
	}
	public static function TimerDel($id=0){
		return \Workerman\Lib\Timer::del($id);
	}
	
	public static function TimerDelAll(){
		return \Workerman\Lib\Timer::delAll();
	}
}