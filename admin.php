<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';
require_once 'includes/auth_session.php';
require_once 'includes/photo_helper.php';

// User Data from Session
$userName = $_SESSION['fullname'] ?? "Verified User";
$userPosition = $_SESSION['position'] ?? "Staff";
$userPhoto = getEmployeePhotoUrl($_SESSION['employee_id'] ?? '');
$currentDate = date("F j, Y");

// Page Routing Control
$page = $_GET['page'] ?? 'dashboard';

// Fetch User Permissions (Needed for Sidebar and Routing)
$userPerms = [];
if (isset($conn) && isset($_SESSION['username'])) {
    // Updated to perm_key
    $permSql = "SELECT perm_key FROM portal_user_access WHERE username = ?";
    $permStmt = sqlsrv_query($conn, $permSql, array($_SESSION['username']));
    if ($permStmt) {
        while ($pRow = sqlsrv_fetch_array($permStmt, SQLSRV_FETCH_ASSOC)) {
            $userPerms[] = $pRow['perm_key'];
        }
    }
}

// Default permissions for new users (Unmanaged)
// If a user has NO records in portal_user_access, we assume they are a standard user
// and grant access to Dashboard, Planner, and Common apps by default.
// Explicitly managed users will have at least 'dashboard' and 'planner' records.
if (empty($userPerms)) {
    $userPerms = ['dashboard', 'planner', 'common', 'chatnow', 'emeals', 'emeals_settings'];
}
$isIT = (isset($_SESSION['department']) && preg_match('/IT|INFORMATION TECHNOLOGY/i', $_SESSION['department']));

// Fetch Real System Metadata
$totalApps = 0;
$activeAnnouncements = 0;

if (isset($conn)) {
    // Count Applications
    $appCountRes = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM portal_apps");
    if ($appCountRes && $row = sqlsrv_fetch_array($appCountRes, SQLSRV_FETCH_ASSOC)) {
        $totalApps = $row['total'];
    }

    // Count Announcements
    $annCountRes = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM portal_announcements WHERE is_active = 1");
    if ($annCountRes && $row = sqlsrv_fetch_array($annCountRes, SQLSRV_FETCH_ASSOC)) {
        $activeAnnouncements = $row['total'];
    }

    // Fetch Scheduled Plans (Meetings) for Dashboard Widget
    $scheduledPosts = [];
    $currentUserId = $_SESSION['username'] ?? '';
    $today = date('Y-m-d');

    // Fetch top 20 upcoming meetings (Facilitator OR Attendee)
    // We join AP_Attendees to check for participation
    $schQuery = "SELECT DISTINCT TOP 20 ps.*, cat.category_name
                 FROM LRNPH_OJT.db_datareader.AP_Meetings ps
                 LEFT JOIN LRNPH_OJT.db_datareader.AP_Attendees pma ON ps.meeting_id = pma.meeting_id
                 LEFT JOIN LRNPH_OJT.db_datareader.AP_Categories cat ON ps.category_id = cat.category_id
                 WHERE ps.meeting_date >= ?
                 AND (ps.facilitator = ? OR pma.employee_id = ?) 
                 ORDER BY ps.meeting_date ASC, ps.start_time ASC";

    $schStmt = sqlsrv_query($conn, $schQuery, array($today, $currentUserId, $currentUserId));

    if ($schStmt) {
        while ($row = sqlsrv_fetch_array($schStmt, SQLSRV_FETCH_ASSOC)) {
            // Fetch Creator (Facilitator) Name
            $creatorName = "Unknown";
            if (!empty($row['facilitator'])) {
                $creatorSql = "SELECT FirstName, LastName FROM LRNPH_E.dbo.lrn_master_list WHERE BiometricsID = ?";
                $creatorStmt = sqlsrv_query($conn, $creatorSql, array($row['facilitator']));
                if ($creatorStmt && $cRow = sqlsrv_fetch_array($creatorStmt, SQLSRV_FETCH_ASSOC)) {
                    $creatorName = $cRow['FirstName'] . ' ' . $cRow['LastName'];
                } else {
                    $creatorName = $row['facilitator']; // Fallback
                }
            }

            // Fetch Attendees (Names from AP_Attendees)
            $attendees = [];
            $attSql = "SELECT attendee_name FROM LRNPH_OJT.db_datareader.AP_Attendees WHERE meeting_id = ?";
            $attStmt = sqlsrv_query($conn, $attSql, array($row['meeting_id']));
            if ($attStmt) {
                while ($att = sqlsrv_fetch_array($attStmt, SQLSRV_FETCH_ASSOC)) {
                    $attendees[] = $att['attendee_name'];
                }
            }

            // Fetch First Agenda Item for Description
            $agendaText = '';
            $agSql = "SELECT TOP 1 topic FROM LRNPH_OJT.db_datareader.AP_MeetingAgenda WHERE meeting_id = ?";
            $agStmt = sqlsrv_query($conn, $agSql, array($row['meeting_id']));
            if ($agStmt && $agRow = sqlsrv_fetch_array($agStmt, SQLSRV_FETCH_ASSOC)) {
                $agendaText = $agRow['topic'];
            }

            // Determine Subtitle (Category or Venue)
            $subtitle = $row['venue'];
            if (!empty($row['category_name'])) {
                $subtitle = $row['category_name'];
            } elseif (!empty($row['custom_category_text'])) {
                $subtitle = $row['custom_category_text'];
            }

            $scheduledPosts[] = [
                'id' => $row['meeting_id'],
                'title' => $row['meeting_name'],
                'description' => $agendaText, // First agenda item
                'date' => $row['meeting_date']->format('M d, Y'),
                'time' => $row['start_time']->format('h:i A'),
                // Map end_time to platform field for list display
                'platform' => $row['end_time'] ? $row['end_time']->format('H:i') : '',
                'account' => $subtitle,
                'image' => !empty($row['image_url']) ? $row['image_url'] : 'assets/lrn-logo.jpg',
                'creator' => $creatorName,
                'attendees' => $attendees,
                'is_creator' => ($row['facilitator'] == $currentUserId)
            ];
        }
    }
}

