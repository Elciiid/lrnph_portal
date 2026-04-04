<?php
require_once __DIR__ . '/../includes/db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['app_id']);
    $currentStatus = intval($_POST['current_status']);

    // Toggle status
    $newStatus = ($currentStatus == 1) ? 0 : 1;

    $query = "UPDATE \"prtl_portal_apps\" SET is_active = ? WHERE id = ?";
    $params = array($newStatus, $id);

    $stmt = $conn->prepare($query);
    $stmt->execute($params);

    if ($stmt === false) {
        die(print_r(['error' => 'Database error occurred'], true));
    }
}

header("Location: ../admin.php?page=content");
exit;
?>