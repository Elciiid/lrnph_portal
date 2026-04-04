<?php
// Standalone API for ChatNow (Admin Portal Version)
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
    if (empty($employeeId)) {
        return "data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2394a3b8%22 style=%22background:%23e2e8f0; border-radius: 50%;%22%3e%3cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3e%3c/svg%3e";
    }
    return 'http://10.2.0.8/lrnph/emp_photos/' . $employeeId . '.jpg';
}

$username = $_SESSION['username'];
$employeeId = $_SESSION['employee_id'] ?? '';
$fullname = preg_replace('/\s+/', ' ', trim($_SESSION['fullname'] ?? $username));

// Database Connection to LRNPH_ITmanagement (Chat DB)
$serverName = "10.2.0.9";
$connectionOptions = [
    "Database" => "LRNPH_ITmanagement",
    "Uid" => "sa",
    "PWD" => "S3rverDB02lrn25",
    "CharacterSet" => "UTF-8"
];

$mssql = sqlsrv_connect($serverName, $connectionOptions);
if (!$mssql) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    exit;
}

// Helpers
function json_out($arr, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

// Ensure schema exists
$schemaSQL = "
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[prtl_UserPresence]') AND type in (N'U'))
BEGIN
    CREATE TABLE prtl_UserPresence (
        user_name NVARCHAR(150) NOT NULL PRIMARY KEY,
        status NVARCHAR(10) NOT NULL DEFAULT 'offline',
        last_seen DATETIME NOT NULL DEFAULT GETDATE()
    );
END;

IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[prtl_UserNotes]') AND type in (N'U'))
BEGIN
    CREATE TABLE prtl_UserNotes (
        username NVARCHAR(150) NOT NULL PRIMARY KEY,
        note_text NVARCHAR(60) NOT NULL,
        image_path NVARCHAR(255) NULL,
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END;
ELSE
BEGIN
    -- Ensure image_path column exists if table was already created
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[prtl_UserNotes]') AND name = 'image_path')
    BEGIN
        ALTER TABLE prtl_UserNotes ADD image_path NVARCHAR(255) NULL;
    END

    IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[prtl_StoryViews]') AND type in (N'U'))
    BEGIN
        CREATE TABLE prtl_StoryViews (
            viewer_name NVARCHAR(150) NOT NULL,
            story_owner_name NVARCHAR(150) NOT NULL,
            last_viewed_at DATETIME NOT NULL DEFAULT GETDATE(),
            reaction NVARCHAR(50) NULL,
            PRIMARY KEY (viewer_name, story_owner_name)
        );
    END
    ELSE
    BEGIN
        IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[prtl_StoryViews]') AND name = 'reaction')
        BEGIN
            ALTER TABLE prtl_StoryViews ADD reaction NVARCHAR(50) NULL;
        END
    END
    -- Ensure prtl_Messages table has reply_to_id
    IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[prtl_Messages]') AND type in (N'U'))
    BEGIN
        IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[prtl_Messages]') AND name = 'reply_to_id')
        BEGIN
            ALTER TABLE prtl_Messages ADD reply_to_id INT NULL;
        END
    END
END;
    IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[prtl_CallSignals]') AND type in (N'U'))
    BEGIN
        CREATE TABLE prtl_CallSignals (
            id INT IDENTITY(1,1) PRIMARY KEY,
            caller_name NVARCHAR(150) NOT NULL,
            receiver_name NVARCHAR(150) NOT NULL,
            status NVARCHAR(20) NOT NULL DEFAULT 'ringing', -- ringing, accepted, declined, ended
            created_at DATETIME NOT NULL DEFAULT GETDATE(),
            updated_at DATETIME NOT NULL DEFAULT GETDATE()
        );
    END
";

// Execute schema once to ensure required tables exist
sqlsrv_query($mssql, $schemaSQL);

// Cleanup old story views (older than 24 hours)
sqlsrv_query($mssql, "DELETE FROM prtl_StoryViews WHERE last_viewed_at < DATEADD(hour, -24, GETDATE())");

// Cleanup old call signals (older than 1 hour)
sqlsrv_query($mssql, "DELETE FROM prtl_CallSignals WHERE updated_at < DATEADD(hour, -1, GETDATE())");

// --- API Router ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'start_call') {
    $caller = $_SESSION['fullname'];
    $receiver = $_POST['receiver'];

    // End any existing calls for caller/receiver
    sqlsrv_query($mssql, "UPDATE prtl_CallSignals SET status = 'ended', updated_at = GETDATE() WHERE (caller_name = ? OR receiver_name = ?) AND status IN ('ringing', 'accepted')", [$caller, $caller]);
    sqlsrv_query($mssql, "UPDATE prtl_CallSignals SET status = 'ended', updated_at = GETDATE() WHERE (caller_name = ? OR receiver_name = ?) AND status IN ('ringing', 'accepted')", [$receiver, $receiver]);

    $sql = "INSERT INTO prtl_CallSignals (caller_name, receiver_name, status) VALUES (?, ?, 'ringing')";
    $res = sqlsrv_query($mssql, $sql, [$caller, $receiver]);

    if ($res) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to start call: ' . print_r(['error' => 'Database error occurred'], true)]);
    }
    exit;
}

