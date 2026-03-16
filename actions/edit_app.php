<?php
require_once '../includes/db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $url = $_POST['url'];
    $icon = $_POST['icon'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 99;

    require_once '../includes/reorder_apps.php';
    if ($sort_order != 99) {
        adjustAppSortOrder($conn, $sort_order, $id);
    }

    $sql = "UPDATE portal_apps SET name = ?, url = ?, icon = ?, is_active = ?, sort_order = ? WHERE id = ?";
    $params = array($name, $url, $icon, $is_active, $sort_order, $id);

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        header("Location: ../admin.php?page=content");
        exit();
    } else {
        echo "Error updating record: " . print_r(sqlsrv_errors(), true);
    }
}
?>