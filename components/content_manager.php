<div class="bg-white rounded-[20px] shadow-sm p-6 flex flex-col h-[calc(100vh-140px)]">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-800">App Management</h2>
            <p class="text-sm text-gray-400 mt-1">Manage the applications visible on the main portal</p>
        </div>
        <button onclick="document.getElementById('addAppModal').classList.remove('hidden')"
            class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 shadow-lg shadow-pink-200">
            <i class="fa-solid fa-plus"></i> Add App
        </button>
    </div>

    <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 pb-4">
            <?php
            $appsQuery = "SELECT * FROM prtl_portal_apps ORDER BY sort_order ASC, name ASC";
            $appsStmt = $conn->query($appsQuery);
            if ($appsStmt) {
                while ($app = $appsStmt->fetch(PDO::FETCH_ASSOC)) {
                    $isActive = $app['is_active'];
                    $statusColor = $isActive ? 'bg-green-50 text-green-600 border-green-100' : 'bg-gray-50 text-gray-500 border-gray-100';
                    $statusText = $isActive ? 'Active' : 'Hidden';
                    $iconClass = $app['icon'] ?: 'fa-solid fa-layer-group';

                    echo "
                    <div class='border border-gray-100 rounded-xl p-4 hover:shadow-md transition-all duration-200 group relative bg-white flex flex-col h-full'>
                        <div class='flex items-start justify-between mb-3'>
                            <div class='w-10 h-10 rounded-lg bg-pink-50 text-pink-500 flex items-center justify-center'>
                                <i class='{$iconClass} text-lg'></i>
                            </div>
                            <div class='flex gap-2 transition-all duration-300'>
                                <form action='actions/toggle_app_status.php' method='POST' class='inline'>
                                    <input type='hidden' name='app_id' value='{$app['id']}'>
                                    <input type='hidden' name='current_status' value='{$app['is_active']}'>
                                    <button type='submit' class='w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-100 transition-all hover:bg-blue-500 hover:text-white hover:border-blue-500 " . ($isActive ? "text-blue-500 shadow-sm" : "text-gray-400") . "' title='" . ($isActive ? "Hide App" : "Show App") . "'>
                                        <i class='fa-solid " . ($isActive ? "fa-eye" : "fa-eye-slash") . " text-xs'></i>
                                    </button>
                                </form>
                                <button onclick='openEditModal(" . json_encode($app) . ")' class='w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-100 text-gray-400 hover:text-white hover:bg-blue-500 hover:border-blue-500 transition-all shadow-sm' title='Edit'>
                                    <i class='fa-solid fa-pen text-xs'></i>
                                </button>
                                <button onclick='confirmDelete({$app['id']}, \"{$app['name']}\")' class='w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-100 text-gray-400 hover:text-white hover:bg-red-500 hover:border-red-500 transition-all shadow-sm' title='Delete'>
                                    <i class='fa-regular fa-trash-can text-xs'></i>
                                </button>
                            </div>
                        </div>
                        <h3 class='font-bold text-gray-800 text-sm mb-1 line-clamp-2 min-h-[2.5rem] leading-tight'>{$app['name']}</h3>
                        <div class='mt-auto flex items-center justify-between pt-2'>
                            <span class='text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full border {$statusColor} font-semibold'>{$statusText}</span>
                            " . ($app['sort_order'] != 99 ? "<span class='text-[10px] bg-gray-100 px-2 py-0.5 rounded-full font-bold text-gray-500'>Order: {$app['sort_order']}</span>" : "") . "
                        </div>
                    </div>
                    ";
                }
            } else {
                echo "<div class='col-span-full flex flex-col items-center justify-center py-20 text-gray-400'>
                        <i class='fa-regular fa-folder-open text-4xl mb-3 opacity-50'></i>
                        <p>No apps found.</p>
                      </div>";
            }
            ?>
        </div>
    </div>
</div>

