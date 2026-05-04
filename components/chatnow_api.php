<?php
// Standalone API for ChatNow (Admin Portal Version)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/photo_helper.php';

// Ensure authenticated
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('Asia/Manila');

// helper for photos
function resolve_photo_url($employeeId)
{
    return getEmployeePhotoUrl($employeeId);
}

$username = $_SESSION['username'];
$employeeId = $_SESSION['employee_id'] ?? '';
$fullname = preg_replace('/\s+/', ' ', trim($_SESSION['fullname'] ?? $username));

// $conn is already available from db.php

// Helpers
function json_out($arr, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

// Cleanup old story views (older than 24 hours)
$conn->query("DELETE FROM \"prtl_StoryViews\" WHERE last_viewed_at < CURRENT_TIMESTAMP - INTERVAL '24 hours'");

// Cleanup old call signals (older than 1 hour)
$conn->query("DELETE FROM \"prtl_CallSignals\" WHERE updated_at < CURRENT_TIMESTAMP - INTERVAL '1 hour'");

// --- API Router ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'start_call') {
    $caller = $_SESSION['fullname'];
    $receiver = $_POST['receiver'];

    // End any existing calls for caller/receiver
    $updSql = "UPDATE \"prtl_CallSignals\" SET status = 'ended', updated_at = CURRENT_TIMESTAMP WHERE (caller_name = ? OR receiver_name = ?) AND status IN ('ringing', 'accepted')";
    $updStmt = $conn->prepare($updSql);
    $updStmt->execute([$caller, $caller]);
    $updStmt->execute([$receiver, $receiver]);

    $sql = "INSERT INTO \"prtl_CallSignals\" (caller_name, receiver_name, status) VALUES (?, ?, 'ringing')";
    $stmt = $conn->prepare($sql);

    if ($stmt->execute([$caller, $receiver])) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to start call']);
    }
    exit;
}

if ($action === 'check_call_status') {
    $me = $_SESSION['fullname'];

    // Check if someone is calling ME (Incoming)
    $incomingSql = "SELECT * FROM \"prtl_CallSignals\" WHERE receiver_name = ? AND status = 'ringing' ORDER BY created_at DESC LIMIT 1";
    $incStmt = $conn->prepare($incomingSql);
    $incStmt->execute([$me]);
    if ($incStmt && $row = $incStmt->fetch(PDO::FETCH_ASSOC)) {
        // Fetch caller's EmployeeID for photo
        $callerName = $row['caller_name'];
        $hqSql = "SELECT \"EmployeeID\" FROM \"prtl_lrn_master_list\" WHERE REPLACE(REPLACE(\"FirstName\" || ' ' || \"LastName\", '  ', ' '), '  ', ' ') = ? LIMIT 1";
        $hqStmt = $conn->prepare($hqSql);
        $hqStmt->execute([$callerName]);
        $hr = $hqStmt->fetch(PDO::FETCH_ASSOC);
        $eid = $hr ? $hr['EmployeeID'] : '';

        echo json_encode(['ok' => true, 'type' => 'incoming', 'caller' => $callerName, 'eid' => $eid, 'call_id' => $row['id']]);
        exit;
    }

    // Check status of my OUTGOING call
    $outgoingSql = "SELECT * FROM \"prtl_CallSignals\" WHERE caller_name = ? AND status IN ('ringing', 'accepted', 'declined', 'ended') ORDER BY created_at DESC LIMIT 1";
    $outStmt = $conn->prepare($outgoingSql);
    $outStmt->execute([$me]);
    if ($outStmt && $row = $outStmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['ok' => true, 'type' => 'outgoing', 'status' => $row['status'], 'call_id' => $row['id']]);
        exit;
    }

    echo json_encode(['ok' => true, 'type' => 'none']);
    exit;
}

if ($action === 'handle_call_action') {
    $me = $_SESSION['fullname'];
    $callId = $_POST['call_id'];
    $newStatus = $_POST['status']; // accepted, declined, ended

    $sql = "UPDATE \"prtl_CallSignals\" SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt->execute([$newStatus, $callId])) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to update call']);
    }
    exit;
}

