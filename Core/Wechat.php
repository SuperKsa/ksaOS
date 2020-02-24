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

    /**
     * 获取微信基础 access_token
     */
    static function AccessToken(){
        global $C;

        $APPID = $C['setting']['wechat_APPID'];
        $AppSecret = $C['setting']['wechat_AppSecret'];

        $wechat_accessTokenData = json_decode($C['setting']['wechat_accessTokenData'],true);
        $access_token = $wechat_accessTokenData['access_token'];
        $access_token_time = intval($wechat_accessTokenData['dateline']); //token缓存时间
        $outTime = intval($wechat_accessTokenData['expires_in']); //token有效期
        $outTime = $outTime && $outTime < 7200 ? $outTime : 7200; //token过期时间

        if(!$APPID || !$AppSecret){
            return ;
        }
        //如果token未过期
        if($access_token_time + $outTime < TIME || !$access_token){
            $curl = curl('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$APPID.'&secret='.$AppSecret);
            $data = $curl['data'] ? json_decode($curl['data'], true) : [];
            if($data['access_token']){
                $data['dateline'] = TIME;
                $C['setting']['wechat_accessTokenData'] = $data;
                $access_token = $data['access_token'];
                DB('setting')->update(['wechat_accessTokenData'=>json_encode($data)]);
            }
        }
        return $access_token;
    }

    /**
     * 获取JS-SDK签名
     * @return mixed|string
     */
    static function wechat_getJsapi_ticket(){
        global $C;
        $access_token = self::AccessToken();
        $ticketData = json_decode($C['setting']['wechat_ticketData'],true);
        $ticket = $ticketData ? $ticketData['ticket'] : '';
        $outTime = intval($ticketData['expires_in']); //有效期
        $outTime = $outTime && $outTime < 7200 ? $outTime : 7200; //过期时间
        $dateline = intval($ticketData['dateline']); //缓存时间
        //如果token未过期
        if(!$ticket || $dateline + $outTime < TIME){
            //拿用户access_token
            $curl = curl('https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi');
            $data = $curl['data'] ? json_decode($curl['data'], true) : [];
            if($data['ticket']){
                $data['dateline'] = TIME;
                $C['setting']['wechat_ticketData'] = $data;
                $ticket = $data['ticket'];
                DB('setting')->update(['wechat_ticketData'=>json_encode($data)]);
            }
        }

        return $ticket;
    }

    /**
     * 生成前端Sign签名
     * @param bool $ismd5 是否以MD5方式加密sign 默认=否
     * @return array
     */
    function wechat_JsSign($ismd5=false){
        global $C;
        $jsapi_ticket = wechat_getJsapi_ticket();

        $url = $C['siteurl'].ltrim($_SERVER['REQUEST_URI'],'/');

        //$dt KEY顺序必须按照A-Z排列
        $dt = [
            'jsapi_ticket' => $jsapi_ticket,
            'noncestr' => crand(20),
            'timestamp' => TIME,
            'url' => $url,
        ];
        $sign = [];
        foreach($dt as $key => $value){
            $sign []= $key.'='.$value;
        }
        $sign = implode('&',$sign);
        $dt['sign'] = $ismd5 ? md5($sign) : sha1($sign);
        unset($dt['jsapi_ticket']);
        $dt['appid'] = $C['setting']['wechat_APPID'];
        return $dt;
    }

    /**
     * 获取用户资料
     * @param string $code
     * @return array
     */
    static function UserInfo($code=''){
        global $C;
        $access_token = self::AccessToken();

        $APPID = $C['setting']['wechat_APPID'];
        $AppSecret = $C['setting']['wechat_AppSecret'];
        //拿用户access_token
        $curl = curl('https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$APPID.'&secret='.$AppSecret.'&code='.$code.'&grant_type=authorization_code');
        $data = $curl['data'] ? json_decode($curl['data'], true) : [];
        $token = $data['access_token'] ? $data['access_token'] : '';
        $openid = $data['openid'] ? $data['openid'] : '';
        $dt = [];
        //根据token 拿用户资料
        if($token && $openid){
            $dt = curl('https://api.weixin.qq.com/sns/userinfo?access_token='.$token.'&openid='.$openid.'&lang=zh_CN');
            $dt = $dt['data'] ? json_decode($dt['data'], true) : [];
        }
        return $dt;
    }
    /**
     * 微信API下单函数
     * 参考资料：https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
     * @param string $userOpenid 用户openID
     * @param string $orderCode 系统内部订单编号
     * @param string $str
     */
    function wechat_pay_api_order($userOpenid,$orderCode='',$total=0,$strbody='',$callbackUrl=''){
        global $C;
        $APIURL = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        //回调地址
        $callbackUrl = ($callbackUrl);
        $nonce_str = crand(10);
        $total = floatval($total);
        $total = $total > 0 ? $total * 100 : 0;


        $returnData = [
            'success' => 0,
            'msg' => '创建支付中',
            'sign' => '',
            'PayUrl' => '', //支付地址
        ];

        if((!WECHAT || WECHAT && $userOpenid) && $orderCode && $total >0){

            $post = [
                'body' => $strbody, //商品简单描述 100字内
                'total_fee' => $total,//支付金额
                'sign_type' => 'MD5',
                'nonce_str' => $nonce_str,
                'out_trade_no' => $orderCode,
                'spbill_create_ip' => $C['ip'],
                'notify_url' => $callbackUrl,
                'trade_type' => WECHAT ? 'JSAPI' : 'MWEB', //自动判断 MWEB=H5支付 JSAPI=JSAPI支付  NATIVE=Native支付 APP=APP支付
            ];
            if(WECHAT){
                $post['openid'] = $userOpenid;
            }

            $post = wechat_pay_sign($post);
            $returnData['sign'] = $post['sign'];
            $post = $post ? pay_Arr2Xml$post) : '';
            $data = curl($APIURL,$post);
            $data = $data['data'];
            if($data){
                $data = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
                $data = json_decode(json_encode($data),true);
                if(strtolower($data['return_code']) == 'success' && $data['appid'] && $data['mch_id'] && ($data['mweb_url'] || $data['prepay_id']) && $data['sign']){
                    $returnData = array_merge($data,$returnData);
                    $returnData['success'] = 1;
                    $returnData['PayUrl'] = $data['mweb_url'];
                }
                if($data['err_code_des']){
                    $returnData['msg'] = $data['err_code_des'];
                }elseif($data['return_msg']){
                    $returnData['msg'] = $data['return_msg'];
                }
            }
        }
        $returnData['msg'] = Common_replace_html($returnData['msg'], 30);
        return $returnData;
    }

    /**
     * 订单状态查询接口
     * 参考资料：https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_2
     * @param string $ordercode 自己系统订单编号
     */
    function wechat_pay_api_order_query($orderCode=''){
        global $C;
        $APIURL = 'https://api.mch.weixin.qq.com/pay/orderquery';
        $nonce_str = crand(10);
        $post = [
            'sign_type' => 'MD5',
            'nonce_str' => $nonce_str,
            'out_trade_no' => $orderCode,
        ];

        $post = wechat_pay_sign($post);

        $post = $post ? pay_Arr2Xml$post) : '';
        $data = curl($APIURL,$post);
        $data = $data['data'];
        $returnData = [
            'success' => 0,
            'msg' => '创建查询',
            'sign' => $post['sign'],
            'total' => 0, //查询的支付金额
            'PayStatus' => 0, //订单付款状态 0=等待付款 1=付款成功
        ];
        if($data){
            $data = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
            $data = json_decode(json_encode($data),true);
            if(strtolower($data['trade_state']) == 'success' && $data['mch_id'] == $C['setting']['wechat_PayID'] && $data['out_trade_no'] == $orderCode){
                $returnData = $data;
                $returnData['success'] = 1;
                $returnData['PayStatus'] = 1; //付款成功
                $returnData['orderCode'] = $orderCode; //成功的同时必须返回传入的$orderCode
                $returnData['total'] = floatval($data['total_fee']) * 100; //微信的金额为分 需要x100 =元
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
    function wechat_pay_sign($post=[]){
        global $C;
        if($post){
            $post['appid'] = $C['setting']['wechat_APPID'];
            $post['mch_id'] = $C['setting']['wechat_PayID'];
            $post['sign'] = wechat_sign($post, true);
        }

        return $post;
    }

    /**
     * 微信Sign生成
     * @param array $signArr 需要生成sign的数组
     * @param bool $isPay 生成商家支付Sign
     * @return array|bool|string
     */
    function sign($signArr=[], $isPay=false){
        global $C;
        $sign = false;
        if($signArr){
            ksort($signArr);
            if($isPay){
                $signArr['key'] = Common_replace_html($C['setting']['wechat_PayKEY'],100);
            }
            $sign = [];
            foreach($signArr as $key => $value){
                if($value !== ''){
                    $sign[] = $key.'='. $value;
                }
            }
            $sign = md5(implode('&',$sign));
            $sign = strtoupper($sign);
        }
        return $sign;
    }

    /**
     * 微信xml数据组成
     * @param type $arr 需要组合的数组
     * @return string
     */
    function pay_Arr2Xml($arr){
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
            file_put_contents(ROOT.'./data/wechatpay.txt', cjson_encode($data));

            //微信主动POST数据检查 开发者ID 商户ID 必须对应 并且有返回支付订单号
            if($data['appid'] == $C['setting']['wechat_APPID'] && $data['mch_id'] == $C['setting']['wechat_PayID'] && $data['out_trade_no']){
                $orderData = DB('user_payorders')->orderCode($data['out_trade_no']);
                if($orderData && $orderData['Status'] ==0 && $orderData['PayID']){
                    DB('user_payorders')->update(['PayCallback'=> cjson_encode($data)],['PayID'=>$orderData['PayID']]);
                    loadFunction('pay');
                    Pay_query($orderData['PayID']);
                }
            }


            return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }
    }
}