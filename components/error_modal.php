<?php if (isset($_GET['error']) && $_GET['error'] == 'invalid_time'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const errorModal = document.createElement('div');
            errorModal.className = 'fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 animate-fade-in';
            errorModal.innerHTML = `
            <div class='bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl transform scale-95 animate-scale-up border border-red-100'>
                <div class='w-16 h-16 bg-red-50 rounded-2xl flex items-center justify-center mx-auto mb-6 text-red-500 shadow-sm'>
                    <i class='fa-regular fa-clock text-3xl'></i>
                </div>
                <h3 class='text-xl font-bold text-gray-800 text-center mb-2'>Invalid Time Range</h3>
                <p class='text-gray-500 text-center text-sm mb-8 leading-relaxed'>
                    The meeting <strong>End Time</strong> cannot be earlier than or the same as the <strong>Start Time</strong>. Please adjust your schedule.
                </p>
                <button onclick='this.closest(".fixed").remove()' 
                    class='w-full py-3.5 bg-gray-900 text-white rounded-xl font-bold hover:bg-black transition-all shadow-lg shadow-gray-200 active:scale-95'>
                    Understood
                </button>
            </div>
        `;
            document.body.appendChild(errorModal);

            // Clean URL to prevent re-display on refresh
            const newUrl = window.location.href.replace(/[&?]error=invalid_time/, '');
            window.history.replaceState({}, document.title, newUrl);

            // Add animation styles dynamically if not present
            if (!document.getElementById('modal-animations')) {
                const style = document.createElement('style');
                style.id = 'modal-animations';
                style.textContent = `
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes scaleUp { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
                .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
                .animate-scale-up { animation: scaleUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
            `;
                document.head.appendChild(style);
            }
        });
    </script>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] == 'overlap'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const errorModal = document.createElement('div');
            errorModal.className = 'fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 animate-fade-in';
            errorModal.innerHTML = `
            <div class='bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl transform scale-95 animate-scale-up border border-orange-100'>
                <div class='w-16 h-16 bg-orange-50 rounded-2xl flex items-center justify-center mx-auto mb-6 text-orange-500 shadow-sm'>
                    <i class='fa-solid fa-calendar-xmark text-3xl'></i>
                </div>
                <h3 class='text-xl font-bold text-gray-800 text-center mb-2'>Schedule Conflict</h3>
                <p class='text-gray-500 text-center text-sm mb-8 leading-relaxed'>
                    This time slot overlaps with another meeting in your schedule. Please choose a different time.
                </p>
                <button onclick='this.closest(".fixed").remove()' 
                    class='w-full py-3.5 bg-gray-900 text-white rounded-xl font-bold hover:bg-black transition-all shadow-lg shadow-gray-200 active:scale-95'>
                    Okay, I'll Check
                </button>
            </div>
        `;
            document.body.appendChild(errorModal);

            // Clean URL to prevent re-display on refresh
            const newUrl = window.location.href.replace(/[&?]error=overlap/, '');
            window.history.replaceState({}, document.title, newUrl);
        });
    </script>
<?php endif; ?>