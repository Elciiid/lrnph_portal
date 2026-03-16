<?php
// Fetch announcements
$annQuery = "SELECT * FROM portal_announcements ORDER BY created_at DESC";
$annStmt = sqlsrv_query($conn, $annQuery);
$headlines = [];
$announcements = [];
if ($annStmt) {
    while ($row = sqlsrv_fetch_array($annStmt, SQLSRV_FETCH_ASSOC)) {
        // Assume 'placement' or 'type' column determines this.
        // If the table uses 'type', we use that. If it uses 'placement', we might need to adjust.
        // Based on setup_announcements.php, the column is 'type'.
        // Based on previous code, 'placement' was used. I will look for 'type' first, then fallback to 'placement' mapping if needed.

        $type = $row['type'] ?? ($row['placement'] == 'dashboard' ? 'headline' : 'announcement');

        if ($type === 'headline') {
            $headlines[] = $row;
        } else {
            $announcements[] = $row;
        }
    }
}
?>


<style>
    .hover-scroll::-webkit-scrollbar {
        height: 8px;
        background-color: transparent;
    }

    .hover-scroll::-webkit-scrollbar-thumb {
        background-color: transparent;
        border-radius: 4px;
    }

    .hover-scroll:hover::-webkit-scrollbar-thumb {
        background-color: rgba(156, 163, 175, 0.5);
    }

    /* Firefox */
    .hover-scroll {
        scrollbar-width: thin;
        scrollbar-color: transparent transparent;
    }

    .hover-scroll:hover {
        scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
    }
</style>

