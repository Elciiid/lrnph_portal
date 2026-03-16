<!-- Help / User Guide Modal -->
<div id="helpModal"
    class="hidden fixed inset-0 bg-black/60 z-[200] flex items-center justify-center p-4 backdrop-blur-sm transition-all duration-300">
    <div
        class="bg-white rounded-[32px] w-[900px] h-[600px] max-w-[95vw] max-h-[90vh] shadow-2xl overflow-hidden flex flex-col transform transition-all scale-100 border border-white/20">
        <!-- Header -->
        <div
            class="p-8 border-b border-gray-50 flex justify-between items-center bg-gradient-to-r from-pink-50/50 to-white">
            <div class="flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-2xl bg-pink-500 text-white flex items-center justify-center shadow-lg shadow-pink-200">
                    <i class="fa-solid fa-circle-question text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">User Guide</h2>
                    <p class="text-sm text-gray-400 font-medium">Everything you need to know about CentralPoint</p>
                </div>
            </div>
            <button onclick="toggleHelpModal()"
                class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Content Area -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Navigation Sidebar -->
            <div class="w-72 bg-gray-50/50 px-8 pt-2 pb-8 flex flex-col justify-start gap-3 border-r border-gray-100">
                <button onclick="switchHelpTab('getting-started')"
                    class="help-tab-btn active px-5 py-4 rounded-2xl text-sm font-bold flex items-center gap-3 transition-all">
                    <i class="fa-solid fa-rocket w-5 text-center"></i>
                    <span>Getting Started</span>
                </button>
                <button onclick="switchHelpTab('planner')"
                    class="help-tab-btn px-5 py-4 rounded-2xl text-sm font-semibold flex items-center gap-3 transition-all text-gray-500 hover:bg-white hover:text-pink-500">
                    <i class="fa-solid fa-calendar-days w-5 text-center"></i>
                    <span>Planner Hub</span>
                </button>
                <button onclick="switchHelpTab('announcements')"
                    class="help-tab-btn px-5 py-4 rounded-2xl text-sm font-semibold flex items-center gap-3 transition-all text-gray-500 hover:bg-white hover:text-pink-500">
                    <i class="fa-solid fa-bullhorn w-5 text-center"></i>
                    <span>Live News</span>
                </button>
                <button onclick="switchHelpTab('user-mgmt')"
                    class="help-tab-btn px-5 py-4 rounded-2xl text-sm font-semibold flex items-center gap-3 transition-all text-gray-500 hover:bg-white hover:text-pink-500">
                    <i class="fa-solid fa-users-gear w-5 text-center"></i>
                    <span>User Management</span>
                </button>
                <button onclick="switchHelpTab('content')"
                    class="help-tab-btn px-5 py-4 rounded-2xl text-sm font-semibold flex items-center gap-3 transition-all text-gray-500 hover:bg-white hover:text-pink-500">
                    <i class="fa-solid fa-pen-nib w-5 text-center"></i>
                    <span>Content Area</span>
                </button>
                <button onclick="switchHelpTab('permissions')"
                    class="help-tab-btn px-5 py-4 rounded-2xl text-sm font-semibold flex items-center gap-3 transition-all text-gray-500 hover:bg-white hover:text-pink-500">
                    <i class="fa-solid fa-shield-halved w-5 text-center"></i>
                    <span>Roles & Access</span>
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto p-6 custom-scrollbar bg-white">
                <!-- Getting Started Tab -->
                <div id="help-getting-started"
                    class="help-tab-content space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Welcome to CentralPoint Portal</h3>
                        <p class="text-gray-500 leading-relaxed mb-6">CentralPoint is the heart of La Rose Noire
                            communications. This portal is designed to streamline how you manage meetings,
                            announcements, and applications.</p>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 rounded-[24px] bg-pink-50/50 border border-pink-100 flex flex-col gap-3">
                                <div
                                    class="w-10 h-10 rounded-xl bg-white text-pink-500 flex items-center justify-center shadow-sm">
                                    <i class="fa-solid fa-house-chimney text-sm"></i>
                                </div>
                                <h4 class="font-bold text-gray-800 text-sm">Dashboard Overview</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">See your upcoming meetings, latest
                                    announcements, and portal statistics at a glance.</p>
                            </div>
                            <div
                                class="p-4 rounded-[24px] bg-purple-50/50 border border-purple-100 flex flex-col gap-3">
                                <div
                                    class="w-10 h-10 rounded-xl bg-white text-purple-500 flex items-center justify-center shadow-sm">
                                    <i class="fa-solid fa-bell text-sm"></i>
                                </div>
                                <h4 class="font-bold text-gray-800 text-sm">Real-time Updates</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Get notified about new announcements
                                    and upcoming meeting conflicts instantly.</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-50">
                        <h4 class="font-bold text-gray-800 mb-4">Quick Navigation</h4>
                        <ul class="space-y-3">
                            <li class="flex items-start gap-3">
                                <div
                                    class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mt-0.5 shrink-0 text-[10px]">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <p class="text-sm text-gray-500"><span class="font-bold text-gray-700">Sidebar:</span>
                                    Use the left sidebar to switch between Dashboard, Planner, and Management tools.</p>
                            </li>
                            <li class="flex items-start gap-3">
                                <div
                                    class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mt-0.5 shrink-0 text-[10px]">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <p class="text-sm text-gray-500"><span class="font-bold text-gray-700">Search:</span>
                                    Type in any search bar to filter meetings or employees by name / department.</p>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Planner Tab -->
                <div id="help-planner"
                    class="help-tab-content hidden space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Managing Your Schedule</h3>
                        <p class="text-gray-500 leading-relaxed">The Planner is more than just a calendar. It is a
                            collaborative tool to coordinate with departments.</p>
                    </div>

                    <div class="space-y-6">
                        <div class="flex gap-5 p-5 rounded-2xl bg-gray-50 border border-gray-100">
                            <div
                                class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-sm text-pink-500">
                                <i class="fa-solid fa-calendar-plus text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">Creating Meetings</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Click any date on the calendar to open
                                    the "New Plan" modal. You can set the title, time, and even upload a banner image
                                    for the meeting.</p>
                            </div>
                        </div>

                        <div class="flex gap-5 p-5 rounded-2xl bg-gray-50 border border-gray-100">
                            <div
                                class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-sm text-pink-500">
                                <i class="fa-solid fa-user-group text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">Attendee Management</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Manage who attends by selecting
                                    specific departments and checking individual employees. You can manage attendees
                                    even after a meeting is created.</p>
                            </div>
                        </div>

                        <div class="flex gap-5 p-5 rounded-2xl bg-gray-50 border border-gray-100">
                            <div
                                class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-sm text-pink-500">
                                <i class="fa-solid fa-mouse-pointer text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">Scrolling Calendar</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Each day in the calendar is scrollable.
                                    Simply hover and scroll to see the full list of meetings for busy days without
                                    leaving the page.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Announcements Tab -->
                <div id="help-announcements"
                    class="help-tab-content hidden space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Live News & Updates</h3>
                        <p class="text-gray-500 leading-relaxed mb-6">The Live News feed keeps the entire organization
                            synchronized with the latest company bulletins and IT advisories.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div
                            class="p-6 rounded-[28px] bg-gradient-to-br from-pink-50/50 to-white border border-pink-100/50 shadow-sm flex items-center gap-6">
                            <div
                                class="w-14 h-14 rounded-2xl bg-white text-pink-500 flex items-center justify-center shadow-md shadow-pink-100 shrink-0">
                                <i class="fa-solid fa-list-check text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">Recent Feed</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">The Dashboard displays the 5 most
                                    recent announcements. This ensures you never miss a critical update upon logging in.
                                </p>
                            </div>
                        </div>

                        <div
                            class="p-6 rounded-[28px] bg-gradient-to-br from-white to-gray-50/50 border border-gray-100 shadow-sm flex items-center gap-6">
                            <div
                                class="w-14 h-14 rounded-2xl bg-white text-gray-400 flex items-center justify-center shadow-md shadow-gray-100 shrink-0">
                                <i class="fa-solid fa-book-open text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">Full Content View</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Click on any announcement title to open
                                    a dedicated reader view. Use this to review long-form company policies or detailed
                                    IT change logs.</p>
                            </div>
                        </div>

                        <div
                            class="p-6 rounded-[28px] bg-gradient-to-br from-blue-50/30 to-white border border-blue-100/50 shadow-sm flex items-center gap-6">
                            <div
                                class="w-14 h-14 rounded-2xl bg-white text-blue-500 flex items-center justify-center shadow-md shadow-blue-100 shrink-0">
                                <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-1">Stay Informed</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Real-time indicators will alert you
                                    when a new global message is published by the administration team.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Management Tab -->
                <div id="help-user-mgmt"
                    class="help-tab-content hidden space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Managing Portal Access</h3>
                        <p class="text-gray-500 leading-relaxed">Control who can access different areas of the portal
                            through a sleek department-based interface.</p>
                    </div>

                    <div class="space-y-4">
                        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm flex items-start gap-4">
                            <div
                                class="w-10 h-10 rounded-xl bg-pink-50 text-pink-500 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-sitemap"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1">Department Folders</h4>
                                <p class="text-xs text-gray-500">Users are grouped by their official department. Click
                                    any folder to see the list of active portal users in that group.</p>
                            </div>
                        </div>

                        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm flex items-start gap-4">
                            <div
                                class="w-10 h-10 rounded-xl bg-pink-50 text-pink-500 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-user-lock"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1">Granular Permissions</h4>
                                <p class="text-xs text-gray-500">Grant specific access to Content, Announcements, or
                                    User Management. Dashboard and Planner access are enabled for all users by default.
                                </p>
                            </div>
                        </div>

                        <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm flex items-start gap-4">
                            <div
                                class="w-10 h-10 rounded-xl bg-pink-50 text-pink-500 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-toggle-on"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1">Instant Activation</h4>
                                <p class="text-xs text-gray-500">Use the toggle switches to instantly enable or disable
                                    a user's portal access without deleting their profile.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content Area Tab -->
                <div id="help-content"
                    class="help-tab-content hidden space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Dynamic Page Content</h3>
                        <p class="text-gray-500 leading-relaxed">The Content Manager allows you to update the visual
                            messages seen by all employees on the main portal.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-5 rounded-[24px] bg-gradient-to-br from-blue-50 to-white border border-blue-100">
                            <h4 class="font-bold text-blue-900 text-sm mb-2">Headline Banners</h4>
                            <p class="text-xs text-blue-800/70">Update the main welcome messages and "Subtitle" texts on
                                the central portal home page.</p>
                        </div>
                        <div class="p-5 rounded-[24px] bg-gradient-to-br from-pink-50 to-white border border-pink-100">
                            <h4 class="font-bold text-pink-900 text-sm mb-2">Side Messages</h4>
                            <p class="text-xs text-pink-800/70">Manage the smaller "Pro Tips" or update messages that
                                appear on the RHS of the public portal.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-200">
                        <i class="fa-solid fa-cloud-arrow-up text-gray-400"></i>
                        <p class="text-[11px] text-gray-500 font-medium">Any changes made here are reflected instantly
                            across all employee workstations without requiring a logout.</p>
                    </div>
                </div>

                <!-- Permissions Tab -->
                <div id="help-permissions"
                    class="help-tab-content hidden space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div class="p-6 rounded-[24px] bg-amber-50 border border-amber-100">
                        <h3 class="text-lg font-bold text-amber-900 mb-2 flex items-center gap-2">
                            <i class="fa-solid fa-lock"></i>
                            Access Control
                        </h3>
                        <p class="text-sm text-amber-800 opacity-80">Some features might be hidden based on your
                            department and role.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-6 pt-4">
                        <div class="space-y-3">
                            <h4 class="font-bold text-gray-800 text-sm">Standard Users</h4>
                            <p class="text-xs text-gray-500">Can view the dashboard, use the planner to schedule
                                meetings, and receive announcements.</p>
                        </div>
                        <div class="space-y-3">
                            <h4 class="font-bold text-gray-800 text-sm text-pink-600">IT & Admin</h4>
                            <p class="text-xs text-gray-500">Full control over Content Management, Announcements
                                publishing, and User Permissions.</p>
                        </div>
                    </div>

                    <div class="p-6 rounded-[24px] bg-blue-50 border border-blue-100 flex items-center gap-4">
                        <i class="fa-solid fa-circle-info text-2xl text-blue-500"></i>
                        <p class="text-xs text-blue-800 leading-relaxed font-medium">If you believe you are missing a
                            menu item that should be visible, please coordinate with your Department Head or contact the
                            IT Service Desk.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-6 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">CentralPoint • 2026</p>
            <button onclick="toggleHelpModal()"
                class="px-8 py-2.5 bg-gray-900 text-white rounded-xl text-sm font-bold hover:bg-gray-800 transition-all shadow-lg shadow-gray-200">
                Got it, thanks!
            </button>
        </div>
    </div>
