<?php
// Meeting Notification Component
?>

<!-- Notification Audio -->
<audio id="meetingNotifySound" preload="auto">
    <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
</audio>

<!-- Notification Toast Container -->
<div id="meetingToast"
    class="fixed bottom-6 right-6 z-[10000] transform translate-x-full opacity-0 transition-all duration-500 ease-out pointer-events-none">
    <div
        class="bg-white rounded-[24px] shadow-[0_20px_50px_rgba(0,0,0,0.15)] border border-gray-100 p-5 w-80 flex gap-4 pointer-events-auto relative overflow-hidden group">
        <!-- Progress Bar Background -->
        <div class="absolute bottom-0 left-0 h-1 bg-gradient-to-r from-pink-500 to-rose-400 w-full origin-left transition-transform duration-[60000ms] ease-linear"
            id="toastProgress"></div>

        <!-- Left Icon -->
        <div
            class="shrink-0 w-12 h-12 bg-pink-50 rounded-2xl flex items-center justify-center text-pink-500 shadow-sm border border-pink-100">
            <i class="fa-solid fa-video text-xl animate-pulse"></i>
        </div>

        <!-- Content -->
        <div class="flex-1">
            <div class="flex justify-between items-start mb-1">
                <span class="text-[10px] font-extrabold text-pink-600 uppercase tracking-widest">Meeting Start
                    Soon</span>
                <button onclick="dismissMeetingToast()" class="text-gray-300 hover:text-gray-500 transition-colors">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>
            <h4 id="meetingToastTitle" class="text-sm font-bold text-gray-900 leading-tight mb-1">Weekly Sync Meeting
            </h4>
            <p id="meetingToastTime" class="text-xs text-gray-500 font-medium">Starting in 1 minute</p>

            <button onclick="dismissMeetingToast()"
                class="mt-3 w-full py-2 bg-gray-900 text-white text-[10px] font-bold rounded-xl hover:bg-black transition-all shadow-lg shadow-gray-200 uppercase tracking-tighter">
                I've noticed
            </button>
        </div>
    </div>
</div>

<script>
    let notifiedMeetings = JSON.parse(localStorage.getItem('notifiedMeetings') || '[]');
    let notificationPermissionRequested = false;

    // Clean up old notified meetings (keep only last 50)
    if (notifiedMeetings.length > 50) notifiedMeetings = notifiedMeetings.slice(-50);

    function checkMeetings() {
        fetch('actions/get_upcoming_notification.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.has_meeting) {
                    const meeting = data.meeting;

                    // Only notify if we haven't notified for this meeting ID yet
                    if (!notifiedMeetings.includes(meeting.id)) {
                        showMeetingNotification(meeting);
                    }
                }
            })
            .catch(err => console.error('Notification check failed:', err));
    }

    let originalTitle = document.title;
    let flashInterval = null;

    function startTabFlashing() {
        if (flashInterval) return;
        flashInterval = setInterval(() => {
            document.title = document.title === "⚠️ MEETING SOON!" ? originalTitle : "⚠️ MEETING SOON!";
        }, 800);
    }

    function stopTabFlashing() {
        clearInterval(flashInterval);
        flashInterval = null;
        document.title = originalTitle;
    }

    function showMeetingNotification(meeting) {
        const isMuted = localStorage.getItem('app_notifications_enabled') === 'false';

        // 1. Browser Level Notification (Pops out on Monitor)
        if (Notification.permission === "granted" && !isMuted) {
            const sysNotify = new Notification("MEETING STARTING SOON: " + meeting.title, {
                body: "Click to view portal. Time: " + meeting.time,
                icon: "assets/lrn-logo.jpg",
                requireInteraction: true // Keeps it on screen until user interacts
            });
            sysNotify.onclick = () => {
                window.focus();
                dismissMeetingToast();
            };
        }

        // Only flash/sound if NOT muted
        if (!isMuted) {
            // 2. Alert sound and Tab Flash
            startTabFlashing();
            const sound = document.getElementById('meetingNotifySound');
            sound.loop = true; // Keep playing until noticed
            sound.play().catch(e => console.log('Audio playback blocked'));

            // 3. In-App Toast Notification
            const toast = document.getElementById('meetingToast');
            const titleEl = document.getElementById('meetingToastTitle');
            const timeEl = document.getElementById('meetingToastTime');
            const progress = document.getElementById('toastProgress');

            titleEl.textContent = meeting.title;
            timeEl.textContent = "Starting at " + meeting.time;

            progress.style.transition = 'none';
            progress.style.transform = 'scaleX(1)';
            toast.classList.remove('translate-x-full', 'opacity-0');

            setTimeout(() => {
                progress.style.transition = 'transform 60000ms linear';
                progress.style.transform = 'scaleX(0)';
            }, 50);
        } else {
            console.log("Notification suppressed: Portal is muted.");
        }

        // Record that we've seen this meeting (even if muted, so we don't notify later if unmuted)
        notifiedMeetings.push(meeting.id);
        localStorage.setItem('notifiedMeetings', JSON.stringify(notifiedMeetings));
    }

    function dismissMeetingToast() {
        const toast = document.getElementById('meetingToast');
        const sound = document.getElementById('meetingNotifySound');

        toast.classList.add('translate-x-full', 'opacity-0');
        sound.pause();
        sound.currentTime = 0;
        stopTabFlashing();
    }

    // Request initial permission
    document.addEventListener('click', () => {
        if (Notification.permission === "default") {
            Notification.requestPermission();
        }
    }, { once: true });

    // Initial check and set interval (every 30 seconds)
    checkMeetings();
    setInterval(checkMeetings, 5000);
</script>