// Presence Update
function set_user_presence($conn, string $name, string $status = 'online'): void
{
    if ($name === '')
        return;
    $sql = "INSERT INTO \"prtl_UserPresence\" (username, status, last_seen) VALUES (?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (username) DO UPDATE SET status = EXCLUDED.status, last_seen = CURRENT_TIMESTAMP";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$name, $status]);
}

if (!empty($fullname)) {
    set_user_presence($conn, $fullname, 'online');
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete_story') {
        $sql = "UPDATE \"prtl_UserNotes\" SET image_path = NULL, updated_at = CURRENT_TIMESTAMP WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$fullname]);
        json_out(['ok' => true]);
    }

    // --- Mark Story as Seen ---
    if ($action === 'mark_story_seen') {
        $owner = trim($_POST['owner'] ?? '');
        if ($owner !== '') {
            $sql = "INSERT INTO \"prtl_StoryViews\" (viewer_name, story_owner_name, last_viewed_at) VALUES (?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT (viewer_name, story_owner_name) DO UPDATE SET last_viewed_at = CURRENT_TIMESTAMP";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$fullname, $owner]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false]);
    }

    // --- Save Note / Story ---
    if ($action === 'save_note') {
        $noteText = trim($_POST['note_text'] ?? '');
        $imagePath = null;

        // Handle Image Upload for Story
        if (isset($_FILES['note_image']) && $_FILES['note_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/chat_attachments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['note_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $fileName = 'story_' . time() . '_' . uniqid() . '.' . $ext;
                $targetFile = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['note_image']['tmp_name'], $targetFile)) {
                    $imagePath = 'uploads/chat_attachments/' . $fileName;
                }
            }
        }

        if ($noteText === '' && $imagePath === null) {
            $sql = "DELETE FROM \"prtl_UserNotes\" WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$fullname]);
        } else {
            if ($imagePath === null) {
                $sql = "INSERT INTO \"prtl_UserNotes\" (username, note_text, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
                        ON CONFLICT (username) DO UPDATE SET note_text = EXCLUDED.note_text, updated_at = CURRENT_TIMESTAMP";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$fullname, $noteText]);
            } else {
                $sql = "INSERT INTO \"prtl_UserNotes\" (username, note_text, image_path, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                        ON CONFLICT (username) DO UPDATE SET note_text = EXCLUDED.note_text, image_path = EXCLUDED.image_path, updated_at = CURRENT_TIMESTAMP";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$fullname, $noteText, $imagePath]);
            }
        }
        json_out(['ok' => true]);
    }

    // --- Create Group ---
    if ($action === 'create_group') {
        $groupName = trim($_POST['group_name'] ?? '');
        $creator = $fullname;
        $membersRaw = $_POST['members'] ?? '[]';
        $members = json_decode($membersRaw, true);
        if (!is_array($members))
            $members = [];

        if ($groupName === '' || $creator === '') {
            json_out(['ok' => false, 'error' => 'Group name and creator required.'], 400);
        }

        try {
            $conn->beginTransaction();

            $sql = "INSERT INTO \"prtl_Conversations\" (name, created_by) VALUES (?, ?) RETURNING id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$groupName, $creator]);
            $cid = $stmt->fetchColumn();

            if (!$cid) {
                throw new Exception('Insert conversation failed');
            }

            // Add creator
            $insPartSql = "INSERT INTO \"prtl_ConversationParticipants\" (conversation_id, participant_name) VALUES (?, ?)";
            $insPartStmt = $conn->prepare($insPartSql);
            $insPartStmt->execute([$cid, $creator]);

            // Add members
            $seen = [$creator => true];
            foreach ($members as $m) {
                $n = trim($m['name'] ?? '');
                $b = trim($m['bio'] ?? '');
                if ($n !== '' && !isset($seen[$n])) {
                    $seen[$n] = true;
                    $insMemberSql = "INSERT INTO \"prtl_ConversationParticipants\" (conversation_id, participant_name, participant_bio) VALUES (?, ?, ?)";
                    $insMemberStmt = $conn->prepare($insMemberSql);
                    $insMemberStmt->execute([$cid, $n, $b ?: null]);
                }
            }

            $conn->commit();
            json_out(['ok' => true, 'cid' => $cid]);
        } catch (Exception $e) {
            $conn->rollBack();
            json_out(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // --- Search Users (for adding to group) ---
    // This is missing in the original msg.php snippet trace but usually required. 
    // Adapting to search prtl_lrn_master_list
    if ($action === 'search_users') {
        $q = trim($_POST['q'] ?? '');
        if (strlen($q) < 2)
            json_out(['ok' => true, 'results' => []]);

        $sql = "SELECT name, \"Department\", \"EmployeeID\" FROM (
                    SELECT (\"FirstName\" || ' ' || \"LastName\") as name, \"Department\", \"EmployeeID\", 1 as priority
                    FROM \"prtl_lrn_master_list\" 
                    WHERE (\"FirstName\" ILIKE ? OR \"LastName\" ILIKE ? OR (\"FirstName\" || ' ' || \"LastName\") ILIKE ?) AND \"isActive\" = true
                    UNION ALL
                    SELECT full_name as name, department as \"Department\", employee_id as \"EmployeeID\", 2 as priority
                    FROM \"prtl_app_ojt_employees\"
                    WHERE full_name ILIKE ?
                ) t
                ORDER BY priority, name LIMIT 15";
        $param = ["%$q%", "%$q%", "%$q%", "%$q%"];

        $stmt = $conn->prepare($sql);
        $stmt->execute($param);
        if ($stmt === false) {
            json_out(['ok' => false, 'error' => 'Search failed']);
        }

        $res = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[] = [
                'name' => preg_replace('/\s+/', ' ', trim($row['name'])),
                'dept' => $row['Department'] ?: 'OJT',
                'bio' => $row['EmployeeID']
            ];
        }
        json_out(['ok' => true, 'results' => $res]);
    }

    // --- Fetch Latest Groups ---
    if ($action === 'check_group_latest') {
        $me = $fullname;
        $sql = "
        SELECT c.id, c.name,
               lm.id AS last_id,
               lm.sender AS last_sender,
               lm.message AS last_message,
               lm.attachment_name AS last_attachment,
               lm.sent_at AS last_time
        FROM \"prtl_Conversations\" c
        INNER JOIN \"prtl_ConversationParticipants\" p
            ON p.conversation_id = c.id AND p.participant_name = ?
        LEFT JOIN LATERAL (
            SELECT m.id, m.sender, m.message, m.attachment_name, m.sent_at
            FROM \"prtl_Messages\" m
            WHERE m.conversation_id = c.id
            ORDER BY m.sent_at DESC, m.id DESC
            LIMIT 1
        ) lm ON TRUE
        ORDER BY c.name ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$me]);
        $rows = [];
        if ($stmt) {
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    'id' => (int) $r['id'],
                    'name' => $r['name'],
                    'last_message' => $r['last_message'] ?? '',
                    'last_sender' => $r['last_sender'] ?? '',
                    'last_time' => $r['last_time'] ? date('Y-m-d H:i', strtotime($r['last_time'])) : ''
                ];
            }
        }
        json_out(['ok' => true, 'list' => $rows]);
    }

    // --- Fetch Latest DMs ---
    if ($action === 'check_dm_latest') {
        $me = $fullname;
        $sql = "
        WITH convs AS (
            SELECT
              CASE WHEN m.sender <= COALESCE(m.receiver,'') THEN m.sender ELSE COALESCE(m.receiver,'') END AS u1,
              CASE WHEN m.sender <= COALESCE(m.receiver,'') THEN COALESCE(m.receiver,'') ELSE m.sender END AS u2
            FROM \"prtl_Messages\" m
            WHERE m.receiver IS NOT NULL
              AND (m.sender = ? OR m.receiver = ?)
            GROUP BY u1, u2
        ),
        lastmsg AS (
            SELECT
              c.u1, c.u2,
              m.id, m.sender, m.message, m.sent_at,
              ROW_NUMBER() OVER (PARTITION BY c.u1, c.u2 ORDER BY m.sent_at DESC, m.id DESC) AS rn
            FROM convs c
            JOIN \"prtl_Messages\" m
              ON ((m.sender = c.u1 AND m.receiver = c.u2) OR (m.sender = c.u2 AND m.receiver = c.u1))
        )
        SELECT u1, u2, id, sender, message, sent_at
        FROM lastmsg
        WHERE rn = 1
        ORDER BY sent_at DESC, id DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$me, $me]);
        $rows = [];
        if ($stmt) {
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $other = ($r['u1'] === $me) ? ($r['u2'] ?? '') : ($r['u1'] ?? '');
                if ($other === '')
                    continue;
                $rows[] = [
                    'other' => $other,
                    'last_message' => $r['message'] ?? '',
                    'last_sender' => $r['sender'] ?? '',
                    'last_time' => $r['sent_at'] ? date('Y-m-d H:i', strtotime($r['sent_at'])) : ''
                ];
            }
        }
        json_out(['ok' => true, 'list' => $rows]);
    }

    // --- Fetch Group Messages ---
    if ($action === 'fetch_group_messages') {
        $cid = (int) ($_POST['cid'] ?? 0);
        $rows = [];
        if ($cid > 0) {
            $sql = "SELECT m.id, m.sender, m.message, m.attachment_path, m.attachment_name, m.sent_at, m.reply_to_id, 
                           e.\"EmployeeID\",
                           r.sender as reply_sender, r.message as reply_text
                    FROM \"prtl_Messages\" m
                    LEFT JOIN \"prtl_lrn_master_list\" e ON REPLACE(REPLACE(\"FirstName\" || ' ' || \"LastName\", '  ', ' '), '  ', ' ') = REPLACE(REPLACE(m.sender, '  ', ' '), '  ', ' ')
                    LEFT JOIN \"prtl_Messages\" r ON m.reply_to_id = r.id
                    WHERE m.conversation_id = ? ORDER BY m.sent_at ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$cid]);
            if ($stmt) {
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rows[] = [
                        'id' => (int) $r['id'],
                        'sender' => $r['sender'],
                        'message' => $r['message'],
                        'attachment_path' => $r['attachment_path'],
                        'attachment_name' => $r['attachment_name'],
                        'sent_at' => $r['sent_at'] ? date('Y-m-d H:i', strtotime($r['sent_at'])) : '',
                        'photo' => resolve_photo_url($r['EmployeeID'] ?? ''),
                        'reply_to' => $r['reply_to_id'],
                        'reply_sender' => $r['reply_sender'],
                        'reply_text' => $r['reply_text']
                    ];
                }
            }
        }
        json_out(['ok' => true, 'messages' => $rows]);
    }

    // --- Send Group Message ---
    if ($action === 'send_group_message') {
        $cid = (int) ($_POST['cid'] ?? 0);
        $msg = trim($_POST['message'] ?? '');
        $attachmentPath = null;
        $attachmentName = null;

        $fileKey = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $fileKey = 'attachment';
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileKey = 'image';
        }

        if ($fileKey) {
            $absTargetDir = __DIR__ . '/../assets/uploads/';
            if (!file_exists($absTargetDir)) {
                @mkdir($absTargetDir, 0755, true);
            }

            $originalName = basename($_FILES[$fileKey]['name']);
            $fname = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $originalName);
            $destPath = $absTargetDir . $fname;

            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $destPath)) {
                $attachmentPath = 'uploads/' . $fname;
                $attachmentName = $originalName;
            }
        }

        if ($cid > 0 && ($msg !== '' || $attachmentPath)) {
            $replyTo = !empty($_POST['reply_to']) ? (int) $_POST['reply_to'] : null;
            $sql = "INSERT INTO \"prtl_Messages\" (conversation_id, sender, message, attachment_path, attachment_name, reply_to_id, sent_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$cid, $fullname, $msg, $attachmentPath, $attachmentName, $replyTo])) {
                json_out(['ok' => true]);
            } else {
                json_out(['ok' => false, 'error' => 'DB Insert Failed']);
            }
        }
        json_out(['ok' => false, 'error' => 'Invalid message']);
    }

    // --- Fetch DM Messages ---
    if ($action === 'fetch_dm_messages') {
        $other = trim($_POST['other'] ?? '');
        $rows = [];
        if ($other !== '') {
            $sql = "SELECT m.id, m.sender, m.message, m.attachment_path, m.attachment_name, m.sent_at, m.reply_to_id,
                           e.\"EmployeeID\",
                           r.sender as reply_sender, r.message as reply_text
                    FROM \"prtl_Messages\" m
                    LEFT JOIN \"prtl_lrn_master_list\" e ON REPLACE(REPLACE(\"FirstName\" || ' ' || \"LastName\", '  ', ' '), '  ', ' ') = REPLACE(REPLACE(m.sender, '  ', ' '), '  ', ' ')
                    LEFT JOIN \"prtl_Messages\" r ON m.reply_to_id = r.id
                    WHERE (REPLACE(REPLACE(m.sender, '  ', ' '), '  ', ' ') = ? AND REPLACE(REPLACE(m.receiver, '  ', ' '), '  ', ' ') = ?) 
                       OR (REPLACE(REPLACE(m.sender, '  ', ' '), '  ', ' ') = ? AND REPLACE(REPLACE(m.receiver, '  ', ' '), '  ', ' ') = ?) 
                    ORDER BY m.sent_at ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$fullname, $other, $other, $fullname]);

            if ($stmt) {
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rows[] = [
                        'id' => (int) $r['id'],
                        'sender' => $r['sender'],
                        'message' => $r['message'],
                        'attachment_path' => $r['attachment_path'],
                        'attachment_name' => $r['attachment_name'],
                        'sent_at' => $r['sent_at'] ? date('Y-m-d H:i', strtotime($r['sent_at'])) : '',
                        'photo' => resolve_photo_url($r['EmployeeID'] ?? ''),
                        'reply_to' => $r['reply_to_id'],
                        'reply_sender' => $r['reply_sender'],
                        'reply_text' => $r['reply_text']
                    ];
                }
            }
        }
        json_out(['ok' => true, 'messages' => $rows]);
    }

    // --- Update GC Settings ---
    if ($action === 'update_gc') {
        $cid = (int) ($_POST['cid'] ?? 0);
        $newName = trim($_POST['name'] ?? '');
        $photoPath = null;

        if ($cid <= 0)
            json_out(['ok' => false, 'error' => 'Invalid ID']);

        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $absTargetDir = __DIR__ . '/../assets/uploads/group_photos/';
            if (!file_exists($absTargetDir))
                @mkdir($absTargetDir, 0755, true);

            $fname = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($_FILES['photo']['name']));
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $absTargetDir . $fname)) {
                $photoPath = 'uploads/group_photos/' . $fname;
            }
        }

        if ($newName !== '') {
            $sql = "UPDATE \"prtl_Conversations\" SET name = ? " . ($photoPath ? ", photo_path = ?" : "") . " WHERE id = ?";
            $params = $photoPath ? [$newName, $photoPath, $cid] : [$newName, $cid];
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Name required']);
    }

    // --- Add GC Member ---
    if ($action === 'add_gc_member') {
        $cid = (int) ($_POST['cid'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($cid > 0 && $name !== '') {
            // Check if already member
            $ckSql = "SELECT 1 FROM \"prtl_ConversationParticipants\" WHERE conversation_id=? AND REPLACE(REPLACE(participant_name, '  ', ' '), '  ', ' ') = REPLACE(REPLACE(?, '  ', ' '), '  ', ' ')";
            $ckStmt = $conn->prepare($ckSql);
            $ckStmt->execute([$cid, $name]);
            if ($ckStmt->fetch()) {
                json_out(['ok' => false, 'error' => 'Already a member']);
            }
            $insSql = "INSERT INTO \"prtl_ConversationParticipants\" (conversation_id, participant_name) VALUES (?, ?)";
            $insStmt = $conn->prepare($insSql);
            $insStmt->execute([$cid, $name]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid request']);
    }

    // --- Remove GC Member ---
    if ($action === 'remove_gc_member') {
        $cid = (int) ($_POST['cid'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($cid > 0 && $name !== '') {
            $delSql = "DELETE FROM \"prtl_ConversationParticipants\" WHERE conversation_id=? AND REPLACE(REPLACE(participant_name, '  ', ' '), '  ', ' ') = REPLACE(REPLACE(?, '  ', ' '), '  ', ' ')";
            $delStmt = $conn->prepare($delSql);
            $delStmt->execute([$cid, $name]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid request']);
    }

    // --- Send DM ---
    if ($action === 'send_dm') {
        $to = trim($_POST['to'] ?? '');
        $msg = trim($_POST['message'] ?? '');
        $attachmentPath = null;
        $attachmentName = null;

        $fileKey = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $fileKey = 'attachment';
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileKey = 'image';
        }

        if ($fileKey) {
            $absTargetDir = __DIR__ . '/../assets/uploads/';
            if (!file_exists($absTargetDir)) {
                @mkdir($absTargetDir, 0755, true);
            }

            $originalName = basename($_FILES[$fileKey]['name']);
            $fname = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $originalName);
            $destPath = $absTargetDir . $fname;

            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $destPath)) {
                $attachmentPath = 'uploads/' . $fname;
                $attachmentName = $originalName;
            }
        }

        if ($to !== '' && ($msg !== '' || $attachmentPath)) {
            $replyTo = !empty($_POST['reply_to']) ? (int) $_POST['reply_to'] : null;
            $sql = "INSERT INTO \"prtl_Messages\" (sender, receiver, message, attachment_path, attachment_name, reply_to_id, sent_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$fullname, $to, $msg, $attachmentPath, $attachmentName, $replyTo])) {
                json_out(['ok' => true]);
            } else {
                json_out(['ok' => false, 'error' => 'DB Insert Failed']);
            }
        }
        json_out(['ok' => false, 'error' => 'Invalid DM']);
    }

    // --- React Message (Frontend-only approach) ---
    if ($action === 'react_message') {
        $msgId = (int) ($_POST['msg_id'] ?? 0);
        $emoji = trim($_POST['emoji'] ?? '');

        if ($msgId > 0) {
            $selSql = "SELECT message FROM \"prtl_Messages\" WHERE id = ?";
            $selStmt = $conn->prepare($selSql);
            $selStmt->execute([$msgId]);
            if ($row = $selStmt->fetch(PDO::FETCH_ASSOC)) {
                $msgText = $row['message'] ?? '';

                $currentReaction = '';
                if (preg_match('/ \[React:(.*?)\]$/s', $msgText, $matches)) {
                    $currentReaction = $matches[1];
                }

                $msgText = preg_replace('/ \[React:.*?\]$/s', '', $msgText);

                if ($emoji !== '' && $emoji !== $currentReaction) {
                    $msgText .= ' [React:' . $emoji . ']';
                }

                $updSql = "UPDATE \"prtl_Messages\" SET message = ? WHERE id = ?";
                $updStmt = $conn->prepare($updSql);
                $updStmt->execute([$msgText, $msgId]);
                json_out(['ok' => true]);
            }
        }
        json_out(['ok' => false, 'error' => 'Invalid message ID']);
    }

    // --- React Story ---
    if ($action === 'react_story') {
        $owner = trim($_POST['owner'] ?? '');
        $emoji = trim($_POST['emoji'] ?? '');
        if ($owner !== '') {
            $sql = "INSERT INTO \"prtl_StoryViews\" (viewer_name, story_owner_name, reaction, last_viewed_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT (viewer_name, story_owner_name) DO UPDATE SET reaction = EXCLUDED.reaction, last_viewed_at = CURRENT_TIMESTAMP";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$fullname, $owner, $emoji]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid owner']);
    }

    // --- Delete Message ---
    if ($action === 'delete_message') {
        $msgId = (int) ($_POST['msg_id'] ?? 0);
        if ($msgId > 0) {
            $sql = "DELETE FROM \"prtl_Messages\" WHERE id = ? AND REPLACE(sender, '  ', ' ') = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$msgId, $fullname]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid message ID']);
    }

    // --- Delete Conversation ---
    if ($action === 'delete_conversation') {
        $cid = (int) ($_POST['cid'] ?? 0);
        $other = trim($_POST['other'] ?? '');

        if ($cid > 0) {
            $conn->prepare("DELETE FROM \"prtl_Messages\" WHERE conversation_id = ?")->execute([$cid]);
            $conn->prepare("DELETE FROM \"prtl_ConversationParticipants\" WHERE conversation_id = ?")->execute([$cid]);
            $conn->prepare("DELETE FROM \"prtl_Conversations\" WHERE id = ?")->execute([$cid]);
            json_out(['ok' => true]);
        } elseif ($other !== '') {
            $sql = "DELETE FROM \"prtl_Messages\" WHERE (REPLACE(sender, '  ', ' ') = ? AND REPLACE(receiver, '  ', ' ') = ?) OR (REPLACE(sender, '  ', ' ') = ? AND REPLACE(receiver, '  ', ' ') = ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$fullname, $other, $other, $fullname]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid conversation parameters']);
    }
}