</div>

<style>
    .help-tab-btn.active {
        background-color: white;
        color: #ec4899;
        /* pink-500 */
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    }

    .help-tab-btn:not(.active):hover {
        background-color: rgba(255, 255, 255, 0.7);
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #fdf2f8;
        /* pink-50 */
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #f472b6;
        /* pink-400 */
        border-radius: 10px;
        border: 1px solid #fdf2f8;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #ec4899;
        /* pink-500 */
    }
</style>

<script>
    function toggleHelpModal() {
        const modal = document.getElementById('helpModal');
        if (modal.classList.contains('hidden')) {
            modal.classList.remove('hidden');
            // Adding a small delay to trigger CSS entry animations if we had them
        } else {
            modal.classList.add('hidden');
        }
    }

    function switchHelpTab(tabId) {
        // Hide all contents
        document.querySelectorAll('.help-tab-content').forEach(c => c.classList.add('hidden'));
        // Deactivate all buttons
        document.querySelectorAll('.help-tab-btn').forEach(b => {
            b.classList.remove('active', 'bg-white', 'shadow-sm', 'text-pink-500');
            b.classList.add('text-gray-500');
        });

        // Show target content
        document.getElementById('help-' + tabId).classList.remove('hidden');

        // Find and activate button
        const btn = Array.from(document.querySelectorAll('.help-tab-btn')).find(b => b.getAttribute('onclick').includes(tabId));
        if (btn) {
            btn.classList.add('active', 'bg-white', 'shadow-sm', 'text-pink-500');
            btn.classList.remove('text-gray-500');
        }
    }

    // Close on backdrop click
    document.getElementById('helpModal').addEventListener('click', function (e) {
        if (e.target === this) toggleHelpModal();
    });
</script>