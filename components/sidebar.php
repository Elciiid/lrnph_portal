<?php require_once __DIR__ . '/../includes/emeals_integration.php'; ?>
<!-- Sidebar Backdrop (Mobile only) -->
<div id="sidebarBackdrop" onclick="toggleSidebarInternal()"
    class="fixed inset-0 bg-black/20 backdrop-blur-sm z-20 hidden transition-opacity duration-300 pointer-events-none opacity-0">
</div>

<aside id="sidebar"
    class="fixed lg:left-3 left-0 top-3 bottom-0 lg:bottom-3 w-[280px] lg:w-[260px] bg-white lg:rounded-[20px] rounded-r-[20px] shadow-2xl lg:shadow-sm p-5 flex flex-col z-30 transition-all duration-300 transform -translate-x-full lg:translate-x-0">
    <!-- Logo / Brand -->
    <div class="mb-10 flex justify-center">
        <img src="assets/centralpoint_logo.png" alt="CentralPoint" class="h-12 w-auto object-contain"
            style="filter: invert(1) hue-rotate(180deg); mix-blend-mode: multiply;">
    </div>

    <!-- Scrollable Menu Wrapper -->
    <div class="flex-1 overflow-y-auto no-scrollbar -mx-2 px-2">
        <!-- Main Menu -->
        <div class="mb-8">
            <div class="text-[0.75rem] text-[#aaa] mb-3 font-semibold tracking-wide pl-3">MENU</div>
            <ul class="list-none">
                <?php if ($isIT || in_array('dashboard', $userPerms)): ?>
                    <li class="mb-1">
                        <a href="admin.php?page=dashboard"
                            class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-[0.95rem] <?php echo ($page == 'dashboard') ? 'bg-pink-50 text-pink-500' : 'text-[#888] hover:bg-pink-50 hover:text-pink-500'; ?>">
                            <i class="fa-brands fa-microsoft w-5 text-center"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($isIT || in_array('planner', $userPerms)): ?>
                    <li class="mb-1">
                        <a href="admin.php?page=planner"
                            class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-[0.95rem] <?php echo ($page == 'planner') ? 'bg-pink-50 text-pink-500' : 'text-[#888] hover:bg-pink-50 hover:text-pink-500'; ?>">
                            <i class="fa-regular fa-calendar w-5 text-center"></i>
                            <span>Planner</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($isIT || in_array('chatnow', $userPerms)): ?>
                    <li class="mb-1">
                        <a href="admin.php?page=chatnow"
                            class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-[0.95rem] <?php echo ($page == 'chatnow') ? 'bg-pink-50 text-pink-500' : 'text-[#888] hover:bg-pink-50 hover:text-pink-500'; ?>">
                            <i class="fa-regular fa-comments w-5 text-center"></i>
                            <span>ChatNow</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="h-px bg-gray-100 mx-4 mb-6"></div>

        <!-- General Menu -->
        <div class="mb-8">
            <div class="text-[0.75rem] text-[#aaa] mb-3 font-semibold tracking-wide pl-3">GENERAL</div>
            <ul class="list-none">
                <?php
                // Fetch App Modules from Database
                $appModulesQuery = "SELECT * FROM prtl_portal_AppModules ORDER BY CASE WHEN module_column = 'Common' THEN 0 ELSE 1 END, ID ASC";
                $appModulesStmt = $conn->query($appModulesQuery);
                $menuConfig = [];
                // Fetch Module Icons dynamically
                $iconMap = [];
                $modIconsQuery = "SELECT module_name, module_icon FROM prtl_portal_Modules";
                $modIconsStmt = $conn->query($modIconsQuery);
                if ($modIconsStmt) {
                    while ($mRow = $modIconsStmt->fetch(PDO::FETCH_ASSOC)) {
                        $mKey = strtolower(str_replace(' ', '_', trim($mRow['module_name'])));
                        $iconMap[$mKey] = $mRow['module_icon'];
                    }
                }

                if ($appModulesStmt) {
                    while ($row = $appModulesStmt->fetch(PDO::FETCH_ASSOC)) {
                        $perm = trim($row['perm_key']);

                        // Module key: "Common" -> "common", "QR Task" -> "qr_task"
                        $rawModule = trim($row['module_column']);
                        $moduleKey = strtolower(str_replace(' ', '_', $rawModule));

                        // Initialize category if not exists
                        if (!isset($menuConfig[$moduleKey])) {
                            $menuConfig[$moduleKey] = [
                                'label' => $rawModule,
                                'icon' => $iconMap[$moduleKey] ?? 'fa-solid fa-box',
                                'id' => $moduleKey,
                                'apps' => []
                            ];
                        }

                        // Generate context-aware permission key to separate access control
                        $uniquePerm = ($moduleKey === 'common') ? $perm : ($perm . '__' . $moduleKey);

                        // Add App
                        $menuConfig[$moduleKey]['apps'][] = [
                            'label' => trim($row['app_name']),
                            'url' => trim($row['app_url']),
                            'perm' => $uniquePerm
                        ];
                    }
                }

                if (isset($menuConfig['chatnow'])) {
                    foreach ($menuConfig['chatnow']['apps'] as &$app) {
                        // Redirect DB-based ChatNow links to internal admin page
                        if (stripos($app['url'], 'msg.php') !== false) {
                            $app['url'] = 'admin.php?page=chatnow';
                        }
                    }
                    unset($app); // break reference
                }
                if (!isset($menuConfig['common'])) {
                    $menuConfig['common'] = [
                        'label' => 'Common',
                        'icon' => 'fa-solid fa-box',
                        'id' => 'common',
                        'apps' => []
                    ];
                }


                foreach ($menuConfig as $catKey => $category):
                    // Filter apps based on permissions
                    $authorizedApps = [];
                    foreach ($category['apps'] as $app) {
                        // Check explicit database permission (IT sees all)
                        if ($isIT || in_array($app['perm'], $userPerms)) {
                            $authorizedApps[] = $app;
                        }
                    }

                    // Only render if authorized apps exist
                    if (!empty($authorizedApps)):
                        ?>
                        <li class="mb-1">
                            <button
                                onclick="toggleMenu('<?php echo $category['id']; ?>AppsMenu', '<?php echo $category['id']; ?>Chevron')"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-[#888] rounded-xl transition-all duration-200 font-medium text-[0.95rem] hover:bg-pink-50 hover:text-pink-500 group">
                                <div class="flex items-center gap-3">
                                    <i class="<?php echo $category['icon']; ?> w-5 text-center"></i>
                                    <span><?php echo $category['label']; ?></span>
                                </div>
                                <i id="<?php echo $category['id']; ?>Chevron"
                                    class="fa-solid fa-chevron-right text-xs transition-transform duration-200"></i>
                            </button>

                            <!-- Submenu -->
                            <div id="<?php echo $category['id']; ?>AppsMenu"
                                class="hidden fixed left-[280px] w-64 bg-white rounded-2xl shadow-2xl border border-pink-100 p-2 z-50 flex flex-col gap-1">
                                <?php foreach ($authorizedApps as $authApp):
                                    $target = '_blank'; // Default for external apps
                                    if (strpos($authApp['url'], 'admin.php') === 0) {
                                        $target = '_self';
                                    }
                                    ?>
                                    <a href="<?php echo htmlspecialchars($authApp['url']); ?>" target="<?php echo $target; ?>"
                                        class="block px-3 py-2 text-[0.85rem] text-gray-500 hover:text-pink-600 hover:bg-pink-50 rounded-lg transition-colors">
                                        <?php echo htmlspecialchars($authApp['label']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </li>
                        <?php
                    endif;
                endforeach;
                ?>
            </ul>
        </div>
    </div>

    <!-- Upgrade Card / Sidebar Footer -->
    <div class="mt-auto">
        <div class="h-px bg-gray-100 mx-4 mb-4"></div>
        <div class="text-center">
            <img src="assets/it-logo.png" alt="IT Logo"
                class="max-w-[150px] h-auto opacity-90 block mx-auto transition-opacity duration-200 hover:opacity-100">
        </div>
    </div>
    <script>
        const allMenus = [
            <?php
            $jsMenus = [];
            foreach ($menuConfig as $catKey => $cat) {
                // Generate menu IDs for JS toggling
                echo "{ menu: '{$cat['id']}AppsMenu', chevron: '{$cat['id']}Chevron' },";
            }
            ?>
        ];

        let activeMenuId = null;

        function toggleSidebarInternal() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const mainContent = document.querySelector('main');

            if (sidebar.classList.contains('-translate-x-full')) {
                // Open Sidebar
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden', 'pointer-events-none');
                setTimeout(() => {
                    backdrop.classList.add('opacity-100');
                }, 10);
                document.body.classList.add('overflow-hidden');
            } else {
                // Close Sidebar
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.remove('opacity-100');
                setTimeout(() => {
                    backdrop.classList.add('hidden', 'pointer-events-none');
                }, 300);
                document.body.classList.remove('overflow-hidden');
            }
        }

        // Expose to window for header button
        window.toggleSidebar = toggleSidebarInternal;

        function toggleMenu(targetMenuId, targetChevronId) {
            allMenus.forEach(item => {
                const menuEl = document.getElementById(item.menu);
                const chevronEl = document.getElementById(item.chevron);

                if (menuEl && chevronEl) {
                    if (item.menu === targetMenuId) {
                        // Toggle current
                        if (menuEl.classList.contains('hidden')) {
                            menuEl.classList.remove('hidden');
                            chevronEl.style.transform = 'rotate(90deg)';

                            // Position logic (simple)
                            const button = chevronEl.closest('button');
                            if (button) {
                                const rect = button.getBoundingClientRect();
                                menuEl.style.top = rect.top + 'px';
                                // Support mobile positioning
                                if (window.innerWidth < 1024) {
                                    menuEl.style.left = '20px';
                                    menuEl.style.width = 'calc(100% - 40px)';
                                    menuEl.style.position = 'fixed';
                                    menuEl.style.top = '50%';
                                    menuEl.style.transform = 'translateY(-50%)';
                                } else {
                                    menuEl.style.left = '280px';
                                    menuEl.style.width = '256px';
                                    menuEl.style.position = 'fixed';
                                    menuEl.style.transform = 'none';
                                }
                            }
                            activeMenuId = targetMenuId;
                        } else {
                            menuEl.classList.add('hidden');
                            chevronEl.style.transform = 'rotate(0deg)';
                            activeMenuId = null;
                        }
                    } else {
                        // Close others
                        if (!menuEl.classList.contains('hidden')) {
                            menuEl.classList.add('hidden');
                            if (chevronEl) chevronEl.style.transform = 'rotate(0deg)';
                        }
                    }
                }
            });
        }

        // Close on outside click (Desktop & Mobile)
        document.addEventListener('click', function (e) {
            if (activeMenuId && !e.target.closest('#' + activeMenuId) && !e.target.closest('button[onclick*="' + activeMenuId + '"]')) {
                const item = allMenus.find(m => m.menu === activeMenuId);
                if (item) {
                    const menuEl = document.getElementById(item.menu);
                    const chevronEl = document.getElementById(item.chevron);
                    if (menuEl) menuEl.classList.add('hidden');
                    if (chevronEl) chevronEl.style.transform = 'rotate(0deg)';
                    activeMenuId = null;
                }
            }
        });

        // Close on scroll
        const sidebarScroll = document.querySelector('aside > div.overflow-y-auto');
        if (sidebarScroll) {
            sidebarScroll.addEventListener('scroll', function () {
                if (activeMenuId && window.innerWidth >= 1024) { // Only auto-close on scroll for desktop
                    const item = allMenus.find(m => m.menu === activeMenuId);
                    if (item) {
                        const menuEl = document.getElementById(item.menu);
                        const chevronEl = document.getElementById(item.chevron);
                        if (menuEl) menuEl.classList.add('hidden');
                        if (chevronEl) chevronEl.style.transform = 'rotate(0deg)';
                        activeMenuId = null;
                    }
                }
            });
        }
    </script>
</aside>