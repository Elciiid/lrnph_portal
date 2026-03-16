<?php
require_once '../includes/db.php';

$id = $_GET['id'] ?? 0;
$attendees = [];

if ($id) {
    // Simply fetch from AP_Attendees
    // We can return employee_id if present, or null
    $sql = "SELECT employee_id, attendee_name 
            FROM LRNPH_OJT.db_datareader.AP_Attendees
            WHERE meeting_id = ? 
            ORDER BY attendee_name ASC";

    $stmt = sqlsrv_query($conn, $sql, array($id));
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $attendees[] = [
                'id' => $row['employee_id'], // Can be null for guests
                'name' => $row['attendee_name']
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($attendees);
?>