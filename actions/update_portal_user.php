<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$isIT = (isset($_SESSION['department']) && preg_match('/IT|INFORMATION TECHNOLOGY/i', $_SESSION['department']));
if (!$isIT) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$userId = $_POST['user_id'] ?? '';
$username = $_POST['username'] ?? '';
$role = $_POST['role'] ?? '';
$empcode = $_POST['empcode'] ?? '';
$department = !empty($_POST['department']) ? $_POST['department'] : null;
$status = $_POST['status'] ?? 'active';
$password = $_POST['password'] ?? '';

if (empty($userId) || empty($username) || empty($role) || empty($empcode)) {
    echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
    exit;
}

// Check for existing username or empcode (excluding current user)
$checkQuery = "SELECT COUNT(*) as count FROM prtl_lrnph_users WHERE (username = ? OR empcode = ?) AND user_id != ?";
$checkParams = array($username, $empcode, $userId);
$checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute($checkParams);

if ($checkStmt) {
    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($row['count'] > 0) {
        // Find which one exists specifically
        $specificCheckQuery = "SELECT (SELECT COUNT(*) FROM prtl_lrnph_users WHERE username = ? AND user_id != ?) as user_exists,
                                     (SELECT COUNT(*) FROM prtl_lrnph_users WHERE empcode = ? AND user_id != ?) as code_exists";
        $specStmt = $conn->prepare($specificCheckQuery);
    $specStmt->execute(array($username, $userId, $empcode, $userId));
        $specRow = $specStmt->fetch(PDO::FETCH_ASSOC);

        if ($specRow['user_exists'] > 0 && $specRow['code_exists'] > 0) {
            $msg = 'Both Username and Employee Code are already in use by another account.';
        } elseif ($specRow['user_exists'] > 0) {
            $msg = 'Username is already taken.';
        } else {
            $msg = 'Employee Code is already registered to another account.';
        }

        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
}

if (!empty($password)) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $query = "UPDATE prtl_lrnph_users 
              SET username = ?, role = ?, empcode = ?, department = ?, status = ?, password = ?, updated_at = GETDATE() 
              WHERE user_id = ?";
    $params = array($username, $role, $empcode, $department, $status, $hashedPassword, $userId);
} else {
    $query = "UPDATE prtl_lrnph_users 
              SET username = ?, role = ?, empcode = ?, department = ?, status = ?, updated_at = GETDATE() 
              WHERE user_id = ?";
    $params = array($username, $role, $empcode, $department, $status, $userId);
}

$stmt = $conn->prepare($query);
    $stmt->execute($params);

if ($stmt) {
    echo json_encode(['success' => true]);
} else {
    $errors = ['error' => 'Database error occurred'];
    echo json_encode(['success' => false, 'message' => $errors[0]['message'] ?? 'Update failed.']);
}
?>