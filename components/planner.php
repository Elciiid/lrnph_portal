<?php
// components/planner.php
// Main Planner Component for the Dashboard

// Initialize variables
$currentMonth = date('m');
$currentYear = date('Y');
if (isset($_GET['month']) && isset($_GET['year'])) {
    $currentMonth = $_GET['month'];
    $currentYear = $_GET['year'];
}

// Get timestamp (Needed for initial render?? No, we are doing AJAX)
// Actually we can skip these, but let's keep init vars
$initMonth = $_GET['month'] ?? date('m');
$initYear = $_GET['year'] ?? date('Y');

// Fetch Departments for Meeting Planner (Attendee Selection)
$departments = [];
if (isset($conn)) {
    $deptQuery = "SELECT DISTINCT \"Department\" FROM \"prtl_lrn_master_list\" WHERE \"isActive\" = true ORDER BY \"Department\" ASC";
    $deptStmt = $conn->query($deptQuery);
    if ($deptStmt) {
        while ($row = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
            $departments[] = $row['Department'];
        }
    }
}
?>

<div id="plannerWrapper" class="h-[calc(100vh-140px)]">
    <div class="flex gap-6 h-full">
        <!-- Calendar Section -->
        <div class="bg-white rounded-[20px] shadow-sm p-6 flex-1 flex flex-col min-w-0">
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-3">
                    <button id="toggleSidebarBtn" onclick="togglePlannerLayout()"
                        class="w-10 h-10 flex items-center justify-center rounded-xl bg-gray-50 text-gray-400 hover:bg-pink-50 hover:text-pink-500 transition-all"
                        title="Toggle Sidebar">
                        <i class="fa-solid fa-bars-staggered"></i>
                    </button>
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                        <i class="fa-regular fa-calendar-days text-pink-500"></i>
                        Planner
                    </h2>
                </div>
                <div class="flex items-center gap-4 bg-gray-50 rounded-xl p-1">
                    <button id="prevBtn"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:bg-white hover:shadow-sm transition-all">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </button>
                    <span id="plannerTitle" class="text-sm font-bold text-gray-700 min-w-[120px] text-center">
                        Loading...
                    </span>
                    <button id="nextBtn"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:bg-white hover:shadow-sm transition-all">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button onclick="openAddPlanModal('<?php echo date('Y-m-d'); ?>')"
                        class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-lg shadow-pink-500/30 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> New Plan
                    </button>
                </div>
            </div>

            <div class="flex-1 grid grid-cols-7 grid-rows-6 gap-2 min-h-0">
                <!-- Headers -->
                <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $h): ?>
                    <div class="text-center text-xs font-bold text-gray-400 uppercase py-1 border-b border-gray-100">
                        <?php echo $h; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Calendar Grid (Populated by JS) -->
                <div id="plannerGrid" class="contents"></div>
            </div>
        </div>

        <!-- Right Sidebar (Up Next) -->
        <div class="w-[320px] bg-white rounded-[20px] shadow-sm p-6 flex flex-col">
            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3">Your Upcoming Meetings</h4>
            <div class="flex-1 overflow-y-auto no-scrollbar">
                <div id="upcomingList" class="space-y-3">
                    <p class="text-sm text-gray-400 italic">Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Meeting Modal -->
<div id="addPlanModal"
    class="hidden fixed inset-0 bg-black/50 z-[99] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div
        class="bg-white rounded-2xl w-full max-w-4xl p-0 shadow-2xl transform transition-all scale-100 flex flex-col max-h-[90vh] overflow-hidden">

        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div>
                <h3 class="text-2xl font-bold text-gray-800">Schedule Meeting</h3>
                <p class="text-sm text-gray-500 mt-1">Create a new event and invite attendees.</p>
            </div>
            <button onclick="document.getElementById('addPlanModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Scrollable Body -->
        <div class="overflow-y-auto custom-scrollbar p-8">
            <form action="actions/add_schedule.php" method="POST" enctype="multipart/form-data" id="addPlanForm">
                <input type="hidden" name="redirect" value="planner">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Left Column: Details -->
                    <div class="space-y-5">
                        <h4
                            class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2 mb-4">
                            Meeting Details</h4>

                        <!-- Meeting Name -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Meeting
                                Name</label>
                            <input type="text" name="title" required placeholder="e.g. Project Kickoff..."
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm font-semibold placeholder-gray-400 bg-gray-50/30 focus:bg-white">
                        </div>

                        <!-- Date -->
                        <div>
                            <label
                                class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Date</label>
                            <input type="date" name="date" id="planDateInput" required
                                min="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 bg-gray-50/30 focus:bg-white">
                        </div>

                        <!-- Time -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Start
                                    Time</label>
                                <input type="time" name="from_time" id="planStartTimeInput" required
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 bg-gray-50/30 focus:bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">End
                                    Time</label>
                                <input type="time" name="to_time" id="planEndTimeInput" required
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 bg-gray-50/30 focus:bg-white">
                            </div>
                        </div>

                        <!-- Facilitator Warning -->
                        <div id="facilitatorWarning"
                            class="hidden p-3 rounded-xl bg-orange-50 border border-orange-100 text-orange-600 text-[10px] font-bold uppercase tracking-wide flex items-center gap-2">
                            <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                            You are already booked for another meeting at this time.
                        </div>

                        <!-- Venue -->
                        <div>
                            <label
                                class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Venue</label>
                            <select name="venue" id="venueSelect" required onchange="toggleCustomVenue(this.value)"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 mb-2 bg-gray-50/30 focus:bg-white">
                                <option value="" disabled selected>Select Venue</option>
                                <!-- Filled by JS -->
                                <option value="Custom">Other (Specify...)</option>
                            </select>
                            <input type="text" name="custom_venue" id="customVenueInput"
                                placeholder="Enter custom venue..."
                                class="hidden w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 mt-2">

                            <!-- Availability Link -->
                            <div class="mt-2 flex items-center gap-2">
                                <button type="button" onclick="openVenueAvailabilityModal()"
                                    class="text-[10px] font-bold text-pink-500 hover:text-pink-600 uppercase tracking-wide flex items-center gap-1.5 transition-colors">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                    Click here to see availability
                                </button>
                            </div>
                        </div>

                        <!-- Category -->
                        <div>
                            <label
                                class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Category</label>
                            <select name="category_id" id="categorySelect" required
                                onchange="toggleCustomCategory(this.value)"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 mb-2 bg-gray-50/30 focus:bg-white">
                                <option value="" disabled selected>Select Category</option>
                                <!-- Filled by JS -->
                                <option value="custom">Other (Custom)</option>
                            </select>
                            <input type="text" name="custom_category_text" id="customCategoryInput"
                                placeholder="Enter custom category..."
                                class="hidden w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/10 outline-none transition-all text-sm text-gray-700 mt-2">
                        </div>
                    </div>

                    <!-- Right Column: Content -->
                    <div class="space-y-6">
                        <h4
                            class="text-xs font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2 mb-4">
                            Content & People</h4>

                        <!-- Agenda Builder -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Meeting
                                Agendas</label>
                            <div class="bg-gray-50/50 rounded-xl p-4 border border-gray-100">
                                <div id="agendaList" class="space-y-3 mb-3">
                                    <div class="flex gap-2">
                                        <div class="w-6 flex items-center justify-center text-pink-300">
                                            <i class="fa-solid fa-1 text-xs font-bold"></i>
                                        </div>
                                        <input type="text" name="agenda[]" placeholder="Agenda..."
                                            class="flex-1 px-3 py-2 rounded-lg border border-gray-200 focus:border-pink-500 outline-none text-sm">
                                    </div>
                                </div>
                                <button type="button" onclick="addAgendaInput()"
                                    class="ml-8 text-xs font-bold text-pink-500 hover:text-pink-600 flex items-center gap-1.5 transition-colors">
                                    <i class="fa-solid fa-plus-circle"></i> Add Agenda
                                </button>
                            </div>
                        </div>

                        <!-- Attendees -->
                        <div>
                            <label
                                class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Attendees</label>
                            <button type="button" onclick="openAttendeeModal()"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 text-gray-600 hover:bg-white hover:border-pink-300 hover:text-pink-600 transition-all text-sm flex items-center justify-between group bg-gray-50/30">
                                <span id="attendeeCountLabel" class="font-medium">Select Attendees...</span>
                                <div
                                    class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center group-hover:border-pink-200 text-gray-400 group-hover:text-pink-500">
                                    <i class="fa-solid fa-users text-xs"></i>
                                </div>
                            </button>
                            <!-- Hidden inputs injected by JS -->
                            <div id="hiddenAttendeeInputs"></div>
                        </div>

                        <!-- Image (Optional) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Cover
                                Image (Optional)</label>
                            <div
                                class="p-4 rounded-xl border border-dashed border-gray-300 hover:border-pink-300 transition-colors bg-gray-50/30">
                                <div class="flex items-center gap-6 mb-3">
                                    <label class="flex items-center gap-2 text-sm cursor-pointer group">
                                        <input type="radio" name="image_option" value="url" checked
                                            onclick="toggleImageInput('url')" class="text-pink-500 focus:ring-pink-500">
                                        <span class="group-hover:text-pink-600 font-medium text-gray-600">Image
                                            URL</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm cursor-pointer group">
                                        <input type="radio" name="image_option" value="file"
                                            onclick="toggleImageInput('file')"
                                            class="text-pink-500 focus:ring-pink-500">
                                        <span class="group-hover:text-pink-600 font-medium text-gray-600">Upload
                                            File</span>
                                    </label>
                                </div>

                                <div id="imageUrlInput">
                                    <input type="text" name="image_url" placeholder="Paste image link here..."
                                        class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:border-pink-500 outline-none text-sm">
                                </div>
                                <div id="imageFileInput" class="hidden">
                                    <input type="file" name="image_file" accept="image/*"
                                        class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Sticky Footer -->
        <div class="px-8 py-5 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 shrink-0">
            <button type="button" onclick="document.getElementById('addPlanModal').classList.add('hidden')"
                class="px-6 py-2.5 rounded-xl border border-gray-200 text-gray-600 font-semibold hover:bg-white transition-colors text-sm">Cancel</button>
            <button type="submit" form="addPlanForm"
                class="px-8 py-2.5 rounded-xl bg-gradient-to-r from-pink-500 to-rose-500 text-white font-bold hover:shadow-lg hover:shadow-pink-500/30 hover:-translate-y-0.5 transition-all text-sm flex items-center gap-2">
                <i class="fa-regular fa-calendar-plus"></i>
                Create Meeting
            </button>
        </div>
    </div>
