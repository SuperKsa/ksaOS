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

use http\Url;
use function Sodium\crypto_aead_aes256gcm_decrypt;

if(!defined('KSAOS')) {
    exit('Error.');
}


class WechatPay {

    const _name = '微信支付处理类';

    private static  $WECHAT_API = 'https://api.mch.weixin.qq.com';
    //jsapi支付接口地址
    private static $WECHAT_API_JSAPI = '/v3/pay/transactions/jsapi';
    //APP支付接口地址
    private static $WECHAT_API_APP = '/v3/pay/transactions/app';

    //微信支付查询订单接口地址
    private static $WECHAT_API_QUERY= '/v3/pay/transactions/id/';
    //微信支付退款订单接口地址
    private static $WECHAT_API_REFUNDS= '/v3/refund/domestic/refunds';

    private static $CERTIFICATES_API = '/v3/certificates';


    /**
     * 微信支付 JSAPI下单函数
     * @param array $option
     * @return array
     */
    static function create_jsapi($APPID, $MCHID, $option=[]){
        $option['amount']['total'] = floor($option['amount']['total']);
        $post = [
            'appid' => $APPID,
            'mchid' => (string)$MCHID,
            'scene_info' => [
                'payer_client_ip' => Rest::ip(),
            ],
        ];
        $post = array_merges($post, $option);
        $post = json_encode($post);
        $paysetting = APP::setting('WECHAT_APP_PAY');
        $api = self::$WECHAT_API_JSAPI;
        $sign = self::authorizationV3('POST', $api, $post, $paysetting['MCHID'], $paysetting['SERIALNO']);
        $send = Curls::send(self::$WECHAT_API.$api, $post, [
            'User-Agent' => USERAGENT,
            'Content-Type' => 'application/json',
            'Authorization' => $sign['Authorization']
        ]);
        if($send && $send['data']){
            $send['data'] = json_decode($send['data'], true);
        }

        $return = [
            'success' => 0,
            'errorcode' => '',
            'msg'  => '',
            'createData' => [ //建单数据
                'option' => $option,
                'sign' => [
                    'time' => $sign['time'],
                    'nonce_str' => $sign['nonce_str'],
                    'sign' => $sign['sign'],
                ],
                'prepay_id' => ''
            ]
        ];
        if($send['httpcode'] == 200 && $send['data']['prepay_id']){
            $return['success'] = 1;
            $return['createData']['prepay_id'] = $send['data']['prepay_id'];

        }else if($send['httpcode'] > 0){
            $return['errorcode'] = $send['data']['code'];
            $return['msg'] = $send['data']['message'];
        }
        return $return;
    }


    /**
     * 微信支付V3接口通信函数
     * 自动生成授权
     * @param string $api API相对地址
     * @param array $post post数据
     * @return array
     */
    static function PAY_V3_SEND($api='', $post=[]){
        $post = $post ? json_encode($post) : '';
        $sign = self::authorizationV3($post ? 'POST' : 'GET', $api, $post);
        $send = Curls::send(self::$WECHAT_API.$api, $post, [
            'User-Agent' => Rest::useragent(),
            'Content-Type' => 'application/json',
            'Authorization' => $sign['Authorization']
        ]);
        if($send && $send['data']){
            $send['data'] = json_decode($send['data'], true);
        }
        return $send;
    }

    /**
     * 微信支付 退款函数
     * 直接面对平台接口 没有任何逻辑
     * @param array  $option 按照微信接口传递
     */
    static function refunds_jsapi($option){

        $send = self::PAY_V3_SEND(self::$WECHAT_API_REFUNDS, $option);

        $return = [
            'success' => 0,
            'errorcode' => '',
            'msg'  => '',
            'data' => [ //建单数据
                'option' => $option,
                'callback' => []
            ]
        ];
        if($send['httpcode'] == 200 && $send['data']['refund_id']){
            $return['success'] = 1;
            $return['data']['callback'] = $send['data'];

        }else if($send['httpcode'] > 0){
            $return['errorcode'] = $send['data']['code'];
            $return['msg'] = $send['data']['message'];
        }
        return $return;
    }

