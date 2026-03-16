<header class="flex justify-between items-center mb-2">
    <div class="flex items-center gap-4">
        <!-- Sidebar Toggle (Mobile) -->
        <button onclick="window.toggleSidebar()"
            class="lg:hidden bg-white border border-[#f0f0f0] w-10 h-10 rounded-xl flex items-center justify-center cursor-pointer text-[#888] transition-all duration-200 hover:bg-[#f8f9fa] hover:text-pink-500 shadow-sm">
            <i class="fa-solid fa-bars-staggered"></i>
        </button>

        <h2 class="text-[1.4rem] lg:text-[1.8rem] font-bold text-[#1a1a1a] truncate max-w-[150px] sm:max-w-none">
            <?php
            switch ($page) {
                case 'dashboard':
                    echo 'Dashboard';
                    break;
                default:
                    echo ''; // Keep other pages minimalist
            }
            ?>
        </h2>
    </div>

    <div class="flex items-center gap-4">
        <!-- Help Button -->
        <button onclick="toggleHelpModal()"
            class="bg-white border border-[#f0f0f0] w-9 h-9 rounded-full flex items-center justify-center cursor-pointer text-[#888] transition-all duration-200 hover:bg-[#f8f9fa] hover:text-pink-500">
            <i class="fa-regular fa-circle-question"></i>
        </button>

        <!-- Notification Button -->
        <button id="notificationBtn" onclick="requestNotificationPermission()"
            class="bg-white border border-[#f0f0f0] w-9 h-9 rounded-full flex items-center justify-center cursor-pointer text-[#888] transition-all duration-200 relative hover:bg-[#f8f9fa] hover:text-pink-500">
            <i class="fa-regular fa-bell"></i>
            <span id="notifStatusDot"
                class="absolute top-3 right-[14px] w-2 h-2 bg-[#FF4D4D] rounded-full border-2 border-white"></span>
        </button>

        <!-- User Profile -->
        <div class="relative group">
            <div onclick="document.getElementById('profileDropdown').classList.toggle('hidden')"
                class="flex items-center gap-3 cursor-pointer py-1 pr-3 pl-1 rounded-[30px] transition-colors duration-200 hover:bg-white">
                <img src="<?php echo $userPhoto; ?>" onerror="this.src='<?php echo DEFAULT_AVATAR_URL; ?>'" alt="User"
                    class="w-11 h-11 rounded-full object-cover">
                <div class="text-left">
                    <h5 class="text-[0.9rem] font-semibold mb-[2px]"><?php echo $userName; ?></h5>
                    <p class="text-[0.75rem] text-[#888]"><?php echo $userPosition; ?></p>
                </div>
                <i class="fa-solid fa-chevron-down text-[0.8rem] text-[#888] ml-2"></i>
            </div>

            <!-- Dropdown Menu -->
            <div id="profileDropdown"
                class="hidden absolute right-0 top-full mt-2 min-w-[160px] bg-white rounded-2xl shadow-[0_10px_40px_rgba(0,0,0,0.12)] p-1.5 border border-[#f0f0f0] z-[100] animate-fade-in-down <?php echo ($isIT || !empty(array_intersect(['content', 'announcements', 'user_management', 'new_employee'], $userPerms))) ? 'w-52' : 'w-auto'; ?>">

                <?php
                $hasSettingsAccess = $isIT || !empty(array_intersect(['content', 'announcements', 'user_management', 'new_employee'], $userPerms));
                if ($hasSettingsAccess):
                    ?>
                    <!-- Settings Toggle -->
                    <button onclick="toggleSettingsMenu()"
                        class="flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-600 rounded-xl hover:bg-gray-50 transition-colors group mb-1">
                        <div class="flex items-center gap-3">
                            <i
                                class="fa-solid fa-gear w-5 text-center text-gray-400 group-hover:text-pink-500 transition-colors"></i>
                            <span>Settings</span>
                        </div>
                        <i id="settingsChevron"
                            class="fa-solid fa-chevron-right text-xs text-gray-400 transition-transform duration-200"></i>
                    </button>

                    <!-- Hidden Admin Menu -->
                    <div id="settingsMenu" class="hidden pl-2 mb-2 space-y-0.5 border-l-2 border-gray-100 ml-5">
                        <?php if ($isIT || in_array('content', $userPerms)): ?>
                            <a href="admin.php?page=content"
                                class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-gray-500 rounded-lg hover:text-pink-600 hover:bg-pink-50 transition-colors">
                                Content Manager
                            </a>
                        <?php endif; ?>

                        <?php if ($isIT || in_array('announcements', $userPerms)): ?>
                            <a href="admin.php?page=announcements"
                                class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-gray-500 rounded-lg hover:text-pink-600 hover:bg-pink-50 transition-colors">
                                Announcements
                            </a>
                        <?php endif; ?>

                        <?php if ($isIT || in_array('user_management', $userPerms)): ?>
                            <a href="admin.php?page=user_management"
                                class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-gray-500 rounded-lg hover:text-pink-600 hover:bg-pink-50 transition-colors">
                                User Management
                            </a>
                        <?php endif; ?>

                        <?php if ($isIT || in_array('new_employee', $userPerms)): ?>
                            <a href="admin.php?page=new_employee"
                                class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-gray-500 rounded-lg hover:text-pink-600 hover:bg-pink-50 transition-colors">
                                New Employee
                            </a>
                        <?php endif; ?>

                        <?php if ($isIT): ?>
                            <a href="admin.php?page=settings"
                                class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-gray-500 rounded-lg hover:text-pink-600 hover:bg-pink-50 transition-colors">
                                System Settings
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="h-px bg-gray-100 my-1"></div>
                <?php endif; ?>

                <a href="logout.php"
                    class="flex items-center <?php echo ($hasSettingsAccess) ? 'justify-start gap-3' : 'justify-center'; ?> w-full py-2 px-3 text-sm text-[#dc3545] font-bold hover:bg-[#ffeef0] rounded-xl transition-all duration-200">
                    <i
                        class="fa-solid fa-power-off <?php echo ($hasSettingsAccess) ? 'w-5 text-center' : '-ml-1 mr-2'; ?> text-xs"></i>
                    Logout
                </a>
            </div>

            <script>
                function toggleSettingsMenu() {
                    const menu = document.getElementById('settingsMenu');
                    const chevron = document.getElementById('settingsChevron');
                    menu.classList.toggle('hidden');
                    if (menu.classList.contains('hidden')) {
                        chevron.style.transform = 'rotate(0deg)';
                    } else {
                        chevron.style.transform = 'rotate(90deg)';
                    }
                }
            </script>
        </div>
    </div>
