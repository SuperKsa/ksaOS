<?php

/**
 * 腾讯云验证码模块
 * 
 * 配置方法：
 * 平台网址：https://007.qq.com/captcha/
 *	1、登录帐号 - 进入配置中心进行配置
 *	2、配置完成后，进入快速接入
 *	3、将快速接入页面中的App ID、App Secret Key、客户端接入网址、服务器接入地址分别复制在下方程序中
 *	4、参考快速接入页面中的接入文档做好接入
 * -------------------------------------------------
 * PHP端的处理：
 *	1、获取AppID      $appID = APP::model('Captcha_QQ::appid');
 *	2、获取前端接入地址     $scriptUrl = APP::model('Captcha_QQ::srciptUrl');
 *	3、校验Ticket与Randstr      true = APP::model('Captcha_QQ::Check', $Ticket, $Randstr); //参数2=前端提交的Ticket 参数3=前端提交的Randstr
 * 
 * 前端例子（根据实际情况修改）：
 *	
<script>
// 绑定一个元素并手动传入场景Id和回调
var QQCaptcha = new TencentCaptcha(
	document.getElementById('TencentCaptcha'),
	"{echo APP::model('Captcha_QQ::appid');}",
	function(res) {
		// res（用户主动关闭验证码）= {ret: 2, ticket: null}
		// res（验证成功） = {ret: 0, ticket: "String", randstr: "String"}
		if(res.ret === 0){
			$('input[name=Ticket]').val(res.ticket);
			$('input[name=Randstr]').val(res.randstr);
			$('#loginform').submit();
		}
	}
);
//QQCaptcha.show(); //初始化完成即显示验证码弹窗

</script>
 * 
 * @date    2019-11-29 11:11:58
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Qcaptcha.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Qcaptcha{
	const AID = CONFIG_QQCAPTCHA['APPID']; //AppID
	const AppSecretKey = CONFIG_QQCAPTCHA['AppSecretKey']; //App Secret Key
	const srciptUrl = 'https://ssl.captcha.qq.com/TCaptcha.js'; //客户端接入网址
	const CheckUrl = 'https://ssl.captcha.qq.com/ticket/verify'; //服务器接入地址
	
	function srciptUrl(){
		$url = self::srciptUrl;
		return $url;
	}
	
	function APPID(){
		return self::AID;
	}
	
	function Check($Ticket='' ,$Randstr=''){
		global $C;
		
		if($Ticket && $Randstr){
			$get = [
				'aid' => self::AID,
				'AppSecretKey' => self::AppSecretKey,
				'Ticket' => $Ticket,
				'Randstr' =>$Randstr,
				'UserIP' => $C['IP']
			];
			$url = '';
			foreach($get as $k => $v){
				$url .= '&'.$k.'='.$v;
			}
			$data = APP::Curl()->send(self::CheckUrl.'?'.$url);
			$data = $data ? json_decode($data['data'],true) : [];
			if($data['response'] == '1'){
				return true;
			}
		}
		return false;
	}
}
