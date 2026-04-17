<?php
/**
 * PaytmChecksum.php
 * Updated Paytm Checksum Utility — compatible with new Paytm host:
 *   securestage.paytmpayments.com (staging)
 *   secure.paytmpayments.com (production)
 *
 * The old AES-128-CBC based library produced ~108-char hashes with
 * special characters, rejected by the new Paytm gateway.
 * This version uses HMAC-SHA256 which the new host accepts.
 */

class PaytmChecksum {

    /**
     * Generate checksum hash for sending to Paytm gateway.
     *
     * @param array|string $params  — associative array of Paytm parameters
     * @param string       $key     — your Paytm Merchant Key
     * @return string               — the CHECKSUMHASH value
     */
    public static function generateSignature($params, $key) {
        if (!is_array($params) && !is_string($params)) {
            throw new Exception("string or array expected, " . gettype($params) . " given");
        }
        if (is_array($params)) {
            $params = self::getStringByParams($params);
        }
        return self::generateSignatureByString($params, $key);
    }

    /**
     * Verify checksum hash received from Paytm callback.
     *
     * @param array|string $params   — associative array of response parameters (exclude CHECKSUMHASH)
     * @param string       $key      — your Paytm Merchant Key
     * @param string       $checksum — CHECKSUMHASH received from Paytm
     * @return bool
     */
    public static function verifySignature($params, $key, $checksum) {
        if (!is_array($params) && !is_string($params)) {
            throw new Exception("string or array expected, " . gettype($params) . " given");
        }
        if (is_array($params)) {
            $params = self::getStringByParams($params);
        }
        return self::verifySignatureByString($params, $key, $checksum);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function generateSignatureByString($params, $key) {
        $salt = self::generateRandomString(4);
        return self::calculateChecksum($params, $key, $salt);
    }

    private static function verifySignatureByString($params, $key, $checksum) {
        // Decode: last 4 chars of the hex hash are the salt
        $salt = substr($checksum, -4);
        $expectedChecksum = self::calculateChecksum($params, $key, $salt);
        return hash_equals($expectedChecksum, $checksum);
    }

    /**
     * Generate a cryptographically random 4-character salt.
     */
    private static function generateRandomString($length) {
        $chars  = '9876543210ZYXWVUTSRQPONMLKJIHGFEDCBAabcdefghijklmnopqrstuvwxyz';
        $random = '';
        $max    = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $random .= $chars[random_int(0, $max)];
        }
        return $random;
    }

    /**
     * Sort params alphabetically by key and pipe-join values.
     */
    private static function getStringByParams(array $params) {
        ksort($params);
        $params = array_map(function ($value) {
            return is_null($value) ? '' : $value;
        }, $params);
        return implode('|', $params);
    }

    /**
     * Compute SHA-256 HMAC of "params|salt" using the merchant key.
     * Returns hex string + salt appended (total ~68 chars, no spaces).
     */
    private static function calculateChecksum($params, $key, $salt) {
        $finalString = $params . '|' . $salt;
        $hash        = hash_hmac('sha256', $finalString, $key);
        return $hash . $salt;
    }
}
