<?php
// actions/get_employees_by_dept.php
// Returns JSON list of employees for a given department

// Disable HTML error output
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['department'])) {
    echo json_encode(['error' => 'No department specified']);
    exit;
}

$dept = $_GET['department'];

if (isset($conn) && $conn) {
    // Query Master List for active employees in department
    // Sort by First Name
    $sql = "SELECT BiometricsID as id, FirstName, LastName 
            FROM dbo.lrn_master_list 
            WHERE Department = ? AND isActive = 1 
            ORDER BY FirstName ASC";

    $params = array($dept);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        // Return SQL error as JSON
        echo json_encode(['error' => 'Query failed', 'details' => sqlsrv_errors()]);
        exit;
    }

    $employees = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
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