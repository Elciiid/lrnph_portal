<?php
// actions/delete_schedule.php
require_once __DIR__ . '/../includes/db.php';

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
    try {
        $conn->beginTransaction();

        // 1. Check Ownership
        $checkSql = "SELECT facilitator FROM \"prtl_AP_Meetings\" WHERE meeting_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute(array($planId));
        if ($checkStmt && $row = $checkStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['facilitator'] != $currentUserId) {
                throw new Exception("Unauthorized: You are not the facilitator.");
            }
        } else {
            throw new Exception("Meeting not found.");
        }

        // 2. Delete Agendas
        $delAg = "DELETE FROM \"prtl_AP_MeetingAgenda\" WHERE meeting_id = ?";
        $stmtAg = $conn->prepare($delAg);
        $stmtAg->execute(array($planId));

        // 3. Delete Attendees
        $delAtt = "DELETE FROM \"prtl_AP_Attendees\" WHERE meeting_id = ?";
        $stmtAtt = $conn->prepare($delAtt);
        $stmtAtt->execute(array($planId));

        // 4. Delete Meeting
        $query = "DELETE FROM \"prtl_AP_Meetings\" WHERE meeting_id = ? AND facilitator = ?";
        $params = array($planId, $currentUserId);
        $stmt = $conn->prepare($query);
        $stmt->execute($params);

        $conn->commit();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header("Location: ../admin.php?page=planner&msg=deleted");
        } else {
            echo json_encode(['success' => true]);
        }

    } catch (Exception $e) {
        $conn->rollBack();
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