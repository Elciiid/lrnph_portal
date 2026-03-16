<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $meetingId = $_POST['id'] ?? 0;
    $attendees = $_POST['attendees'] ?? []; // Array of IDs
    // Custom Attendees (Names)
    $customAttendees = $_POST['custom_attendees'] ?? [];

    $currentUserId = $_SESSION['username'] ?? '';

    if (!$meetingId) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    // 1. Verify Creator (Facilitator)
    $checkSql = "SELECT facilitator FROM LRNPH_OJT.db_datareader.AP_Meetings WHERE meeting_id = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, array($meetingId));

    if ($checkStmt === false || !sqlsrv_has_rows($checkStmt)) {
        echo json_encode(['success' => false, 'message' => 'Meeting not found']);
        exit;
    }

    $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    if ($row['facilitator'] != $currentUserId) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Only the facilitator can manage attendees']);
        exit;
    }

    // 2. Manage Attendees
    // Strategy: We will sync the list.
    // For simplicity in this "Add/Edit" context, let's FULLY REPLACE the list for this meeting.

    sqlsrv_begin_transaction($conn);
    try {
        // Remove existing attendees
        $delSql = "DELETE FROM LRNPH_OJT.db_datareader.AP_Attendees WHERE meeting_id = ?";
        $delStmt = sqlsrv_query($conn, $delSql, array($meetingId));
        if ($delStmt === false)
            throw new Exception("Error clearing attendees");

        // Prepare Insert SQL
        $insSql = "INSERT INTO LRNPH_OJT.db_datareader.AP_Attendees (meeting_id, employee_id, attendee_name, department) VALUES (?, ?, ?, ?)";

        // A. Process Registered Employees
        if (is_array($attendees)) {
            foreach ($attendees as $empId) {
                // Sanitize
                $empId = preg_replace('/[^a-zA-Z0-9]/', '', $empId);
                if (!empty($empId)) {
                    // Fetch Name & Dept
                    $detSql = "SELECT FirstName, LastName, Department FROM LRNPH_E.dbo.lrn_master_list WHERE BiometricsID = ?";
                    $detStmt = sqlsrv_query($conn, $detSql, array($empId));

                    if ($detStmt && $emp = sqlsrv_fetch_array($detStmt, SQLSRV_FETCH_ASSOC)) {
                        $name = $emp['FirstName'] . ' ' . $emp['LastName'];
                        $dept = $emp['Department'];

                        $res = sqlsrv_query($conn, $insSql, array($meetingId, $empId, $name, $dept));
                        if ($res === false)
                            throw new Exception("Error inserting attendee: $empId");
                    }
                }
            }
        }

        // B. Process Custom Attendees
        if (is_array($customAttendees)) {
            foreach ($customAttendees as $cName) {
                $cName = trim($cName);
                if (!empty($cName)) {
                    // Insert custom with NULL ID
                    $res = sqlsrv_query($conn, $insSql, array($meetingId, null, $cName, 'External Guest'));
                    if ($res === false)
                        throw new Exception("Error inserting custom attendee: $cName");
                }
            }
        }

        sqlsrv_commit($conn);
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>