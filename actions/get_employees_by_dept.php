<?php
// actions/get_employees_by_dept.php
// Returns JSON list of employees for a given department

// Disable HTML error output
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['department'])) {
    echo json_encode(['error' => 'No department specified']);
    exit;
}

$dept = $_GET['department'];

if (isset($conn) && $conn) {
    // Query Master List for active employees in department
    // Sort by First Name
    $sql = "SELECT \"BiometricsID\" as id, \"FirstName\", \"LastName\" 
            FROM \"prtl_lrn_master_list\" 
            WHERE \"Department\" = ? AND \"isActive\" = true 
            ORDER BY \"FirstName\" ASC";

    $params = array($dept);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if (!$stmt) {
        echo json_encode(['error' => 'Query failed']);
        exit;
    }

    $employees = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format name: First Last
        $fullName = $row['FirstName'] . ' ' . $row['LastName'];

        $employees[] = [
            'id' => $row['id'],
            'name' => $fullName
        ];
    }

    echo json_encode($employees);
} else {
    echo json_encode(['error' => 'Database connection failed']);
}
?>