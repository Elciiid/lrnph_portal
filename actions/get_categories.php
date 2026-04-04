<?php
// actions/get_categories.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (isset($conn)) {
    $sql = "SELECT category_id, category_name FROM prtl_AP_Categories ORDER BY category_name ASC";
    $stmt = $conn->query($sql);

    if ($stmt === false) {
        echo json_encode(['error' => 'Query failed', 'details' => ['error' => 'Database error occurred']]);
        exit;
    }

    $categories = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = [
            'id' => $row['category_id'],
            'name' => $row['category_name']
        ];
    }

    echo json_encode($categories);
} else {
    echo json_encode(['error' => 'Database connection failed']);
}
?>