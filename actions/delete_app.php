<?php
require_once '../includes/db.php';
session_start();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $query = "DELETE FROM portal_apps WHERE id = ?";
    $params = array($id);

    $stmt = sqlsrv_query($conn, $query, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

header("Location: ../admin.php?page=content");
exit;
?>