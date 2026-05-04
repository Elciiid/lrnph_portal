<?php
require_once __DIR__ . '/../includes/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Optional: Check ownership or admin rights
    // $userId = $_SESSION['employee_id'] ?? '';
    // $sql = "DELETE FROM prtl_portal_announcements WHERE id = ? AND created_by = ?";
    // $params = [$id, $userId];

    // For now, allow any admin to delete
    $sql = "DELETE FROM \"prtl_portal_announcements\" WHERE id = ?";
    $params = [$id];

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt === false) {
        die(print_r(['error' => 'Database error occurred'], true));
    }
}

header("Location: ../admin.php?page=announcements");
exit();