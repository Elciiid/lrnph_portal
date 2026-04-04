<?php
require_once __DIR__ . '/../includes/db.php';

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
$checkQuery = "SELECT COUNT(*) as count FROM prtl_lrnph_users WHERE username = ? OR empcode = ?";
$checkParams = array($username, $empcode);
$checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute($checkParams);

if ($checkStmt) {
    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($row['count'] > 0) {
        // Find which one exists specifically for a better error message
        $specificCheckQuery = "SELECT (SELECT COUNT(*) FROM prtl_lrnph_users WHERE username = ?) as user_exists,
                                     (SELECT COUNT(*) FROM prtl_lrnph_users WHERE empcode = ?) as code_exists";
        $specStmt = $conn->prepare($specificCheckQuery);
    $specStmt->execute(array($username, $empcode));
        $specRow = $specStmt->fetch(PDO::FETCH_ASSOC);

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
$query = "INSERT INTO prtl_lrnph_users (username, password, role, empcode, department, status, created_at, updated_at) 
          VALUES (?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";

$params = array($username, $hashedPassword, $role, $empcode, $department, $status);
$stmt = $conn->prepare($query);
    $stmt->execute($params);

if ($stmt === false) {
    $errors = ['error' => 'Database error occurred'];
    $errorMessage = 'Database insertion failed.';
    if ($errors) {
        $errorMessage = $errors[0]['message'];
    }
    echo json_encode(['success' => false, 'message' => $errorMessage]);
} else {
    echo json_encode(['success' => true, 'message' => 'Employee added successfully.']);
}
?>