</div>

<!-- Attendee Selection Modal -->
<div id="attendeeModal"
    class="hidden fixed inset-0 bg-black/50 z-[100] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-5xl h-[85vh] shadow-2xl flex flex-col overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <div>
                <h3 class="text-xl font-bold text-gray-800">Manage Attendees</h3>
                <p class="text-xs text-gray-500 mt-0.5">Select employees or add guests.</p>
            </div>
            <button onclick="closeAttendeeModal()"
                class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="flex flex-1 min-h-0">
            <!-- Left: Departments -->
            <div class="w-1/3 border-r border-gray-100 flex flex-col bg-gray-50/50">
                <div class="p-3 border-b border-gray-100">
                    <input type="text" placeholder="Search Depts..." onkeyup="filterDepts(this.value)"
                        class="w-full px-3 py-2 rounded-lg border border-gray-200 text-xs focus:outline-none focus:border-pink-500 bg-white">
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-1" id="deptList">
                    <?php foreach ($departments as $dept):
                        $displayDept = str_replace(' - LRN', '', $dept);
                        ?>
                        <button
                            onclick="selectDepartment('<?php echo htmlspecialchars($dept); ?>', this, '<?php echo htmlspecialchars($displayDept); ?>')"
                            class="dept-btn w-full text-left px-3 py-2 rounded-lg text-xs text-gray-600 hover:bg-white hover:shadow-sm hover:text-pink-600 transition-all font-medium truncate">
                            <?php echo htmlspecialchars($displayDept); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Employees -->
            <div class="flex-1 flex flex-col bg-white relative border-r border-gray-100">
                <div class="p-4 border-b border-gray-100 flex flex-col gap-3 bg-white sticky top-0 z-20">
                    <div class="flex items-center justify-between">
                        <input type="text" placeholder="Search employees..." onkeyup="filterEmployees(this.value)"
                            class="flex-1 px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:border-pink-500 bg-gray-50">

                        <!-- Select All Checkbox -->
                        <div id="selectAllContainer"
                            class="hidden ml-4 flex items-center gap-2 px-3 py-1.5 bg-pink-50 rounded-lg border border-pink-100 cursor-pointer hover:bg-pink-100 transition-colors"
                            onclick="toggleDeptSelectAll()">
                            <input type="checkbox" id="deptSelectAll"
                                class="rounded text-pink-500 focus:ring-pink-500 pointer-events-none">
                            <label for="deptSelectAll"
                                class="text-[10px] font-bold text-pink-600 uppercase tracking-wide cursor-pointer pointer-events-none">Select
                                All</label>
                        </div>
                    </div>

                    <!-- Custom Guest Input -->
                    <div class="pt-2 border-t border-gray-100">
                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Add Custom
                            Guest</label>
                        <div class="flex gap-2 mt-1">
                            <input type="text" id="customAttendeeInput" placeholder="Name (e.g. John Doe)"
                                class="flex-1 px-3 py-1.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:border-pink-500"
                                onkeypress="if(event.key === 'Enter') { addCustomAttendee(); event.preventDefault(); }">
                            <button onclick="addCustomAttendee()"
                                class="bg-gray-800 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-black transition-colors">Add</button>
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-4" id="employeeListContainer">
                    <div id="employeeListPlaceholder"
                        class="h-full flex flex-col items-center justify-center text-gray-300">
                        <i class="fa-regular fa-building text-3xl mb-2"></i>
                        <p class="text-xs">Select department</p>
                    </div>
                    <div id="employeeListGrid" class="grid grid-cols-1 gap-2"></div>
                </div>

                <!-- Loading Overlay -->
                <div id="attendeeLoading"
                    class="hidden absolute inset-0 bg-white/80 z-10 flex items-center justify-center">
                    <i class="fa-solid fa-circle-notch fa-spin text-pink-500 text-2xl"></i>
                </div>
            </div>

            <!-- Third: Selection Review (The "Small Modal on the Side" Feel) -->
            <div class="w-1/4 flex flex-col bg-gray-50/50">
                <div class="p-4 border-b border-gray-100 bg-white/80 backdrop-blur-sm sticky top-0 z-20">
                    <h4 class="text-xs font-bold text-gray-800 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-pink-500"></i>
                        Review Selection
                    </h4>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-6">
                    <!-- Employees Section -->
                    <div class="review-section">
                        <button onclick="toggleReviewSection('reviewEmpList', 'reviewEmpChevron')"
                            class="w-full flex items-center justify-between mb-2 group">
                            <div class="flex items-center gap-2">
                                <i id="reviewEmpChevron"
                                    class="fa-solid fa-chevron-down text-[8px] text-gray-400 transition-transform duration-200"></i>
                                <span
                                    class="text-[10px] font-bold text-gray-400 uppercase tracking-widest group-hover:text-pink-500 transition-colors">Employees</span>
                            </div>
                            <span id="selectedEmpBadge"
                                class="text-[10px] bg-pink-100 text-pink-600 px-1.5 py-0.5 rounded-full font-bold">0</span>
                        </button>
                        <div id="reviewEmpList" class="space-y-1 overflow-hidden transition-all duration-300">
                            <p class="text-[10px] text-gray-400 italic text-center py-2">No employees selected</p>
                        </div>
                    </div>

                    <!-- Custom Guests Section -->
                    <div class="review-section">
                        <button onclick="toggleReviewSection('reviewGuestList', 'reviewGuestChevron')"
                            class="w-full flex items-center justify-between mb-2 pt-4 border-t border-gray-200/50 group">
                            <div class="flex items-center gap-2">
                                <i id="reviewGuestChevron"
                                    class="fa-solid fa-chevron-down text-[8px] text-gray-400 transition-transform duration-200"></i>
                                <span
                                    class="text-[10px] font-bold text-gray-400 uppercase tracking-widest group-hover:text-pink-500 transition-colors">Custom
                                    Guests</span>
                            </div>
                            <span id="selectedGuestBadge"
                                class="text-[10px] bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded-full font-bold">0</span>
                        </button>
                        <div id="reviewGuestList" class="space-y-1 overflow-hidden transition-all duration-300">
                            <p class="text-[10px] text-gray-400 italic text-center py-2">No guests added</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex justify-between items-center shrink-0">
            <div class="text-xs text-gray-500">
                Selected: <span id="totalSelectedCount" class="font-bold text-pink-600">0</span>
                <button onclick="clearAllAttendees()"
                    class="ml-2 text-red-400 hover:text-red-600 underline">Clear</button>
            </div>
            <div class="flex gap-2">
                <button onclick="closeAttendeeModal()"
                    class="px-4 py-2 rounded-lg text-gray-500 text-sm hover:bg-gray-200">Cancel</button>
                <button onclick="saveAttendees()"
                    class="px-4 py-2 rounded-lg bg-pink-500 text-white text-sm font-bold hover:bg-pink-600 shadow-md">Done</button>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div id="viewMeetingDetailsModal"
    class="hidden fixed inset-0 bg-black/50 z-[105] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-start bg-gray-50/50">
            <div>
                <h3 class="text-xl font-bold text-gray-800 leading-tight" id="viewMeetingTitle">...</h3>
                <p class="text-sm text-pink-600 font-medium mt-1" id="viewMeetingSubtitle">Venue</p>
            </div>
            <button onclick="document.getElementById('viewMeetingDetailsModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-6 overflow-y-auto custom-scrollbar space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-[10px] text-gray-400 uppercase font-bold">Date</span>
                    <p class="text-sm font-semibold text-gray-700" id="viewMeetingDate">-</p>
                </div>
                <div>
                    <span class="text-[10px] text-gray-400 uppercase font-bold">Time</span>
                    <p class="text-sm font-semibold text-gray-700" id="viewMeetingTime">-</p>
                </div>
                <div>
                    <span class="text-[10px] text-gray-400 uppercase font-bold">Creator</span>
                    <p class="text-sm font-semibold text-gray-700" id="viewMeetingCreator">-</p>
                </div>
                <div>
                    <span class="text-[10px] text-gray-400 uppercase font-bold">Category</span>
                    <p class="text-sm font-semibold text-gray-700" id="viewMeetingCategory">-</p>
                </div>
            </div>

            <div class="bg-pink-50/50 rounded-xl p-4 border border-pink-100">
                <h4 class="text-xs font-bold text-pink-400 uppercase tracking-wide mb-2"><i
                        class="fa-solid fa-list-check mr-1"></i> Agenda</h4>
                <div id="viewMeetingAgendaList" class="space-y-1.5"></div>
            </div>

            <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Attendees <span
                        id="viewMeetingAttendeeCount" class="ml-1 bg-gray-100 px-1 rounded text-[10px]">0</span></h4>
                <div class="flex flex-wrap gap-2" id="viewMeetingAttendeesList"></div>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end">
            <button id="viewMeetingEditBtn" class="hidden mr-auto text-pink-600 text-sm font-bold hover:underline">Edit
                (Coming Soon)</button>
            <button onclick="document.getElementById('viewMeetingDetailsModal').classList.add('hidden')"
                class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm font-bold hover:bg-gray-100">Close</button>
        </div>
    </div>
