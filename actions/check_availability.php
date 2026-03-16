<?php
// actions/check_availability.php
require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currentUserId = $_SESSION['username'];
$date = $_GET['date'] ?? null;
$startTime = $_GET['start_time'] ?? null;
$endTime = $_GET['end_time'] ?? null;

if (!$date || !$startTime || !$endTime) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

if (isset($conn)) {
    // 1. Check Facilitator Availability (Are THEY already in a meeting, either as creator or attendee?)
    $facilitatorBooked = false;
    $facSql = "SELECT ps.meeting_id FROM LRNPH_OJT.db_datareader.AP_Meetings ps
               WHERE ps.meeting_date = ? 
               AND (
                   ps.facilitator = ? 
                   OR EXISTS (SELECT 1 FROM LRNPH_OJT.db_datareader.AP_Attendees att WHERE att.meeting_id = ps.meeting_id AND att.employee_id = ?)
               )
               AND ps.start_time < ? 
               AND ps.end_time > ?";

    $facStmt = sqlsrv_query($conn, $facSql, [$date, $currentUserId, $currentUserId, $endTime, $startTime]);
    if ($facStmt && sqlsrv_has_rows($facStmt)) {
        $facilitatorBooked = true;
    }

    // 2. Check Unavailable Venues
    $unavailableVenues = [];
    $venueSql = "SELECT DISTINCT venue FROM LRNPH_OJT.db_datareader.AP_Meetings 
                 WHERE meeting_date = ? 
                 AND venue IS NOT NULL 
                 AND venue != 'Online'
                 AND start_time < ? 
                 AND end_time > ?";

    $venueStmt = sqlsrv_query($conn, $venueSql, [$date, $endTime, $startTime]);
    if ($venueStmt) {
        while ($row = sqlsrv_fetch_array($venueStmt, SQLSRV_FETCH_ASSOC)) {
            $unavailableVenues[] = $row['venue'];
        }
    }

    // 3. Check All Booked Employees for this slot
    $bookedEmployees = [];
    $empSql = "SELECT facilitator as employee_id FROM LRNPH_OJT.db_datareader.AP_Meetings 
               WHERE meeting_date = ? AND start_time < ? AND end_time > ?
               UNION
               SELECT att.employee_id FROM LRNPH_OJT.db_datareader.AP_Attendees att
               JOIN LRNPH_OJT.db_datareader.AP_Meetings mt ON att.meeting_id = mt.meeting_id
               WHERE mt.meeting_date = ? AND mt.start_time < ? AND mt.end_time > ? 
               AND att.employee_id IS NOT NULL";

    $empStmt = sqlsrv_query($conn, $empSql, [$date, $endTime, $startTime, $date, $endTime, $startTime]);
    if ($empStmt) {
        while ($row = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC)) {
            $bookedEmployees[] = (string) $row['employee_id'];
        }
    }

    echo json_encode([
        'facilitator_booked' => $facilitatorBooked,
        'unavailable_venues' => $unavailableVenues,
        'booked_employees' => $bookedEmployees
    ]);
} else {
    echo json_encode(['error' => 'Database connection failed']);
}
?>