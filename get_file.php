<?php
require_once 'classes/EmailAPI.php';

$email_api = new EmailAPI();

if (isset($email_number)) {
    try {
        $result = $email_api->install_attachments($email_number);
        header('Content-Type: application/json');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } catch (Exception $e) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(["error" => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT |JSON_UNESCAPED_SLASHES);
    }
} else {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Email number is required"], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}