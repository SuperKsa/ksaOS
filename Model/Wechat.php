<?php
/**
 * 微信对接类
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


class Wechat {

    const _name = '微信业务处理类';

    private static  $WECHAT_API = 'https://api.mch.weixin.qq.com';
    //AccessToken 接口地址
    private static $ACCESS_TOKEN_API = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={appid}&secret={secret}';
    //jsapi支付接口地址
    private static $WECHAT_API_JSAPI = '/v3/pay/transactions/jsapi';
    //微信支付查询订单接口地址
    private static $WECHAT_API_QUERY= '/v3/pay/transactions/id/';
    //微信支付退款订单接口地址
    private static $WECHAT_API_REFUNDS= '/v3/refund/domestic/refunds';
    //小程序统一服务消息发送接口
    private static $WEAPP_MessageSend_API = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send';

    //服务号统一服务消息发送接口
    private static $SERVICE_MessageSend_API = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=';

    //获取前端ticket jsapi接口地址
    private static $TICKET_API_JSAPI = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={token}&type=jsapi';

    private static $USER_CODE_TOKEN_API = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid={appid}&secret={secret}&code={code}&grant_type=authorization_code';
    private static $USER_CODE_INFO_API = 'https://api.weixin.qq.com/sns/userinfo?access_token={token}&openid={openid}&lang=zh_CN';

    /**
     * 获取微信基础 access_token
     * 缓存有效期 7200秒
     * @param string $APPID
     * @param string $AppSecret
     * @return mixed|void
     */
    static function AccessToken($APPID='', $AppSecret='', $timeOut=7200){
        $setting = APP::setting('WECHAT');
        $APPID = $APPID ? $APPID : $setting['APPID'];
        $AppSecret = $AppSecret ? $AppSecret : $setting['AppSecret'];
        if(!$APPID || !$AppSecret){
            return ;
        }
        $cacheKey = 'WX_AccessToken_'.md5($APPID.$AppSecret);
        $accessToken = Cache($cacheKey);

        $outTime = intval($accessToken['expires_in']); //token有效期
        $outTime = $outTime && $outTime < $timeOut ? $outTime : $timeOut; //token过期时间
        $access_token_time = intval($accessToken['dateline']); //token缓存时间
        $access_token = $accessToken['access_token'];

        //如果token未过期
        if($access_token_time + $outTime < time() || !$access_token){
            $url = str_replace(['{appid}','{secret}'], [$APPID, $AppSecret], self::$ACCESS_TOKEN_API);
            $curl = Curls::send($url);
            $data = $curl['data'] ? json_decode($curl['data'], true) : [];
            if($data['access_token']){
                $data['dateline'] = time();
                Cache($cacheKey,$data);
                $access_token = $data['access_token'];
            }
        }

        return $access_token;
    }

    /**
     * 获取JS-SDK签名
     * @return mixed|string
     */
    static function getJsapi_ticket(){
        global $C;
        $access_token = self::AccessToken();
        $ticketData = Cache('WX_TicketData');
        $ticketData = $ticketData ? json_decode($ticketData,true) : [];
        $ticket = $ticketData ? $ticketData['ticket'] : '';
        $outTime = intval($ticketData['expires_in']); //有效期
        $outTime = $outTime && $outTime < 7200 ? $outTime : 7200; //过期时间
        $dateline = intval($ticketData['dateline']); //缓存时间
        //如果token过期 则重新获取
        if(!$ticket || $dateline + $outTime < time()){
            $curl = Curls::send(str_replace('{token}', $access_token, self::$TICKET_API_JSAPI));
            $data = $curl['data'] ? json_decode($curl['data'], true) : [];
            if($data['ticket']){
                $data['dateline'] = time();
                $ticket = $data['ticket'];
                Cache('WX_TicketData',$data);
            }
        }

        return $ticket;
    }

    /**
     * 生成前端Sign签名
     * @param bool $ismd5 是否以MD5方式加密sign 默认=否
     * @return array
     */
    static function JsSign($ismd5=false){
        global $C;
        $jsapi_ticket = self::getJsapi_ticket();

        $url = $C['siteurl'].ltrim($_SERVER['REQUEST_URI'],'/');

        //$dt KEY顺序必须按照A-Z排列
        $dt = [
            'jsapi_ticket' => $jsapi_ticket,
            'noncestr' => rands(20),
            'timestamp' => time(),
            'url' => $url,
        ];
        $sign = [];
        foreach($dt as $key => $value){
            $sign []= $key.'='.$value;
        }
        $sign = implode('&',$sign);
        $dt['sign'] = $ismd5 ? md5($sign) : sha1($sign);
        unset($dt['jsapi_ticket']);
        $dt['appid'] = APP::setting('WECHAT/APPID');
        return $dt;
    }


    /**
     * 通过code获取用户资料
     * @param string $code
     * @return array
     */
    static function UserInfo($code='', $option=[], $APPID='', $AppSecret=''){
        global $C;
        $setting = APP::setting('WECHAT');
        $APPID = $setting['APPID'];
        $AppSecret = $setting['AppSecret'];
        if($option){
            if($option['AppID']){
                $APPID = $option['AppID'];
            }
            if($option['AppSecret']){
                $AppSecret = $option['AppSecret'];
            }
        }

        //拿用户access_token
        $curl = Curls::send(str_replace(['{appid}', '{secret}', '{code}'], [$APPID, $AppSecret, $code], self::$USER_CODE_TOKEN_API));

        $data = $curl['data'] ? json_decode($curl['data'], true) : [];
        $token = $data['access_token'] ? $data['access_token'] : '';
        $openid = $data['openid'] ? $data['openid'] : '';
        $dt = [];
        //根据token 拿用户资料
        if($token && $openid){
            $dt = Curls::send(str_replace(['{token}', '{openid}'], [$token, $openid], self::$USER_CODE_INFO_API));
            $dt = $dt['data'] ? json_decode($dt['data'], true) : [];
        }
        return $dt;
    }




    /**
     * 微信API下单函数
     * 即将废弃 2021年1月28日 00:54:04
     *
     * 参考资料：https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
     * @param string $userOpenid 用户openID
     * @param string $PayCode 系统内部订单编号
     * @param string $str
     */
    static function _Pay_create($userOpenid, $PayCode='', $total=0, $strbody='', $callbackUrl=''){
        global $C;
        $APIURL = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        //回调地址
        $callbackUrl = ($callbackUrl);
        $nonce_str = rands(10);
        $total = floatval($total);
        $total = $total > 0 ? floor($total * 100) : 0; //付款金额以分为单位的整数 向下舍掉分后面的值


        $returnData = [
            'success' => 0,
            'msg' => '创建支付中',
            'sign' => '',
            'PayUrl' => '', //支付地址
        ];

        if($PayCode && $total >0){
            $strbody = mb_substr($strbody,0,120); //body不能超过120个字符
            $post = [
                'body' => $strbody, //商品简单描述 100字内
                'total_fee' => $total,//支付金额
                'sign_type' => 'MD5',
                'nonce_str' => $nonce_str,
                'out_trade_no' => $PayCode,
                'spbill_create_ip' => $C['IP'],
                'notify_url' => $callbackUrl,
                'trade_type' => self::isWechat() ? 'JSAPI' : 'MWEB', //自动判断 MWEB=H5支付 JSAPI=JSAPI支付  NATIVE=Native支付 APP=APP支付
            ];

            if($userOpenid){
                $post['openid'] = $userOpenid;
            }
            $post = self::pay_sign($post);

            $returnData['sign'] = $post['sign'];
            $post = $post ? self::pay_Arr2Xml($post) : '';
            $data = Curls::send($APIURL, $post);

            $data = $data['data'];
            if($data){
                $data = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
                $data = json_decode(json_encode($data),true);
                if(strtolower($data['return_code']) == 'success' && $data['appid'] && $data['mch_id'] && ($data['mweb_url'] || $data['prepay_id']) && $data['sign']){
                    $returnData = array_merge($data,$returnData);
                    $returnData['success'] = 1;
                    $returnData['PayUrl'] = $data['mweb_url'];

                    //以下字段在return_code 和result_code都为SUCCESS的时候有返回
                    //交易类型 SAPI=JSAPI支付 NATIVE=Native支付 APP=APP支付
                    $returnData['trade_type'] = $data['trade_type']; //JSAPI

                    //预支付交易会话标识 微信生成的预支付会话标识，用于后续接口调用中使用，该值有效期为2小时
                    $returnData['prepay_id'] = $data['prepay_id']; //wx201410272009395522657a690389285100

                    //trade_type=NATIVE时有返回，此url用于生成支付二维码，然后提供给用户进行扫码支付。
                    $returnData['code_url'] = $data['code_url']; //weixin://wxpay/bizpayurl/up?pr=NwY5Mz9&groupid=00

                }
                if($data['err_code_des']){
                    $returnData['msg'] = $data['err_code_des'];
                }elseif($data['return_msg']){
                    $returnData['msg'] = $data['return_msg'];
                }
            }
        }
        $returnData['msg'] = stripTags($returnData['msg'], 30);
        return $returnData;
    }




    /**
     * 微信xml数据组成
     * @param array $arr 需要组合的数组
     * @return string
     */
    static function pay_Arr2Xml($arr){
        $xml = "<xml>";
        foreach ($arr as $key=>$val){
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }


    /*判断是否是微信浏览器*/
    static function isWechat() {
        $wechat = false;
        $useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
        if(strpos($useragent,'android') !== false || strpos($useragent,'iphone') !== false){
            if(strpos($useragent, 'micromessenger') !== false){
                $wechat = true;
            }
        }
        return $wechat;
    }

    static function Service_MessageSend($APPID='', $AppSecret='', $sendData=[]){
        $token = self::AccessToken($APPID, $AppSecret);
        $send = Curls::send(self::$SERVICE_MessageSend_API.$token, jsonEn($sendData));
        $send['data'] = $send['data'] ? json_decode($send['data'], 1) : [];
        if($send['data']['errcode'] == 0 && $send['data']['errmsg'] =='ok'){
            return true;
        }
        return false;
    }

}