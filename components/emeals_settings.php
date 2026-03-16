<?php
// E-Meals Settings - Native Admin Component
// Manages JSON configuration files in the emeals directory
require_once __DIR__ . '/../includes/emeals/device-name-config.php';
require_once __DIR__ . '/../includes/emeals/lunch-settings.php';

$scheduleLockFile = __DIR__ . '/../includes/emeals/emeals_schedule_lock.json';

function get_schedule_lock()
{
    global $scheduleLockFile;
    if (!file_exists($scheduleLockFile))
        return [];
    $data = json_decode(file_get_contents($scheduleLockFile), true);
    return is_array($data) ? $data : [];
}

function save_schedule_lock(?array $settings)
{
    global $scheduleLockFile;
    if ($settings === null) {
        if (file_exists($scheduleLockFile))
            unlink($scheduleLockFile);
        return true;
    }
    return file_put_contents($scheduleLockFile, json_encode($settings, JSON_PRETTY_PRINT)) !== false;
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_devices') {
        $names = [
            'emeals1' => $_POST['emeals1'] ?? 'Emeals 1',
            'emeals2' => $_POST['emeals2'] ?? 'Emeals 2'
        ];
        if (persistEmealsDeviceNames($names)) {
            $msg = 'Device names updated successfully.';
            $msgType = 'success';
        } else {
            $msg = 'Failed to update device names.';
            $msgType = 'error';
        }
    }

    if ($action === 'update_lunch') {
        $gap = (int) ($_POST['lunch_gap'] ?? 3);
        if (persistEmealsLunchSettings(['lunch_gap_hours' => $gap])) {
            $msg = 'Meal timing updated successfully.';
            $msgType = 'success';
        } else {
            $msg = 'Failed to update meal timing.';
            $msgType = 'error';
        }
    }

    if ($action === 'update_lock') {
        if (isset($_POST['unlock'])) {
            save_schedule_lock(null);
            $msg = 'Schedule lock has been lifted.';
            $msgType = 'success';
        } else {
            $from = $_POST['lock_from'] ?? '';
            $to = $_POST['lock_to'] ?? '';
            $recurrence = $_POST['recurrence'] ?? 'none';
            $days = $_POST['days'] ?? [];
            if ($from && $to) {
                $settings = ['lock_from' => $from, 'lock_to' => $to, 'recurrence' => $recurrence, 'weekly_days' => $days];
                if (save_schedule_lock($settings)) {
                    $msg = 'Schedule lock applied.';
                    $msgType = 'success';
                }
            } else {
                $msg = 'Please select valid lock dates.';
                $msgType = 'error';
            }
        }
    }
}

$deviceNames = readEmealsDeviceNames(true);
$lunchSettings = readEmealsLunchSettings(true);
$lockSettings = get_schedule_lock();
$isLocked = !empty($lockSettings);
?>

<style>
    .settings-input {
        width: 100%;
        padding: 0.75rem 1rem;
        background: #f9fafb;
        border: 1.5px solid #e5e7eb;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #1f2937;
        outline: none;
        transition: all 0.2s;
    }

    .settings-input:focus {
        background: #fff;
        border-color: #ec4899;
        box-shadow: 0 0 0 4px rgba(236, 72, 153, 0.06);
    }

    .settings-input[type="number"] {
        -moz-appearance: textfield;
    }

    .settings-input::-webkit-outer-spin-button,
    .settings-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .day-pill input[type="checkbox"] {
        display: none;
    }

    .day-pill label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        border: 1.5px solid #e5e7eb;
        background: #f9fafb;
        font-size: 0.7rem;
        font-weight: 800;
        color: #9ca3af;
        cursor: pointer;
        transition: all 0.2s;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        user-select: none;
    }

    .day-pill input:checked+label {
        background: #ec4899;
        border-color: #ec4899;
        color: #fff;
        box-shadow: 0 4px 12px rgba(236, 72, 153, 0.25);
    }

    .day-pill label:hover {
        border-color: #9ca3af;
        background: #fff;
        color: #374151;
    }

    .day-pill input:checked+label:hover {
        background: #db2777;
    }

    .settings-card {
        background: #fff;
        border-radius: 1.5rem;
        border: 1.5px solid #f3f4f6;
        padding: 1.75rem;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        transition: box-shadow 0.2s;
    }

    .settings-card:hover {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    }

    .settings-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: #ec4899;
        color: #fff;
        border: none;
        border-radius: 0.875rem;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 2px 8px rgba(236, 72, 153, 0.2);
    }

    .settings-btn-primary:hover {
        background: #db2777;
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(236, 72, 153, 0.3);
    }

    .settings-btn-primary:active {
        transform: translateY(0);
    }

    .settings-btn-danger {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.65rem 1.25rem;
        background: #fff;
        color: #ef4444;
        border: 1.5px solid #fee2e2;
        border-radius: 0.875rem;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        cursor: pointer;
        transition: all 0.2s;
    }

    .settings-btn-danger:hover {
        background: #fef2f2;
        border-color: #fca5a5;
    }
