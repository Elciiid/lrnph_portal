<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$username = $_SESSION['username'];
$employee_id = $_SESSION['employee_id'] ?? '';

// Check for meetings starting within the next 2 minutes to be safe with polling intervals
// We want to find meetings where the user is the creator OR an attendee
$query = "SELECT ps.meeting_id, ps.meeting_name, ps.start_time, ps.meeting_date
          FROM \"prtl_AP_Meetings\" ps
          LEFT JOIN \"prtl_AP_Attendees\" pma ON ps.meeting_id = pma.meeting_id
          WHERE ps.meeting_date = CURRENT_DATE
          AND ps.start_time > CURRENT_TIME
          AND ps.start_time <= CURRENT_TIME + INTERVAL '2 minutes'
          AND (ps.facilitator = ? OR pma.employee_id = ?)
          ORDER BY ps.start_time ASC
          LIMIT 1";

$params = array($username, $employee_id);
$stmt = $conn->prepare($query);
    $stmt->execute($params);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Query error', 'errors' => ['error' => 'Database error occurred']]);
    exit;
}

if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $startTime = date('h:i A', strtotime($row['start_time']));
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