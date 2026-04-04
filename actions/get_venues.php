<?php
// actions/get_venues.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (isset($conn)) {
    $sql = "SELECT venue_id, venue_name FROM \"prtl_AP_Venues\" WHERE is_active = 1 ORDER BY venue_name ASC";
    $stmt = $conn->query($sql);

    if (!$stmt) {
        echo json_encode(['error' => 'Query failed']);
        exit;
    }

    $venues = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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