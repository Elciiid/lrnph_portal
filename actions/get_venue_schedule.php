<?php
// actions/get_venue_schedule.php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');

if (isset($conn)) {
    // 1. Get all active venues
    $venues = [];
    $vSql = "SELECT venue_name FROM \"prtl_AP_Venues\" WHERE is_active = 1 ORDER BY venue_name ASC";
    $vStmt = $conn->query($vSql);
    if ($vStmt) {
        while ($vRow = $vStmt->fetch(PDO::FETCH_ASSOC)) {
            $venues[$vRow['venue_name']] = [];
        }
    }

    // 2. Get all meetings for this date
    $mSql = "SELECT ps.meeting_name, ps.venue, ps.start_time, ps.end_time, ps.facilitator,
                ml.\"FirstName\", ml.\"LastName\"
             FROM \"prtl_AP_Meetings\" ps
             LEFT JOIN \"prtl_lrn_master_list\" ml ON ps.facilitator = ml.\"BiometricsID\"
             WHERE ps.meeting_date = ?::date AND ps.venue IS NOT NULL AND ps.venue != 'Online'
             ORDER BY ps.start_time ASC";

    $mStmt = $conn->prepare($mSql);
    $mStmt->execute([$date]);
    if ($mStmt) {
        while ($row = $mStmt->fetch(PDO::FETCH_ASSOC)) {
            $v = $row['venue'];
            // If venue exists in our list (even if custom, but usually we filter by known venues)
            if (!isset($venues[$v])) {
                $venues[$v] = [];
            }

            $sTime = $row['start_time'];
            $eTime = $row['end_time'];

            $venues[$v][] = [
                'title' => $row['meeting_name'],
                'start' => $sTime ? date('h:i A', strtotime($sTime)) : '',
                'end' => $eTime ? date('h:i A', strtotime($eTime)) : '',
                'facilitator' => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')) ?: $row['facilitator']
            ];
        }
    }

    echo json_encode([
        'date' => date('M d, Y', strtotime($date)),
        'venues' => $venues
    ]);

} else {
    echo json_encode(['error' => 'Database connection failed']);
}