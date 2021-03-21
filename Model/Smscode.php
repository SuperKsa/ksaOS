<?php
/**
 * 短信验证码发送与校验类
 * 暂无介绍
 * @date    2020-1-10 22:09:42
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Smscode.php (____ / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
    exit('Error.');
}

class Smscode{

    /**
     * 验证码校验函数
     * @param int $mobile  需要校验的手机号
     * @param int $smsID 短信ID
     * @param string $smsHash 短信HASH
     * @param int $code  验证码
     * @param int $outTime 验证码有效期（秒） 默认=900
     * @return array 始终返回数组 [
    'success' =>0,
    'msg' => '',
    'mobile' => $mobile
    ]
     */
    public static function check($mobile, $smsID, $smsHash, $code, $outTime=900){
        $return = [
            'success' =>0,
            'msg' => '',
            'mobile' => $mobile
        ];
        if(!Filter::mobile($mobile)){
            $return['msg'] = '手机号码错误';
        }elseif(!$smsID){
           $return['msg'] = '请获取短信验证码';
        }elseif(!$smsHash){
           $return['msg'] = '请填写短信验证码';
        }else{
            $sms = DB('user_sms')->ID($smsID);
            if(!$sms){
                $return['msg'] = '短信验证码参数错误';
            }elseif($sms['mobile'] != $mobile){
                $return['msg'] = '短信验证码错误';
            }elseif($sms['isUse']){
                $return['msg'] = '短信验证码已失效';
            }elseif($code != $sms['code'] || $smsHash != md5($sms['code'].$sms['smsID'])){
                $return['msg'] = '短信验证码错误';
            }elseif($sms['dateline'] < TIME - 900){//短信验证码超过15分钟
                DB('user_sms')->where('smsID', $smsID)->update(['isUse'=>1]);
                $return['msg'] = '短信验证码已失效';
            }else{
                DB('user_sms')->where('smsID', $smsID)->update(['isUse'=>1]);
                $return['success'] = 1;
            }
        }
       return $return;
    }

    /**
     * 发送短信验证码
     * @param string $mobile 需要发送的手机号
     * @param bool $callFun 回调函数 参数1=CURL结果 必须返回true才能认为是成功
     * @param int $codeNum 验证码位数 默认6位，仅支持4-6位
     * @param string $content 短信内容[可选] 默认调用后台设置，自定义时必须带参数{code}
     * @return array
     */
    public static function send($mobile='', $callFun=false, $codeNum=6, $content=''){
        global $C;
        $sendHours = intval($C['setting']['SMS_sendHours']); //最大发送次数时间单位 小时
        $sendMaxNum = intval($C['setting']['SMS_maxNum']); //X小时内最大发送次数
        $sendTime = intval($C['setting']['SMS_intervals']); //每次发送间隔时间 秒

        $sendMsg = trim($content ? $content : $C['setting']['SMS_content']); //短信模板内容
        $API_url = $C['setting']['SMS_API'];
        $API_account = $C['setting']['SMS_account']; //接口帐号
        $API_password = $C['setting']['SMS_password']; //接口密码

        $Returns = [
            'success' =>0,
            'msg' => '',
            'mobile' => $mobile,
            'sendTime' => $sendTime,
        ];

        if(!Filter::Mobile($mobile)){
            $Returns['msg'] = '手机号码错误';
        }elseif(!$API_account){
            $Returns['msg'] = '短信API配置参数丢失 1';
        }elseif(!$API_password){
            $Returns['msg'] = '短信API配置参数丢失 2';
        }else{
            //短信验证码为纯数字
            $codeNum = in_array($codeNum, [4, 5, 6]) ? $codeNum : 6;
            $CODE = mt_rand(1,9);
            for($i=0;$i<$codeNum-1;$i++){
                $CODE .= mt_rand(0,9);
            }
            $sendMsg = trim($sendMsg);

            if(!$API_url || !$API_account || !$API_password || !$sendMsg || $sendHours <=0 || $sendMaxNum <=0 || $sendTime <=0 || strpos($sendMsg,'{code}') === false){
                $Returns['msg'] = '系统参数配置错误，无法继续';
            }elseif(mb_strlen($mobile) != 11){
                $Returns['msg'] = '手机号码错误';
            }else{
                $sendNum = DB('user_sms')->mobileTimeCount($mobile, 3600*$sendHours);
                if($sendNum){
                    if($sendNum >= $sendMaxNum){
                        $Returns['msg'] = '最大发送次数达到限制，请尝试更换手机号';
                    }elseif($sendTime >0){
                        $lastDt = DB('user_sms')->mobileLast($mobile);
                        if($lastDt && $lastDt['dateline'] + $sendTime > time()){
                            $Returns['msg'] = '发送间隔时间限制，请稍后重试';
                        }
                    }
                }

                if(!$Returns['msg']){
                    $sendMsg = str_replace('{code}',$CODE,$sendMsg);
                    $post = [
                        'u='.$API_account,
                        'p='.md5($API_password),
                        'm='.$mobile,
                        'c='.$sendMsg,
                    ];
                    $API_url = $API_url.'?'.implode('&',$post);
                    $Curl = Curls::send($API_url);

                    if(call_user_func($callFun, $Curl['data']) === true){
                        //发送成功
                        $smsID = DB('user_sms')->insert([
                            'uid' => $C['uid'] ? $C['uid'] : 0,
                            'mobile' => $mobile,
                            'code' => $CODE,
                            'content' => $sendMsg,
                            'sendStatus' => 1,
                            'apiReturn' => $Curl['data'],
                            'dateline' => time(),
                        ], true);
                        $Returns['smsHash'] = md5($CODE.$smsID);
                        $Returns['smsID'] = $smsID;
                        $Returns['success'] = 1;

                    }else{
                        $Returns['msg'] = '短信发送失败';
                    }
                }
            }
        }
        return $Returns;
    }


}