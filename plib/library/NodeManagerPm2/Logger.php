<?php
class Modules_NodeManagerPm2_Logger
{
    public static function info($message, array $context = [])
    {
        self::write('info', $message, $context);
    }

    public static function warning($message, array $context = [])
    {
        self::write('warning', $message, $context);
    }

    public static function error($message, array $context = [])
    {
        self::write('error', $message, $context);
    }

    public static function path()
    {
        return Modules_NodeManagerPm2_Config::varDir() . '/logs/node-manager-pm2.log';
    }

    private static function write($level, $message, array $context)
    {
        $line = sprintf(
            "[%s] %s %s%s\n",
            gmdate('c'),
            strtoupper($level),
            self::clean($message),
            $context ? ' ' . self::json($context) : ''
        );

        try {
            $path = self::path();
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
            @chmod($path, 0640);
        } catch (Exception $e) {
        }

        error_log('[node-manager-pm2] ' . trim($line));
    }

    private static function json(array $context)
    {
        $encoded = json_encode(self::normalize($context), JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '{}' : $encoded;
    }

    private static function normalize($value)
    {
        if ($value instanceof Exception) {
            return [
                'class' => get_class($value),
                'message' => $value->getMessage(),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
                'trace' => self::shorten($value->getTraceAsString(), 6000),
            ];
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = self::normalize($item);
            }
            return $out;
        }

        if (is_object($value)) {
            return get_class($value);
        }

        if (is_string($value)) {
            return self::shorten($value, 6000);
        }

        return $value;
    }

    private static function clean($message)
    {
        return self::shorten(str_replace(["\r", "\n"], ' ', (string) $message), 2000);
    }

    private static function shorten($value, $limit)
    {
        $value = (string) $value;
        return strlen($value) > $limit ? substr($value, 0, $limit) . '...' : $value;
    }
}
