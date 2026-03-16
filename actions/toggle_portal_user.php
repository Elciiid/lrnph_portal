<?php
session_start();
require_once '../includes/db.php';

// Check for permission again
if (!isset($_SESSION['department']) || !preg_match('/IT|INFORMATION TECHNOLOGY/i', $_SESSION['department'])) {
    header("Location: ../admin.php?page=dashboard&error=access_denied");
    exit();
}

if (isset($_GET['username']) && isset($_GET['status'])) {
    $username = $_GET['username'];
    $newStatus = $_GET['status'] === 'active' ? 'active' : 'inactive';

    // Prevent deactivating your own account
    if ($username === $_SESSION['username'] && $newStatus === 'inactive') {
        header("Location: ../admin.php?page=user_management&error=cannot_deactivate_self");
        exit();
    }

    $sql = "UPDATE LRNPH.dbo.lrnph_users SET status = ? WHERE username = ?";
    $stmt = sqlsrv_query($conn, $sql, array($newStatus, $username));

    if ($stmt) {
        header("Location: ../admin.php?page=user_management&success=status_updated");
    } else {
        header("Location: ../admin.php?page=user_management&error=update_failed");
    }
    exit();
}
?>