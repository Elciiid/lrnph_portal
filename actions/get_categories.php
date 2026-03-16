<?php
// actions/get_categories.php
require_once '../includes/db.php';

header('Content-Type: application/json');

if (isset($conn)) {
    $sql = "SELECT category_id, category_name FROM LRNPH_OJT.db_datareader.AP_Categories ORDER BY category_name ASC";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        echo json_encode(['error' => 'Query failed', 'details' => sqlsrv_errors()]);
        exit;
    }

    $categories = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
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