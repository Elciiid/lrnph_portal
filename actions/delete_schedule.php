<?php
// actions/delete_schedule.php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['employee_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header("Location: ../login.php");
    } else {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    }
    exit();
}

$currentUserId = $_SESSION['username'];
$planId = $_POST['id'] ?? $_GET['id'] ?? null;

if (!$planId) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header("Location: ../admin.php?error=invalid_id");
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    }
    exit();
}

if (isset($conn)) {
    // Delete from AP_Meetings (Assuming cascading delete or manual delete of attendees/agenda if FK not set)
    // To be safe, let's delete children first if no cascade

    sqlsrv_begin_transaction($conn);
    try {
        // 1. Check Ownership
        $checkSql = "SELECT facilitator FROM LRNPH_OJT.db_datareader.AP_Meetings WHERE meeting_id = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, array($planId));
        if ($checkStmt && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['facilitator'] != $currentUserId) {
                throw new Exception("Unauthorized: You are not the facilitator.");
            }
        } else {
            throw new Exception("Meeting not found.");
        }

        // 2. Delete Agendas
        $delAg = "DELETE FROM LRNPH_OJT.db_datareader.AP_MeetingAgenda WHERE meeting_id = ?";
        $stmtAg = sqlsrv_query($conn, $delAg, array($planId));
        if ($stmtAg === false) {
            throw new Exception("Failed to delete meeting agenda items.");
        }

        // 3. Delete Attendees
        $delAtt = "DELETE FROM LRNPH_OJT.db_datareader.AP_Attendees WHERE meeting_id = ?";
        $stmtAtt = sqlsrv_query($conn, $delAtt, array($planId));
        if ($stmtAtt === false) {
            throw new Exception("Failed to delete meeting attendees.");
        }

        // 4. Delete Meeting
        $query = "DELETE FROM LRNPH_OJT.db_datareader.AP_Meetings WHERE meeting_id = ? AND facilitator = ?";
        $params = array($planId, $currentUserId);
        $stmt = sqlsrv_query($conn, $query, $params);

        if ($stmt === false) {
            throw new Exception("Failed to delete meeting record.");
        }

        sqlsrv_commit($conn);

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header("Location: ../admin.php?page=planner&msg=deleted");
        } else {
            echo json_encode(['success' => true]);
        }

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header("Location: ../admin.php?error=delete_failed");
        } else {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

} else {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header("Location: ../admin.php?error=conn_failed");
    } else {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    }
}
?>