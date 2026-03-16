<?php
require_once '../includes/db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['app_id']);
    $currentStatus = intval($_POST['current_status']);

    // Toggle status
    $newStatus = ($currentStatus == 1) ? 0 : 1;

    $query = "UPDATE portal_apps SET is_active = ? WHERE id = ?";
    $params = array($newStatus, $id);

    $stmt = sqlsrv_query($conn, $query, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

header("Location: ../admin.php?page=content");
exit;
?>