</div>

<script>
    // Global State
    let currentMonth = <?php echo $initMonth; ?>;
    let currentYear = <?php echo $initYear; ?>;
    const currentUserId = '<?php echo $currentUserId ?? ""; ?>';
    let bookedEmployees = [];
    let currentSelectedDeptName = null;
    let isPlannerExpanded = false;
    let globalPlannerData = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadPlannerData(currentMonth, currentYear);

        // Automatically hide sidebar for expanded view on load
        setTimeout(() => {
            if (!isPlannerExpanded) togglePlannerLayout(true);
        }, 100);
    });

    function changePlannerMonth(m, y) {
        currentMonth = m;
        currentYear = y;
        // Update URL without reload
        const url = new URL(window.location.href);
        url.searchParams.set('month', m);
        url.searchParams.set('year', y);
        window.history.pushState({}, '', url);

        loadPlannerData(m, y);
    }

    function loadPlannerData(m, y) {
        document.getElementById('plannerTitle').innerText = 'Loading...';

        fetch(`actions/fetch_planner_events.php?month=${m}&year=${y}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('plannerTitle').innerText = data.error;
                    return;
                }

                // Update Header
                document.getElementById('plannerTitle').innerText = `${data.monthName} ${data.year}`;

                // Update Buttons
                const prev = document.getElementById('prevBtn');
                const next = document.getElementById('nextBtn');

                // Remove old event listeners by cloning logic or just setting onclick
                prev.onclick = () => changePlannerMonth(data.prev.m, data.prev.y);
                next.onclick = () => changePlannerMonth(data.next.m, data.next.y);

                // Render Grid
                globalPlannerData = data;
                renderGrid(data);

                // Render Sidebar
                renderSidebar(data);
            })
            .catch(err => {
                console.error(err);
                document.getElementById('plannerTitle').innerText = 'Fetch error';
            });
    }

    function renderGrid(data) {
        const grid = document.getElementById('plannerGrid');
        grid.innerHTML = ''; // Clear Date Cells

        // 1. Padding
        for (let i = 0; i < data.dayOfWeek; i++) {
            const pad = document.createElement('div');
            pad.className = 'bg-gray-50/30 rounded-xl';
            grid.appendChild(pad);
        }

        // 2. Days
        const now = new Date();
        const todayStr = [
            now.getFullYear(),
            String(now.getMonth() + 1).padStart(2, '0'),
            String(now.getDate()).padStart(2, '0')
        ].join('-');

        for (let d = 1; d <= data.daysInMonth; d++) {
            // Construct date string manually to match PHP Y-m-d
            const dStr = String(d).padStart(2, '0');
            const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${dStr}`;

            const isToday = (dateStr === todayStr);
            const isPast = (dateStr < todayStr);

            const evts = data.events[d] || [];
            const hasEvents = evts.length > 0;

            // Styles
            let bgClass = 'bg-white border border-gray-100 hover:border-pink-200 cursor-pointer';
            if (isToday) bgClass = 'bg-pink-50/30 border border-pink-200 cursor-pointer';
            else if (isPast) bgClass = 'bg-gray-50 border border-gray-100 opacity-60 cursor-not-allowed';

            // Container
            const cell = document.createElement('div');
            cell.className = `${bgClass} rounded-xl p-2 relative flex flex-col transition-all group min-h-[110px] max-h-[110px] overflow-hidden`;

            if (!isPast) {
                cell.onclick = () => openAddPlanModal(dateStr);
            }

            // Header (Day Num + Icon)
            const head = document.createElement('div');
            head.className = 'flex justify-between items-start mb-1 shrink-0';

            const num = document.createElement('span');
            num.className = `text-xs font-bold ${isToday ? 'text-pink-600' : 'text-gray-700'}`;
            num.textContent = d;

            head.appendChild(num);

            if (!isPast) {
                const headIcons = document.createElement('div');
                headIcons.className = 'flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity';

                if (hasEvents) {
                    const listIcon = document.createElement('button');
                    listIcon.type = 'button';
                    listIcon.className = 'w-5 h-5 flex items-center justify-center rounded-full bg-pink-100 text-pink-500 hover:bg-pink-500 hover:text-white transition-all';
                    listIcon.innerHTML = '<i class="fa-solid fa-list-ul text-[10px]"></i>';
                    listIcon.title = 'View all meetings';
                    listIcon.onclick = (e) => {
                        e.stopPropagation();
                        openDayMeetingsModal(dateStr, evts);
                    };
                    headIcons.appendChild(listIcon);
                }

                const addIcon = document.createElement('i');
                addIcon.className = 'fa-solid fa-plus-circle text-pink-300 text-sm';
                headIcons.appendChild(addIcon);

                head.appendChild(headIcons);
            }
            cell.appendChild(head);

            // Events List
            if (hasEvents) {
                const list = document.createElement('div');
                list.className = 'flex-1 overflow-y-auto no-scrollbar flex flex-col gap-1 pr-0.5';
                list.onclick = (e) => e.stopPropagation();

                evts.forEach(e => {
                    const item = document.createElement('div');
                    const isMineClass = e.is_mine
                        ? 'bg-white border-pink-200 text-pink-600 font-bold'
                        : 'bg-gray-50 border-gray-200 text-gray-500';

                    item.className = `text-[9px] leading-tight px-1.5 py-1.5 rounded shadow-sm border ${isMineClass} hover:border-pink-400 transition-colors cursor-pointer`;
                    item.onclick = (event) => {
                        event.stopPropagation();
                        openViewMeetingDetails(e.id);
                    };

                    item.innerHTML = `
                        <div class="flex flex-col gap-0.5">
                            <div class="font-bold opacity-60 text-[8px]">${e.time}</div>
                            <div class="line-clamp-2">${e.title}</div>
                            ${isPlannerExpanded ? `
                                <div class="mt-1 flex flex-col gap-0.5 pt-1 border-t border-inherit/30 italic opacity-80">
                                    <div class="truncate"><i class="fa-solid fa-location-dot mr-1"></i>${e.venue || 'No Venue'}</div>
                                    <div class="truncate"><i class="fa-solid fa-user mr-1"></i>${e.creator_name}</div>
                                </div>
                            ` : ''}
                        </div>
                    `;
                    list.appendChild(item);
                });
                cell.appendChild(list);
            }

            grid.appendChild(cell);
        }
    }

    function renderSidebar(data) {
        const list = document.getElementById('upcomingList');
        list.innerHTML = '';

        const now = new Date();
        const todayStr = [
            now.getFullYear(),
            String(now.getMonth() + 1).padStart(2, '0'),
            String(now.getDate()).padStart(2, '0')
        ].join('-');

        let myEvents = [];
        Object.keys(data.events).forEach(day => {
            const dayNum = parseInt(day);
            const dStr = String(dayNum).padStart(2, '0');
            const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${dStr}`;

            // Only show if today or future
            if (dateStr < todayStr) return;

            const dayEvts = data.events[day];
            dayEvts.forEach(e => {
                if (e.is_mine) {
                    myEvents.push({ ...e, day: dayNum });
                }
            });
        });

        if (myEvents.length === 0) {
            list.innerHTML = '<p class="text-sm text-gray-400 italic">No upcoming meetings relevant to you.</p>';
            return;
        }

        // Sort
        myEvents.sort((a, b) => {
            if (a.day !== b.day) return a.day - b.day;
            return a.timestamp - b.timestamp;
        });

        myEvents.forEach(evt => {
            const row = document.createElement('div');
            row.className = 'flex gap-3 group hover:bg-gray-50 p-2 rounded-xl transition-colors cursor-pointer border border-transparent hover:border-gray-100';
            row.onclick = () => openViewMeetingDetails(evt.id);

            const timeRange = evt.time + (evt.to_time ? ' - ' + evt.to_time : '');
            const monthShort = data.monthName.substring(0, 3);

            let deleteBtn = '';
            // Note: in JS, comparison needs loose or strict mismatch check
            if (String(evt.created_by) == String(currentUserId)) {
                deleteBtn = `
                    <button onclick="event.stopPropagation(); deletePlan(${evt.id})"
                        class="w-6 h-6 rounded-md flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all opacity-0 group-hover:opacity-100 self-center">
                        <i class="fa-regular fa-trash-can text-xs"></i>
                    </button>
                `;
            }

            row.innerHTML = `
                <div class="w-10 h-10 rounded-xl bg-pink-50 text-pink-500 flex flex-col items-center justify-center shrink-0 border border-pink-100 font-bold">
                    <span class="text-xs text-gray-800">${evt.day}</span>
                    <span class="text-[0.6rem] text-gray-400 font-medium">${monthShort}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h5 class="text-sm font-semibold text-gray-800 truncate" title="${evt.title}">${evt.title}</h5>
                    <div class="flex flex-col gap-0.5 mt-0.5">
                        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">
                            <i class="fa-regular fa-clock mr-1"></i> ${timeRange}
                        </span>
                        ${evt.venue ? `<span class="text-[10px] text-gray-400 truncate"><i class="fa-solid fa-location-dot mr-1"></i>${evt.venue}</span>` : ''}
                    </div>
                </div>
                ${deleteBtn}
            `;
            list.appendChild(row);
        });
    }


    // Use Set for unique selection. Stores Employee IDs as strings for real emps, and Names for customs.
    let selectedDeptEmployees = new Set(); // Stores IDs
    let selectedCustomGuests = new Set(); // Stores Names
    let currentDeptEmployees = []; // Stores current view's emps [{id, name}]
    let empNameMap = {}; // Helper for review list names {id: name}

    // --- Init ---
    document.addEventListener('DOMContentLoaded', () => {
        // Load Categories
        fetch('actions/get_categories.php')
            .then(res => res.ok ? res.json() : Promise.reject('API Error'))
            .then(data => {
                const select = document.getElementById('categorySelect');
                if (!select) return;
                
                // Clear existing dynamic options (except Select and Custom)
                const customOpt = select.querySelector('option[value=\"custom\"]');
                
                if (Array.isArray(data)) {
                    data.forEach(cat => {
                        const opt = document.createElement('option');
                        opt.value = cat.id;
                        opt.textContent = cat.name;
                        cat.id && select.insertBefore(opt, customOpt || select.lastElementChild);
                    });
                } else if (data.error) {
                    console.error('Category Load Error:', data.error);
                }
            })
            .catch(err => {
                console.error('Categories Fetch Failed:', err);
                const select = document.getElementById('categorySelect');
                if (select) {
                    const opt = document.createElement('option');
                    opt.disabled = true;
                    opt.textContent = \"(Failed to load categories)\";
                    select.prepend(opt);
                }
            });

        // Load Venues
        fetch('actions/get_venues.php')
            .then(res => res.json())
            .then(data => {
                const select = document.getElementById('venueSelect');
                if (!select) return;
                if (!data.error) {
                    data.forEach(venue => {
                        const opt = document.createElement('option');
                        opt.value = venue.name;
                        opt.textContent = venue.name;
                        opt.dataset.venue = venue.name; // Tag for availability filtering
                        select.insertBefore(opt, select.lastElementChild);
                    });
                }
            });

        // Availability Listeners
        ['planDateInput', 'planStartTimeInput', 'planEndTimeInput'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', checkScheduleAvailability);
        });
    });

    function checkScheduleAvailability() {
        const date = document.getElementById('planDateInput').value;
        const start = document.getElementById('planStartTimeInput').value;
        const end = document.getElementById('planEndTimeInput').value;

        if (!date || !start || !end) return;

        fetch(`actions/check_availability.php?date=${date}&start_time=${start}&end_time=${end}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) return;

                // 1. Handle Facilitator Warning
                const warning = document.getElementById('facilitatorWarning');
                if (data.facilitator_booked) {
                    warning.classList.remove('hidden');
                } else {
                    warning.classList.add('hidden');
                }

                // 2. Handle Venue Dropdown
                const select = document.getElementById('venueSelect');
                const unavailable = data.unavailable_venues || [];

                Array.from(select.options).forEach(opt => {
                    if (opt.value === "" || opt.value === "Custom") return;

                    const venueName = opt.dataset.venue || opt.value;
                    if (unavailable.includes(venueName)) {
                        opt.disabled = true;
                        opt.textContent = `${venueName} (Occupied)`;
                        opt.style.color = '#ccc';
                        // If it was selected, reset it
                        if (select.value === venueName) {
                            select.value = "";
                        }
                    } else {
                        opt.disabled = false;
                        opt.textContent = venueName;
                        opt.style.color = '';
                    }
                });

                // 3. Handle Booked Employees
                bookedEmployees = data.booked_employees || [];

                // Refresh Employee List if someone is viewing it
                if (document.getElementById('attendeeModal').classList.contains('hidden') === false && currentSelectedDeptName) {
                    selectDepartment(currentSelectedDeptName, null, null, true);
                }
            });
    }

    // --- UI Toggles ---
    function toggleCustomVenue(val) {
        const inp = document.getElementById('customVenueInput');
        if (val === 'Custom') inp.classList.remove('hidden'); else inp.classList.add('hidden');
    }
    function toggleCustomCategory(val) {
        const inp = document.getElementById('customCategoryInput');
        if (val === 'custom') inp.classList.remove('hidden'); else inp.classList.add('hidden');
    }
    function toggleImageInput(type) {
        if (type === 'url') {
            document.getElementById('imageUrlInput').classList.remove('hidden');
            document.getElementById('imageFileInput').classList.add('hidden');
        } else {
            document.getElementById('imageUrlInput').classList.add('hidden');
            document.getElementById('imageFileInput').classList.remove('hidden');
        }
    }
    function addAgendaInput() {
        const wrap = document.createElement('div');
        wrap.className = 'flex gap-2';
        wrap.innerHTML = `
<div class="w-6 flex items-center justify-center text-pink-300">
    <i class="fa-solid fa-circle text-[6px]"></i>
</div>
<input type="text" name="agenda[]" placeholder="Agenda..."
    class="flex-1 px-3 py-2 rounded-lg border border-gray-200 focus:border-pink-500 outline-none text-sm">
<button type="button" onclick="this.parentElement.remove()"
    class="text-gray-300 hover:text-red-500 px-2 transition-colors"><i class="fa-solid fa-xmark"></i></button>
`;
        document.getElementById('agendaList').appendChild(wrap);
    }
    function openAddPlanModal(date) {
        document.getElementById('planDateInput').value = date;
        document.getElementById('addPlanModal').classList.remove('hidden');
        // Reset Logic if needed
    }

    // --- Attendee Logic ---
    function openAttendeeModal() {
        document.getElementById('attendeeModal').classList.remove('hidden');
        renderSelectionCount();
        renderReviewList();
    }
    function closeAttendeeModal() {
        document.getElementById('attendeeModal').classList.add('hidden');
    }

    // Fetch Employees
    function selectDepartment(deptName, btnEl, displayName, isRefresh = false) {
        currentSelectedDeptName = deptName;
        // Highlight active btn
        if (!isRefresh) {
            document.querySelectorAll('.dept-btn').forEach(b => b.classList.remove('bg-pink-50', 'text-pink-600'));
            if (btnEl) btnEl.classList.add('bg-pink-50', 'text-pink-600');
        }

        const list = document.getElementById('employeeListGrid');
        const loader = document.getElementById('attendeeLoading');
        const placeholder = document.getElementById('employeeListPlaceholder');
        const selectAllWrap = document.getElementById('selectAllContainer');

        placeholder.classList.add('hidden');
        loader.classList.remove('hidden');
        selectAllWrap.classList.add('hidden');
        list.innerHTML = '';
        currentDeptEmployees = [];

        fetch(`actions/get_employees_by_dept.php?department=${encodeURIComponent(deptName)}`)
            .then(res => res.json())
            .then(data => {
                loader.classList.add('hidden');
                if (data.error) {
                    list.innerHTML = `<p class="text-red-500 text-xs p-4">${data.error}</p>`;
                    return;
                }

                currentDeptEmployees = data;
                selectAllWrap.classList.remove('hidden');
                updateSelectAllState();

                data.forEach(emp => {
                    empNameMap[emp.id] = emp.name; // Cache name
                    const isSel = selectedDeptEmployees.has(String(emp.id));
                    const isBooked = bookedEmployees.includes(String(emp.id));

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.disabled = isBooked;

                    let btnClasses = `flex items-center p-2 rounded-lg border text-left transition-all employee-picker-btn `;
                    if (isBooked) {
                        btnClasses += `border-gray-100 bg-gray-50 opacity-60 cursor-not-allowed`;
                    } else if (isSel) {
                        btnClasses += `border-pink-500 bg-pink-50`;
                    } else {
                        btnClasses += `border-gray-100 hover:bg-white hover:border-pink-200 hover:shadow-sm`;
                    }

                    btn.className = btnClasses;
                    btn.setAttribute('data-emp-id', emp.id);
                    if (!isBooked) {
                        btn.onclick = () => toggleEmployee(String(emp.id), btn);
                    }

                    btn.innerHTML = `
<div
    class="w-8 h-8 rounded-full ${isBooked ? 'bg-gray-300' : 'bg-gray-200'} flex items-center justify-center text-xs font-bold text-gray-500 mr-2 shrink-0">
    ${emp.name.charAt(0)}
</div>
<div class="min-w-0 flex-1">
    <p class="text-xs font-bold ${isBooked ? 'text-gray-400' : 'text-gray-700'} truncate text-name">${emp.name}</p>
    <p class="text-[10px] text-gray-400 truncate">${isBooked ? '<span class="text-orange-500 font-bold">Unavailable</span>' : 'ID: ' + emp.id}</p>
</div>
${isSel && !isBooked ? '<i class="fa-solid fa-check text-pink-500 ml-auto text-xs"></i>' : ''}
`;
                    // If it was selected but now is booked, we should probably remove it from selection?
                    // Or just keep it but disable edit. User said "she should not be clickable".
                    if (isSel && isBooked) {
                        // Optional: remove from selection?
                        // selectedDeptEmployees.delete(String(emp.id));
                    }

                    list.appendChild(btn);
                });
            })
            .catch(err => {
                loader.classList.add('hidden');
                console.error(err);
            });
    }

    function updateSelectAllState() {
        const checkbox = document.getElementById('deptSelectAll');
        if (!currentDeptEmployees.length) {
            checkbox.checked = false;
            return;
        }
        const allSelected = currentDeptEmployees.every(emp => selectedDeptEmployees.has(String(emp.id)));
        checkbox.checked = allSelected;
    }

    function toggleDeptSelectAll() {
        const checkbox = document.getElementById('deptSelectAll');
        const shouldSelect = !checkbox.checked;

        currentDeptEmployees.forEach(emp => {
            const id = String(emp.id);
            const isBooked = bookedEmployees.includes(id);
            if (shouldSelect) {
                if (!isBooked) selectedDeptEmployees.add(id);
            } else {
                selectedDeptEmployees.delete(id);
            }
        });

        // Sync UI for current list
        document.querySelectorAll('.employee-picker-btn').forEach(btn => {
            const id = btn.getAttribute('data-emp-id');
            const isSel = selectedDeptEmployees.has(id);

            if (isSel) {
                btn.classList.add('border-pink-500', 'bg-pink-50');
                btn.classList.remove('border-gray-100');
                if (!btn.querySelector('.fa-check'))
                    btn.insertAdjacentHTML('beforeend', '<i class="fa-solid fa-check text-pink-500 ml-auto text-xs"></i>');
            } else {
                btn.classList.remove('border-pink-500', 'bg-pink-50');
                btn.classList.add('border-gray-100');
                const check = btn.querySelector('.fa-check');
                if (check) check.remove();
            }
        });

        checkbox.checked = shouldSelect;
        renderSelectionCount();
        renderReviewList();
    }

    function toggleEmployee(id, btn) {
        if (bookedEmployees.includes(id)) return; // Double check

        if (selectedDeptEmployees.has(id)) {
            selectedDeptEmployees.delete(id);
            btn.classList.remove('border-pink-500', 'bg-pink-50');
            btn.classList.add('border-gray-100');
            const icon = btn.querySelector('.fa-check');
            if (icon) icon.remove();
        } else {
            selectedDeptEmployees.add(id);
            btn.classList.remove('border-gray-100');
            btn.classList.add('border-pink-500', 'bg-pink-50');
            // Append Check
            if (!btn.querySelector('.fa-check'))
                btn.insertAdjacentHTML('beforeend', '<i class="fa-solid fa-check text-pink-500 ml-auto text-xs"></i>');
        }
        renderSelectionCount();
        renderReviewList();
        updateSelectAllState();
    }

    function addCustomAttendee() {
        const inp = document.getElementById('customAttendeeInput');
        const val = inp.value.trim();
        if (val) {
            selectedCustomGuests.add(val);
            renderSelectionCount();
            renderReviewList();
            inp.value = '';
        }
    }

    function renderSelectionCount() {
        const hasCreator = currentUserId ? 1 : 0;
        const count = selectedDeptEmployees.size + selectedCustomGuests.size + (selectedDeptEmployees.has(currentUserId) ? 0 : hasCreator);

        // Adjust display count to ensure host is counted if not already in selectedDeptEmployees
        let displaySize = selectedDeptEmployees.size;
        if (currentUserId && !selectedDeptEmployees.has(currentUserId)) displaySize++;

        document.getElementById('totalSelectedCount').textContent = count;
        document.getElementById('selectedEmpBadge').textContent = displaySize;
        document.getElementById('selectedGuestBadge').textContent = selectedCustomGuests.size;
    }

    function renderReviewList() {
        const empListEl = document.getElementById('reviewEmpList');
        const guestListEl = document.getElementById('reviewGuestList');

        // Employees
        if (selectedDeptEmployees.size === 0 && !currentUserId) {
            empListEl.innerHTML = '<p class="text-[10px] text-gray-400 italic text-center py-2">No employees selected</p>';
        } else {
            empListEl.innerHTML = '';

            // 1. Show Creator (Always first and not removable)
            if (currentUserId) {
                const creatorName = empNameMap[currentUserId] || 'You (Host)';
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between gap-2 p-1.5 bg-pink-50/50 rounded-lg border border-pink-100 group shadow-sm';
                div.innerHTML = `
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <span class="text-[10px] font-bold text-pink-700 truncate">${creatorName}</span>
                        <span class="text-[8px] px-1 bg-pink-100 text-pink-600 rounded font-bold uppercase">Host</span>
                    </div>
                `;
                empListEl.appendChild(div);
            }

            // 2. Show Selected Employees
            selectedDeptEmployees.forEach(id => {
                if (id === currentUserId) return; // Already shown as host
                const name = empNameMap[id] || 'Employee ' + id;
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between gap-2 p-1.5 bg-white rounded-lg border border-gray-100 group hover:border-pink-200 transition-colors shadow-sm';
                div.innerHTML = `
                    <span class="text-[10px] font-bold text-gray-700 truncate flex-1">${name}</span>
                    <button onclick="removeAttendeeFromReview('${id}', 'emp')" class="text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all">
                        <i class="fa-solid fa-circle-minus"></i>
                    </button>
                `;
                empListEl.appendChild(div);
            });
        }

        // Guests
        if (selectedCustomGuests.size === 0) {
            guestListEl.innerHTML = '<p class="text-[10px] text-gray-400 italic text-center py-2">No guests added</p>';
        } else {
            guestListEl.innerHTML = '';
            selectedCustomGuests.forEach(name => {
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between gap-2 p-1.5 bg-white rounded-lg border border-gray-100 group hover:border-pink-200 transition-colors shadow-sm';
                div.innerHTML = `
                    <span class="text-[10px] font-bold text-gray-600 truncate flex-1">${name}</span>
                    <button onclick="removeAttendeeFromReview('${name}', 'guest')" class="text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all">
                        <i class="fa-solid fa-circle-minus"></i>
                    </button>
                `;
                guestListEl.appendChild(div);
            });
        }
    }

    function removeAttendeeFromReview(val, type) {
        if (type === 'emp') {
            selectedDeptEmployees.delete(val);
            // Update UI grid if active
            const btn = document.querySelector(`.employee-picker-btn[data-emp-id="${val}"]`);
            if (btn) {
                btn.classList.remove('border-pink-500', 'bg-pink-50');
                btn.classList.add('border-gray-100');
                const check = btn.querySelector('.fa-check');
                if (check) check.remove();
            }
            updateSelectAllState();
        } else {
            selectedCustomGuests.delete(val);
        }
        renderSelectionCount();
        renderReviewList();
    }

    function toggleReviewSection(listId, chevronId) {
        const list = document.getElementById(listId);
        const chevron = document.getElementById(chevronId);

        if (list.classList.contains('hidden')) {
            list.classList.remove('hidden');
            chevron.style.transform = 'rotate(0deg)';
        } else {
            list.classList.add('hidden');
            chevron.style.transform = 'rotate(-90deg)';
        }
    }

    function clearAllAttendees() {
        selectedDeptEmployees.clear();
        selectedCustomGuests.clear();
        renderSelectionCount();
        renderReviewList();
        // Refresh grid if open?
        const activeDeptBtn = document.querySelector('.dept-btn.bg-pink-50');
        if (activeDeptBtn) activeDeptBtn.click(); // Re-fetch to clear UI checks
    }

    function saveAttendees() {
        // Inject into Main Form
        const container = document.getElementById('hiddenAttendeeInputs');
        container.innerHTML = '';

        let labelText = [];

        // Always include creator (currentUserId)
        const creatorIdInp = document.createElement('input');
        creatorIdInp.type = 'hidden';
        creatorIdInp.name = 'attendees[]';
        creatorIdInp.value = currentUserId;
        container.appendChild(creatorIdInp);

        // Emp IDs
        selectedDeptEmployees.forEach(id => {
            if (id === currentUserId) return; // Skip if already added as creator
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'attendees[]';
            inp.value = id;
            container.appendChild(inp);
        });
        if (selectedDeptEmployees.size > 0) labelText.push(`${selectedDeptEmployees.size} Employee(s)`);

        // Names
        selectedCustomGuests.forEach(name => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'custom_attendees[]';
            inp.value = name;
            container.appendChild(inp);
        });
        if (selectedCustomGuests.size > 0) labelText.push(`${selectedCustomGuests.size} Guest(s)`);

        const total = selectedDeptEmployees.size + selectedCustomGuests.size;
        document.getElementById('attendeeCountLabel').textContent = total > 0 ? labelText.join(', ') : 'Select Attendees...';

        closeAttendeeModal();
    }

    function filterDepts(q) {
        q = q.toLowerCase();
        document.querySelectorAll('.dept-btn').forEach(btn => {
            btn.style.display = btn.textContent.toLowerCase().includes(q) ? 'block' : 'none';
        });
    }

    function filterEmployees(q) {
        q = q.toLowerCase();
        const list = document.getElementById('employeeListGrid');
        // Simple client side filter of CURRENTLY loaded list
        Array.from(list.children).forEach(child => {
            child.style.display = child.textContent.toLowerCase().includes(q) ? 'flex' : 'none';
        });
    }

    // --- View Details ---
    function openViewMeetingDetails(id) {
        const modal = document.getElementById('viewMeetingDetailsModal');
        modal.classList.remove('hidden');

        document.getElementById('viewMeetingTitle').textContent = 'Loading...';
        document.getElementById('viewMeetingAgendaList').innerText = '';
        document.getElementById('viewMeetingAttendeesList').innerText = '';

        fetch(`actions/get_schedule_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('viewMeetingTitle').textContent = data.error;
                    return;
                }
                const s = data.schedule || {};
                document.getElementById('viewMeetingTitle').textContent = s.meeting_name;
                document.getElementById('viewMeetingSubtitle').textContent = s.venue || 'No Venue';

                document.getElementById('viewMeetingDate').textContent = s.date_formatted;
                document.getElementById('viewMeetingTime').textContent = `${s.start_formatted} - ${s.end_formatted}`;
                document.getElementById('viewMeetingCreator').textContent = s.creator_name;
                document.getElementById('viewMeetingCategory').textContent = s.category_name || '-';

                // Agenda
                const agList = document.getElementById('viewMeetingAgendaList');
                agList.innerHTML = '';
                if (data.agendas && data.agendas.length > 0) {
                    data.agendas.forEach(txt => {
                        const div = document.createElement('div');
                        div.className = 'flex items-start gap-2 text-sm text-gray-700';
                        div.innerHTML = `<i class="fa-solid fa-check text-[10px] text-pink-500 mt-1"></i> <span>${txt}</span>`;
                        agList.appendChild(div);
                    });
                } else agList.innerHTML = '<span class="text-xs text-gray-400 italic">No agenda items.</span>';

                // Attendees
                const attList = document.getElementById('viewMeetingAttendeesList');
                attList.innerHTML = '';
                const atts = data.attendees || [];
                document.getElementById('viewMeetingAttendeeCount').textContent = atts.length;

                atts.forEach(att => {
                    const sp = document.createElement('span');
                    sp.className = "text-xs px-2 py-1 bg-gray-100 rounded text-gray-700";
                    sp.textContent = att.name;
                    attList.appendChild(sp);
                });
            });
    }

    function deletePlan(id) {
        showConfirmModal(
            'Delete Meeting?',
            'This action cannot be undone.',
            () => {
                const formData = new FormData();
                formData.append('id', id);

                fetch('actions/delete_schedule.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            loadPlannerData(currentMonth, currentYear);
                        } else {
                            alert(res.message || 'Error deleting');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('An error occurred while deleting.');
                    });
            }
        );
    }

    function togglePlannerLayout(isInitial = false) {
        const sidebar = document.getElementById('sidebar');
        const main = document.querySelector('main');
        const toggleBtn = document.getElementById('toggleSidebarBtn');

        if (!isInitial) isPlannerExpanded = !isPlannerExpanded;
        else isPlannerExpanded = true;

        if (isPlannerExpanded) {
            if (sidebar) {
                sidebar.style.transform = 'translateX(-120%)';
                sidebar.style.opacity = '0';
                sidebar.style.pointerEvents = 'none';
            }
            if (main) main.style.marginLeft = '20px';
            toggleBtn.classList.add('bg-pink-500', 'text-white', 'shadow-lg', 'shadow-pink-500/20');
            toggleBtn.classList.remove('bg-gray-50', 'text-gray-400');
            toggleBtn.innerHTML = '<i class="fa-solid fa-bars"></i>';
        } else {
            if (sidebar) {
                sidebar.style.transform = 'translateX(0)';
                sidebar.style.opacity = '1';
                sidebar.style.pointerEvents = 'auto';
            }
            if (main) main.style.marginLeft = '284px';
            toggleBtn.classList.remove('bg-pink-500', 'text-white', 'shadow-lg', 'shadow-pink-500/20');
            toggleBtn.classList.add('bg-gray-50', 'text-gray-400');
            toggleBtn.innerHTML = '<i class="fa-solid fa-bars-staggered"></i>';
        }

        // Re-render grid to show/hide details with slight delay for animation
        setTimeout(() => {
            if (globalPlannerData) renderGrid(globalPlannerData);
        }, 300);
    }

    function openDayMeetingsModal(dateStr, events) {
        const modal = document.getElementById('dayMeetingsModal');
        const title = document.getElementById('dayMeetingsTitle');
        const list = document.getElementById('dayMeetingsList');

        // Format Date
        const d = new Date(dateStr);
        const dateFormatted = d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        title.textContent = dateFormatted;
        list.innerHTML = '';

        events.forEach(e => {
            const item = document.createElement('div');
            item.className = 'p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-white hover:border-pink-200 transition-all cursor-pointer group';
            item.onclick = () => {
                modal.classList.add('hidden');
                openViewMeetingDetails(e.id);
            };

            const isMineBadge = e.is_mine ? '<span class="text-[8px] px-1.5 py-0.5 rounded-full bg-pink-100 text-pink-600 font-bold uppercase">Involved</span>' : '';

            item.innerHTML = `
                <div class="flex justify-between items-start mb-2">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-pink-500">${e.time} - ${e.to_time}</span>
                        ${isMineBadge}
                    </div>
                    <i class="fa-solid fa-chevron-right text-[10px] text-gray-300 group-hover:text-pink-400 transition-colors"></i>
                </div>
                <h5 class="text-sm font-bold text-gray-800 mb-2">${e.title}</h5>
                <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-gray-100">
                    <div class="text-[10px] text-gray-400"><i class="fa-solid fa-location-dot mr-1 text-pink-300"></i> ${e.venue || 'No Venue'}</div>
                    <div class="text-[10px] text-gray-400"><i class="fa-solid fa-user mr-1 text-pink-300"></i> ${e.creator_name}</div>
                </div>
            `;
            list.appendChild(item);
        });

        modal.classList.remove('hidden');
    }

    function openVenueAvailabilityModal() {
        const date = document.getElementById('planDateInput').value;
        if (!date) return;

        const modal = document.getElementById('venueAvailabilityModal');
        modal.classList.remove('hidden');

        const list = document.getElementById('availabilityVenueList');
        list.innerHTML = '<p class="text-sm text-gray-400 italic p-4">Loading schedule...</p>';

        fetch(`actions/get_venue_schedule.php?date=${date}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    list.innerHTML = `<p class="text-xs text-red-500 p-4">${data.error}</p>`;
                    return;
                }

                document.getElementById('availabilityModalDate').textContent = data.date;

                list.innerHTML = '';
                Object.keys(data.venues).forEach(venue => {
                    const meetings = data.venues[venue];
                    const card = document.createElement('div');
                    card.className = 'p-4 rounded-xl border border-gray-100 bg-gray-50/50';

                    let meetingsHtml = '';
                    if (meetings.length === 0) {
                        meetingsHtml = '<p class="text-[10px] text-emerald-600 font-bold uppercase mt-2"><i class="fa-solid fa-circle-check mr-1"></i> Available all day</p>';
                    } else {
                        meetingsHtml = meetings.map(m => `
                            <div class="mt-2 p-2 bg-white rounded-lg border border-gray-100 shadow-sm">
                                <div class="flex justify-between items-start gap-2">
                                    <span class="text-[10px] font-bold text-gray-800 line-clamp-1">${m.title}</span>
                                    <span class="text-[10px] font-bold text-pink-500 whitespace-nowrap">${m.start} - ${m.end}</span>
                                </div>
                                <p class="text-[9px] text-gray-400 mt-1">Facilitator: ${m.facilitator}</p>
                            </div>
                        `).join('');
                    }

                    card.innerHTML = `
                        <h5 class="text-xs font-bold text-gray-700 border-b border-gray-200 pb-2 mb-2 uppercase tracking-wide flex items-center justify-between">
                            ${venue}
                            <span class="text-[8px] px-1.5 py-0.5 rounded bg-gray-200 text-gray-500">${meetings.length} Booked</span>
                        </h5>
                        ${meetingsHtml}
                    `;
                    list.appendChild(card);
                });
            });
    }