    static function query_jsapi($option){

        $send = self::PAY_V3_SEND(self::$WECHAT_API_QUERY.$option['transaction_id'].'?mchid='.$option['mchid']);

        $return = [
            'success' => 0,
            'errorcode' => '',
            'msg'  => '',
            'data' => [ //建单数据
                'option' => $option,
                'callback' => []
            ]
        ];
        if($send['httpcode'] == 200 && $send['data']['appid']){
            $return['success'] = 1;
            $return['data']['callback'] = $send['data'];

        }else if($send['httpcode'] > 0){
            $return['errorcode'] = $send['data']['code'];
            $return['msg'] = $send['data']['message'];
        }
        return $return;
    }


    /**
     * 微信支付 V3授权生成
     * @param $method string 请求方式 POST GET PUT
     * @param $api Url 请求API地址
     * @param $post string 请求body数据
     * @param $MCHID string 商户ID
     * @param $SERIALNO string 证书序列号
     * @param $certificate string 证书公钥内容内容
     * @param $privateKey string 证书私钥内容
     *
     * @return array
     */
    static function authorizationV3($method='', $api ='', $post=NULL, $MCHID = '', $SERIALNO='', $certificate='', $privateKey=''){
        $paysetting = APP::setting('WECHAT_APP_PAY');
        $MCHID = $MCHID ? $MCHID : $paysetting['MCHID'];
        $SERIALNO = $SERIALNO ? $SERIALNO : $paysetting['SERIALNO'];
        $certificate = $certificate ? $certificate : $paysetting['CERTIFICATE'];
        $privateKey = $privateKey ? $privateKey : $paysetting['PRIVATEKEY'];
        
        
        $time = time();
        $nonce_str = rands(10);
        $sign = self::siginV3([
            strtoupper($method),
            $api,
            $time,
            $nonce_str,
            $post,
        ], $certificate, $privateKey);
        return [
            'method' => $method,
            'api' => $api,
            'post' => $post,
            'sign' => $sign,
            'nonce_str' => $nonce_str,
            'time' => $time,
            'Authorization' => 'WECHATPAY2-SHA256-RSA2048 '.sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"', $MCHID, $nonce_str, $time, $SERIALNO, $sign)
        ];
    }

    /**
     * 微信支付 V3签名生成
     * @param array $signData 签名内容数组
     * @param string $certificate 证书内容 可选
     * @param string $privateKey 证书私钥 可选
     * @return string
     */
    static function siginV3($signData =[], $certificate='', $privateKey=''){
        $message = '';
        foreach($signData as $value){
            $message .= $value."\n";
        }

        $sigin = '';
        if($privateKey && $certificate){
            openssl_sign($message, $certificate, $privateKey, 'sha256WithRSAEncryption');
            $sigin = base64_encode($certificate);
        }
        return $sigin;
    }


    /**
     * 支付结果回调的签名验证函数
     * @param string $serial 证书序列号 HTTP Wechatpay_Serial
     * @param string $sign 回调签名 HTTP Wechatpay_Signature
     * @param int $timestamp 回调时间戳 HTTP Wechatpay_Timestamp
     * @param string $nonceStr 回调随机串 HTTP Wechatpay_Nonce
     * @param string $post 回调请求的post原文 POST
     * @return bool
     */
    static function callbackSignVerify($serial='', $sign='', $timestamp=0, $nonceStr='', $post=''){
        $certificates = '';
        foreach(WechatPay::certificates() as $value){
            if($value['serial_no'] == $serial){
                $certificates = $value['encrypt_certificate']['ciphertext'];
            }
        }
        $checkResult = false;
        $Wechatpay_Signature = base64_decode($sign);
        if($certificates && $Wechatpay_Signature){
            $pubkeyid = openssl_get_publickey($certificates);
            $verifyContent = $timestamp."\n".$nonceStr."\n".$post."\n";
            $checkResult = (bool)openssl_verify($verifyContent, $Wechatpay_Signature, $pubkeyid, OPENSSL_ALGO_SHA256);
            openssl_free_key($pubkeyid);
        }
        return $checkResult;
    }

