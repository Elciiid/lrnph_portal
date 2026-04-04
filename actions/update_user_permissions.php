<?php
require_once __DIR__ . '/../includes/db.php';

// Check for permission (IT Dept Only)
if (!isset($_SESSION['department']) || !preg_match('/IT|INFORMATION TECHNOLOGY/i', $_SESSION['department'])) {
    header("Location: ../admin.php?page=dashboard&error=access_denied");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $permissions = $_POST['permissions'] ?? [];

    // Mandatory permissions removed - now fully controlled by admin
    // if (!in_array('dashboard', $permissions)) ...

    $superAdmin = $_SESSION['username'];

    if (empty($username)) {
        header("Location: ../admin.php?page=user_management&error=invalid_user");
        exit();
    }

    // 1. Remove existing permissions for this user
    $deleteSql = "DELETE FROM \"prtl_portal_user_access\" WHERE username = ?";
    $deleteStmt = $conn->prepare($deleteSql);

    if (!$deleteStmt->execute(array($username))) {
        header("Location: ../admin.php?page=user_management&error=delete_failed");
        exit();
    }

    // 2. Insert new permissions
    if (!empty($permissions)) {
        // Updated column name from page_code to perm_key
        $insertSql = "INSERT INTO \"prtl_portal_user_access\" (username, perm_key, granted_by, date_granted) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
        $insertStmt = $conn->prepare($insertSql);

        foreach ($permissions as $code) {
            $insertStmt->execute(array($username, $code, $superAdmin));
        }
    }

    header("Location: ../admin.php?page=user_management&success=perms_updated");
    exit();
}
?>