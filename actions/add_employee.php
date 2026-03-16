<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Permission check - Only IT can add employees (as per sidebar logic)
$isIT = (isset($_SESSION['department']) && preg_match('/IT|INFORMATION TECHNOLOGY/i', $_SESSION['department']));
if (!$isIT) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';
$empcode = $_POST['empcode'] ?? '';
$department = !empty($_POST['department']) ? $_POST['department'] : null;
$status = $_POST['status'] ?? 'active';

if (empty($username) || empty($password) || empty($role) || empty($empcode)) {
    echo json_encode(['success' => false, 'message' => 'Username, Password, Role, and Employee Code are required.']);
    exit;
}

// Check for existing username or empcode
$checkQuery = "SELECT COUNT(*) as count FROM LRNPH.dbo.lrnph_users WHERE username = ? OR empcode = ?";
$checkParams = array($username, $empcode);
$checkStmt = sqlsrv_query($conn, $checkQuery, $checkParams);

if ($checkStmt) {
    $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    if ($row['count'] > 0) {
        // Find which one exists specifically for a better error message
        $specificCheckQuery = "SELECT (SELECT COUNT(*) FROM LRNPH.dbo.lrnph_users WHERE username = ?) as user_exists,
                                     (SELECT COUNT(*) FROM LRNPH.dbo.lrnph_users WHERE empcode = ?) as code_exists";
        $specStmt = sqlsrv_query($conn, $specificCheckQuery, array($username, $empcode));
        $specRow = sqlsrv_fetch_array($specStmt, SQLSRV_FETCH_ASSOC);

        if ($specRow['user_exists'] > 0 && $specRow['code_exists'] > 0) {
            $msg = 'Both Username and Employee Code already exist.';
        } elseif ($specRow['user_exists'] > 0) {
            $msg = 'Username already exists.';
        } else {
            $msg = 'Employee Code is already registered.';
        }

        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
}

// Hash password for security
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Target database as requested: LRNPH_OJT
$query = "INSERT INTO LRNPH.dbo.lrnph_users (username, password, role, empcode, department, status, created_at, updated_at) 
          VALUES (?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";

$params = array($username, $hashedPassword, $role, $empcode, $department, $status);
$stmt = sqlsrv_query($conn, $query, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $errorMessage = 'Database insertion failed.';
    if ($errors) {
        $errorMessage = $errors[0]['message'];
    }
    echo json_encode(['success' => false, 'message' => $errorMessage]);
} else {
    echo json_encode(['success' => true, 'message' => 'Employee added successfully.']);
}
?>