    /**
     * 获得证书列表
     * 60分钟自动抓取一次
     */
    static function certificates(){
        $paysetting = APP::setting('WECHAT_APP_PAY');
        $cacheKey = 'WechatPaycertificates_'.$paysetting['MCHID'];
        if(!($data = Cache($cacheKey))){
            $Authorization = self::authorizationV3('GET', self::$CERTIFICATES_API,'', $paysetting['MCHID'], $paysetting['SERIALNO']);

            $data = Curls::send(self::$WECHAT_API.self::$CERTIFICATES_API, '',  [
                'Authorization' => $Authorization['Authorization'],
                'Accept' => 'application/json',
                'User-Agent' => Rest::useragent()
            ]);
            $data = $data['data'] ? json_decode($data['data'], true) : [];
            $data = $data['data'];
            $data && Cache($cacheKey, $data, 3600);
        }
        $wechatPaySetting = APP::setting('WECHAT_APP_PAY');
        //自动解密证书
        foreach($data as $key => $value){
            $value['encrypt_certificate']['ciphertext'] = Aes256::decode($value['encrypt_certificate']['ciphertext'], $value['encrypt_certificate']['associated_data'], $value['encrypt_certificate']['nonce'], $wechatPaySetting['APIKEY']);
            $data[$key] = $value;
        }
        return $data;
    }


    /**
     * APP下单
     * @param $appid
     * @param $mchid
     * @param $total
     * @param $desc
     * @param $out_trade_no
     * @param $notify_url
     * @param $option
     *
     * @return array
     */
    static function Create_APP_Orders($appid='', $mchid='', $total=0, $desc='', $out_trade_no='', $notify_url='', $option=[]){

        $payData = [
            'appid' => $appid,
            'mchid' => $mchid,
            'description' => $desc,
            'out_trade_no' => $out_trade_no,
            'amount' => [
                'total' => $total,
                'currency' => 'CNY'
            ],
            'notify_url' => $notify_url
        ];


        $post = array_merges($payData, $option);
        $post = json_encode($post);
        $paysetting = APP::setting('WECHAT_APP_PAY');
        $api = self::$WECHAT_API_APP;
        $sign = self::authorizationV3('POST', $api, $post, $paysetting['MCHID'], $paysetting['SERIALNO'], $paysetting['CERTIFICATE'], $paysetting['PRIVATEKEY']);
        $send = Curls::send(self::$WECHAT_API.$api, $post, [
            'User-Agent' => USERAGENT,
            'Content-Type' => 'application/json',
            'Authorization' => $sign['Authorization']
        ]);
        if($send && $send['data']){
            $send['data'] = json_decode($send['data'], true);
        }

        $return = [
            'success' => 0,
            'errorcode' => '',
            'msg'  => '',
            'createData' => [ //建单数据
              'option' => $option,
              'sign' => $sign,
              'prepay_id' => ''
            ]
        ];
        if($send['httpcode'] == 200 && $send['data']['prepay_id']){
            $return['success'] = 1;
            $return['createData']['prepay_id'] = $send['data']['prepay_id'];

        }else if($send['httpcode'] > 0){
            $return['errorcode'] = $send['data']['code'];
            $return['msg'] = $send['data']['message'];
        }
        return $return;
    }