</style>

<div class="flex flex-col h-full animate-in fade-in zoom-in duration-500">

    <!-- Page Header -->
    <div class="shrink-0 mb-6 px-1">
        <h2 class="text-xl font-black text-gray-800 tracking-tighter leading-none">E-Meals Settings</h2>
        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">System Configuration</p>
    </div>

    <!-- Alert Toast -->
    <?php if ($msg): ?>
        <div class="shrink-0 mb-4 animate-in slide-in-from-top duration-300">
            <div
                class="flex items-center gap-3 p-4 rounded-2xl border <?= $msgType === 'success' ? 'bg-emerald-50 border-emerald-100 text-emerald-800' : 'bg-red-50 border-red-100 text-red-800' ?>">
                <div
                    class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 <?= $msgType === 'success' ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white' ?>">
                    <i class="fa-solid <?= $msgType === 'success' ? 'fa-check' : 'fa-triangle-exclamation' ?> text-xs"></i>
                </div>
                <p class="text-sm font-bold flex-1"><?= htmlspecialchars($msg) ?></p>
                <button onclick="this.closest('div.animate-in').remove()"
                    class="opacity-40 hover:opacity-80 transition-opacity text-current">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Settings Grid -->
    <div class="flex-1 overflow-y-auto custom-scrollbar pr-1">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 w-full">

            <!-- ─── Card 1: Device Station Names ─── -->
            <div class="settings-card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-gray-900 text-white flex items-center justify-center">
                        <i class="fa-solid fa-tablet-screen-button text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-gray-800 uppercase tracking-tight">Device Stations</h3>
                        <p class="text-[10px] text-gray-400 font-medium mt-0.5">Rename meal scanning terminals</p>
                    </div>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_devices">

                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block">Station
                            1</label>
                        <input type="text" name="emeals1" placeholder="e.g. Main Cafeteria"
                            value="<?= htmlspecialchars($deviceNames['emeals1'] ?? 'Emeals 1') ?>"
                            class="settings-input">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block">Station
                            2</label>
                        <input type="text" name="emeals2" placeholder="e.g. Production Wing"
                            value="<?= htmlspecialchars($deviceNames['emeals2'] ?? 'Emeals 2') ?>"
                            class="settings-input">
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="settings-btn-primary w-full justify-center">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Station Names
                        </button>
                    </div>
                </form>
            </div>

            <!-- ─── Card 2: Meal Timing ─── -->
            <div class="settings-card">
                <div class="flex items-center gap-3 mb-6">
                    <div
                        class="w-10 h-10 rounded-xl bg-pink-50 text-pink-500 flex items-center justify-center border border-pink-100">
                        <i class="fa-regular fa-clock text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-gray-800 uppercase tracking-tight">Meal Timing</h3>
                        <p class="text-[10px] text-gray-400 font-medium mt-0.5">Gap between consecutive meals</p>
                    </div>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_lunch">

                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block">Lunch
                            Gap</label>
                        <div class="relative">
                            <input type="number" name="lunch_gap" min="1" max="12"
                                value="<?= (int) ($lunchSettings['lunch_gap_hours'] ?? 3) ?>"
                                class="settings-input pr-12">
                            <span
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-400 uppercase tracking-widest">hrs</span>
                        </div>
                        <p class="text-[10px] text-gray-400 font-medium mt-1.5 leading-relaxed">Minimum interval between
                            meal scans for the same employee.</p>
                    </div>

                    <!-- Visual scale indicator -->
                    <div class="bg-gray-50 rounded-xl p-3 flex items-center gap-3">
                        <div class="flex gap-1 flex-1">
                            <?php for ($i = 1; $i <= 12; $i++):
                                $gap = (int) ($lunchSettings['lunch_gap_hours'] ?? 3); ?>
                                <div
                                    class="flex-1 rounded-full h-1.5 transition-all <?= $i <= $gap ? 'bg-pink-400' : 'bg-gray-200' ?>">
                                </div>
                            <?php endfor; ?>
                        </div>
                        <span
                            class="text-[10px] font-black text-gray-500 shrink-0"><?= (int) ($lunchSettings['lunch_gap_hours'] ?? 3) ?>
                            / 12h</span>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="settings-btn-primary w-full justify-center">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Update Timing
                        </button>
                    </div>
                </form>
            </div>

            <!-- ─── Card 3: Schedule Lock Control (Full Width) ─── -->
            <div class="settings-card lg:col-span-2">
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center <?= $isLocked ? 'bg-red-50 text-red-500 border border-red-100' : 'bg-emerald-50 text-emerald-500 border border-emerald-100' ?>">
                            <i class="fa-solid <?= $isLocked ? 'fa-lock' : 'fa-lock-open' ?> text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-gray-800 uppercase tracking-tight">Schedule Lock</h3>
                            <p class="text-[10px] text-gray-400 font-medium mt-0.5">Prevent schedule uploads during a
                                date range</p>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="flex items-center gap-2">
                        <div
                            class="flex items-center gap-2 px-4 py-2 rounded-xl <?= $isLocked ? 'bg-red-50 border border-red-100' : 'bg-emerald-50 border border-emerald-100' ?>">
                            <span
                                class="w-2 h-2 rounded-full <?= $isLocked ? 'bg-red-500' : 'bg-emerald-500 animate-pulse' ?>"></span>
                            <span
                                class="text-[10px] font-black uppercase tracking-widest <?= $isLocked ? 'text-red-600' : 'text-emerald-600' ?>"><?= $isLocked ? 'Locked' : 'Open' ?></span>
                        </div>
                        <?php if ($isLocked): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_lock">
                                <input type="hidden" name="unlock" value="1">
                                <button type="submit" class="settings-btn-danger">
                                    <i class="fa-solid fa-lock-open"></i> Unlock
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Active Lock Info -->
                <?php if ($isLocked): ?>
                    <div class="mb-6 p-4 bg-red-50/60 border border-red-100 rounded-xl flex items-center gap-3">
                        <i class="fa-solid fa-circle-info text-red-400 text-sm"></i>
                        <div class="text-sm text-red-700">
                            Schedule uploads are locked from
                            <strong><?= htmlspecialchars($lockSettings['lock_from']) ?></strong> to
                            <strong><?= htmlspecialchars($lockSettings['lock_to']) ?></strong>
                            <?php if (!empty($lockSettings['recurrence']) && $lockSettings['recurrence'] !== 'none'): ?>
                                &mdash; <span
                                    class="italic"><?= ucfirst(htmlspecialchars($lockSettings['recurrence'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Lock Form -->
                <form method="POST">
                    <input type="hidden" name="action" value="update_lock">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- Left: Date Range -->
                        <div class="space-y-4">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Date Range</p>
                            <div class="space-y-1">
                                <label class="text-[10px] font-semibold text-gray-500 block">Lock From</label>
                                <input type="date" name="lock_from"
                                    value="<?= htmlspecialchars($lockSettings['lock_from'] ?? '') ?>"
                                    class="settings-input">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-semibold text-gray-500 block">Lock Until</label>
                                <input type="date" name="lock_to"
                                    value="<?= htmlspecialchars($lockSettings['lock_to'] ?? '') ?>"
                                    class="settings-input">
                            </div>
                        </div>

                        <!-- Right: Recurrence & Days -->
                        <div class="space-y-4">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Recurrence</p>
                            <div class="space-y-1">
                                <label class="text-[10px] font-semibold text-gray-500 block">Pattern</label>
                                <div class="relative">
                                    <select name="recurrence"
                                        class="settings-input appearance-none cursor-pointer pr-10">
                                        <option value="none" <?= ($lockSettings['recurrence'] ?? 'none') === 'none' ? 'selected' : '' ?>>One-time only</option>
                                        <option value="weekly" <?= ($lockSettings['recurrence'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                        <option value="yearly" <?= ($lockSettings['recurrence'] ?? '') === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                                    </select>
                                    <i
                                        class="fa-solid fa-chevron-down absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px] pointer-events-none"></i>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-semibold text-gray-500 block">Days (Weekly only)</label>
                                <div class="flex gap-2 flex-wrap">
                                    <?php $weekDays = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']; ?>
                                    <?php foreach ($weekDays as $k => $d): ?>
                                        <div class="day-pill">
                                            <input type="checkbox" name="days[]" value="<?= $k ?>" id="day_<?= $k ?>"
                                                <?= in_array($k, $lockSettings['weekly_days'] ?? []) ? 'checked' : '' ?>>
                                            <label for="day_<?= $k ?>"><?= $d ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Row -->
                        <div class="md:col-span-2 pt-4 border-t border-gray-100 flex justify-end">
                            <button type="submit" class="settings-btn-primary">
                                <i class="fa-solid fa-lock"></i>
                                Apply Lock Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>