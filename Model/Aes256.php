<?php
/**
 * AES256加解密算法
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

class Aes256{

    const ENCRYPT_METHOD = 'aes-256-gcm'; // type of encryption
    const ENCRYPT_LEN = 16; //openssl tag长度

    /**
     * 加密函数
     * @param string $message 需要加密的字符串
     * @param string $associatedData 附加内容
     * @param string $nonceStr 随机字符串
     * @param string $AesKEY 密钥
     * @return string
     * @throws \SodiumException
     */
    public static function encode(string $message , string $associatedData , string $nonceStr , string $AesKEY){

        // ext-sodium (default installed on >= PHP 7.2)
        if (function_exists('\sodium_crypto_aead_aes256gcm_encrypt')) {
            $message = \sodium_crypto_aead_aes256gcm_encrypt($message, $associatedData, $nonceStr, $AesKEY);
        // ext-libsodium (need install libsodium-php 1.x via pecl)
        }elseif (function_exists('\Sodium\crypto_aead_aes256gcm_encrypt')) {
            $message = \Sodium\crypto_aead_aes256gcm_encrypt($message, $associatedData, $nonceStr, $AesKEY);
        // openssl (PHP >= 7.1 support AEAD)
        }else{
            $message = self::openssl_encode($message, $associatedData, $nonceStr, $AesKEY);
        }

        $message = base64_encode($message);
        return $message;
    }

    /**
     * AES256解密
     * @param string $ciphertext 密文
     * @param string $associatedData 附加字符
     * @param string $nonceStr 随机字符
     * @param string $AesKEY 密钥
     * @return string
     * @throws \SodiumException
     */
    public static function decode(string $ciphertext, string $associatedData, string $nonceStr, string $AesKEY) {
        $ciphertext = base64_decode($ciphertext);

        // ext-sodium (default installed on >= PHP 7.2)
        if (function_exists('\sodium_crypto_aead_aes256gcm_decrypt')) {
            return sodium_crypto_aead_aes256gcm_decrypt($ciphertext, $associatedData, $nonceStr, $AesKEY);
        // ext-libsodium (need install libsodium-php 1.x via pecl)
        }elseif (function_exists('\Sodium\crypto_aead_aes256gcm_decrypt')) {
            return \Sodium\crypto_aead_aes256gcm_decrypt($ciphertext, $associatedData, $nonceStr, $AesKEY);

        // openssl (PHP >= 7.1 support AEAD)
        }else {
            return self::openssl_decode($ciphertext, $associatedData, $nonceStr, $AesKEY);
        }
    }

    public static function openssl_encode($message, $associatedData, $nonceStr, $AesKEY) {
        $tag = '';
        $str =  openssl_encrypt($message, self::ENCRYPT_METHOD, $AesKEY, OPENSSL_RAW_DATA, $nonceStr,  $tag, $associatedData, self::ENCRYPT_LEN).$tag;
        return $str;
    }

    public static function openssl_decode($ciphertext, $associatedData, $nonceStr, $AesKEY) {
        $Content = substr($ciphertext, 0, -self::ENCRYPT_LEN);
        $Tag = substr($ciphertext, -self::ENCRYPT_LEN);
        return openssl_decrypt($Content, self::ENCRYPT_METHOD, $AesKEY, OPENSSL_RAW_DATA, $nonceStr, $Tag, $associatedData);
    }

}