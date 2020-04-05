<?php
/**
 * 过滤器
 * 所有函数须成功校验成功必须返回原始值或二次修饰值
 * -------------------------------
 * Author:  CR180 <cr180@cr180.com>
 * Date:    2020/4/5 11:58
 * Update:  2020/4/5 11:58
 *
 */

namespace ksaOS;

if(!defined('KSAOS')) {
    exit('Error.');
}

class Filter{

    /**
     * 过滤所有非连续数字 支持负值 | -123a = -123 | 123a456=123 | -123-456=-123
     * @param string $str
     * @return string
     */
    static function int($str='') {
        preg_match('/-?[0-9]+/', $str, $tmp);
        if($tmp){
            return $tmp[0];
        }
        return false;
    }

    /**
     * 字符串剥离html标签并实体化
     * @param string $str
     * @return string
     */
    function text($str='', $len=0){
        $str = chtmlspecialchars(stripTags($str, $len));
        return $str;
    }

    /**
     * 过滤日期格式的字符串
     * @param string $str
     * @return string $str
     */
    function date($str=''){
        $str = trim($str);
        if(preg_match('/[0-9]{4}[-|年\/][0-9]{1,2}[-|月\/][0-9]{1,2}(\s+[0-9]{1,2}\:[0-5]{1,2}(\:[0-9]{1,2})?)?/', $str)){
            return $str;
        }
    }

    /**
     * 正则匹配
     * @param string $str 输入值
     * @param string $reg 正则
     * @return string 成功返回输入值
     */
    static function reg($str='', $reg=''){

        if(preg_match($reg, $str)){
            return $str;
        }
        return false;
    }

    /**
     * 判断是否为邮箱地址
     * @param string $str 邮箱地址
     * @return null 成功返回原值
     */
    static function email($str='') {
        return filter_var($str, FILTER_VALIDATE_EMAIL) ? $str : false;
    }

    /**
     * 判断是否为IPV6地址
     * @param string $str IP
     * @return null 成功返回原值
     */
    static function IP($str='') {
        return $str && filter_var($str, FILTER_VALIDATE_IP) ? $str : false;
    }

    /**
     * 判断是否为IPV6地址
     * @param string $str IPV6地址
     * @return null 成功返回原值
     */
    static function IP6($str='') {
        return $str && filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? $str : false;
    }

    /**
     * 判断是否为座机号码
     * @param string $str
     * @return null 成功返回原值
     */
    static function Phone($str='') {
        //先判断是否是座机 区号[ |-]号码
        if(preg_match("/^[0-9]{3,4}[\s+|-]?[0-9]{7,8}$/", $str)){
            return $str;
        }
        return false;
    }

    /**
     * 判断是否为手机号码
     * @param string $str 手机号
     * @return null 成功返回原值
     */
    static function Mobile($str='') {
        return mb_strlen($str) == 11 && preg_match("/^1([0-9]{10})$/", $str) ? $str : false;
    }

    /**
     * 判断是否为中文姓名
     * @param string $str
     * @return null 成功返回原值
     */
    static function cnName($str=''){
        return $str && preg_match("/^[\x{4e00}-\x{9fa5}]{2,8}$/u", $str) ? $str : false;
    }

    /**
     * 验证是否为身份证号码
     * @param string $str 身份证号码
     * @param bool $sp 是否需要返回格式化数据
     * @return null $str 成功返回原值或格式化后的数组
     */
    static function idCard($str='', $sp=false){
        if(is_numeric($str) && strlen($str) == 18){
            $SCODE = [11=>'北京',12=>'天津',13=>'河北',14=>'山西',15=>'内蒙古',21=>'辽宁',22=>'吉林',23=>'黑龙江',31=>'上海',32=>'江苏',33=>'浙江',34=>'安徽',35=>'福建',36=>'江西',37=>'山东',41=>'河南',42=>'湖北',43=>'湖南',44=>'广东',45=>'广西',46=>'海南',50=>'重庆',51=>'四川',52=>'贵州',53=>'云南',54=>'西藏',61=>'陕西',62=>'甘肃',63=>'青海',64=>'宁夏',65=>'新疆',71=>'台湾',81=>'香港',82=>'澳门',91=>'国外'];

            $X = [
                1 => substr($str,0,2), //省号
                2 => substr($str,2,4), //城市号
                3 => substr($str,6,4), //出生年
                4 => substr($str,10,2), //出生月
                5 => substr($str,12,2), //出生日
                6 => substr($str,14,2), //序号
                7 => substr($str,16,1), //序号-性别 奇数男 偶数女
                8 => substr($str,17,1)
            ];
            if($SCODE[$X[1]] && strtotime($X[3].'-'.$X[4].'-'.$X[5])) {
                //检测最后一位 按照ISO 7064:1983.MOD 11-2的规定生成
                $map = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
                $factor = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
                $sign = 0;
                for ($i = 0; $i < 17; $i++) {
                    $sign += intval($str{$i}) * $map[$i];
                }
                $n = $sign % 11;
                if ($factor[$n] == $X[8]) {
                    return $sp ? $X : $str;
                }
            }
        }
        return false;
    }
}