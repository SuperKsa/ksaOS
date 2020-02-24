<?php
/**
 * 短信验证码处理类
 * -------------------------------
 * Author:  CR180 <cr180@cr180.com>
 * Date:    2020/2/24 23:26
 * Update:  2020/2/24 23:26
 *
 */
 
 class Sms{
     /**
      * 短信验证码发送函数
      * @param type $mobile 手机号
      * @return type 返回数组
     array(
     //固定返回
     'error' =>0, //错误代码 0=成功
     'msg' => '', //错误信息 为空=成功
     'mobile' => $mobile, //发送手机号
     'sendTime' => 0, //两次发送间隔时间
     'dateline' => '', //发送时间
      *
     //成功返回
     'code' => '', //验证码
     'smsID' => '', //入库记录ID
     )
      */
     function send($mobile){
         global $C;
         $sendMsg = '您的短信验证码为{code}(30分钟内有效)，若非本人操作请忽略'; //短信模板内容
         $API_url = 'http://smssh1.253.com/msg/send/json';
         $API_account = ''; //接口帐号
         $API_password = ''; //接口密码
         $API_report = true; //接口是否需要返回报告 true=是 请勿修改


         $sendHours = 1; //最大发送次数时间单位 小时
         $sendMaxNum = 10; //X小时内最大发送次数
         $sendTime = 60; //每次发送间隔时间 秒

         $CODE = mt_rand(1,9).mt_rand(0,9).mt_rand(0,9).mt_rand(0,9).mt_rand(0,9).mt_rand(0,9); //短信验证码为纯数字
         $sendMsg = trim($sendMsg);
         $mobile = intval($mobile);

         $Returns = [
             'error' => 0,
             'msg' => '',
             'mobile' => $mobile,
             'sendTime' => $sendTime,
             'dateline' => TIME,
         ];

         if(!$API_url || !$API_account || !$API_password || !$sendMsg || $sendHours <=0 || $sendMaxNum <=0 || $sendTime <=0 || strpos($sendMsg,'{code}') === false){
             $Returns['error'] = 1;
             $Returns['msg'] = '系统参数配置错误，无法继续';
         }elseif(mb_strlen($mobile) != 11){
             $Returns['error'] = 2;
             $Returns['msg'] = '手机号码错误';
         }else{
             $sendNum = DB('user_sms')->mobileTimeCount($mobile, 3600*$sendHours);
             if($sendNum){
                 if($sendNum >= $sendMaxNum){
                     $Returns['error'] = 3;
                     $Returns['msg'] = '最大发送次数达到限制，请尝试更换手机号';
                 }else{
                     $lastDt = DB('user_sms')->mobileLast($mobile);
                     if($lastDt && $lastDt['dateline'] + $sendTime > TIME){
                         $Returns['error'] = 4;
                         $Returns['msg'] = '发送间隔时间限制，请稍后重试';
                     }
                 }
             }

             if(!$Returns['error']){
                 $sendMsg = str_replace('{code}',$CODE,$sendMsg);
                 $post =array(
                     'account' =>$API_account,
                     'password' =>$API_password,
                     'msg' => urlencode($sendMsg),
                     'phone' => $mobile,
                     'report' => $API_report
                 );
                 $Curl = curl($API_url, json_encode($post), ['Content-Type'=>'application/json; charset=utf-8']);
                 $callBackData = $Curl['data'] ? json_decode($Curl['data'],true) : [];

                 $sendStatus = 2;
                 $apiReturn = '';
                 if($callBackData && $callBackData['time']){
                     if($callBackData['code'] =='0' && !$callBackData['errorMsg'] && $callBackData['msgId']){
                         //发送成功
                         $sendStatus = 1;
                         $apiReturn = cjson_encode($callBackData);
                     }
                     $smsID = DB('user_sms')->insert([
                         'uid' => $C['uid'] ? $C['uid'] : 0,
                         'mobile' => $mobile,
                         'code' => $CODE,
                         'content' => $sendMsg,
                         'sendStatus' => $sendStatus,
                         'apiReturn' => $apiReturn,
                         'dateline' => TIME,
                     ], true);
                     $Returns['code'] = $CODE;
                     $Returns['smsID'] = $smsID;
                 }else{
                     $Returns['error'] = 5;
                     $Returns['msg'] = '短信发送失败';
                 }
             }
         }
         return $Returns;
     }

 }