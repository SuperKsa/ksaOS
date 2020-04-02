<?php
/**
 *
 * -------------------------------
 * Author:  CR180 <cr180@cr180.com>
 * Date:    2020/4/1 10:27
 * Update:  2020/4/1 10:27
 *
 */
namespace ksaOS;

use Cassandra\Date;

if(!defined('KSAOS')) {
    exit('Error.');
}

class Pay{
    function Pay_getID($id){
        return DB('pay_data')->ID($id);
    }

    /**
     * 生成一条不重复订单编号 24位
     * 0-14位固定日期（秒级） 15-24位为自增ID
     * 依靠redis完成自增值机制
     */
    static function orderCode(){
        $cacheK = '_orderCode_';
        //0-14位固定日期（秒级） 15-24位为自增
        $str = date('YmdHis');
        $X = Cache::RAM('get',$cacheK);
        $Xx = Cache::RAM('get',$cacheK.'_k_');
        if($Xx < time()){
            $X = 1;
            Cache::RAM('set',$cacheK.'_k_', time(),0);
        }else{
            $X ++;
        }
        Cache::RAM('set',$cacheK,$X,0);
        $str .= str_pad($X,10,'0', STR_PAD_LEFT);
        return $str;
    }

    /**
     * 支付订单状态查询
     * @param type $PayID 支付订单PayID
     * @return array $returnData
     */
    function query($PayID){
        $PayID = intval($PayID);
        $Data = Pay_getID($PayID);
        $queryDt = [];
        $PayTypeName = [
            'wechat' => '微信',
            'alipay' => '支付宝',
        ];

        $returnData = [
            //该订单数据是否是首次确认，用于后续数据更新
            //	0=从DB中读取的数据 1=第一次从API中确认用户已支付成功
            'isNew' => 0,
            //end

            'success' => 0, //支付状态 1=成功
            'PayType' => $Data['PayType'], //支付方式
            'PayTypeName' => $PayTypeName[$Data['PayType']], //支付方式名称
            'msg' => '', //消息内容
            'orderData' => $Data //当前支付订单DB数据

        ];
        if($Data['Status'] == 0){
            if($Data['PayType'] =='wechat'){
                $queryDt = Wechat::pay_query($Data['DataOrderCode']);
            }elseif($Data['PayType'] =='alipay'){
                $queryDt = Alipay::pay_query($Data['DataOrderCode']);
            }
            $isTotalOK = floatval($queryDt['total']) == floatval($Data['total']) ? 1 : 0; //实际支付金额和订单金额是否对应
            $isTotalOK = 1;
            if($queryDt['success'] && $isTotalOK){
                $returnData['success'] = 1;
                __Pay_success_DatatypeStatus($Data,$Data['DataType'],$Data['DataID'],$Data['DataOrderCode']);
            }
            $returnData['msg'] = $queryDt['msg'];
            $returnData['isNew'] = 1; //首次确认状态 必须要与success=1一起判断支付成功

        }elseif($Data['Status'] == 1){
            $returnData['success'] = 1;
            $returnData['msg'] = '支付成功';
        }
        return $returnData;
    }

