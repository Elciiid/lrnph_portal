<?php
require_once __DIR__ . '/../includes/db.php';

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

    $sql = "UPDATE \"prtl_lrnph_users\" SET status = ? WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt->execute(array($newStatus, $username))) {
        header("Location: ../admin.php?page=user_management&success=status_updated");
    } else {
        header("Location: ../admin.php?page=user_management&error=update_failed");
    }
    exit();
}
?>