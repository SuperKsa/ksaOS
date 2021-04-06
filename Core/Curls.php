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
	 * @return array
	 */
	public static function send($url='', $post=[], $header=[], $timeout=15, $resolve=[], $referer=''){
		global $C;
		APP::hook(__CLASS__ , __FUNCTION__);
		$startTime = microtime(true);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
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
		curl_setopt($ch, CURLOPT_HEADER, true);
		if($post) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post === true ? '' : $post);
		}
		if($referer){
            curl_setopt($ch, CURLOPT_REFERER, $referer);
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
	 * @param array $URLS 地址数组 注意下方说明
	 * 纯URL模式
	 * ['url-1','url-2','url-3' ... ]
	 * 
	 * 组合模式
	 * [
	 *	[
	 *		'url' => 'http://xxx', //请求地址
	 *		'post' => [], //需要post的数据 数组或者string
	 *		'header' => [], //需要发送的header 必须数组 ['token'=>'xxx', 'timestamp'=>1234]
	 *		'ip' => '192.168.0.1', //请求地址需要指定的IP地址
	 *	],
	 * ]
	 * 
	 * @param array/string $post 所有url的POST数据（$URLS组合模式中未指定post的所有url都继承该值 如不需要，必须给定一个空值）
	 * @param array $header 所有url的请求头数组（$URLS组合模式中未指定header的所有url都继承该值 如不需要，必须给定一个空值）
	 * @param int $timeout 建立连接后的超时时间 秒
	 * @param array $resolve 所有url的域名指定IP、端口的设置 每个元素以冒号分隔。格式： array('xxx' => 'xxx')
	 * @param array $setoptDataAppend 所有url的附加setopt参数
	 * @return array
	 */
	public static function sends($URLS=[], $post=[], $header=[], $timeout=15, $resolve=[], $setoptDataAppend=[]){
		APP::hook(__CLASS__ , __FUNCTION__);
		//整理数据
		$Sends = [];
		$i = 0;
		foreach($URLS as $key => $value){
			//如果单传一条URL过来 则初始化value变量为数组
			if(is_string($value)){
				$value = ['url' => $value];
			}
			$value['post'] = isset($value['post']) ? $value['post'] : $post;
			$value['header'] = isset($value['header']) ? $value['header'] : $header;
			$value['resolve'] = isset($value['resolve']) ? $value['resolve'] : $resolve;
			if(isset($value['ip'])){
				$u = parse_url($value['url']);
				$value['resolve'][$u['host']] = $value['ip'];
			}
			if(is_array($value['header'])){
				foreach($value['header'] as $k => $v){
					$value['header'][$k] = $k.': '.$v;
				}
			}
			$Sends[$i] = [
				'url' => $value['url'],
				'post' => $value['post'],
				'header' => $value['header'],
				'resolve' => $value['resolve'],
			];
			$i ++;
		}
		$setoptData = [
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HEADER => 3,
			CURLOPT_CONNECTTIMEOUT => 5, //x秒内没响应直接断开
			CURLOPT_TIMEOUT => $timeout, //连接后超过N秒没获取到数据直接断开 如一个文件10秒未下载完成则断开
		];
		if($setoptDataAppend){
			foreach($setoptDataAppend as $key => $value){
					$setoptData[$key] = $key.': '.$value;
			}
		}
		$mh = curl_multi_init();
		$CH = [];
		$startTime = [];
		foreach ($Sends as $i => $value) {
			$startTime[$i] = microtime(true);
			$setopt = $setoptData;
			$setopt[CURLOPT_URL] = $value['url'];
			if($value['header']){
				$setopt[CURLOPT_HTTPHEADER] = $value['header'];
			}
			if($value['resolve']){
				$setopt[CURLOPT_RESOLVE] = $value['resolve'];
			}
			if($value['post']) {
				$setopt[CURLOPT_POST] = 1;
				$setopt[CURLOPT_POSTFIELDS] = $value['post'];
			}
			$CH[$i]=curl_init();
			 curl_setopt_array($CH[$i],$setopt);
			 curl_multi_add_handle($mh,$CH[$i]);
		}
		$active = NULL;
		do{
			$mrc = curl_multi_exec($mh,$active);
		}while($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($mh) != -1) {
				do {
					$mrc = curl_multi_exec($mh, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}
		//通过curl_multi_info_read 卡住进程，等待请求完成 获取已完成线程的结果
		while($done = curl_multi_info_read($mh)) {}
		
		$Info = [];
		$Error = [];
		$i =0;
		foreach ($URLS as $key => $value) {
			$errno = curl_errno($CH[$i]);
			$info = curl_getinfo($CH[$i]);
			$body = curl_multi_getcontent($CH[$i]);
			curl_multi_remove_handle($mh, $CH[$i]);
			curl_close($CH[$i]);
			if(isset($value['header'])){
				unset($value['header']);
			}
			if(isset($value['post'])){
				unset($value['post']);
			}
			if(isset($value['resolve'])){
				unset($value['resolve']);
			}
			
			$value['url'] = $value['url'];
			$value['httpcode'] = $info['http_code'];
			$value['error'] = $errno;
			$value['data'] = (string)substr($body, $info['header_size']);
			$URLS[$key] = $value;
			
			APP::debug('CURL',['url'=>$value['url'], 'queryTime'=>round((microtime(true) - $startTime[$i]) ,5),'data'=>$body,'post'=>(isset($Sends[$i]['post']) ? $Sends[$i]['post'] : ''),'header'=>(isset($Sends[$i]['header']) ? $Sends[$i]['header'] : ''),'status'=>$info]);
			$i ++;
		}
		unset($errno,$info,$body);
		curl_multi_close($mh);
		
		
		return $URLS;
	}
}