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
    //jsapi支付接口地址
    private static $WECHAT_API_JSAPI = '/v3/pay/transactions/jsapi';
    //微信支付查询订单接口地址
    private static $WECHAT_API_QUERY= '/v3/pay/transactions/id/';

    /**
     * 获取微信基础 access_token
     * 缓存有效期 7200秒
     */
    static function AccessToken($option=[]){
        global $C;

        $APPID = $C['setting']['WX_APPID'];
        $AppSecret = $C['setting']['WX_AppSecret'];
        if($option){
            if($option['AppID']){
                $APPID = $option['AppID'];
            }
            if($option['AppSecret']){
                $AppSecret = $option['AppSecret'];
            }
        }
        $accessToken = Cache('WX_ACCESSTOKEN');
        $accessToken = $accessToken ? json_decode($accessToken,true) : [];

        $outTime = intval($accessToken['expires_in']); //token有效期
        $outTime = $outTime && $outTime < 7200 ? $outTime : 7200; //token过期时间
        $access_token_time = intval($accessToken['dateline']); //token缓存时间
        $access_token = $accessToken['access_token'];

        if(!$APPID || !$AppSecret){
            return ;
        }
        //如果token未过期
        if($access_token_time + $outTime < time() || !$access_token){
            $curl = Curls::send('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$APPID.'&secret='.$AppSecret);
            $data = $curl['data'] ? json_decode($curl['data'], true) : [];
            if($data['access_token']){
                $data['dateline'] = time();
                Cache('WX_ACCESSTOKEN',$data);
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
            $curl = Curls::send('https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi');
            $data = $curl['data'] ? json_decode($curl['data'], true) : [];
            if($data['ticket']){
                $data['dateline'] = time();
                $C['setting']['wechat_ticketData'] = $data;
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
        $dt['appid'] = $C['setting']['WX_APPID'];
        return $dt;
    }

    /**
     * 小程序 获取用户openid
     * @param string $code
     * @param array $option
     * @return array|mixed
     */
    static function UserInfoMini($code='', $option=[]){
        global $C;

        $APPID = $C['setting']['WX_APPID'];
        $AppSecret = $C['setting']['WX_AppSecret'];
        if($option){
            if($option['AppID']){
                $APPID = $option['AppID'];
            }
            if($option['AppSecret']){
                $AppSecret = $option['AppSecret'];
            }
        }
        $sendAPI = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$APPID.'&secret='.$AppSecret.'&js_code='.$code.'&grant_type=authorization_code';

        //拿用户access_token
        $curl = Curls::send($sendAPI);

        $data = $curl['data'] ? json_decode($curl['data'], true) : [];
        return $data;
    }

    /**
     * 获取用户资料
     * @param string $code
     * @return array
     */
    static function UserInfo($code='', $option=[]){
        global $C;
        $access_token = self::AccessToken($option);

        $APPID = $C['setting']['WX_APPID'];
        $AppSecret = $C['setting']['WX_AppSecret'];
        if($option){
            if($option['AppID']){
                $APPID = $option['AppID'];
            }
            if($option['AppSecret']){
                $AppSecret = $option['AppSecret'];
            }
        }
        $sendAPI = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$APPID.'&secret='.$AppSecret.'&code='.$code.'&grant_type=authorization_code';

        //拿用户access_token
        $curl = Curls::send($sendAPI);

        $data = $curl['data'] ? json_decode($curl['data'], true) : [];
        $token = $data['access_token'] ? $data['access_token'] : '';
        $openid = $data['openid'] ? $data['openid'] : '';
        $dt = [];
        //根据token 拿用户资料
        if($token && $openid){
            $dt = Curls::send('https://api.weixin.qq.com/sns/userinfo?access_token='.$token.'&openid='.$openid.'&lang=zh_CN');
            $dt = $dt['data'] ? json_decode($dt['data'], true) : [];
        }
        return $dt;
    }

    /**
     * 微信支付 JSAPI下单函数
     * @param array $option
     * @return array
     */
    static function Pay_create_jsapi($option=[]){
        global $C;
        $option['amount']['total'] = floor($option['amount']['total']);
        $post = [
            'appid' => $C['setting']['WX_APPID'],
            'mchid' => ''.$C['setting']['WX_PayID'].'',
            'scene_info' => [
                'payer_client_ip' => $C['IP'],
            ],
        ];
        $post = array_merges($post, $option);
        $post = json_encode($post);

        $api = self::$WECHAT_API_JSAPI;
        $sign = self::authorizationV3('POST', $api, $post);
        $send = Curls::send(self::$WECHAT_API.$api, $post, [
            'User-Agent' => $C['useragent'],
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
     * 微信支付 V3授权生成
     * @param string $method 请求方式 POST GET PUT
     * @param string $api 请求API地址
     * @param null $post 请求body数据
     * @return array
     */
    static function authorizationV3($method='', $api ='', $post=NULL){
        global $C;
        $time = time();
        $nonce_str = rands(10);
        $sign = self::siginV3([
            strtoupper($method),
            $api,
            $time,
            $nonce_str,
            $post,
        ]);
        return [
            'sign' => $sign,
            'Authorization' => 'WECHATPAY2-SHA256-RSA2048 '.sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"', $C['setting']['WX_PayID'], $nonce_str, $time, '372657B6135AFB32446D9B6A54128A5693BF91E8', $sign)
        ];
    }

    /**
     * 微信支付 V3签名生成
     * @param array $signData
     * @return string
     */
    static function siginV3($signData =[]){
        global $C;
        $message = '';
        foreach($signData as $value){
            $message .= $value."\n";
        }

        $signature = $C['setting']['WX_PayCERTIFICATE'];
        $privateKey = $C['setting']['WX_PayPRIVATEKEY'];
        openssl_sign($message, $signature, $privateKey, 'sha256WithRSAEncryption');
        return base64_encode($signature);

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
     * 订单状态查询接口
     * @param string $PayCode 支付订单号
     * @param string $transaction_id 微信支付系统生成的订单号
     * @return array|mixed
     */
    function pay_query($PayCode='', $transaction_id =''){
        global $C;

        $api = self::$WECHAT_API_QUERY.$transaction_id.'?mchid='.$C['setting']['WX_PayID'];
        $sign = self::authorizationV3('GET', $api);
        $send = Curls::send(self::$WECHAT_API.$api, '', [
            'User-Agent' => $C['useragent'],
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
            if(strtolower($data['trade_state']) == 'success' && $data['mch_id'] == $C['setting']['WX_PayID'] && $data['appid'] == $C['setting']['WX_APPID'] && $data['out_trade_no'] == $PayCode){
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
     * 支付参数签名并输出签名后的POST XML数据
     * 不需要传入商户KEY、商户ID、公众号APPID
     * @param array $post
     * @return array
     */
    static function pay_sign($post=[]){
        global $C;
        if($post){
            $post['appid'] = $C['setting']['WX_APPID'];
            $post['mch_id'] = $C['setting']['WX_PayID'];
            $post['sign'] = self::sign($post, true);
        }
        return $post;
    }

    /**
     * 微信Sign生成
     * @param array $signArr 需要生成sign的数组
     * @param bool $isPay 生成商家支付Sign
     * @return array|bool|string
     */
    static function sign($signArr=[], $isPay=false){
        global $C;
        $sign = false;
        if($signArr){
            ksort($signArr);
            if($isPay){
                $signArr['key'] = stripTags($C['setting']['WX_PayKEY'],32);
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


    function PayStatusReturn(){
        $data = file_get_contents('php://input');
        if($data){
            $data = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
            $data = json_decode(json_encode($data),true);
            file_put_contents(ROOT.'./data/wechatpay.txt', json_encode($data));

            //微信主动POST数据检查 开发者ID 商户ID 必须对应 并且有返回支付订单号
            if($data['appid'] == $C['setting']['WX_APPID'] && $data['mch_id'] == $C['setting']['WX_PayID'] && $data['out_trade_no']){
                $orderData = DB('user_payorders')->orderCode($data['out_trade_no']);
                if($orderData && $orderData['Status'] ==0 && $orderData['PayID']){
                    DB('user_payorders')->where('PayID', $orderData['PayID'])->update(['PayCallback'=> json_encode($data)]);
                    Wechat::Pay_query($orderData['PayID']);
                }
            }


            return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }
    }

    /*判断是否是手机*/
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

    /**
     * Decrypt AEAD_AES_256_GCM ciphertext
     *
     * @param string    $associatedData     AES GCM additional authentication data
     * @param string    $nonceStr           AES GCM nonce
     * @param string    $ciphertext         AES GCM cipher text
     *
     * @return string|bool      Decrypted string on success or FALSE on failure
     */
    static function decryptToString($associatedData, $nonceStr, $ciphertext) {
        global $C;
        $ciphertext = \base64_decode($ciphertext);
        $AUTH_TAG_LENGTH_BYTE = 16;
        if (strlen($ciphertext) <= $AUTH_TAG_LENGTH_BYTE) {
            return false;
        }
        $aesKey = $C['setting']['WX_PayKEY'];
        // ext-sodium (default installed on >= PHP 7.2)
        if (function_exists('\sodium_crypto_aead_aes256gcm_is_available') &&
            \sodium_crypto_aead_aes256gcm_is_available()) {
            return \sodium_crypto_aead_aes256gcm_decrypt($ciphertext, $associatedData, $nonceStr, $aesKey);
        }

        // ext-libsodium (need install libsodium-php 1.x via pecl)
        if (function_exists('\Sodium\crypto_aead_aes256gcm_is_available') &&
            \Sodium\crypto_aead_aes256gcm_is_available()) {
            return \Sodium\crypto_aead_aes256gcm_decrypt($ciphertext, $associatedData, $nonceStr, $aesKey);
        }

        // openssl (PHP >= 7.1 support AEAD)
        if (PHP_VERSION_ID >= 70100 && in_array('aes-256-gcm', \openssl_get_cipher_methods())) {
            $ctext = substr($ciphertext, 0, -$AUTH_TAG_LENGTH_BYTE);
            $authTag = substr($ciphertext, -$AUTH_TAG_LENGTH_BYTE);

            return \openssl_decrypt($ctext, 'aes-256-gcm', $aesKey, \OPENSSL_RAW_DATA, $nonceStr,
                $authTag, $associatedData);
        }

        throw new \RuntimeException('AEAD_AES_256_GCM需要PHP 7.1以上或者安装libsodium-php');
    }
}