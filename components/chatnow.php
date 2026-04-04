<?php
// ChatNow Component for Admin Portal
// Expects $conn (LRNPH_E) from admin.php, but we need LRNPH_ITmanagement for Chat.
// We'll create a local connection to ITmanagement here or use the one from chatnow_api.php if we include it? 
// careful with double headers. We'll just make a new connection.

// Database Connection to LRNPH_E (Supabase PostgreSQL)
require_once __DIR__ . '/../includes/db.php';
// $conn is already available from db.php

$fullname = preg_replace('/\s+/', ' ', trim($_SESSION['fullname'] ?? $_SESSION['username']));
$employeeId = $_SESSION['employee_id'] ?? '';

require_once __DIR__ . '/../includes/photo_helper.php';

// Helper to resolve photo
function resolve_photo($name)
{
    return DEFAULT_AVATAR_URL;
}

$cid = isset($_GET['cid']) ? (int) $_GET['cid'] : 0;
$user1 = $_GET['user1'] ?? '';
$user2 = $_GET['user2'] ?? '';

// Initialize state variables to prevent JS errors when no chat is active
$isGroup = false;
$groupMembers = [];
$messages = [];

// Handle non-AJAX actions? Ideally all JS. 
// But the view needs to render the initial state.

// Fetch prtl_Conversations (DMs)
$sqlDMs = "
WITH pairs AS (
    SELECT
        REPLACE(REPLACE(sender, '  ', ' '), '  ', ' ') AS u1,
        REPLACE(REPLACE(receiver, '  ', ' '), '  ', ' ') AS u2,
        MAX(sent_at) as last_activity
    FROM \"prtl_Messages\"
    WHERE (REPLACE(REPLACE(sender, '  ', ' '), '  ', ' ') = ? OR REPLACE(REPLACE(receiver, '  ', ' '), '  ', ' ') = ?) AND receiver IS NOT NULL
    GROUP BY u1, u2
),
unified_pairs AS (
    SELECT 
        CASE WHEN u1 <= u2 THEN u1 ELSE u2 END as uu1,
        CASE WHEN u1 <= u2 THEN u2 ELSE u1 END as uu2,
        MAX(last_activity) as m_activity
    FROM pairs
    GROUP BY uu1, uu2
)
SELECT p.uu1, p.uu2, p.m_activity as last_activity, e.\"EmployeeID\", lm.message as last_message, lm.sender as last_sender 
FROM unified_pairs p
LEFT JOIN LATERAL (
    SELECT \"EmployeeID\" FROM \"prtl_lrn_master_list\" 
    WHERE REPLACE(REPLACE(\"FirstName\" || ' ' || \"LastName\", '  ', ' '), '  ', ' ') = (CASE WHEN p.uu1 = ? THEN p.uu2 ELSE p.uu1 END)
    LIMIT 1
) e ON TRUE
LEFT JOIN LATERAL (
    SELECT message, REPLACE(REPLACE(sender, '  ', ' '), '  ', ' ') as sender
    FROM \"prtl_Messages\" m 
    WHERE ((REPLACE(REPLACE(sender, '  ', ' '), '  ', ' ') = p.uu1 AND REPLACE(REPLACE(receiver, '  ', ' '), '  ', ' ') = p.uu2) 
        OR (REPLACE(REPLACE(sender, '  ', ' '), '  ', ' ') = p.uu2 AND REPLACE(REPLACE(receiver, '  ', ' '), '  ', ' ') = p.uu1))
    ORDER BY sent_at DESC
    LIMIT 1
) lm ON TRUE
ORDER BY p.m_activity DESC";
$stmtDMs = $conn->prepare($sqlDMs);
$stmtDMs->execute([$fullname, $fullname, $fullname]);

// Fetch Groups with Members for Search
$sqlGroups = "
SELECT c.id, c.name, c.photo_path,
       (SELECT STRING_AGG(participant_name, ' ') FROM \"prtl_ConversationParticipants\" WHERE conversation_id = c.id) as members,
       lm.message as last_message, lm.sender as last_sender,
       (SELECT MAX(sent_at) FROM \"prtl_Messages\" WHERE conversation_id = c.id) as max_sent
FROM \"prtl_Conversations\" c
JOIN \"prtl_ConversationParticipants\" p ON p.conversation_id = c.id
LEFT JOIN LATERAL (
    SELECT message, REPLACE(REPLACE(sender, '  ', ' '), '  ', ' ') as sender 
    FROM \"prtl_Messages\" 
    WHERE conversation_id = c.id 
    ORDER BY sent_at DESC
    LIMIT 1
) lm ON TRUE
WHERE p.participant_name = ?
ORDER BY max_sent DESC";
$stmtGroups = $conn->prepare($sqlGroups);
$stmtGroups->execute([$fullname]);

// Schema creation removed as it's handled in supabase_schema.sql

// Fetch Active Notes with Seen Status
$sqlNotes = "
SELECT n.username, n.note_text, n.image_path, n.updated_at, e.\"EmployeeID\",
       CASE WHEN v.last_viewed_at >= n.updated_at THEN 1 ELSE 0 END as is_seen
FROM \"prtl_UserNotes\" n
LEFT JOIN \"prtl_lrn_master_list\" e ON REPLACE(REPLACE(\"FirstName\" || ' ' || \"LastName\", '  ', ' '), '  ', ' ') = n.username
LEFT JOIN \"prtl_StoryViews\" v ON v.story_owner_name = n.username AND v.viewer_name = ?
WHERE n.updated_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'
ORDER BY n.updated_at DESC";
$stmtNotes = $conn->prepare($sqlNotes);
$stmtNotes->execute([$fullname]);
$activeNotes = [];
$myNote = null;
if ($stmtNotes) {
    while ($row = $stmtNotes->fetch(PDO::FETCH_ASSOC)) {
        if ($row['username'] === $fullname) {
            $myNote = $row;
        } else {
            $activeNotes[$row['username']] = $row;
        }
    }
}

?>
<link rel="stylesheet" href="assets/chatnow.css?v=2.0">

<div class="msg-app">
    <!-- Sidebar -->
    <aside class="chat-sidebar">
        <div class="p-4 flex justify-between items-center border-b border-gray-100">
            <h2 class="font-bold text-2xl tracking-tight text-gray-900">Chat</h2>
            <div class="flex gap-2">
                <button onclick="showNewMsg()"
                    class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors text-gray-800"
                    title="New Message">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button onclick="showNewGroup()"
                    class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors text-gray-800"
                    title="New Group">
                    <i class="fa-solid fa-users"></i>
                </button>
            </div>
        </div>

        <!-- Stories / Notes Section -->
        <div
            class="px-3 mb-3 grid grid-cols-4 gap-y-4 gap-x-2 overflow-y-auto max-h-[200px] custom-scrollbar py-3 items-start mt-1">
            <!-- Current User -->
            <div class="flex flex-col items-center gap-1 cursor-pointer shrink-0 w-[72px] relative"
                onclick="<?= $myNote && !empty($myNote['image_path']) ? "window.location.href='admin.php?page=chatnow&view_story=" . urlencode($fullname) . "'" : 'openNoteModal()' ?>"
                title="Your Story">
                <div class="relative w-14 h-14 transition-transform hover:scale-105">
                    <?php
                    $isMyStorySeen = ($myNote && ($myNote['is_seen'] ?? 0) == 1);
                    $myStoryClass = ($myNote && !empty($myNote['image_path'])) ? ($isMyStorySeen ? 'border-2 border-gray-300 p-[1px]' : 'border-2 border-pink-500 p-[1px]') : 'border border-gray-200';
                    ?>
                    <div
                        class="w-full h-full rounded-full <?= $myStoryClass ?> relative flex items-center justify-center bg-gray-50 overflow-hidden">
                        <img src="<?= getEmployeePhotoUrl($employeeId) ?>"
                            class="w-full h-full rounded-full object-cover">
                    </div>
                    <?php if ($myNote && !empty($myNote['note_text'])): ?>
                        <div
                            class="absolute -top-1 left-1/2 -translate-x-1/2 bg-white border border-gray-100 shadow-sm rounded-xl px-2 py-0.5 text-[10px] font-medium text-gray-700 whitespace-nowrap max-w-[80px] truncate z-20">
                            <?= htmlspecialchars($myNote['note_text']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="absolute bottom-0 right-0 w-4 h-4 bg-pink-500 rounded-full flex items-center justify-center text-white text-[12px] font-bold ring-2 ring-white z-20"
                        onclick="event.stopPropagation(); openNoteModal();" title="Add">
                        +
                    </div>
                </div>
                <span class="text-[11px] text-gray-500 font-medium mt-1 truncate w-full text-center">Your Note</span>
            </div>

            <!-- Other Users -->
            <?php foreach ($activeNotes as $n):
                $firstName = explode(' ', $n['username'])[0];
                $hasStory = !empty($n['image_path']);
                $hasNote = !empty($n['note_text']);
                $isSeen = ($n['is_seen'] ?? 0) == 1;
                ?>
                <div class="flex flex-col items-center gap-1 shrink-0 w-[72px] group relative cursor-pointer"
                    onclick="<?= $hasStory ? "window.location.href='admin.php?page=chatnow&view_story=" . urlencode($n['username']) . "'" : "window.location.href='admin.php?page=chatnow&user1=" . urlencode($fullname) . "&user2=" . urlencode($n['username']) . "'" ?>">
                    <div class="relative w-14 h-14 transition-transform group-hover:scale-105">
                        <div
                            class="w-full h-full rounded-full <?= $hasStory ? ($isSeen ? 'border-2 border-gray-300 p-[1px]' : 'border-2 border-pink-500 p-[1px]') : 'border border-gray-200' ?> relative flex items-center justify-center overflow-hidden">
                            <img src="<?= getEmployeePhotoUrl($n['EmployeeID'] ?? '') ?>"
                                class="w-full h-full rounded-full object-cover">
                        </div>
                        <?php if ($hasNote): ?>
                            <div
                                class="absolute -top-1 left-1/2 -translate-x-1/2 bg-white border border-gray-200 shadow-sm rounded-xl px-2 py-0.5 text-[10px] font-medium text-gray-800 whitespace-nowrap max-w-[80px] truncate z-20">
                                <?= htmlspecialchars($n['note_text']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span
                        class="text-[11px] text-gray-700 font-medium mt-1 truncate w-full text-center"><?= htmlspecialchars($firstName) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="px-4 mb-2">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400 text-sm"></i>
                <input type="text" id="sidebarSearchInput" onkeyup="filterConversations()"
                    placeholder="Search Messenger"
                    class="w-full bg-gray-100 text-gray-900 rounded-full py-2.5 pl-10 pr-4 outline-none text-[15px] focus:bg-gray-50 border border-transparent focus:border-gray-200 transition-all">
            </div>
        </div>

        <div class="conv-list custom-scrollbar">
            <?php if ($stmtDMs): ?>
                <?php while ($row = $stmtDMs->fetch(PDO::FETCH_ASSOC)):
                    $other = ($row['uu1'] === $fullname) ? $row['uu2'] : $row['uu1'];
                    if (!$other)
                        continue;
                    $isActive = ($user2 === $other);
                    $initial = strtoupper(substr($other, 0, 1));
                    ?>
                    <div class="conv-link <?= $isActive ? 'active' : '' ?> flex items-center px-3 py-2">
                        <?php
                        $storyInfo = $activeNotes[$other] ?? null;
                        $hasStory = ($storyInfo && !empty($storyInfo['image_path'])) ? true : false;
                        $isSeen = $storyInfo && ($storyInfo['is_seen'] ?? 0) == 1;

                        $avatarClass = $hasStory ? ($isSeen ? 'border-2 border-gray-300 p-[1px]' : 'border-2 border-pink-500 p-[1px]') : 'border border-gray-100';
                        $avatarClick = $hasStory ? "event.stopPropagation(); window.location.href='admin.php?page=chatnow&view_story=" . urlencode($other) . "';" : "";
                        ?>
                        <div class="w-[48px] h-[48px] rounded-full shrink-0 overflow-hidden relative <?= $hasStory ? 'cursor-pointer transition-transform hover:scale-105' : 'bg-gray-200 shadow-sm' ?>"
                            onclick="<?= $avatarClick ?>" title="<?= $hasStory ? 'View Story' : '' ?>">
                            <img src="<?= getEmployeePhotoUrl($row['EmployeeID'] ?? '') ?>"
                                alt="<?= htmlspecialchars($other) ?>"
                                class="w-full h-full rounded-full object-cover <?= $avatarClass ?>">
                        </div>
                        <div class="flex-1 min-w-0 ml-3 cursor-pointer"
                            onclick="window.location.href='admin.php?page=chatnow&user1=<?= urlencode($fullname) ?>&user2=<?= urlencode($other) ?>'">
                            <div class="font-medium text-[15px] truncate text-gray-900"><?= htmlspecialchars($other) ?></div>
                            <div class="text-[13px] text-gray-500 truncate font-normal">
                                <?php if (!empty($row['last_message'])): ?>
                                    <?= ($row['last_sender'] === $fullname) ? 'You: ' : '' ?>
                                    <?= htmlspecialchars($row['last_message']) ?>
                                <?php else: ?>
                                    Start a conversation
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

            <?php if ($stmtGroups): ?>
                <div class="px-3 mt-4 mb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">Groups</div>
                <?php while ($row = $stmtGroups->fetch(PDO::FETCH_ASSOC)):
                    $isActive = ($cid === $row['id']);
                    ?>
                    <a href="admin.php?page=chatnow&cid=<?= $row['id'] ?>"
                        class="conv-link <?= $isActive ? 'active' : '' ?> flex items-center px-3 py-2">
                        <div
                            class="w-[48px] h-[48px] rounded-full shrink-0 overflow-hidden flex items-center justify-center border border-gray-100 shadow-sm ml-0">
                            <?php if (!empty($row['photo_path'])): ?>
                                <img src="assets/<?= htmlspecialchars($row['photo_path']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div
                                    class="w-full h-full bg-gradient-to-br from-pink-400 to-pink-600 flex items-center justify-center text-white text-lg font-bold">
                                    #
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0 ml-3">
                            <div class="font-medium text-[15px] truncate text-gray-900"><?= htmlspecialchars($row['name']) ?>
                            </div>
                            <div class="text-[13px] text-gray-500 truncate font-normal">
                                <?php if (!empty($row['last_message'])): ?>
                                    <?php
                                    $senderName = explode(' ', $row['last_sender'])[0];
                                    ?>
                                    <?= ($row['last_sender'] === $fullname) ? 'You' : htmlspecialchars($senderName) ?>:
                                    <?= htmlspecialchars($row['last_message']) ?>
                                <?php else: ?>
                                    No messages yet
                                <?php endif; ?>
                            </div>
                            <!-- Hidden members list for search matching -->
                            <span style="display:none">
                                <?= htmlspecialchars($row['members'] ?? '') ?>
                            </span>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Thread -->
    <main class="thread relative">
        <?php if (isset($_GET['view_story'])):
            $viewStoryUser = $_GET['view_story'];
            $story = null;
            $prevUser = null;
            $nextUser = null;

            if ($viewStoryUser === $fullname) {
                $story = $myNote;
            } elseif (isset($activeNotes[$viewStoryUser])) {
                $story = $activeNotes[$viewStoryUser];
            }

            $viewersList = [];
            $myReaction = '';
            if ($story) {
                // Fetch viewers if owner
                if ($viewStoryUser === $fullname) {
                    $vSql = "SELECT viewer_name, reaction FROM \"prtl_StoryViews\" WHERE story_owner_name = ? AND last_viewed_at >= ? AND viewer_name != ?";
                    $vStmt = $conn->prepare($vSql);
                    // $story['updated_at'] might be a string or object depending on fetch mode, but strtotime/date works
                    $updatedAt = is_string($story['updated_at']) ? $story['updated_at'] : $story['updated_at']->format('Y-m-d H:i:s');
                    $vStmt->execute([$fullname, $updatedAt, $fullname]);
                    if ($vStmt) {
                        while ($vr = $vStmt->fetch(PDO::FETCH_ASSOC)) {
                            $viewersList[] = $vr;
                        }
                    }
                }

                // Fetch my reaction
                $rSql = "SELECT reaction FROM \"prtl_StoryViews\" WHERE viewer_name = ? AND story_owner_name = ?";
                $rStmt = $conn->prepare($rSql);
                $rStmt->execute([$fullname, $viewStoryUser]);
                if ($rStmt && $rr = $rStmt->fetch(PDO::FETCH_ASSOC)) {
                    $myReaction = $rr['reaction'] ?? '';
                }

                // Mark as seen
                $checkSql = "INSERT INTO \"prtl_StoryViews\" (viewer_name, story_owner_name, last_viewed_at) VALUES (?, ?, CURRENT_TIMESTAMP)
                            ON CONFLICT (viewer_name, story_owner_name) DO UPDATE SET last_viewed_at = CURRENT_TIMESTAMP";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute([$fullname, $viewStoryUser]);
            }
            ?>
            <!-- Messenger Styled Story Viewer -->
            <div
                class="w-full h-full bg-zinc-950 flex flex-col items-center justify-center relative overflow-hidden group/viewer">
                <?php if ($story): ?>
                    <?php
                    $keys = array_keys($activeNotes);
                    if ($myNote)
                        array_unshift($keys, $fullname);
                    $currentIndex = array_search($viewStoryUser, $keys);
                    $prevUser = $currentIndex > 0 ? $keys[$currentIndex - 1] : null;
                    $nextUser = $currentIndex < count($keys) - 1 ? $keys[$currentIndex + 1] : null;
                    ?>


                    <!-- Content wrapper (The actual "Story Card") -->
                    <div
                        class="w-full h-full sm:max-w-[450px] sm:max-h-[850px] sm:my-4 relative flex flex-col items-center justify-center sm:rounded-[24px] overflow-hidden shadow-[0_0_60px_rgba(0,0,0,0.5)] bg-black transition-all z-20">

                        <!-- Top Navigation & Progress (Constrained inside) -->
                        <div
                            class="absolute top-0 left-0 right-0 p-5 z-[70] bg-gradient-to-b from-black/80 via-black/40 to-transparent">
                            <!-- Progress Bars -->
                            <div class="flex gap-1 mb-4">
                                <div class="h-0.5 flex-1 bg-white/20 overflow-hidden relative rounded-full">
                                    <div id="storyProgressBar" class="absolute inset-0 bg-white w-0"></div>
                                </div>
                            </div>

                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <img src="<?= getEmployeePhotoUrl($story['EmployeeID'] ?? $employeeId) ?>"
                                        class="w-9 h-9 rounded-full border border-white/20 object-cover shadow-lg">
                                    <div>
                                        <div class="text-white font-bold text-sm leading-tight drop-shadow-md">
                                            <?= htmlspecialchars($viewStoryUser) ?>
                                        </div>
                                        <div class="text-white/60 text-[10px] font-medium drop-shadow-md">
                                            <?= $story['updated_at']->format('M j, h:i A') ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($viewStoryUser === $fullname): ?>
                                        <div class="relative">
                                            <button onclick="toggleStoryMenu(event)"
                                                class="w-8 h-8 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-all backdrop-blur-md border border-white/10 focus:outline-none">
                                                <i class="fa-solid fa-ellipsis-vertical text-sm"></i>
                                            </button>
                                            <div id="storyMenu"
                                                class="absolute right-0 top-full mt-2 hidden bg-white rounded-2xl shadow-2xl border border-gray-100 py-2 min-w-[200px] z-[100] overflow-hidden animate-fade-in-up">
                                                <button onclick="deleteStory()"
                                                    class="w-full px-3 py-3 text-left text-red-500 hover:bg-red-50 transition-all duration-200 flex items-center gap-3 group">
                                                    <div
                                                        class="w-10 h-10 rounded-xl bg-red-50 group-hover:bg-red-100 flex items-center justify-center transition-colors shrink-0">
                                                        <i class="fa-solid fa-trash-can text-sm"></i>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-[15px] leading-none text-red-600">Remove</span>
                                                        <span class="text-[12px] text-red-400 font-medium mt-1 leading-none">Your
                                                            Story</span>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <button onclick="window.location.href='admin.php?page=chatnow'"
                                        class="w-10 h-10 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20 transition-all backdrop-blur-md border border-white/10 shadow-lg">
                                        <i class="fa-solid fa-xmark text-base"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Content wrapper -->
                        <?php if (!empty($story['image_path'])): ?>
                            <div class="absolute inset-0 bg-cover bg-center blur-3xl scale-125 opacity-40 transition-opacity"
                                style="background-image: url('<?= htmlspecialchars($story['image_path']) ?>')"></div>
                            <img src="<?= htmlspecialchars($story['image_path']) ?>"
                                class="w-full h-full object-contain relative z-10 transition-transform duration-500">
                        <?php else: ?>
                            <div
                                class="w-full h-full bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 relative z-10 flex flex-col items-center justify-center px-12 text-center">
                                <i
                                    class="fa-solid fa-quote-left text-white/20 text-[100px] absolute top-10 transform -rotate-12"></i>
                                <p class="text-white text-3xl font-bold leading-tight drop-shadow-xl z-20">
                                    <?= htmlspecialchars($story['note_text'] ?? '') ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Navigation Arrows (Now Inside Card) -->
                        <div
                            class="absolute inset-y-0 left-0 w-16 z-50 flex items-center justify-center opacity-0 group-hover/viewer:opacity-100 transition-opacity">
                            <?php if ($prevUser): ?>
                                <button
                                    onclick="window.location.href='admin.php?page=chatnow&view_story=<?= urlencode($prevUser) ?>'"
                                    class="w-10 h-10 rounded-full bg-black/20 text-white flex items-center justify-center hover:bg-black/40 transition-all backdrop-blur-sm border border-white/10">
                                    <i class="fa-solid fa-chevron-left text-xs"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div
                            class="absolute inset-y-0 right-0 w-16 z-50 flex items-center justify-center opacity-0 group-hover/viewer:opacity-100 transition-opacity">
                            <?php if ($nextUser): ?>
                                <button
                                    onclick="window.location.href='admin.php?page=chatnow&view_story=<?= urlencode($nextUser) ?>'"
                                    class="w-10 h-10 rounded-full bg-black/20 text-white flex items-center justify-center hover:bg-black/40 transition-all backdrop-blur-sm border border-white/10">
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </button>
                            <?php else: ?>
                                <button onclick="window.location.href='admin.php?page=chatnow&view_story=done'"
                                    class="w-10 h-10 rounded-full bg-black/20 text-white flex items-center justify-center hover:bg-black/40 transition-all backdrop-blur-sm border border-white/10">
                                    <i class="fa-solid fa-check text-xs"></i>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Bottom Interaction Bar (Constrained inside) -->
                        <?php if ($viewStoryUser !== $fullname): ?>
                            <div
                                class="absolute bottom-0 left-0 right-0 p-4 z-40 bg-gradient-to-t from-black/90 via-black/30 to-transparent">
                                <div class="w-full flex items-center gap-3">
                                    <!-- Reply bar -->
                                    <div class="flex-1 relative group/input">
                                        <input type="text" id="storyReplyInput" placeholder="Reply..."
                                            onkeydown="if(event.key==='Enter') sendStoryReply(this.value)"
                                            class="w-full bg-white/10 border border-white/20 text-white px-4 py-2.5 rounded-full text-xs outline-none focus:bg-white/20 placeholder:text-white/40 transition-all backdrop-blur-md focus:border-white/30">
                                    </div>

                                    <!-- Reactions (Beside the input) -->
                                    <div class="flex items-center gap-4 px-2">
                                        <?php foreach (['❤️', '😂', '😮', '😢', '🙏'] as $em): ?>
                                            <?php $isMyReaction = ($myReaction === $em); ?>
                                            <button onclick="reactStory('<?= $em ?>', this)"
                                                class="flex flex-col items-center gap-1 group/reaction relative">
                                                <span
                                                    class="text-2xl transform hover:scale-125 transition-transform active:scale-95 drop-shadow-lg"><?= $em ?></span>
                                                <div
                                                    class="reaction-dot w-1.5 h-1.5 bg-sky-400 rounded-full transition-all shadow-[0_0_8px_rgba(56,189,248,0.6)] <?= $isMyReaction ? 'opacity-100 scale-100' : 'opacity-0 scale-0' ?>">
                                                </div>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Owner View: Clickable Viewers Bar -->
                            <button onclick="showViewersModal()"
                                class="absolute bottom-0 left-0 right-0 p-6 z-40 bg-gradient-to-t from-black/90 via-black/20 to-transparent w-full text-left group/viewers focus:outline-none">
                                <div
                                    class="flex items-center gap-3 text-white/90 group-hover/viewers:translate-y-[-2px] transition-transform">
                                    <div
                                        class="flex items-center justify-center w-10 h-10 rounded-full bg-white/10 backdrop-blur-md border border-white/10">
                                        <i class="fa-solid fa-eye text-sm"></i>
                                    </div>
                                    <div class="flex flex-col flex-1">
                                        <div class="text-[14px] font-bold"><?= count($viewersList) ?> Views</div>
                                        <div class="text-[11px] text-white/60 truncate max-w-[200px]">
                                            <?php if (!empty($viewersList)): ?>
                                                Seen by
                                                <?php
                                                $names = [];
                                                foreach (array_slice($viewersList, 0, 2) as $v) {
                                                    $names[] = htmlspecialchars(explode(' ', $v['viewer_name'])[0]) . ($v['reaction'] ? ' ' . $v['reaction'] : '');
                                                }
                                                echo implode(', ', $names);
                                                echo count($viewersList) > 2 ? ' and ' . (count($viewersList) - 2) . ' others' : '';
                                                ?>
                                            <?php else: ?>
                                                No views yet
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <i class="fa-solid fa-chevron-up text-white/40 text-xs"></i>
                                </div>
                            </button>

                            <!-- Viewers List Modal (Overlay inside card) -->
                            <div id="viewersModal"
                                class="absolute inset-0 z-[60] hidden bg-black/60 backdrop-blur-md animate-slide-up">
                                <div
                                    class="absolute bottom-0 left-0 right-0 h-3/4 bg-zinc-900 rounded-t-[32px] overflow-hidden flex flex-col border-t border-white/10">
                                    <div class="flex items-center justify-between p-6 border-b border-white/5">
                                        <div class="flex flex-col">
                                            <h3 class="text-white font-bold text-lg">Story Viewers</h3>
                                            <p class="text-white/40 text-xs"><?= count($viewersList) ?> people have seen this</p>
                                        </div>
                                        <button onclick="hideViewersModal()"
                                            class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-white/60 hover:text-white transition-colors">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                    <div class="flex-1 overflow-y-auto p-2 custom-scrollbar">
                                        <?php if (empty($viewersList)): ?>
                                            <div class="flex flex-col items-center justify-center h-full text-white/20">
                                                <i class="fa-solid fa-users text-4xl mb-2"></i>
                                                <p class="text-sm">No views yet</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($viewersList as $v): ?>
                                                <div
                                                    class="flex items-center justify-between p-4 rounded-2xl hover:bg-white/5 transition-colors group">
                                                    <div class="flex items-center gap-3">
                                                        <div
                                                            class="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-400 font-bold border border-indigo-500/20">
                                                            <?= strtoupper(substr($v['viewer_name'], 0, 1)) ?>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span
                                                                class="text-white font-semibold text-sm"><?= htmlspecialchars($v['viewer_name']) ?></span>
                                                            <span class="text-white/40 text-[10px]">Just now</span>
                                                        </div>
                                                    </div>
                                                    <?php if ($v['reaction']): ?>
                                                        <div class="text-2xl drop-shadow-md animate-bounce-short"><?= $v['reaction'] ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div
                        class="w-full flex-1 flex flex-col items-center justify-center text-center p-12 bg-zinc-900 sm:max-w-[450px] sm:max-h-[850px] sm:rounded-[24px] opacity-90 mx-auto relative group/done">
                        <!-- Done View Close Button -->
                        <button onclick="window.location.href='admin.php?page=chatnow'"
                            class="absolute top-6 right-6 w-10 h-10 rounded-full bg-white/5 text-white/40 flex items-center justify-center hover:bg-white/10 hover:text-white transition-all border border-white/5 z-50">
                            <i class="fa-solid fa-xmark text-base"></i>
                        </button>
                        <div
                            class="w-24 h-24 bg-gray-500/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-white/10">
                            <i class="fa-solid fa-check text-4xl text-gray-400"></i>
                        </div>
                        <h2 class="text-2xl font-bold mb-2 text-gray-300">You're all caught up!</h2>
                        <p class="text-gray-500 mb-8 max-w-[280px] mx-auto">You've seen all current stories from your contacts.
                        </p>
                        <button onclick="window.location.href='admin.php?page=chatnow'"
                            class="px-8 py-3 bg-zinc-800 text-white border border-white/10 rounded-full font-bold transition-transform hover:scale-105 active:scale-95">Back
                            to Chat</button>
                    </div>
                <?php endif; ?>
            </div>

            <style>
                @keyframes progress {
                    from {
                        width: 0%;
                    }

                    to {
                        width: 100%;
                    }
                }

                @keyframes slideUp {
                    from {
                        transform: translateY(10px);
                        opacity: 0;
                    }

                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }

                .highlight-msg {
                    animation: msgPulse 2s ease-out;
                    box-shadow: 0 0 20px rgba(236, 72, 153, 0.3) !important;
                }

                @keyframes msgPulse {
                    0% {
                        background-color: rgba(236, 72, 153, 0.2);
                    }

                    100% {
                        background-color: transparent;
                    }
                }

                .animate-slide-up {
                    animation: slideUp 0.2s ease-out forwards;
                }

                @keyframes bounceShort {

                    0%,
                    100% {
                        transform: translateY(0);
                    }

                    50% {
                        transform: translateY(-4px);
                    }
                }

                .animate-bounce-short {
                    animation: bounceShort 0.5s ease-out;
                }

                .custom-scrollbar::-webkit-scrollbar {
                    width: 4px;
                }

                .custom-scrollbar::-webkit-scrollbar-track {
                    background: transparent;
                }

                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background: rgba(255, 255, 255, 0.1);
                    border-radius: 10px;
                }
            </style>

            <script>
                function toggleStoryMenu(e) {
                    e.stopPropagation();
                    const menu = document.getElementById('storyMenu');
                    menu.classList.toggle('hidden');
                }

                document.addEventListener('click', () => {
                    document.getElementById('storyMenu')?.classList.add('hidden');
                });

                function deleteStory() {
                    showConfirmModal(
                        'Remove Story?',
                        'This will permanently delete your story for all users. Proceed?',
                        () => {
                            const fd = new FormData();
                            fd.append('action', 'delete_story');
                            fetch('components/chatnow_api.php', { method: 'POST', body: fd })
                                .then(r => r.json())
                                .then(data => {
                                    if (data.ok) {
                                        window.location.href = 'admin.php?page=chatnow';
                                    } else {
                                        alert('Error: ' + data.error);
                                    }
                                });
                        }
                    );
                }

                // Story Progress & Auto-advance
                const progressBar = document.getElementById('storyProgressBar');
                if (progressBar) {
                    let progress = 0;
                    const duration = 5000; // 5 seconds
                    const intervalTime = 50;
                    const increment = (intervalTime / duration) * 100;

                    const timer = setInterval(() => {
                        progress += increment;
                        progressBar.style.width = Math.min(progress, 100) + '%';

                        if (progress >= 100) {
                            clearInterval(timer);
                            const nextUrl = "<?= $nextUser ? 'admin.php?page=chatnow&view_story=' . urlencode($nextUser) : 'admin.php?page=chatnow&view_story=done' ?>";

                            // If it's the last story, show the "Caught up" state by going to a "done" view or just back
                            // Actually, let's just go back to chat if no nextUser, but wait, the user wanted a gray card.
                            // If nextUser exists, go to it. If not, go back (or we could show the card via a param)
                            window.location.href = nextUrl;
                        }
                    }, intervalTime);
                }

                function showViewersModal() {
                    const modal = document.getElementById('viewersModal');
                    if (modal) {
                        modal.classList.remove('hidden');
                    }
                }

                function hideViewersModal() {
                    const modal = document.getElementById('viewersModal');
                    if (modal) {
                        modal.classList.add('hidden');
                    }
                }

                function reactStory(emoji, btn) {
                    const otherUser = <?= json_encode($viewStoryUser) ?>;
                    const fd = new FormData();
                    fd.append('action', 'react_story');
                    fd.append('owner', otherUser);
                    fd.append('emoji', emoji);

                    // Hide all other dots first
                    document.querySelectorAll('.reaction-dot').forEach(d => {
                        d.classList.add('opacity-0', 'scale-0');
                        d.classList.remove('opacity-100', 'scale-100');
                    });

                    // Show the selected dot
                    const dot = btn.querySelector('.reaction-dot');
                    if (dot) {
                        dot.classList.remove('opacity-0', 'scale-0');
                        dot.classList.add('opacity-100', 'scale-100');
                    }

                    fetch('components/chatnow_api.php', { method: 'POST', body: fd });
                }

                function sendStoryReply(text) {
                    if (!text || !text.trim()) return;

                    const otherUser = <?= json_encode($viewStoryUser) ?>;

                    const fd = new FormData();
                    fd.append('action', 'send_dm');
                    fd.append('to', otherUser);
                    fd.append('message', "[StoryReply] " + text.trim());

                    fetch('components/chatnow_api.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.ok) {
                                window.location.href = `admin.php?page=chatnow&user1=<?= urlencode($fullname) ?>&user2=${encodeURIComponent(otherUser)}`;
                            } else {
                                alert('Error sending reply');
                            }
                        });
                }
            </script>

        <?php elseif ($cid > 0 || ($user1 && $user2)): ?>
            <?php
            $isGroup = ($cid > 0);
            if ($isGroup) {
                // Get Group Info
                $gq = $conn->prepare("SELECT name, photo_path FROM \"prtl_Conversations\" WHERE id=?");
                $gq->execute([$cid]);
                $gcPhotoPath = '';
                if ($r = $gq->fetch(PDO::FETCH_ASSOC)) {
                    $headerTitle = $r['name'];
                    $gcPhotoPath = $r['photo_path'];
                }

                // Get Group Members with photos
                $memSql = "SELECT cp.participant_name, e.\"EmployeeID\" 
                           FROM \"prtl_ConversationParticipants\" cp
                           LEFT JOIN \"prtl_lrn_master_list\" e ON REPLACE(REPLACE(\"FirstName\" || ' ' || \"LastName\", '  ', ' '), '  ', ' ') = REPLACE(REPLACE(cp.participant_name, '  ', ' '), '  ', ' ')
                           WHERE cp.conversation_id=? ORDER BY cp.participant_name";
                $memQ = $conn->prepare($memSql);
                $memQ->execute([$cid]);
                $groupMembers = [];
                if ($memQ)
                    while ($mqr = $memQ->fetch(PDO::FETCH_ASSOC))
                        $groupMembers[] = $mqr;

                // Get Messages (Group) with sender photos and reply info
                $msgSql = "SELECT m.*, e.\"EmployeeID\", r.sender as reply_sender, r.message as reply_text
                           FROM \"prtl_Messages\" m
                           LEFT JOIN \"prtl_lrn_master_list\" e ON REPLACE(REPLACE(\"FirstName\" || ' ' || \"LastName\", '  ', ' '), '  ', ' ') = REPLACE(REPLACE(m.sender, '  ', ' '), '  ', ' ')
                           LEFT JOIN \"prtl_Messages\" r ON m.reply_to_id = r.id
                           WHERE m.conversation_id=? ORDER BY m.sent_at ASC";
                $mq = $conn->prepare($msgSql);
                $mq->execute([$cid]);
                while ($m = $mq->fetch(PDO::FETCH_ASSOC))
                    $messages[] = $m;
            } else {
                $headerTitle = $user2;
                // Fetch DM header user photo
                $headerBio = '';
                $hq = $conn->prepare("SELECT \"EmployeeID\" FROM \"prtl_lrn_master_list\" WHERE REPLACE(REPLACE(\"FirstName\" || ' ' || \"LastName\", '  ', ' '), '  ', ' ') = ? LIMIT 1");
                $hq->execute([$headerTitle]);
                if ($hr = $hq->fetch(PDO::FETCH_ASSOC))
                    $headerBio = $hr['EmployeeID'];

                $msgSql = "SELECT m.*, e.\"EmployeeID\", r.sender as reply_sender, r.message as reply_text
                           FROM \"prtl_Messages\" m
                           LEFT JOIN \"prtl_lrn_master_list\" e ON REPLACE(REPLACE(\"FirstName\" || ' ' || \"LastName\", '  ', ' '), '  ', ' ') = REPLACE(REPLACE(m.sender, '  ', ' '), '  ', ' ')
                           LEFT JOIN \"prtl_Messages\" r ON m.reply_to_id = r.id
                           WHERE (REPLACE(REPLACE(m.sender, '  ', ' '), '  ', ' ') = ? AND REPLACE(REPLACE(m.receiver, '  ', ' '), '  ', ' ') = ?) 
                              OR (REPLACE(REPLACE(m.sender, '  ', ' '), '  ', ' ') = ? AND REPLACE(REPLACE(m.receiver, '  ', ' '), '  ', ' ') = ?) 
                           ORDER BY m.sent_at ASC";
                $mq = $conn->prepare($msgSql);
                $mq->execute([$user1, $user2, $user2, $user1]);
                while ($m = $mq->fetch(PDO::FETCH_ASSOC))
                    $messages[] = $m;
            }
            ?>

            <!-- Header -->
            <div
                class="bg-white px-4 py-3 border-b border-gray-200 flex justify-between items-center shadow-sm z-10 h-[60px]">
                <div class="flex items-center gap-3">
                    <?php
                    $initial = strtoupper(substr($headerTitle, 0, 1));
                    $storyInfo = $activeNotes[$headerTitle] ?? null;
                    $hasStory = ($storyInfo && !empty($storyInfo['image_path'])) ? true : false;
                    $isSeen = $storyInfo && ($storyInfo['is_seen'] ?? 0) == 1;
                    $headerAvatarClass = $hasStory ? ($isSeen ? 'border-2 border-gray-300 p-[1px]' : 'border-2 border-pink-500 p-[1px]') : 'border border-gray-100';
                    $headerBgClass = ($isGroup || empty($headerBio)) ? 'bg-gradient-to-br from-pink-500 to-pink-600' : 'bg-white';
                    ?>
                    <div class="w-10 h-10 rounded-full <?= $headerBgClass ?> text-white flex items-center justify-center text-sm font-bold shadow-sm overflow-hidden <?= $hasStory ? 'cursor-pointer hover:scale-105 transition-transform' : '' ?>"
                        onclick="<?= $hasStory ? "window.location.href='admin.php?page=chatnow&view_story=" . urlencode($headerTitle) . "'" : "" ?>">
                        <?php if (!$isGroup && !empty($headerBio)): ?>
                            <img src="<?= getEmployeePhotoUrl($headerBio) ?>" alt="<?= htmlspecialchars($headerTitle) ?>"
                                class="w-full h-full object-cover <?= $headerAvatarClass ?> rounded-full">
                        <?php elseif ($isGroup && !empty($gcPhotoPath)): ?>
                            <img src="assets/<?= htmlspecialchars($gcPhotoPath) ?>" alt="<?= htmlspecialchars($headerTitle) ?>"
                                class="w-full h-full object-cover border border-gray-100 rounded-full">
                        <?php else: ?>
                            <?= $isGroup ? '#' : $initial ?>
                        <?php endif; ?>
                    </div>
                    <div class="leading-none">
                        <?php if ($isGroup): ?>
                            <button onclick="document.getElementById('groupMembersModal').classList.remove('hidden')"
                                class="font-bold text-[17px] text-gray-900 hover:text-pink-600 hover:underline text-left transition-colors">
                                <?= htmlspecialchars($headerTitle) ?>
                            </button>
                            <div class="text-[13px] text-gray-500 cursor-pointer hover:text-pink-600 transition-colors"
                                onclick="document.getElementById('groupMembersModal').classList.remove('hidden')">
                                <?= count($groupMembers) ?> members
                            </div>
                        <?php else: ?>
                            <h3 class="font-bold text-[17px] text-gray-900"><?= htmlspecialchars($headerTitle) ?></h3>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <!-- Call Button -->
                    <button onclick="callUser()"
                        class="w-10 h-10 rounded-full bg-gray-50 hover:bg-pink-50 text-gray-400 hover:text-pink-500 flex items-center justify-center transition-all"
                        title="Call <?= htmlspecialchars($headerTitle) ?>">
                        <i class="fa-solid fa-phone text-[14px]"></i>
                    </button>

                    <!-- More Actions (3 Dots) -->
                    <div class="relative">
                        <button onclick="toggleChatActionsMenu(event)"
                            class="w-10 h-10 rounded-full bg-gray-50 hover:bg-gray-100 text-gray-400 hover:text-gray-600 flex items-center justify-center transition-all"
                            title="More Actions">
                            <i class="fa-solid fa-ellipsis-vertical text-[14px]"></i>
                        </button>

                        <!-- Actions Dropdown -->
                        <div id="chatActionsMenu"
                            class="absolute right-0 top-full mt-2 hidden bg-white rounded-2xl shadow-2xl border border-gray-100 py-2 min-w-[220px] z-[100] overflow-hidden animate-fade-in-up">
                            <button onclick="deleteConversation()"
                                class="w-full px-3 py-3 text-left text-red-500 hover:bg-red-50 transition-all duration-200 flex items-center gap-3 group">
                                <div
                                    class="w-10 h-10 rounded-xl bg-red-50 group-hover:bg-red-100 flex items-center justify-center transition-colors shrink-0">
                                    <i class="fa-solid fa-trash-can text-sm"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-[15px] leading-none text-red-600">Delete</span>
                                    <span class="text-[12px] text-red-400 font-medium mt-1 leading-none">Conversation</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Group Settings Modal -->
            <?php if ($isGroup): ?>
                <div id="groupMembersModal"
                    class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm animate-fade-in"
                    onclick="if(event.target===this) this.classList.add('hidden')">
                    <div
                        class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-gray-100 overflow-hidden transform transition-all scale-100 flex flex-col max-h-[90vh]">
                        <!-- Modal Scrollable Content -->
                        <div class="overflow-y-auto custom-scrollbar flex-1">
                            <div
                                class="p-4 border-b border-gray-100 flex justify-between items-center sticky top-0 bg-white/80 backdrop-blur-md z-10">
                                <h3 class="font-bold text-lg text-gray-800">Group Settings</h3>
                                <button onclick="document.getElementById('groupMembersModal').classList.add('hidden')"
                                    class="text-gray-400 hover:text-gray-600">
                                    <i class="fa-solid fa-xmark text-xl"></i>
                                </button>
                            </div>

                            <div class="p-6 space-y-6">
                                <!-- GC Photo & Name -->
                                <div class="flex flex-col items-center gap-4">
                                    <div class="relative group cursor-pointer"
                                        onclick="document.getElementById('gcPhotoInput').click()">
                                        <div
                                            class="w-24 h-24 rounded-full bg-pink-100 border-4 border-white shadow-lg overflow-hidden flex items-center justify-center text-3xl text-pink-500 font-bold">
                                            <?php if (!empty($gcPhotoPath)): ?>
                                                <img id="gcPreview" src="assets/<?= htmlspecialchars($gcPhotoPath) ?>"
                                                    class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <span id="gcInitial">#</span>
                                                <img id="gcPreview" src="" class="w-full h-full object-cover hidden">
                                            <?php endif; ?>
                                        </div>
                                        <div
                                            class="absolute inset-0 bg-black/40 rounded-full opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                            <i class="fa-solid fa-camera text-white text-xl"></i>
                                        </div>
                                        <input type="file" id="gcPhotoInput" class="hidden" accept="image/*"
                                            onchange="previewGCPhoto(this)">
                                    </div>
                                    <div class="w-full">
                                        <label
                                            class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Group
                                            Name</label>
                                        <div class="relative">
                                            <input type="text" id="gcNameInput" value="<?= htmlspecialchars($headerTitle) ?>"
                                                class="w-full bg-gray-50 border border-gray-200 text-gray-800 p-3 rounded-xl focus:border-pink-500 focus:ring-1 focus:ring-pink-500 outline-none transition-all font-semibold">
                                            <button onclick="saveGCSettings()"
                                                class="absolute right-2 top-1.5 bg-pink-500 hover:bg-pink-600 text-white text-xs px-3 py-1.5 rounded-lg shadow-sm transition-all">Save</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add Employee Section -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Add
                                        Employee</label>
                                    <div class="relative">
                                        <i class="fa-solid fa-user-plus absolute left-3 top-3.5 text-gray-400 text-xs"></i>
                                        <input type="text" placeholder="Search by name..." oninput="searchAddMember(this.value)"
                                            class="w-full bg-gray-50 border border-gray-200 text-gray-800 pl-9 p-3 rounded-xl focus:border-pink-500 focus:ring-1 focus:ring-pink-500 outline-none transition-all">
                                        <div id="addMemberResults"
                                            class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-100 rounded-xl shadow-xl z-20 hidden custom-scrollbar max-h-48 overflow-y-auto">
                                        </div>
                                    </div>
                                </div>

                                <!-- Member List -->
                                <div>
                                    <label
                                        class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 ml-1">Members
                                        (<?= count($groupMembers) ?>)</label>
                                    <div class="space-y-1">
                                        <?php foreach ($groupMembers as $member):
                                            $mName = $member['participant_name'];
                                            $mEmp = $member['EmployeeID'];
                                            ?>
                                            <div
                                                class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-xl group transition-all">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-gray-200 border border-pink-50 overflow-hidden shrink-0">
                                                        <img src="<?= getEmployeePhotoUrl($mEmp) ?>"
                                                            alt="<?= htmlspecialchars($mName) ?>" class="w-full h-full object-cover"
                                                            onerror="this.onerror=null; this.src='<?= DEFAULT_AVATAR_URL ?>'">
                                                    </div>
                                                    <span class="font-medium text-gray-700"><?= htmlspecialchars($mName) ?></span>
                                                </div>
                                                <button onclick="removeGCMember('<?= addslashes($mName) ?>')"
                                                    class="text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all p-2">
                                                    <i class="fa-solid fa-user-slash"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- prtl_Messages Area -->
            <div class="message-content custom-scrollbar" id="msgContainer">
                <div class="mt-auto"></div>
                <?php
                $lastTimestamp = null;
                $msgCount = count($messages);
                foreach ($messages as $index => $msg):
                    $isMe = ($msg['sender'] === $fullname);
                    $align = $isMe ? 'right' : 'left';
                    $currentTimestamp = $msg['sent_at'];
                    $showSeparator = false;

                    if ($lastTimestamp === null) {
                        $showSeparator = true;
                    } else {
                        $interval = $currentTimestamp->getTimestamp() - $lastTimestamp->getTimestamp();
                        if ($interval > 1800) { // 30 mins
                            $showSeparator = true;
                        }
                    }

                    if ($showSeparator):
                        $isToday = $currentTimestamp->format('Y-m-d') === date('Y-m-d');
                        $format = $isToday ? 'h:i A' : 'M j, h:i A';
                        ?>
                        <div class="timestamp-sep"><?= $currentTimestamp->format($format) ?></div>
                    <?php endif;
                    $lastTimestamp = $currentTimestamp;
                    $msgText = $msg['message'] ?? '';
                    ?>
                    <div id="msg-container-<?= $msg['id'] ?>"
                        class="msg-row <?= $align ?> group/row transition-all duration-500 origin-right">
                        <?php if (!$isMe): ?>
                            <?php
                            $sender = $msg['sender'];
                            $storyInfo = $activeNotes[$sender] ?? null;
                            $hasStory = ($storyInfo && !empty($storyInfo['image_path'])) ? true : false;
                            $isSeen = $storyInfo && ($storyInfo['is_seen'] ?? 0) == 1;
                            $msgAvatarClass = $hasStory ? ($isSeen ? 'border border-gray-300 p-[1px]' : 'border border-pink-500 p-[1px]') : 'border border-white';
                            ?>
                            <div class="avatar-xs bg-gray-300 shrink-0 overflow-hidden <?= $hasStory ? 'cursor-pointer' : '' ?>"
                                onclick="<?= $hasStory ? "window.location.href='admin.php?page=chatnow&view_story=" . urlencode($sender) . "'" : "" ?>">
                                <?php if (!empty($msg['EmployeeID'])): ?>
                                    <img src="<?= getEmployeePhotoUrl($msg['EmployeeID']) ?>"
                                        alt="<?= htmlspecialchars($msg['sender']) ?>"
                                        class="w-full h-full object-cover rounded-full <?= $msgAvatarClass ?>">
                                <?php else: ?>
                                    <div
                                        class="w-full h-full flex items-center justify-center text-[10px] text-gray-600 font-bold <?= $msgAvatarClass ?>">
                                        <?= strtoupper(substr($msg['sender'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div id="msg-<?= $msg['id'] ?>" class="bubble relative"
                            title="<?= $msg['sent_at']->format('M d, h:i A') ?>">

                            <!-- Action Buttons -->
                            <div
                                class="absolute top-1/2 -translate-y-1/2 <?= $isMe ? '-left-20' : '-right-20' ?> flex items-center gap-1 opacity-0 group-hover/row:opacity-100 transition-all z-20">
                                <!-- React Button -->
                                <button onclick="openReactMenu(event, <?= $msg['id'] ?>)"
                                    class="h-8 w-8 rounded-full bg-white border border-gray-100 shadow-sm flex items-center justify-center text-gray-400 hover:text-pink-500 hover:bg-gray-50 transition-colors focus:outline-none">
                                    <i class="fa-regular fa-face-smile text-[14px]"></i>
                                </button>
                                <!-- Reply Button -->
                                <button
                                    onclick="setReply(<?= $msg['id'] ?>, '<?= addslashes($msg['sender']) ?>', '<?= addslashes(preg_replace('/\s+/', ' ', mb_strimwidth($msgText, 0, 50, "..."))) ?>')"
                                    class="h-8 w-8 rounded-full bg-white border border-gray-100 shadow-sm flex items-center justify-center text-gray-400 hover:text-blue-500 hover:bg-gray-50 transition-colors focus:outline-none">
                                    <i class="fa-solid fa-reply text-[13px]"></i>
                                </button>
                                <?php if ($isMe): ?>
                                    <!-- Delete Button -->
                                    <button onclick="deleteMessage(<?= $msg['id'] ?>)"
                                        class="h-8 w-8 rounded-full bg-white border border-gray-100 shadow-sm flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-gray-50 transition-colors focus:outline-none">
                                        <i class="fa-solid fa-trash text-[13px]"></i>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <?php if (!$isMe && $isGroup): ?>
                                <span class="bubble-meta"
                                    style="font-size:11px; margin-bottom:2px; font-weight:600"><?= htmlspecialchars($msg['sender']) ?></span>
                            <?php endif; ?>

                            <?php
                            $reaction = '';
                            if (preg_match('/ \[React:(.*?)\]$/s', $msgText, $matches)) {
                                $msgText = preg_replace('/ \[React:.*?\]$/s', '', $msgText);
                                $reaction = $matches[1];
                            }
                            ?>
                            <?php if (!empty($msg['reply_to_id'])): ?>
                                <div class="mb-2 p-2 bg-black/5 rounded-lg border-l-4 border-pink-400 text-[12px] opacity-80 cursor-pointer hover:bg-black/10 transition-colors"
                                    onclick="scrollToMessage(<?= $msg['reply_to_id'] ?>)">
                                    <div class="font-bold text-pink-600 mb-0.5">
                                        Replying to <?= htmlspecialchars(explode(' ', $msg['reply_sender'])[0]) ?>
                                    </div>
                                    <div class="italic truncate max-w-[200px]">
                                        <?= htmlspecialchars($msg['reply_text'] ?: 'Attachment') ?>
                                    </div>
                                </div>
                            <?php elseif (strpos($msgText, "[StoryReply] ") === 0):
                                $storyReplyContent = substr($msgText, 13);
                                $msgText = $storyReplyContent; // The main bubble will only show the emoji/text
                                ?>
                                <div class="mb-2 p-2 bg-black/5 rounded-lg border-l-4 border-pink-400 text-[12px] opacity-80">
                                    <div class="font-bold text-pink-600 mb-0.5">
                                        Replied to <?= $isMe ? "their" : "your" ?> story
                                    </div>
                                    <div class="italic truncate max-w-[200px]">
                                        Story
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?= nl2br(htmlspecialchars($msgText)) ?>

                            <?php if (!empty($msg['attachment_path'])):
                                $attPath = $msg['attachment_path'];
                                if (strpos($attPath, 'uploads/') === 0) {
                                    $attPath = 'assets/' . $attPath;
                                }

                                // Check if the attachment is an image
                                $ext = strtolower(pathinfo($attPath, PATHINFO_EXTENSION));
                                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                ?>

                                <?php if ($isImg): ?>
                                    <div class="mt-2 overflow-hidden rounded-lg border border-white/20">
                                        <a href="<?= htmlspecialchars($attPath) ?>" target="_blank">
                                            <img src="<?= htmlspecialchars($attPath) ?>"
                                                class="max-w-full h-auto block hover:opacity-90 transition-opacity"
                                                style="max-height: 300px;">
                                        </a>
                                    </div>
                                <?php else:
                                    $displayFileName = $msg['attachment_name'] ?: basename($attPath);
                                    $fileIcon = 'fa-file';
                                    if (strpos($ext, 'pdf') !== false)
                                        $fileIcon = 'fa-file-pdf text-red-400';
                                    elseif (in_array($ext, ['doc', 'docx']))
                                        $fileIcon = 'fa-file-word text-blue-400';
                                    elseif (in_array($ext, ['xls', 'xlsx']))
                                        $fileIcon = 'fa-file-excel text-green-400';
                                    elseif (in_array($ext, ['ppt', 'pptx']))
                                        $fileIcon = 'fa-file-powerpoint text-orange-400';
                                    elseif (in_array($ext, ['zip', 'rar']))
                                        $fileIcon = 'fa-file-archive text-yellow-500';
                                    ?>
                                    <div
                                        class="mt-2 text-xs bg-white/20 p-2.5 rounded-xl border border-white/10 flex items-center gap-3 text-inherit group/file hover:bg-white/30 transition-all">
                                        <i class="fa-solid <?= $fileIcon ?> text-lg"></i>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-bold truncate" title="<?= htmlspecialchars($displayFileName) ?>">
                                                <?= htmlspecialchars($displayFileName) ?>
                                            </div>
                                            <div class="opacity-60 uppercase text-[10px] font-bold"><?= $ext ?> File</div>
                                        </div>
                                        <a href="<?= htmlspecialchars($attPath) ?>" target="_blank"
                                            class="w-7 h-7 rounded-lg bg-black/10 flex items-center justify-center hover:bg-black/20 transition-colors shrink-0">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($reaction): ?>
                                <div class="absolute -bottom-3.5 <?= $isMe ? '-left-2' : '-right-2' ?> bg-white rounded-full px-1.5 py-0.5 shadow-md border border-gray-100 text-[14px] leading-none cursor-pointer hover:scale-110 transition-transform z-20 flex items-center justify-center"
                                    onclick="openReactMenu(event, <?= $msg['id'] ?>)">
                                    <?= htmlspecialchars($reaction) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($isMe && $index === ($msgCount - 1)): ?>
                        <div class="msg-status pr-1">
                            <?= $msg['is_read'] ? 'Read' : 'Sent' ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (empty($messages)): ?>
                    <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                            <div class="w-12 h-12 rounded-full bg-pink-500"></div>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800">No messages yet</h3>
                        <p class="text-sm">Start the conversation!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Reply Preview Area -->
            <div id="replyPreview"
                class="hidden px-4 py-2 border-t border-gray-100 bg-gray-50 flex items-center gap-3 animate-slide-up">
                <div class="flex-1 min-w-0 border-l-4 border-pink-500 pl-3 py-1">
                    <div class="text-[12px] font-bold text-pink-600">Replying to <span id="replySenderName">User</span>
                    </div>
                    <div id="replyTextPreview" class="text-[13px] text-gray-500 truncate italic">Message content...</div>
                </div>
                <button onclick="clearReply()"
                    class="w-8 h-8 rounded-full hover:bg-gray-200 flex items-center justify-center text-gray-400 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Reply Input -->
            <div class="reply-bar">
                <!-- Icons -->
                <div class="flex gap-1 text-pink-600">
                    <button type="button" class="btn-icon text-[20px]"
                        onclick="document.getElementById('fileInput').click()" title="Attach File">
                        <i class="fa-solid fa-circle-plus"></i>
                    </button>
                    <button type="button" class="btn-icon text-[20px]"
                        onclick="document.getElementById('imageInput').click()" title="Upload Image">
                        <i class="fa-regular fa-image"></i>
                    </button>
                </div>

                <form id="chatForm" class="flex-1 flex items-center gap-2" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?= $isGroup ? 'send_group_message' : 'send_dm' ?>">
                    <input type="hidden" name="cid" value="<?= $cid ?>">
                    <input type="hidden" name="to" value="<?= htmlspecialchars($user2) ?>">
                    <input type="hidden" id="replyToInput" name="reply_to" value="">

                    <!-- Hidden Inputs -->
                    <input type="file" id="fileInput" name="attachment" class="hidden" onchange="handleFileSelect(this)">
                    <input type="file" id="imageInput" name="image" accept="image/*" class="hidden"
                        onchange="handleFileSelect(this)">

                    <div class="relative flex-1 w-full">
                        <input type="text" name="message" id="messageInput" class="reply-input w-full pr-12"
                            placeholder="Aa" autocomplete="off">
                        <button type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-pink-500 hover:text-pink-700"
                            onclick="toggleEmojiPicker()">
                            <i class="fa-regular fa-face-smile text-lg"></i>
                        </button>
                    </div>


                    <button type="submit" class="btn-icon text-pink-600 hover:bg-gray-100">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>

                <button type="button" class="btn-icon text-pink-600" onclick="sendLike()">
                    <i class="fa-solid fa-thumbs-up"></i>
                </button>
            </div>

        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-full text-gray-500 select-none">
                <img src="assets/lrn-logo.jpg" alt="La Rose Noire" class="w-32 opacity-50 mb-4">
                <h3 class="text-xl font-bold text-gray-700">ChatNow by La Rose Noire</h3>
                <p class="text-gray-400 mt-2">Pick a person from the left to start chatting.</p>
            </div>
        <?php endif; ?>

        <!-- New Message Modal (Simple) -->
        <div id="newMsgModal"
            class="hidden absolute inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-2xl w-96 border border-gray-100 shadow-xl">
                <h3 class="text-gray-800 font-bold mb-4 text-lg">New Message</h3>
                <div class="relative mb-4">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3.5 text-gray-400 text-xs"></i>
                    <input type="text" id="newMsgSearch"
                        class="w-full bg-gray-50 border border-gray-200 text-gray-700 pl-9 p-2.5 rounded-lg focus:border-pink-500 focus:ring-1 focus:ring-pink-500 outline-none text-sm transition-all"
                        placeholder="Search user..." oninput="searchUsers(this.value)">
                </div>
                <div id="searchResults" class="max-h-60 overflow-y-auto mb-4 bg-white space-y-1 custom-scrollbar"></div>
                <button onclick="document.getElementById('newMsgModal').classList.add('hidden')"
                    class="w-full py-2.5 text-gray-500 hover:bg-gray-50 rounded-lg font-medium transition-colors text-sm">Cancel</button>
            </div>
        </div>

        <!-- New Group Chat Modal -->
        <div id="newGroupModal"
            class="hidden absolute inset-0 bg-white/95 backdrop-blur-sm flex items-center justify-center z-50 animate-fade-in">
            <div
                class="bg-white p-6 rounded-2xl w-full max-w-md border border-gray-100 shadow-2xl transform transition-all scale-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-800">Create Group Chat</h3>
                    <button onclick="document.getElementById('newGroupModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Group Name</label>
                        <input type="text" id="newGroupName"
                            class="w-full bg-gray-50 border border-gray-200 text-gray-800 p-3 rounded-xl focus:border-pink-500 focus:ring-1 focus:ring-pink-500 outline-none transition-all placeholder-gray-400"
                            placeholder="e.g. IT Team">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Add Members</label>
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-3.5 text-gray-400 text-xs"></i>
                            <input type="text" id="groupUserSearch"
                                class="w-full bg-gray-50 border border-gray-200 text-gray-800 pl-9 p-3 rounded-xl focus:border-pink-500 focus:ring-1 focus:ring-pink-500 outline-none transition-all placeholder-gray-400"
                                placeholder="Search employees..." oninput="searchGroupUsers(this.value)">
                        </div>
                        <!-- Search Results Dropdown -->
                        <div id="groupSearchResults"
                            class="max-h-40 overflow-y-auto mt-2 bg-white border border-gray-100 rounded-xl shadow-lg custom-scrollbar empty:hidden">
                        </div>
                    </div>

                    <!-- Selected Members -->
                    <div>
                        <div id="selectedMembersList"
                            class="flex flex-wrap gap-2 max-h-32 overflow-y-auto custom-scrollbar min-h-[40px] p-2 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                            <span class="text-xs text-gray-400 italic p-1">No members selected</span>
                        </div>
                    </div>

                    <button onclick="createGroup()"
                        class="w-full bg-pink-600 hover:bg-pink-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-pink-200 transition-all transform active:scale-95 mt-2">
                        Create Group
                    </button>
                </div>
            </div>
        </div>



        <!-- Note/Story Selection Modal -->
        <div id="noteModal"
            class="hidden fixed inset-0 bg-black/60 z-[60] flex items-center justify-center px-4 backdrop-blur-sm animate-fade-in"
            onclick="if(event.target===this) this.classList.add('hidden')">
            <div
                class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6 transform transition-all scale-100 flex flex-col overflow-hidden">

                <!-- Screen 1: Selection -->
                <div id="noteMain" class="flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-xl text-gray-800">What's new?</h3>
                        <button onclick="document.getElementById('noteModal').classList.add('hidden')"
                            class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <button onclick="showNoteEditor()"
                            class="flex flex-col items-center gap-3 p-6 rounded-2xl bg-gray-50 hover:bg-pink-50 hover:text-pink-600 transition-all border border-gray-100 group">
                            <div
                                class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm group-hover:shadow-pink-100 transition-all">
                                <i class="fa-solid fa-quote-left text-xl"></i>
                            </div>
                            <span class="font-bold text-sm">Post a Note</span>
                        </button>

                        <button onclick="showStoryEditor()"
                            class="flex flex-col items-center gap-3 p-6 rounded-2xl bg-gray-50 hover:bg-pink-50 hover:text-pink-600 transition-all border border-gray-100 group">
                            <div
                                class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm group-hover:shadow-pink-100 transition-all">
                                <i class="fa-solid fa-camera text-xl"></i>
                            </div>
                            <span class="font-bold text-sm">Post a Story</span>
                        </button>
                    </div>
                </div>

                <!-- Screen 2: Note Editor -->
                <div id="noteEditor" class="hidden flex flex-col">
                    <div class="flex items-center gap-3 mb-6">
                        <button onclick="backToNoteMain()"
                            class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-gray-200">
                            <i class="fa-solid fa-arrow-left text-xs"></i>
                        </button>
                        <h3 class="font-bold text-lg text-gray-800">Post a Note</h3>
                    </div>

                    <div class="relative w-full mb-6">
                        <input type="text" id="noteInput" maxlength="60" placeholder="What's on your mind?"
                            class="w-full border-2 border-gray-100 bg-gray-50 rounded-2xl px-4 py-4 text-base focus:outline-none focus:border-pink-400 focus:bg-white focus:ring-4 focus:ring-pink-50 transition-all text-center font-medium"
                            value="<?= htmlspecialchars($myNote['note_text'] ?? '') ?>">
                        <div class="text-[10px] text-gray-400 mt-2 text-center uppercase tracking-widest font-bold">
                            Appears above your profile</div>
                    </div>

                    <button onclick="saveNote('note')"
                        class="w-full py-3 bg-pink-600 text-white text-[15px] font-bold rounded-2xl hover:bg-pink-700 shadow-lg shadow-pink-100 transition-all flex items-center justify-center gap-2">
                        Share Note
                    </button>
                </div>

                <!-- Screen 3: Story Editor -->
                <div id="storyEditor" class="hidden flex flex-col">
                    <div class="flex items-center gap-3 mb-6">
                        <button onclick="backToNoteMain()"
                            class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-gray-200">
                            <i class="fa-solid fa-arrow-left text-xs"></i>
                        </button>
                        <h3 class="font-bold text-lg text-gray-800">Share a Story</h3>
                    </div>

                    <div class="flex flex-col items-center gap-6 mb-6">
                        <div class="relative cursor-pointer group"
                            onclick="document.getElementById('storyImageInput').click()">
                            <div
                                class="w-32 h-32 rounded-3xl overflow-hidden shrink-0 border-2 border-gray-100 shadow-md relative mt-2 rotate-2 group-hover:rotate-0 transition-transform">
                                <img id="storyPreviewImg"
                                    src="<?= ($myNote && !empty($myNote['image_path'])) ? htmlspecialchars($myNote['image_path']) : getEmployeePhotoUrl($employeeId) ?>"
                                    class="w-full h-full object-cover">
                                <div
                                    class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i class="fa-solid fa-camera text-white text-2xl"></i>
                                </div>
                            </div>
                            <div
                                class="absolute -bottom-2 -right-2 w-10 h-10 bg-pink-600 rounded-full flex items-center justify-center shadow-lg border-2 border-white text-white">
                                <i class="fa-solid fa-plus"></i>
                            </div>
                            <input type="file" id="storyImageInput" name="story_image" accept="image/*"
                                capture="environment" class="hidden" onchange="previewStoryImage(this)">
                        </div>

                        <p class="text-[11px] text-gray-500 text-center px-4 leading-relaxed">Images appear in our 24h
                            cinematic viewer. Higher resolution is recommended!</p>
                    </div>

                    <button onclick="saveNote('story')"
                        class="w-full py-4 bg-pink-600 text-white text-[15px] font-bold rounded-2xl hover:bg-pink-700 shadow-lg shadow-pink-100 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> Share Story
                    </button>
                    <!-- Cinematic Call Modal -->
                    <div id="callModal"
                        class="hidden fixed inset-0 bg-black/90 z-[100] flex flex-col items-center justify-center backdrop-blur-xl animate-fade-in text-white p-6">
                        <input type="hidden" id="activeCallId" value="">
                        <input type="hidden" id="callType" value="">
                        <div class="flex flex-col items-center w-full max-w-sm">
                            <!-- Profile Pulse -->
                            <div class="relative mb-12">
                                <div id="callPulse"
                                    class="absolute inset-0 bg-pink-500 rounded-full animate-ping opacity-20 scale-150">
                                </div>
                                <div
                                    class="relative w-32 h-32 rounded-full border-4 border-pink-500 p-1 bg-zinc-900 shadow-2xl overflow-hidden">
                                    <img id="callAvatar" src="<?= DEFAULT_AVATAR_URL ?>"
                                        class="w-full h-full object-cover rounded-full">
                                </div>
                            </div>

                            <h3 id="callName" class="text-3xl font-bold mb-2">Employee Name</h3>
                            <div id="callStatus"
                                class="text-pink-500 font-medium tracking-widest uppercase text-xs mb-16 animate-pulse">
                                Ringing...</div>

                            <!-- Call Controls -->
                            <div class="flex gap-8 items-center">
                                <!-- Accept (Incoming Only) -->
                                <button id="btnAcceptCall" onclick="answerCall()"
                                    class="hidden w-16 h-16 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-500/20 transition-all transform active:scale-90">
                                    <i class="fa-solid fa-phone text-2xl"></i>
                                </button>

                                <!-- Decline/End -->
                                <button id="btnEndCall" onclick="endCall()"
                                    class="w-16 h-16 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center shadow-lg shadow-red-500/20 transition-all transform active:scale-90">
                                    <i class="fa-solid fa-phone-slash text-2xl rotate-[135deg]"></i>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Premium Confirmation Modal -->
                    <div id="confirmModal"
                        class="hidden fixed inset-0 bg-black/60 z-[200] flex items-center justify-center p-4 backdrop-blur-sm animate-fade-in">
                        <div
                            class="bg-white w-full max-w-sm rounded-[24px] shadow-2xl overflow-hidden transform transition-all scale-100 p-6 text-center">
                            <div
                                class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fa-solid fa-trash-can text-2xl"></i>
                            </div>
                            <h3 id="confirmTitle" class="text-xl font-bold text-gray-900 mb-2">Are you sure?</h3>
                            <p id="confirmDesc" class="text-gray-500 text-sm mb-8 leading-relaxed">This action cannot be
                                undone. Are you sure you want to proceed?</p>
                            <div class="flex gap-3">
                                <button id="confirmCancel"
                                    class="flex-1 py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition-all">Cancel</button>
                                <button id="confirmBtn"
                                    class="flex-1 py-3 px-4 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl shadow-lg shadow-red-200 transition-all">Delete</button>
                            </div>
                        </div>
                    </div>

    </main>
</div>

<!-- Chat JS -->
<script>
    // Default Avatar for JS
    const DEFAULT_AVATAR = `<?= DEFAULT_AVATAR_URL ?>`;
    const currentGCMembers = <?= json_encode((isset($isGroup) && $isGroup && isset($groupMembers)) ? array_column($groupMembers, 'participant_name') : []) ?>;
    const myName = <?= json_encode($fullname) ?>;

    function handleAvatarError(img) {
        if (!img.dataset.error) {
            img.dataset.error = "1";
            img.src = DEFAULT_AVATAR;
        }
        img.onerror = null;
    }

    // Scroll to bottom
    const container = document.getElementById('msgContainer');
    if (container) container.scrollTop = container.scrollHeight;

    function setReply(id, sender, text) {
        const preview = document.getElementById('replyPreview');
        const input = document.getElementById('replyToInput');
        const senderLabel = document.getElementById('replySenderName');
        const textLabel = document.getElementById('replyTextPreview');

        input.value = id;
        senderLabel.innerText = sender.split(' ')[0];
        textLabel.innerText = text;
        preview.classList.remove('hidden');
        document.getElementById('messageInput').focus();
    }

    function clearReply() {
        document.getElementById('replyPreview').classList.add('hidden');
        document.getElementById('replyToInput').value = '';
    }

    function scrollToMessage(id) {
        const el = document.querySelector(`.bubble[data-msg-id="${id}"]`) || document.getElementById(`msg-${id}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('highlight-msg');
            setTimeout(() => el.classList.remove('highlight-msg'), 2000);
        }
    }



    // Send Message
    document.getElementById('chatForm')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('components/chatnow_api.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    // Clear inputs
                    document.getElementById('messageInput').value = '';
                    const fileInput = document.getElementById('fileInput');
                    if (fileInput) fileInput.value = '';
                    const imageInput = document.getElementById('imageInput');
                    if (imageInput) imageInput.value = '';

                    // Clear reply state
                    clearReply();

                    // Fetch current page and extract updated messages container
                    fetch(window.location.href)
                        .then(r => r.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newMsgContainer = doc.getElementById('msgContainer');
                            const currentContainer = document.getElementById('msgContainer');
                            if (newMsgContainer && currentContainer) {
                                currentContainer.innerHTML = newMsgContainer.innerHTML;
                                currentContainer.scrollTop = currentContainer.scrollHeight;
                            }
                        });
                } else {
                    alert('Error sending: ' + (data.error || 'Unknown'));
                }
            });
    });

    function showNewMsg() {
        document.getElementById('newMsgModal').classList.remove('hidden');
        document.getElementById('newMsgSearch').focus();
    }

    function searchUsers(q) {
        const results = document.getElementById('searchResults');
        if (q.length < 2) {
            results.innerHTML = '';
            return;
        }

        const fd = new FormData();
        fd.append('action', 'search_users');
        fd.append('q', q);

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                results.innerHTML = '';
                if (data.results) {
                    data.results.forEach(u => {
                        const div = document.createElement('div');
                        div.className = 'flex items-center gap-3 p-3 hover:bg-gray-50 cursor-pointer rounded-xl border border-transparent hover:border-pink-100 transition-all';
                        div.innerHTML = `
                            <div class="w-10 h-10 rounded-full bg-gray-200 overflow-hidden shrink-0">
                                <img src="${u.bio ? 'http://10.2.0.8/lrnph/emp_photos/' + u.bio + '.jpg' : DEFAULT_AVATAR}" class="w-full h-full object-cover" onerror="handleAvatarError(this)">
                            </div>
                            <div class="flex-1">
                                <div class="text-[14px] font-bold text-gray-800">${u.name}</div>
                                <div class="text-[11px] text-gray-500">${u.dept}</div>
                            </div>
                        `;
                        div.onclick = () => {
                            window.location.href = `admin.php?page=chatnow&user1=${encodeURIComponent(myName)}&user2=${encodeURIComponent(u.name)}`;
                        };
                        results.appendChild(div);
                    });
                }
            });
    }

    function showNewGroup() {
        document.getElementById('newGroupModal').classList.remove('hidden');
    }

    let selectedGroupMembers = [];

    function searchGroupUsers(q) {
        if (q.length < 2) {
            document.getElementById('groupSearchResults').innerHTML = '';
            return;
        }
        const fd = new FormData();
        fd.append('action', 'search_users');
        fd.append('q', q);

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                const res = document.getElementById('groupSearchResults');
                res.innerHTML = '';
                if (data.results) {
                    data.results.forEach(u => {
                        // Skip if already selected OR if it's ME
                        if (selectedGroupMembers.some(m => m.name === u.name)) return;
                        if (u.name === myName) return;

                        const div = document.createElement('div');
                        div.className = 'flex items-center gap-3 p-2 hover:bg-gray-50 cursor-pointer rounded-lg transition-colors';
                        const photoUrl = u.bio ? `http://10.2.0.8/lrnph/emp_photos/${u.bio}.jpg` : DEFAULT_AVATAR;

                        div.innerHTML = `
                             <div class="w-8 h-8 rounded-full bg-gray-200 border border-gray-100 shrink-0 overflow-hidden">
                                 <img src="${photoUrl}" alt="${u.name}" class="w-full h-full object-cover" onerror="handleAvatarError(this)">
                             </div>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-800">${u.name}</div>
                            </div>
                            <div class="text-blue-600 text-xs font-bold">+</div>
                        `;
                        div.onclick = () => addMemberToGroup(u);
                        res.appendChild(div);
                    });
                }
            });
    }

    function addMemberToGroup(u) {
        selectedGroupMembers.push(u);
        renderSelectedMembers();
        document.getElementById('groupUserSearch').value = '';
        document.getElementById('groupSearchResults').innerHTML = '';
    }

    function removeMemberFromGroup(name) {
        selectedGroupMembers = selectedGroupMembers.filter(m => m.name !== name);
        renderSelectedMembers();
    }

    function renderSelectedMembers() {
        const container = document.getElementById('selectedMembersList');
        container.innerHTML = '';
        if (selectedGroupMembers.length === 0) {
            container.innerHTML = '<span class="text-xs text-gray-400 italic p-1">No members selected</span>';
            return;
        }
        selectedGroupMembers.forEach(u => {
            const tag = document.createElement('span');
            tag.className = 'inline-flex items-center pl-1 pr-2 py-1 rounded-full text-xs font-medium bg-pink-100 text-pink-700 gap-2 border border-pink-200';
            const photoUrl = u.bio ? `http://10.2.0.8/lrnph/emp_photos/${u.bio}.jpg` : DEFAULT_AVATAR;
            tag.innerHTML = `
                <div class="w-5 h-5 rounded-full bg-pink-200 overflow-hidden shrink-0">
                    <img src="${photoUrl}" class="w-full h-full object-cover" onerror="handleAvatarError(this)">
                </div>
                <span class="truncate max-w-[120px]">${u.name}</span>
                <button type="button" onclick="removeMemberFromGroup('${u.name}')" class="ml-1 text-pink-900 hover:text-pink-500 focus:outline-none font-bold text-lg leading-3">×</button>
            `;
            container.appendChild(tag);
        });
    }

    function createGroup() {
        const name = document.getElementById('newGroupName').value;
        if (!name) return alert('Please enter a group name');
        if (selectedGroupMembers.length === 0) return alert('Please select at least one member');

        const fd = new FormData();
        fd.append('action', 'create_group');
        fd.append('group_name', name);

        // Pass members as JSON since API uses json_decode
        fd.append('members', JSON.stringify(selectedGroupMembers));

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    window.location.reload();
                } else {
                    alert('Error creating group: ' + (data.error || 'Unknown'));
                }
            });
    }

    function handleFileSelect(input) {
        if (input.files && input.files[0]) {
            // Auto-submit the form when a file is selected
            // Ideally shows a preview but for now we auto-send
            document.getElementById('chatForm').dispatchEvent(new Event('submit'));
        }
    }

    // Initialize Emoji Picker using EmojiButton library
    let picker;
    async function initEmojiPicker() {
        try {
            const { EmojiButton } = await import('https://cdn.jsdelivr.net/npm/@joeattardi/emoji-button@4.6.4/dist/index.min.js');
            picker = new EmojiButton({
                position: 'top-end',
                autoHide: false
            });

            picker.on('emoji', selection => {
                const input = document.getElementById('messageInput');
                input.value += selection.emoji;
                input.focus();
            });
        } catch (e) {
            console.error("EmojiPicker failed to load", e);
        }
    }

    initEmojiPicker();

    function toggleEmojiPicker() {
        if (picker) {
            picker.togglePicker(document.querySelector('.fa-face-smile').parentElement);
        }
    }

    let reactMessageId = null;
    const quickEmojis = ['👍', '❤️', '😂', '😮', '😢'];

    // Create the tooltip container once
    const reactTooltip = document.createElement('div');
    reactTooltip.className = 'absolute hidden bg-white rounded-full shadow-[0_4px_15px_rgba(0,0,0,0.1)] border border-gray-100 flex items-center gap-1 p-1 z-50 transition-all duration-200 transform scale-95 opacity-0';

    quickEmojis.forEach(emoji => {
        const btn = document.createElement('button');
        btn.className = 'w-9 h-9 rounded-full hover:bg-gray-100 text-xl flex items-center justify-center transition-transform hover:scale-125 hover:rotate-12 focus:outline-none focus:bg-gray-100';
        btn.innerHTML = emoji;
        btn.onclick = (e) => {
            e.stopPropagation();
            if (reactMessageId) {
                sendReaction(reactMessageId, emoji);
                closeReactMenu();
            }
        };
        reactTooltip.appendChild(btn);
    });

    document.body.appendChild(reactTooltip);

    function openReactMenu(event, msgId) {
        event.stopPropagation();
        reactMessageId = msgId;

        // Position it just above the clicked button
        const rect = event.currentTarget.getBoundingClientRect();
        reactTooltip.style.left = `${rect.left - 60}px`; // Center roughly over the button
        reactTooltip.style.top = `${rect.top - 50 + window.scrollY}px`;

        reactTooltip.classList.remove('hidden');
        // trigger animation frame
        requestAnimationFrame(() => {
            reactTooltip.classList.remove('scale-95', 'opacity-0');
            reactTooltip.classList.add('scale-100', 'opacity-100');
        });
    }

    function closeReactMenu() {
        if (reactTooltip.classList.contains('hidden')) return;
        reactTooltip.classList.remove('scale-100', 'opacity-100');
        reactTooltip.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            reactTooltip.classList.add('hidden');
            reactMessageId = null;
        }, 200);
    }

    // Close when clicking outside
    document.addEventListener('click', () => {
        closeReactMenu();
    });

    function sendReaction(msgId, emoji) {
        const fd = new FormData();
        fd.append('action', 'react_message');
        fd.append('msg_id', msgId);
        fd.append('emoji', emoji);

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    window.location.reload();
                } else {
                    console.error("Failed to react");
                }
            });
    }


    function previewGCPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const preview = document.getElementById('gcPreview');
                const initial = document.getElementById('gcInitial');
                if (preview) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                if (initial) initial.classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function saveGCSettings() {
        const cid = "<?= $cid ?>";
        const name = document.getElementById('gcNameInput').value;
        const photo = document.getElementById('gcPhotoInput').files[0];

        if (!name) return alert('Name required');

        const fd = new FormData();
        fd.append('action', 'update_gc');
        fd.append('cid', cid);
        fd.append('name', name);
        if (photo) fd.append('photo', photo);

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.ok) window.location.reload();
                else alert(data.error || 'Failed to update');
            });
    }

    function searchAddMember(q) {
        const results = document.getElementById('addMemberResults');
        if (q.length < 2) {
            results.classList.add('hidden');
            return;
        }

        const fd = new FormData();
        fd.append('action', 'search_users');
        fd.append('q', q);

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                results.innerHTML = '';
                if (data.results && data.results.length > 0) {
                    const filtered = data.results.filter(u => !currentGCMembers.includes(u.name));
                    if (filtered.length > 0) {
                        results.classList.remove('hidden');
                        filtered.forEach(u => {
                            const div = document.createElement('div');
                            div.className = 'flex items-center gap-3 p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-0';
                            const photoUrl = u.bio ? `http://10.2.0.8/lrnph/emp_photos/${u.bio}.jpg` : DEFAULT_AVATAR;
                            div.innerHTML = `
                                <img src="${photoUrl}" class="w-8 h-8 rounded-full object-cover border border-gray-100" onerror="handleAvatarError(this)">
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-gray-800">${u.name}</div>
                                    <div class="text-[10px] text-gray-400 italic">${u.dept}</div>
                                </div>
                            `;
                            div.onclick = () => addMemberToGC(u.name);
                            results.appendChild(div);
                        });
                    } else {
                        results.classList.add('hidden');
                    }
                } else {
                    results.classList.add('hidden');
                }
            });
    }

    function addMemberToGC(name) {
        const cid = "<?= $cid ?>";
        const fd = new FormData();
        fd.append('action', 'add_gc_member');
        fd.append('cid', cid);
        fd.append('name', name);

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.ok) window.location.reload();
                else alert(data.error || 'Failed to add');
            });
    }

    function removeGCMember(name) {
        if (!confirm('Remove ' + name + ' from group?')) return;
        const cid = "<?= $cid ?>";
        const fd = new FormData();
        fd.append('action', 'remove_gc_member');
        fd.append('cid', cid);
        fd.append('name', name);

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.ok) window.location.reload();
                else alert(data.error || 'Failed to remove');
            });
    }

    function sendLike() {
        const input = document.getElementById('messageInput');
        const originalVal = input.value;
        input.value = '👍';
        // Create an event to submit
        const event = new Event('submit', {
            'bubbles': true,
            'cancelable': true
        });
        document.getElementById('chatForm').dispatchEvent(event);
        // Restore/Clear? If like is sent, usually acts as a message. 
        // We'll clear after submit logic handles it.
        setTimeout(() => input.value = '', 100);
    }

    function filterConversations() {
        const input = document.getElementById('sidebarSearchInput');
        const filter = input.value.toUpperCase();
        const links = document.querySelectorAll('.chat-sidebar .conv-list .conv-link');

        links.forEach(link => {
            const txtValue = link.textContent || link.innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                link.style.display = "flex";
            } else {
                link.style.display = "none";
            }
        });

        // Optional: Hide section headers if no items below them?
        // For now, simpler is better as requested. User just wants search to work for names and groups.
    }

    function previewStoryImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('storyPreviewImg').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function openNoteModal() {
        document.getElementById('noteMain').classList.remove('hidden');
        document.getElementById('noteEditor').classList.add('hidden');
        document.getElementById('storyEditor').classList.add('hidden');
        document.getElementById('noteModal').classList.remove('hidden');
    }

    function showNoteEditor() {
        document.getElementById('noteMain').classList.add('hidden');
        document.getElementById('noteEditor').classList.remove('hidden');
        setTimeout(() => document.getElementById('noteInput').focus(), 50);
    }

    function showStoryEditor() {
        document.getElementById('noteMain').classList.add('hidden');
        document.getElementById('storyEditor').classList.remove('hidden');
    }

    function backToNoteMain() {
        document.getElementById('noteEditor').classList.add('hidden');
        document.getElementById('storyEditor').classList.add('hidden');
        document.getElementById('noteMain').classList.remove('hidden');
    }

    function saveNote(type) {
        const text = document.getElementById('noteInput').value.trim();
        const imageFile = document.getElementById('storyImageInput').files[0];

        const fd = new FormData();
        fd.append('action', 'save_note');

        if (type === 'note') {
            fd.append('note_text', text);
            // Don't append image for plain notes
        } else {
            if (imageFile) {
                fd.append('note_image', imageFile);
            } else if (!confirm('You haven\'t selected an image for your story. Do you want to post it anyway?')) {
                return;
            }
            // Optional: Include text in story too? Or clear it? 
            // User said "If it's a note, display it above pfp", but story-viewing usually doesn't show notes now.
            // Let's keep the text if they want it.
            fd.append('note_text', text);
        }

        // Change button state
        const activeBtn = document.querySelector(`#${type}Editor button.bg-pink-600`);
        const origText = activeBtn.innerHTML;
        activeBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        activeBtn.disabled = true;

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    window.location.reload();
                } else {
                    alert('Error saving: ' + (data.error || 'Unknown'));
                    activeBtn.innerHTML = origText;
                    activeBtn.disabled = false;
                }
            })
            .catch(() => {
                window.location.reload();
            });
    }

    let currentConfirmCallback = null;

    function showConfirmModal(title, desc, callback) {
        const modal = document.getElementById('confirmModal');
        document.getElementById('confirmTitle').innerText = title;
        document.getElementById('confirmDesc').innerText = desc;
        currentConfirmCallback = callback;

        modal.classList.remove('hidden');

        // Setup listeners
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('confirmCancel');

        const close = () => modal.classList.add('hidden');

        confirmBtn.onclick = () => {
            close();
            if (currentConfirmCallback) currentConfirmCallback();
        };

        cancelBtn.onclick = close;
        modal.onclick = (e) => { if (e.target === modal) close(); };
    }

    function deleteMessage(msgId) {
        const msgEl = document.getElementById(`msg-container-${msgId}`);
        if (!msgEl) return;

        showConfirmModal(
            'Delete Message?',
            'This message will be permanently removed for everyone.',
            () => {
                const fd = new FormData();
                fd.append('action', 'delete_message');
                fd.append('msg_id', msgId);

                // Visual fade out
                msgEl.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                msgEl.style.opacity = '0';
                msgEl.style.transform = 'scale(0.9) translateX(40px)';
                msgEl.style.maxHeight = '0';
                msgEl.style.marginBottom = '0';
                msgEl.style.marginTop = '0';
                msgEl.style.pointerEvents = 'none';

                fetch('components/chatnow_api.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.ok) {
                            setTimeout(() => msgEl.remove(), 600);
                        } else {
                            msgEl.style.opacity = '1';
                            msgEl.style.transform = 'scale(1)';
                            msgEl.style.maxHeight = '1000px';
                            alert('Error: ' + data.error);
                        }
                    });
            }
        );
    }

    function deleteConversation() {
        showConfirmModal(
            'Delete Conversation?',
            'All messages in this chat will be lost forever. Continue?',
            () => {
                const fd = new FormData();
                fd.append('action', 'delete_conversation');
                fd.append('cid', <?= json_encode($cid) ?>);
                fd.append('other', <?= json_encode($user2) ?>);

                fetch('components/chatnow_api.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.ok) {
                            // Cinematic fade out of the entire thread
                            const thread = document.querySelector('.thread');
                            if (thread) {
                                thread.style.transition = 'all 0.8s ease';
                                thread.style.opacity = '0';
                                thread.style.filter = 'blur(20px)';
                            }
                            setTimeout(() => window.location.href = 'admin.php?page=chatnow', 800);
                        } else {
                            alert('Error: ' + data.error);
                        }
                    });
            }
        );
    }

    function callUser() {
        const otherUser = <?= json_encode($user2) ?>;
        if (!otherUser) return;

        const fd = new FormData();
        fd.append('action', 'start_call');
        fd.append('receiver', otherUser);

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    showCallModal('outgoing', otherUser, `<?= getEmployeePhotoUrl(isset($headerBio) ? $headerBio : '') ?>`);
                } else {
                    alert('Call failed: ' + (data.error || 'Unknown error'));
                }
            });
    }

    function showCallModal(type, name, photo, callId = '') {
        const modal = document.getElementById('callModal');
        const nameEl = document.getElementById('callName');
        const avatarEl = document.getElementById('callAvatar');
        const statusEl = document.getElementById('callStatus');
        const btnAccept = document.getElementById('btnAcceptCall');
        const activeCallIdInput = document.getElementById('activeCallId');
        const callTypeInput = document.getElementById('callType');

        nameEl.innerText = name;
        avatarEl.src = photo || DEFAULT_AVATAR;
        activeCallIdInput.value = callId;
        callTypeInput.value = type;

        if (type === 'incoming') {
            statusEl.innerText = 'Incoming Call...';
            btnAccept.classList.remove('hidden');
        } else {
            statusEl.innerText = 'Ringing...';
            btnAccept.classList.add('hidden');
        }

        modal.classList.remove('hidden');
    }

    function answerCall() {
        const callId = document.getElementById('activeCallId').value;
        const fd = new FormData();
        fd.append('action', 'handle_call_action');
        fd.append('call_id', callId);
        fd.append('status', 'accepted');

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(() => {
                document.getElementById('callStatus').innerText = 'In Call';
                document.getElementById('btnAcceptCall').classList.add('hidden');
                document.getElementById('callPulse').classList.remove('animate-ping');
            });
    }

    function endCall() {
        const callId = document.getElementById('activeCallId').value;
        const fd = new FormData();
        fd.append('action', 'handle_call_action');
        fd.append('call_id', callId);
        fd.append('status', 'ended');

        fetch('components/chatnow_api.php', { method: 'POST', body: fd })
            .then(() => {
                document.getElementById('callModal').classList.add('hidden');
            });
    }

    function checkCallStatus() {
        fetch('components/chatnow_api.php?action=check_call_status')
            .then(res => res.json())
            .then(data => {
                const modal = document.getElementById('callModal');
                const isModalOpen = !modal.classList.contains('hidden');

                if (data.type === 'incoming') {
                    if (!isModalOpen) {
                        const photo = data.eid ? `http://10.2.0.8/lrnph/emp_photos/${data.eid}.jpg` : DEFAULT_AVATAR;
                        showCallModal('incoming', data.caller, photo, data.call_id);
                    }
                } else if (data.type === 'outgoing') {
                    if (isModalOpen) {
                        if (data.status === 'accepted') {
                            document.getElementById('callStatus').innerText = 'Call Accepted';
                            document.getElementById('callPulse').classList.remove('animate-ping');
                        } else if (data.status === 'declined' || data.status === 'ended') {
                            modal.classList.add('hidden');
                        }
                    }
                } else if (data.type === 'none') {
                    if (isModalOpen) {
                        modal.classList.add('hidden');
                    }
                }
            });
    }

    // Poll for calls every 3 seconds
    setInterval(checkCallStatus, 3000);

    function toggleChatActionsMenu(e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('chatActionsMenu');
        if (menu) menu.classList.toggle('hidden');
    }

    // Outside click handlers
    document.addEventListener('click', function (e) {
        // Story menu
        const storyMenu = document.getElementById('storyMenu');
        if (storyMenu && !e.target.closest('#storyMenu') && !e.target.closest('button[onclick*="toggleStoryMenu"]')) {
            storyMenu.classList.add('hidden');
        }

        // Chat actions menu
        const chatMenu = document.getElementById('chatActionsMenu');
        if (chatMenu && !e.target.closest('#chatActionsMenu') && !e.target.closest('button[onclick*="toggleChatActionsMenu"]')) {
            chatMenu.classList.add('hidden');
        }
    });
</script>