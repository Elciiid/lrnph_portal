<?php
session_start();
require_once '../includes/db.php';

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = $_GET['id'];
    $status = $_GET['status']; // 1 or 0

    $sql = "UPDATE portal_announcements SET is_active = ? WHERE id = ?";
    $params = [$status, $id];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

header("Location: ../admin.php?page=announcements");
exit();
?>