<!-- Add App Modal -->
<div id="addAppModal"
    class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl transform transition-all scale-100">
        <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
            <h3 class="text-lg font-bold text-gray-800">Add New App</h3>
            <button onclick="document.getElementById('addAppModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 transition-colors bg-transparent hover:bg-gray-100 w-8 h-8 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="actions/add_app.php" method="POST">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">App Name</label>
                    <input type="text" name="name" required
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 placeholder-gray-400"
                        placeholder="e.g. Workday">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">URL</label>
                    <input type="text" name="url"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 placeholder-gray-400"
                        placeholder="https://..." value="#">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Icon</label>
                    <div class="flex gap-3">
                        <div id="selectedIconPreview"
                            class="w-[46px] h-[46px] rounded-xl bg-pink-50 text-pink-500 flex items-center justify-center border border-pink-100 flex-shrink-0">
                            <i id="previewIconClass" class="fa-solid fa-layer-group text-lg"></i>
                        </div>
                        <input type="hidden" name="icon" id="iconInput" value="fa-solid fa-layer-group">
                        <button type="button" onclick="openIconPicker('add')"
                            class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 hover:border-pink-500 hover:text-pink-500 text-gray-500 transition-all text-sm text-left flex justify-between items-center group">
                            <span>Select Icon...</span>
                            <i class="fa-solid fa-chevron-right text-xs opacity-50 group-hover:opacity-100"></i>
                        </button>
                    </div>
                </div>
                <div class="flex flex-col gap-3 mt-2 p-3 bg-gray-50 rounded-xl border border-gray-100">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="is_active" id="isActive" checked
                            class="w-5 h-5 text-pink-500 rounded border-gray-300 focus:ring-pink-500 cursor-pointer">
                        <label for="isActive"
                            class="text-sm font-medium text-gray-700 cursor-pointer select-none">Visible on Main
                            Portal</label>
                    </div>
                    <div class="pt-2 border-t border-gray-200 mt-1">
                        <label
                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Priority
                            / Sort Order (Lower = First)</label>
                        <input type="number" name="sort_order" value="99"
                            class="w-full px-3 py-1.5 rounded-lg border border-gray-200 focus:border-pink-500 outline-none transition-all text-sm text-gray-700">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-8 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('addAppModal').classList.add('hidden')"
                    class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-colors text-sm">Cancel</button>
                <button type="submit"
                    class="flex-1 px-4 py-2.5 rounded-xl bg-pink-500 text-white font-semibold hover:bg-pink-600 transition-colors text-sm shadow-lg shadow-pink-200 hover:shadow-xl hover:shadow-pink-300 transform hover:-translate-y-0.5">Add
                    App</button>
            </div>
        </form>
    </div>
</div>

<!-- Icon Picker Modal -->
<div id="iconPickerModal"
    class="hidden fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-2xl h-[80vh] flex flex-col shadow-2xl">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">Select an Icon</h3>
            <button onclick="document.getElementById('iconPickerModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="p-4 border-b border-gray-100 bg-gray-50/50">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-3.5 text-gray-400"></i>
                <input type="text" id="iconSearch" onkeyup="filterIcons()"
                    placeholder="Search icons (e.g. user, chart, file)..."
                    class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm">
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
            <div id="iconGrid" class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-3">
                <!-- Icons will be populated by JS -->
            </div>
        </div>
    </div>
</div>
</div>

<!-- Edit App Modal -->
<div id="editAppModal"
    class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl transform transition-all scale-100">
        <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
            <h3 class="text-lg font-bold text-gray-800">Edit App</h3>
            <button onclick="document.getElementById('editAppModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 transition-colors bg-transparent hover:bg-gray-100 w-8 h-8 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="actions/edit_app.php" method="POST">
            <input type="hidden" name="id" id="editAppId">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">App Name</label>
                    <input type="text" name="name" id="editAppName" required
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 placeholder-gray-400">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">URL</label>
                    <input type="text" name="url" id="editAppUrl"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 placeholder-gray-400">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Icon</label>
                    <div class="flex gap-3">
                        <div id="editIconPreview"
                            class="w-[46px] h-[46px] rounded-xl bg-pink-50 text-pink-500 flex items-center justify-center border border-pink-100 flex-shrink-0">
                            <i id="editPreviewIconClass" class="fa-solid fa-layer-group text-lg"></i>
                        </div>
                        <input type="hidden" name="icon" id="editIconInput" value="fa-solid fa-layer-group">
                        <button type="button" onclick="openIconPicker('edit')"
                            class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 hover:border-pink-500 hover:text-pink-500 text-gray-500 transition-all text-sm text-left flex justify-between items-center group">
                            <span>Change Icon...</span>
                            <i class="fa-solid fa-chevron-right text-xs opacity-50 group-hover:opacity-100"></i>
                        </button>
                    </div>
                </div>
                <div class="flex flex-col gap-3 mt-2 p-3 bg-gray-50 rounded-xl border border-gray-100">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="is_active" id="editIsActive" value="1"
                            class="w-5 h-5 text-pink-500 rounded border-gray-300 focus:ring-pink-500 cursor-pointer">
                        <label for="editIsActive"
                            class="text-sm font-medium text-gray-700 cursor-pointer select-none">Visible
                            on Main Portal</label>
                    </div>
                    <div class="pt-2 border-t border-gray-200 mt-1">
                        <label
                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Priority
                            / Sort Order (Lower = First)</label>
                        <input type="number" name="sort_order" id="editAppSortOrder" value="99"
                            class="w-full px-3 py-1.5 rounded-lg border border-gray-200 focus:border-pink-500 outline-none transition-all text-sm text-gray-700">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-8 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('editAppModal').classList.add('hidden')"
                    class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-colors text-sm">Cancel</button>
                <button type="submit"
                    class="flex-1 px-4 py-2.5 rounded-xl bg-pink-500 text-white font-semibold hover:bg-pink-600 transition-colors text-sm shadow-lg shadow-pink-200 hover:shadow-xl hover:shadow-pink-300 transform hover:-translate-y-0.5">Save
                    Changes</button>
            </div>
        </form>
    </div>
