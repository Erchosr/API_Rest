<?php
require_once 'config.php';
require_once 'classes/EmailAPI.php';

$email_api = new EmailAPI();

// Définir les routes
$request_url = $_GET['url'];
$segments = explode('/', trim($request_url, '/'));

if (count($segments) >= 1) {
    $action = $segments[0];
    $param = isset($segments[1]) ? $segments[1] : null;

    switch ($action) {
        // Route pour afficher les détails d'un email spécifique ou lister tous les emails non lus
        case 'view':
            if ($param !== null) {
                $email_number = intval($param);
                include 'view_email.php';
            } else {
                // Route pour lister tous les emails non lus
                $unread_emails = $email_api->get_unread_emails();
                header('Content-Type: application/json');
                echo json_encode($unread_emails, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            break;

        // Route pour lister les emails avec pièces jointes
        case 'index':
            include 'index.php';
            break;

        // Route pour télécharger les pièces jointes d'un email spécifique
        case 'get':
            if ($param !== null) {
                $email_number = intval($param);
                include 'get_file.php';
            } else {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(["error" => "Email number is required"], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            break;

        // Route par défaut
        default:
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(["error" => "Not found"], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            break;
    }
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Not found"], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}