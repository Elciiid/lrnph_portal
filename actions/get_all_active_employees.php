<?php
// actions/get_all_active_employees.php
// Returns JSON list of ALL active employees

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (isset($conn) && $conn) {
    $search = $_GET['search'] ?? '';

    $sql = "SELECT BiometricsID as id, FirstName, LastName, Department
            FROM prtl_lrn_master_list 
            WHERE isActive = 1 ";

    $params = [];
    if (!empty($search)) {
        $sql .= "AND (FirstName LIKE ? OR LastName LIKE ? OR (FirstName + ' ' + LastName) LIKE ?) ";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam, $searchParam];
    }

    $sql .= "ORDER BY FirstName ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt === false) {
        echo json_encode(['error' => 'Query failed', 'details' => ['error' => 'Database error occurred']]);
        exit;
    }

    $employees = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fullName = $row['FirstName'] . ' ' . $row['LastName'];
        $employees[] = [
            'id' => $row['id'],
            'name' => $fullName,
            'department' => $row['Department']
        ];
    }

    echo json_encode($employees);
} else {
    echo json_encode(['error' => 'Database connection failed']);
}
?>