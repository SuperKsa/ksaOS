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

class SMS{

    public static function setting(){
        $setting = APP::setting('SMS');

        return $setting;
    }



    /**
     * 校验短信验证码
     * 校验成功后 该验证码将会无法再次校验！！
     * @param string $action 发送验证码时的$acion值
     * @param int $mobile 手机号
     * @param int $smsID 短信ID（发送验证码时返回）
     * @param string $smsHash 短信hash（发送验证码时返回）
     * @param string $code 验证码（不传递则为自动生成）
     * @return array 始终返回数组 [
    'success' =>0,
    'msg' => '',
    'mobile' => $mobile
    ]
     */
    public static function checkCode($action='', $mobile=0, $smsID=0, $smsHash='', $code=''){
        $return = [
            'success' =>0,
            'msg' => '',
            'mobile' => $mobile
        ];
        if(!IS::mobile($mobile)){
            $return['msg'] = '手机号码错误';
        }elseif(!$smsID){
            $return['msg'] = '请获取短信验证码';
        }elseif(!$smsHash){
            $return['msg'] = '请填写短信验证码';
        }else{
            $smsSetting = APP::setting('SMS');
            $outTime = $smsSetting && $smsSetting['outTime'] > 0 ? ($smsSetting['outTime'] * 60) : 900;
            $sms = DB('user_sms')->where(['smsID'=>$smsID])->fetch_first();

            if(!$sms) {
                $return['msg'] = '短信验证码参数错误';
            }elseif($sms['actions'] != $action){
                $return['msg'] = '短信验证码场景错误';
            }elseif($sms['mobile'] != $mobile){
                $return['msg'] = '短信验证码错误';
            }elseif($sms['isUse']){
                $return['msg'] = '短信验证码已失效';
            }elseif($code != $sms['code'] || $smsHash != md5($sms['code'].$sms['smsID'])){
                $return['msg'] = '短信验证码错误';
            }elseif($sms['dateline'] < TIME - $outTime){//短信验证码超过n分钟
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
     * @param string $action 指定验证码场景值（必须） 多个场景下验证码校验互不干扰
     * @param string $mobile 需要发送的手机号
     * @param false $callFun 回调函数(参数1=API原始结果) 必须返回true才能认为是成功
     * @param int $codeNum 验证码位数（默认自动生成）
     * @param string $content 验证码模板内容（默认调用后台设置，自定义时必须带参数{code}）
     *
     */
    public static function sendCode($action='', $mobile='', $callFun='', $codeNum=6, $content=''){
        global $C;
        $setting = self::setting();
        $Returns = [
            'success' =>0,
            'msg' => '',
            'mobile' => $mobile,
            'sendTime' => $setting['intervals'],
            'smsHash' => NULL,
            'smsID' => NULL
        ];

        $sendMsg = trim($content ? $content : $setting['content']); //短信模板内容

        if(!$sendMsg){
            $Returns['msg'] = '短信验证码参数丢失[模板内容]';
        }elseif($setting['intervals'] < 0){
            $Returns['msg'] = '短信验证码参数丢失[间隔时间]';
        }elseif($setting['sendHours'] < 0){
            $Returns['msg'] = '短信验证码参数丢失[最大发送时间 ]';
        }elseif(strpos($sendMsg,'{code}') === false){
            $Returns['msg'] = '短信验证码参数丢失[模板内容没有{code}参数]';
        }else{
            //短信验证码为纯数字
            $codeNum = in_array($codeNum, [4, 5, 6]) ? $codeNum : 6;
            $CODE = mt_rand(1,9);
            for($i=0;$i<$codeNum-1;$i++){
                $CODE .= mt_rand(0,9);
            }
            //查询一定时间内的发送次数
            if($setting['sendHours'] > 0 && $setting['maxNum'] >0) {
                $sendNum = DB('user_sms')->where([['mobile',$mobile],['dateline','>',3600*$setting['sendHours']], ['isUse',0], 'actions' => $action])->fetch_count();
                if ($sendNum >= $setting['maxNum']) {
                    $Returns['msg'] = '最大发送次数达到限制，请尝试更换手机号';
                }
            }
            if($setting['intervals'] >0){
                $lastDt = DB('user_sms')->where(['mobile'=>$mobile,'isUse'=>0, 'actions' => $action])->order('smsID')->limit(0,1)->fetch_first();
                if($lastDt && $lastDt['dateline'] + $setting['intervals'] > time()){
                    $Returns['msg'] = '发送间隔时间限制，请('.($lastDt['dateline'] + $setting['intervals'] - TIME).'秒后)重试';
                }
            }


            if(!$Returns['msg']){
                $sendMsg = str_replace('{code}',$CODE,$sendMsg);

                $send = self::send($action, $mobile, $sendMsg, $callFun);
                if($send['success'] && $send['smslogid']){
                    //发送成功
                    $smsID = DB('user_sms')->insert([
                        'actions' => $action,
                        'smslogid' => $send['smslogid'],
                        'uid' => $C['uid'] ? $C['uid'] : 0,
                        'mobile' => $mobile,
                        'code' => $CODE,
                        'dateline' => time(),
                    ], true);
                    $Returns['smsHash'] = md5($CODE.$smsID);
                    $Returns['smsID'] = $smsID;
                    $Returns['success'] = 1;

                }else{
                    $Returns['msg'] = $send['msg'];
                }
            }
        }
        return $Returns;
    }

    /**
     * 短信发送函数
     * @param $mobile
     * @param string $content
     */
    public static function send($action, $mobile, $content='', $callFun=''){
        global $C;

        $setting = self::setting();
        $Returns = [
            'smslogid' => NULL,
            'success' => 0,
            'msg' => '',
            'mobile' => $mobile,
            'content' => $content,
            'result' => NULL,
        ];
        if(!$callFun){
            $Returns['msg'] = '短信API缺少回调函数支持';
        }elseif(!$setting['API']){
            $Returns['msg'] = '短信API参数丢失[接口地址]';
        }elseif(!$setting['account']){
            $Returns['msg'] = '短信API参数丢失[帐号]';
        }elseif(!$setting['password']){
            $Returns['msg'] = '短信API参数丢失[密码]';
        }elseif(!IS::Mobile($mobile)){
            $Returns['msg'] = '手机号码错误';
        }else{
            //发送成功
            $Returns['smslogid'] = DB('sms')->insert([
                'actions' => $action,
                'uid' => $C['uid'] ? $C['uid'] : 0,
                'mobile' => $mobile,
                'content' => $content,
                'sendStatus' => 2,
                'dateline' => time(),
            ], true);
            $post = [
                'u='.$setting['account'],
                'p='.md5($setting['password']),
                'm='.$mobile,
                'c='.$content,
            ];
            $api = $setting['API'].'?'.implode('&',$post);
            $Curl = Curls::send($api);
            if($callFun && call_user_func($callFun, $Curl['data']) === true){
                DB('sms')->where('smslogid', $Returns['smslogid'])->update(['sendStatus'=>1, 'apiReturn'=>$Curl['data']]);
            }
            $Returns['result'] = $Curl['data'];
            if(!$Curl['error']){
                $Returns['success'] = 1;
            }else{
                $Returns['success'] = '短信API请求失败 errorCode:'.$Curl['httpcode'];
            }
        }
        return $Returns;
    }


}