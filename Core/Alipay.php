<?php
/**
 * 支付宝业务处理类
 * -------------------------------
 * Author:  CR180 <cr180@cr180.com>
 * Date:    2020/2/24 23:19
 * Update:  2020/2/24 23:19
 *
 */

namespace ksaOS;

if(!defined('KSAOS')) {
    exit('Error.');
}

class Alipay{
    const _name = '支付宝业务处理类';

    /**
     * 支付宝订单交易状态查询接口
     * 参考资料：
    https://docs.open.alipay.com/api_1/alipay.trade.query/
     * @param type $orderCode
     */
    function pay_query($orderCode=''){
        $APIURL = 'https://openapi.alipay.com/gateway.do';
        $APPID = '2019052265341092';


        $returnData = [
            'success' => 0,
            'msg' => '创建查询',
            'sign' => '',
            'total' => 0, //查询的支付金额
            'PayStatus' => 0, //订单付款状态 0=等待付款 1=付款成功
        ];

        $body = [
            'out_trade_no' => $orderCode,  //订单支付时传入的商户订单号,和支付宝交易号不能同时为空。 trade_no,out_trade_no如果同时存在优先取trade_no
        ];
        $post = [
            'app_id' => $APPID, //	String	是	32	支付宝分配给开发者的应用ID	2014072300007148
            'method' => 'alipay.trade.query', //	String	是	128	接口名称	alipay.trade.wap.pay
            'format' => 'json', //	String	否	40	仅支持JSON	JSON
            'charset' => 'utf-8', //	String	是	10	请求使用的编码格式，如utf-8,gbk,gb2312等	utf-8
            'sign_type' => 'RSA2', //	String	是	10	商户生成签名字符串所使用的签名算法类型，目前支持RSA2和RSA，推荐使用RSA2	RSA2
            'timestamp' => times(TIME,'Y-m-d H:i:s'), //	String	是	19	发送请求的时间，格式"yyyy-MM-dd HH:mm:ss"	2014-07-24 03:07:50
            'version' => '1.0', //	String	是	3	调用的接口版本，固定为：1.0	1.0
            'biz_content' => cjson_encode($body), //	String	是	-	业务请求参数的集合，最大长度不限，除公共参数外所有请求参数都必须放在这个参数中传递，具体参照各产品快速接入文档
        ];
        $post = alipay_sign($post);
        $data = curl($APIURL,$post);
        $data = $data['data'] ? json_decode($data['data'],true) : [];

        $alipayData = $data['alipay_trade_query_response'] ? $data['alipay_trade_query_response'] : [];
        if($data['sign'] && $alipayData && $alipayData['code'] =='10000' && $alipayData['trade_status'] =='TRADE_SUCCESS'){
            $returnData = $alipayData;
            $returnData['success'] = 1;
            $returnData['PayStatus'] = 1; //付款成功
            $returnData['orderCode'] = $orderCode; //成功的同时必须返回传入的$orderCode
            $returnData['total'] = floatval($alipayData['total_amount']);
            $returnData['sign'] = $data['sign'];
        }
        $returnData['msg'] = $alipayData['msg'];

        return $returnData;
    }

