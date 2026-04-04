<?php
// Fetch Departments for the dropdown
$departments = [];
if (isset($conn)) {
    $deptQuery = "SELECT DISTINCT Department FROM prtl_lrn_master_list WHERE isActive = 1 ORDER BY Department ASC";
    $deptStmt = $conn->query($deptQuery);
    if ($deptStmt) {
        while ($row = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
            $departments[] = $row['Department'];
        }
    }
}

$roles = [
    'Admin',
    'Manager',
    'Employee',
    'System Admin',
    'DB Admin',
    'Ticket Admin',
    'Task Admin',
    'QA Manager',
    'QA',
    'HR Admin',
    'HR ER'
];
?>

<div class="flex-1 flex flex-col min-h-0">
    <!-- Top Header & View Switcher -->
    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between shrink-0 px-1 gap-4">
        <div id="createHeader">
            <h1 class="text-2xl lg:text-3xl font-extrabold text-gray-900 tracking-tight">User Onboarding</h1>
            <p class="text-sm text-gray-400 font-medium mt-1">Register and provision new portal access credentials</p>
        </div>
        <div id="viewHeader" class="hidden">
            <h1 class="text-2xl lg:text-3xl font-extrabold text-gray-900 tracking-tight">Portal Directory</h1>
            <p class="text-sm text-gray-400 font-medium mt-1">Manage and audit existing user accounts</p>
        </div>

        <div class="flex items-center gap-1 bg-white p-1.5 rounded-[22px] shadow-sm border border-gray-100/80 w-fit">
            <button onclick="changeView('create')" id="tab-create"
                class="px-4 lg:px-6 py-2.5 rounded-[18px] text-sm font-bold transition-all flex items-center gap-2 bg-pink-500 text-white shadow-lg shadow-pink-100">
                <i class="fa-solid fa-plus-circle"></i>
                <span>Register</span>
            </button>
            <button onclick="changeView('view')" id="tab-view"
                class="px-4 lg:px-6 py-2.5 rounded-[18px] text-sm font-bold transition-all flex items-center gap-2 text-gray-400 hover:text-gray-600 hover:bg-gray-50">
                <i class="fa-solid fa-table-list"></i>
                <span>Directory</span>
                <span id="userCountBadge"
                    class="ml-1 px-1.5 py-0.5 bg-gray-100 text-black text-[10px] rounded-md hidden">0</span>
            </button>
        </div>
    </div>

    <!-- Registration Section -->
    <div id="createSection" class="flex-1 flex flex-col min-h-0">
        <div class="flex flex-col lg:flex-row gap-6 p-1">
            <!-- Left Column: Form Section -->
            <div class="flex-1 flex flex-col min-w-0 order-2 lg:order-1">
                <div
                    class="bg-white rounded-[32px] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100/50 flex flex-col relative pb-4">
                    <div
                        class="absolute -top-24 -right-24 w-64 h-64 bg-pink-50 rounded-full blur-3xl opacity-50 pointer-events-none">
                    </div>
                    <div
                        class="absolute -bottom-24 -left-24 w-64 h-64 bg-purple-50 rounded-full blur-3xl opacity-50 pointer-events-none">
                    </div>

                    <div class="px-8 pt-8 pb-6 border-b border-gray-50 relative z-10 flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">
                                Registration Details</h2>
                            <p class="text-xs text-gray-400 mt-1 font-medium">Please fulfill the security requirements
                                below.
                            </p>
                        </div>
                        <div
                            class="w-10 h-10 bg-pink-50 rounded-xl flex items-center justify-center text-pink-500 shadow-sm border border-pink-100">
                            <i class="fa-solid fa-fingerprint text-lg"></i>
                        </div>
                    </div>

                    <div class="p-8 relative z-10">
                        <form id="addEmployeeForm" onsubmit="submitNewEmployee(event)"
                            class="space-y-8 max-w-3xl mx-auto">
                            <!-- Auth Section -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="space-y-2 group">
                                    <label
                                        class="text-[0.8rem] font-bold text-gray-500 ml-1 uppercase tracking-tight">Username</label>
                                    <div class="relative">
                                        <i
                                            class="fa-solid fa-at absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-pink-500 transition-all"></i>
                                        <input type="text" name="username" required
                                            class="w-full pl-11 pr-4 py-3.5 bg-gray-50/50 border border-gray-200 rounded-2xl outline-none focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 focus:bg-white transition-all text-sm font-medium"
                                            placeholder="0012XX">
                                    </div>
                                </div>
                                <div class="space-y-2 group">
                                    <label
                                        class="text-[0.8rem] font-bold text-gray-500 ml-1 uppercase tracking-tight">Access
                                        Password</label>
                                    <div class="relative">
                                        <i
                                            class="fa-solid fa-key absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-pink-500 transition-all"></i>
                                        <input type="password" name="password" required
                                            class="w-full pl-11 pr-4 py-3.5 bg-gray-50/50 border border-gray-200 rounded-2xl outline-none focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 focus:bg-white transition-all text-sm font-medium"
                                            placeholder="••••••••">
                                    </div>
                                </div>
                            </div>

                            <!-- Org Section -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="space-y-2 group">
                                    <label
                                        class="text-[0.8rem] font-bold text-gray-500 ml-1 uppercase tracking-tight">Employee
                                        Code</label>
                                    <div class="relative">
                                        <i
                                            class="fa-solid fa-fingerprint absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-pink-500 transition-all"></i>
                                        <input type="text" name="empcode" required
                                            class="w-full pl-11 pr-4 py-3.5 bg-gray-50/50 border border-gray-200 rounded-2xl outline-none focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 focus:bg-white transition-all text-sm font-medium"
                                            placeholder="2100XX">
                                    </div>
                                </div>
                                <div class="space-y-2 group">
                                    <label
                                        class="text-[0.8rem] font-bold text-gray-500 ml-1 uppercase tracking-tight">Permission
                                        Role</label>
                                    <div class="relative">
                                        <i
                                            class="fa-solid fa-user-shield absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-pink-500 transition-all"></i>
                                        <select name="role" required
                                            class="w-full pl-11 pr-10 py-3.5 bg-gray-50/50 border border-gray-200 rounded-2xl outline-none focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 focus:bg-white transition-all text-sm font-medium appearance-none cursor-pointer">
                                            <option value="" disabled selected>Select Role</option>
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?php echo $role; ?>"><?php echo $role; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i
                                            class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2 group">
                                <label
                                    class="text-[0.8rem] font-bold text-gray-500 ml-1 uppercase tracking-tight">Department
                                    <span class="text-gray-400 font-normal lowercase italic">(optional)</span></label>
                                <div class="relative">
                                    <i
                                        class="fa-solid fa-building-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-pink-500 transition-all"></i>
                                    <select name="department"
                                        class="w-full pl-11 pr-10 py-3.5 bg-gray-50/50 border border-gray-200 rounded-2xl outline-none focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 focus:bg-white transition-all text-sm font-medium appearance-none cursor-pointer">
                                        <option value="" selected>Generic / No Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i
                                        class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                </div>
                            </div>
                            <input type="hidden" name="status" value="active">
                        </form>
                    </div>

                    <div
                        class="px-8 py-6 bg-gray-50/50 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between relative z-10 gap-4">
                        <button type="button" onclick="window.history.back()"
                            class="px-6 py-3 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">Cancel</button>
                        <button type="submit" form="addEmployeeForm" id="submitBtn"
                            class="w-full sm:w-auto px-10 py-3.5 bg-pink-500 border-b-4 border-pink-700 text-white text-sm font-bold rounded-2xl hover:bg-pink-600 hover:border-pink-800 active:border-b-0 active:translate-y-1 transition-all flex items-center justify-center gap-3">
                            <span>Register Employee</span>
                            <i class="fa-solid fa-paper-plane text-xs opacity-80"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Info -->
            <div class="w-full lg:w-[350px] flex flex-col gap-6 shrink-0 order-1 lg:order-2">
                <div
                    class="bg-white rounded-[32px] border border-gray-100 p-8 flex flex-col shadow-sm relative overflow-hidden">
                    <div class="absolute -right-8 -bottom-8 w-40 h-40 bg-pink-50 rounded-full blur-2xl"></div>
                    <h4 class="text-gray-900 font-bold mb-6 flex items-center gap-2 relative z-10">
                        <i class="fa-solid fa-circle-info text-pink-500"></i>
                        Onboarding Guide
                    </h4>
                    <div class="space-y-6 relative z-10">
                        <div>
                            <span
                                class="text-[10px] font-extrabold text-pink-500 uppercase tracking-widest block mb-1">Step
                                1</span>
                            <p class="text-xs text-gray-500 leading-relaxed font-medium">Verify the employee's Biometric
                                ID matches their portal username.</p>
                        </div>
                        <div>
                            <span
                                class="text-[10px] font-extrabold text-pink-500 uppercase tracking-widest block mb-1">Step
                                2</span>
                            <p class="text-xs text-gray-500 leading-relaxed font-medium">Assign roles carefully;
                                permissions dictate module access.</p>
                        </div>
                    </div>
                    <div class="mt-8 px-4 py-3 bg-pink-50 rounded-2xl border border-pink-100 relative z-10">
                        <span
                            class="text-[0.65rem] font-bold text-pink-600 uppercase tracking-tighter block text-center italic">New
                            users can log in immediately.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Section -->
    <div id="viewSection" class="hidden flex-1 flex flex-col min-h-0">
        <div
            class="bg-white rounded-[32px] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100/50 flex flex-col h-full overflow-hidden">
            <div class="px-8 pt-8 pb-6 border-b border-gray-50 flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-gray-900 rounded-xl flex items-center justify-center text-white">
                        <i class="fa-solid fa-users text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Portal Directory</h2>
                        <p class="text-xs text-gray-400 mt-1 uppercase tracking-tighter font-bold">Manage User Accounts
                        </p>
                    </div>
                </div>
                <div class="relative">
                    <i
                        class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="text" id="userSearch" oninput="filterUsers()" placeholder="Search registry..."
                        class="pl-11 pr-4 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm outline-none focus:bg-white focus:border-pink-200 transition-all w-72 shadow-inner font-medium">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar relative">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 bg-white z-20">
                        <tr class="bg-gray-50/50">
                            <th
                                class="px-8 py-4 text-[0.7rem] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50">
                                Identity</th>
                            <th
                                class="px-8 py-4 text-[0.7rem] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50">
                                Access Level</th>
                            <th
                                class="px-8 py-4 text-[0.7rem] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50">
                                Organization</th>
                            <th
                                class="px-8 py-4 text-[0.7rem] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50">
                                Status</th>
                            <th
                                class="px-8 py-4 text-[0.7rem] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50 text-right">
                                Utility</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <!-- Rows injected via JS -->
                    </tbody>
                </table>
                <div id="loadMoreContainer" class="p-8 flex justify-center hidden">
                    <button onclick="loadNextPage()"
                        class="px-8 py-2.5 bg-gray-50 text-gray-500 text-xs font-bold rounded-xl border border-gray-200 hover:bg-white hover:text-pink-500 transition-all">
                        LOAD MORE USERS
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit User Modal (Modernized) -->
        <div id="editModal"
            class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-gray-950/40 backdrop-blur-md">
            <div class="bg-white p-10 rounded-[40px] shadow-3xl max-w-xl w-full mx-4 border border-white/20">
                <div class="flex justify-between items-center mb-10">
                    <div class="flex items-center gap-4">
                        <div
                            class="w-12 h-12 bg-pink-50 rounded-2xl flex items-center justify-center text-pink-500 shadow-sm border border-pink-100">
                            <i class="fa-solid fa-user-pen text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900">Modify Account</h3>
                            <p class="text-sm text-gray-400 font-medium">Update credentials and access levels</p>
                        </div>
                    </div>
                    <button onclick="closeEditModal()"
                        class="w-10 h-10 rounded-full bg-gray-50 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form id="editUserForm" onsubmit="submitEditUser(event)" class="space-y-6">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1 group">
                            <label class="text-[0.65rem] font-bold text-gray-500 ml-1 uppercase">Username</label>
                            <input type="text" name="username" id="edit_username" required
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 shadow-inner font-medium">
                        </div>
                        <div class="space-y-1 group">
                            <label class="text-[0.65rem] font-bold text-gray-500 ml-1 uppercase">New Password <span
                                    class="text-[10px] lowercase italic">(blank to ignore)</span></label>
                            <input type="password" name="password" placeholder="••••••••"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 shadow-inner">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1 group">
                            <label class="text-[0.65rem] font-bold text-gray-500 ml-1 uppercase">Employee
                                Code</label>
                            <input type="text" name="empcode" id="edit_empcode" required
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:ring-4 focus:ring-pink-500/5 focus:border-pink-500 shadow-inner font-medium">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[0.65rem] font-bold text-gray-500 ml-1 uppercase">Role</label>
                            <select name="role" id="edit_role" required
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:border-pink-500 shadow-inner font-bold text-gray-700">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role; ?>"><?php echo $role; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[0.65rem] font-bold text-gray-500 ml-1 uppercase">Department</label>
                        <select name="department" id="edit_department"
                            class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none focus:border-pink-500 shadow-inner">
                            <option value="">Generic / No Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[0.65rem] font-bold text-gray-500 ml-1 uppercase">Account
                            Integrity</label>
                        <div class="flex gap-4">
                            <label
                                class="flex-1 flex items-center justify-center gap-3 p-4 bg-gray-50 rounded-2xl border-2 border-transparent cursor-pointer has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-200 transition-all has-[:checked]:shadow-lg has-[:checked]:shadow-emerald-50">
                                <input type="radio" name="status" value="active" id="status_active" class="hidden">
                                <i class="fa-solid fa-user-check text-emerald-500"></i>
                                <span class="text-sm font-bold text-emerald-700">Active</span>
                            </label>
                            <label
                                class="flex-1 flex items-center justify-center gap-3 p-4 bg-gray-50 rounded-2xl border-2 border-transparent cursor-pointer has-[:checked]:bg-red-50 has-[:checked]:border-red-200 transition-all has-[:checked]:shadow-lg has-[:checked]:shadow-red-50">
                                <input type="radio" name="status" value="inactive" id="status_inactive" class="hidden">
                                <i class="fa-solid fa-user-slash text-red-500"></i>
                                <span class="text-sm font-bold text-red-700">Suspended</span>
                            </label>
                        </div>
                    </div>
                    <div class="pt-6 flex justify-end gap-4">
                        <button type="button" onclick="closeEditModal()"
                            class="px-8 py-3 text-sm font-bold text-gray-400 hover:text-gray-600 transition-colors uppercase tracking-widest">Withdraw</button>
                        <button type="submit" id="editSubmitBtn"
                            class="px-10 py-3.5 bg-gray-900 border-b-4 border-gray-950 text-white text-sm font-bold rounded-2xl hover:bg-black active:border-b-0 active:translate-y-1 transition-all shadow-xl shadow-gray-200">
                            Commit Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function changeView(view) {
            const createTab = document.getElementById('tab-create');
            const viewTab = document.getElementById('tab-view');
            const createSection = document.getElementById('createSection');
            const viewSection = document.getElementById('viewSection');
            const createHeader = document.getElementById('createHeader');
            const viewHeader = document.getElementById('viewHeader');

            if (view === 'create') {
                createTab.className = "px-6 py-2.5 rounded-[18px] text-sm font-bold transition-all flex items-center gap-2 bg-pink-500 text-white shadow-lg shadow-pink-100";
                viewTab.className = "px-6 py-2.5 rounded-[18px] text-sm font-bold transition-all flex items-center gap-2 text-gray-400 hover:text-gray-600 hover:bg-gray-50";
                createSection.classList.remove('hidden');
                viewSection.classList.add('hidden');
                createHeader.classList.remove('hidden');
                viewHeader.classList.add('hidden');
            } else {
                viewTab.className = "px-6 py-2.5 rounded-[18px] text-sm font-bold transition-all flex items-center gap-2 bg-gray-900 text-white shadow-lg shadow-gray-100";
                createTab.className = "px-6 py-2.5 rounded-[18px] text-sm font-bold transition-all flex items-center gap-2 text-gray-400 hover:text-gray-600 hover:bg-gray-50";
                createSection.classList.add('hidden');
                viewSection.classList.remove('hidden');
                createHeader.classList.add('hidden');
                viewHeader.classList.remove('hidden');
                loadRegistry();
            }
        }

        let allUsers = [];
        let filteredUsers = [];
        let currentPage = 1;
        const pageSize = 50;
        let searchTimeout = null;

        function loadRegistry() {
            const body = document.getElementById('userTableBody');
            body.innerHTML = `<tr><td colspan="5" class="px-8 py-32 text-center text-gray-300 font-bold"><i class="fa-solid fa-spinner fa-spin text-4xl mb-4 block opacity-20"></i>ACCESSING REGISTRY...</td></tr>`;

            fetch('actions/get_portal_users.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        allUsers = data.data;
                        filteredUsers = [...allUsers];
                        document.getElementById('userCountBadge').textContent = allUsers.length;
                        document.getElementById('userCountBadge').classList.remove('hidden');
                        currentPage = 1;
                        renderRegistry();
                    }
                });
        }

        function renderRegistry(append = false) {
            const body = document.getElementById('userTableBody');
            const start = (currentPage - 1) * pageSize;
            const end = currentPage * pageSize;
            const usersToShow = filteredUsers.slice(append ? start : 0, end);

            if (usersToShow.length === 0 && !append) {
                body.innerHTML = `<tr><td colspan="5" class="px-8 py-32 text-center text-gray-400 font-bold opacity-50">REGISTRY IS EMPTY</td></tr>`;
                document.getElementById('loadMoreContainer').classList.add('hidden');
                return;
            }

            const html = usersToShow.map(u => `
            <tr class="hover:bg-gray-50/80 transition-[background-color] duration-200 group">
                <td class="px-8 py-5 border-b border-gray-50">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-2xl bg-pink-50 text-pink-500 flex items-center justify-center font-bold text-sm border border-pink-100 shadow-sm transition-all group-hover:bg-pink-500 group-hover:text-white">
                            ${(u.FirstName && u.LastName) ? u.FirstName.charAt(0) + u.LastName.charAt(0) : u.username.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="block text-sm font-bold text-gray-800">${u.FirstName ? u.FirstName + ' ' + u.LastName : u.username}</span>
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 bg-pink-100/50 text-pink-600 rounded border border-pink-100">${u.role}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">${u.username}</span>
                                <span class="text-[10px] text-gray-300">•</span>
                                <span class="text-[10px] text-gray-400 font-medium italic">${u.PositionTitle || 'Staff'}</span>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="px-8 py-5 border-b border-gray-50">
                    <span class="px-3 py-1 bg-gray-100 text-gray-600 text-[10px] font-bold rounded-lg border border-gray-200">
                        ${u.role}
                    </span>
                </td>
                <td class="px-8 py-5 border-b border-gray-50">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-gray-600">${u.department || 'Generic'}</span>
                        <span class="text-[9px] text-gray-400 font-medium uppercase tracking-tight">Assigned Origin</span>
                    </div>
                </td>
                <td class="px-8 py-5 border-b border-gray-50">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full ${u.status === 'active' ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : 'bg-red-500'}"></div>
                        <span class="text-[10px] font-extrabold uppercase text-gray-700 tracking-tighter">${u.status}</span>
                    </div>
                </td>
                <td class="px-8 py-5 border-b border-gray-50 text-right">
                    <div class="flex items-center justify-end gap-2 transition-all duration-300">
                        <button onclick="openEditModal(${u.user_id})" class="w-8 h-8 rounded-xl bg-white border border-blue-100 text-blue-500 hover:bg-blue-500 hover:text-white transition-all flex items-center justify-center shadow-sm">
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                        </button>
                        <button onclick="dropUser(${u.user_id}, '${u.username}')" class="w-8 h-8 rounded-xl bg-white border border-red-100 text-red-500 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center shadow-sm">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

            if (append) {
                body.insertAdjacentHTML('beforeend', html);
            } else {
                body.innerHTML = html;
            }

            // Show/Hide Load More
            if (filteredUsers.length > end) {
                document.getElementById('loadMoreContainer').classList.remove('hidden');
            } else {
                document.getElementById('loadMoreContainer').classList.add('hidden');
            }
        }

        function loadNextPage() {
            currentPage++;
            renderRegistry(true);
        }

        function filterUsers() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = document.getElementById('userSearch').value.toLowerCase();
                filteredUsers = allUsers.filter(u =>
                    u.username.toLowerCase().includes(query) ||
                    u.empcode.toLowerCase().includes(query) ||
                    (u.FirstName && u.FirstName.toLowerCase().includes(query)) ||
                    (u.LastName && u.LastName.toLowerCase().includes(query)) ||
                    (u.department && u.department.toLowerCase().includes(query))
                );
                currentPage = 1;
                renderRegistry();
            }, 300); // 300ms debounce
        }

        function dropUser(id, name) {
            if (!confirm(`NOTICE: Permanently delete portal credentials for "${name}"? This cannot be undone.`)) return;
            fetch('actions/delete_portal_user.php', {
                method: 'POST',
                body: JSON.stringify({ user_id: id })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    loadRegistry();
                } else alert(data.message);
            });
        }

        function openEditModal(id) {
            const u = allUsers.find(user => user.user_id == id);
            if (!u) return;
            document.getElementById('edit_user_id').value = u.user_id;
            document.getElementById('edit_username').value = u.username;
            document.getElementById('edit_empcode').value = u.empcode;
            document.getElementById('edit_role').value = u.role;
            document.getElementById('edit_department').value = u.department || '';
            document.getElementById('status_' + u.status).checked = true;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function submitEditUser(e) {
            e.preventDefault();
            const btn = document.getElementById('editSubmitBtn');
            const form = e.target;
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> SAVING...`;

            fetch('actions/update_portal_user.php', {
                method: 'POST',
                body: new FormData(form)
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    closeEditModal();
                    loadRegistry();
                } else alert(data.message);
            }).finally(() => {
                btn.disabled = false;
                btn.innerHTML = `Commit Changes`;
            });
        }

        function submitNewEmployee(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const form = e.target;
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> REGISTERING...`;

            fetch('actions/add_employee.php', {
                method: 'POST',
                body: new FormData(form)
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    alert('Account registry successful!');
                    form.reset();
                    changeView('view');
                } else alert(data.message);
            }).finally(() => {
                btn.disabled = false;
                btn.innerHTML = `<span>Register Employee</span><i class="fa-solid fa-paper-plane text-xs opacity-80"></i>`;
            });
        }
    </script>