    /**
     * 微信支付API 统一下单函数
     *
     * 参考资料：https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
     *
     */
    static function UnifiedOrder($appid='', $mchid='', $total=0, $desc='', $out_trade_no='', $notify_url='', $option=[], $trade_type='APP'){
        $APIURL = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        //回调地址
        $nonce_str = rands(10);
        $time = time();
        $total = floatval($total);
        $total = $total > 0 ? $total : 0; //付款金额以分为单位的整数 向下舍掉分后面的值

        $returnData = [
            'success' => 0,
            'errorcode' => '',
            'msg'  => '',
            'createData' => [ //建单数据
              'option' => $option,
              'sign' => [
                  'nonce_str' => $nonce_str,
                  'time' => $time,
                  'sign' => '',
              ],
              'prepay_id' => ''
            ]
        ];

        if($appid && $mchid && $total){
            $post = [
                'appid' => $appid,
                'mch_id' => $mchid,
                'body' => mb_substr($desc,0,120), //商品简单描述 不能超过120个字符
                'total_fee' => $total,//支付金额
                'sign_type' => 'MD5',
                'nonce_str' => $nonce_str,
                'out_trade_no' => $out_trade_no,
                'spbill_create_ip' => Rest::ip(),
                'notify_url' => $notify_url,
                'trade_type' => $trade_type, //自动判断 MWEB=H5支付 JSAPI=JSAPI支付  NATIVE=Native支付 APP=APP支付
            ];
            $paysetting = APP::setting('WECHAT_APP_PAY');
            $post['sign'] = self::sign($post, $paysetting['APIKEY']);

            $returnData['createData']['sign']['sign'] = $post['sign'];
            $post = $post ? self::pay_Arr2Xml($post) : '';


            $data = Curls::send($APIURL, $post);

            $data = $data['data'];
            if($data){
                $data = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
                $data = json_decode(json_encode($data),true);

                if(strtolower($data['return_code']) == 'success' && $data['appid'] && $data['mch_id'] && $data['prepay_id'] && $data['sign']){
                    $returnData = array_merge($data,$returnData);
                    $returnData['success'] = 1;
                    //预支付交易会话标识 微信生成的预支付会话标识，用于后续接口调用中使用，该值有效期为2小时
                    $returnData['createData']['prepay_id'] = $data['prepay_id']; //wx201410272009395522657a690389285100

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
     * 订单状态查询接口
     * @param string $PayCode 支付订单号
     * @param string $transaction_id 微信支付系统生成的订单号
     * @return array|mixed
     */
    public static function query($PayCode='', $transaction_id =''){
        $paysetting = APP::setting('WECHAT_APP_PAY');
        $api = self::$WECHAT_API_QUERY.$transaction_id.'?mchid='.$paysetting['MCHID'];
        $sign = self::authorizationV3('GET', $api, '', $paysetting['MCHID'], $paysetting['SERIALNO']);
        $send = Curls::send(self::$WECHAT_API.$api, '', [
            'User-Agent' => Rest::useragent(),
            'Content-Type' => 'application/json',
            'Authorization' => $sign['Authorization']
        ]);
        $data = json_decode($send['data'], true);
        $returnData = [
            'success' => 0,
            'msg' => '创建查询',
            'sign' => $sign,
            'total' => 0, //查询的支付金额
            'PayStatus' => 0, //订单付款状态 0=等待付款 1=付款成功
        ];
        if($data){
            if(strtolower($data['trade_state']) == 'success' && $data['mch_id'] == $paysetting['MCHID'] && $data['out_trade_no'] == $PayCode){
                $returnData = $data;
                $returnData['success'] = 1;
                $returnData['PayStatus'] = 1; //付款成功
                $returnData['orderCode'] = $PayCode; //成功的同时必须返回传入的$PayCode
                $returnData['total'] = floatval($data['total_fee']) /100; //微信的金额为分 需要转为元
            }
            $returnData['msg'] = $data['trade_state_desc'];
        }
        return $returnData;
    }


    /**
     * 微信Sign生成
     * @param array $signArr 需要生成sign的数组
     * @param bool $isPay 生成商家支付Sign
     * @return array|bool|string
     */
    static function sign($signArr=[], $APIKEY=''){
        $sign = false;
        if($signArr){
            ksort($signArr);
            if($APIKEY){
                $signArr['key'] = stripTags($APIKEY,100);
            }
            $sign = [];
            foreach($signArr as $key => $value){
                $sign[] = $key.'='. $value;
            }
            $sign = md5(implode('&',$sign));
            $sign = strtoupper($sign);
        }
        return $sign;
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


    public static function PayStatusReturn(){
        $data = file_get_contents('php://input');
        if($data){
            $data = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
            $data = json_decode(json_encode($data),true);
            file_put_contents(ROOT.'./data/wechatpay.txt', json_encode($data));
            $settingPay = APP::setting('WECHAT_APP_PAY');
            //微信主动POST数据检查 开发者ID 商户ID 必须对应 并且有返回支付订单号
            if($data['mch_id'] == $settingPay['MCHID'] && $data['out_trade_no']){
                $orderData = DB('user_payorders')->orderCode($data['out_trade_no']);
                if($orderData && $orderData['Status'] ==0 && $orderData['PayID']){
                    DB('user_payorders')->where('PayID', $orderData['PayID'])->update(['PayCallback'=> json_encode($data)]);
                    self::query($orderData['PayID']);
                }
            }
            return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }
        return '';
    }

}