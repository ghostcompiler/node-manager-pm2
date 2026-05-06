<?php
class Modules_NodeManagerPm2_Crypto
{
    public static function encrypt($plainText)
    {
        if ($plainText === null) {
            return null;
        }

        if (class_exists('pm_Crypt')) {
            return 'pmcrypt:' . pm_Crypt::encrypt((string) $plainText);
        }

        $key = self::key();
        $iv = Modules_NodeManagerPm2_Security::randomBytes(16);
        $cipher = openssl_encrypt((string) $plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new Modules_NodeManagerPm2_Exception('Unable to encrypt environment value.');
        }

        return 'local:' . base64_encode($iv . $cipher);
    }

    public static function decrypt($cipherText)
    {
        if ($cipherText === null || $cipherText === '') {
            return '';
        }

        if (strpos($cipherText, 'pmcrypt:') === 0 && class_exists('pm_Crypt')) {
            return pm_Crypt::decrypt(substr($cipherText, 8));
        }

        if (strpos($cipherText, 'local:') !== 0) {
            return $cipherText;
        }

        $raw = base64_decode(substr($cipherText, 6), true);
        if ($raw === false || strlen($raw) < 17) {
            throw new Modules_NodeManagerPm2_Exception('Stored encrypted value is invalid.');
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new Modules_NodeManagerPm2_Exception('Unable to decrypt environment value.');
        }

        return $plain;
    }

    private static function key()
    {
        $file = Modules_NodeManagerPm2_Config::varDir() . '/secret.key';
        if (!is_file($file)) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            file_put_contents($file, bin2hex(Modules_NodeManagerPm2_Security::randomBytes(32)));
            @chmod($file, 0600);
        }

        $key = trim(file_get_contents($file));
        return hash('sha256', $key, true);
    }
}
