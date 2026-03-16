<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$username = $_SESSION['username'];
$employee_id = $_SESSION['employee_id'] ?? '';

// Check for meetings starting within the next 2 minutes to be safe with polling intervals
// We want to find meetings where the user is the creator OR an attendee
$query = "SELECT TOP 1 ps.meeting_id, ps.meeting_name, ps.start_time, ps.meeting_date
          FROM LRNPH_OJT.db_datareader.AP_Meetings ps
          LEFT JOIN LRNPH_OJT.db_datareader.AP_Attendees pma ON ps.meeting_id = pma.meeting_id
          WHERE ps.meeting_date = CAST(GETDATE() AS DATE)
          AND ps.start_time > CAST(GETDATE() AS TIME)
          AND ps.start_time <= DATEADD(minute, 2, CAST(GETDATE() AS TIME))
          AND (ps.facilitator = ? OR pma.employee_id = ?)
          ORDER BY ps.start_time ASC";

$params = array($username, $employee_id);
$stmt = sqlsrv_query($conn, $query, $params);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Query error', 'errors' => sqlsrv_errors()]);
    exit;
}

if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $startTime = $row['start_time']->format('h:i A');
    $title = $row['meeting_name'];
    $meetingId = $row['meeting_id'];

    echo json_encode([
        'success' => true,
        'has_meeting' => true,
        'meeting' => [
            'id' => $meetingId,
            'title' => $title,
            'time' => $startTime
        ]
    ]);
} else {
    echo json_encode(['success' => true, 'has_meeting' => false]);
}
?>