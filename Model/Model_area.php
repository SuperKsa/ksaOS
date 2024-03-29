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
    /**
     * 读取所有地区数据
     * @return array
     */
    static function All(){
		$data = (array)DB('area')->cache('areaAll',900)->order('orders','asc')->fetch_all('id');
		return $data;
	}

    /**
     * 判断指定地区ID组合是否存在
     * @param int $province 省ID
     * @param int $city 市ID
     * @param int $area 县ID
     * @param int $town 镇ID
     * @return array 输出对应地址
     */
    static function address($province, $city, $area, $town){
        $data = self::All();
        $isset = [];
        if($province && $data[$province] && $data[$province]['upid'] ==0){
            $isset[0] = $data[$province]['name'];
        }
        if($city && $data[$city] && $data[$city]['upID'] == $province){
            $isset[1] = $data[$city]['name'];
        }
        if($area && $data[$area] && $data[$area]['upID'] == $city){
            $isset[2] = $data[$area]['name'];
        }
        if($town && $data[$town] && $data[$town]['upID'] == $area){
            $isset[3] = $data[$town]['name'];
        }
        $dt = [];
        if((!$town || $isset[3]) && (!$area || $isset[2]) && (!$city || $isset[1]) && (!$province || $isset[0])){
            $dt = $isset;
        }
        return $dt;
    }

    /**
     * 读取指定上级ID的地区信息
     * @param int $upID
     * @param array $select
     * @return array
     */
    static function data($upID=0, $select=[]){
		$upID = intval($upID);
		$query = (array)DB('area')->cache('area_upID_'.$upID,900)->where('upID',$upID)->order('orders','asc')->fetch_all('id');
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