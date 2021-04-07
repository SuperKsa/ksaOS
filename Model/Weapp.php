<?php
/**
 * 微信小程序处理类
 * -------------------------------
 * Author:  CR180 <cr180@cr180.com>
 * Date:    2020/2/24 23:12
 * Update:  2020/2/24 23:12
 *
 */

namespace ksaOS;

if(!defined('KSAOS')) {
    exit('Error.');
}


class Weapp {

    const _name = '微信小程序处理类';

    //AccessToken 接口地址
    private static $ACCESS_TOKEN_API = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={appid}&secret={secret}';
    //小程序统一服务消息发送接口
    private static $MessageSend_API = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send';

    //获取用户资料接口
    private static $API_USERINFO = 'https://api.weixin.qq.com/sns/jscode2session?appid={appid}&secret={appsecret}&js_code={code}&grant_type=authorization_code';

    /**
     * 小程序 获取用户openid
     * @param string $code
     * @param array $config 小程序配置参数
     * @return array|mixed
     */
    static function UserInfo($code='', $config=[]){
        $setting = APP::setting('WEAPP');
        $APPID = $setting['APPID'];
        $AppSecret = $setting['AppSecret'];
        if($config){
            if($config['AppID']){
                $APPID = $config['AppID'];
            }
            if($config['AppSecret']){
                $AppSecret = $config['AppSecret'];
            }
        }
        $curl = Curls::send(str_replace(['{appid}', '{appsecret}', '{code}'], [$APPID, $AppSecret, $code], self::$API_USERINFO));
        $data = $curl['data'] ? json_decode($curl['data'], true) : [];
        $data = $data['openid'] ? $data : [];
        return $data;
    }

    /**
     * 小程序统一服务消息发送接口
     * @param string $APPID APPID
     * @param string $AppSecret 接口密钥
     * @param string $openid 接收消息的用户openid 可以是小程序的openid，也可以是mp_template_msg.appid对应的公众号的openid
     * @param array $weapp_template_msg 小程序模板消息相关的信息，可以参考小程序模板消息接口; 有此节点则优先发送小程序模板消息
     * @param array $mp_template_msg 公众号模板消息相关的信息，可以参考公众号模板消息接口；有此节点并且没有weapp_template_msg节点时，发送公众号模板消息
     */
    static function MessageSend($APPID='', $AppSecret='', $sendData=[]){
        $setting = APP::setting('WEAPP');
        $token = Wechat::AccessToken($APPID, $AppSecret);
        $touser = $sendData['touser'];
        $oldData = $sendData;
        $oldData['appid'] = $setting['APPID'];
        unset($oldData['touser']);
        $sendData = [
            'access_token' => $token,
            'touser' => $touser,
            'mp_template_msg' => $oldData
        ];
        $send = Curls::send(self::$MessageSend_API.'?access_token='.$token, jsonEn($sendData));
        $send['data'] = $send['data'] ? json_decode($send['data'], 1) : [];
        if($send['data']['errcode'] == 0 && $send['data']['errmsg'] =='ok'){
            return true;
        }
        return false;
    }
}