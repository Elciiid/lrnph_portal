<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect = $_POST['redirect'] ?? 'admin';

    // Map fields
    $title = $_POST['title'] ?? '';
    // 'account_name' DB column stores 'Subtitle' -- NO LONGER USED
    // 'description' -- NO LONGER USED

    $date = $_POST['date'] ?? date('Y-m-d');
    $time = $_POST['from_time'] ?? date('H:i:s');
    $platform = $_POST['to_time'] ?? ''; // platform -> To Time

    // New Fields
    $venue = $_POST['venue'];
    if ($venue === 'Custom' && !empty($_POST['custom_venue'])) {
        $venue = $_POST['custom_venue'];
    }

    $categoryId = $_POST['category_id'] ?? null;
    $customCategory = null;
    if ($categoryId === 'custom') {
        $categoryId = null; // Ensure ID is null if custom
        $customCategory = $_POST['custom_category_text'] ?? '';
    }

    // Agendas
    $agendas = $_POST['agenda'] ?? []; // Array of agendas

    // Image Logic
    $image = '';
    $imageOption = $_POST['image_option'] ?? 'url';

    if ($imageOption === 'file' && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExt = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $newFileName = uniqid('plan_', true) . '.' . $fileExt;
        $uploadFile = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadFile)) {
            // Save relative path for web access
            $image = 'assets/uploads/' . $newFileName;
        }
    } else {
        $image = $_POST['image_url'] ?? '';
        if (empty($image)) {
            $image = 'assets/lrn-logo.jpg';
        }
    }

    // Validate Time (Start < End)
    if (!empty($platform) && strtotime($time) >= strtotime($platform)) {
        if ($redirect === 'planner') {
            header("Location: ../admin.php?page=planner&error=invalid_time");
        } else {
            header("Location: ../admin.php?error=invalid_time");
        }
        exit();
    }

    // Use username (BiometricsID) as the identifier -> facilitator
    $createdBy = $_SESSION['username'] ?? null;

    // Check overlapping Logic (Venue or Creator availability)
    $overlapSql = "SELECT ps.meeting_id FROM LRNPH_OJT.db_datareader.AP_Meetings ps
                   WHERE ps.meeting_date = ? 
                   AND (
                       (ps.venue = ? AND ps.venue != 'Online') 
                       OR ps.facilitator = ?
                       OR EXISTS (SELECT 1 FROM LRNPH_OJT.db_datareader.AP_Attendees att WHERE att.meeting_id = ps.meeting_id AND att.employee_id = ?)
                   )
                   AND ps.start_time < ? 
                   AND ps.end_time > ?";

    $overlapParams = array($date, $venue, $createdBy, $createdBy, $platform, $time);

    $checkStmt = sqlsrv_query($conn, $overlapSql, $overlapParams);
    if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
        if ($redirect === 'planner') {
            header("Location: ../admin.php?page=planner&error=overlap");
        } else {
            header("Location: ../admin.php?error=overlap");
        }
        exit();
    }

    // Use transaction for integrity
    sqlsrv_begin_transaction($conn);

    try {
        // Insert Meeting (Updated Table & Logic)
        $sql = "INSERT INTO LRNPH_OJT.db_datareader.AP_Meetings 
                (meeting_name, venue, category_id, custom_category_text, meeting_date, start_time, end_time, image_url, facilitator, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE()); SELECT SCOPE_IDENTITY() AS id";

        $params = [$title, $venue, $categoryId, $customCategory, $date, $time, $platform, $image, $createdBy];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }

        sqlsrv_next_result($stmt);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $newMeetingId = $row['id'];

        // Insert Agendas (AP_MeetingAgenda)
        // Insert Agendas (AP_MeetingAgenda)
        if (!empty($agendas)) {
            $agendaSql = "INSERT INTO LRNPH_OJT.db_datareader.AP_MeetingAgenda (meeting_id, topic) VALUES (?, ?)";
            foreach ($agendas as $agendaText) {
                if (!empty(trim($agendaText))) {
                    $res = sqlsrv_query($conn, $agendaSql, array($newMeetingId, trim($agendaText)));
                    if ($res === false) {
                        throw new Exception("Error saving agenda");
                    }
                }
            }
        }

        // Insert Attendees (Updated Logic: fetch names, handle custom)
        $attendeeSql = "INSERT INTO LRNPH_OJT.db_datareader.AP_Attendees (meeting_id, employee_id, attendee_name, department) VALUES (?, ?, ?, ?)";

        // 1. Registered Employees (Look up details)
        $processedAttendees = [];
        if (isset($_POST['attendees']) && is_array($_POST['attendees'])) {
            // Use unique to avoid duplicates
            $uniqueAttendees = array_unique($_POST['attendees']);
            foreach ($uniqueAttendees as $empId) {
                if (empty($empId) || in_array($empId, $processedAttendees))
                    continue;

                // Fetch Details
                $detSql = "SELECT FirstName, LastName, Department FROM LRNPH_E.dbo.lrn_master_list WHERE BiometricsID = ?";
                $detStmt = sqlsrv_query($conn, $detSql, array($empId));
                if ($detStmt && $emp = sqlsrv_fetch_array($detStmt, SQLSRV_FETCH_ASSOC)) {
                    $name = $emp['FirstName'] . ' ' . $emp['LastName'];
                    $dept = $emp['Department'];

                    $res = sqlsrv_query($conn, $attendeeSql, array($newMeetingId, $empId, $name, $dept));
                    if ($res === false)
                        throw new Exception("Error saving attendee: $name");

                    $processedAttendees[] = $empId;
                }
            }
        }

        // 2. Custom Attendees (No ID, assume 'External Guest' dept or similar)
        if (isset($_POST['custom_attendees']) && is_array($_POST['custom_attendees'])) {
            foreach ($_POST['custom_attendees'] as $customName) {
                $customName = trim($customName);
                if (!empty($customName)) {
                    // Insert with NULL ID
                    $res = sqlsrv_query($conn, $attendeeSql, array($newMeetingId, null, $customName, 'External Guest'));
                    if ($res === false)
                        throw new Exception("Error adding custom attendee $customName");
                }
            }
        }

        sqlsrv_commit($conn);
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        die("Error processing request: " . $e->getMessage());
    }

    if ($redirect === 'planner') {
        header("Location: ../admin.php?page=planner");
    } else {
        header("Location: ../admin.php");
    }
    exit();
}
?>