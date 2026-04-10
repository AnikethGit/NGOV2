<?php
/**
 * PaytmChecksum.php
 * Official Paytm Checksum Utility
 * Source: https://github.com/paytm/Paytm_PHP_Checksum
 */

class PaytmChecksum {

    public static function encrypt($input, $key) {
        $key = html_entity_decode($key);
        if (strlen($key) != 16) return null;
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-128-CBC'));
        $encrypted = openssl_encrypt($input, 'AES-128-CBC', $key, 0, $iv);
        return base64_encode($iv . base64_decode($encrypted));
    }

    public static function decrypt($input, $key) {
        $key = html_entity_decode($key);
        if (strlen($key) != 16) return null;
        $input = base64_decode($input);
        $iv = substr($input, 0, openssl_cipher_iv_length('AES-128-CBC'));
        $encrypted = substr($input, openssl_cipher_iv_length('AES-128-CBC'));
        return openssl_decrypt(base64_encode($encrypted), 'AES-128-CBC', $key, 0, $iv);
    }

    public static function generateSignature($params, $key) {
        if (!is_array($params) && !is_string($params)) {
            throw new Exception("string or array expected, ".gettype($params)." given");
        }
        if (is_array($params)) {
            $params = self::getStringByParams($params);
        }
        return self::generateSignatureByString($params, $key);
    }

    public static function verifySignature($params, $key, $checksum) {
        if (!is_array($params) && !is_string($params)) {
            throw new Exception("string or array expected, ".gettype($params)." given");
        }
        if (is_array($params)) {
            $params = self::getStringByParams($params);
        }
        return self::verifySignatureByString($params, $key, $checksum);
    }

    private static function generateSignatureByString($params, $key) {
        $salt = self::generateRandomString(4);
        return self::calculateChecksum($params, $key, $salt);
    }

    private static function verifySignatureByString($params, $key, $checksum) {
        $paytm_hash  = base64_decode($checksum);
        $salt        = substr($paytm_hash, -4);
        return $paytm_hash == self::calculateHash($params, $salt) . $salt;
    }

    private static function generateRandomString($length) {
        $random = "";
        srand((float) microtime() * 1000000);
        $data  = "9876543210ZYXWVUTSRQPONMLKJIHGFEDCBAabcdefghijklmnopqrstuvwxyz!@#$&_";
        for ($i = 0; $i < $length; $i++) {
            $random .= substr($data, (rand() % (strlen($data))), 1);
        }
        return $random;
    }

    private static function getStringByParams($params) {
        ksort($params);
        $params = array_map(function ($value) {
            return (is_null($value)) ? "" : $value;
        }, $params);
        return implode("|", $params);
    }

    private static function calculateHash($params, $salt) {
        $finalString = $params . "|" . $salt;
        return hash("sha256", $finalString);
    }

    private static function calculateChecksum($params, $key, $salt) {
        $hashString = self::calculateHash($params, $salt);
        return self::encrypt($hashString . $salt, $key);
    }
}
