<?php
session_start();
require_once '../includes/db.php';

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
    $deleteSql = "DELETE FROM portal_user_access WHERE username = ?";
    $deleteStmt = sqlsrv_query($conn, $deleteSql, array($username));

    if ($deleteStmt === false) {
        header("Location: ../admin.php?page=user_management&error=delete_failed");
        exit();
    }

    // 2. Insert new permissions
    if (!empty($permissions)) {
        // Updated column name from page_code to perm_key
        $insertSql = "INSERT INTO portal_user_access (username, perm_key, granted_by) VALUES (?, ?, ?)";

        foreach ($permissions as $code) {
            $insertStmt = sqlsrv_query($conn, $insertSql, array($username, $code, $superAdmin));
            if (!$insertStmt) {
                // Log error but continue
                // error_log(print_r(sqlsrv_errors(), true));
            }
        }
    }

    header("Location: ../admin.php?page=user_management&success=perms_updated");
    exit();
}
?>