    /**
     * 支付宝付款URL生成函数
     * 生成一条付款URL给前端跳转
     * 参考资料：
    https://docs.open.alipay.com/203/105285/
    https://docs.open.alipay.com/203/107090
     * @param type $userOpenid 用户openID
     * @param type $orderCode 系统内部订单编号
     * @param type $str
     */
    static function Pay_create($orderCode='',$total=0,$strbody='',$callbackUrl=''){
        global $C;
        $APIURL = 'https://openapi.alipay.com/gateway.do';

        $returnData = [
            'success' => 0,
            'msg' => '创建支付中',
            'sign' => '',
            'PayUrl' => '', //支付地址
        ];

        $APPID = $C['setting']['alipay_APPID'];
        if(!$APPID){
            $returnData['msg'] = '支付宝支付参数配置缺失';
            return $returnData;
        }

        $total = floatval($total);
        if($total <=0){
            $returnData['msg'] = '支付金额必须大于0';
            return $returnData;
        }
        if(!$orderCode){
            $returnData['msg'] = '订单号缺失';
            return $returnData;
        }

        //付款成功后返回支付结果确认页面
        $return_url = $C['siteurl'].'index.php?mod=pay&action=confirm&orderCode='.$orderCode;

        //回调地址
        $callbackUrl = $C['siteurl'].'pay_callback_alipay.php';


        $callbackUrl = $callbackUrl;
        $strbody = urlencode(mb_substr($strbody,0,128));
        $body = [
            'subject' => $strbody, //商品的标题/交易标题/订单标题/订单关键字等。
            'out_trade_no' => $orderCode,  //商户网站唯一订单号
            'timeout_express' => '2h', //该笔订单允许的最晚付款时间，逾期将关闭交易。取值范围：1m～15d。m-分钟，h-小时，d-天，1c-当天（1c-当天的情况下，无论交易何时创建，都在0点关闭）。 该参数数值不接受小数点， 如 1.5h，可转换为 90m。注：若为空，则默认为15d。
            'total_amount' => $total, //订单总金额，单位为元，精确到小数点后两位，取值范围[0.01,100000000]
            'product_code' => 'QUICK_WAP_WAY', //销售产品码，商家和支付宝签约的产品码。该产品请填写固定值：QUICK_WAP_WAY
            'goods_type' => '0', //商品主类型：0—虚拟类商品，1—实物类商品注：虚拟类商品不支持使用花呗渠道
            'passback_params' => urlencode('orderCode='.$orderCode), //公用回传参数，如果请求时传递了该参数，则返回给商户时会回传该参数。支付宝会在异步通知时将该参数原样返回。本参数必须进行UrlEncode之后才可以发送给支付宝
        ];
        $post = [
            'app_id' => $APPID, //	String	是	32	支付宝分配给开发者的应用ID	2014072300007148
            'method' => 'alipay.trade.wap.pay', //	String	是	128	接口名称	alipay.trade.wap.pay
            'format' => 'json', //	String	否	40	仅支持JSON	JSON
            'charset' => 'utf-8', //	String	是	10	请求使用的编码格式，如utf-8,gbk,gb2312等	utf-8
            'sign_type' => 'RSA2', //	String	是	10	商户生成签名字符串所使用的签名算法类型，目前支持RSA2和RSA，推荐使用RSA2	RSA2
            'timestamp' => times(TIME,'Y-m-d H:i:s'), //	String	是	19	发送请求的时间，格式"yyyy-MM-dd HH:mm:ss"	2014-07-24 03:07:50
            'version' => '1.0', //	String	是	3	调用的接口版本，固定为：1.0	1.0
            'notify_url' => $callbackUrl, //	String	否	256	支付宝服务器主动通知商户服务器里指定的页面http/https路径。	https://api.xx.com/receive_notify.htm
            'return_url' => $return_url, //支付宝支付后跳转的回调地址
            'biz_content' => cjson_encode($body), //	String	是	-	业务请求参数的集合，最大长度不限，除公共参数外所有请求参数都必须放在这个参数中传递，具体参照各产品快速接入文档
        ];
        $post = alipay_sign($post);
        $urls = [];
        foreach($post as $key => $value){
            $urls[] = $key.'='.urlencode($value);
        }
        $PayUrl = $APIURL.'?'.implode('&',$urls);

        $returnData = [
            'success' => 1,
            'msg' => '支付订单生成成功',
            'sign' => $post['sign'],
            'PayUrl' => $PayUrl, //支付地址
        ];
        return $returnData;
    }

