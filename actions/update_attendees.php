<?php
require_once __DIR__ . '/../includes/db.php';

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
    $checkSql = "SELECT facilitator FROM prtl_AP_Meetings WHERE meeting_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute(array($meetingId));

    if ($checkStmt === false || !sqlsrv_has_rows($checkStmt)) {
        echo json_encode(['success' => false, 'message' => 'Meeting not found']);
        exit;
    }

    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
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
        $delSql = "DELETE FROM prtl_AP_Attendees WHERE meeting_id = ?";
        $delStmt = $conn->prepare($delSql);
    $delStmt->execute(array($meetingId));
        if ($delStmt === false)
            throw new Exception("Error clearing attendees");

        // Prepare Insert SQL
        $insSql = "INSERT INTO prtl_AP_Attendees (meeting_id, employee_id, attendee_name, department) VALUES (?, ?, ?, ?)";

        // A. Process Registered Employees
        if (is_array($attendees)) {
            foreach ($attendees as $empId) {
                // Sanitize
                $empId = preg_replace('/[^a-zA-Z0-9]/', '', $empId);
                if (!empty($empId)) {
                    // Fetch Name & Dept
                    $detSql = "SELECT FirstName, LastName, Department FROM prtl_lrn_master_list WHERE BiometricsID = ?";
                    $detStmt = $conn->prepare($detSql);
    $detStmt->execute(array($empId));

                    if ($detStmt && $emp = $detStmt->fetch(PDO::FETCH_ASSOC)) {
                        $name = $emp['FirstName'] . ' ' . $emp['LastName'];
                        $dept = $emp['Department'];

                        $res = $conn->prepare($insSql);
    $res->execute(array($meetingId, $empId, $name, $dept));
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
                    $res = $conn->prepare($insSql);
    $res->execute(array($meetingId, null, $cName, 'External Guest'));
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