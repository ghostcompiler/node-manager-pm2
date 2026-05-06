<?php
class Modules_NodeManagerPm2_Security
{
    public static function csrfToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['node_manager_pm2_csrf'])) {
            $_SESSION['node_manager_pm2_csrf'] = bin2hex(self::randomBytes(32));
        }

        return $_SESSION['node_manager_pm2_csrf'];
    }

    public static function assertCsrf()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        if ($method === 'GET' || $method === 'HEAD') {
            return;
        }

        $token = '';
        if (isset($_SERVER['HTTP_X_NODE_MANAGER_CSRF'])) {
            $token = $_SERVER['HTTP_X_NODE_MANAGER_CSRF'];
        } elseif (isset($_POST['_csrf'])) {
            $token = $_POST['_csrf'];
        }

        if (!$token || !hash_equals(self::csrfToken(), $token)) {
            throw new Modules_NodeManagerPm2_Exception('Invalid security token. Refresh Plesk and try again.');
        }
    }

    public static function randomBytes($length)
    {
        if (function_exists('random_bytes')) {
            return random_bytes($length);
        }

        return openssl_random_pseudo_bytes($length);
    }

    public static function token()
    {
        return bin2hex(self::randomBytes(24));
    }

    public static function hashToken($token)
    {
        return hash('sha256', (string) $token);
    }
}
