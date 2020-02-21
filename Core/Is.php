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
     * @param  string  $formID 前台是否提交该表单名称
     *
     * @return bool 成功返回true
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
	 * @return boolean 成功返回true
	 */
	public static function email(string $email='') {
		return filter_var($email, FILTER_VALIDATE_EMAIL) ? true : false;
	}
	
	/**
	 * 判断是否为IPV6地址
	 * @param string $ip IPV4或IPV6地址
	 * @param string $isIPv6 1=IPV6 默认IPV4
	 * @return boolean
	 */
	public static function IP(string $ip='', $isIPv6=false) {
		return $isIPv6 ? filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) : filter_var($ip, FILTER_VALIDATE_IP);
	}

    /**
     * 判断是否为电话号码
     * @param  int     $str
     * @param  int     $cn
     * @param  string  $type
     *
     * @return bool
     */
	public static function Phone($str=0, $cn=86, $type='mobile') {
		if($type == 'mobile' && $cn == 86 && mb_strlen($str) == 11 && preg_match("/^1([0-9]{10})$/", $str)){
			return true;
		}
		return false;
	}

    /**
     * 判断是否为手机号码
     * @param  int  $str 手机号
     *
     * @return bool
     */
	public static function Mobile($str=0) {
		if(mb_strlen($str) == 11 && preg_match("/^1([0-9]{10})$/", $str)){
			return true;
		}
		return false;
	}

    /**验证是否为身份证号码
     * @param int $str
     * @return bool
     */
	public static function idCardCode($str=0){
        $SCODE = [11=>'北京',12=>'天津',13=>'河北',14=>'山西',15=>'内蒙古',21=>'辽宁',22=>'吉林',23=>'黑龙江',31=>'上海',32=>'江苏',33=>'浙江',34=>'安徽',35=>'福建',36=>'江西',37=>'山东',41=>'河南',42=>'湖北',43=>'湖南',44=>'广东',45=>'广西',46=>'海南',50=>'重庆',51=>'四川',52=>'贵州',53=>'云南',54=>'西藏',61=>'陕西',62=>'甘肃',63=>'青海',64=>'宁夏',65=>'新疆',71=>'台湾',81=>'香港',82=>'澳门',91=>'国外'];

        preg_match_all('/(\d{2})(\d{4})(\d{4})(\d{2})(\d{2})(\d{2})(\d)(\d)/',$str,$N);
        if(!$SCODE[$N[1][0]]){
            return false;
        }
        //验证3-6位城市代码
        if(!is_numeric($N[2][0])){
            return false;
        }

        //验证出生日期
        $n3 = $N[3][0].'-'.$N[4][0].'-'.$N[5][0];
        if(!strtotime($n3)){
            return false;
        }

        if($N[7][0]){

        }

        //检测最后一位 按照ISO 7064:1983.MOD 11-2的规定生成
        $map = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $factor = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $sign = 0;
        for($i = 0; $i < 17; $i++ ){
            $sign += intval($str{$i}) * $map[$i];
        }
        $n = $sign % 11;
        if ($factor[$n] == $N[8][0]){
            return true;
        }
	    return false;
    }

    /**
     * 条件判断并输出一条错误提示
     * @param  bool $condition
     * @param  string  $msg
     */
	public static function F($condition, $msg=''){
		if($condition){
			Msg($msg);
		}
	}
}