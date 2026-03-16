<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No ID provided']);
    exit;
}

$scheduleId = $_GET['id'];

if (isset($conn)) {
    // 1. Get Meeting Details (AP_Meetings)
    // Join with Categories for display name
    // Join with Master List for facilitator name
    $sql = "SELECT DISTINCT ps.*, 
            ml.FirstName as CreatorFirst, ml.LastName as CreatorLast,
            cat.category_name
            FROM LRNPH_OJT.db_datareader.AP_Meetings ps
            LEFT JOIN LRNPH_E.dbo.lrn_master_list ml ON ps.facilitator = ml.BiometricsID COLLATE DATABASE_DEFAULT
            LEFT JOIN LRNPH_OJT.db_datareader.AP_Categories cat ON ps.category_id = cat.category_id
            WHERE ps.meeting_id = ?";

    $stmt = sqlsrv_query($conn, $sql, array($scheduleId));

    if ($stmt === false) {
        $errs = sqlsrv_errors();
        $msg = "SQL Query Failed: ";
        if ($errs) {
            foreach ($errs as $e)
                $msg .= "[" . $e['code'] . "] " . $e['message'] . " ";
        }
        echo json_encode(['error' => $msg]);
        exit;
    }
    if (!sqlsrv_has_rows($stmt)) {
        echo json_encode(['error' => 'Schedule not found']);
        exit;
    }

    $schedule = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    // Format Times/Dates
    $schedule['date_formatted'] = $schedule['meeting_date']->format('F j, Y');
    $schedule['start_formatted'] = $schedule['start_time']->format('h:i A');
    $schedule['end_formatted'] = $schedule['end_time'] ? $schedule['end_time']->format('h:i A') : '';

    $creatorName = $schedule['CreatorFirst'] . ' ' . $schedule['CreatorLast'];
    if (empty(trim($creatorName)))
        $creatorName = $schedule['facilitator'];
    $schedule['creator_name'] = $creatorName;

    // Handle Custom Category Display Logic
    if (empty($schedule['category_name']) && !empty($schedule['custom_category_text'])) {
        $schedule['category_name'] = $schedule['custom_category_text'] . ' (Custom)';
    }


    // 2. Get All Attendees (AP_Attendees)
    // We select attendee_name directly mostly, but if employee_id exists, we could cross check.
    // The table stores the name snapshot, so just use that.
    $attSql = "SELECT attendee_name, department 
               FROM LRNPH_OJT.db_datareader.AP_Attendees
               WHERE meeting_id = ?
               ORDER BY attendee_name ASC";
    $attStmt = sqlsrv_query($conn, $attSql, array($scheduleId));

    $attendees = [];
    if ($attStmt) {
        while ($row = sqlsrv_fetch_array($attStmt, SQLSRV_FETCH_ASSOC)) {
            $attendees[] = [
                'name' => $row['attendee_name'],
                'dept' => $row['department']
            ];
        }
    }

    // 3. Get Agendas (AP_MeetingAgenda)
    $agendaSql = "SELECT topic FROM LRNPH_OJT.db_datareader.AP_MeetingAgenda WHERE meeting_id = ?";
    $agendaStmt = sqlsrv_query($conn, $agendaSql, array($scheduleId));

    $agendas = [];
    if ($agendaStmt) {
        while ($row = sqlsrv_fetch_array($agendaStmt, SQLSRV_FETCH_ASSOC)) {
            $agendas[] = $row['topic'];
        }
    }

    echo json_encode([
        'schedule' => $schedule,
        'attendees' => $attendees,
        'agendas' => $agendas
    ]);
} else {
    echo json_encode(['error' => 'Database connection failed']);
}
?>