<?php
/**
 * 阿里云短信验证码发送
 * @date    2022-11-14 13:56:12
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 *
 * 发送短信用法:
    Alisms::send('18888888888', 4, 'XXX科技', 'SMS_248425303', ['code'=>1234]);
 *
 * 发送语音通知用法：
    Alisms::voice('18888888888', 'SMS_248425303', ['code'=>1234])
 *
 */
namespace ksaOS;

if(!defined('KSAOS')) {
    exit('Error.');
}

class Alisms{


    public static $AccessKeyId = ''; //授权ID
    public static $accessKeySecret = ''; //授权私钥
    public static $SignatureVersion = '1.0'; //授权版本
    public static $Version = '2017-05-25'; //API版本号

    private static function get_url($sendData=[]){
        $data = [
            "AccessKeyId" => self::$AccessKeyId,
            "Format" => "JSON",
            "RegionId" => "cn-hangzhou",
            "SignatureMethod" => "HMAC-SHA1",
            "SignatureNonce" => Uuid(),
            "SignatureVersion" => self::$SignatureVersion,
            "Timestamp" => Dates::timeUTC(null, 'Y-m-d H:i:s').'Z',
            "Version" => self::$Version
        ];
        $data = array_merges($data, $sendData);

        $sign = [];
        foreach($data as $key => $value){
            if($value != null && $value != ""){
                $value = urlencode($value);
                $value = str_replace("+", "%20", $value);
                $sign[$key] = $key.'='.$value;
            }

        }
        ksort($sign);
        $sign = implode('&', $sign);
        return $sign;
    }

    private static function sign($sign=''){
        $sign = 'GET&%2F&'.urlencode($sign);
        $sign = urlencode(Hmacsha::Sha1($sign, self::$accessKeySecret.'&'));
        return $sign;
    }


    /**
     * 发送短信验证码
     * @param $mobile string 需要发送的手机号
     * @param $signName string 短信签名
     * @param $TemplateCode string 短信模板代码
     * @param $TemplateParam array 短信模板参数 程序自动将数组转换为json
     * @param $callFun callable 回调函数 参数1=CURL结果 必须返回true才能认为是成功
     * @param $OutId string 外部流水扩展字段。
     * @param $SmsUpExtendCode string 上行短信扩展码。上行短信指发送给通信服务提供商的短信，用于定制某种服务、完成查询，或是办理某种业务等，需要收费，按运营商普通短信资费进行扣费。
     * @return void
     */
    public static function send($mobile='', $signName='', $TemplateCode='', $TemplateParam=[], $callFun=false, $OutId='', $SmsUpExtendCode=''){

        $apiUrl = 'https://dysmsapi.aliyuncs.com/'; //发送地址

        $TemplateParam = $TemplateParam ? json_encode($TemplateParam) : '';
        $sign = self::get_url([
            "PhoneNumbers" => $mobile,
            "SignName" => $signName,
            "TemplateCode" => $TemplateCode,
            "TemplateParam" => $TemplateParam,
            "OutId" => $OutId,
            "SmsUpExtendCode" => $SmsUpExtendCode,
            "Action" => 'SendSms'
        ]);

        $Signature = self::sign($sign);
        $API_url = $apiUrl.'?Signature='.$Signature.'&'.$sign;

        $Curl = Curls::send($API_url);

        $res = $Curl['data'] ? json_decode($Curl['data'], true) : [];
        $callFun && call_user_func($callFun, $res);
        return $res;
    }


    /**
     * 发送语音模板消息
     * @param $mobile int|string 接收电话号码
     * @param $TemplateCode string 语音模板ID
     * @param $TemplateParam array 模板参数 以数组的形式传入，函数自动转json
     * @param $CalledShowNumber int|string 来电显示号码(需要单独申请)
     * @param $PlayTimes int 每通电话语音播报次数 1-3 默认2次
     * @param $Volume int 音量 1-100 默认100
     * @param $Speed int 播报语速 -500 - 500 默认200
     * @param $OutId string 预留自定义ID 比如提前生成的本地业务ID
     * @param $callFun callable 请求完成后立即回调
    {
    "Code": "OK",
    "Message": "OK",
    "RequestId": "D9CB3933-9FE3-4870-BA8E-2BEE91B69D23",
    "CallId": "116012354148^10281378****"
    }
     * @return array|mixed 返回原始数据
     */
    public static function voice($mobile='', $TemplateCode='', $TemplateParam=[], $CalledShowNumber='', $PlayTimes=2, $Volume=100, $Speed= 0, $OutId='', $callFun=false){
        $apiUrl = 'https://dyvmsapi.aliyuncs.com/'; //发送地址

        $TemplateParam = $TemplateParam ? json_encode($TemplateParam) : '';
        $sign = self::get_url([
            'CalledShowNumber' => $CalledShowNumber,
            'CalledNumber' => $mobile,
            'TtsCode' => $TemplateCode,
            'TtsParam' => $TemplateParam,
            'PlayTimes' => $PlayTimes,
            'OutId' => $OutId,
            'Volume' => $Volume,
            'Speed' => $Speed,
            'Action' => 'SingleCallByTts'
        ]);

        $Signature = self::sign($sign);
        $API_url = $apiUrl.'?Signature='.$Signature.'&'.$sign;

        $Curl = Curls::send($API_url);

        $res = $Curl['data'] ? json_decode($Curl['data'], true) : [];
        $callFun && call_user_func($callFun, $res);

        return $res;
    }

}