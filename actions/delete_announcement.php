<?php
session_start();
require_once '../includes/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Optional: Check ownership or admin rights
    // $userId = $_SESSION['employee_id'] ?? '';
    // $sql = "DELETE FROM portal_announcements WHERE id = ? AND created_by = ?";
    // $params = [$id, $userId];

    // For now, allow any admin to delete
    $sql = "DELETE FROM portal_announcements WHERE id = ?";
    $params = [$id];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

header("Location: ../admin.php?page=announcements");
exit();
?>