    /**
     * 创建支付订单
     * 注意：强制为当前用户登录用户创建支付订单！
     * 如果未登录 则而失败
     * @param string $orderCode 订单ID (必须)
     * @param string $PayType 支付类型(必须) wechat || alipay
     * @param string $dataType 商品数据类型 (必须) goods=热卖 vip=会员 expert=专家
     * @param int $dataID 商品数据ID (必须) int
     * @param string $Total 支付金额 (必须)
     * @param string $Title 商品名称 (必须) 100字
     * @param string $Summary 支付摘要 用户支付时显示 可选 100字
     * @return array $returnData
     */
    static function create($orderCode, $PayType='', $dataType='', $dataID='', $Total=0, $Title='', $Summary=''){
        global $C;
        $uid = $C['uid'] && $C['uid']['user'] ? intval($C['uid']) : 0;

        $orderCode = stripTags($orderCode, 100);
        $PayType = in_array($PayType,['wechat','alipay']) ? $PayType : '';
        $dataType = in_array($dataType,['goods','vip','expert']) ? $dataType : '';
        $dataID = intval($dataID);
        $Total = floatval($Total);
        $Title = stripTags($Title, 100);
        $Summary = stripTags($Summary, 100);
        $returnData = [
            'success' => 0,
            'uid' => $uid,
            'msg' => '创建支付订单失败',
            'PayData' => [] //返回的订单数据
        ];

        if($uid > 0 && $PayType && $dataID && $Total >0 && $Title){

            $user = DB('user')->uid($uid);
            if($user){

                $insertDt = [
                    'uid' => $uid,
                    'DataID' => $dataID,
                    'DataType' => $dataType,
                    'DataOrderCode' => $orderCode,
                    'title' => $Title,
                    'total' => $Total,
                    'PayType' => $PayType
                ];
                ksort($insertDt);
                //订单失效时间
                $orderOutTime = 180;

                //检查是否有重复的待支付订单 数据类型 与数据订单号相同
                $PayData = DB('pay_data')->where(['uid'=>$uid, 'DataID'=>$insertDt['DataID'],'DataType'=>$insertDt['DataType'],'DataOrderCode'=>$insertDt['DataOrderCode'],'Status'=>[0,1]])->fetch_first();
                if($PayData){
                    //如果该订单已失效 则变更状态
                    if($PayData['createDate'] < time() - $orderOutTime){
                        DB('pay_data')->where('PayID', $PayData['PayID'])->update(['status'=>3]);
                        unset($PayData);
                    }
                }

                if(!$PayData){
                    //添加待支付订单数据到DB
                    $insertDt = array_merge($insertDt,[
                        'IP' => $C['IP'],
                        'IP_port' => $C['port'],
                        'useragent' => $C['useragent'],
                        'createDate' => time(),
                        'Status' => 0,
                        'PayCode' => self::orderCode()
                    ]);
                    $insertDt['PayID'] = DB('pay_data')->insert($insertDt,true);
                    $PayData = $insertDt;
                }
                if($PayData){
                    $PayCreateStatus = 0;
                    //支付平台回调地址
                    $callbackUrl = $C['siteurl'].'pay/confirm/callback_wechat/';
                    //微信 下单
                    if($PayType == 'wechat'){
                        if($user['WXopenid']){
                            $payDt = Wechat::Pay_create($user['WXopenid'], $PayData['PayCode'], $Total, $Title,$callbackUrl);
                            //下单成功
                            if($payDt && $payDt['success'] && $payDt['prepay_id'] && $payDt['sign']){
                                $PayData['PayUrl'] = $payDt['PayUrl'];
                                $PayData['payCreateData'] = $payDt;
                                $PayCreateStatus = 1;
                            }else{
                                $returnData['msg'] = 'PayMsg: '.$payDt['msg'];
                            }
                        }else{
                            $returnData['msg'] = '非微信环境或没有该用户的微信OpenID';
                        }

                        //支付宝 下单
                    }elseif($PayType =='alipay'){
                        $payDt = Alipay::Pay_create($PayData['PayCode'], $Total, $Title, $callbackUrl);
                        //下单成功
                        if($payDt && $payDt['success'] && $payDt['sign']){
                            $PayData['PayUrl'] = $payDt['PayUrl'];
                            $PayData['payCreateData'] = $payDt;
                            $PayCreateStatus = 1;
                        }else{
                            $returnData['msg'] = 'PayMsg: '.$payDt['msg'];
                        }
                    }

                    if($PayCreateStatus){
                        $returnData['success'] = 1;
                        $returnData['msg'] = '下单成功';
                        $returnData['PayData'] = $PayData;
                        DB('pay_data')->where('PayID', $PayData['PayID'])->update([
                            'payCreateData'=>json_encode($PayData['payCreateData']),
                            'PayUrl'=>$PayData['PayUrl'],
                            'Status' => 1 //支付订单状态变更为建单成功 待付款
                        ]);
                    }
                }

            }else{
                $returnData['success'] = 0;
                $returnData['msg'] = '下单用户不存在';
            }
        }else{
            $returnData['success'] = 0;
            $returnData['msg'] = '下单数据异常';
        }
        return $returnData;
    }


    /**
     * 支付成功后更新对应数据状态(仅限于首次支付成功处理) 当前模块内部函数
     * @global type $C
     * @param type $OrderData 订单数据
     * @param type $dataType 订单类型
     * @param type $dataID 订单ID
     * @param type $orderCode 订单号
     * @return boolean
     */
    function __Pay_success_DatatypeStatus($OrderData, $dataType='',$dataID='',$orderCode=''){
        global $C;

        if(!$OrderData || !$OrderData['DataOrderCode'] || !$OrderData['PayID']){
            return false;
        }

        $user = DB('user')->ID($OrderData['uid']);
        if(!$user){
            return false;
        }

        //更新支付状态为成功
        DB('pay_data')->where('PayID', $OrderData['PayID'])->update(['Status'=>1,'checkDate'=>time()]);


    }
}