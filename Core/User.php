<?php

/**
 * 登录相关
 * 所有函数必须静态调用
 * @date    2019-11-28 17:06:04
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file User.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class User{
    //API输出时允许全局输出的user字段
    static $userInfoField = ['uid','name','avatar','sex','mobile'];

    /**
     * 获取用户信息
     * @param int $id 用户ID，一个或数组
     * @param string $select 需要的字段 默认*
     * @return array 根据$id智能返回
     */
	static function info($id=0, $select='*'){
	    $id = ints($id,1,1);
	    if($id) {
            $data = DB('user')->select($select)->where('uid', $id)->fetch_all('uid');
            foreach ($data as $key => $value) {
                if($value['avatar']) {
                    $value['avatar'] = APP::Attach()->Url('avatar', $value['avatar']);
                }
                $data[$key] = $value;
            }
            if(!is_array($id)){
                $data = $data[$id];
            }
        }
	    return $data;
    }
    
    static function WXopenidInfo($openid='', $select='*'){
        $data = DB('user')->select($select)->where('WXopenid', $openid)->fetch_first();
        return $data;
    }
    
    static function WXunionid($unionid='', $select='*'){
        $data = DB('user')->select($select)->where('WXunionid', $unionid)->fetch_first();
        return $data;
    }

    /**
     * 过滤API接口输出user字段
     * @param array $user 用户信息
     * @return array|mixed 返回过滤后的字段数据
     */
    static function apiInfo($user=[]){
	    if(self::$userInfoField) {
            foreach ($user as $key => $value) {
                if (!in_array($key, self::$userInfoField)) {
                    unset($user[$key]);
                }
            }
        }
        return $user;
    }

	/**
	 * 校验用户token是否有效并自动登录
	 * @param string $token 用户token（选传）由当前类函数userlogin生成的token
	 * @return string 已登录或成功返回user数据且附带token字段 否则=false
	 */
	static function isLogin($token=''){
		global $C;

		$user = self::checkToken($token);
        if($user && $C['user'] && $C['user']['uid'] == $user['uid']){
            return $C['user'];
        }
		if($user && $user['uid'] && $user['token']){
            cookies('token',$token,86400 * 15); //token保持在线
			unset($user['salt'],$user['password']);
			$user['avatar'] = APP::Attach()->Url('avatar',$user['avatar']);
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
	 * Out('token','token2'); //一个参数代表一个需要清理的cookies
	 * @return boolean
	 */
	static function Out(){
		global $C;
		$C['user'] = [];
		$C['uid'] = 0;
		$C['token'] = '';
		cookies('token','');
		foreach(func_get_args() as $value){
			cookies($value,'');
		}
		return true;
	}

	
	/**
	 * 用户登录(登录成功，写cookie：token并返回token串)
	 * @global array $C
	 * @param array $user 用户原始信息
	 * @param string $account 登录帐号 (微信登录 值固定为WECHAT)
	 * @param string $password 用户提交的明文密码(微信登录 值=WXopenid字段值)
	 * @return string/boolean 成功返回user数据且附带token字段 否则=false
	 */
	static function Login($user=[], $account='', $password=''){
		global $C;
		if($account && $password && $user && is_array($user) && isset($user['uid']) && $user['password']){
            $PWstatus = false;
		    if($account =='WECHAT'){
		        if(($user['WXopenid'] && $password == $user['WXopenid']) || ($user['WXunionid'] && $password == $user['WXunionid'])){
                    $PWstatus = true;
                }
            }else{
                $PWstatus = self::checkPassword($user, $password);
                if(!$PWstatus){
                    $PWstatus = $user && $user['mobile'] == $account && $user['password'] == $password;
                }
            }

			if($PWstatus){
				$token = self::getToken($user);
				cookies('token',$token,86400 * 15);
				unset($user['salt'],$user['password']);
				$user['token'] = $token;
				$C['user'] = $user;
				$C['uid'] = $user['uid'];
				$C['token'] = $token;
				DB('user_status')->where('uid',$user['uid'])->update(['loginIP'=>$C['IP'],'loginPort'=>$C['port'],'loginDate'=>time(),'lastIP'=>$C['IP'],'lastPort'=>$C['port'],'lastDate'=>time()]);
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
     * 解码token
     * @param $token
     * @param $ck
     *
     * @return string
     */
    static function tokenDecode($token='', $ck=''){
        $ck .= ENCODEKEY;
		return KsaCode::decode($token, $ck);
    }
	
	/**
	 * 校验用户token是否有效
	 * @param string $token 需要校验的token值
	 * @param string $ck 混淆密钥（可选）
	 * @return boolean|array 成功返回用户数据且附带token字段 否则false
	 */
	static function checkToken($token='', $ck='', $user=[]){
        
        $decodetoken = self::tokenDecode($token, $ck);
        
		[$uid,$password] = explode('_', $decodetoken);
		$uid = intval($uid);
		if($uid && $password){
            $user = DB('user')->where('uid',$uid)->fetch_first();
			$pw = self::pwHash($user, $ck);
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
	 * @param string $ck 混淆密钥（可选）
	 * @return string token
	 */
	static function getToken($user=[], $ck=''){
		$token = self::pwHash($user, $ck);
		if($token){
            $ck .= ENCODEKEY;
			$token = KsaCode::encode($user['uid'].'_'.$token, $ck);
		}
		return $token;
	}
	
	/**
	 * 生成token中的密码混淆
	 * @param array $user 指定用户信息
	 * @param string $ck 混淆密钥（可选）
	 * @return string 成功返回加密后的密码
	 */
	static function pwHash($user=[], $ck=''){
        $ck .= ENCODEKEY;
		$pw = '';
		if(is_array($user) && isset($user['uid']) && $user['password'] && $user['salt']){
			$pw = md5($ck.md5($user['uid'].$user['password']).$user['password'].$user['salt']);
		}
		return $pw;
	}
	
	/**
	 * 生成密码与混淆字符
	 * @param string $pw 需要生成的密码明文
	 * @return array 返回：参数1=密码 参数2=混淆字符
	 */
	static function getPwSign($pw){
		$salt = rands(6);
		$pw = md5($pw.md5($salt));
		return ['password'=>$pw,'salt'=>$salt];
	}
}