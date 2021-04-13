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
    public static $TYPES = [
        'wechat' => '微信支付',
        'weapp' => '微信小程序',
        'alipay' => '支付宝'
    ];

    function Pay_getID($id){
        return DB('pay_data')->ID($id);
    }

    /**
     * 生成一条不重复订单编号 14-24位
     * 0-14位固定日期（秒级） 15-24位为自增ID
     * 依靠redis完成自增值机制
     * @param int $N 需要生成多少位(默认20) 值范围18-24
     * @return false|string
     */
    static function orderCode($N=20){
        $str = intval(date('Y')) - 2020; //第一组 1-2位 年差值
        $str .= date('mdHis'); //第二组 4位 月日
        list($ms, $sec) = explode(' ', microtime());
        //毫秒控制6位
        $ms = str_pad(substr($ms, 2, 6), 6, '0', STR_PAD_RIGHT); //右侧补0
        $str += $sec; //累加10位时间戳
        $str .= $ms; //追加毫秒数
        $str = substr($str, 1);
        //利用缓存 相同号码下自增
        $cacheK = '_orderCodeAuto_'.$str;
        $auto = Cache::RAM('get', $cacheK);
        $auto = $auto > 0 ? $auto : 10;
        $auto ++;
        Cache::RAM('set', $cacheK, $auto, 1);
        $str .= $auto;
        $str .= mt_rand(100000, 999999); //补充6位随机码 最终达到至少24位
        return substr($str, 0, $N);
    }

    /**
     * 支付订单状态查询
     * @param int $PayID 支付订单PayID
     * @return array $returnData
     */
    function query($PayID=0){
        $PayID = intval($PayID);
        $Data = self::Pay_getID($PayID);
        $queryDt = [];
        $returnData = [
            //该订单数据是否是首次确认，用于后续数据更新
            //	0=从DB中读取的数据 1=第一次从API中确认用户已支付成功
            'isNew' => 0,
            //end

            'success' => 0, //支付状态 1=成功
            'PayType' => $Data['PayType'], //支付方式
            'PayTypeName' => self::$TYPES[$Data['PayType']], //支付方式名称
            'msg' => '', //消息内容
            'orderData' => $Data //当前支付订单DB数据

        ];
        if($Data['Status'] == 0){
            if($Data['PayType'] =='wechat'){
                $queryDt = WechatPay::query($Data['DataOrderCode']);
            }elseif($Data['PayType'] =='weapp'){
                $queryDt = WechatPay::query_jsapi($Data['DataOrderCode']);
            }elseif($Data['PayType'] =='alipay'){
                $queryDt = Alipay::pay_query($Data['DataOrderCode']);
            }
            $isTotalOK = floatval($queryDt['total']) == floatval($Data['total']) ? 1 : 0; //实际支付金额和订单金额是否对应
            $isTotalOK = 1;
            if($queryDt['success'] && $isTotalOK){
                $returnData['success'] = 1;
                $this->__Pay_success_DatatypeStatus($Data,$Data['DataType'],$Data['DataID'],$Data['DataOrderCode']);
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
     * 注意：
     *      强制为当前用户登录用户创建支付订单！
     *      根据传入的数据自动过期、自动创建
     * 如果未登录 则而失败
     * @param string $orderCode 订单ID (必须)
     * @param string $PayType 支付类型(必须) wechat || alipay
     * @param string $dataType 商品数据类型 (必须) goods=热卖 vip=会员 expert=专家
     * @param int $dataID 商品数据ID (必须) int
     * @param string $Total 支付金额 (必须) 单位分
     * @param string $Title 商品名称 (必须) 100字
     * @param string $Summary 支付摘要 用户支付时显示 可选 100字
     * @return array $returnData
     */
    static function create($orderCode, $PayType='', $dataType='', $dataID='', $Total=0, $Title='', $Summary=''){
        global $C;
        $uid = $C['uid'] && $C['uid']['user'] ? intval($C['uid']) : 0;
        $orderCode = Filter::int($orderCode);
        $PayType = self::$TYPES[$PayType] ? $PayType : '';
        $dataType = in_array($dataType,['goods','vip','expert']) ? $dataType : '';
        $dataID = Filter::int($dataID);
        $Total = floatval($Total);
        $Title = stripTags($Title, 120);
        $Summary = stripTags($Summary, 120);
        $returnData = [
            'success' => 0,
            'uid' => $uid,
            'msg' => '',
            'PayData' => [] //返回的订单数据
        ];

        //订单失效时间 秒
        $orderOutTime = 3600;

        if($uid > 0 && $PayType && $dataID && $Total >0 && $Title){

            $user = DB('user')->uid($uid);
            if($user){

                //检查是否有重复的待支付订单 数据类型 与数据订单号相同
                $PayData = DB('pay_data')->where(['uid'=>$uid, 'DataID'=>$dataID,'DataType'=>$dataType,'DataOrderCode'=>$orderCode, 'Status'=>[0,1]])->fetch_first();
                if($PayData){
                    //如果该订单超过有效期  则变更为失效状态
                    if($PayData['createDate'] < time() - $orderOutTime){
                        DB('pay_data')->where('PayID', $PayData['PayID'])->update(['status'=>3]);
                        unset($PayData);
                    }else{
                        $PayData['payCreateData'] = json_decode($PayData['payCreateData'], true);
                    }
                }
                if(!$PayData){
                    //添加待支付订单数据到DB
                    $PayData = [
                        'uid' => $uid,
                        'DataID' => $dataID,
                        'DataType' => $dataType,
                        'DataOrderCode' => $orderCode,
                        'title' => $Title,
                        'total' => $Total,
                        'PayType' => $PayType,
                        'IP' => Rest::ip(),
                        'IP_port' => Rest::ipProt(),
                        'useragent' => Rest::useragent(),
                        'createDate' => time(),
                        'Status' => 0,
                        'PayCode' => self::orderCode()
                    ];
                    $PayData['PayID'] = DB('pay_data')->insert($PayData, true);

                    //支付平台回调地址
                    $callbackUrl = $C['siteurl'].'pay/confirm/callback_wechat/';
                    //微信 下单
                    if($PayType == 'wechat' || $PayType == 'weapp'){

                        if($user['WXopenid']){
                            $wechatSetting = $PayType == 'weapp' ? APP::setting('WEAPP') : APP::setting('WECHAT');
                            $wechatPaySetting = APP::setting('WECHATPAY');
                            $payDt = WechatPay::create_jsapi($wechatSetting['APPID'], $wechatPaySetting['MCHID'], [
                                'description' => $Title,
                                'out_trade_no' => $PayData['PayCode'],
                                'notify_url' => $callbackUrl,
                                'amount' => [
                                    'total' => $Total,
                                    'currency' => 'CNY'
                                ],
                                'payer' => [
                                    'openid' => $user['WXopenid'],
                                ],
                                'scene_info' => [
                                    'payer_client_ip' => Rest::ip(),
                                ],
                            ]);

                            //下单成功
                            if($payDt['success']){
                                $PayData['payCreateData'] = $payDt['createData'];
                            }else{
                                $returnData['msg'] = 'PayMsg: '.$payDt['msg'];
                            }
                        }else{
                            $returnData['msg'] = '用户微信OpenID缺失';
                        }

                        //支付宝 下单
                    }elseif($PayType =='alipay'){
                        $payDt = Alipay::Pay_create($PayData['PayCode'], $Total, $Title, $callbackUrl);
                        //下单成功
                        if($payDt && $payDt['success'] && $payDt['sign']){
                            $PayData['payCreateData'] = $payDt['createData'];
                        }else{
                            $returnData['msg'] = 'PayMsg: '.$payDt['msg'];
                        }
                    }

                }

                if($PayData['payCreateData'] && $PayData['Status'] === 0){
                    DB('pay_data')->where('PayID', $PayData['PayID'])->update([
                        'payCreateData'=>json_encode($PayData['payCreateData']),
                        'Status' => 1 //支付订单状态变更为建单成功 待付款
                    ]);
                }

                if(!$returnData['msg']){
                    $returnData['success'] = 1;
                    $returnData['msg'] = '下单成功';
                    $returnData['PayData'] = $PayData;
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
     * @param array $OrderData 订单数据
     * @param string $dataType 订单类型
     * @param string $dataID 订单ID
     * @param string $orderCode 订单号
     * @return boolean
     */
    function __Pay_success_DatatypeStatus($OrderData=[], $dataType='',$dataID='',$orderCode=''){

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