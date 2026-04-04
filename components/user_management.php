<?php
// Group users by department

// Configuration for dynamic access control (Same as Sidebar)
// Fetch Core Access from Database
$coreAccessQuery = "SELECT * FROM prtl_portal_CoreAccess ORDER BY ID ASC";
$coreAccessStmt = $conn->query($coreAccessQuery);
$coreAccessList = [];
if ($coreAccessStmt) {
    while ($row = $coreAccessStmt->fetch(PDO::FETCH_ASSOC)) {
        $coreAccessList[] = $row;
    }
}

// Fetch App Modules from Database
$appModulesQuery = "SELECT * FROM prtl_portal_AppModules ORDER BY ID ASC";
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
        
        // Deduplication removed to allow module-specific tracking

        // Module key: "Common" -> "common", "QR Task" -> "qr_task"
        $rawModule = trim($row['module_column']);
        $moduleKey = strtolower(str_replace(' ', '_', $rawModule));

        // Initialize category if not exists
        if (!isset($menuConfig[$moduleKey])) {
            $menuConfig[$moduleKey] = [
                'label' => $rawModule,
                'icon' => $iconMap[$moduleKey] ?? 'fa-solid fa-box', // Default icon
                'apps' => []
            ];
        }

        // Generate context-aware permission key
        // If module is NOT common, append module key to distinguish access
        // Common apps keep original key for backward compatibility
        $uniquePerm = ($moduleKey === 'common') ? $perm : ($perm . '__' . $moduleKey);

        // Add App
        $menuConfig[$moduleKey]['apps'][] = [
            'label' => trim($row['app_name']),
            'perm' => $uniquePerm
        ];
    }
}

// We fetch users first, then group them in PHP
// We fetch users first, then group them in PHP

$usersQuery = "SELECT lu.username, lu.status, lu.role, ml.FirstName, ml.LastName, ml.Department, ml.PositionTitle, ml.EmployeeID
               FROM prtl_lrnph_users lu
               INNER JOIN prtl_lrn_master_list ml ON lu.username = ml.BiometricsID
               WHERE ml.isActive = 1
               ORDER BY ml.LastName ASC";
$usersStmt = $conn->query($usersQuery);

$groupedUsers = [];
$totalUsers = 0;

$seenUsers = [];
if ($usersStmt) {
    while ($row = $usersStmt->fetch(PDO::FETCH_ASSOC)) {
        // Prevent duplicates
        if (isset($seenUsers[$row['username']])) {
            continue;
        }
        $seenUsers[$row['username']] = true;

        $dept = $row['Department'] ?: 'Unassigned';
        if (!isset($groupedUsers[$dept])) {
            $groupedUsers[$dept] = [];
        }
        $groupedUsers[$dept][] = $row;
        $totalUsers++;
    }
}
ksort($groupedUsers); // Sort departments alphabetically

// Fetch permissions and merge
$allPermsQuery = "SELECT username, perm_key FROM prtl_portal_user_access";
$allPermsStmt = $conn->query($allPermsQuery);
$userPermissions = [];
if ($allPermsStmt) {
    while ($pRow = $allPermsStmt->fetch(PDO::FETCH_ASSOC)) {
        $uKey = strtoupper(trim((string) $pRow['username']));
        $pKey = strtolower(trim((string) $pRow['perm_key']));
        $userPermissions[$uKey][] = $pKey;
    }
}

// Inject permissions into groupedUsers
foreach ($groupedUsers as $dept => &$deptUsers) {
    foreach ($deptUsers as &$u) {
        $uKey = strtoupper(trim((string) $u['username']));
        $u['permissions'] = $userPermissions[$uKey] ?? [];
    }
}
unset($deptUsers);
unset($u);
?>

