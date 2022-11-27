<?php
/**
 * HmacSha加密算法
 * -------------------------------
 * Author:  CR180 <cr180@cr180.com>
 * Date:    2022/11/24 23:19
 * Update:  2022/11/24 23:19
 *
 */

namespace ksaOS;


if(!defined('KSAOS')) {
    exit('Error.');
}

class Hmacsha{
    /**
     * hmac_sha1算法
     * @param $str string 源串
     * @param $key string 密钥
     * @return string 签名值
     */
    public static function Sha1($str='', $key='') {
        if (function_exists('hash_hmac')) {
            return base64_encode(hash_hmac("sha1", $str, $key, true));
        } else {
            $blocksize = 64;
            $hashfunc = 'sha1';
            if (strlen($key) > $blocksize) {
                $key = pack('H*', $hashfunc($key));
            }
            $key = str_pad($key, $blocksize, chr(0x00));
            $ipad = str_repeat(chr(0x36), $blocksize);
            $opad = str_repeat(chr(0x5c), $blocksize);
            $hmac = pack(
                'H*', $hashfunc(
                    ($key ^ $opad) . pack(
                        'H*', $hashfunc(
                            ($key ^ $ipad) . $str
                        )
                    )
                )
            );
            return base64_encode($hmac);
        }
    }

}