<?php
// settings.php - Manage Core Access and App Modules

// Security Check (restrict to IT or Admin)
// Assuming $isIT is available from admin.php or we check session
$isIT = (isset($_SESSION['department']) && preg_match('/IT|INFORMATION TECHNOLOGY/i', $_SESSION['department']));
if (!$isIT) {
    echo "<div class='p-10 text-center text-gray-500'>You do not have permission to access system settings.</div>";
    exit;
}

// Core Access fetch removed as per request

// Fetch App Modules
$appQuery = "SELECT * FROM \"prtl_portal_AppModules\" ORDER BY CASE WHEN module_column = 'Common' THEN 0 ELSE 1 END, module_column ASC, \"ID\" ASC";
$appStmt = $conn->query($appQuery);
$appModules = [];
if ($appStmt) {
    while ($row = $appStmt->fetch(PDO::FETCH_ASSOC)) {
        $appModules[] = $row;
    }
}

// Ensure prtl_portal_Modules table exists handling missing schema
$checkTable = "SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'prtl_portal_Modules'";
$checkStmt = $conn->query($checkTable);
$tableExists = ($checkStmt && $checkStmt->fetchColumn() > 0);

if (!$tableExists) {
    $createTable = "CREATE TABLE prtl_portal_Modules (
        \"ID\" SERIAL PRIMARY KEY,
        module_name VARCHAR(255) NOT NULL,
        module_icon VARCHAR(255) DEFAULT 'fa-solid fa-box'
    )";
    $conn->exec($createTable);
}

// Fetch Available Modules
$modQuery = "SELECT * FROM \"prtl_portal_Modules\" ORDER BY module_name ASC";
$modStmt = $conn->query($modQuery);
$availableModules = [];
if ($modStmt) {
    while ($mRow = $modStmt->fetch(PDO::FETCH_ASSOC)) {
        $availableModules[] = $mRow;
    }
}

// Seed defaults - populate list if clean install
if (empty($availableModules)) {
    $defaults = [
        ['Common', 'fa-solid fa-layer-group'],
        ['QR Task', 'fa-solid fa-qrcode'],
        ['IT', 'fa-solid fa-laptop-code'],
        ['HR', 'fa-solid fa-users'],
        ['Sales', 'fa-solid fa-chart-line'],
        ['Admin', 'fa-solid fa-user-shield'],
        ['Production', 'fa-solid fa-industry']
    ];
    $insertSql = "INSERT INTO \"prtl_portal_Modules\" (module_name, module_icon) VALUES (?, ?)";
    $stmt = $conn->prepare($insertSql);
    foreach ($defaults as $def) {
        $stmt->execute($def);
    }
    // Re-fetch to show immediate result
    $modStmt = $conn->query($modQuery);
    if ($modStmt) {
        $availableModules = [];
        while ($mRow = $modStmt->fetch(PDO::FETCH_ASSOC)) {
            $availableModules[] = $mRow;
        }
    }
}
?>