<div class="flex flex-col gap-6 transition-all duration-500">
    <!-- Ultra Modern Header -->
    <div class="flex items-center gap-4 shrink-0 px-2 lg:px-0">
        <!-- Title (Text Only) -->
        <div>
            <h2 class="text-2xl font-black text-gray-800 tracking-tighter leading-none">User Management</h2>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] flex items-center gap-2 mt-2">
                <span class="w-1.5 h-1.5 rounded-full bg-pink-500/50"></span>
                Staff Accounts & Access
            </p>
        </div>

        <!-- Central Search Bar (Filling the gap) -->
        <div class="flex-1 flex justify-center px-4 animate-in fade-in slide-in-from-bottom-2 duration-700 delay-150">
            <div class="relative group w-full max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-pink-500 transition-colors"></i>
                <input type="text" 
                    placeholder="Search departments" 
                    class="w-full bg-white/50 backdrop-blur-md border border-white/60 pl-11 pr-4 py-3 rounded-2xl text-sm font-medium outline-none focus:bg-white focus:border-pink-300 focus:ring-4 focus:ring-pink-500/5 transition-all shadow-sm"
                    onkeyup="filterGlobalUsers(this.value)">
            </div>
        </div>

        <!-- Stats Container -->
        <div class="bg-white/80 backdrop-blur-xl border border-white/60 px-6 py-2 rounded-2xl flex items-center gap-4 shadow-sm ml-auto">
            <div class="flex flex-col items-end leading-tight">
                <span class="text-[10px] text-gray-400 font-black uppercase tracking-widest">Active Users</span>
                <span class="text-xl font-black text-pink-600 leading-none"><?php echo $totalUsers; ?></span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-pink-500 to-pink-300 flex items-center justify-center text-white shadow-md shadow-pink-500/10">
                <i class="fa-solid fa-user-shield"></i>
            </div>
        </div>
    </div>

    <!-- Alert prtl_Messages (Same as before) -->
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 text-red-500 p-4 rounded-xl border border-red-100 flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <?php
                if ($_GET['error'] == 'user_exists')
                    echo "User already exists.";
                elseif ($_GET['error'] == 'insert_failed')
                    echo "Failed to add user. Please try again.";
                elseif ($_GET['error'] == 'update_failed')
                    echo "Failed to update user status.";
                elseif ($_GET['error'] == 'cannot_deactivate_self')
                    echo "You cannot deactivate your own account.";
                else
                    echo "An error occurred.";
                ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] !== 'perms_updated'): ?>
        <div class="bg-emerald-50 text-emerald-500 p-4 rounded-xl border border-emerald-100 flex items-center gap-3">
            <i class="fa-solid fa-circle-check"></i>
            <div>
                <?php
                if ($_GET['success'] == 'user_added')
                    echo "User added successfully.";
                elseif ($_GET['success'] == 'status_updated')
                    echo "User status updated successfully.";
                ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Departments Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (empty($groupedUsers)): ?>
            <div class="col-span-full py-20 text-center text-gray-400">
                <i class="fa-solid fa-users-slash text-5xl mb-4 opacity-20"></i>
                <p>No active portal users found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($groupedUsers as $dept => $users): ?>
                <div onclick='openDeptModal(<?php echo json_encode($dept, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                    class="bg-white p-6 rounded-[24px] shadow-sm hover:shadow-md transition-all cursor-pointer border border-gray-100 group relative overflow-hidden flex flex-col justify-center min-h-[160px]">

                    <div
                        class="absolute top-0 right-0 w-24 h-24 bg-pink-50 rounded-bl-[100px] -mr-4 -mt-4 transition-transform group-hover:scale-110">
                    </div>

                    <span
                        class="absolute top-5 right-5 bg-pink-100 text-pink-600 text-[10px] font-bold px-3 py-1 rounded-full shadow-sm z-20">
                        <?php echo count($users); ?>
                    </span>

                    <?php
                    // Adjusted sizing for better fit
                    $titleClass = strlen($dept) > 25 ? 'text-lg' : 'text-xl';
                    ?>

                    <div class="relative z-10 text-center w-full flex-1 flex flex-col justify-center items-center">
                        <h3
                            class="font-extrabold text-gray-800 <?php echo $titleClass; ?> leading-tight break-words w-full px-2">
                            <?php echo htmlspecialchars($dept); ?>
                        </h3>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Department Users Modal -->
<div id="deptUsersModal" class="hidden fixed inset-0 z-[50]">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onclick="document.getElementById('deptUsersModal').classList.add('hidden')"></div>
    <div
        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl w-full max-w-4xl h-[80vh] flex flex-col shadow-2xl animate-fade-in-up">

        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-100 flex justify-between items-center shrink-0">
            <div>
                <h3 class="text-2xl font-bold text-gray-800" id="modalDeptTitle">Department Name</h3>
                <p class="text-sm text-gray-500">Manage portal access for this department</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="deptSearchInput" placeholder="Search user..." onkeyup="filterDeptUsers()"
                        class="pl-9 pr-4 py-2 bg-gray-50 border border-transparent focus:bg-white focus:border-gray-200 rounded-xl text-sm outline-none transition-all w-64">
                </div>
                <button onclick="document.getElementById('deptUsersModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Scrollable Users List -->
        <div class="flex-1 overflow-y-auto p-6 bg-gray-50/50">
            <div id="deptUsersList" class="grid grid-cols-1 gap-3">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>
