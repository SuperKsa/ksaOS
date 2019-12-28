<?php

/**
 * 前端网页参数配置
 *  暂无介绍
 * @date    2019-11-30 0:16:50
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Documents.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Documents{
	const _name = 'ksaOS前端页面处理类';
	
	public static function title($str=''){
		global $C;
		if(!isset($C['TITLEdata'])){
			$C['TITLEdata'] = [];
		}
		$str = trims($str);
		if($str){
			$C['TITLE'] = $str.($C['TITLE'] ? '-'.$C['TITLE'] : '');
			$C['TITLEdata'][] = $str;
		}
		APP::hook(__CLASS__ , __FUNCTION__);
	}
	
	public static function desc($str=''){
		global $C;
		if($str){
			$str = trims($str);
			$C['description'] = $str;
		}
		APP::hook(__CLASS__ , __FUNCTION__);
	}
	
	public static function keywords($str=''){
		global $C;
		$keywords = [];
		if(!is_array($str)){
			$str = explode(',',$str);
		}
		$str = trims($str);
		foreach($str as $value){
			$value = trim($value);
			$keywords[$value] = $value;
		}
		if($keywords){
			$keywords = implode(',',$keywords);
			$C['keywords'] = $keywords;
		}
		APP::hook(__CLASS__ , __FUNCTION__);
	}
}