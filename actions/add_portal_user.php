<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password']; // Plain text password from form

    if (empty($username) || empty($password)) {
        header("Location: ../admin.php?page=user_management&error=empty_fields");
        exit();
    }

    // Check if user already exists
    $checkSql = "SELECT username FROM prtl_lrnph_users WHERE username = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute(array($username));

    if ($checkStmt === false) {
        // Debug: print_r(['error' => 'Database error occurred'], true);
        header("Location: ../admin.php?page=user_management&error=db_error");
        exit();
    }

    if (sqlsrv_has_rows($checkStmt)) {
        header("Location: ../admin.php?page=user_management&error=user_exists");
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $insertSql = "INSERT INTO prtl_lrnph_users (username, password) VALUES (?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->execute(array($username, $hashedPassword));

    if ($insertStmt) {
        header("Location: ../admin.php?page=user_management&success=user_added");
    } else {
        // die(print_r(['error' => 'Database error occurred'], true)); // Debug
        header("Location: ../admin.php?page=user_management&error=insert_failed");
    }
    exit();
}
?>