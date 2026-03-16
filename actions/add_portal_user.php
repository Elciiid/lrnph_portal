<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password']; // Plain text password from form

    if (empty($username) || empty($password)) {
        header("Location: ../admin.php?page=user_management&error=empty_fields");
        exit();
    }

    // Check if user already exists
    $checkSql = "SELECT username FROM LRNPH.dbo.lrnph_users WHERE username = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, array($username));

    if ($checkStmt === false) {
        // Debug: print_r(sqlsrv_errors(), true);
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
    $insertSql = "INSERT INTO LRNPH.dbo.lrnph_users (username, password) VALUES (?, ?)";
    $insertStmt = sqlsrv_query($conn, $insertSql, array($username, $hashedPassword));

    if ($insertStmt) {
        header("Location: ../admin.php?page=user_management&success=user_added");
    } else {
        // die(print_r(sqlsrv_errors(), true)); // Debug
        header("Location: ../admin.php?page=user_management&error=insert_failed");
    }
    exit();
}
?>