    function alipay_sign($data){
        global $C;
        ksort($data);
        $url = [];
        foreach($data as $key => $value){
            $url[] = $key.'='.$value;
        }
        $url = implode('&',$url);
        //echo $url.'<br>';
        $sign=null;
        $PKEY = 'MIIEogIBAAKCAQEAsJLNQ0XNZLiApNeflHEeILVdLxtmMqerluJlWBA1taxa6sCL0lWsf0+qm7E461guYxouTO2KBnOFNAv9K610iW72B5LbvqmjvOWZM8jRAJnKuiIVsbOUlMoS6IPZP22TSJ64TDyqxoBYLrghUb/9pm0aRRCGAhnB6xu5AES5j9MMoABixbvT80L5GqwrB+Jy8Uc0bfPj6+kD7BBj1TsWoD8zKHHTLWDUUlk4hd1XaYMOUDx2tNRUOwwVO8zbaGoJO+kWdj250cIi9lmBiJbxBLe+aEHHIfbmXb/RNqXIwKxsGaTdwXgWKI/RflvlC0FeIa5/gdWic2a+yxQ7WBIgRwIDAQABAoIBAF4u37fraqQ32b6TmPYn5CcUNlEjLz8Dun3v4pi3hL6T4abn72zQ8AK0bs8/F5pI3e1UkK/N4FqSkdFyN6dtjQSloCvoBLhNah4e5bn+eqT0Y3MnLSUtIaq/ophNg7rWasJIjwtzLLBW0zKQWo1teBGmjmWaa7CBJrOOhP6EqenxiS8C75LnS+9kcxTd6ZmJTHY/Jk9uTJfffzN3xsyUrl+jKTsDrY2yZh2AtgqGOY2nmNSwyg4e4EeyY0LeP5V53jPw7GtzembTB9oOSY5TfO29DTooRG2iTRRtpI74nHxXGDa/lzGbdpVhBPXmKdO+E02eWYIWFdxK63J2V04giQECgYEA1o8fcpvMiL0dpbTVJ43XMn+mP60Ayh34jSI2u4NYbjbOxw8mhKwxNrlexoqhaFzNfunfkjR3FY/WrOJyLf8TzwoLCEPXiI+a24GD9DUpUoDrRGf4LZoH2CuvSPL4Vp7aI+BRKACoqq0DEciqFbNMYAv7qF0BAsCvkrY4mQW394ECgYEA0q16l0oku6QNAk4GTc4FUeQpEAG4Ns+K3hkpWtZADVEqdsKd0F2cHmZy7IUorEi+kHHqGhvHihCq9hT81WJLvEPYWvTvhQq4aDDZ4+lyAQTGy0Bc043YfphlFGXxIa+rTbXRIQIuLKZmt3E+MSOvIqjTbZcXZd0GEzz0WDMGO8cCgYAcOH7+aBei9JztqrdOmI1xivCm925fJ0oF5jYku8Xp2TOhYxDB6pQeios4ugs42tv8kW5ioJv5Lg4idzZlbmOAm+WPlLzIrXrE3GgqusNQorxPJw2xkczuVfCcO7kGS6aNiXejN1L4AAGjWS1l5UtqZqkXIAR+BDITrfwLxIDKAQKBgC31rG2+vf61TiU3kkZ88EoqJQ8Z4O8MHbZP9OadIMIG9+WKlVT0Zu922BFjBzl2cSQfxbtGXiRveGxQrct7MxxyDIvjLTFv4kTQi2gd8EHqodeLRfTc1+LeKgbmKlF3+j3ssR+rUxlof7X7HV8o8rbz75PTx0Xwjre5r3BiSTTXAoGARXbjw+KHC6OLgVcysf2luOvHWqeeJw7E/NwsH3crhWOAH/3YA/syXI9vK3OXUZIWTNyOGVwq5niG7b8hRGUMFamG4gZh+rK70YL9HV79mJwzWqYnqeJBsFpwsDFC7IUkBYsLInjkozQn9CvKdKv36lSRvkqRdt9gEJPItloHBC0=';
        if($C['setting']['alipay_PayKEY']){
            $PKEY = $C['setting']['alipay_PayKEY'];
        }
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($PKEY, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        openssl_sign($url, $sign, $res, OPENSSL_ALGO_SHA256);
        $sign = base64_encode($sign);
        $data['sign'] = $sign;
        return $data;
    }

    /**
     * 支付宝异步订单状态请求处理
     */
    function ReturnStatus($data=[], $orderData=[]){
        global $C;
        if($_POST){
            file_put_contents(ROOT.'./data/alipay.txt', jsonEn($data));
            //只要有订单ID就直接查询订单状态 无需校验是否是支付宝发出的请求
            if($_POST && $_POST['out_trade_no']){
                $orderCode = preg_replace('/[^0-9]/','',$_POST['out_trade_no']);
                //支付宝主动POST数据检查 开发者ID 商户ID 必须对应 并且有返回支付订单号
                if($_POST['auth_app_id'] == $C['setting']['alipay_APPID'] && $orderCode){
                    DB('user_payorders')->update(['PayCallback'=> jsonEn($_POST)],['PayID'=>$orderData['PayID']]);
                    $orderData = DB('user_payorders')->orderCode($orderCode);
                    if($orderData && $orderData['Status'] ==0 && $orderData['PayID']){
                        loadFunction('pay');
                        Pay_query($orderData['PayID']);
                    }
                }
            }
            echo 'success';
        }
    }
}