</div>



<!-- Edit Permissions Modal (Reused) -->
<div id="editPermissionsModal" class="hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onclick="document.getElementById('editPermissionsModal').classList.add('hidden')"></div>
    <div
        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl w-full max-w-6xl p-8 shadow-2xl animate-fade-in-up">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800">Edit Permissions</h3>
            <button onclick="document.getElementById('editPermissionsModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="actions/update_user_permissions.php" method="POST" class="space-y-5">
            <input type="hidden" name="username" id="editPermUsername">

            <div class="bg-pink-50 p-4 rounded-xl mb-4">
                <p class="text-xs text-pink-600 font-medium">Select sections this user can access:</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 relative">
                <!-- Vertical Divider (Desktop) -->
                <div class="hidden lg:block absolute top-0 bottom-0 left-1/2 w-px bg-gray-100 -ml-[0.5px]"></div>

                <!-- Left Column: Core Access -->
                <div class="space-y-4">
                    <h4
                        class="font-bold text-gray-700 border-b-2 border-pink-100 pb-2 text-sm tracking-wide uppercase flex items-center gap-2">
                        <i class="fa-solid fa-cube text-pink-400"></i> Core Access
                    </h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach ($coreAccessList as $core): ?>
                            <?php 
                                // Mandatory logic removed - user can now toggle these
                                $isMandatory = false; // No longer mandatory
                                $checkedAttr = ''; // Rely on JS to set initial state based on data
                                $classAttr = '';
                                
                                // Normalize Key for ID/Consistency
                                $permKey = strtolower(trim($core['perm_key']));
                            ?>
                            <label
                                class="flex items-center gap-3 p-3 border border-gray-100 rounded-xl hover:bg-gray-50 cursor-pointer transition-colors h-full <?php echo $classAttr; ?>">
                                <input type="checkbox" name="permissions[]" value="<?php echo $permKey; ?>" 
                                    id="perm_<?php echo $permKey; ?>"
                                    class="w-5 h-5 text-pink-500 rounded focus:ring-pink-500 border-gray-300"
                                    data-perm="<?php echo $permKey; ?>"
                                    <?php echo $checkedAttr; ?>>
                                <div>
                                    <span class="block text-sm font-bold text-gray-800"><?php echo htmlspecialchars($core['access_name']); ?></span>
                                    <span class="block text-[10px] text-gray-400 leading-tight"><?php echo htmlspecialchars($core['description']); ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right Column: App Modules -->
                <div class="space-y-4">
                    <h4
                        class="font-bold text-gray-700 border-b-2 border-pink-100 pb-2 text-sm tracking-wide uppercase flex items-center gap-2">
                        <i class="fa-solid fa-cubes text-pink-400"></i> App Modules
                    </h4>

                    <div class="space-y-2 h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                        <?php foreach ($menuConfig as $key => $category): ?>
                            <div
                                class="border border-gray-100 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                                <button type="button"
                                    onclick="togglePermAccordion('accordion-<?php echo $key; ?>', 'chevron-<?php echo $key; ?>')"
                                    class="w-full flex items-center justify-between px-4 py-3 bg-white hover:bg-gray-50 transition-colors text-left group">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-8 h-8 rounded-lg bg-pink-50 flex items-center justify-center text-pink-500 group-hover:bg-pink-100 group-hover:text-pink-600 transition-colors">
                                            <i class="<?php echo $category['icon']; ?>"></i>
                                        </div>
                                        <span
                                            class="font-bold text-gray-700 text-sm"><?php echo $category['label']; ?></span>
                                    </div>
                                    <i id="chevron-<?php echo $key; ?>"
                                        class="fa-solid fa-chevron-down text-gray-400 text-xs transition-transform duration-200"></i>
                                </button>

                                <div id="accordion-<?php echo $key; ?>"
                                    class="hidden bg-gray-50/50 border-t border-gray-100 p-3">
                                    <div class="grid grid-cols-1 gap-2">
                                        <?php foreach ($category['apps'] as $app): ?>
                                            <label
                                                class="flex items-center gap-3 p-2.5 bg-white border border-gray-100 rounded-lg hover:border-pink-200 cursor-pointer transition-all">
                                                <input type="checkbox" name="permissions[]" value="<?php echo $app['perm']; ?>"
                                                    class="w-4 h-4 text-pink-500 rounded focus:ring-pink-500 border-gray-300"
                                                    data-perm="<?php echo $app['perm']; ?>">
                                                <div class="flex flex-col">
                                                    <span
                                                        class="text-[0.8rem] font-bold text-gray-700 leading-tight"><?php echo htmlspecialchars($app['label']); ?></span>
                                                    <span
                                                        class="text-[0.65rem] text-gray-400 font-mono mt-0.5"><?php echo $app['perm']; ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <button type="submit"
                    class="w-full bg-pink-500 text-white py-3.5 rounded-xl font-bold shadow-lg shadow-pink-500/30 hover:bg-pink-600 transition-all transform hover:-translate-y-0.5 text-lg">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const allDepartmentsData = <?php echo json_encode($groupedUsers, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?: '{}'; ?>;

    // Cache current users for filtering and source of truth
    let currentDeptUsers = [];
    let originalDeptUsers = []; // New variable to store the unfiltered list

    function openDeptModal(deptName) {
        const users = allDepartmentsData[deptName];
        if (users) {
            document.getElementById('deptUsersModal').classList.remove('hidden');
            document.getElementById('modalDeptTitle').textContent = deptName;
            originalDeptUsers = users; // Store the full list
            renderDeptUsers(users);
        }
    }

    function renderDeptUsers(users) {
        currentDeptUsers = users;
        const list = document.getElementById('deptUsersList');
        list.innerHTML = '';

        if (users.length === 0) {
            list.innerHTML = '<div class="text-center p-10 text-gray-400">No users found.</div>';
            return;
        }

        users.forEach(user => {
            const isActive = (user.status === 'active');
            const initials = user.FirstName.charAt(0) + user.LastName.charAt(0);

            // Build the Action URL
            const actionUrl = `actions/toggle_portal_user.php?username=${user.username}&status=${isActive ? 'inactive' : 'active'}`;
            const confirmMsg = isActive ? "Deactivate this user?" : "Activate this user?";
            // HTML for toggle button
            const toggleBtn = isActive
                ? `<a href="${actionUrl}" onclick="return confirm('${confirmMsg}'); event.stopPropagation();" class="text-pink-500 hover:text-pink-600 p-2"><i class="fa-solid fa-toggle-on text-xl"></i></a>`
                : `<a href="${actionUrl}" onclick="return confirm('${confirmMsg}'); event.stopPropagation();" class="text-gray-300 hover:text-pink-500 p-2"><i class="fa-solid fa-toggle-off text-xl"></i></a>`;

            const editBtn = `<button onclick="fetchAndEditPerms('${user.username}'); event.stopPropagation();" class="text-pink-400 hover:text-pink-600 p-2"><i class="fa-solid fa-pen-to-square"></i></button>`;

            const item = document.createElement('div');
            item.className = 'bg-white p-4 rounded-xl border border-gray-200 flex items-center justify-between hover:border-pink-200 transition-colors';
            item.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center font-bold text-gray-500 text-sm">
                        ${initials}
                    </div>
                    <div>
                        <div class="font-bold text-gray-800">${user.FirstName} ${user.LastName}</div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-[9px] font-black uppercase px-2 py-0.5 bg-gray-100 text-gray-500 rounded border border-gray-200">${user.role}</span>
                            <span class="text-xs text-gray-400 font-medium">${user.PositionTitle} | ID: ${user.username}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center">
                    ${editBtn}
                    ${toggleBtn}
                </div>
            `;
            list.appendChild(item);
        });
    }

    function filterDeptUsers() {
        const query = document.getElementById('deptSearchInput').value.toLowerCase();
        const filtered = originalDeptUsers.filter(u =>
            u.FirstName.toLowerCase().includes(query) ||
            u.LastName.toLowerCase().includes(query) ||
            String(u.username).toLowerCase().includes(query)
        );
        renderDeptUsers(filtered);
    }

    // New helper to fetch permissions via AJAX since they aren't in the main user object anymore
    async function fetchAndEditPerms(username) {
        const targetId = String(username).trim().toUpperCase();
        const user = originalDeptUsers.find(u => String(u.username).trim().toUpperCase() === targetId);
        if (user) {
            openEditPermissionsModal({
                username: user.username,
                perms: user.permissions || [],
                department: user.Department // Pass the department
            });
        }
    }

    function togglePermAccordion(id, chevronId) {
        const el = document.getElementById(id);
        const chevron = document.getElementById(chevronId);
        if (el.classList.contains('hidden')) {
            el.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';
        } else {
            el.classList.add('hidden');
            chevron.style.transform = 'rotate(0deg)';
        }
    }

    function openEditPermissionsModal(data) {
        document.getElementById('editPermissionsModal').classList.remove('hidden');
        document.getElementById('editPermUsername').value = data.username;

        // Reset ALL checkboxes first
        const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
        checkboxes.forEach(cb => {
            // Keep required ones checked if they are disabled/readonly (logic below handles re-checking)
            cb.checked = false;
        });

        // Handle IT Department Defaults
        const dept = (data.department || '').trim().toUpperCase();
        const isIT = /IT|INFORMATION TECHNOLOGY/.test(dept);

        if (isIT) {
            checkboxes.forEach(cb => cb.checked = true);
        } else {
            // For non-IT:

            if (!data.perms || data.perms.length === 0) {
                const defaultPerms = [
                    'dashboard', 'planner', 'common', 'chatnow', 'emeals', 'emeals_settings', // Core
                    'secure_pass', 'ewos', 'tickethub', 'checkpoint',
                    'door_access', 'hr_touchpoint', 'uniform_request', 'driver_request'
                ];

                defaultPerms.forEach(perm => {
                    // Try by ID first (Core)
                    let cb = document.getElementById('perm_' + perm);
                    // Or by data attribute (Apps)
                    if (!cb) cb = document.querySelector(`input[data-perm="${perm}"]`);
                    
                    if (cb) cb.checked = true;
                });
            }

            // 2. Load explicitly saved permissions
            if (data.perms && data.perms.length > 0) {
                data.perms.forEach(perm => {
                    // Try by ID first (Core)
                    let cb = document.getElementById('perm_' + perm);
                    // Or by data attribute (Apps)
                    if (!cb) cb = document.querySelector(`input[data-perm="${perm}"]`);

                    if (cb) cb.checked = true;
                });
            }
        }
    }

    function filterGlobalUsers(query) {
        query = query.toLowerCase().trim();
        const deptCards = document.querySelectorAll('.grid > div[onclick^="openDeptModal"]');
        
        deptCards.forEach(card => {
            const h3 = card.querySelector('h3');
            if(!h3) return;
            const deptName = h3.textContent.toLowerCase();
            
            // Extract dept key from onclick
            const onclick = card.getAttribute('onclick');
            const match = onclick ? onclick.match(/'([^']+)'/) : null;
            const deptKey = match ? match[1] : '';
            const usersInDept = allDepartmentsData[deptKey] || [];
            
            const hasUserMatch = usersInDept.some(u => 
                (u.FirstName && u.FirstName.toLowerCase().includes(query)) || 
                (u.LastName && u.LastName.toLowerCase().includes(query)) || 
                (u.username && u.username.toLowerCase().includes(query))
            );

            if (deptName.includes(query) || hasUserMatch) {
                card.style.display = 'flex';
                card.classList.add('animate-in', 'fade-in', 'zoom-in-95');
            } else {
                card.style.display = 'none';
                card.classList.remove('animate-in', 'fade-in', 'zoom-in-95');
            }
        });
    }
</script>

<!-- Success Modal for Permissions -->
<?php if (isset($_GET['success']) && $_GET['success'] == 'perms_updated'): ?>
    <div id="permsSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeSuccessModal()"></div>
        <div class="relative bg-white rounded-[32px] p-8 max-w-sm w-full shadow-2xl animate-success-fade-in text-center">
            <div class="w-20 h-20 bg-pink-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-check text-3xl text-pink-500"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Success!</h3>
            <p class="text-gray-500 mb-8 font-medium">User permissions have been updated successfully.</p>
            <button onclick="closeSuccessModal()"
                class="w-full bg-pink-500 text-white py-4 rounded-2xl font-bold shadow-lg shadow-pink-500/30 hover:bg-pink-600 transition-all hover:scale-[1.02] active:scale-[0.98]">
                Great, thanks!
            </button>
        </div>
    </div>
    <script>
        function closeSuccessModal() {
            const modal = document.getElementById('permsSuccessModal');
            modal.classList.add('hidden');
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, '', url);
        }
    </script>
<?php endif; ?>

<style>
    @keyframes fade-in-up {
        from {
            opacity: 0;
            transform: translate(-50%, -40%);
        }

        to {
            opacity: 1;
            transform: translate(-50%, -50%);
        }
    }

    .animate-fade-in-up {
        animation: fade-in-up 0.3s ease-out forwards;
    }

    @keyframes success-fade-in {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-success-fade-in {
        animation: success-fade-in 0.3s ease-out forwards;
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #fce7f3;
        border-radius: 20px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background-color: #fbcfe8;
    }
</style>