</script>

<!-- Venue Availability Modal -->
<div id="venueAvailabilityModal"
    class="hidden fixed inset-0 bg-black/50 z-[110] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-2xl h-[70vh] shadow-2xl flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Room Availability</h3>
                <p class="text-[10px] text-gray-500 mt-0.5 uppercase tracking-widest font-bold">Schedule for <span
                        id="availabilityModalDate" class="text-pink-500">...</span></p>
            </div>
            <button onclick="document.getElementById('venueAvailabilityModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
            <div id="availabilityVenueList" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Populated by JS -->
            </div>
        </div>
        <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex justify-end shrink-0">
            <button onclick="document.getElementById('venueAvailabilityModal').classList.add('hidden')"
                class="px-4 py-2 rounded-lg bg-gray-800 text-white text-xs font-bold hover:bg-black shadow-md transition-all">Close</button>
        </div>
    </div>
</div>

<!-- Day Meetings Modal -->
<div id="dayMeetingsModal"
    class="hidden fixed inset-0 bg-black/50 z-[120] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[70vh] overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <div>
                <h3 class="text-lg font-bold text-gray-800" id="dayMeetingsTitle">Day Meetings</h3>
                <p class="text-[10px] text-gray-500 mt-0.5 uppercase tracking-widest font-bold">Planned Schedule</p>
            </div>
            <button onclick="document.getElementById('dayMeetingsModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
            <div id="dayMeetingsList" class="space-y-3">
                <!-- Populated by JS -->
            </div>
        </div>
        <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex justify-end shrink-0">
            <button onclick="document.getElementById('dayMeetingsModal').classList.add('hidden')"
                class="px-4 py-2 rounded-lg bg-gray-800 text-white text-xs font-bold hover:bg-black shadow-md transition-all">Close</button>
        </div>
    </div>
</div>