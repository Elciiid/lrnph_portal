<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$isIT = (isset($_SESSION['department']) && preg_match('/IT|INFORMATION TECHNOLOGY/i', $_SESSION['department']));
if (!$isIT) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required.']);
    exit;
}

$query = "DELETE FROM \"prtl_lrnph_users\" WHERE user_id = ?";
$stmt = $conn->prepare($query);

if ($stmt->execute(array($userId))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete user.']);
}
?>