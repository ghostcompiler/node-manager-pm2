<?php
class Modules_NodeManagerPm2_PublicWebhook
{
    public static function handle()
    {
        header('Content-Type: application/json');
        try {
            if (class_exists('pm_Context') && (!method_exists('pm_Context', 'isInitialized') || !pm_Context::isInitialized())) {
                pm_Context::init('node-manager-pm2');
            }
            Modules_NodeManagerPm2_Store::instance()->initialize();
            $appId = isset($_GET['app']) ? $_GET['app'] : '';
            $token = isset($_GET['token']) ? $_GET['token'] : '';
            if (!$appId || !$token) {
                throw new Modules_NodeManagerPm2_Exception('Webhook app and token are required.');
            }

            $result = (new Modules_NodeManagerPm2_DeployService())->deployByWebhook($appId, $token);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }
}