</header>
<script>
    // Close dropdown when clicking outside
    document.addEventListener('click', function (event) {
        var dropdown = document.getElementById('profileDropdown');
        if (!dropdown) return;
        var profileBtn = dropdown.previousElementSibling;
        if (profileBtn && !profileBtn.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.add('hidden');
        }
    });

    /**
     * Browser Notification Logic
     */
    function updateNotifDot() {
        const dot = document.getElementById('notifStatusDot');
        if (!dot) return;

        const isAppEnabled = localStorage.getItem('app_notifications_enabled') !== 'false';

        if (Notification.permission === 'granted' && isAppEnabled) {
            dot.style.backgroundColor = '#10B981'; // Emerald/Green if accepted AND enabled
        } else if (Notification.permission === 'denied') {
            dot.style.backgroundColor = '#64748B'; // Gray if blocked by browser
        } else {
            dot.style.backgroundColor = '#FF4D4D'; // Red for default or muted
        }
    }

    async function requestNotificationPermission() {
        if (!("Notification" in window)) {
            alert("This browser does not support desktop notifications.");
            return;
        }

        const isAppEnabled = localStorage.getItem('app_notifications_enabled') !== 'false';

        // Toggle Logic
        if (Notification.permission === 'granted' && isAppEnabled) {
            // User wants to mute
            localStorage.setItem('app_notifications_enabled', 'false');
            updateNotifDot();
        } else {
            // User wants to enable or unmute
            const permission = await Notification.requestPermission();
            localStorage.setItem('app_notifications_enabled', 'true');
            updateNotifDot();

            if (permission === 'granted') {
                new Notification("Notifications Enabled", {
                    body: "You will now receive portal updates directly on your desktop.",
                    icon: "assets/lrn-logo.jpg"
                });
            }
        }
    }

    // Initial check
    document.addEventListener('DOMContentLoaded', updateNotifDot);
</script>