<div class="h-full flex flex-col gap-6 overflow-y-auto custom-scrollbar p-1">
    <!-- Header -->
    <div
        class="flex flex-col md:flex-row justify-between items-end md:items-center bg-white p-6 rounded-[20px] shadow-sm gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Content Manager</h2>
            <p class="text-sm text-gray-500 mt-1">Manage headlines and sidebar announcements</p>
        </div>
        <div class="flex gap-3">
            <button onclick="openAnnouncementModal('announcement')"
                class="bg-white border border-pink-100 text-pink-500 px-5 py-2.5 rounded-xl font-semibold shadow-sm hover:bg-pink-50 transition-all flex items-center gap-2">
                <i class="fa-solid fa-bullhorn"></i>
                Add Announcement
            </button>
            <button onclick="openAnnouncementModal('headline')"
                class="bg-pink-500 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg shadow-pink-500/30 hover:bg-pink-600 transition-all transform hover:-translate-y-0.5 flex items-center gap-2">
                <i class="fa-solid fa-heading"></i>
                Add Headline
            </button>
        </div>
    </div>

    <!-- Headlines Section -->
    <div>
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <span class="w-2 h-8 bg-pink-500 rounded-full"></span>
            Headlines (Top Banner)
        </h3>
        <div id="headlinesContainer" class="flex overflow-x-auto pb-4 gap-6 snap-x hover-scroll items-start">
            <?php if (empty($headlines)): ?>
                <div
                    class="w-full py-12 bg-white rounded-2xl border border-dashed border-gray-200 text-center flex-shrink-0">
                    <p class="text-gray-400">No headlines created yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($headlines as $ann): ?>
                    <!-- Inline Card for Headline -->
                    <!-- Inline Card for Headline (Text Only) -->
                    <div
                        class="bg-gradient-to-r from-pink-500 to-pink-600 rounded-2xl shadow-md overflow-hidden group hover:shadow-lg transition-all flex flex-col text-white relative min-w-[300px] w-[300px] flex-shrink-0 snap-start">
                        <div class="absolute top-0 right-0 p-4 opacity-10">
                            <i class="fa-solid fa-quote-right text-6xl"></i>
                        </div>

                        <div class="p-5 flex-1 flex flex-col relative z-10">
                            <div class="flex justify-between items-start mb-3">
                                <span
                                    class="<?php echo $ann['is_active'] ? 'bg-white/20 text-white' : 'bg-black/20 text-gray-200'; ?> text-[10px] font-bold px-2 py-1 rounded-full backdrop-blur-sm">
                                    <?php echo $ann['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                                <div class="flex gap-2">
                                    <button onclick='openEditAnnouncementModal(<?php echo json_encode($ann); ?>)'
                                        class="text-white/70 hover:text-white transition-colors" title="Edit"><i
                                            class="fa-solid fa-pen"></i></button>
                                    <a href="actions/toggle_announcement.php?id=<?php echo $ann['id']; ?>&status=<?php echo $ann['is_active'] ? 0 : 1; ?>"
                                        class="text-white/70 hover:text-white transition-colors" title="Toggle Status"><i
                                            class="fa-solid <?php echo $ann['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i></a>
                                    <a href="actions/delete_announcement.php?id=<?php echo $ann['id']; ?>"
                                        onclick="return confirm('Delete this headline?');"
                                        class="text-white/70 hover:text-white transition-colors" title="Delete"><i
                                            class="fa-regular fa-trash-can"></i></a>
                                </div>
                            </div>

                            <h3 class="font-bold text-white text-lg mb-2 leading-tight line-clamp-2">
                                <?php echo htmlspecialchars($ann['title']); ?>
                            </h3>
                            <p
                                class="text-pink-100 text-sm line-clamp-3 mb-4 font-light bg-black/5 p-3 rounded-lg backdrop-blur-sm border border-white/5">
                                <?php echo htmlspecialchars($ann['description'] ?? ''); ?>
                            </p>

                            <div class="mt-auto border-t border-white/10 pt-3">
                                <span class="text-[10px] text-pink-100/80 font-medium flex items-center gap-1">
                                    <i class="fa-regular fa-clock"></i>
                                    <?php echo $ann['created_at']->format('M d, Y'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Side Announcements Section -->
    <div class="mt-2">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <span class="w-2 h-8 bg-purple-500 rounded-full"></span>
            Side Announcements
        </h3>
        <div id="announcementsContainer" class="flex overflow-x-auto pb-4 gap-6 snap-x hover-scroll items-start">
            <?php if (empty($announcements)): ?>
                <div
                    class="w-full py-12 bg-white rounded-2xl border border-dashed border-gray-200 text-center flex-shrink-0">
                    <p class="text-gray-400">No side announcements created yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                    <!-- Inline Card for Announcement -->
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md transition-all flex flex-col min-w-[300px] w-[300px] flex-shrink-0 snap-start">
                        <div class="relative h-40 bg-gray-100 overflow-hidden">
                            <?php if (!empty($ann['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($ann['image_url']); ?>"
                                    class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gray-50 text-gray-400"><i
                                        class="fa-regular fa-image text-3xl opacity-50"></i></div>
                            <?php endif; ?>
                            <div class="absolute top-3 right-3">
                                <span
                                    class="<?php echo $ann['is_active'] ? 'bg-emerald-500' : 'bg-gray-400'; ?> text-white text-[10px] font-bold px-2 py-1 rounded-full shadow-sm">
                                    <?php echo $ann['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="p-4 flex-1 flex flex-col">
                            <h3 class="font-bold text-gray-800 mb-1 leading-tight line-clamp-1">
                                <?php echo htmlspecialchars($ann['title']); ?>
                            </h3>
                            <p class="text-gray-500 text-xs line-clamp-2 mb-3">
                                <?php echo htmlspecialchars($ann['description'] ?? ''); ?>
                            </p>
                            <div class="flex items-center justify-between pt-3 border-t border-gray-50 mt-auto">
                                <span
                                    class="text-[10px] text-gray-400 font-medium"><?php echo $ann['created_at']->format('M d, Y'); ?></span>
                                <div class="flex gap-2">
                                    <button onclick='openEditAnnouncementModal(<?php echo json_encode($ann); ?>)'
                                        class="text-gray-400 hover:text-blue-500 transition-colors" title="Edit"><i
                                            class="fa-solid fa-pen"></i></button>
                                    <a href="actions/toggle_announcement.php?id=<?php echo $ann['id']; ?>&status=<?php echo $ann['is_active'] ? 0 : 1; ?>"
                                        class="text-gray-400 hover:text-emerald-500"><i
                                            class="fa-solid <?php echo $ann['is_active'] ? 'fa-toggle-on text-emerald-500' : 'fa-toggle-off'; ?>"></i></a>
                                    <a href="actions/delete_announcement.php?id=<?php echo $ann['id']; ?>"
                                        onclick="return confirm('Delete this announcement?');"
                                        class="text-gray-400 hover:text-red-500"><i class="fa-regular fa-trash-can"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Announcement Modal -->
<div id="addAnnouncementModal" class="hidden fixed inset-0 z-[9999]">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onclick="document.getElementById('addAnnouncementModal').classList.add('hidden')"></div>

    <!-- Modal Content -->
    <div
        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl w-full max-w-lg p-8 shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800" id="modalTitle">Create New</h3>
            <button onclick="document.getElementById('addAnnouncementModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="actions/add_announcement.php" method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="type" id="announcementTypeInput" value="announcement">

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5"
                    id="titleLabel">Title</label>
                <input type="text" name="title" required placeholder="Enter title..."
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm font-semibold text-gray-800 placeholder-gray-400">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Description
                    (Optional)</label>
                <textarea name="description" rows="3" placeholder="Add some details..."
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm resize-none placeholder-gray-400"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <!-- Hidden or removed placement selector, using inferred type -->
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Status</label>
                    <select name="is_active"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm bg-white cursor-pointer">
                        <option value="1">Active</option>
                        <option value="0">Draft (Inactive)</option>
                    </select>
                </div>
            </div>

            <div id="imageInputContainer">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Image</label>

                <!-- Toggle between URL and File -->
                <div class="flex items-center gap-4 mb-3">
                    <label class="flex items-center gap-2 text-sm cursor-pointer group">
                        <input type="radio" name="image_option" value="url" checked onclick="toggleAnnImageInput('url')"
                            class="text-pink-500 focus:ring-pink-500">
                        <span class="group-hover:text-pink-600 transition-colors">Image URL</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer group">
                        <input type="radio" name="image_option" value="file" onclick="toggleAnnImageInput('file')"
                            class="text-pink-500 focus:ring-pink-500">
                        <span class="group-hover:text-pink-600 transition-colors">Upload File</span>
                    </label>
                </div>

                <div id="annImageUrlInput">
                    <input type="text" name="image_url" placeholder="https://..."
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm placeholder-gray-400">
                </div>
                <div id="annImageFileInput" class="hidden">
                    <div
                        class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center hover:bg-gray-50 transition-colors cursor-pointer relative">
                        <input type="file" name="image_file" accept="image/*"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="flex flex-col items-center justify-center text-gray-400">
                            <i class="fa-solid fa-cloud-arrow-up text-2xl mb-2"></i>
                            <span class="text-xs font-medium">Click to upload image</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="w-full bg-pink-500 text-white py-3.5 rounded-xl font-bold shadow-lg shadow-pink-500/30 hover:bg-pink-600 transition-all transform hover:-translate-y-0.5">
                    Create Announcement
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleAnnImageInput(option) {
        if (option === 'url') {
            document.getElementById('annImageUrlInput').classList.remove('hidden');
            document.getElementById('annImageFileInput').classList.add('hidden');
        } else {
            document.getElementById('annImageUrlInput').classList.add('hidden');
            document.getElementById('annImageFileInput').classList.remove('hidden');
        }
    }

    function toggleEditAnnImageInput(option) {
        if (option === 'url') {
            document.getElementById('editAnnImageUrlInput').classList.remove('hidden');
            document.getElementById('editAnnImageFileInput').classList.add('hidden');
        } else {
            document.getElementById('editAnnImageUrlInput').classList.add('hidden');
            document.getElementById('editAnnImageFileInput').classList.remove('hidden');
        }
    }

    function openAnnouncementModal(type) {
        document.getElementById('addAnnouncementModal').classList.remove('hidden');
        document.getElementById('announcementTypeInput').value = type;

        const titleEl = document.getElementById('modalTitle');
        const labelEl = document.getElementById('titleLabel');

        if (type === 'headline') {
            titleEl.textContent = 'Create New Headline';
            labelEl.textContent = 'Headline Title';
            document.getElementById('imageInputContainer').classList.add('hidden');
        } else {
            titleEl.textContent = 'Create Side Announcement';
            labelEl.textContent = 'Announcement Title';
            document.getElementById('imageInputContainer').classList.remove('hidden');
        }
    }

    function openEditAnnouncementModal(ann) {
        document.getElementById('editAnnouncementModal').classList.remove('hidden');

        document.getElementById('editAnnId').value = ann.id;
        document.getElementById('editAnnTitle').value = ann.title;
        document.getElementById('editAnnDescription').value = ann.description || '';
        document.getElementById('editAnnStatus').value = ann.is_active;
        document.getElementById('editAnnImageUrl').value = ann.image_url || '';
        document.getElementById('editAnnType').value = ann.type || (ann.placement == 'dashboard' ? 'headline' : 'announcement');

        // Handle type-specific visibility
        const type = document.getElementById('editAnnType').value;
        if (type === 'headline') {
            document.getElementById('editImageContainer').classList.add('hidden');
            document.getElementById('editModalTitle').textContent = 'Edit Headline';
        } else {
            document.getElementById('editImageContainer').classList.remove('hidden');
            document.getElementById('editModalTitle').textContent = 'Edit Announcement';
        }
    }
</script>

<!-- Edit Announcement Modal -->
<div id="editAnnouncementModal" class="hidden fixed inset-0 z-[9999]">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onclick="document.getElementById('editAnnouncementModal').classList.add('hidden')"></div>

    <div
        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl w-full max-w-lg p-8 shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800" id="editModalTitle">Edit Announcement</h3>
            <button onclick="document.getElementById('editAnnouncementModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="actions/edit_announcement.php" method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="id" id="editAnnId">
            <input type="hidden" name="type" id="editAnnType"> <!-- To preserve type -->

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Title</label>
                <input type="text" name="title" id="editAnnTitle" required
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm font-semibold text-gray-800 placeholder-gray-400">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Description</label>
                <textarea name="description" id="editAnnDescription" rows="3"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm resize-none placeholder-gray-400"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Status</label>
                <select name="is_active" id="editAnnStatus"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm bg-white cursor-pointer">
                    <option value="1">Active</option>
                    <option value="0">Draft (Inactive)</option>
                </select>
            </div>

            <div id="editImageContainer">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Image</label>
                <div class="flex items-center gap-4 mb-3">
                    <label class="flex items-center gap-2 text-sm cursor-pointer group">
                        <input type="radio" name="image_option" value="url" checked
                            onclick="toggleEditAnnImageInput('url')" class="text-pink-500 focus:ring-pink-500">
                        <span class="group-hover:text-pink-600 transition-colors">Image URL</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer group">
                        <input type="radio" name="image_option" value="file" onclick="toggleEditAnnImageInput('file')"
                            class="text-pink-500 focus:ring-pink-500">
                        <span class="group-hover:text-pink-600 transition-colors">Upload File</span>
                    </label>
                </div>

                <div id="editAnnImageUrlInput">
                    <input type="text" name="image_url" id="editAnnImageUrl" placeholder="https://..."
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm placeholder-gray-400">
                </div>
                <div id="editAnnImageFileInput" class="hidden">
                    <div
                        class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center hover:bg-gray-50 transition-colors cursor-pointer relative">
                        <input type="file" name="image_file" accept="image/*"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="flex flex-col items-center justify-center text-gray-400">
                            <i class="fa-solid fa-cloud-arrow-up text-2xl mb-2"></i>
                            <span class="text-xs font-medium">Click to upload new image</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="w-full bg-pink-500 text-white py-3.5 rounded-xl font-bold shadow-lg shadow-pink-500/30 hover:bg-pink-600 transition-all transform hover:-translate-y-0.5">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

</div>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        function enableHorizontalScroll(containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;

            container.addEventListener("wheel", (evt) => {
                // Transform vertical scroll (deltaY) to horizontal scroll
                if (evt.deltaY !== 0) {
                    evt.preventDefault();
                    container.scrollLeft += evt.deltaY;
                }
            }, { passive: false });
        }

        enableHorizontalScroll("headlinesContainer");
        enableHorizontalScroll("announcementsContainer");
    });
</script>