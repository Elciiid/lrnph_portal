<!-- Global Confirmation Modal -->
<div id="globalConfirmModal"
    class="hidden fixed inset-0 z-[10000] flex items-center justify-center p-4 backdrop-blur-md transition-all duration-300 opacity-0 bg-black/40">
    <div
        class="bg-white rounded-[32px] w-full max-w-sm shadow-2xl transform transition-all scale-95 opacity-0 duration-300 overflow-hidden flex flex-col border border-gray-100">
        <!-- Icon/Header Section -->
        <div class="pt-8 pb-4 flex flex-col items-center text-center px-8">
            <div
                class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mb-4 border-4 border-white shadow-sm">
                <i class="fa-solid fa-trash-can text-3xl text-red-500 animate-bounce-subtle"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 leading-tight" id="confirmTitle">Are you sure?</h3>
            <p class="text-sm text-gray-500 mt-2 leading-relaxed" id="confirmMessage">
                This action cannot be undone. All data related to this meeting will be permanently deleted.
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="p-6 flex flex-col gap-3">
            <button id="confirmBtn"
                class="w-full py-4 bg-red-500 hover:bg-red-600 text-white font-bold rounded-2xl shadow-lg shadow-red-200 transition-all active:scale-[0.98] flex items-center justify-center gap-2">
                <span>Yes, Delete it</span>
            </button>
            <button onclick="closeConfirmModal()"
                class="w-full py-4 bg-gray-50 hover:bg-gray-100 text-gray-600 font-bold rounded-2xl transition-all active:scale-[0.98]">
                Cancel
            </button>
        </div>

        <!-- Subtle Progress Bar -->
        <div class="h-1.5 w-full bg-gray-50 overflow-hidden">
            <div class="h-full bg-red-500/20 w-1/3 animate-shimmer"></div>
        </div>
    </div>
</div>

<style>
    @keyframes bounce-subtle {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-5px);
        }
    }

    .animate-bounce-subtle {
        animation: bounce-subtle 2s infinite ease-in-out;
    }

    @keyframes shimmer {
        0% {
            transform: translateX(-100%);
        }

        100% {
            transform: translateX(300%);
        }
    }

    .animate-shimmer {
        animation: shimmer 2s infinite linear;
    }
</style>

<script>
    let confirmCallback = null;

    function showConfirmModal(title, message, callback) {
        const modal = document.getElementById('globalConfirmModal');
        const box = modal.querySelector('div');

        document.getElementById('confirmTitle').textContent = title || 'Are you sure?';
        document.getElementById('confirmMessage').textContent = message || 'This action cannot be undone.';
        confirmCallback = callback;

        modal.classList.remove('hidden');
        // Force reflow
        void modal.offsetWidth;

        modal.classList.replace('opacity-0', 'opacity-100');
        box.classList.replace('scale-95', 'scale-100');
        box.classList.replace('opacity-0', 'opacity-100');
    }

    function closeConfirmModal() {
        const modal = document.getElementById('globalConfirmModal');
        const box = modal.querySelector('div');

        modal.classList.replace('opacity-100', 'opacity-0');
        box.classList.replace('scale-100', 'scale-95');
        box.classList.replace('opacity-100', 'opacity-0');

        setTimeout(() => {
            modal.classList.add('hidden');
            confirmCallback = null;
        }, 300);
    }

    document.getElementById('confirmBtn').addEventListener('click', () => {
        if (confirmCallback) confirmCallback();
        closeConfirmModal();
    });
</script>