<div class="flex flex-col gap-6 h-full">
    <!-- Header -->
    <div class="flex items-center justify-between shrink-0">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">System Settings</h1>
            <p class="text-sm text-gray-400 font-medium mt-1">Manage portal modules and core permissions</p>
        </div>
    </div>

    <!-- Content Area -->
    <div class="flex items-center justify-between shrink-0 mb-4">
        <div class="relative">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" id="searchInput" onkeyup="filterApps()" placeholder="Search modules..."
                class="pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-xs font-medium focus:outline-none focus:border-pink-500 w-64 shadow-sm transition-all">
        </div>
        <div class="flex gap-2">
            <button onclick="openModuleModal()"
                class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 transition-all flex items-center gap-2">
                <i class="fa-solid fa-layer-group"></i> Manage Modules
            </button>
            <button onclick="openAppModal()"
                class="px-4 py-2 bg-pink-500 text-white text-xs font-bold rounded-xl hover:bg-pink-600 transition-all flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Add App Module
            </button>
        </div>
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto pr-2 custom-scrollbar relative">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-6">
            <table class="w-full text-left">
                <thead class="bg-gray-50 border-b border-gray-100 sticky top-0 z-10">
                    <tr>
                        <th class="px-6 py-4 text-[0.65rem] font-bold text-gray-400 uppercase tracking-wider">App Name
                        </th>
                        <th class="px-6 py-4 text-[0.65rem] font-bold text-gray-400 uppercase tracking-wider">Module
                        </th>
                        <th class="px-6 py-4 text-[0.65rem] font-bold text-gray-400 uppercase tracking-wider">Perm Key
                        </th>
                        <th class="px-6 py-4 text-[0.65rem] font-bold text-gray-400 uppercase tracking-wider">URL</th>
                        <th
                            class="px-6 py-4 text-[0.65rem] font-bold text-gray-400 uppercase tracking-wider text-right">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($appModules as $item): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-6 py-4 text-sm font-bold text-gray-800">
                                <?php echo htmlspecialchars($item['app_name']); ?>
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">
                                <?php echo htmlspecialchars($item['module_column']); ?>
                            </td>
                            <td
                                class="px-6 py-4 text-xs font-mono text-pink-500 bg-pink-50/50 rounded-lg inline-block my-2 mx-6 w-fit px-2 py-1">
                                <?php echo htmlspecialchars($item['perm_key']); ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-blue-500 hover:underline truncate max-w-[150px]">
                                <a href="<?php echo htmlspecialchars($item['app_url'] ?? '#'); ?>"
                                    target="_blank"><?php echo htmlspecialchars($item['app_url'] ?? 'N/A'); ?></a>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button onclick='editApp(<?php echo json_encode($item); ?>)'
                                    class="w-8 h-8 rounded-lg bg-gray-50 text-blue-500 hover:bg-blue-50 transition-all"><i
                                        class="fa-solid fa-pen-to-square text-xs"></i></button>
                                <button onclick="deleteApp(<?php echo $item['ID']; ?>)"
                                    class="w-8 h-8 rounded-lg bg-gray-50 text-red-500 hover:bg-red-50 transition-all ml-1"><i
                                        class="fa-solid fa-trash text-xs"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($appModules)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">No app modules found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- App Module Modal -->
