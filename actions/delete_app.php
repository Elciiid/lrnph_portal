<?php
require_once __DIR__ . '/../includes/db.php';
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $query = "DELETE FROM \"prtl_portal_apps\" WHERE id = ?";
    $params = array($id);

    $stmt = $conn->prepare($query);
    $stmt->execute($params);

    if ($stmt === false) {
        die(print_r(['error' => 'Database error occurred'], true));
    }
}

header("Location: ../admin.php?page=content");
exit;
?>