<?php
require_once 'classes/EmailAPI.php';

$email_api = new EmailAPI();

if (isset($email_number)) {
    try {
        $email_details = $email_api->get_email_details($email_number);
        header('Content-Type: application/json');
        echo json_encode($email_details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } catch (Exception $e) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(["error" => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
} else {
    $unread_emails = $email_api->get_unread_emails();
    header('Content-Type: application/json'); 
    echo json_encode($unread_emails, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}