<div id="appModal"
    class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-gray-950/40 backdrop-blur-sm">
    <div class="bg-white p-8 rounded-3xl shadow-2xl max-w-lg w-full mx-4">
        <h3 id="appModalTitle" class="text-xl font-bold text-gray-900 mb-6">Add App Module</h3>
        <form action="actions/manage_apps.php" method="POST" class="space-y-4">
            <input type="hidden" name="id" id="app_id">
            <input type="hidden" name="action" id="app_action" value="add">

            <div class="space-y-1">
                <label class="text-xs font-bold text-gray-500 uppercase">App Name</label>
                <input type="text" name="app_name" id="app_name" required
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:border-pink-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-gray-500 uppercase">Module Group</label>
                    <select name="module_column" id="app_module" required
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:border-pink-500 outline-none text-gray-700 font-medium">
                        <option value="">Select Module...</option>
                        <?php foreach ($availableModules as $mod): ?>
                            <option value="<?php echo htmlspecialchars($mod['module_name']); ?>">
                                <?php echo htmlspecialchars($mod['module_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-gray-500 uppercase">Perm Key</label>
                    <input type="text" name="perm_key" id="app_perm" required
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:border-pink-500 outline-none font-mono">
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-gray-500 uppercase">App URL</label>
                <input type="text" name="app_url" id="app_url" placeholder="http://..."
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:border-pink-500 outline-none">
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeAppModal()"
                    class="px-6 py-2.5 text-sm font-bold text-gray-400 hover:text-gray-600">Cancel</button>
                <button type="submit"
                    class="px-8 py-2.5 bg-pink-500 text-white text-sm font-bold rounded-xl hover:bg-pink-600">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal"
    class="hidden fixed inset-0 z-[10000] flex items-center justify-center bg-gray-950/40 backdrop-blur-sm">
    <div class="bg-white p-6 rounded-3xl shadow-2xl max-w-sm w-full mx-4 text-center">
        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-xl"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-2">Delete App Module?</h3>
        <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete this module? This action cannot be undone
            and users will lose access immediately.</p>

        <div class="flex gap-3 justify-center">
            <button onclick="closeDeleteModal()"
                class="px-5 py-2 text-sm font-bold text-gray-500 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all">Cancel</button>
            <button onclick="confirmDelete()"
                class="px-5 py-2 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-lg shadow-red-200 transition-all">Delete</button>
        </div>
    </div>
</div>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="id" id="delete_id">
    <input type="hidden" name="action" value="delete">
</form>

<!-- Manage Modules Modal -->
<div id="moduleModal"
    class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-gray-950/40 backdrop-blur-sm">
    <div class="bg-white p-8 rounded-3xl shadow-2xl max-w-lg w-full mx-4 flex flex-col max-h-[80vh]">
        <div class="flex justify-between items-center mb-6 shrink-0">
            <h3 class="text-xl font-bold text-gray-900">Manage Modules</h3>
            <button onclick="document.getElementById('moduleModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- Add Module Form -->
        <form action="actions/manage_modules.php" method="POST" class="flex gap-2 mb-6 shrink-0">
            <input type="hidden" name="action" value="add">
            <div class="flex-1">
                <input type="text" name="module_name" placeholder="Module Name" required
                    class="w-full px-4 py-2 bg-gray-50 border border-gray-100 rounded-xl text-sm focus:border-pink-500 outline-none">
            </div>
            <div class="w-1/3 relative">
                <input type="hidden" name="module_icon" id="module_icon_input" required>
                <button type="button" onclick="openIconPicker()" id="iconPickerBtn"
                    class="w-full px-4 py-2 bg-gray-50 border border-gray-100 rounded-xl text-sm text-gray-500 hover:bg-gray-100 hover:border-pink-200 transition-all flex items-center justify-between h-full">
                    <span id="iconBtnLabel">Select Icon</span>
                    <i id="iconBtnPreview" class="fa-solid fa-icons"></i>
                </button>
            </div>
            <button type="submit"
                class="px-4 py-2 bg-pink-500 text-white rounded-xl hover:bg-pink-600 transition-colors">
                <i class="fa-solid fa-plus"></i>
            </button>
        </form>

        <!-- Current Modules List -->
        <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-2">
            <?php foreach ($availableModules as $mod): ?>
                <div
                    class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100 group hover:border-pink-200 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center text-pink-500 shadow-sm">
                            <i class="<?php echo htmlspecialchars($mod['module_icon'] ?? 'fa-solid fa-box'); ?>"></i>
                        </div>
                        <span
                            class="font-bold text-gray-700 text-sm"><?php echo htmlspecialchars($mod['module_name']); ?></span>
                    </div>
                    <form action="actions/manage_modules.php" method="POST"
                        onsubmit="return confirm('Delete this module?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $mod['ID']; ?>">
                        <button type="submit" class="text-gray-400 hover:text-red-500 p-2 transition-colors">
                            <i class="fa-solid fa-trash text-xs"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (empty($availableModules)): ?>
                <div class="text-center text-gray-400 py-4 text-sm">No modules found. Add one above.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Icon Picker Modal -->
<div id="iconPickerModal"
    class="hidden fixed inset-0 z-[10005] flex items-center justify-center bg-gray-950/40 backdrop-blur-sm">
    <div class="bg-white p-6 rounded-3xl shadow-2xl max-w-md w-full mx-4 flex flex-col max-h-[60vh]">
        <div class="flex justify-between items-center mb-4 shrink-0">
            <h3 class="text-lg font-bold text-gray-900">Choose Icon</h3>
            <button type="button" onclick="document.getElementById('iconPickerModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="grid grid-cols-5 gap-3 overflow-y-auto p-2 custom-scrollbar" id="iconGrid">
            <!-- Icons generated by JS -->
        </div>
    </div>
</div>

<script>
    const availableIcons = [
        'fa-solid fa-box', 'fa-solid fa-layer-group', 'fa-solid fa-cube', 'fa-solid fa-laptop-code',
        'fa-solid fa-users', 'fa-solid fa-chart-line', 'fa-solid fa-user-shield', 'fa-solid fa-industry',
        'fa-solid fa-list-check', 'fa-solid fa-truck', 'fa-solid fa-warehouse', 'fa-solid fa-file-invoice-dollar',
        'fa-solid fa-utensils', 'fa-solid fa-screwdriver-wrench', 'fa-solid fa-calendar-days', 'fa-solid fa-bullhorn',
        'fa-solid fa-globe', 'fa-solid fa-qrcode', 'fa-solid fa-ticket', 'fa-solid fa-id-card', 'fa-solid fa-clock',
        'fa-solid fa-money-bill-wave', 'fa-solid fa-clipboard-check', 'fa-solid fa-briefcase', 'fa-solid fa-building',
        'fa-solid fa-store', 'fa-solid fa-cart-shopping', 'fa-solid fa-comments', 'fa-solid fa-address-book'
    ];

    function openIconPicker() {
        const grid = document.getElementById('iconGrid');
        if (grid.children.length === 0) {
            availableIcons.forEach(iconClass => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'aspect-square flex items-center justify-center bg-gray-50 rounded-xl hover:bg-pink-50 hover:text-pink-500 hover:border hover:border-pink-200 transition-all text-gray-500 text-xl';
                btn.innerHTML = `<i class="${iconClass}"></i>`;
                btn.onclick = () => selectIcon(iconClass);
                grid.appendChild(btn);
            });
        }
        document.getElementById('iconPickerModal').classList.remove('hidden');
    }

    function selectIcon(iconClass) {
        document.getElementById('module_icon_input').value = iconClass;
        document.getElementById('iconBtnLabel').textContent = ''; // Hide text
        document.getElementById('iconBtnPreview').className = iconClass + ' text-pink-500 text-lg'; // Show big icon
        document.getElementById('iconPickerModal').classList.add('hidden');
    }

    function openModuleModal() {
        document.getElementById('moduleModal').classList.remove('hidden');
    }
    let deleteTargetId = null;

    function openAppModal() {
        document.getElementById('app_id').value = '';
        document.getElementById('app_action').value = 'add';
        document.getElementById('app_name').value = '';
        document.getElementById('app_module').value = 'Common';
        document.getElementById('app_perm').value = '';
        document.getElementById('app_url').value = '';
        document.getElementById('appModalTitle').innerText = 'Add App Module';
        document.getElementById('appModal').classList.remove('hidden');
    }
    function editApp(item) {
        document.getElementById('app_id').value = item.ID;
        document.getElementById('app_action').value = 'edit';
        document.getElementById('app_name').value = item.app_name;
        document.getElementById('app_module').value = item.module_column;
        document.getElementById('app_perm').value = item.perm_key;
        document.getElementById('app_url').value = item.app_url;
        document.getElementById('appModalTitle').innerText = 'Edit App Module';
        document.getElementById('appModal').classList.remove('hidden');
    }
    function closeAppModal() {
        document.getElementById('appModal').classList.add('hidden');
    }

    function deleteApp(id) {
        deleteTargetId = id;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        deleteTargetId = null;
    }

    function confirmDelete() {
        if (deleteTargetId) {
            const form = document.getElementById('deleteForm');
            form.action = 'actions/manage_apps.php';
            document.getElementById('delete_id').value = deleteTargetId;
            form.submit();
        }
    }

    function filterApps() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const appName = row.cells[0].textContent.toLowerCase();
            const moduleGroup = row.cells[1].textContent.toLowerCase();
            if (appName.includes(filter) || moduleGroup.includes(filter)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }
</script>