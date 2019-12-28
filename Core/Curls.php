<?php

/**
 * CURL请求类
 *  暂无介绍
 * @date    2019-11-28 16:41:52
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Curls.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Curls{
	const _name = 'ksaOS Curl处理类';
	/**
	 * 单线程CURL
	 * @param type $url 地址
	 * @param type $post POST数据
	 * @param string $header 请求头数组
	 * @param type $timeout 建立连接后的超时时间 秒
	 * @param string $resolve 域名指定IP、端口的设置 每个元素以冒号分隔。格式： array("example.com:80:127.0.0.1")
	 * @return type
	 */
	public static function send($url='', $post=[], $header=[], $timeout=15, $resolve=[]){
		global $C;
		APP::hook(__CLASS__ , __FUNCTION__);
		$startTime = microtime(true);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		//$header['HTTP_USER_AGENT'] = 'Mozilla/4.0 (compatible; CROS PHP bot; ' . php_uname('a') . '; PHP/' . phpversion() . ') / TIME:'.TIME;
		//$header['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
		//$header['REMOTE_PORT'] = $_SERVER['REMOTE_PORT'];
		if($header){
			foreach($header as $key => $value){
				$header[$key] = $key.': '.$value;
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		}
		if($resolve){
			foreach($resolve as $key => $value){
				$resolve[$key] = $key.': '.$value;
			}
			curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
		}
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		if($post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1); //握手时间固定1秒 没响应直接断开
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //文件下载时间 超过直接断开
		$data = curl_exec($ch);
		$status = curl_getinfo($ch);
		$errno = curl_errno($ch);
		curl_close($ch);
		$GLOBALS['filesockheader'] = substr($data, 0, $status['header_size']);
		
		$endTime = microtime(true);
		APP::debug('CURL',['queryTime'=>round(($endTime - $startTime) ,5),'data'=>$data,'post'=>$post,'header'=>$header,'status'=>$status]);
		
		$data = (string)substr($data, $status['header_size']);
		if($errno || $status['http_code'] != 200) {
			return ['error'=>1,'httpcode'=>$status['http_code'] ,'data'=>$data];
		} else {
			return ['error'=>0,'httpcode'=>$status['http_code'] ,'data'=>$data];
		}
	}
	
	/**
	 * 多线程CURL
	 * @param type $URLS 地址数组
	 * @param type $post POST数据（对应$URLS顺序的数据）
	 * @param string $header 请求头数组（对应$URLS顺序的数据）
	 * @param type $timeout 建立连接后的超时时间 秒
	 * @param string $resolve 域名指定IP、端口的设置 每个元素以冒号分隔。格式： array("example.com:80:127.0.0.1")
	 * @param string $setoptDataAppend 附加的setopt参数（对应$URLS顺序的数据）
	 * @return type
	 */
	public static function sends($URLS=[], $post=[], $header=[], $timeout=15, $resolve=[], $setoptDataAppend=[]){
		APP::hook(__CLASS__ , __FUNCTION__);
		$setoptData = [
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HEADER => 1,
			CURLOPT_CONNECTTIMEOUT => 1, //握手时间固定1秒 没响应直接断开
			CURLOPT_TIMEOUT => $timeout, //文件下载时间 超过直接断开
		];
		if($setoptDataAppend){
			foreach($setoptDataAppend as $i => $value){
				foreach($value as $key => $val){
					$setoptDataAppend[$i][$key] = $key.': '.$val;
				}
			}
		}
		if($header){
			foreach($header as $i => $value){
				foreach($value as $key => $val){
					$header[$i][$key] = $key.': '.$val;
				}
			}
		}
		if($resolve){
			foreach($resolve as $key => $value){
				$resolve[$key] = $key.': '.$val;
			}
		}

		$mh = curl_multi_init();
		$CH = [];
		foreach ($URLS as $i => $url) {
			$startTime[$i] = microtime(true);
			$setopt = $setoptData;
			$setopt[CURLOPT_URL] = $url;
			if($header[$i]){
				$setopt[CURLOPT_HTTPHEADER] = $header[$i];
			}
			if($resolve){
				$setopt[CURLOPT_RESOLVE] = $resolve;
			}
			if($post) {
				$setopt[CURLOPT_POST] = 1;
				$setopt[CURLOPT_POSTFIELDS] = $post;
			}
			$CH[$i]=curl_init();
			 curl_setopt_array($CH[$i],$setopt);
			 curl_multi_add_handle($mh,$CH[$i]);
		}

		do{
				$mrc = curl_multi_exec($mh,$active);
		}while($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active and $mrc == CURLM_OK) {
				if (curl_multi_select($mh) != -1) {
						do {
								$mrc = curl_multi_exec($mh, $active);
						} while ($mrc == CURLM_CALL_MULTI_PERFORM);
				}
		}
		$Request = [];
		$Info = [];
		$Error = [];
		foreach ($URLS as $i => $url) {
			$errno = curl_errno($CH[$i]);
			$info = curl_getinfo($CH[$i]);
			$body = curl_multi_getcontent($CH[$i]);
			$Request[$i] =[
				'httpcode' => $info['http_code'],
				'error' => $errno,
				'data' => (string)substr($body, $info['header_size'])
			];
			curl_close($CH[$i]);
			$endTime = microtime(true);
			APP::debug('CURL',['url'=>$url, 'queryTime'=>round(($endTime - $startTime[$i]) ,5),'data'=>$Request[$i]['data'],'post'=>$post[$i],'header'=>$header[$i],'status'=>$info]);
			unset($errno,$info,$body);
		}
		return $Request;
	}
}