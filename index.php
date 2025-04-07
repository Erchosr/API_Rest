<?php
require_once 'classes/EmailAPI.php';
$email_api = new EmailAPI();
try {
    $emails = $email_api->get_all_emails();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des Emails</title>
</head>
<body>
    <h1>Liste des Emails</h1>
    <table border="1">
        <tr>
            <th>Subject</th>
            <th>From</th>
            <th>Date</th>
            <th>Readen</th>
            <th>Attachements</th>
            <th>To</th>
            <th>Actions</th>
        </tr>
        <?php if (!empty($emails)): ?>
            <?php foreach ($emails as $email): ?>
                <tr>
                    <td><?= htmlspecialchars($email["subject"]); ?></td>
                    <td><?= htmlspecialchars($email["from"]); ?></td>
                    <td><?= htmlspecialchars($email["date"]); ?></td>
                    <td><?= $email["readen"]; ?></td>
                    <td><?= $email["hasAttachments"] ? "📎" : ""; ?></td>
                    <td><?= implode(", ", $email["recipients"]); ?></td>
                    <td>
                        <a href="view/<?= $email["email_number"]; ?>">Voir</a>
                        <?php if ($email["hasAttachments"]): ?>
                            <a href="get/<?= $email["email_number"]; ?>">Télécharger les pièces jointes</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7">Aucun email trouvé.</td>
            </tr>
        <?php endif; ?>
    </table>
</body>
</html>