</div>



<script>
    const commonIcons = [
        'fa-solid fa-house', 'fa-solid fa-user', 'fa-solid fa-users', 'fa-solid fa-briefcase', 'fa-solid fa-folder', 'fa-solid fa-folder-open',
        'fa-solid fa-file', 'fa-solid fa-file-pdf', 'fa-solid fa-file-word', 'fa-solid fa-file-excel', 'fa-solid fa-file-invoice',
        'fa-solid fa-chart-simple', 'fa-solid fa-chart-pie', 'fa-solid fa-chart-line', 'fa-solid fa-chart-area', 'fa-solid fa-chart-column',
        'fa-solid fa-calendar', 'fa-solid fa-calendar-days', 'fa-solid fa-calendar-check', 'fa-solid fa-clock', 'fa-solid fa-bell',
        'fa-solid fa-envelope', 'fa-solid fa-envelope-open', 'fa-solid fa-inbox', 'fa-solid fa-paper-plane', 'fa-solid fa-address-book',
        'fa-solid fa-phone', 'fa-solid fa-video', 'fa-solid fa-camera', 'fa-solid fa-image', 'fa-solid fa-globe', 'fa-solid fa-location-dot',
        'fa-solid fa-map', 'fa-solid fa-building', 'fa-solid fa-shop', 'fa-solid fa-cart-shopping', 'fa-solid fa-credit-card',
        'fa-solid fa-wallet', 'fa-solid fa-money-bill', 'fa-solid fa-piggy-bank', 'fa-solid fa-landmark', 'fa-solid fa-graduation-cap',
        'fa-solid fa-book', 'fa-solid fa-bookmark', 'fa-solid fa-clipboard', 'fa-solid fa-clipboard-check', 'fa-solid fa-list',
        'fa-solid fa-list-check', 'fa-solid fa-check', 'fa-solid fa-pen', 'fa-solid fa-pen-to-square', 'fa-solid fa-gear', 'fa-solid fa-wrench',
        'fa-solid fa-screwdriver-wrench', 'fa-solid fa-lock', 'fa-solid fa-key', 'fa-solid fa-shield', 'fa-solid fa-shield-halved',
        'fa-solid fa-circle-info', 'fa-solid fa-circle-question', 'fa-solid fa-circle-exclamation', 'fa-solid fa-circle-check', 'fa-solid fa-star',
        'fa-solid fa-heart', 'fa-solid fa-thumbs-up', 'fa-solid fa-comment', 'fa-solid fa-comments', 'fa-solid fa-share-nodes',
        'fa-solid fa-download', 'fa-solid fa-upload', 'fa-solid fa-cloud', 'fa-solid fa-database', 'fa-solid fa-server', 'fa-solid fa-laptop',
        'fa-solid fa-desktop', 'fa-solid fa-mobile-screen', 'fa-solid fa-tablet-screen-button', 'fa-solid fa-print', 'fa-solid fa-bug',
        'fa-solid fa-code', 'fa-solid fa-terminal', 'fa-solid fa-layer-group', 'fa-solid fa-cube', 'fa-solid fa-cubes', 'fa-solid fa-puzzle-piece',
        'fa-solid fa-bullhorn', 'fa-solid fa-microphone', 'fa-solid fa-headphones', 'fa-solid fa-music', 'fa-solid fa-video',
        'fa-solid fa-film', 'fa-solid fa-ticket', 'fa-solid fa-palette', 'fa-solid fa-brush', 'fa-solid fa-ruler', 'fa-solid fa-calculator',
        'fa-solid fa-flask', 'fa-solid fa-vial', 'fa-solid fa-dna', 'fa-solid fa-stethoscope', 'fa-solid fa-user-doctor', 'fa-solid fa-hospital',
        'fa-solid fa-pills', 'fa-solid fa-plus', 'fa-solid fa-minus', 'fa-solid fa-xmark', 'fa-solid fa-bars', 'fa-solid fa-ellipsis',
        'fa-solid fa-arrow-right', 'fa-solid fa-arrow-left', 'fa-solid fa-arrow-up', 'fa-solid fa-arrow-down', 'fa-solid fa-rotate',
        'fa-brands fa-facebook', 'fa-brands fa-twitter', 'fa-brands fa-instagram', 'fa-brands fa-linkedin', 'fa-brands fa-youtube',
        'fa-brands fa-whatsapp', 'fa-brands fa-slack', 'fa-brands fa-microsoft', 'fa-brands fa-google', 'fa-brands fa-apple',
        'fa-solid fa-bus', 'fa-solid fa-car', 'fa-solid fa-truck', 'fa-solid fa-plane', 'fa-solid fa-ship', 'fa-solid fa-ticket-simple',
        'fa-solid fa-id-card', 'fa-solid fa-passport', 'fa-solid fa-fingerprint', 'fa-solid fa-qrcode', 'fa-solid fa-barcode', 'fa-solid fa-box',
        'fa-solid fa-boxes-stacked', 'fa-solid fa-dolly', 'fa-solid fa-truck-ramp-box', 'fa-solid fa-warehouse', 'fa-solid fa-industry',
        'fa-solid fa-helmet-safety', 'fa-solid fa-hard-hat', 'fa-solid fa-fire-extinguisher', 'fa-solid fa-recycle', 'fa-solid fa-leaf',
        'fa-solid fa-seedling', 'fa-solid fa-tree', 'fa-solid fa-utensils', 'fa-solid fa-burger', 'fa-solid fa-pizza-slice', 'fa-solid fa-mug-hot',
        'fa-solid fa-sun'
    ];

    let iconPickerMode = 'add'; // 'add' or 'edit'

    function openIconPicker(mode) {
        iconPickerMode = mode;
        document.getElementById('iconPickerModal').classList.remove('hidden');
    }

    function initIconPicker() {
        const grid = document.getElementById('iconGrid');
        grid.innerHTML = '';

        commonIcons.forEach(iconClass => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-12 h-12 flex items-center justify-center rounded-xl hover:bg-pink-50 hover:text-pink-500 transition-colors border border-transparent hover:border-pink-200 text-gray-500 text-lg group';
            btn.onclick = () => selectIcon(iconClass);
            btn.innerHTML = `<i class="${iconClass}"></i>`;
            btn.title = iconClass;
            grid.appendChild(btn);
        });
    }

    function selectIcon(iconClass) {
        if (iconPickerMode === 'edit') {
            document.getElementById('editIconInput').value = iconClass;
            document.getElementById('editPreviewIconClass').className = iconClass + ' text-lg';
        } else {
            document.getElementById('iconInput').value = iconClass;
            document.getElementById('previewIconClass').className = iconClass + ' text-lg';
        }
        document.getElementById('iconPickerModal').classList.add('hidden');
    }

    function filterIcons() {
        const query = document.getElementById('iconSearch').value.toLowerCase();
        const buttons = document.getElementById('iconGrid').getElementsByTagName('button');

        for (let btn of buttons) {
            const iconClass = btn.title.toLowerCase();
            if (iconClass.includes(query)) {
                btn.style.display = 'flex';
            } else {
                btn.style.display = 'none';
            }
        }
    }

    function openEditModal(app) {
        // App is passed as object
        document.getElementById('editAppId').value = app.id;
        document.getElementById('editAppName').value = app.name;
        document.getElementById('editAppUrl').value = app.url;
        document.getElementById('editIconInput').value = app.icon;
        document.getElementById('editPreviewIconClass').className = (app.icon || 'fa-solid fa-layer-group') + ' text-lg';
        document.getElementById('editIsActive').checked = app.is_active == 1;
        document.getElementById('editAppSortOrder').value = app.sort_order || 99;

        document.getElementById('editAppModal').classList.remove('hidden');
    }

    // Initialize on load
    initIconPicker();
</script>

<script>
    function confirmDelete(id, name) {
        if (confirm('Are you sure you want to delete "' + name + '"?')) {
            window.location.href = 'actions/delete_app.php?id=' + id;
        }
    }
</script>