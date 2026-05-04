<?php
// actions/check_availability.php
require_once __DIR__ . '/../includes/db.php';
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
    $facSql = "SELECT ps.meeting_id FROM \"prtl_AP_Meetings\" ps
               WHERE ps.meeting_date = ?::date 
               AND (
                   ps.facilitator = ? 
                   OR EXISTS (SELECT 1 FROM \"prtl_AP_Attendees\" att WHERE att.meeting_id = ps.meeting_id AND att.employee_id = ?)
               )
               AND ps.start_time < ?::time 
               AND ps.end_time > ?::time";

    $facStmt = $conn->prepare($facSql);
    $facStmt->execute([$date, $currentUserId, $currentUserId, $endTime, $startTime]);
    if ($facStmt && $facStmt->fetch()) {
        $facilitatorBooked = true;
    }

    // 2. Check Unavailable Venues
    $unavailableVenues = [];
    $venueSql = "SELECT DISTINCT venue FROM \"prtl_AP_Meetings\" 
                 WHERE meeting_date = ?::date 
                 AND venue IS NOT NULL 
                 AND venue != 'Online'
                 AND start_time < ?::time 
                 AND end_time > ?::time";

    $venueStmt = $conn->prepare($venueSql);
    $venueStmt->execute([$date, $endTime, $startTime]);
    if ($venueStmt) {
        while ($row = $venueStmt->fetch(PDO::FETCH_ASSOC)) {
            $unavailableVenues[] = $row['venue'];
        }
    }

    // 3. Check All Booked Employees for this slot
    $bookedEmployees = [];
    $empSql = "SELECT facilitator as employee_id FROM \"prtl_AP_Meetings\" 
               WHERE meeting_date = ?::date AND start_time < ?::time AND end_time > ?::time
               UNION
               SELECT att.employee_id FROM \"prtl_AP_Attendees\" att
               JOIN \"prtl_AP_Meetings\" mt ON att.meeting_id = mt.meeting_id
               WHERE mt.meeting_date = ?::date AND mt.start_time < ?::time AND mt.end_time > ?::time 
               AND att.employee_id IS NOT NULL";

    $empStmt = $conn->prepare($empSql);
    $empStmt->execute([$date, $endTime, $startTime, $date, $endTime, $startTime]);
    if ($empStmt) {
        while ($row = $empStmt->fetch(PDO::FETCH_ASSOC)) {
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