// System Metadata Cards (Replacing Analytics Stats)
$stats = [
    [
        "title" => "Total Applications",
        "value" => $totalApps,
        "change" => "Portal Tools",
        "is_increase" => true,
        "period" => "Installed",
        "icon" => "fa-layer-group",
        "color" => "pink"
    ],
    [
        "title" => "Live News",
        "value" => $activeAnnouncements,
        "change" => "Announcements",
        "is_increase" => true,
        "period" => "Published",
        "icon" => "fa-bullhorn",
        "color" => "purple"
    ],
    [
        "title" => "Portal Status",
        "value" => "Online",
        "change" => "Healthy",
        "is_increase" => true,
        "period" => "Operational",
        "icon" => "fa-circle-check",
        "color" => "emerald"
    ]
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Management Dashboard</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Three.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- GSAP -->
    <!-- GSAP Removed -->
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>

<body class="bg-[#f4f7fa] text-[#1a1a1a] flex h-screen overflow-hidden">

    <!-- Sidebar Component -->
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content Container -->
    <main
        class="flex-1 flex flex-col lg:ml-[284px] ml-0 h-full overflow-hidden min-h-0 transition-all duration-500 ease-in-out">

        <!-- Header Container (Not flex-1) -->
        <div class="px-4 lg:px-6 py-3 shrink-0">
            <?php include 'components/header.php'; ?>
        </div>

        <div
            class="flex-1 flex flex-col px-4 lg:px-6 pb-4 min-h-0 <?= ($page === 'dashboard' || $page === 'emeals') ? 'overflow-hidden' : 'overflow-y-auto' ?> custom-scrollbar animate-in fade-in duration-700">
            <?php
            // Fetch User Permissions (Already fetched earlier for sidebar)
            
            // Restricted access check
            // Define restricted pages here. If page is in this list, check permissions.
            $restrictedPages = ['content', 'announcements', 'user_management', 'planner', 'new_employee', 'settings'];

            // Allow if IT OR explicitly granted
            $hasAccess = $isIT || in_array($page, $userPerms);

            // Special Case removed: Dashboard and Planner now follow standard permission rules
            // if ($page === 'dashboard' || $page === 'planner') ...
            
            if (in_array($page, $restrictedPages) && !$hasAccess) {
                echo "<div class='flex-1 flex items-center justify-center p-4 lg:p-10 text-center'>
                        <div class='bg-red-50 text-red-500 p-8 rounded-2xl inline-block max-w-md w-full border border-red-100 shadow-sm'>
                            <i class='fa-solid fa-lock text-5xl mb-4 opacity-80'></i>
                            <h1 class='text-2xl font-bold mb-2'>Access Denied</h1>
                            <p class='text-sm opacity-80 leading-relaxed'>You do not have permission to view the <span class='font-bold'>" . ucfirst(str_replace('_', ' ', $page)) . "</span> page.<br>Please contact your administrator if you believe this is an error.</p>
                            <a href='admin.php?page=dashboard' class='mt-6 inline-block px-6 py-2 bg-red-500 text-white text-sm font-bold rounded-xl shadow-lg shadow-red-200 hover:bg-red-600 transition-colors'>Return to Dashboard</a>
                        </div>
                     </div>";
            }
            // Dashboard Page
            elseif ($page === 'dashboard') {
                // Welcome Banner
                echo '<div class="mb-6 shrink-0">';
                include 'components/welcome.php';
                echo '</div>';
                ?>
                <!-- Dashboard Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-[2.3fr_1.1fr] gap-6 flex-1 min-h-0 overflow-hidden no-scrollbar">
                    <!-- Left Column -->
                    <div class="flex flex-col gap-6 h-full min-h-0">
                        <!-- Stats Cards Component (Fixed height/Auto) -->
                        <div class="shrink-0">
                            <?php include 'components/kpi_cards.php'; ?>
                        </div>
                        <!-- Live Overview (Flexible, takes remaining space) -->
                        <div class="flex-1 flex flex-col min-h-0 lg:overflow-hidden h-full lg:h-auto">
                            <?php include 'components/live_overview.php'; ?>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="flex flex-col gap-6 h-full min-h-0 pb-6 lg:pb-0">
                        <!-- Calendar Widget Component (Fixed height) -->
                        <div class="shrink-0 h-[380px]">
                            <?php include 'components/calendar.php'; ?>
                        </div>
                        <!-- Schedule Post Widget Component (Flexible, takes remaining space) -->
                        <div class="flex-1 flex flex-col min-h-0 lg:overflow-hidden mt-3 h-full lg:h-auto">
                            <?php include 'components/scheduled_plans.php'; ?>
                        </div>
                    </div>
                </div>
                <?php
            }
            // Content Manager Page
            elseif ($page === 'content') {
                include 'components/content_manager.php';
            }
            // Planner Page
            elseif ($page === 'planner') {
                include 'components/planner.php';
            }
            // Announcements Page
            elseif ($page === 'announcements') {
                include 'components/announcements.php';
            }
            // User Management Page
            elseif ($page === 'user_management') {
                include 'components/user_management.php';
            }
            // New Employee Page
            elseif ($page === 'new_employee') {
                include 'components/new_employee.php';
            }
            // Settings Page
            elseif ($page === 'settings') {
                include 'components/settings.php';
            }
            // ChatNow Page
            elseif ($page === 'chatnow') {
                include 'components/chatnow.php';
            }
            // E-Meals Page
            elseif ($page === 'emeals') {
                include 'components/emeals.php';
            }
            // E-Meals Settings Page
            elseif ($page === 'emeals_settings') {
                include 'components/emeals_settings.php';
            }
            // 404 / Default
            elseif ($page !== 'dashboard') {
                echo "<div class='flex-1 flex flex-col items-center justify-center text-center p-10'>
                        <div class='bg-gray-50 rounded-full h-24 w-24 flex items-center justify-center mb-4'>
                            <i class='fa-solid fa-ghost text-4xl text-gray-300'></i>
                        </div>
                        <h2 class='text-xl font-bold text-gray-800 mb-1'>Page Not Found</h2>
                        <p class='text-gray-400 text-sm'>We couldn't find the page you're looking for.</p>
                        <a href='admin.php?page=dashboard' class='mt-6 px-6 py-2.5 bg-white border border-gray-200 text-gray-600 text-sm font-bold rounded-xl hover:bg-gray-50 transition-colors'>Go Home</a>
                      </div>";
            }
            ?>
        </div>
    </main>
    <?php include 'components/help_modal.php'; ?>
    <?php include 'components/meeting_notifications.php'; ?>
    <?php include 'components/error_modal.php'; ?>
    <?php include 'components/confirm_modal.php'; ?>
</body>

</html>