<?php
// actions/fetch_planner_events.php
error_reporting(0);
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['username'])) {
        throw new Exception('Unauthorized');
    }

    $currentUserId = $_SESSION['username'];
    $monthReq = $_GET['month'] ?? date('m');
    $yearReq = $_GET['year'] ?? date('Y');

    if (!is_numeric($monthReq) || !is_numeric($yearReq)) {
        throw new Exception('Invalid Date Parameters');
    }

    $month = (int) $monthReq;
    $year = (int) $yearReq;

    // Calculate Month Bounds
    $timestamp = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = (int) date('t', $timestamp);
    $monthName = date('F', $timestamp);

    $startDate = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    $endDate = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $daysInMonth);

    $monthEvents = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $monthEvents[$d] = [];
    }

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $evtQuery = "SELECT ps.meeting_id, ps.meeting_name, ps.venue, ps.meeting_date, ps.start_time, ps.end_time, ps.facilitator, ps.custom_category_text,
                     ml.FirstName as CreatorFirst, ml.LastName as CreatorLast,
                     cat.category_name,
                     (SELECT COUNT(*) FROM prtl_AP_Attendees att 
                      WHERE att.meeting_id = ps.meeting_id AND att.employee_id = ?) as IsAttendee
                 FROM prtl_AP_Meetings ps
                 LEFT JOIN prtl_lrn_master_list ml ON ps.facilitator = ml.BiometricsID COLLATE DATABASE_DEFAULT
                 LEFT JOIN prtl_AP_Categories cat ON ps.category_id = cat.category_id
                 WHERE ps.meeting_date >= ? AND ps.meeting_date <= ?
                 ORDER BY ps.start_time ASC";

    $evtStmt = $conn->prepare($evtQuery);
    $evtStmt->execute(array($currentUserId, $startDate, $endDate));

    if ($evtStmt === false) {
        $errs = ['error' => 'Database error occurred'];
        $msg = "SQL Query Failed: ";
        if ($errs) {
            foreach ($errs as $e)
                $msg .= "[" . $e['code'] . "] " . $e['message'] . " ";
        }
        throw new Exception($msg);
    }

    while ($row = $evtStmt->fetch(PDO::FETCH_ASSOC)) {
        $mDate = $row['meeting_date'];
        $dayNum = 0;
        if (is_a($mDate, 'DateTime')) {
            $dayNum = (int) $mDate->format('j');
        } else if ($mDate) {
            $dayNum = (int) date('j', strtotime($mDate));
        }

        if ($dayNum < 1)
            continue;

        $sTime = $row['start_time'];
        $eTime = $row['end_time'];
        $startTimeStr = is_a($sTime, 'DateTime') ? $sTime->format('h:i A') : ($sTime ? date('h:i A', strtotime($sTime)) : '');
        $endTimeStr = is_a($eTime, 'DateTime') ? $eTime->format('h:i A') : ($eTime ? date('h:i A', strtotime($eTime)) : '');
        $timestampSort = is_a($sTime, 'DateTime') ? $sTime->getTimestamp() : ($sTime ? strtotime($sTime) : 0);

        $creatorName = trim(($row['CreatorFirst'] ?? '') . ' ' . ($row['CreatorLast'] ?? ''));
        if (empty($creatorName))
            $creatorName = $row['facilitator'];

        $isMine = ($row['facilitator'] == $currentUserId || ($row['IsAttendee'] ?? 0) > 0);

        $evt = [
            'id' => $row['meeting_id'],
            'title' => $row['meeting_name'],
            'time' => $startTimeStr,
            'to_time' => $endTimeStr,
            'timestamp' => $timestampSort,
            'venue' => $row['venue'] ?? '',
            'category' => ($row['category_name'] ?: ($row['custom_category_text'] ?? '')) ?: 'Uncategorized',
            'created_by' => $row['facilitator'],
            'creator_name' => $creatorName,
            'is_mine' => $isMine
        ];

        if ($dayNum <= $daysInMonth) {
            $monthEvents[$dayNum][] = $evt;
        }
    }

    $prevTS = mktime(0, 0, 0, $month - 1, 1, $year);
    $nextTS = mktime(0, 0, 0, $month + 1, 1, $year);

    echo json_encode([
        'monthName' => $monthName,
        'year' => $year,
        'daysInMonth' => $daysInMonth,
        'dayOfWeek' => (int) date('w', $timestamp),
        'events' => $monthEvents,
        'prev' => ['m' => (int) date('m', $prevTS), 'y' => (int) date('Y', $prevTS)],
        'next' => ['m' => (int) date('m', $nextTS), 'y' => (int) date('Y', $nextTS)],
        'currentUserId' => $currentUserId
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>