<?php
require_once __DIR__ . '/includes/db.php';
// require_once 'includes/auth_session.php'; // Removed to allow public access
require_once 'includes/photo_helper.php';

// User Data
$isLoggedIn = isset($_SESSION['username']); // check if user is logged in
$userName = $_SESSION['fullname'] ?? "Guest User";
$userPosition = $_SESSION['position'] ?? "Visitor";
// Only try to get photo if logged in, otherwise default
$userPhoto = $isLoggedIn ? getEmployeePhotoUrl($_SESSION['employee_id'] ?? '') : DEFAULT_AVATAR_URL;

// Fetch Data
// 1. Headlines (Headlines)
$headlines = [];
$hlQuery = "SELECT * FROM prtl_portal_announcements WHERE type = 'headline' AND is_active = 1 ORDER BY created_at DESC LIMIT 1";
$hlStmt = $conn->query($hlQuery);
if ($hlStmt && $row = $hlStmt->fetch(PDO::FETCH_ASSOC)) {
    $headlines[] = $row;
}

// 2. Apps
$apps = [];
$appQuery = "SELECT * FROM prtl_portal_apps WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
$appStmt = $conn->query($appQuery);
if ($appStmt) {
    while ($row = $appStmt->fetch(PDO::FETCH_ASSOC)) {
        $apps[] = $row;
    }
}

