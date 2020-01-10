<?php

/**
 * 
 * 暂无介绍
 * @date    2020-1-10 22:09:42
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Model_area.php (____ / UTF-8)
 */

namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Model_area{
	function All(){
		$data = DB('area')->cache('areaAll',900)->order('orders','asc')->fetch_all('id');
		return $data;
	}
	
	function data($upID=0, $select=[]){
		$upID = intval($upID);
		$query = DB('area')->cache('area_upID_'.$upID,900)->where('upID',$upID)->order('orders','asc')->fetch_all('id');
		if($select && is_array($select)){
			$data = [];
			foreach($query as $key => $value){
				foreach($value as $k => $val){
					if(in_array($k, $select)){
						$data[$key][$k] = $val;
					}
				}
			}
		}else{
			$data = $query;
		}
		return $data;
	}
}