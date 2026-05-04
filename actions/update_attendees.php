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
    $checkSql = "SELECT facilitator FROM \"prtl_AP_Meetings\" WHERE meeting_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute(array($meetingId));

    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Meeting not found']);
        exit;
    }

    if ($row['facilitator'] != $currentUserId) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Only the facilitator can manage attendees']);
        exit;
    }

    // 2. Manage Attendees
    try {
        $conn->beginTransaction();

        // Remove existing attendees
        $delSql = "DELETE FROM \"prtl_AP_Attendees\" WHERE meeting_id = ?";
        $delStmt = $conn->prepare($delSql);
        $delStmt->execute(array($meetingId));

        // Prepare Insert SQL
        $insSql = "INSERT INTO \"prtl_AP_Attendees\" (meeting_id, employee_id, attendee_name, department) VALUES (?, ?, ?, ?)";
        $insStmt = $conn->prepare($insSql);

        // A. Process Registered Employees
        if (is_array($attendees)) {
            $detSql = "SELECT \"FirstName\", \"LastName\", \"Department\" FROM \"prtl_lrn_master_list\" WHERE \"BiometricsID\" = ?";
            $detStmt = $conn->prepare($detSql);

            foreach ($attendees as $empId) {
                // Sanitize
                $empId = preg_replace('/[^a-zA-Z0-9]/', '', $empId);
                if (!empty($empId)) {
                    // Fetch Name & Dept
                    $detStmt->execute(array($empId));
                    if ($emp = $detStmt->fetch(PDO::FETCH_ASSOC)) {
                        $name = $emp['FirstName'] . ' ' . $emp['LastName'];
                        $dept = $emp['Department'];

                        $insStmt->execute(array($meetingId, $empId, $name, $dept));
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
                    $insStmt->execute(array($meetingId, null, $cName, 'External Guest'));
                }
            }
        }

        $conn->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}