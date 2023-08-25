<?php
/**
 * KSA加密算法
 * -------------------------------
 * Author:  CR180 <cr180@cr180.com>
 * Date:    2023年8月12日 16:35:30
 * Update:  2023年8月12日 16:35:30
 *
 */

namespace ksaOS;


if(!defined('KSAOS')) {
    exit('Error.');
}

class KsaCode{
    /**
     * 加密私钥
     * @var string
     */
    public static $ENCODEKEY = 'KSAOS';
    /**
     * 混淆长度
     * @var int
     */
    public static $CkeyLen = 4;
    
    public static function encode($string='', $key = '', $expiry = 0){
        $result = self::base('ENCODE', $string, $key, $expiry);
        $result = urlencode($result);
        return $result;
    }
    
    public static function decode($string='', $key = ''){
        $string = urldecode($string);
        $result = self::base('DECODE', $string, $key);
        return $result;
    }
    
    /**
     * 可逆加函数
     * @param string $Type 处理方式 (DECODE=解密 ENCODE=加密)
     * @param string $string 需要处理的字符
     * @param string $key 加解密时需要混淆的Salt
     * @param int $expiry 过期时间
     * @return string
     */
    public static function base($Type = 'DECODE', $string='', $key = '', $expiry = 0) {
        $key .= self::$ENCODEKEY;
        $ckey_length = self::$CkeyLen;
        $key = md5($key);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = '';
        if($ckey_length){
            if($Type == 'DECODE'){
                $keyc = substr($string, 0, $ckey_length);
            }else{
                $keyc = substr(md5(microtime()), -$ckey_length);
            }
        }
        $skey = $keya.md5($keya.$keyc);
        $klen = strlen($skey);
        if($Type == 'DECODE'){
            $string = base64_decode(substr($string, $ckey_length));
        }else{
            $string = sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        }
        $len = strlen($string);
    
        $result = '';
        $box = range(0, 255);
    
        $rndkey = [];
        for($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($skey[$i % $klen]);
        }
    
        for($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
    
        for($a = $j = $i = 0; $i < $len; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
    
        if($Type == 'DECODE') {
            if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc.str_replace('=', '', base64_encode($result));
        }
    
    }
    
}