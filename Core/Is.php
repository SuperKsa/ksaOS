<?php

/**
 * is判断类
 *  暂无介绍
 * @date    2019-11-28 16:41:52
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Is.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class IS{
	const _name = 'ksaOS 判断处理类';
	/**
	 * 判断是否为POST
	 * @param type $formID 前台是否提交该表单名称
	 * @return boolean 成功返回true
	 */
	public static function POST(string $formID=''){
		if($_SERVER['REQUEST_METHOD'] == 'POST'){
			if($formID){
				if($_POST['FORMID'] == $formID){
					return true;
				}else{
					return false;
				}
			}else{
				return true;
			}
		}
	}
	
	/**
	 * 判断的当前访问是否是移动端
	 * @return boolean true=是
	 */
	public static function Pmd(){
		if(defined('MOBILE') && MOBILE){
			return true;
		}
		if(!isset($_SERVER['HTTP_USER_AGENT'])){
			return false;
		}
		$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
		if(strpos($useragent,'android') !== false){
			return true;
		}
		return false;
	}
	
	/**
	 * 判断当前是否是微信
	 * @return boolean true=是
	 */
	public static function Wechat(){
		if(defined('WECHAT') && WECHAT){
			return true;
		}
		if(!isset($_SERVER['HTTP_USER_AGENT'])){
			return false;
		}
		$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
		if(strpos($useragent,'android') !== false || strpos($useragent,'iphone') !== false){
			if(strpos($useragent, 'micromessenger') !== false){
				return true;
			}
		}
		return false;
	}
	
	/**
	 * 判断是否为邮箱地址
	 * @param string $email 邮箱地址
	 * @return type 成功返回true
	 */
	public static function email(string $email='') {
		return filter_var($email, FILTER_VALIDATE_EMAIL) ? true : false;
	}
	
	/**
	 * 判断是否为IPV6地址
	 * @param string $ip IPV4或IPV6地址
	 * @param type $isIPv6 1=IPV6 默认IPV4
	 * @return boolean
	 */
	public static function IP(string $ip='', $isIPv6=false) {
		return $isIPv6 ? filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) : filter_var($ip, FILTER_VALIDATE_IP);
	}
	
	/**
	 * 判断是否为电话号码
	 * @param type $str
	 * @param type $cn
	 * @return boolean
	 */
	public static function Phone($str=0, $cn=86, $type='mobile') {
		if($type == 'mobile' && $cn == 86 && mb_strlen($str) == 11 && preg_match("/^1([0-9]{10})$/", $str)){
			return true;
		}
		return false;
	}
	
	/**
	 * 条件判断并输出一条错误提示
	 * @param type $condition if条件
	 * @param type $msg 错误提示信息
	 */
	public static function F($condition, $msg=''){
		if($condition){
			Msg($msg);
		}
	}
}