// 3. Side Announcements
$announcements = [];
$annQuery = "SELECT * FROM prtl_portal_announcements WHERE type = 'announcement' AND is_active = 1 ORDER BY created_at DESC LIMIT 10";
$annStmt = $conn->query($annQuery);
if ($annStmt) {
    while ($row = $annStmt->fetch(PDO::FETCH_ASSOC)) {
        $announcements[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal - La Rose Noire</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Three.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <!-- GSAP Libs -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
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

        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        /* Flip Animation Styles */
        .perspective-1000 {
            perspective: 1000px;
        }

        .transform-style-3d {
            transform-style: preserve-3d;
        }

        .backface-hidden {
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .rotate-y-180 {
            transform: rotateY(180deg);
        }

        /* Initial Hidden States for GSAP */
        nav,
        .headline-banner,
        #appsContainer,
        .sidebar-widget,
        .app-card,
        #headlineActionPill {
            opacity: 0;
            visibility: hidden;
            will-change: transform, opacity;
        }

        /* Refined Floating Animation */
        @keyframes pillFloat {

            0%,
            100% {
                transform: translateY(0) scale(1) rotate(0deg);
            }

            50% {
                transform: translateY(-6px) scale(1.02) rotate(0.5deg);
            }
        }

        .animate-pill-float {
            animation: pillFloat 4s ease-in-out infinite;
        }

        body.sidebar-open main {
            z-index: 60 !important;
        }
    </style>
    <style id="theme-overrides">
        /* Dynamic Theme Colors will be injected here */
    </style>
    <style>
        @keyframes gradientMove {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        @keyframes breathe {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            33% {
                transform: translate(2%, 2%) scale(1.1);
            }

            66% {
                transform: translate(-1%, 3%) scale(0.95);
            }
        }

        .animate-breathe {
            animation: breathe 15s ease-in-out infinite;
        }

        @keyframes shimmerStreak {
            0% {
                transform: translateX(-150%) skewX(-45deg);
                opacity: 0;
            }

            50% {
                opacity: 0.2;
            }

            100% {
                transform: translateX(150%) skewX(-45deg);
                opacity: 0;
            }
        }

        .animate-streak {
            animation: shimmerStreak 10s linear infinite;
        }

        .animate-gradient {
            background-size: 400% 400%;
            animation: gradientMove 15s ease infinite;
        }

        /* Optimization for Tablet/Mobile */
        @media (max-width: 1024px) {

            .blur-\[120px\],
            .blur-\[140px\],
            .blur-\[150px\],
            .animate-streak {
                display: none !important;
            }

            .animate-breathe,
            .animate-gradient {
                animation-duration: 40s !important;
            }

            #bgCanvas {
                display: none !important;
            }

            .shadow-2xl,
            .shadow-xl,
            .shadow-lg {
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1) !important;
            }
        }

        @media (max-width: 768px) {
            #heroCanvas {
                display: none !important;
            }
        }
    </style>
</head>

<body class="bg-[#f4f7fa] text-[#1a1a1a] h-screen overflow-hidden flex flex-col relative">

    <!-- Dynamic Animated Gradient Background -->
    <div class="fixed inset-0 z-[-10] overflow-hidden pointer-events-none transition-all duration-700"
        id="mainBackground">
        <!-- Three.js Background Layer (Liquid Silk) -->
        <canvas id="bgCanvas" class="absolute inset-0 w-full h-full opacity-40"></canvas>

        <!-- Floating Orbs -->
        <div class="absolute top-[-10%] left-[-10%] w-[60%] h-[60%] rounded-full opacity-15 blur-[120px] blob-1 transition-colors duration-700"
            style="animation-delay: 0s;"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[70%] h-[70%] rounded-full opacity-15 blur-[140px] animate-breathe blob-2 transition-colors duration-700"
            style="animation-delay: -5s; animation-direction: reverse;"></div>

        <!-- Inside Effects: Moving Light Streaks -->
        <div class="absolute inset-y-0 left-0 w-1/3 bg-white/5 blur-[120px]" style="animation-delay: 2s; opacity: 0.5;">
        </div>
        <div class="absolute inset-y-0 left-0 w-1/2 bg-white/10 blur-[150px]"
            style="animation-delay: 6s; opacity: 0.5;"></div>

        <!-- Texture Overlay -->
        <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
            style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
    </div>

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 glass-effect border-b border-gray-100 shadow-sm shrink-0">
        <div class="w-full mx-auto px-6 h-20 flex justify-between items-center relative">
            <!-- Brand Left -->
            <div class="flex items-center gap-4">
                <!-- Brand Identity -->
                <img src="assets/centralpoint_no-bg.png" alt="Logo" id="mainLogo" class="h-10 w-auto object-contain">
                <div class="h-8 w-px bg-gray-200 ml-4"></div>
                <h1 class="text-lg font-bold text-gray-700 tracking-tight ml-4">Employee Portal</h1>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-3">
                <!-- Mobile Right Sidebar Toggle -->
                <button onclick="toggleRightSidebar()"
                    class="lg:hidden w-10 h-10 rounded-full bg-white text-gray-500 hover:text-pink-600 hover:bg-pink-50 transition-all flex items-center justify-center border border-gray-100 shadow-sm relative z-20">
                    <i class="fa-regular fa-calendar-days text-lg"></i>
                    <!-- Notification Dot (Optional) -->
                    <span class="absolute top-2 right-2.5 w-2 h-2 bg-pink-500 rounded-full border border-white"></span>
                </button>

                <button onclick="openHelpModal()"
                    class="w-9 h-9 rounded-full bg-white text-gray-400 hover:text-pink-600 hover:bg-pink-50 transition-all flex items-center justify-center border border-gray-100 shadow-sm"
                    title="Help & Support">
                    <i class="fa-regular fa-circle-question text-lg"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Sidebar space reclaimed -->

    <!-- Main Content -->
    <main
        class="flex-1 w-full mx-auto p-4 md:p-6 lg:px-8 grid grid-cols-1 lg:grid-cols-[1fr_380px] lg:grid-rows-[minmax(0,1fr)] gap-8 min-h-0 overflow-y-auto lg:overflow-hidden">
        <!-- Global Mobile Backdrop -->
        <div id="globalBackdrop" onclick="closeAllSidebars()"
            class="fixed inset-0 bg-black/30 backdrop-blur-sm z-[60] hidden transition-all duration-300 opacity-0 pointer-events-none">
        </div>

        <!-- Left Column (Headlines + Apps) -->
        <div class="flex flex-col gap-6 min-w-0 h-auto lg:h-full">

            <!-- Headline Banner -->
            <?php if (!empty($headlines)):
                $hl = $headlines[0]; ?>
                <div class="headline-banner relative w-full rounded-[24px] overflow-hidden shadow-lg group shrink-0">
                    <!-- Background Gradient/Image -->
                    <div class="absolute inset-0 bg-gradient-to-r headline-gradient">
                        <!-- Three.js Canvas Container -->
                        <div id="heroCanvas" class="absolute inset-0 opacity-80"></div>
                    </div>

                    <!-- Placeholder for pill placement -->

                    <div
                        class="relative z-10 p-5 md:p-6 flex flex-col md:flex-row items-center justify-between gap-3 text-white h-full min-h-[110px]">

                        <!-- Adaptive Action Pill (Integrated in 3D Space) -->
                        <div id="headlineActionPill"
                            class="absolute top-3 right-3 flex items-center gap-1.5 p-1 rounded-full border shadow-2xl transition-all duration-500 scale-90 origin-top-right">
                            <a href="index.php"
                                class="pill-btn w-7 h-7 flex items-center justify-center rounded-full transition-all group/dash relative active"
                                title="Dashboard">
                                <i class="fa-solid fa-house text-[10px]"></i>
                                <span
                                    class="absolute top-9 right-0 bg-gray-900 text-white text-[9px] font-bold px-1.5 py-0.5 rounded opacity-0 group-hover/dash:opacity-100 transition-opacity whitespace-nowrap pointer-events-none uppercase tracking-tighter shadow-xl">Dashboard</span>
                            </a>
                            <div class="w-px h-2.5 bg-current opacity-20"></div>
                            <button onclick="openSettingsModal()"
                                class="pill-btn w-7 h-7 flex items-center justify-center rounded-full transition-all group/set relative"
                                title="Settings">
                                <i class="fa-solid fa-sliders text-[10px]"></i>
                                <span
                                    class="absolute top-9 right-0 bg-gray-900 text-white text-[9px] font-bold px-1.5 py-0.5 rounded opacity-0 group-hover/set:opacity-100 transition-opacity whitespace-nowrap pointer-events-none uppercase tracking-tighter shadow-xl">Customize
                                    Portal</span>
                            </button>
                            <div class="w-px h-2.5 bg-current opacity-20"></div>
                            <a href="<?php echo $isLoggedIn ? '/admin.php' : '/login.php'; ?>"
                                class="pill-btn w-7 h-7 flex items-center justify-center rounded-full transition-all group/login relative hover:bg-white/20"
                                title="Core Access">
                                <i class="fa-solid <?php echo $isLoggedIn ? 'fa-shield-halved' : 'fa-right-to-bracket'; ?> text-[10px]"></i>
                                <span
                                    class="absolute top-9 right-0 bg-gray-900 text-white text-[9px] font-bold px-1.5 py-0.5 rounded opacity-0 group-hover/login:opacity-100 transition-opacity whitespace-nowrap pointer-events-none uppercase tracking-tighter shadow-xl">Core Access</span>
                            </a>
                        </div>
                        <div class="flex-1 max-w-2xl pr-20">
                            <span id="weatherStatusPill"
                                class="inline-block px-2 py-0.5 rounded-full bg-white/20 backdrop-blur-md border border-white/20 text-[9px] font-bold uppercase tracking-wider mb-1.5 shadow-sm">
                                <i class="fa-solid fa-cloud-sun mr-1.5"></i> Fetching Weather...
                            </span>
                            <h2 class="text-xl md:text-2xl font-bold mb-1 leading-snug tracking-tight">
                                <?php echo htmlspecialchars($hl['title']); ?>
                            </h2>
                            <p class="text-sm text-pink-100 leading-relaxed opacity-90 font-light line-clamp-2">
                                <?php echo htmlspecialchars($hl['description']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Apps Grid -->
            <div id="appsContainer"
                class="apps-gradient backdrop-blur-xl border border-white/50 shadow-2xl rounded-[32px] flex-1 overflow-hidden relative flex flex-col transition-all duration-500">

                <!-- Advanced Decorative Background -->
                <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
                    <!-- Dynamic Mesh Background -->
                    <div class="absolute inset-0 opacity-30"
                        style="background-image: radial-gradient(circle at 20% 30%, var(--tw-gradient-from) 0%, transparent 50%), radial-gradient(circle at 80% 70%, var(--tw-gradient-to) 0%, transparent 50%); filter: blur(60px);">
                    </div>

                    <!-- Decorative Dots Grid -->
                    <div class="absolute inset-0 opacity-[0.03]"
                        style="background-image: radial-gradient(circle, currentColor 1px, transparent 1px); background-size: 32px 32px;">
                    </div>
                </div>

                <!-- Container Header -->
                <div
                    class="sticky top-0 z-30 flex flex-col md:flex-row items-center justify-between px-8 py-2.5 bg-white/60 backdrop-blur-md border-b border-white/20 shadow-sm gap-4 transition-colors duration-500 container-header">
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <img src="assets/lrn-logo.jpg" alt="App Icon"
                                class="w-12 h-12 rounded-2xl object-contain shadow-xl shadow-pink-500/20 border-2 border-white">
                            <div
                                class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white rounded-full">
                            </div>
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-gray-800 tracking-tight leading-none mb-1">
                                My Applications
                            </h3>
                            <p
                                class="text-[10px] uppercase font-bold text-pink-600 tracking-widest opacity-80 current-selection-text">
                                Personalized Dashboard
                            </p>
                        </div>
                    </div>

                    <div class="relative group w-full md:w-72">
                        <i
                            class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm group-focus-within:text-pink-500 transition-colors"></i>
                        <input type="text" id="appSearch" placeholder="Filter your workspace..."
                            class="pl-11 pr-5 py-3 rounded-2xl border border-white/60 bg-white/40 text-sm focus:border-pink-500 focus:bg-white focus:ring-8 focus:ring-pink-500/5 outline-none w-full transition-all shadow-inner placeholder:text-gray-400">
                    </div>
                </div>

                <!-- Apps Content -->
                <div class="flex-1 overflow-y-auto custom-scrollbar relative z-10 p-3">
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-5 gap-2.5" id="appsGrid">
                        <?php if (empty($apps)): ?>
                            <div
                                class="col-span-full py-24 text-center text-gray-400 bg-white/20 backdrop-blur-md rounded-[32px] border border-dashed border-gray-300/50">
                                <div
                                    class="w-20 h-20 bg-white/50 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-xl">
                                    <i class="fa-regular fa-folder-open text-3xl opacity-30 text-gray-400"></i>
                                </div>
                                <h4 class="text-xl font-bold text-gray-800 mb-2">Workspace Empty</h4>
                                <p class="text-sm font-medium opacity-60">No active applications found in your portal.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($apps as $app): ?>
                                <a href="<?php echo htmlspecialchars($app['url']); ?>" target="_blank"
                                    data-app-id="<?php echo $app['id']; ?>"
                                    data-sort-order="<?php echo $app['sort_order'] ?? 99; ?>"
                                    class="app-card group relative p-2.5 rounded-[16px] bg-white/40 border border-white/60 hover:border-pink-300 shadow-lg hover:shadow-[0_12px_30px_rgba(236,72,153,0.1)] transition-all duration-500 flex flex-col gap-1.5 h-full overflow-hidden hover:-translate-y-1">

                                    <!-- Card Shine Effect -->
                                    <div
                                        class="absolute inset-0 bg-gradient-to-tr from-white/0 via-white/0 to-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                    </div>

                                    <!-- Top Row -->
                                    <div class="flex items-start justify-between relative z-10">
                                        <div
                                            class="w-9 h-9 rounded-[12px] bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center text-white text-base shadow-2xl shadow-pink-500/40 group-hover:scale-110 group-hover:rotate-3 transition-all duration-500 ease-out">
                                            <i
                                                class="<?php echo !empty($app['icon']) ? $app['icon'] : 'fa-solid fa-layer-group'; ?>"></i>
                                        </div>
                                        <button onclick="toggleFavorite(event, this, '<?php echo $app['id']; ?>')"
                                            class="w-7 h-7 rounded-lg bg-white/80 backdrop-blur-md border border-white/50 text-gray-300 hover:text-red-500 hover:border-red-100 hover:bg-white transition-all shadow-sm flex items-center justify-center group/fav">
                                            <i
                                                class="fa-regular fa-heart text-sm group-hover/fav:scale-110 transition-transform"></i>
                                        </button>
                                    </div>

                                    <!-- Content -->
                                    <div class="relative z-10 flex-1 flex flex-col justify-between">
                                        <div>
                                            <h4
                                                class="app-name font-black text-gray-800 text-[11px] leading-[1.2] group-hover:text-pink-600 transition-colors duration-300 min-h-[28px] line-clamp-2">
                                                <?php echo htmlspecialchars($app['name']); ?>
                                            </h4>
                                            <div
                                                class="flex items-center gap-2 mt-1.5 opacity-0 group-hover:opacity-100 transition-all duration-500 transform translate-y-2 group-hover:translate-y-0">
                                                <span
                                                    class="px-2 py-0.5 rounded-md bg-pink-100 text-pink-600 text-[8px] font-black uppercase tracking-tighter">Launch
                                                    App</span>
                                                <i
                                                    class="fa-solid fa-arrow-right text-pink-500 text-[9px] animate-bounce-horizontal"></i>
                                            </div>
                                        </div>

                                        <!-- Footer Info -->
                                        <div
                                            class="mt-2 flex items-center justify-between border-t border-gray-100/30 pt-1.5 opacity-60 group-hover:opacity-100 transition-opacity">
                                            <span class="text-[7px] font-bold uppercase tracking-widest text-gray-400">System
                                                Tool</span>
                                            <div class="flex items-center gap-1">
                                                <div class="w-1 h-1 rounded-full bg-green-500 animate-pulse"></div>
                                                <span class="text-[7px] font-bold text-gray-500 lowercase">online</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Decorative Ghost Icon -->
                                    <div
                                        class="absolute -right-6 -bottom-6 opacity-[0.04] group-hover:opacity-[0.1] transition-all duration-700 transform group-hover:scale-125 group-hover:-rotate-12 pointer-events-none">
                                        <i
                                            class="<?php echo !empty($app['icon']) ? $app['icon'] : 'fa-solid fa-layer-group'; ?> text-9xl"></i>
                                    </div>

                                    <!-- Background Pattern -->
                                    <div
                                        class="absolute -right-8 -bottom-8 w-40 h-40 bg-gradient-to-br from-pink-500/10 to-transparent rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700">
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column (Sidebar) -->
        <div id="rightSidebar"
            class="fixed inset-y-0 right-0 w-[320px] bg-white lg:bg-transparent shadow-2xl lg:shadow-none z-[70] transform translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col gap-6 h-full p-6 lg:p-0 lg:static lg:w-auto lg:z-auto overflow-y-auto no-scrollbar">

            <!-- Mobile Close Header -->
            <div class="flex justify-between items-center lg:hidden mb-2 pt-2">
                <h3 class="font-bold text-gray-800 text-lg">Updates & Events</h3>
                <button onclick="toggleRightSidebar()"
                    class="w-8 h-8 rounded-full bg-gray-50 text-gray-400 hover:text-gray-600 flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Quick Date & Weather Placeholder (Flip Widget) -->
            <div id="calendarWidget"
                class="perspective-1000 w-full relative group h-[200px] transition-all duration-500 ease-in-out sidebar-widget shrink-0">
                <div id="calendarInner"
                    class="relative w-full h-full transform-style-3d transition-transform duration-700 shadow-lg rounded-[24px]">

                    <!-- Front: Quick Date -->
                    <div id="calendarFront"
                        class="absolute inset-0 w-full h-full backface-hidden bg-gradient-to-br from-[#1a1a1a] to-[#333] rounded-[24px] p-6 text-white flex flex-col justify-between overflow-hidden pointer-events-auto z-20">
                        <button onclick="toggleCalendar()"
                            class="absolute top-4 right-4 text-white/20 hover:text-white transition-colors z-20 p-2 rounded-full hover:bg-white/10">
                            <i class="fa-solid fa-calendar-days text-lg"></i>
                        </button>

                        <div class="relative z-10">
                            <p class="text-white/60 text-sm font-medium uppercase tracking-wider mb-1">
                                <?php echo date('Y'); ?>
                            </p>
                            <h3 class="text-3xl font-bold"><?php echo date('l, M j'); ?></h3>
                        </div>
                        <div class="flex items-center gap-2 mt-4 text-white/80 relative z-10">
                            <i class="fa-regular fa-clock"></i>
                            <span class="font-mono text-lg" id="liveClock"><?php echo date('h:i A'); ?></span>
                        </div>

                        <div class="absolute -bottom-4 -right-4 text-white/5 text-[8rem] pointer-events-none">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                    </div>

                    <!-- Back: Full Calendar -->
                    <div id="calendarBack"
                        class="absolute inset-0 w-full h-full backface-hidden rotate-y-180 bg-white text-gray-800 rounded-[24px] p-2 border border-gray-100 overflow-hidden flex flex-col pointer-events-none z-10 gap-0.5">
                        <div class="flex justify-between items-center shrink-0">
                            <h4 id="calMonthYear" class="font-bold text-gray-800 text-sm"></h4>
                            <div class="flex items-center gap-1">
                                <button onclick="changeMonth(-1)"
                                    class="w-8 h-8 rounded-full hover:bg-gray-100 text-gray-400 flex items-center justify-center transition-colors"><i
                                        class="fa-solid fa-chevron-left text-xs"></i></button>
                                <button onclick="changeMonth(1)"
                                    class="w-8 h-8 rounded-full hover:bg-gray-100 text-gray-400 flex items-center justify-center transition-colors"><i
                                        class="fa-solid fa-chevron-right text-xs"></i></button>
                                <button onclick="toggleCalendar()"
                                    class="w-8 h-8 rounded-full hover:bg-pink-50 text-gray-400 hover:text-pink-500 transition-colors flex items-center justify-center ml-1"><i
                                        class="fa-solid fa-xmark"></i></button>
                            </div>
                        </div>

                        <!-- Days Header -->
                        <div class="grid grid-cols-7 text-center shrink-0 mb-1">
                            <span class="text-[10px] font-bold text-gray-400 uppercase">S</span>
                            <span class="text-[10px] font-bold text-gray-400 uppercase">M</span>
                            <span class="text-[10px] font-bold text-gray-400 uppercase">T</span>
                            <span class="text-[10px] font-bold text-gray-400 uppercase">W</span>
                            <span class="text-[10px] font-bold text-gray-400 uppercase">T</span>
                            <span class="text-[10px] font-bold text-gray-400 uppercase">F</span>
                            <span class="text-[10px] font-bold text-gray-400 uppercase">S</span>
                        </div>

                        <!-- Calendar Grid -->
                        <div id="calGrid" class="grid grid-cols-7 gap-0.5 text-center text-xs flex-1 content-center">
                            <!-- JS Generated -->
                        </div>
                    </div>

                </div>
            </div>

            <!-- Announcements List -->
            <!-- Announcements Carousel -->
            <div
                class="bg-white rounded-[24px] shadow-sm border border-gray-100 flex flex-col overflow-hidden sidebar-widget flex-1 min-h-0">
                <div class="p-4 border-b border-gray-50 flex justify-between items-center bg-gray-50/30">
                    <h3 class="font-bold text-gray-800 text-base">Announcements</h3>
                    <span
                        class="bg-pink-100 text-pink-600 text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo count($announcements); ?>
                        New</span>
                </div>

                <div class="relative overflow-hidden group flex-1 min-h-0">
                    <div class="flex transition-transform duration-500 ease-in-out h-full" id="announcementTrack">
                        <?php if (empty($announcements)): ?>
                            <div class="w-full flex-shrink-0 p-8 text-center text-gray-400">
                                <p class="text-sm">No announcements yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($announcements as $index => $ann): ?>
                                <div class="w-full flex-shrink-0 p-4 h-full overflow-y-auto no-scrollbar">
                                    <?php if (!empty($ann['image_url'])): ?>
                                        <div
                                            class="w-full mb-3 rounded-xl overflow-hidden relative shadow-sm bg-gray-50 border border-gray-100 group cursor-zoom-in">
                                            <img src="<?php echo htmlspecialchars($ann['image_url']); ?>"
                                                class="w-full h-auto object-contain max-h-[320px]"
                                                onclick="openImageModal(this.src)">
                                            <div
                                                class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors pointer-events-none">
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <h4 class="font-bold text-gray-800 text-sm mb-1.5 leading-snug">
                                        <?php echo htmlspecialchars($ann['title']); ?>
                                    </h4>
                                    <?php
                                    $desc = $ann['description'] ?? '';
                                    ?>
                                    <p class="text-xs text-gray-600 leading-relaxed">
                                        <?php echo nl2br(htmlspecialchars($desc)); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-1.5 border-t border-gray-50 flex justify-end items-center bg-gray-50/30">
                    <div class="flex gap-1.5">
                        <button id="prevAnn"
                            class="w-7 h-7 rounded-full bg-white border border-gray-200 text-gray-500 hover:text-pink-600 hover:border-pink-200 hover:shadow-sm flex items-center justify-center transition-all">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </button>
                        <button id="nextAnn"
                            class="w-7 h-7 rounded-full bg-white border border-gray-200 text-gray-500 hover:text-pink-600 hover:border-pink-200 hover:shadow-sm flex items-center justify-center transition-all">
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <!-- Image Zoom Modal -->
    <div id="imageModal"
        class="fixed inset-0 z-[70] hidden bg-black/90 backdrop-blur-sm flex items-center justify-center opacity-0 transition-opacity duration-300"
        onclick="closeImageModal()">
        <button
            class="absolute top-6 right-6 text-white/50 hover:text-white text-4xl Transition-colors focus:outline-none">&times;</button>
        <img id="zoomedImage" src=""
            class="max-w-[95vw] max-h-[95vh] object-contain rounded-lg shadow-2xl transform scale-95 transition-transform duration-300"
            onclick="event.stopPropagation()">
    </div>

    <footer
        class="mt-auto py-4 text-center text-xs text-gray-400 border-t border-gray-100 bg-white/50 backdrop-blur-sm shrink-0 relative z-10">
        <p>&copy; <?php echo date('Y'); ?> <span class="text-pink-600 font-bold">La Rose Noire Philippines</span>. All
            rights reserved.</p>
    </footer>



    <!-- Settings Modal -->
    <div id="settingsModal"
        class="fixed inset-0 z-[60] hidden bg-black/20 backdrop-blur-sm flex items-center justify-center opacity-0 transition-opacity duration-300">
        <div
            class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-8 transform scale-95 transition-transform duration-300 relative border border-white/60">
            <button onclick="closeSettingsModal()"
                class="absolute top-4 right-4 w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <h3 class="text-2xl font-bold text-gray-800 mb-2">My Portal</h3>
            <p class="text-gray-500 text-sm mb-8">Personalize your experience.</p>

            <!-- Headline Animation Toggle -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-2">
                    <label class="font-semibold text-gray-700 flex items-center gap-2">
                        <i class="fa-solid fa-wand-magic-sparkles text-purple-500"></i> Headline Effects
                    </label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="animToggle" class="sr-only peer" checked
                            onchange="toggleAnimation(this.checked)">
                        <div
                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600">
                        </div>
                    </label>
                </div>
                <p class="text-xs text-gray-400">Enable dynamic background particles on the headline card.</p>
            </div>

            <!-- Dark Mode Toggle -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-2">
                    <label class="font-semibold text-gray-700 flex items-center gap-2">
                        <i class="fa-solid fa-moon text-blue-600"></i> Dark Mode
                    </label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="darkToggle" class="sr-only peer"
                            onchange="toggleDarkMode(this.checked)">
                        <div
                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-800">
                        </div>
                    </label>
                </div>
                <p class="text-xs text-gray-400">Switch between light and dark themes.</p>
            </div>

            <!-- Theme Color -->
            <div>
                <label class="font-semibold text-gray-700 block mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-palette text-pink-500"></i> Accent Color
                </label>
                <div class="grid grid-cols-5 gap-3">
                    <button onclick="setTheme('default')"
                        class="w-10 h-10 rounded-full border-2 border-white ring-2 ring-transparent hover:scale-110 transition-all shadow-md focus:ring-pink-300 outline-none"
                        style="background-color: #ec4899;" title="Rose Pink"></button>
                    <button onclick="setTheme('purple')"
                        class="w-10 h-10 rounded-full border-2 border-white ring-2 ring-transparent hover:scale-110 transition-all shadow-md focus:ring-purple-300 outline-none"
                        style="background-color: #a855f7;" title="Royal Purple"></button>
                    <button onclick="setTheme('cyan')"
                        class="w-10 h-10 rounded-full border-2 border-white ring-2 ring-transparent hover:scale-110 transition-all shadow-md focus:ring-cyan-300 outline-none"
                        style="background-color: #06b6d4;" title="Sky Cyan"></button>
                    <button onclick="setTheme('green')"
                        class="w-10 h-10 rounded-full border-2 border-white ring-2 ring-transparent hover:scale-110 transition-all shadow-md focus:ring-emerald-300 outline-none"
                        style="background-color: #10b981;" title="Fresh Green"></button>
                    <button onclick="setTheme('yellow')"
                        class="w-10 h-10 rounded-full border-2 border-white ring-2 ring-transparent hover:scale-110 transition-all shadow-md focus:ring-yellow-300 outline-none"
                        style="background-color: #facc15;" title="Sunny Yellow"></button>
                    <!-- New Themes -->
                    <button onclick="setTheme('blue')"
                        class="w-10 h-10 rounded-full border-2 border-white ring-2 ring-transparent hover:scale-110 transition-all shadow-md focus:ring-blue-300 outline-none"
                        style="background-color: #5e9fe8;" title="Sky Blue"></button>
                    <button onclick="setTheme('red')"
                        class="w-10 h-10 rounded-full border-2 border-white ring-2 ring-transparent hover:scale-110 transition-all shadow-md focus:ring-red-300 outline-none"
                        style="background-color: #a41313;" title="Crimson Red"></button>
                    <button onclick="setTheme('brown')"
                        class="w-10 h-10 rounded-full border-2 border-white ring-2 ring-transparent hover:scale-110 transition-all shadow-md focus:ring-amber-900 outline-none"
                        style="background-color: #554436;" title="Espresso Brown"></button>
                    <button onclick="setTheme('forest')"
                        class="w-10 h-10 rounded-full border-2 border-white ring-2 ring-transparent hover:scale-110 transition-all shadow-md focus:ring-green-700 outline-none"
                        style="background-color: #395b40;" title="Forest Green"></button>
                    <button onclick="setTheme('lavender')"
                        class="w-10 h-10 rounded-full border-2 border-white ring-2 ring-transparent hover:scale-110 transition-all shadow-md focus:ring-purple-300 outline-none"
                        style="background-color: #aa98d3;" title="Soft Lavender"></button>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end">
                <button onclick="closeSettingsModal()"
                    class="px-6 py-2 rounded-xl bg-gray-900 text-white font-medium hover:bg-gray-800 transition-colors shadow-lg shadow-gray-200">Done</button>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div id="helpModal"
        class="fixed inset-0 z-[60] hidden bg-black/20 backdrop-blur-sm flex items-center justify-center opacity-0 transition-opacity duration-300">
        <div
            class="bg-white rounded-3xl shadow-2xl w-full max-w-lg p-8 transform scale-95 transition-transform duration-300 relative border border-white/60">
            <button onclick="closeHelpModal()"
                class="absolute top-4 right-4 w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="text-center mb-8">
                <div
                    class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center mx-auto mb-4 icon-gradient text-white shadow-lg icon-shadow">
                    <i class="fa-regular fa-life-ring text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800">Welcome to the CentralPoint </h3>
                <p class="text-gray-500 text-sm">Here's how to make the most of your experience.</p>
            </div>

            <div class="space-y-6">
                <!-- Step 1 -->
                <div class="flex gap-4">
                    <div
                        class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 text-sm">Access Applications</h4>
                        <p class="text-gray-500 text-xs leading-relaxed mt-1">Browse company apps in the main grid. Use
                            the <strong class="text-gray-700">Search Bar</strong> to find specific tools quickly.</p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="flex gap-4">
                    <div
                        class="w-10 h-10 rounded-full bg-red-50 text-red-500 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 text-sm">Favorite Your Top Apps</h4>
                        <p class="text-gray-500 text-xs leading-relaxed mt-1">Click the <strong
                                class="text-gray-700">Heart Icon</strong> on any app card to pin it to the top of your
                            list for easy access.</p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="flex gap-4">
                    <div
                        class="w-10 h-10 rounded-full bg-purple-50 text-purple-500 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 text-sm">Personalize Your Theme</h4>
                        <p class="text-gray-500 text-xs leading-relaxed mt-1">Open <strong
                                class="text-gray-700">Settings</strong> from the floating menu to change the portal's
                            accent color to Pink, Purple, Cyan, Green, or Yellow.</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end">
                <button onclick="closeHelpModal()"
                    class="px-6 py-2 rounded-xl bg-gray-900 text-white font-medium hover:bg-gray-800 transition-colors shadow-lg shadow-gray-200">Got
                    it!</button>
            </div>
        </div>
    </div>

    <script>
        // Three.js Implementation for Headline Banner with Weather Integration
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('heroCanvas');
            if (!container) return;

            // Optimization: Skip or reduce on small screens
            const isLowPower = window.innerWidth < 1024;
            // Setup Basic Scene
            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(75, container.offsetWidth / container.offsetHeight, 0.1, 1000);
            const renderer = new THREE.WebGLRenderer({
                alpha: true,
                antialias: !isLowPower
            });

            renderer.setSize(container.offsetWidth, container.offsetHeight);
            container.appendChild(renderer.domElement);
            camera.position.z = 5;

            // Weather State
            let currentWeather = 'clear';
            let weatherParticles = [];
            let particleSystem;
            let clock = new THREE.Clock();
            let lightningFlash = null;

            // Lightning Bolt System
            let lightningBolts = [];

            function createBolt() {
                const startX = (Math.random() - 0.5) * 20;
                const startY = 10;
                const startZ = (Math.random() - 0.5) * 5 - 2; // Keep in background

                const points = [];
                let currentX = startX;
                let currentY = startY;
                let currentZ = startZ;

                points.push(new THREE.Vector3(currentX, currentY, currentZ));

                // Zig-zag down
                while (currentY > -10) {
                    currentX += (Math.random() - 0.5) * 1.5;
                    currentY -= Math.random() * 1.5;
                    currentZ += (Math.random() - 0.5) * 0.5;
                    points.push(new THREE.Vector3(currentX, currentY, currentZ));
                }

                // Geometry
                const geometry = new THREE.BufferGeometry().setFromPoints(points);
                const material = new THREE.LineBasicMaterial({
                    color: 0xffffff,
                    linewidth: 2, // Note: linewidth is 1 on WebGL usually
                    transparent: true,
                    opacity: 1
                });

                const bolt = new THREE.Line(geometry, material);
                scene.add(bolt);

                // Add to array with lifecycle props
                lightningBolts.push({
                    mesh: bolt,
                    life: 1.0, // Start fully visible
                    decay: 0.05 + Math.random() * 0.1 // Random fade speed
                });
            }

            let sunGroup; // Group for beams
            let sunParticles; // Bokeh/Dust

            function createSun() {
                sunGroup = new THREE.Group();
                sunGroup.position.set(0, 10, -5); // Position the whole sun here
                scene.add(sunGroup);

                // 1. Texture Helpers
                function createBeamTexture() {
                    const canvas = document.createElement('canvas');
                    canvas.width = 128;
                    canvas.height = 512;
                    const ctx = canvas.getContext('2d');
                    // Linear gradient for beam
                    const grd = ctx.createLinearGradient(0, 0, 0, 512);
                    grd.addColorStop(0, 'rgba(255, 255, 255, 0)');
                    grd.addColorStop(0.2, 'rgba(255, 240, 200, 0.4)');
                    grd.addColorStop(0.5, 'rgba(255, 220, 100, 0.2)');
                    grd.addColorStop(1, 'rgba(255, 255, 255, 0)');
                    ctx.fillStyle = grd;
                    ctx.fillRect(0, 0, 128, 512);
                    return new THREE.CanvasTexture(canvas);
                }

                function createParticleTexture() {
                    const canvas = document.createElement('canvas');
                    canvas.width = 32;
                    canvas.height = 32;
                    const ctx = canvas.getContext('2d');
                    ctx.translate(16, 16);
                    const grd = ctx.createRadialGradient(0, 0, 0, 0, 0, 16);
                    grd.addColorStop(0, 'rgba(255, 255, 255, 1)');
                    grd.addColorStop(1, 'rgba(255, 220, 100, 0)');
                    ctx.fillStyle = grd;
                    ctx.beginPath();
                    ctx.arc(0, 0, 16, 0, Math.PI * 2);
                    ctx.fill();
                    return new THREE.CanvasTexture(canvas);
                }

                function createSunCoreTexture() {
                    const canvas = document.createElement('canvas');
                    canvas.width = 128;
                    canvas.height = 128;
                    const ctx = canvas.getContext('2d');
                    const grd = ctx.createRadialGradient(64, 64, 0, 64, 64, 64);
                    grd.addColorStop(0, 'rgba(255, 255, 255, 1)');
                    grd.addColorStop(0.3, 'rgba(255, 240, 200, 0.9)');
                    grd.addColorStop(0.7, 'rgba(255, 200, 100, 0.4)');
                    grd.addColorStop(1, 'rgba(255, 150, 50, 0)');
                    ctx.fillStyle = grd;
                    ctx.fillRect(0, 0, 128, 128);
                    return new THREE.CanvasTexture(canvas);
                }

                // 2. Create Glowing Sun Core
                const sunCoreMat = new THREE.SpriteMaterial({
                    map: createSunCoreTexture(),
                    blending: THREE.AdditiveBlending,
                    transparent: true,
                    depthWrite: false
                });
                const sunCore = new THREE.Sprite(sunCoreMat);
                sunCore.scale.set(12, 12, 1); // Large glowing core
                sunGroup.add(sunCore);

                // 3. Create Distributed Light Beams (Radiating)
                const beamGeo = new THREE.PlaneGeometry(6, 40);
                beamGeo.translate(0, -20, 0); // Shift so they radiate from their local origin

                const beamMat = new THREE.MeshBasicMaterial({
                    map: createBeamTexture(),
                    transparent: true,
                    opacity: 0.6,
                    side: THREE.DoubleSide,
                    depthWrite: false,
                    blending: THREE.AdditiveBlending
                });

                const beamCount = 16;
                for (let i = 0; i < beamCount; i++) {
                    const beam = new THREE.Mesh(beamGeo, beamMat.clone());
                    beam.position.set(0, 0, 0); // Set at parent origin so group rotates them around center

                    // Radiate in a circle
                    const angle = (i / beamCount) * Math.PI * 2;
                    beam.rotation.z = angle;

                    // Custom property for animation
                    beam.userData = {
                        speed: 0.2 + Math.random() * 0.3,
                        offset: Math.random() * 100
                    };

                    sunGroup.add(beam);
                }

                // 3. Golden Dust (Bokeh)
                const pCount = 200;
                const pGeo = new THREE.BufferGeometry();
                const pPos = [];
                const pSizes = [];

                for (let i = 0; i < pCount; i++) {
                    pPos.push((Math.random() - 0.5) * 50); // Wide X
                    pPos.push((Math.random() - 0.5) * 20); // Y
                    pPos.push((Math.random() - 0.5) * 10); // Z
                    pSizes.push(Math.random() * 0.5 + 0.1);
                }

                pGeo.setAttribute('position', new THREE.Float32BufferAttribute(pPos, 3));
                pGeo.setAttribute('size', new THREE.Float32BufferAttribute(pSizes, 1)); // We'll ignore size attr if using PointsMaterial size, but good for ref.

                const pMat = new THREE.PointsMaterial({
                    color: 0xffdd44,
                    map: createParticleTexture(),
                    size: 0.6,
                    transparent: true,
                    opacity: 0.6,
                    blending: THREE.AdditiveBlending,
                    depthWrite: false
                });

                sunParticles = new THREE.Points(pGeo, pMat);
                scene.add(sunParticles);
            }

            let lastWeatherCode = null;

            // Weather Fetcher
            async function fetchWeather() {
                try {
                    // Mabalacat, Pampanga Coordinates: 15.2229° N, 120.5744° E
                    const response = await fetch('https://api.open-meteo.com/v1/forecast?latitude=15.2229&longitude=120.5744&current_weather=true');
                    const data = await response.json();
                    if (data && data.current_weather) {
                        const code = data.current_weather.weathercode;
                        const temp = data.current_weather.temperature;
                        const isDay = data.current_weather.is_day;
                        const statusKey = code + '-' + isDay;
                        console.log("Weather fetched for Mabalacat:", code, temp, "isDay:", isDay);

                        // Self-refresh portal if weather changes after initial load
                        if (lastWeatherCode !== null && lastWeatherCode !== statusKey) {
                            console.log("Weather/Time changed. Refreshing portal...");
                            window.location.reload();
                            return;
                        }

                        lastWeatherCode = statusKey;
                        applyWeatherEffect(code, temp, isDay);
                    } else {
                        throw new Error("Invalid weather data");
                    }
                } catch (e) {
                    console.error("Weather fetch failed, defaulting back", e);
                    const h = new Date().getHours();
                    applyWeatherEffect(0, '--', (h >= 6 && h < 18) ? 1 : 0);
                }
            }

            function applyWeatherEffect(code, temp, isDay) {
                // Clear existing
                if (particleSystem) {
                    scene.remove(particleSystem);
                    if (particleSystem.geometry) particleSystem.geometry.dispose();
                    if (particleSystem.material) particleSystem.material.dispose();
                }
                if (lightningFlash) {
                    scene.remove(lightningFlash);
                    lightningFlash = null;
                }

                // Clear lightning bolts
                lightningBolts.forEach(bolt => {
                    scene.remove(bolt.mesh);
                    if (bolt.mesh.geometry) bolt.mesh.geometry.dispose();
                    if (bolt.mesh.material) bolt.mesh.material.dispose();
                });
                lightningBolts = [];

                // Clear sun
                if (sunGroup) {
                    scene.remove(sunGroup);
                    sunGroup.children.forEach(child => {
                        if (child.geometry) child.geometry.dispose();
                        if (child.material) {
                            if (child.material.map) child.material.map.dispose();
                            child.material.dispose();
                        }
                    });
                    sunGroup = null;
                }
                if (sunParticles) {
                    scene.remove(sunParticles);
                    if (sunParticles.geometry) sunParticles.geometry.dispose();
                    if (sunParticles.material) {
                        if (sunParticles.material.map) sunParticles.material.map.dispose();
                        sunParticles.material.dispose();
                    }
                    sunParticles = null;
                }

                // Determine Weather Label
                let weatherText = temp !== '--' ? `${Math.round(temp)}°C` : '--°C';
                let weatherIcon = isDay ? "fa-cloud-sun" : "fa-cloud-moon";

                if ([95, 96, 99].includes(code)) { weatherIcon = "fa-bolt-lightning"; }
                else if ((code >= 51 && code <= 67) || (code >= 80 && code <= 82)) { weatherIcon = "fa-cloud-rain"; }
                else if ((code >= 71 && code <= 77) || (code >= 85 && code <= 86)) { weatherIcon = "fa-snowflake"; }
                else if (code === 1 || code === 0) { weatherIcon = isDay ? "fa-sun" : "fa-moon"; }
                else if ([2, 3, 45, 48].includes(code)) { weatherIcon = "fa-cloud"; }

                const pill = document.getElementById("weatherStatusPill");
                if (pill) {
                    pill.innerHTML = `<i class="fa-solid ${weatherIcon} mr-1.5"></i> ${weatherText}`;
                }

                // Thunderstorm codes: 95, 96, 99
                if ([95, 96, 99].includes(code)) {
                    createRain(true); // storm = true
                    currentWeather = 'storm';
                }
                // Rain codes: 51-67, 80-82
                else if ((code >= 51 && code <= 67) || (code >= 80 && code <= 82)) {
                    createRain(false);
                    currentWeather = 'rain';
                }
                // Snow codes (unlikely but good practice): 71-77, 85-86
                else if ((code >= 71 && code <= 77) || (code >= 85 && code <= 86)) {
                    createSnow();
                    currentWeather = 'snow';
                }
                // Sunny/Mainly Clear (Code 0 & 1 for Sun Rays) 
                else if (code === 1 || code === 0) {
                    if (isDay) {
                        createSun(); // God Rays
                        currentWeather = 'sunny';
                    } else {
                        createClearSky();
                        currentWeather = 'clear';
                    }
                }
                // Cloud/Fog: 2, 3, 45, 48
                else if ([2, 3, 45, 48].includes(code)) {
                    createClouds();
                    currentWeather = 'cloudy';
                }
                // Clear Sky (0) and Default: No Effect
                else {
                    createClearSky();
                    currentWeather = 'clear';
                }
            }

            function createClearSky() {
                // Default Design: No particles, no effects.
                // Just ensure everything is cleared (handled by applyWeatherEffect)
            }

            function createRain(isStorm) {
                const count = isLowPower ? 800 : 2000;
                const geometry = new THREE.BufferGeometry();
                const positions = [];
                const velocities = [];

                for (let i = 0; i < count; i++) {
                    const x = (Math.random() - 0.5) * 20;
                    const y = (Math.random() - 0.5) * 20;
                    const z = (Math.random() - 0.5) * 10;
                    const v = -0.15 - Math.random() * 0.2; // Slightly faster for streaks

                    // Top vertex
                    positions.push(x, y, z);
                    // Bottom vertex (streak length 0.4)
                    positions.push(x, y - 0.4, z);

                    // Store velocity for both vertices
                    velocities.push(v);
                    velocities.push(v);
                }

                geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
                geometry.setAttribute('velocity', new THREE.Float32BufferAttribute(velocities, 1));

                const material = new THREE.LineBasicMaterial({
                    color: 0xaaddff,
                    transparent: true,
                    opacity: 0.5
                });

                particleSystem = new THREE.LineSegments(geometry, material);
                scene.add(particleSystem);
            }

            function createSnow() {
                const count = isLowPower ? 400 : 1000;
                const geometry = new THREE.BufferGeometry();
                const positions = [];
                const velocities = [];

                for (let i = 0; i < count; i++) {
                    positions.push((Math.random() - 0.5) * 20); // x
                    positions.push((Math.random() - 0.5) * 20); // y
                    positions.push((Math.random() - 0.5) * 10); // z
                    velocities.push(-0.02 - Math.random() * 0.05); // Slow falling
                }

                geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
                geometry.setAttribute('velocity', new THREE.Float32BufferAttribute(velocities, 1));

                const material = new THREE.PointsMaterial({
                    size: 0.08,
                    color: 0xffffff,
                    transparent: true,
                    opacity: 0.8
                });

                particleSystem = new THREE.Points(geometry, material);
                scene.add(particleSystem);
            }

            function createClouds() {
                const count = isLowPower ? 50 : 200;
                const geometry = new THREE.BufferGeometry();
                const positions = [];
                // const sizes = []; // Uniform size for now

                // Create soft cloud puff texture
                const canvas = document.createElement('canvas');
                canvas.width = 128;
                canvas.height = 128;
                const ctx = canvas.getContext('2d');
                // complex puff shape
                ctx.translate(64, 64);
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(0, 0, 30, 0, Math.PI * 2);
                ctx.arc(20, 10, 25, 0, Math.PI * 2);
                ctx.arc(-20, 15, 20, 0, Math.PI * 2);
                ctx.arc(0, -20, 25, 0, Math.PI * 2);
                ctx.fill();

                // Soften edges with radial gradient on top? 
                // Simpler: Just a soft radial gradient
                const texCanvas = document.createElement('canvas');
                texCanvas.width = 64;
                texCanvas.height = 64;
                const tCtx = texCanvas.getContext('2d');
                const grd = tCtx.createRadialGradient(32, 32, 0, 32, 32, 32);
                grd.addColorStop(0, 'rgba(255, 255, 255, 0.9)'); // Bright white center
                grd.addColorStop(1, 'rgba(255, 255, 255, 0)');
                tCtx.fillStyle = grd;
                tCtx.fillRect(0, 0, 64, 64);

                const texture = new THREE.CanvasTexture(texCanvas);

                for (let i = 0; i < count; i++) {
                    positions.push((Math.random() - 0.5) * 40); // Wide spread X
                    positions.push(Math.random() * 6 - 2);     // Vertical spread (mid-high)
                    positions.push((Math.random() - 0.5) * 15 - 5); // Depth, mostly back
                }

                geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));

                const material = new THREE.PointsMaterial({
                    size: 6, // Very large puffs
                    map: texture,
                    color: 0xffffff, // Pure white
                    transparent: true,
                    opacity: 0.7, // Slightly more opaque
                    blending: THREE.NormalBlending, // Normal blessing for opacity layering (foggy)
                    depthWrite: false
                });

                particleSystem = new THREE.Points(geometry, material);
                scene.add(particleSystem);
            }

            // Animation Loop
            function animate() {
                requestAnimationFrame(animate);
                const delta = clock.getDelta();
                const time = clock.getElapsedTime();

                // Animate Distributed Sun Effects
                if (currentWeather === 'sunny') {
                    // Beams Pulse & Rotate Sun
                    if (sunGroup) {
                        sunGroup.rotation.z -= 0.1 * delta; // Slowly spin the entire starburst

                        sunGroup.children.forEach(child => {
                            if (child.isMesh && child.userData.speed) {
                                const speed = child.userData.speed || 1;
                                const offset = child.userData.offset || 0;
                                // Subtle opacity pulse for individual rays
                                child.material.opacity = 0.3 + Math.sin(time * speed + offset) * 0.15;
                            } else if (child.isSprite) {
                                // Pulse the core a little bit too
                                const scalePulse = 12 + Math.sin(time * 2) * 0.5;
                                child.scale.set(scalePulse, scalePulse, 1);
                            }
                        });
                    }
                    // Particles Drift
                    if (sunParticles) {
                        const positions = sunParticles.geometry.attributes.position.array;
                        for (let i = 0; i < positions.length / 3; i++) {
                            // Float upwards slowly
                            positions[i * 3 + 1] += 0.005;
                            // Wiggle X
                            positions[i * 3] += Math.sin(time + i) * 0.002;

                            // Reset if too high
                            if (positions[i * 3 + 1] > 10) positions[i * 3 + 1] = -10;
                        }
                        sunParticles.geometry.attributes.position.needsUpdate = true;
                    }
                }

                if (particleSystem) {
                    if (currentWeather === 'clear') {
                        particleSystem.rotation.y = time * 0.05;
                    }
                    else if (currentWeather === 'rain' || currentWeather === 'storm') {
                        const positions = particleSystem.geometry.attributes.position.array;
                        const velocities = particleSystem.geometry.attributes.velocity.array;

                        // Check if using LineSegments (Rain) or Points (Snow/fallback)
                        if (particleSystem.isLineSegments) {
                            // Iterate through PAIRS of vertices (6 floats per streak)
                            for (let i = 0; i < positions.length / 6; i++) {
                                const topIdx = i * 6 + 1; // Y of top
                                const botIdx = i * 6 + 4; // Y of bot
                                const v = velocities[i * 2]; // Velocity (same for both)

                                positions[topIdx] += v;
                                positions[botIdx] += v;

                                // Reset if top goes below -10
                                if (positions[topIdx] < -10) {
                                    positions[topIdx] = 10;
                                    positions[botIdx] = 9.6; // 10 - 0.4 length
                                }
                            }
                        } else {
                            // Fallback logic if somehow not LineSegments
                            for (let i = 0; i < positions.length / 3; i++) {
                                positions[i * 3 + 1] += velocities[i];
                                if (positions[i * 3 + 1] < -10) {
                                    positions[i * 3 + 1] = 10;
                                }
                            }
                        }

                        particleSystem.geometry.attributes.position.needsUpdate = true;

                        if (currentWeather === 'storm') {
                            // Spawn new bolt roughly 2-3% of frames
                            if (Math.random() > 0.98) {
                                createBolt();
                            }

                            // Update existing bolts
                            for (let i = lightningBolts.length - 1; i >= 0; i--) {
                                const boltData = lightningBolts[i];
                                boltData.life -= boltData.decay;
                                boltData.mesh.material.opacity = boltData.life;

                                if (boltData.life <= 0) {
                                    scene.remove(boltData.mesh);
                                    boltData.mesh.geometry.dispose();
                                    boltData.mesh.material.dispose();
                                    lightningBolts.splice(i, 1);
                                }
                            }
                        }
                    }
                    else if (currentWeather === 'snow') {
                        const positions = particleSystem.geometry.attributes.position.array;
                        const velocities = particleSystem.geometry.attributes.velocity.array;

                        for (let i = 0; i < positions.length / 3; i++) {
                            positions[i * 3 + 1] += velocities[i]; // Y
                            positions[i * 3] += Math.sin(time + i) * 0.01; // X wiggle
                            if (positions[i * 3 + 1] < -10) {
                                positions[i * 3 + 1] = 10;
                            }
                        }
                        particleSystem.geometry.attributes.position.needsUpdate = true;
                    }
                    else if (currentWeather === 'cloudy') {
                        // Drift clouds
                        const positions = particleSystem.geometry.attributes.position.array;
                        for (let i = 0; i < positions.length / 3; i++) {
                            positions[i * 3] -= 0.01; // Drift Left
                            // Wrap around
                            if (positions[i * 3] < -25) {
                                positions[i * 3] = 25;
                            }
                        }
                        particleSystem.geometry.attributes.position.needsUpdate = true;
                    }
                } // Close particleSystem check

                renderer.render(scene, camera);
            }

            animate();
            fetchWeather(); // Init fetch

            // Poll for weather changes every 5 minutes (300,000 ms)
            setInterval(fetchWeather, 300000);

            // Resize Handler
            window.addEventListener('resize', () => {
                camera.aspect = container.offsetWidth / container.offsetHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(container.offsetWidth, container.offsetHeight);
            });
        });

        // GSAP Animations Removed
        document.addEventListener('DOMContentLoaded', () => {
            // Simple 3D Tilt Effect - Native or CSS driven could be here, keeping JS minimal/native if needed.
            // For now, removing GSAP dependency entirely as requested.
        });

        // Live Clock
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            document.getElementById('liveClock').textContent = `${hours}:${minutes} ${ampm}`;
        }
        setInterval(updateClock, 1000);

        // Sidebar Toggle Logic
        const leftSidebar = document.getElementById('leftSidebar');
        const rightSidebar = document.getElementById('rightSidebar');
        const backdrop = document.getElementById('globalBackdrop');

        function toggleLeftSidebar() {
            if (!leftSidebar) return;
            const isOpen = !leftSidebar.classList.contains('-translate-x-[200%]');
            if (isOpen) {
                closeAllSidebars();
            } else {
                // Open Left
                leftSidebar.classList.remove('-translate-x-[200%]');
                showBackdrop();
                // Close right if open
                if (rightSidebar) rightSidebar.classList.add('translate-x-full');
                document.body.classList.add('sidebar-open');
            }
        }

        function toggleRightSidebar() {
            if (!rightSidebar) return;
            const isOpen = !rightSidebar.classList.contains('translate-x-full');
            if (isOpen) {
                closeAllSidebars();
            } else {
                // Open Right
                rightSidebar.classList.remove('translate-x-full');
                showBackdrop();
                // Close left if open
                if (leftSidebar) leftSidebar.classList.add('-translate-x-[200%]');
                document.body.classList.add('sidebar-open');
            }
        }

        function closeAllSidebars() {
            if (leftSidebar) leftSidebar.classList.add('-translate-x-[200%]');
            if (rightSidebar) rightSidebar.classList.add('translate-x-full');
            document.body.classList.remove('sidebar-open');
            hideBackdrop();
        }

        function showBackdrop() {
            if (!backdrop) return;
            backdrop.classList.remove('hidden');
            // Small delay to allow display:block to apply before opacity transition
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                backdrop.classList.remove('pointer-events-none');
            }, 10);
        }

        function hideBackdrop() {
            if (!backdrop) return;
            backdrop.classList.add('opacity-0');
            backdrop.classList.add('pointer-events-none');
            setTimeout(() => {
                backdrop.classList.add('hidden');
            }, 300); // Match transition duration
        }

        // App Search Logic
        document.getElementById('appSearch').addEventListener('keyup', function (e) {
            const term = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.app-card');

            cards.forEach(card => {
                const name = card.querySelector('.app-name').textContent.toLowerCase();
                if (name.includes(term)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // App Favorites Logic
        function toggleFavorite(e, btn, appId) {
            e.preventDefault();
            e.stopPropagation();

            let favorites = JSON.parse(localStorage.getItem('portal_favorites') || '[]');
            const index = favorites.indexOf(appId);
            const icon = btn.querySelector('i');

            if (index === -1) {
                favorites.push(appId);
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid', 'text-red-500');
            } else {
                favorites.splice(index, 1);
                icon.classList.remove('fa-solid', 'text-red-500');
                icon.classList.add('fa-regular');
            }

            localStorage.setItem('portal_favorites', JSON.stringify(favorites));
            sortApps();
        }

        function sortApps() {
            const grid = document.getElementById('appsGrid');
            if (!grid) return;
            const cards = Array.from(grid.children);
            const favorites = JSON.parse(localStorage.getItem('portal_favorites') || '[]');

            cards.sort((a, b) => {
                const idA = a.getAttribute('data-app-id');
                const idB = b.getAttribute('data-app-id');
                const isFavA = favorites.includes(idA);
                const isFavB = favorites.includes(idB);

                // 1. Sort by Favorites
                if (isFavA && !isFavB) return -1;
                if (!isFavA && isFavB) return 1;

                // 2. Sort by Manual Priority (Sort Order)
                const orderA = parseInt(a.getAttribute('data-sort-order') || '99');
                const orderB = parseInt(b.getAttribute('data-sort-order') || '99');
                if (orderA !== orderB) return orderA - orderB;

                // 3. Sort Alphabetically
                const nameA = a.querySelector('.app-name').textContent.trim().toLowerCase();
                const nameB = b.querySelector('.app-name').textContent.trim().toLowerCase();
                return nameA.localeCompare(nameB);
            });

            cards.forEach(card => grid.appendChild(card));
        }

        // Announcements Carousel
        const track = document.getElementById('announcementTrack');
        const prevBtn = document.getElementById('prevAnn');
        const nextBtn = document.getElementById('nextAnn');
        let currentIndex = 0;
        const items = track.children;
        const totalItems = items.length;
        let autoSlideInterval;

        function updateCarousel() {
            const width = track.parentElement.offsetWidth;
            track.style.transform = `translateX(-${currentIndex * width}px)`;
        }

        function nextSlide() {
            currentIndex = (currentIndex + 1) % totalItems;
            updateCarousel();
        }

        function prevSlide() {
            currentIndex = (currentIndex - 1 + totalItems) % totalItems;
            updateCarousel();
        }

        function startAutoSlide() {
            if (totalItems > 1) {
                autoSlideInterval = setInterval(nextSlide, 3000);
            }
        }

        function resetTimer() {
            clearInterval(autoSlideInterval);
            startAutoSlide();
        }

        // Settings Modal Logic
        const settingsModal = document.getElementById('settingsModal');

        function openSettingsModal() {
            settingsModal.classList.remove('hidden');
            // Small delay for transition
            requestAnimationFrame(() => {
                settingsModal.classList.remove('opacity-0');
                settingsModal.querySelector('div').classList.remove('scale-95');
                settingsModal.querySelector('div').classList.add('scale-100');
            });
        }

        function closeSettingsModal() {
            settingsModal.classList.add('opacity-0');
            settingsModal.querySelector('div').classList.remove('scale-100');
            settingsModal.querySelector('div').classList.add('scale-95');
            setTimeout(() => {
                settingsModal.classList.add('hidden');
            }, 300);
        }

        // Close on backdrop click
        settingsModal.addEventListener('click', (e) => {
            if (e.target === settingsModal) closeSettingsModal();
        });

        // Help Modal Logic
        const helpModal = document.getElementById('helpModal');

        function openHelpModal() {
            helpModal.classList.remove('hidden');
            requestAnimationFrame(() => {
                helpModal.classList.remove('opacity-0');
                helpModal.querySelector('div').classList.remove('scale-95');
                helpModal.querySelector('div').classList.add('scale-100');
            });
        }

        function closeHelpModal() {
            helpModal.classList.add('opacity-0');
            helpModal.querySelector('div').classList.remove('scale-100');
            helpModal.querySelector('div').classList.add('scale-95');
            setTimeout(() => {
                helpModal.classList.add('hidden');
            }, 300);
        }

        // Close on backdrop click
        helpModal.addEventListener('click', (e) => {
            if (e.target === helpModal) closeHelpModal();
        });

        // Functions for Settings
        function toggleAnimation(enabled) {
            const canvas = document.querySelector('#heroCanvas canvas');
            if (canvas) {
                canvas.style.display = enabled ? 'block' : 'none';
            }
            localStorage.setItem('portal_animation', enabled);
        }

        const themes = {
            default: { // Pink
                primary: '#db2777', light: '#fce7f3', hover: '#be185d', ring: '#ec4899', bg_hover: '#fdf2f8',
                dark_bg: '#0f172a', dark_card: '#1e293b', dark_text: '#f8fafc', dark_light: '#500724'
            },
            purple: {
                primary: '#9333ea', light: '#f3e8ff', hover: '#7e22ce', ring: '#a855f7', bg_hover: '#faf5ff',
                dark_bg: '#0f172a', dark_card: '#1e293b', dark_text: '#f8fafc', dark_light: '#3b0764'
            },
            cyan: {
                primary: '#0891b2', light: '#cffafe', hover: '#0e7490', ring: '#06b6d4', bg_hover: '#ecfeff',
                dark_bg: '#0f172a', dark_card: '#1e293b', dark_text: '#f8fafc', dark_light: '#164e63'
            },
            green: {
                primary: '#059669', light: '#d1fae5', hover: '#047857', ring: '#10b981', bg_hover: '#ecfdf5',
                dark_bg: '#0f172a', dark_card: '#1e293b', dark_text: '#f8fafc', dark_light: '#064e3b'
            },
            yellow: {
                primary: '#ca8a04', light: '#fef9c3', hover: '#a16207', ring: '#eab308', bg_hover: '#fefce8',
                dark_bg: '#0f172a', dark_card: '#1e293b', dark_text: '#f8fafc', dark_light: '#422006'
            },
            blue: {
                primary: '#5e9fe8', light: '#eef5ff', hover: '#4a8bd6', ring: '#5e9fe8', bg_hover: '#f5f9ff',
                dark_bg: '#0f172a', dark_card: '#1e293b', dark_text: '#f8fafc', dark_light: '#1e3a5f'
            },
            red: {
                primary: '#a41313', light: '#fdeaea', hover: '#8b1010', ring: '#a41313', bg_hover: '#fff5f5',
                dark_bg: '#0f172a', dark_card: '#1e293b', dark_text: '#f8fafc', dark_light: '#4c0505'
            },
            brown: {
                primary: '#554436', light: '#f5f3f1', hover: '#45372c', ring: '#554436', bg_hover: '#faf9f8',
                dark_bg: '#0f172a', dark_card: '#1e293b', dark_text: '#f8fafc', dark_light: '#2c241d'
            },
            forest: {
                primary: '#395b40', light: '#edf2ee', hover: '#2d4732', ring: '#395b40', bg_hover: '#f5f8f6',
                dark_bg: '#0f172a', dark_card: '#1e293b', dark_text: '#f8fafc', dark_light: '#1d2e20'
            },
            lavender: {
                primary: '#aa98d3', light: '#f4f2f9', hover: '#9381c0', ring: '#aa98d3', bg_hover: '#f9f8fc',
                dark_bg: '#0f172a', dark_card: '#1e293b', dark_text: '#f8fafc', dark_light: '#3f3552'
            }
        };

        let isDarkMode = false;
        let currentTheme = 'default';
        let bgShaderMaterial;

        function toggleDarkMode(enabled) {
            isDarkMode = enabled;
            localStorage.setItem('portal_dark_mode', enabled);
            setTheme(currentTheme);
        }

        function setTheme(color) {
            currentTheme = color;
            const theme = themes[color] || themes.default;
            const style = document.getElementById('theme-overrides');

            const bg = isDarkMode ? theme.dark_bg : '#f4f7fa';
            const cardBg = isDarkMode ? theme.dark_card : '#ffffff';
            const textColor = isDarkMode ? theme.dark_text : '#1a1a1a';
            const subTextColor = isDarkMode ? '#94a3b8' : '#6b7280';
            const borderColor = isDarkMode ? '#334155' : '#f3f4f6';
            const glassBg = isDarkMode ? 'rgba(15, 23, 42, 0.8)' : 'rgba(255, 255, 255, 0.7)';
            const accentLight = isDarkMode ? theme.dark_light : theme.light;

            const isMobile = window.innerWidth < 1024;
            const blurSize = isMobile ? '4px' : (isDarkMode ? '12px' : '24px');

            // Override Tailwind Classes
            style.innerHTML = `
                :root {
                    --bg-main: ${bg};
                    --bg-card: ${cardBg};
                    --text-main: ${textColor};
                    --text-sub: ${subTextColor};
                    --border-main: ${borderColor};
                }

                body { background-color: transparent !important; color: ${textColor} !important; }
                main {
                    background: radial-gradient(1200px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), 
                        ${isDarkMode ? 'rgba(255,255,255,0.03)' : 'rgba(236,72,153,0.02)'}, 
                        transparent 70%) !important;
                    position: relative;
                    z-index: 0;
                }
                main::before {
                    content: "";
                    position: absolute;
                    inset: 0;
                    background-image: url("https://www.transparenttextures.com/patterns/graphy-very-light.png");
                    opacity: ${isDarkMode ? '0.03' : '0.02'};
                    pointer-events: none;
                    z-index: -1;
                }
                
                .backdrop-blur-xl, .backdrop-blur-2xl, .glass-effect {
                    backdrop-filter: blur(${blurSize}) !important;
                    -webkit-backdrop-filter: blur(${blurSize}) !important;
                }
                .bg-white { background-color: ${cardBg} !important; }
                .bg-\\[\\#f4f7fa\\] { background-color: transparent !important; }
                
                .text-gray-800, .text-gray-900, .text-gray-700, .text-\\[\\#1a1a1a\\] { color: ${textColor} !important; }
                .text-gray-400, .text-gray-500, .text-gray-600 { color: ${subTextColor} !important; }
                
                .border-gray-50, .border-gray-100, .border-gray-200, .border-white\\/20, .border-white\\/40, .border-white\\/50, .border-white\\/60, .border-white\\/80 { border-color: ${borderColor} !important; }
                .glass-effect { background: ${glassBg} !important; }
                
                /* Right Sidebar Fixes */
                @media (max-width: 1023px) {
                    #rightSidebar.bg-white { background-color: ${cardBg} !important; border-left: 1px solid ${borderColor} !important; }
                }
                @media (min-width: 1024px) {
                    #rightSidebar.lg\\:bg-transparent { background-color: transparent !important; }
                }

                /* Hover States */
                .hover\\:bg-gray-50:hover, .hover\\:bg-gray-100:hover { background-color: ${isDarkMode ? '#334155' : '#f9fafb'} !important; }
                .hover\\:text-gray-600:hover { color: ${textColor} !important; }
                
                /* Dynamic Animated Gradient Background */
                #mainBackground { 
                    background: linear-gradient(-45deg, 
                        ${bg}, 
                        ${isDarkMode ? theme.dark_card : theme.light}, 
                        ${isDarkMode ? theme.dark_bg : '#f8fafc'}, 
                        ${bg}
                    ) !important;
                    background-size: 400% 400% !important;
                }
                .blob-1 { background-color: ${theme.primary} !important; filter: blur(100px) saturate(1.5) !important; will-change: transform; }
                .blob-2 { background-color: ${theme.ring} !important; filter: blur(120px) saturate(1.5) !important; will-change: transform; }
                
                /* Left Sidebar Specifics */
                #leftSidebar.bg-white\\/95, #leftSidebar.lg\\:bg-white\\/80 { background-color: ${isDarkMode ? 'rgba(30, 41, 59, 0.9)' : 'rgba(255, 255, 255, 0.8)'} !important; }
                
                /* Text & Background Colors */
                .text-pink-600, .group:hover .group-hover\\:text-pink-600, .hover\\:text-pink-600:hover { color: ${isDarkMode ? '#ffffff' : theme.primary} !important; }
                .text-pink-500, .hover\\:text-pink-500:hover, .group:focus-within .group-focus-within\\:text-pink-500 { color: ${theme.ring} !important; }
                .text-pink-100, .text-pink-200 { color: ${accentLight} !important; }
                
                .bg-pink-100 { background-color: ${accentLight} !important; }
                .bg-pink-50, .hover\\:bg-pink-50:hover { background-color: ${isDarkMode ? theme.dark_light : theme.bg_hover} !important; }
                .bg-pink-500, .hover\\:bg-pink-500:hover { background-color: ${theme.ring} !important; }
                .hover\\:bg-pink-600:hover { background-color: ${theme.primary} !important; }
                
                /* Borders */
                .border-pink-200, .hover\\:border-pink-200:hover { border-color: ${accentLight} !important; }
                .focus\\:border-pink-500:focus { border-color: ${theme.ring} !important; }
                .bg-pink-50\\/50 { background-color: ${isDarkMode ? theme.dark_light + '80' : theme.bg_hover + '80'} !important; }
                
                /* Rings & Shadows */
                .focus\\:ring-pink-500:focus { --tw-ring-color: ${theme.ring} !important; }
                .focus\\:ring-pink-500\\/5:focus { --tw-ring-color: ${theme.ring}0d !important; }
                .ring-pink-400 { --tw-ring-color: ${theme.ring}aa !important; }
                .shadow-pink-500\\/20 { --tw-shadow-color: ${theme.ring}33 !important; }
                .shadow-pink-500\\/40 { --tw-shadow-color: ${theme.ring}66 !important; }
                .hover\\:shadow-pink-500\\/10:hover { --tw-shadow-color: ${theme.ring}1a !important; }
                .hover\\:shadow-\\[0_20px_50px_rgba\\(236\\,72\\,153\\,0\\.15\\)\\]:hover { box-shadow: 0 20px 50px ${theme.ring}26 !important; }

                /* Cards & Containers */
                .app-card { 
                    background-color: ${isDarkMode ? 'rgba(30, 41, 59, 0.6)' : 'rgba(255, 255, 255, 0.4)'} !important; 
                    border-color: ${isDarkMode ? 'rgba(255,255,255,0.05)' : 'rgba(255,255,255,0.6)'} !important;
                    position: relative;
                    overflow: hidden;
                    box-shadow: inset 0 0 20px rgba(255,255,255,${isDarkMode ? '0.01' : '0.05'}) !important;
                    /* backdrop-filter: blur(8px) !important; Removed for performance */
                    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease, background 0.3s ease;
                }
                .app-card .border-t {
                    border-color: ${isDarkMode ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)'} !important;
                }
                @media (min-width: 1024px) {
                    .app-card:hover { 
                    border-color: ${theme.ring} !important;
                    background: radial-gradient(circle at var(--card-mouse-x, 50%) var(--card-mouse-y, 50%), 
                                ${theme.ring}1a 0%, 
                                ${isDarkMode ? 'rgba(30, 41, 59, 0.6)' : 'rgba(255,255,255,0.6)'} 100%) !important;
                    transform: translateY(-5px) !important;
                    box-shadow: 0 20px 40px -15px ${theme.ring}33, inset 0 0 25px ${theme.ring}0d !important;
                }
                }
                @media (min-width: 1024px) {
                    .app-card::after {
                        content: "";
                        position: absolute;
                        inset: 0;
                        background: linear-gradient(125deg, 
                            transparent 0%, 
                            ${isDarkMode ? 'rgba(255,255,255,0.1)' : theme.ring + '26'} 45%, 
                            ${isDarkMode ? 'rgba(255,255,255,0.25)' : theme.ring + '4D'} 50%, 
                            ${isDarkMode ? 'rgba(255,255,255,0.1)' : theme.ring + '26'} 55%, 
                            transparent 100%);
                        transform: translateX(-100%);
                        transition: transform 0.6s ease;
                    }
                    .app-card:hover::after {
                        transform: translateX(100%);
                    }
                }
                .container-header { background-color: ${isDarkMode ? 'rgba(15, 23, 42, 0.6)' : 'rgba(255, 255, 255, 0.4)'} !important; }
                .current-selection-text { color: ${theme.ring} !important; }
                .app-card button { background-color: ${isDarkMode ? 'rgba(15, 23, 42, 0.4)' : 'rgba(255, 255, 255, 0.8)'} !important; border-color: ${borderColor} !important; }
                
                @media (min-width: 1024px) {
                    .app-card button:hover { background-color: ${isDarkMode ? 'rgba(15, 23, 42, 0.8)' : '#ffffff'} !important; }
                }

                /* Mobile/Tablet Overrides */
                @media (max-width: 1023px) {
                    .app-card { transform: none !important; box-shadow: none !important; border-color: ${borderColor} !important; }
                    .app-card:hover { background: ${isDarkMode ? 'rgba(30, 41, 59, 0.6)' : 'rgba(255, 255, 255, 0.4)'} !important; }
                    .group-hover\:opacity-100 { opacity: 0 !important; }
                    .app-card i.animate-bounce-horizontal { animation: none !important; }
                    /* Disable Ghost Icon Hover */
                    .group:hover .group-hover\:scale-125 { transform: none !important; }
                    .group:hover .group-hover\:-rotate-12 { transform: none !important; }
                    .group:hover .group-hover\:opacity-\[0\.1\] { opacity: 0.04 !important; }
                }
                
                .bg-gray-100\\/80 { background-color: ${isDarkMode ? 'rgba(30, 41, 59, 0.8)' : 'rgba(243, 244, 246, 0.8)'} !important; }
                .bg-gray-50\\/30 { background-color: ${isDarkMode ? 'rgba(15, 23, 42, 0.3)' : 'rgba(249, 250, 251, 0.3)'} !important; }
                .bg-gray-50 { background-color: ${isDarkMode ? '#1e293b' : '#f9fafb'} !important; }
                .bg-slate-50\\/50 { background-color: ${isDarkMode ? 'transparent' : 'rgba(248, 250, 252, 0.5)'} !important; }
                #calendarBack { background-color: ${cardBg} !important; }

                /* Gradients */
                .from-pink-500 { --tw-gradient-from: ${theme.ring} !important; }
                .from-pink-300 { --tw-gradient-from: ${theme.light} !important; }
                .from-pink-500\\/10 { --tw-gradient-from: ${theme.ring}1a !important; }
                .to-rose-600 { --tw-gradient-to: ${theme.hover} !important; }
                .headline-gradient { 
                    background-image: linear-gradient(to right, ${theme.primary}, ${isDarkMode ? theme.dark_card : 'white'}) !important; 
                }
                .headline-banner p {
                    color: ${isDarkMode ? '#e2e8f0' : accentLight} !important;
                    text-shadow: ${isDarkMode ? '0 2px 4px rgba(0,0,0,0.5)' : 'none'} !important;
                }
                .apps-gradient { background-image: linear-gradient(to bottom right, ${accentLight}33, ${cardBg}fa) !important; }
                .blob-1 { background-color: ${accentLight} !important; }
                .blob-2 { background-color: ${theme.ring}33 !important; }
                .bg-pink-400\\/10 { background-color: ${theme.ring}1a !important; }
                
                /* Modals & Popups */
                #settingsModal .bg-white, #helpModal .bg-white { background-color: ${cardBg} !important; }
                
                /* Done Button Fix */
                #settingsModal button.bg-gray-900 {
                    background-color: ${isDarkMode ? theme.ring : '#111827'} !important;
                    color: white !important;
                    box-shadow: ${isDarkMode ? '0 4px 20px ' + theme.ring + '66' : '0 10px 15px -3px rgba(0, 0, 0, 0.1)'} !important;
                }
                #settingsModal button.bg-gray-900:hover {
                    background-color: ${isDarkMode ? theme.primary : '#1f2937'} !important;
                    box-shadow: ${isDarkMode ? '0 4px 25px ' + theme.ring + '99' : '0 20px 25px -5px rgba(0, 0, 0, 0.1)'} !important;
                }

                /* Headline Action Pill (Subtle Glass Style) */
                #headlineActionPill { 
                    background-color: rgba(255, 255, 255, 0.1) !important; 
                    backdrop-filter: blur(8px) !important;
                    -webkit-backdrop-filter: blur(8px) !important;
                    color: #ffffff !important;
                    border-color: rgba(255, 255, 255, 0.2) !important;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important;
                }
                #headlineActionPill .pill-btn {
                    color: rgba(255, 255, 255, 0.7) !important;
                }
                #headlineActionPill .pill-btn:hover { 
                    background-color: rgba(255, 255, 255, 0.1) !important; 
                    color: #ffffff !important;
                }
                #headlineActionPill .pill-btn.active {
                    background-color: ${theme.ring} !important;
                    color: #ffffff !important;
                    box-shadow: 0 4px 15px ${theme.ring}66;
                }
                #headlineActionPill button:not(.active) i {
                    color: ${theme.ring} !important;
                    filter: drop-shadow(0 0 5px ${theme.ring}33);
                }

                /* Inputs */
                input#appSearch { background-color: ${isDarkMode ? '#0f172a' : 'white'} !important; color: ${textColor} !important; }
                input#appSearch::placeholder { color: ${subTextColor} !important; }

                /* Legacy Overrides */
                .icon-gradient { background-image: linear-gradient(to bottom right, #475569, ${theme.ring}) !important; }
                .accent-pink-500 { accent-color: ${theme.ring} !important; }

                /* Toggle Visibility */
                #darkToggle + div { background-color: ${isDarkMode ? '#0f172a' : '#e5e7eb'} !important; border: 1px solid ${borderColor} !important; }
                #darkToggle:checked + div { background-color: ${theme.ring} !important; border-color: ${theme.ring} !important; }
                #animToggle + div { background-color: ${isDarkMode ? '#0f172a' : '#e5e7eb'} !important; border: 1px solid ${borderColor} !important; }
                #animToggle:checked + div { background-color: ${theme.ring} !important; border-color: ${theme.ring} !important; }

                /* Custom Scrollbar */
                ::-webkit-scrollbar { width: 10px; height: 10px; }
                ::-webkit-scrollbar-track { background: transparent; }
                ::-webkit-scrollbar-thumb { background: ${theme.ring}33; border-radius: 20px; border: 3px solid transparent; background-clip: content-box; }
                ::-webkit-scrollbar-thumb:hover { background: ${theme.ring}66; border-radius: 20px; border: 3px solid transparent; background-clip: content-box; }
                * { scrollbar-width: thin; scrollbar-color: ${theme.ring}33 transparent; }

                /* Footer Override */
                footer { background-color: ${isDarkMode ? bg : 'rgba(255, 255, 255, 0.5)'} !important; border-top-color: ${borderColor} !important; }
            `;

            // Update Logo Source & Style
            const logo = document.getElementById('mainLogo');
            if (logo) {
                logo.src = 'assets/centralpoint_no-bg.png';
                if (isDarkMode) {
                    logo.style.filter = 'none';
                    logo.style.mixBlendMode = 'normal';
                } else {
                    logo.style.filter = 'invert(1) hue-rotate(180deg)';
                    logo.style.mixBlendMode = 'multiply';
                }
            }

            localStorage.setItem('portal_theme', color);

            // Update Shader Colors Dynamically
            if (bgShaderMaterial) {
                const c1 = new THREE.Color(isDarkMode ? theme.dark_bg : '#ffffff');
                const c2 = new THREE.Color(isDarkMode ? theme.dark_light : theme.light);

                // If in dark mode, we want a very subtle shift, not too bright
                if (isDarkMode) {
                    bgShaderMaterial.uniforms.uColor1.value.set(c1).lerp(new THREE.Color(theme.primary), 0.02);
                    bgShaderMaterial.uniforms.uColor2.value.set(c1).lerp(new THREE.Color(theme.ring), 0.05);
                } else {
                    bgShaderMaterial.uniforms.uColor1.value.set(c1);
                    bgShaderMaterial.uniforms.uColor2.value.set(c2);
                }
            }
        }



        // Load Preferences
        document.addEventListener('DOMContentLoaded', () => {
            const savedAnim = localStorage.getItem('portal_animation');
            // Default to true if not set
            const isEnabled = savedAnim !== null ? savedAnim === 'true' : true;
            document.getElementById('animToggle').checked = isEnabled;
            setTimeout(() => toggleAnimation(isEnabled), 100);

            // Load Dark Mode
            const savedDarkMode = localStorage.getItem('portal_dark_mode') === 'true';
            document.getElementById('darkToggle').checked = savedDarkMode;
            isDarkMode = savedDarkMode;

            // Load Theme
            const savedTheme = localStorage.getItem('portal_theme') || 'default';
            setTheme(savedTheme);

            // Load Favorites
            let favorites = localStorage.getItem('portal_favorites');
            if (favorites === null) {
                favorites = [];
            } else {
                favorites = JSON.parse(favorites);
            }
            const cards = document.querySelectorAll('.app-card');
            cards.forEach(card => {
                const id = card.getAttribute('data-app-id');
                if (favorites.includes(id)) {
                    const icon = card.querySelector('.fa-heart');
                    if (icon) {
                        icon.classList.remove('fa-regular');
                        icon.classList.add('fa-solid', 'text-red-500');
                    }
                }
            });
            sortApps();
        });

        // Event Listeners
        if (totalItems > 1) {
            nextBtn.addEventListener('click', () => {
                nextSlide();
                resetTimer();
            });

            prevBtn.addEventListener('click', () => {
                prevSlide();
                resetTimer();
            });

            // Handle Resize
            window.addEventListener('resize', updateCarousel);

            // Start
            startAutoSlide();
        } else {
            // Hide buttons if only 1 item
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
        }

        // Calendar Logic
        let isCalendarView = false;
        let currentDate = new Date();

        // Initial Render
        renderCalendar();

        function toggleCalendar() {
            const widget = document.getElementById('calendarWidget');
            const inner = document.getElementById('calendarInner');
            const front = document.getElementById('calendarFront');
            const back = document.getElementById('calendarBack');
            isCalendarView = !isCalendarView;

            if (isCalendarView) {
                inner.style.transform = 'rotateY(180deg)';
                // Disable front, enable back
                front.classList.remove('pointer-events-auto', 'z-20');
                front.classList.add('pointer-events-none', 'z-10');

                back.classList.remove('pointer-events-none', 'z-10');
                back.classList.add('pointer-events-auto', 'z-20');
            } else {
                inner.style.transform = 'rotateY(0deg)';
                // Enable front, disable back
                front.classList.remove('pointer-events-none', 'z-10');
                front.classList.add('pointer-events-auto', 'z-20');

                back.classList.remove('pointer-events-auto', 'z-20');
                back.classList.add('pointer-events-none', 'z-10');
            }
        }

        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1; // JS months are 0-indexed

            fetch(`actions/get_calendar_data.php?month=${month}&year=${year}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('calMonthYear').textContent = data.monthName;
                    document.getElementById('calGrid').innerHTML = data.gridHtml;
                })
                .catch(error => console.error('Error fetching calendar:', error));
        }

        function changeMonth(step) {
            currentDate.setMonth(currentDate.getMonth() + step);
            renderCalendar();
        }

        // Image Modal Logic
        function openImageModal(src) {
            const modal = document.getElementById('imageModal');
            const img = document.getElementById('zoomedImage');
            img.src = src;
            modal.classList.remove('hidden');
            // Small delay for animation
            requestAnimationFrame(() => {
                modal.classList.remove('opacity-0');
                img.classList.remove('scale-95');
                img.classList.add('scale-100');
            });
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            const img = document.getElementById('zoomedImage');
            modal.classList.add('opacity-0');
            img.classList.remove('scale-100');
            img.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                img.src = '';
            }, 300);
        }

        // Alive Gradient Background Shader
        document.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('bgCanvas');
            if (!canvas || window.innerWidth < 1024) return; // Disable full-screen shader on tablets

            const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: false });
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.setPixelRatio(1); // Performance: Don't over-render on High DPI

            const scene = new THREE.Scene();
            const camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);

            // Shader Material - Design V3: Monochromatic Liquid Silk
            bgShaderMaterial = new THREE.ShaderMaterial({
                uniforms: {
                    uTime: { value: 0 },
                    uResolution: { value: new THREE.Vector2(window.innerWidth, window.innerHeight) },
                    uColor1: { value: new THREE.Color(0.99, 0.96, 0.97) },
                    uColor2: { value: new THREE.Color(0.95, 0.85, 0.90) }
                },
                vertexShader: `
                     varying vec2 vUv;
                     void main() {
                         vUv = uv;
                         gl_Position = vec4(position, 1.0);
                     }
                 `,
                fragmentShader: `
                     uniform float uTime;
                     uniform vec2 uResolution;
                     uniform vec3 uColor1;
                     uniform vec3 uColor2;
                     varying vec2 vUv;

                     void main() {
                         vec2 uv = gl_FragCoord.xy / uResolution.xy;
                         uv.x *= uResolution.x / uResolution.y;

                         // Use dynamic uniforms for colors
                         vec3 baseColor = uColor1;
                         vec3 shadowColor = uColor2;

                         // Create organic movement pattern
                         float t = uTime * 0.2;
                         
                         // Distort UVs based on noise/waves
                         vec2 p = uv;
                         p.y += sin(p.x * 5.0 + t) * 0.05;
                         p.x += cos(p.y * 5.0 - t) * 0.05;
                         
                         // Calculate intensity ("Silk" effect)
                         float wave = sin(p.x * 20.0 + t * 2.0) + sin(p.y * 15.0 + t) * 0.5;
                         wave = smoothstep(-2.0, 2.0, wave); // Soften

                         // monochromatic mix: Mix base and shadow based on wave intensity
                         vec3 finalColor = mix(baseColor, shadowColor, wave * 0.3);

                         // Add very subtle vignetting from center for depth
                         float d = length(uv - 0.5);
                         finalColor -= d * 0.05; 

                         gl_FragColor = vec4(finalColor, 1.0);
                     }
                 `
            });

            const plane = new THREE.PlaneGeometry(2, 2);
            const mesh = new THREE.Mesh(plane, bgShaderMaterial);
            scene.add(mesh);

            // Animation
            const clock = new THREE.Clock();

            function animate() {
                bgShaderMaterial.uniforms.uTime.value = clock.getElapsedTime();

                renderer.render(scene, camera);
                requestAnimationFrame(animate);
            }
            animate();

            // Events
            window.addEventListener('resize', () => {
                renderer.setSize(window.innerWidth, window.innerHeight);
                bgShaderMaterial.uniforms.uResolution.value.set(window.innerWidth, window.innerHeight);
            });

            // Initial Theme Sync
            setTheme(currentTheme);
        });
    </script>
    <!-- GSAP Animations -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Wait for internal components to be ready
            setTimeout(() => {
                const tl = gsap.timeline({
                    defaults: { ease: "power4.out" }
                });

                // 1. Reveal Navbar 
                tl.fromTo("nav",
                    { y: -100, opacity: 0, visibility: 'hidden' },
                    { duration: 0.6, y: 0, opacity: 1, visibility: 'visible' }
                );

                // 2. Reveal Headline Banner 
                tl.fromTo(".headline-banner",
                    { scale: 0.95, y: 40, opacity: 0, visibility: 'hidden' },
                    { duration: 0.5, scale: 1, y: 0, opacity: 1, visibility: 'visible' },
                    "-=0.4"
                );

                // 3. Reveal Apps Dashboard Container
                tl.fromTo("#appsContainer",
                    { y: 50, opacity: 0, visibility: 'hidden' },
                    { duration: 0.5, y: 0, opacity: 1, visibility: 'visible' },
                    "-=0.4"
                );

                // 4. Reveal Sidebar Widgets 
                tl.fromTo(".sidebar-widget",
                    { x: 50, opacity: 0, visibility: 'hidden' },
                    { duration: 0.4, x: 0, opacity: 1, visibility: 'visible', stagger: 0.1 },
                    "-=0.3"
                );

                // 5. Staggered App Cards Bloom 
                tl.fromTo(".app-card",
                    { scale: 0.8, opacity: 0, visibility: 'hidden' },
                    {
                        duration: 0.4,
                        scale: 1,
                        opacity: 1,
                        visibility: 'visible',
                        stagger: {
                            amount: 0.2,
                            grid: "auto",
                            from: "start"
                        },
                        ease: "back.out(1.4)"
                    },
                    "-=0.2"
                );

                // 6. Final Accent: Action Pill in Headline (Faster)
                tl.fromTo("#headlineActionPill",
                    { x: 30, scale: 0.8, opacity: 0, visibility: 'hidden' },
                    { duration: 0.5, x: 0, scale: 1, opacity: 1, visibility: 'visible', ease: "back.out(2)" },
                    "-=0.2"
                );



                // 8. Card Interaction Inside (Spotlight & Hover State)
                document.querySelectorAll('.app-card').forEach(card => {
                    let rafId = null;
                    card.addEventListener('mousemove', (e) => {
                        if (rafId) return;
                        rafId = requestAnimationFrame(() => {
                            const rect = card.getBoundingClientRect();
                            const x = ((e.clientX - rect.left) / rect.width) * 100;
                            const y = ((e.clientY - rect.top) / rect.height) * 100;
                            card.style.setProperty('--card-mouse-x', `${x}%`);
                            card.style.setProperty('--card-mouse-y', `${y}%`);
                            rafId = null;
                        });
                    });
                });

            }, 100);
        });
    </script>
</body>

</html>