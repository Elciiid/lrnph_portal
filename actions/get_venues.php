<?php
// actions/get_venues.php
require_once '../includes/db.php';

header('Content-Type: application/json');

if (isset($conn)) {
    $sql = "SELECT venue_id, venue_name FROM LRNPH_OJT.db_datareader.AP_Venues WHERE is_active = 1 ORDER BY venue_name ASC";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        echo json_encode(['error' => 'Query failed', 'details' => sqlsrv_errors()]);
        exit;
    }

    $venues = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $venues[] = [
            'id' => $row['venue_id'],
            'name' => $row['venue_name']
        ];
    }

    echo json_encode($venues);
} else {
    echo json_encode(['error' => 'Database connection failed']);
}
?>