if ($action === 'check_call_status') {
    $me = $_SESSION['fullname'];
    $cid = $_GET['cid'] ?? 0;

    // Check if someone is calling ME (Incoming)
    $incoming = sqlsrv_query($mssql, "SELECT TOP 1 * FROM prtl_CallSignals WHERE receiver_name = ? AND status = 'ringing' ORDER BY created_at DESC", [$me]);
    if ($incoming && $row = $incoming->fetch(PDO::FETCH_ASSOC)) {
        // Fetch caller's EmployeeID for photo
        $callerName = $row['caller_name'];
        $hq = sqlsrv_query($mssql, "SELECT TOP 1 EmployeeID FROM prtl_lrn_master_list WHERE REPLACE(REPLACE(LTRIM(RTRIM(FirstName)) + ' ' + LTRIM(RTRIM(LastName)), '  ', ' '), '  ', ' ') = ?", [$callerName]);
        $eid = ($hq && $hr = sqlsrv_fetch_array($hq)) ? $hr['EmployeeID'] : '';

        echo json_encode(['ok' => true, 'type' => 'incoming', 'caller' => $callerName, 'eid' => $eid, 'call_id' => $row['id']]);
        exit;
    }

    // Check status of my OUTGOING call
    $outgoing = sqlsrv_query($mssql, "SELECT TOP 1 * FROM prtl_CallSignals WHERE caller_name = ? AND status IN ('ringing', 'accepted', 'declined', 'ended') ORDER BY created_at DESC", [$me]);
    if ($outgoing && $row = $outgoing->fetch(PDO::FETCH_ASSOC)) {
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

    $sql = "UPDATE prtl_CallSignals SET status = ?, updated_at = GETDATE() WHERE id = ?";
    $res = sqlsrv_query($mssql, $sql, [$newStatus, $callId]);

    if ($res) {
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
    sqlsrv_query(
        $conn,
        "MERGE prtl_UserPresence AS t
         USING (SELECT ? AS user_name, ? AS status) AS s
         ON t.user_name = s.user_name
         WHEN MATCHED THEN
             UPDATE SET status = s.status, last_seen = GETDATE()
         WHEN NOT MATCHED THEN
             INSERT (user_name, status, last_seen) VALUES (s.user_name, s.status, GETDATE());",
        [$name, $status]
    );
}

if (!empty($fullname)) {
    set_user_presence($mssql, $fullname, 'online');
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- Delete Story (Image only) ---
    if ($action === 'delete_story') {
        sqlsrv_query($mssql, "UPDATE prtl_UserNotes SET image_path = NULL, updated_at = GETDATE() WHERE username = ?", [$fullname]);
        json_out(['ok' => true]);
    }

    // --- Mark Story as Seen ---
    if ($action === 'mark_story_seen') {
        $owner = trim($_POST['owner'] ?? '');
        if ($owner !== '') {
            $sql = "MERGE prtl_StoryViews AS t
                    USING (SELECT ? AS viewer, ? AS owner) AS s
                    ON t.viewer_name = s.viewer AND t.story_owner_name = s.owner
                    WHEN MATCHED THEN UPDATE SET last_viewed_at = GETDATE()
                    WHEN NOT MATCHED THEN INSERT (viewer_name, story_owner_name, last_viewed_at) VALUES (s.viewer, s.owner, GETDATE());";
            sqlsrv_query($mssql, $sql, [$fullname, $owner]);
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
            // Only allow common image formats for stories to prevent security issues and huge files
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $fileName = 'story_' . time() . '_' . uniqid() . '.' . $ext;
                $targetFile = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['note_image']['tmp_name'], $targetFile)) {
                    $imagePath = 'uploads/chat_attachments/' . $fileName;
                }
            }
        }

        if ($noteText === '' && $imagePath === null) {
            // If they are clearing both, delete the node entirely
            sqlsrv_query($mssql, "DELETE FROM prtl_UserNotes WHERE username = ?", [$fullname]);
        } else {
            // Keep existing image if they are just updating text and didn't upload a new one
            if ($imagePath === null) {
                $mergeSql = "
                    MERGE prtl_UserNotes AS t
                    USING (SELECT ? AS username, ? AS note_text) AS s
                    ON t.username = s.username
                    WHEN MATCHED THEN UPDATE SET note_text = s.note_text, updated_at = GETDATE()
                    WHEN NOT MATCHED THEN INSERT (username, note_text, updated_at) VALUES (s.username, s.note_text, GETDATE());
                ";
                sqlsrv_query($mssql, $mergeSql, [$fullname, $noteText]);
            } else {
                $mergeSql = "
                    MERGE prtl_UserNotes AS t
                    USING (SELECT ? AS username, ? AS note_text, ? AS image_path) AS s
                    ON t.username = s.username
                    WHEN MATCHED THEN UPDATE SET note_text = s.note_text, image_path = s.image_path, updated_at = GETDATE()
                    WHEN NOT MATCHED THEN INSERT (username, note_text, image_path, updated_at) VALUES (s.username, s.note_text, s.image_path, GETDATE());
                ";
                sqlsrv_query($mssql, $mergeSql, [$fullname, $noteText, $imagePath]);
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

        sqlsrv_begin_transaction($mssql);

        $stmt = sqlsrv_query(
            $mssql,
            "INSERT INTO prtl_Conversations (name, created_by) OUTPUT INSERTED.id VALUES (?, ?)",
            [$groupName, $creator]
        );

        if ($stmt === false) {
            sqlsrv_rollback($mssql);
            json_out(['ok' => false, 'error' => 'Insert conversation failed'], 500);
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC);
        $cid = (int) $row[0];

        // Add creator
        sqlsrv_query($mssql, "INSERT INTO prtl_ConversationParticipants (conversation_id, participant_name) VALUES (?, ?)", [$cid, $creator]);

        // Add members
        $seen = [$creator => true];
        foreach ($members as $m) {
            $n = trim($m['name'] ?? '');
            $b = trim($m['bio'] ?? ''); // we might not use bio logic here but keeping compat
            if ($n !== '' && !isset($seen[$n])) {
                $seen[$n] = true;
                sqlsrv_query($mssql, "INSERT INTO prtl_ConversationParticipants (conversation_id, participant_name, participant_bio) VALUES (?, ?, ?)", [$cid, $n, $b ?: null]);
            }
        }

        sqlsrv_commit($mssql);
        json_out(['ok' => true, 'cid' => $cid]);
    }

    // --- Search Users (for adding to group) ---
    // This is missing in the original msg.php snippet trace but usually required. 
    // Adapting to search prtl_lrn_master_list
    if ($action === 'search_users') {
        $q = trim($_POST['q'] ?? '');
        if (strlen($q) < 2)
            json_out(['ok' => true, 'results' => []]);

        // We need a secondary connection to LRNPH_E for user list, OR assume we can cross-query if on same server instance.
        // The config shows 10.2.0.9 for both? No, includes/db.php was 10.2.0.9 LRNPH_E.
        // This file connects to 10.2.0.9 LRNPH_ITmanagement. 
        // Cross DB query usually works if user has permissions. 

        // Search both Master List and OJT Employees
        $sql = "SELECT TOP 15 name, Department, EmployeeID FROM (
                    SELECT (LTRIM(RTRIM(FirstName)) + ' ' + LTRIM(RTRIM(LastName))) as name, Department, EmployeeID, 1 as priority
                    FROM prtl_lrn_master_list 
                    WHERE (FirstName LIKE ? OR LastName LIKE ? OR (LTRIM(RTRIM(FirstName)) + ' ' + LTRIM(RTRIM(LastName))) LIKE ?) AND isActive = 1
                    UNION ALL
                    SELECT full_name as name, department as Department, employee_id as EmployeeID, 2 as priority
                    FROM prtl_app_ojt_employees
                    WHERE full_name LIKE ?
                ) t
                ORDER BY priority, name";
        $param = ["%$q%", "%$q%", "%$q%", "%$q%"];

        $stmt = sqlsrv_query($mssql, $sql, $param);
        if ($stmt === false) {
            json_out(['ok' => false, 'error' => 'Search failed', 'd' => ['error' => 'Database error occurred']]);
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
        FROM prtl_Conversations c
        INNER JOIN prtl_ConversationParticipants p
            ON p.conversation_id = c.id AND p.participant_name = ?
        OUTER APPLY (
            SELECT TOP 1 m.id, m.sender, m.message, m.attachment_name, m.sent_at
            FROM prtl_Messages m
            WHERE m.conversation_id = c.id
            ORDER BY m.sent_at DESC, m.id DESC
        ) lm
        ORDER BY c.name ASC;";

        $st = sqlsrv_query($mssql, $sql, [$me]);
        $rows = [];
        if ($st) {
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    'id' => (int) $r['id'],
                    'name' => $r['name'],
                    'last_message' => $r['last_message'] ?? '',
                    'last_sender' => $r['last_sender'] ?? '',
                    'last_time' => ($r['last_time'] instanceof DateTime) ? $r['last_time']->format('Y-m-d H:i') : ''
                ];
            }
        }
        json_out(['ok' => true, 'list' => $rows]);
    }

    // --- Fetch Latest DMs ---
    if ($action === 'check_dm_latest') {
        $me = $fullname;
        // Simplified query for DMs
        $sql = "
        WITH convs AS (
            SELECT
              CASE WHEN m.sender <= ISNULL(m.receiver,'') THEN m.sender ELSE ISNULL(m.receiver,'') END AS u1,
              CASE WHEN m.sender <= ISNULL(m.receiver,'') THEN ISNULL(m.receiver,'') ELSE m.sender END AS u2
            FROM prtl_Messages m
            WHERE m.receiver IS NOT NULL
              AND (m.sender = ? OR m.receiver = ?)
            GROUP BY CASE WHEN m.sender <= ISNULL(m.receiver,'') THEN m.sender ELSE ISNULL(m.receiver,'') END,
                     CASE WHEN m.sender <= ISNULL(m.receiver,'') THEN ISNULL(m.receiver,'') ELSE m.sender END
        ),
        lastmsg AS (
            SELECT
              c.u1, c.u2,
              m.id, m.sender, m.message, m.sent_at,
              ROW_NUMBER() OVER (PARTITION BY c.u1, c.u2 ORDER BY m.sent_at DESC, m.id DESC) AS rn
            FROM convs c
            JOIN prtl_Messages m
              ON ((m.sender = c.u1 AND m.receiver = c.u2) OR (m.sender = c.u2 AND m.receiver = c.u1))
        )
        SELECT u1, u2, id, sender, message, sent_at
        FROM lastmsg
        WHERE rn = 1
        ORDER BY sent_at DESC, id DESC;";

        $st = sqlsrv_query($mssql, $sql, [$me, $me]);
        $rows = [];
        if ($st) {
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $other = ($r['u1'] === $me) ? ($r['u2'] ?? '') : ($r['u1'] ?? '');
                if ($other === '')
                    continue;
                $rows[] = [
                    'other' => $other,
                    'last_message' => $r['message'] ?? '',
                    'last_sender' => $r['sender'] ?? '',
                    'last_time' => ($r['sent_at'] instanceof DateTime) ? $r['sent_at']->format('Y-m-d H:i') : ''
                ];
            }
        }
        json_out(['ok' => true, 'list' => $rows]);
    }

    // --- Fetch Group prtl_Messages ---
    if ($action === 'fetch_group_messages') {
        $cid = (int) ($_POST['cid'] ?? 0);
        $rows = [];
        if ($cid > 0) {
            $sql = "SELECT m.id, m.sender, m.message, m.attachment_path, m.attachment_name, m.sent_at, m.reply_to_id, 
                           e.EmployeeID,
                           r.sender as reply_sender, r.message as reply_text
                    FROM prtl_Messages m
                    LEFT JOIN prtl_lrn_master_list e ON REPLACE(REPLACE(LTRIM(RTRIM(e.FirstName)) + ' ' + LTRIM(RTRIM(e.LastName)), '  ', ' '), '  ', ' ') = REPLACE(REPLACE(LTRIM(RTRIM(m.sender)), '  ', ' '), '  ', ' ')
                    LEFT JOIN prtl_Messages r ON m.reply_to_id = r.id
                    WHERE m.conversation_id = ? ORDER BY m.sent_at ASC";
            $st = sqlsrv_query($mssql, $sql, [$cid]);
            if ($st) {
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $rows[] = [
                        'id' => (int) $r['id'],
                        'sender' => $r['sender'],
                        'message' => $r['message'],
                        'attachment_path' => $r['attachment_path'],
                        'attachment_name' => $r['attachment_name'],
                        'sent_at' => ($r['sent_at'] instanceof DateTime) ? $r['sent_at']->format('Y-m-d H:i') : '',
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
        // handle file upload if present
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
            $sql = "INSERT INTO prtl_Messages (conversation_id, sender, message, attachment_path, attachment_name, reply_to_id, sent_at) VALUES (?, ?, ?, ?, ?, ?, GETDATE())";

            $stmt = sqlsrv_query($mssql, $sql, [$cid, $fullname, $msg, $attachmentPath, $attachmentName, $replyTo]);
            if ($stmt === false) {
                $errors = ['error' => 'Database error occurred'];
                $errorDetails = '';
                if ($errors) {
                    foreach ($errors as $error) {
                        $errorDetails .= "SQLSTATE: " . $error['SQLSTATE'] . " Code: " . $error['code'] . " Message: " . $error['message'] . " ";
                    }
                }
                json_out(['ok' => false, 'error' => 'DB Insert Failed: ' . $errorDetails]);
            }
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid message']);
    }

    // --- Fetch DM prtl_Messages ---
    if ($action === 'fetch_dm_messages') {
        $other = trim($_POST['other'] ?? '');
        $rows = [];
        if ($other !== '') {
            $sql = "SELECT m.id, m.sender, m.message, m.attachment_path, m.attachment_name, m.sent_at, m.reply_to_id,
                           e.EmployeeID,
                           r.sender as reply_sender, r.message as reply_text
                    FROM prtl_Messages m
                    LEFT JOIN prtl_lrn_master_list e ON REPLACE(REPLACE(LTRIM(RTRIM(e.FirstName)) + ' ' + LTRIM(RTRIM(e.LastName)), '  ', ' '), '  ', ' ') = REPLACE(REPLACE(LTRIM(RTRIM(m.sender)), '  ', ' '), '  ', ' ')
                    LEFT JOIN prtl_Messages r ON m.reply_to_id = r.id
                    WHERE (REPLACE(REPLACE(LTRIM(RTRIM(m.sender)), '  ', ' '), '  ', ' ') = ? AND REPLACE(REPLACE(LTRIM(RTRIM(m.receiver)), '  ', ' '), '  ', ' ') = ?) 
                       OR (REPLACE(REPLACE(LTRIM(RTRIM(m.sender)), '  ', ' '), '  ', ' ') = ? AND REPLACE(REPLACE(LTRIM(RTRIM(m.receiver)), '  ', ' '), '  ', ' ') = ?) 
                    ORDER BY m.sent_at ASC";
            $st = sqlsrv_query($mssql, $sql, [$fullname, $other, $other, $fullname]);

            if ($st) {
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $rows[] = [
                        'id' => (int) $r['id'],
                        'sender' => $r['sender'],
                        'message' => $r['message'],
                        'attachment_path' => $r['attachment_path'],
                        'attachment_name' => $r['attachment_name'],
                        'sent_at' => ($r['sent_at'] instanceof DateTime) ? $r['sent_at']->format('Y-m-d H:i') : '',
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

    // --- Update GC Settings (Name/Photo) ---
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
            $sql = "UPDATE prtl_Conversations SET name = ? " . ($photoPath ? ", photo_path = ?" : "") . " WHERE id = ?";
            $params = $photoPath ? [$newName, $photoPath, $cid] : [$newName, $cid];
            sqlsrv_query($mssql, $sql, $params);
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
            $ck = sqlsrv_query($mssql, "SELECT 1 FROM prtl_ConversationParticipants WHERE conversation_id=? AND REPLACE(REPLACE(LTRIM(RTRIM(participant_name)), '  ', ' '), '  ', ' ') = REPLACE(REPLACE(LTRIM(RTRIM(?)), '  ', ' '), '  ', ' ')", [$cid, $name]);
            if ($ck && sqlsrv_fetch_array($ck)) {
                json_out(['ok' => false, 'error' => 'Already a member']);
            }
            sqlsrv_query($mssql, "INSERT INTO prtl_ConversationParticipants (conversation_id, participant_name) VALUES (?, ?)", [$cid, $name]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid request']);
    }

    // --- Remove GC Member ---
    if ($action === 'remove_gc_member') {
        $cid = (int) ($_POST['cid'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($cid > 0 && $name !== '') {
            sqlsrv_query($mssql, "DELETE FROM prtl_ConversationParticipants WHERE conversation_id=? AND REPLACE(REPLACE(LTRIM(RTRIM(participant_name)), '  ', ' '), '  ', ' ') = REPLACE(REPLACE(LTRIM(RTRIM(?)), '  ', ' '), '  ', ' ')", [$cid, $name]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid request']);
    }

    // --- Send DM ---
    if ($action === 'send_dm') {
        $to = trim($_POST['to'] ?? '');
        $msg = trim($_POST['message'] ?? '');
        // handle file upload if present
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
            $sql = "INSERT INTO prtl_Messages (sender, receiver, message, attachment_path, attachment_name, reply_to_id, sent_at) VALUES (?, ?, ?, ?, ?, ?, GETDATE())";

            $stmt = sqlsrv_query($mssql, $sql, [$fullname, $to, $msg, $attachmentPath, $attachmentName, $replyTo]);
            if ($stmt === false) {
                json_out(['ok' => false, 'error' => 'DB Insert Failed']);
            }
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid DM']);
    }

    // --- React Message (Frontend-only approach) ---
    if ($action === 'react_message') {
        $msgId = (int) ($_POST['msg_id'] ?? 0);
        $emoji = trim($_POST['emoji'] ?? '');

        if ($msgId > 0) {
            $stmt = sqlsrv_query($mssql, "SELECT message FROM prtl_Messages WHERE id = ?", [$msgId]);
            if ($stmt && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $msgText = $row['message'] ?? '';

                // Check what the current reaction is
                $currentReaction = '';
                if (preg_match('/ \[React:(.*?)\]$/s', $msgText, $matches)) {
                    $currentReaction = $matches[1];
                }

                // Remove existing reaction if any
                $msgText = preg_replace('/ \[React:.*?\]$/s', '', $msgText);

                // Append new reaction only if it's not simply toggling off the exact same one
                if ($emoji !== '' && $emoji !== $currentReaction) {
                    $msgText .= ' [React:' . $emoji . ']';
                }

                sqlsrv_query($mssql, "UPDATE prtl_Messages SET message = ? WHERE id = ?", [$msgText, $msgId]);
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
            $sql = "MERGE prtl_StoryViews AS t
                    USING (SELECT ? AS viewer, ? AS owner) AS s
                    ON t.viewer_name = s.viewer AND t.story_owner_name = s.owner
                    WHEN MATCHED THEN UPDATE SET reaction = ?, last_viewed_at = GETDATE()
                    WHEN NOT MATCHED THEN INSERT (viewer_name, story_owner_name, reaction, last_viewed_at) VALUES (s.viewer, s.owner, ?, GETDATE());";
            sqlsrv_query($mssql, $sql, [$fullname, $owner, $emoji, $emoji]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid owner']);
    }

    // --- Delete Message ---
    if ($action === 'delete_message') {
        $msgId = (int) ($_POST['msg_id'] ?? 0);
        if ($msgId > 0) {
            $sql = "DELETE FROM prtl_Messages WHERE id = ? AND REPLACE(REPLACE(LTRIM(RTRIM(sender)), '  ', ' '), '  ', ' ') = ?";
            sqlsrv_query($mssql, $sql, [$msgId, $fullname]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid message ID']);
    }

    // --- Delete Conversation ---
    if ($action === 'delete_conversation') {
        $cid = (int) ($_POST['cid'] ?? 0);
        $other = trim($_POST['other'] ?? '');

        if ($cid > 0) {
            sqlsrv_query($mssql, "DELETE FROM prtl_Messages WHERE conversation_id = ?", [$cid]);
            sqlsrv_query($mssql, "DELETE FROM prtl_ConversationParticipants WHERE conversation_id = ?", [$cid]);
            sqlsrv_query($mssql, "DELETE FROM prtl_Conversations WHERE id = ?", [$cid]);
            json_out(['ok' => true]);
        } elseif ($other !== '') {
            $sql = "DELETE FROM prtl_Messages WHERE (REPLACE(REPLACE(LTRIM(RTRIM(sender)), '  ', ' '), '  ', ' ') = ? AND REPLACE(REPLACE(LTRIM(RTRIM(receiver)), '  ', ' '), '  ', ' ') = ?) OR (REPLACE(REPLACE(LTRIM(RTRIM(sender)), '  ', ' '), '  ', ' ') = ? AND REPLACE(REPLACE(LTRIM(RTRIM(receiver)), '  ', ' '), '  ', ' ') = ?)";
            sqlsrv_query($mssql, $sql, [$fullname, $other, $other, $fullname]);
            json_out(['ok' => true]);
        }
        json_out(['ok' => false, 'error' => 'Invalid conversation parameters']);
    }
}
?>