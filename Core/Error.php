<?php

/**
 * @title     全局错误拦截处理 
 * @desc    cr180内部核心框架
 * @date    2019-10-10 20:58:14
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Error.php (KSAOS / UTF-8)
 */
namespace ksaOS;
if(!defined('KSAOS')) {
	exit('Error.');
}

class Error {
	const _name = 'ksaOS错误处理类';
	
	static function register(){
		error_reporting(E_ERROR);
		set_error_handler([__CLASS__, 'Warn']);
		set_exception_handler([__CLASS__, 'Exc']);
		register_shutdown_function([__CLASS__, 'Stop']);
	}
	//脚本停止后执行函数
	static function Stop(){
		//echo '<br>Error:Stop';
	}
	//警示类错误处理
	static function Warn($code=0,$msg='', $file='', $line=0){
		APP::hook(__CLASS__ , __FUNCTION__);
		$Msg = self::clear($file).'('.$line.') '.$msg.'<br>';
		//echo $Msg;
	}
	//致命性错误处理
	static function Exc($E=NULL, $errorCode=0){
		APP::hook(__CLASS__ , __FUNCTION__);
		$Type = 'system';
		if(isset($E->isDB)) {
			$Type = 'db';
			$Msg = '('.$E->code.') '. self::clear(str_replace($E->pre, '', $E->getMessage()));
			if($E->sql) {
				$Msg .= '<div class="sql">'.self::clear(str_replace($E->pre, '', $E->sql)).'</div>';
			}
		}elseif(gettype($E) =='object'){
			$Type = 'system';
			$Msg = self::clear($E->getMessage());
		}else{
			$Type = 'msg';
			$Msg = self::clear($E);
		}
		if(in_array($Type,['system','db'])){
			self::log($Type, $Msg);
			$trace = $E->getTrace();
			krsort($trace);
			$trace[] = ['file'=>$E->getFile(), 'line'=>$E->getLine(), 'function'=> 'break'];
			$phpmsg = [];
			foreach ($trace as $value) {
				if($value['function']) {
					$fun = '';
					if(isset($value['class'])) {
						$fun .= $value['class'].$value['type'];
					}
					$fun .= $value['function'].'(';
					if(isset($value['args'])) {
						$mark = '';
						foreach($value['args'] as $val) {
							$fun .= $mark;
							if(is_array($val)) {
								$val = '[...]';
							}elseif(is_bool($val)) {
								$val = $val ? 'true' : 'false';
							}elseif(is_numeric($val)) {

							}elseif(is_object($val)){
								$val = 'object';
							}else{
								if(mb_strlen($val) > 10){
									$val = mb_substr($val, 0, 20).'...'.mb_substr(mb_substr($val, 20), -5);
								}
								$val = ('\''.htmlspecialchars(self::clear($val)).'\'');
							}
							$fun .= $val;
							$mark = ', ';
						}
					}
					$fun .= ')';
					$value['function'] = $fun;
				}
				$phpmsg[] = [
					'file' => isset($value['file']) ? str_replace(['\\',ROOT], ['/',''], $value['file']) : '',
					'line' => isset($value['line']) ? $value['line'] : 0,
					'function' => $value['function'],
				];
			}
		}
		self::show($Type, $Msg, $phpmsg, $errorCode);
		exit;
	}
	static function clear($str='') {
		if(!is_object($str)){
			if(defined('KSAOS_DB_PRE')){
				$str = str_replace(KSAOS_DB_PRE, '', $str);
			}
			$str = str_replace(['\\',ROOT], ['/',''], $str);
			$str = htmlspecialchars($str);
			$str = str_replace(["\t", "\r", "\n"], " ", $str);
		}
		return $str;
		
	}
	
	private static function show($type, $Msg, $phpmsg = '', $errorCode) {
		$title = $type == 'db' ? '数据库' : '系统';
		if(defined('__BIGER__')){
			echo "■■■■■■■■■■■■■ KSAOS {$title}错误 ■■■■■■■■■■■■■\n";
			echo '■■ '.$Msg."\n";
			echo "■■■■■■■■■■■■■ KSAOS End ■■■■■■■■■■■■■\n";
		}else{
			if(isset($_GET['ajax'])){
				JSON($Msg,['msg'=>$Msg,'success'=>0,'locationUrl'=>'./']);
			}
			if($errorCode == '404'){
				include APP::Template('Core/tpl/404', KSAOS);
			}else{
				include APP::Template('Core/tpl/_error', KSAOS);
			}
		}
		exit;
	}

	static function log($type='system', $message) {
		if(defined('__BIGER__')){
			return;
		}
		global $C;
		$message = self::clear($message);
		$file =  ROOT.'./data/cache/system/runlog/'.date('Y-m').'_errorlog.php';
		$hash = md5($message);
		Files::mkdir(dirname($file));
		$D = ['<?PHP exit;?>'];
		$D[] =  $type;
		$D[] =  TIME;
		$D[] = '【'.$message.'】';
		$D[] = 'uid='.(isset($C['uid']) ? $C['uid'] : 0);
		$D[] = 'IP='.(isset($C['IP']) ? $C['IP'] : '').':'.(isset($C['port']) ? $C['port'] : '');
		$D[] = 'Request: '.htmlspecialchars(self::clear($_SERVER['REQUEST_URI']));
		$D[] = $hash;
		$D = implode("\t", $D)."\n";
		
		if($fp = @fopen($file, 'rb')) {
			$lastlen = 50000;
			$maxtime = 900;
			$offset = filesize($file) - $lastlen;
			if($offset > 0) {
				fseek($fp, $offset);
			}
			if($data = fread($fp, $lastlen)) {
				$array = explode("\n", $data);
				if(is_array($array)){
					foreach($array as $key => $val) {
						$row = explode("\t", $val);
						if($row[0] != '<?PHP exit;?>'){
							continue;
						}
						if($row[7] == $hash && (intval($row[2]) > TIME - $maxtime)) {
							return;
						}
					}
				}
			}
		}
		error_log($D, 3, $file);
	}
}