<?php

/**
 * 扩展库模块 - 登录相关
 * 所有function必须使用 public
 * @date    2019-11-28 17:06:04
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file User.php (ksaOS / UTF-8)
 */
namespace ksaOS\M;

if(!defined('KSAOS')) {
	exit('Error.');
}

class user extends \ksaOS\M{
	
	/**
	 * 校验用户token是否有效并自动登录
	 * @param string $token 用户token（选传）由当前类函数userlogin生成的token
	 * @return string 已登录或成功返回user数据且附带token字段 否则=false
	 */
	static function isLogin(string $token=''){
		global $C;
		if($C['user']){
			return $C['user'];
		}
		$user = self::checkToken($token);
		if($user && $user['uid'] && $user['token']){
			unset($user['salt'],$user['password']);
			$user['avatar'] = \ksaOS\APP::Attach()->Url('avatar',$user['avatar']);
			$user['token'] = $token;
			$C['uid'] = $user['uid'];
			$C['user'] = $user;
			$C['token'] = $token;
			return $user;
		}
		return false;
	}
	
	/**
	 * 用户退出登录
	 * M\User::Out('token','token2'); //一个参数代表一个需要清理的cookies
	 * @return boolean
	 */
	static function Out(){
		global $C;
		$C['user'] = [];
		$C['uid'] = 0;
		$C['token'] = '';
		\ksaOS\cookies('token','');
		foreach(func_get_args() as $value){
			\ksaOS\cookies($value,'');
		}
		return true;
	}
	
	/**
	 * 用户登录判断 - 前端
	 * @global type $C
	 * @param type $user 用户原始信息
	 * @param type $account 登录帐号
	 * @param type $password 用户提交的明文密码
	 * @return boolean 成功返回user数据且附带token字段 否则=false
	 */
	static function Login(array $user=[], string $account='', string $password=''){
		global $C;
		if($account && $password && $user && is_array($user) && isset($user['uid']) && $user['password']){
			$PWstatus = self::checkPassword($user, $password);
			if($PWstatus){
				$token = self::getToken($user);
				$s = \ksaOS\cookies('token',$token,86400 * 15);
				unset($user['salt'],$user['password']);
				$user['token'] = $token;
				$C['user'] = $user;
				$C['uid'] = $user['uid'];
				$C['token'] = $token;
				return $user;
			}
		}
		return false;
	}
	
	/**
	 * 校验用户的密码是否正确
	 * @param array $user 指定用户信息
	 * @param string $password 需要校验的密码
	 * @return boolean true=正确 false=错误
	 */
	static function checkPassword($user=[], $password=''){
		if($password && $user && is_array($user) && isset($user['uid']) && $user['password'] && $user['salt']){
			$uid = $user['uid'];
			$passwordMD5 = md5($password.md5($user['salt']));
			if($passwordMD5 === $user['password']){
				return true;
			}
		}
		return false;
	}
	
	/**
	 * 校验用户token是否有效
	 * @param string $token 需要校验的token值
	 * @param type $ck 混淆密钥（可选）
	 * @return boolean|array 成功返回用户数据且附带token字段 否则false
	 */
	static function checkToken(string $token='', $ck=''){
		$decodetoken = \ksaOS\base('DECODE',$token);
		list($uid,$password) = explode('_', $decodetoken);
		$uid = intval($uid);
		if($uid && $password){
			$user = \ksaOS\DB('user')->where('uid',$uid)->fetch_first();
			$pw = self::__tokenPW($user, $ck);
			if($password == $pw){
				unset($user['salt'],$user['password']);
				$user['token'] = $token;
				return $user;
			}
		}
		return false;
	}
		
	/**
	 * 根据用户信息生成一条token
	 * @param array $user 用户信息
	 * @param type $ck 混淆密钥（可选）
	 * @return string token
	 */
	static function getToken($user=[], $ck=''){
		$token = self::__tokenPW($user, $ck);
		if($token){
			$token = \ksaOS\base('ENCODE', $user['uid'].'_'.$token);
		}
		return $token;
	}
	
	/**
	 * 生成token中的密码混淆
	 * @param type $user 指定用户信息
	 * @param type $ck 混淆密钥（可选）
	 * @return type 成功返回加密后的密码
	 */
	static function __tokenPW($user=[], $ck=''){
		$pw = '';
		if(is_array($user) && isset($user['uid']) && $user['password'] && $user['salt']){
			$pw = md5(ENCODEKEY.md5($user['uid'].$user['password']).$user['password'].$user['salt']);
		}
		return $pw;
	}
	
	/**
	 * 生成密码与混淆字符
	 * @param type $pw 需要生成的密码明文
	 * @return array 返回：参数1=密码 参数2=混淆字符
	 */
	static function getPwSign($pw){
		$salt = \ksaOS\rands(6);
		$pw = md5($pw.md5($salt));
		return ['password'=>$pw,'salt'=>$salt];
	}
}