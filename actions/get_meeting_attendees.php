<?php
require_once __DIR__ . '/../includes/db.php';

$id = $_GET['id'] ?? 0;
$attendees = [];

if ($id) {
    // Simply fetch from prtl_AP_Attendees
    // We can return employee_id if present, or null
    $sql = "SELECT employee_id, attendee_name 
            FROM prtl_AP_Attendees
            WHERE meeting_id = ? 
            ORDER BY attendee_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute(array($id));
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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