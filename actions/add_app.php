<?php
require_once __DIR__ . '/../includes/db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $url = trim($_POST['url']);
    $icon = trim($_POST['icon']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $sort_order = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 99;

    require_once '../includes/reorder_apps.php';
    if ($sort_order != 99) {
        adjustAppSortOrder($conn, $sort_order);
    }

    if (empty($name)) {
        echo "<script>alert('App name is required'); window.history.back();</script>";
        exit;
    }

    $query = "INSERT INTO prtl_portal_apps (name, url, icon, is_active, sort_order) VALUES (?, ?, ?, ?, ?)";
    $params = array($name, $url, $icon, $isActive, $sort_order);

    $stmt = $conn->prepare($query);
    $stmt->execute($params);

    if ($stmt === false) {
        die(print_r(['error' => 'Database error occurred'], true));
    } else {
        header("Location: ../admin.php?page=content");
        exit;
    }
}
?>