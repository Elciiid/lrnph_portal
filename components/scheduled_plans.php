<div class="bg-white p-3 rounded-[20px] shadow-sm flex-1 flex flex-col h-full min-h-0 overflow-hidden">
    <div class="flex justify-between items-center mb-2">
        <h3 class="text-[1rem] font-semibold">Scheduled Plans</h3>
    </div>

    <div class="flex-1 min-h-0 flex flex-col gap-2 overflow-y-auto justify-start pb-2 no-scrollbar custom-scrollbar">
        <?php if (empty($scheduledPosts)): ?>
            <div class="flex flex-col items-center justify-center h-full text-center text-[#888] py-4">
                <i class="fa-regular fa-calendar-xmark text-2xl mb-2 opacity-50"></i>
                <p class="text-[0.8rem] font-medium">No scheduled plans</p>
            </div>
        <?php else: ?>
            <?php foreach ($scheduledPosts as $sch): ?>
                <div onclick="openViewModal('<?php echo htmlspecialchars(json_encode($sch)); ?>')"
                    class="flex items-start gap-3 pb-2 border-b border-[#f8f9fa] last:border-b-0 shrink-0 group relative pr-2 cursor-pointer hover:bg-gray-50 rounded-lg p-2 transition-colors">
                    <!-- Image Container -->
                    <img src="<?php echo !empty($sch['image']) ? $sch['image'] : 'assets/lrn-logo.jpg'; ?>"
                        class="w-12 h-12 rounded-lg bg-[#ccc] object-cover shrink-0">

                    <!-- Content Container -->
                    <!-- Content Container -->
                    <div class="flex-1 min-w-0 pt-0.5">
                        <!-- Title & Subtitle (Block Flow) -->
                        <div class="flex flex-col pr-0 sm:pr-24">
                            <h5 class="text-[0.85rem] font-semibold leading-none truncate text-[#1a1a1a]">
                                <?php echo $sch['title']; ?>
                                <span class="text-[0.7rem] text-gray-400 font-normal ml-1">by
                                    <?php echo $sch['creator']; ?></span>
                            </h5>
                            <?php if (!empty($sch['account'])): ?>
                                <div class="text-[0.7rem] text-[#aaa] leading-none mt-1 line-clamp-1">
                                    <?php echo $sch['account']; // Subtitle ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Absolute Date & Time (Hidden or adjusted on small mobile) -->
                        <?php
                        // 'platform' column has To Time
                        $toTime = !empty($sch['platform']) ? date('h:i A', strtotime($sch['platform'])) : '';
                        $timeDisplay = $sch['time'] . ($toTime ? ' - ' . $toTime : '');
                        ?>
                        <div
                            class="sm:absolute sm:top-2 sm:right-2 text-[0.65rem] text-[#888] font-medium whitespace-nowrap sm:text-right mt-2 sm:mt-0 flex sm:block items-center justify-between border-t sm:border-t-0 border-gray-50 pt-1.5 sm:pt-0">
                            <span class="block text-pink-600 font-bold"><?php echo $sch['date']; ?></span>
                            <span><?php echo $timeDisplay; ?></span>
                        </div>

                        <?php if (!empty($sch['description'])): ?>
                            <div class="text-[0.7rem] text-gray-500 line-clamp-2 mb-1">
                                <?php echo $sch['description']; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($sch['attendees'])): ?>
                            <div class="flex items-center gap-1.5 mt-1.5">
                                <i class="fa-solid fa-user-group text-[0.6rem] text-gray-400"></i>
                                <span class="text-[0.65rem] text-gray-500 font-medium truncate">
                                    <?php
                                    echo implode(', ', array_slice($sch['attendees'], 0, 2));
                                    if (count($sch['attendees']) > 2)
                                        echo ' +' . (count($sch['attendees']) - 2) . ' others';
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <button onclick="document.getElementById('viewAllScheduleModal').classList.remove('hidden')"
        class="w-full bg-[#1a1a1a] text-white p-3.5 rounded-xl border-none font-semibold text-[0.9rem] cursor-pointer mt-2.5 transition-colors duration-200 hover:bg-[#333]">View
        all Plans</button>


    <!-- View Schedule Modal (Unified Design) -->
    <div id="viewPlanDetailsModal"
        class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity bg-black/50">
        <div
            class="bg-white rounded-2xl w-full max-w-lg shadow-2xl transform transition-all scale-100 flex flex-col max-h-[90vh] overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-start bg-gray-50/50 shrink-0">
                <div class="pr-6">
                    <h3 class="text-xl font-bold text-gray-800 leading-tight" id="viewPlanTitle">Meeting Title</h3>
                    <p class="text-sm text-pink-600 font-medium mt-1" id="viewPlanSubtitle">Subtitle</p>
                </div>
                <button onclick="document.getElementById('viewPlanDetailsModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="p-6 overflow-y-auto custom-scrollbar space-y-5">

                <!-- Image Display (Specific to Scheduled Plans) -->
                <img id="viewPlanImage" src="" class="w-full h-48 object-cover rounded-xl shadow-sm hidden">

                <!-- Meta Info -->
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <div
                            class="w-8 h-8 rounded-lg bg-pink-50 text-pink-500 flex items-center justify-center shrink-0">
                            <i class="fa-regular fa-calendar"></i>
                        </div>
                        <div>
                            <div class="font-bold text-gray-800" id="viewPlanDate">Date</div>
                            <div class="text-xs text-gray-400">Reservation Date</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <div
                            class="w-8 h-8 rounded-lg bg-orange-50 text-orange-500 flex items-center justify-center shrink-0">
                            <i class="fa-regular fa-clock"></i>
                        </div>
                        <div>
                            <div class="font-bold text-gray-800" id="viewPlanTime">Time</div>
                            <div class="text-xs text-gray-400">Duration</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <div
                            class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center shrink-0">
                            <i class="fa-regular fa-user"></i>
                        </div>
                        <div>
                            <div class="font-bold text-gray-800" id="viewPlanCreator">Creator</div>
                            <div class="text-xs text-gray-400">Organizer</div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Agenda / Description</h4>
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap" id="viewPlanDescription">
                        No description provided.
                    </p>
                </div>

                <!-- Attendees -->
                <div>
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3 flex items-center gap-2">
                        Attendees <span class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded text-[10px]"
                            id="viewPlanAttendeeCount">0</span>
                    </h4>
                    <div class="flex flex-wrap gap-2" id="viewPlanAttendeesList">
                        <!-- Attendees injected here -->
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-between shrink-0">
                <button id="viewPlanDeleteBtn"
                    onclick="const id = this.getAttribute('data-id'); showConfirmModal('Delete Plan?', 'Are you sure you want to remove this scheduled plan?', () => { window.location.href='actions/delete_schedule.php?id=' + id; });"
                    class="hidden mr-auto px-5 py-2.5 rounded-xl bg-red-50 text-red-500 font-bold hover:bg-red-100 transition-all text-sm group">
                    <i class="fa-regular fa-trash-can mr-2 group-hover:animate-pulse"></i> Delete
                </button>
                <button onclick="document.getElementById('viewPlanDetailsModal').classList.add('hidden')"
                    class="px-5 py-2.5 rounded-xl bg-white border border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 hover:text-gray-800 transition-colors text-sm shadow-sm">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function openViewModal(schJson) {
            const sch = JSON.parse(schJson);

            // Populate Fields
            document.getElementById('viewPlanTitle').textContent = sch.title;
            document.getElementById('viewPlanSubtitle').textContent = sch.account || ''; // Using account as subtitle
            document.getElementById('viewPlanDate').textContent = sch.date;
            document.getElementById('viewPlanTime').textContent = sch.time;
            document.getElementById('viewPlanCreator').textContent = sch.creator;
            document.getElementById('viewPlanDescription').textContent = sch.description || 'No detailed agenda provided.';

            // Image Handler
            const imgEl = document.getElementById('viewPlanImage');
            if (sch.image && sch.image !== 'assets/lrn-logo.jpg' && sch.image.trim() !== '') {
                imgEl.src = sch.image;
                imgEl.classList.remove('hidden');
            } else {
                imgEl.classList.add('hidden');
            }

            // Attendees
            const list = document.getElementById('viewPlanAttendeesList');
            list.innerHTML = '';

            const attendees = sch.attendees || [];
            document.getElementById('viewPlanAttendeeCount').textContent = attendees.length;

            if (attendees.length === 0) {
                list.innerHTML = '<span class="text-sm text-gray-400 italic">No attendees added.</span>';
            } else {
                attendees.forEach(name => {
                    const span = document.createElement('span');
                    span.className = 'text-xs px-3 py-1.5 rounded-full bg-pink-50 border border-pink-100 text-pink-700 font-bold shadow-sm flex items-center gap-2';
                    span.innerHTML = `<i class="fa-solid fa-user text-[10px] opacity-50"></i> ${name}`;
                    list.appendChild(span);
                });
            }

            // Delete Button
            const delBtn = document.getElementById('viewPlanDeleteBtn');
            if (sch.is_creator) {
                delBtn.setAttribute('data-id', sch.id);
                delBtn.classList.remove('hidden');
            } else {
                delBtn.classList.add('hidden');
            }

            // Show Modal
            document.getElementById('viewPlanDetailsModal').classList.remove('hidden');
        }

        // Toggle delete button visibility on card click (Maintains legacy card logic)
        function toggleDeleteButton(card, event) {
            if (event.target.closest('.delete-btn')) return;
            const deleteBtn = card.querySelector('.delete-btn');
            const isVisible = deleteBtn.classList.contains('opacity-100');
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.classList.remove('opacity-100', 'visible');
                btn.classList.add('opacity-0', 'invisible');
            });
            if (!isVisible) {
                deleteBtn.classList.remove('opacity-0', 'invisible');
                deleteBtn.classList.add('opacity-100', 'visible');
            }
        }

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.bg-white.border.border-gray-100')) {
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.classList.remove('opacity-100', 'visible');
                    btn.classList.add('opacity-0', 'invisible');
                });
            }
        });
    </script>

    <!-- View All Schedule Modal -->
    <div id="viewAllScheduleModal" class="hidden fixed inset-0 z-[9999]">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
            onclick="document.getElementById('viewAllScheduleModal').classList.add('hidden')"></div>

        <!-- Modal Content -->
        <div
            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl w-full max-w-4xl max-h-[85vh] shadow-2xl overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="flex justify-between items-center p-6 border-b border-gray-100">
                <h2 class="text-2xl font-bold text-[#1a1a1a]">All Scheduled Posts</h2>
                <button onclick="document.getElementById('viewAllScheduleModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <!-- Modal Body - Scrollable Grid -->
            <div class="flex-1 overflow-y-auto p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if (!empty($scheduledPosts)): ?>
                        <?php foreach ($scheduledPosts as $sch): ?>
                            <div class="bg-white border border-gray-100 rounded-xl p-4 hover:shadow-lg transition-shadow group relative"
                                onclick="toggleDeleteButton(this, event)">
                                <?php if ($sch['is_creator']): ?>
                                    <!-- Delete Button (shows on click) -->
                                    <button
                                        onclick="event.stopPropagation(); showConfirmModal('Delete Post?', 'Remove this scheduled post permanently?', () => { window.location.href='actions/delete_schedule.php?id=<?php echo $sch['id']; ?>'; });"
                                        class="delete-btn absolute top-2 left-2 z-10 bg-red-500 text-white w-8 h-8 rounded-full flex items-center justify-center opacity-0 invisible transition-all shadow-lg hover:bg-red-600">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Card Content -->
                                <div class="cursor-pointer">
                                    <!-- Image -->
                                    <div class="relative mb-3 rounded-lg overflow-hidden h-40 bg-gray-100">
                                        <img src="<?php echo $sch['image']; ?>"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                        <div
                                            class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm px-2 py-1 rounded-md text-xs font-semibold text-gray-600">
                                            <?php echo $sch['time']; ?>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span
                                                class="inline-flex items-center gap-1.5 text-xs font-bold text-pink-600 bg-pink-50 px-2 py-1 rounded-md">
                                                <i class="fa-brands fa-instagram"></i>
                                                <?php echo $sch['platform']; ?>
                                            </span>
                                            <span class="text-xs text-gray-500 font-medium"><?php echo $sch['date']; ?></span>
                                        </div>

                                        <h3 class="font-semibold text-sm text-[#1a1a1a] line-clamp-2 leading-tight">
                                            <?php echo $sch['title']; ?>
                                            <span class="text-[10px] text-gray-400 font-normal ml-1">by
                                                <?php echo $sch['creator']; ?></span>
                                        </h3>

                                        <p class="text-xs text-gray-400 mb-2"><?php echo $sch['account']; ?></p>

                                        <p class="text-xs text-gray-600 line-clamp-2 leading-relaxed">
                                            <?php echo $sch['description']; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full flex flex-col items-center justify-center py-12 text-gray-400">
                            <i class="fa-regular fa-calendar-xmark text-4xl mb-3 opacity-50"></i>
                            <p class="text-sm font-medium">No scheduled posts available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="border-t border-gray-100 p-4 flex justify-end gap-3">
                <button onclick="document.getElementById('viewAllScheduleModal').classList.add('hidden')"
                    class="px-6 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors">
                    Close
                </button>
                <button onclick="window.location.href='admin.php?page=planner'"
                    class="bg-pink-500 text-white px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-pink-600 transition-colors shadow-lg shadow-pink-500/30">
                    <i class="fa-solid fa-plus mr-2"></i> Go to Planner
                </button>
            </div>
        </div>
    </div>
</div>