<?php
require_once __DIR__ . '/../includes/db.php';

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = $_GET['id'];
    $status = $_GET['status']; // 1 or 0

    $sql = "UPDATE prtl_portal_announcements SET is_active = ? WHERE id = ?";
    $params = [$status, $id];

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt === false) {
        die(print_r(['error' => 'Database error occurred'], true));
    }
}

header("Location: ../admin.php?page=announcements");
exit();
?>