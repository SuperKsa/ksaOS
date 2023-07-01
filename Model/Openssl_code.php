<?php
/**
 * OpenSSL加解密算法
 * -------------------------------
 * Author:  CR180 <cr180@cr180.com>
 * Date:    2020/2/24 23:19
 * Update:  2021/2/24 23:19
 *
 */

namespace ksaOS;


if(!defined('KSAOS')) {
    exit('Error.');
}

class Openssl_code {
    /**
     * 加密
     * @param $str
     *
     * @return string
     */
    public static function encode($str, $key='', $iv='')
    {
        if(!$iv){
            $iv = $key;
        }
        $str = preg_replace("/[\s]{2,}/", "", $str);
        $data = openssl_encrypt($str, "DES-CBC", $key, OPENSSL_RAW_DATA, $iv);
        $data = strtolower(bin2hex($data));
        return $data;
    }
    
    /**
     * 解密
     * @param $str
     * @param $isjson
     *
     * @return array|false|mixed|string
     */
    public static function decode($str, $isjson = 0, $key='', $iv='')
    {
        if(!$iv){
            $iv = $key;
        }
        $str = preg_replace("/[\s]{2,}/", "", $str);
        try {
            $dcode = openssl_decrypt(hex2bin($str), 'DES-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($isjson) {
                try{
                    $dcode = json_decode(urldecode($dcode), true);
                    if (!$dcode) {
                        $dcode = [];
                    }
                }catch (\Exception $e){
                
                }
            }
            return $dcode;
        } catch (\Throwable $th) {
            return "";
        }
    }
}