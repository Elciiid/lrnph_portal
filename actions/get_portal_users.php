<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Check permissions: IT OR has 'user_management' access
// Since this is an AJAX request, we re-verify permissions.
$hasAccess = false;
if (isset($_SESSION['username'])) {
    if (isset($_SESSION['department']) && preg_match('/IT|INFORMATION TECHNOLOGY/i', $_SESSION['department'])) {
        $hasAccess = true;
    } else {
        // Check DB for specific permission (e.g. 'new_employee_access' or similar if defined, or just if they are an active user for now?)
        // The user said "gave an employee the new employee access". Assuming this maps to a perm_key.
        // Let's query if they have ANY admin-level access or specific key.
        // For now, let's allow if they are authenticated, assuming the UI protects the link.
        // BETTER: Check if they have 'user_management' or 'new_employee' permission.
        // Check DB for specific permission
        // We check for 'user_management' (legacy), 'admin_tab' (legacy), 'admin' (general), or 'new_employee' (specific)
        // Adjust these keys based on what you actually save in the DB for this feature.
        $permSql = "SELECT COUNT(*) as cnt FROM prtl_portal_user_access WHERE username = ? AND perm_key IN ('user_management', 'admin_tab', 'new_employee', 'admin_access')";
        $permStmt = $conn->prepare($permSql);
    $permStmt->execute(array($_SESSION['username']));
        if ($permStmt && $row = $permStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['cnt'] > 0) {
                $hasAccess = true;
            }
        }
    }
}

if (!$hasAccess) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access to registry.']);
    exit;
}

$query = "SELECT u.user_id, u.username, u.role, u.empcode, u.department, u.status, u.created_at,
                 ml.FirstName, ml.LastName, ml.PositionTitle
          FROM prtl_lrnph_users u
          LEFT JOIN prtl_lrn_master_list ml ON u.username = ml.BiometricsID
          WHERE ml.isActive = 1
          ORDER BY u.created_at DESC";
$stmt = $conn->query($query);

$users = [];
if ($stmt) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['created_at']) {
            $row['created_at'] = $row['created_at']->format('Y-m-d H:i');
        }
        $users[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $users]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch users.']);
}
?>