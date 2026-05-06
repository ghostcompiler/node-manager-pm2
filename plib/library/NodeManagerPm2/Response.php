<?php
class Modules_NodeManagerPm2_Response
{
    public static function ok($controller, $data = [])
    {
        self::json($controller, [
            'success' => true,
            'data' => $data,
        ]);
    }

    public static function error($controller, $message, $code = 400, $details = [])
    {
        if (!headers_sent()) {
            http_response_code($code);
        }

        self::json($controller, [
            'success' => false,
            'error' => [
                'message' => $message,
                'details' => $details,
            ],
        ]);
    }

    private static function json($controller, $payload)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode($payload);
        exit;
    }
}
