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
    private static $MessageSend_API = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=';

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
        $token = Wechat::AccessToken($APPID, $AppSecret);
        $touser = $sendData['touser'];
        $oldData = $sendData;
        $wechatSetting = APP::setting('WECHAT');
        $oldData['appid'] = $wechatSetting['APPID'];
        unset($oldData['touser']);
        $sendData = [
            'access_token' => $token,
            'touser' => $touser,
            'mp_template_msg' => $oldData
        ];
        $send = Curls::send(self::$MessageSend_API.$token, jsonEn($sendData));
        $send['data'] = $send['data'] ? json_decode($send['data'], 1) : [];
        if($send['data']['errcode'] == 0 && $send['data']['errmsg'] =='ok'){
            return true;
        }
        return false;
    }

    /**
     * 生成小程序二维码
     * @param string $page 小程序访问路径 此参数存在时，小程序必须为正式版
     * @param int $width 二维码宽度
     * @param string $scene 路径参数 最大32个可见字符
     * @param string $auto_color  自动配置线条颜色，如果颜色依然是黑色，则说明不建议配置主色调，默认 false
     * @param string $line_color auto_color 为 false 时生效，使用 rgb 设置颜色 例如 {"r":"xxx","g":"xxx","b":"xxx"} 十进制表示
     * @param string $is_hyaline 是否需要透明底色，为 true 时，生成透明底色的小程序
     * @return array
     */
    static function getQrcode($page='', $scene='', $width=430, $auto_color=false, $line_color=['r'=>'0','g'=>'0','b'=>'0'], $is_hyaline=false){
        $page = trim($page);
        $auto_color = $auto_color ? true : false;
        $is_hyaline = $is_hyaline ? true : false;
        $width = $width > 1280 ? 1280 : $width;
        $return = [
            'src' => '',
            'msg' => '生成失败'
        ];
        $post = [
            'scene' => $scene,
            'width' => (string) $width  ,
            'auto_color' => $auto_color,
            'line_color' => $line_color,
            'is_hyaline' => $is_hyaline
        ];
        if($page){
            $post['page'] = $page;
        }
        if($line_color){
            foreach($line_color as $key => $value){
                $line_color[$key] = (string) $value;
            }
            $post['line_color'] = $line_color;
        }
        $KEY = md5(json_encode($post));
        $fileName = $KEY.'.'.($is_hyaline ? 'png' : 'jpg');
        $dir = ATTACHDIR.'weapp_qrcode/'.substr($KEY, 0, 2).'/';

        if(is_file(ROOT . $dir . $fileName)){
            $return['src'] = $dir.$fileName;
            $return['msg'] = 'success';
        }else {
            $weappSetting = APP::setting('WEAPP');
            $access_token = Wechat::AccessToken($weappSetting['APPID'], $weappSetting['AppSecret']);
            $curl = Curls::send('https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $access_token, jsonEn($post));
            $jsonData = json_decode($curl['data'], true);
            if ($curl['data'] && $jsonData && is_array($jsonData)) {
                $errorLang = [
                    45009	=> '调用分钟频率受限(目前5000次/分钟，会调整)，如需大量小程序码，建议预生成。',
                    41030	=> '所传page页面不存在，或者小程序没有发布'
                ];
                $return['msg'] =$errorLang[$jsonData['errcode']] ? $errorLang[$jsonData['errcode']] : $jsonData['errmsg'];
            } elseif ($curl['data']) {
                Files::mkdir(ROOT . $dir);
                if (file_put_contents(ROOT . $dir . $fileName, $curl['data']) && is_file(ROOT . $dir . $fileName)) {
                    $return['src'] = $dir . $fileName;
                    $return['msg'] = 'success';
                }
            }
        }
        return $return;
    }
}
