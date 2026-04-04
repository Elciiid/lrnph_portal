<?php

// -- Helpers --
if (!function_exists('emeals_format_val')) {
    function emeals_format_val($value, $format = 'Y-m-d H:i:s')
    {
        if (empty($value)) return '';
        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }
        $timestamp = strtotime($value);
        return $timestamp ? date($format, $timestamp) : (string) $value;
    }
}

// -- Data Fetching Functions --

function get_emeals_schedule($deptFilter = null)
{
    global $conn;
    
    $sql = "SELECT s.full_name, s.bio_id, s.plotted_date, s.time_in, s.time_out, s.schedule, s.overtime 
            FROM \"prtl_emeals_plotted_schedule\" AS s
            LEFT JOIN \"prtl_lrn_master_list\" AS ml ON ml.\"BiometricsID\" = s.bio_id";
    $params = [];
    if ($deptFilter) {
        $sql .= " WHERE ml.\"Department\" = ?";
        $params[] = $deptFilter;
    }
    $sql .= " ORDER BY s.plotted_date DESC, s.bio_id ASC LIMIT 60";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    if ($stmt) {
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $r['plotted_date'] = emeals_format_val($r['plotted_date'], 'Y-m-d');
            $r['time_in'] = emeals_format_val($r['time_in'], 'H:i');
            $r['time_out'] = emeals_format_val($r['time_out'], 'H:i');
            $rows[] = $r;
        }
    }
    return ['rows' => $rows, 'error' => $stmt ? null : 'Database error occurred'];
}

function get_fcl_access()
{
    global $conn;

    $sql = "SELECT staff_code, employee_name, biometric, department, remarks, served FROM \"prtl_fcl_access\" ORDER BY staff_code ASC LIMIT 100";
    $stmt = $conn->query($sql);
    $rows = [];
    if ($stmt) {
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function get_emeals_monitor()
{
    global $conn;

    $sql = "SELECT 
            m.emp_id, m.full_name, MAX(m.log_date::date) as log_date,
            MIN(m.log_time) as log_time, MAX(m.device_name) as device_name,
            MAX(m.meal_1::int) as meal_1, MAX(m.meal_1_datetime) as meal_1_datetime,
            MAX(m.meal_2::int) as meal_2, MAX(m.meal_2_datetime) as meal_2_datetime,
            MAX(m.meal_3::int) as meal_3, MAX(m.meal_3_datetime) as meal_3_datetime
            FROM \"prtl_emeals_monitor\" m
            GROUP BY m.emp_id, m.full_name
            ORDER BY MAX(m.log_date::date) DESC, m.emp_id ASC LIMIT 100";

    $stmt = $conn->query($sql);
    $rows = [];
    if ($stmt) {
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $r['log_date'] = emeals_format_val($r['log_date'], 'Y-m-d');
            $r['log_time'] = emeals_format_val($r['log_time'], 'H:i');
            $r['meal_1_datetime'] = $r['meal_1_datetime'] ? emeals_format_val($r['meal_1_datetime'], 'H:i') : '';
            $r['meal_2_datetime'] = $r['meal_2_datetime'] ? emeals_format_val($r['meal_2_datetime'], 'H:i') : '';
            $r['meal_3_datetime'] = $r['meal_3_datetime'] ? emeals_format_val($r['meal_3_datetime'], 'H:i') : '';
            $rows[] = $r;
        }
    }
    return $rows;
}

// -- Action Handling --
$actionMsg = '';
$actionStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Upload FCL Logic
    if ($action === 'upload_fcl' && isset($_FILES['attendance_file'])) {
        $file = $_FILES['attendance_file']['tmp_name'];
        if (($handle = fopen($file, "r")) !== FALSE) {
            fgetcsv($handle); // skip header
            $count = 0;
            $sql = "INSERT INTO \"prtl_fcl_access\" (staff_code, employee_name, biometric, department, remarks, served) 
                    VALUES (?, ?, ?, ?, ?, 0)
                    ON CONFLICT (staff_code) DO UPDATE 
                    SET employee_name = EXCLUDED.employee_name, biometric = EXCLUDED.biometric, 
                        department = EXCLUDED.department, remarks = EXCLUDED.remarks";
            $stmt = $conn->prepare($sql);

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) < 6)
                    continue;
                $code = trim($data[1]);
                $name = trim($data[2]);
                $bio = trim($data[3]);
                $dept = trim($data[4]);
                $rem = trim($data[5]);

                $stmt->execute([$code, $name, $bio, $dept, $rem]);
                $count++;
            }
            fclose($handle);
            $actionMsg = "Successfully uploaded $count personnel records.";
            $actionStatus = 'success';
        } else {
            $actionMsg = "Error: Could not open the uploaded file.";
            $actionStatus = 'error';
        }
    }

    // Upload Schedule Logic
    if ($action === 'upload_schedule' && isset($_FILES['schedule_csv'])) {
        $file = $_FILES['schedule_csv']['tmp_name'];
        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle);

            $dateCols = [];
            for ($i = 2; $i < count($header); $i++) {
                $dateStr = trim($header[$i]);
                if ($dateStr) {
                    $dt = DateTime::createFromFormat('l, F j, Y', $dateStr);
                    if ($dt)
                        $dateCols[$i] = $dt->format('Y-m-d');
                }
            }

            if (!empty($dateCols)) {
                $count = 0;
                $sql = "INSERT INTO \"prtl_emeals_plotted_schedule\" (full_name, bio_id, plotted_date, time_in, time_out, schedule, overtime) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON CONFLICT (bio_id, plotted_date) DO UPDATE 
                        SET full_name = EXCLUDED.full_name, time_in = EXCLUDED.time_in, 
                            time_out = EXCLUDED.time_out, schedule = EXCLUDED.schedule, overtime = EXCLUDED.overtime";
                $stmt = $conn->prepare($sql);

                while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (!isset($row[0]))
                        continue;
                    $bioId = trim($row[0]);
                    $fullName = trim($row[1]);

                    foreach ($dateCols as $idx => $dateVal) {
                        $sched = isset($row[$idx]) ? trim($row[$idx]) : '';
                        if (!$sched)
                            continue;

                        $ot = (stripos($sched, 'OT') !== false) ? 1 : 0;
                        $timeIn = null;
                        $timeOut = null;
                        if (preg_match('/\((\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})\)/', $sched, $m)) {
                            $timeIn = $m[1];
                            $timeOut = $m[2];
                        }

                        $stmt->execute([$fullName, $bioId, $dateVal, $timeIn, $timeOut, $sched, $ot]);
                    }
                    $count++;
                }
                $actionMsg = "Plotted schedules for $count employees updated successfully.";
                $actionStatus = 'success';
            } else {
                $actionMsg = "Error: Invalid file format. No date columns detected.";
                $actionStatus = 'error';
            }
            fclose($handle);
        }
    }

    // Clear Served Actions
    if (isset($_POST['reset_served_action'])) {
        if ($_POST['reset_served_action'] === 'all') {
            $conn->query("UPDATE \"prtl_fcl_access\" SET served = 0");
            $actionMsg = "All FCL served flags have been cleared.";
        } elseif ($_POST['reset_served_action'] === 'staff' && !empty($_POST['reset_staff_code'])) {
            $stmt = $conn->prepare("UPDATE \"prtl_fcl_access\" SET served = 0 WHERE staff_code = ?");
            $stmt->execute([$_POST['reset_staff_code']]);
            $actionMsg = "Served status alert cleared for code: " . htmlspecialchars($_POST['reset_staff_code']);
        }
        $actionStatus = 'success';
    }
}

// -- View State --
$tab = $_GET['tab'] ?? 'monitor';
$monitorData = ($tab === 'monitor') ? get_emeals_monitor() : [];
$scheduleData = ($tab === 'schedule') ? get_emeals_schedule() : ['rows' => []];
$fclData = ($tab === 'fcl') ? get_fcl_access() : [];

$totalRecords = 0;
if ($tab === 'monitor')
    $totalRecords = count($monitorData);
elseif ($tab === 'schedule')
    $totalRecords = count($scheduleData['rows']);
elseif ($tab === 'fcl')
    $totalRecords = count($fclData);

?>

<style>
    .glass-tab-active {
        /* Intentionally empty to preserve structure, replaced by inline Tailwind classes */
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #f1f5f9;
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #e2e8f0;
    }
    .row-hover:hover {
        background-color: rgba(236, 72, 153, 0.03);
    }
    .status-pill {
        padding: 2px 10px;
        border-radius: 9999px;
        font-weight: 700;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }
</style>

<div class="flex flex-col h-full gap-4 min-h-0 animate-in fade-in zoom-in duration-500">
    
    <!-- Ultra Modern Header & Tabs -->
    <div class="flex items-center gap-4 shrink-0 px-2 pb-1 lg:px-0">
        <!-- Title (Text Only) -->
        <div class="pr-6">
            <h2 class="text-xl font-black text-gray-800 tracking-tighter leading-none">E-Meals</h2>
            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">Meal Tracking</p>
        </div>

        <!-- Navigation Tabs (Floating) -->
        <div class="flex items-center gap-1 p-1.5 rounded-[22px] shadow-sm border border-gray-100/80 bg-white w-fit">
            <a href="admin.php?page=emeals&tab=monitor" 
            class="px-4 lg:px-6 py-2.5 rounded-[18px] text-sm font-bold transition-all flex items-center gap-2 <?= $tab === 'monitor' ? 'bg-gray-900 text-white shadow-lg shadow-gray-200' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-50' ?>">
                <i class="fa-solid fa-desktop"></i>
                <span class="hidden md:inline">Monitor</span>
            </a>
            <a href="admin.php?page=emeals&tab=schedule" 
            class="px-4 lg:px-6 py-2.5 rounded-[18px] text-sm font-bold transition-all flex items-center gap-2 <?= $tab === 'schedule' ? 'bg-gray-900 text-white shadow-lg shadow-gray-200' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-50' ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span class="hidden md:inline">Schedule</span>
            </a>
            <a href="admin.php?page=emeals&tab=fcl" 
            class="px-4 lg:px-6 py-2.5 rounded-[18px] text-sm font-bold transition-all flex items-center gap-2 <?= $tab === 'fcl' ? 'bg-gray-900 text-white shadow-lg shadow-gray-200' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-50' ?>">
                <i class="fa-solid fa-gift"></i>
                <span class="hidden md:inline">FCL Access</span>
            </a>
        </div>

        <!-- Central Search Bar (Filling the gap) -->
        <div class="flex-1 flex justify-center px-4 animate-in fade-in slide-in-from-bottom-2 duration-700 delay-150 md:-translate-x-12">
            <div class="relative group w-full max-w-sm">
                <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 group-focus-within:text-pink-500 transition-colors"></i>
                <input type="text" 
                    placeholder="Search entries..." 
                    class="w-full bg-white/50 backdrop-blur-md border border-white/60 pl-11 pr-4 py-2.5 rounded-2xl text-sm font-medium outline-none focus:bg-white focus:border-pink-300 focus:ring-4 focus:ring-pink-500/5 transition-all shadow-sm"
                    onkeyup="filterTable(this.value)">
            </div>
        </div>

        <!-- Stats Container -->
        <div class="bg-white/80 backdrop-blur-xl border border-white/60 px-6 py-2 rounded-2xl flex items-center gap-4 shadow-sm ml-auto">
            <div class="flex flex-col items-end leading-tight">
                <span class="text-[10px] text-gray-400 font-black uppercase tracking-widest">Active Records</span>
                <span class="text-xl font-black text-pink-600"><?= $totalRecords ?></span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-pink-50 text-pink-500 flex items-center justify-center shadow-sm shadow-pink-500/5">
                <i class="fa-solid fa-bolt-lightning text-xs"></i>
            </div>
        </div>
    </div>

    <!-- Main Dynamic Content Column -->
    <div class="flex-1 flex flex-col min-h-0 gap-4 overflow-hidden">
        
        <!-- Animated Action Panel (Uploaders, Alerts) -->
        <?php if ($actionMsg || $tab !== 'monitor'): ?>
                <div class="shrink-0 flex flex-col gap-3">
                
                    <!-- Feedback Toast (Inline) -->
                    <?php if ($actionMsg): ?>
                            <div class="animate-in slide-in-from-top duration-300 p-4 rounded-2xl border flex items-center gap-3 shadow-sm <?= $actionStatus === 'success' ? 'bg-emerald-50 border-emerald-100 text-emerald-800' : 'bg-rose-50 border-rose-100 text-rose-800' ?>">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center <?= $actionStatus === 'success' ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white' ?>">
                                    <i class="fa-solid <?= $actionStatus === 'success' ? 'fa-check' : 'fa-triangle-exclamation' ?>"></i>
                                </div>
                                <p class="text-sm font-bold"><?= htmlspecialchars($actionMsg) ?></p>
                                <button onclick="this.parentElement.remove()" class="ml-auto opacity-50 hover:opacity-100 transition-opacity">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                    <?php endif; ?>

                    <!-- Dynamic Controls based on Tab -->
                    <?php if ($tab === 'schedule'): ?>
                            <div class="bg-white/80 border border-white/60 p-5 rounded-2xl shadow-sm flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-pink-50 text-pink-500 flex items-center justify-center text-xl">
                                        <i class="fa-solid fa-file-csv"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-black text-gray-800 uppercase text-xs tracking-wider">Upload Plotted Schedule</h3>
                                        <p class="text-[11px] text-gray-400 font-medium italic">Import Excel/CSV plotted shifts for meal validation</p>
                                    </div>
                                </div>
                                <form method="POST" enctype="multipart/form-data" class="flex gap-2">
                                    <input type="hidden" name="action" value="upload_schedule">
                                    <label class="cursor-pointer group">
                                        <span class="bg-gray-50 border border-gray-200 text-gray-600 px-4 py-2.5 rounded-xl text-xs font-bold transition-all group-hover:bg-white group-hover:border-pink-300 block">
                                            <i class="fa-solid fa-paperclip mr-2 text-gray-400"></i> Browse CSV
                                        </span>
                                        <input type="file" name="schedule_csv" required class="hidden" onchange="this.form.submit()">
                                    </label>
                                    <button type="submit" class="bg-pink-500 text-white px-6 py-3 rounded-[0.875rem] text-xs font-[800] hover:bg-pink-600 active:translate-y-0 hover:-translate-y-[1px] shadow-[0_2px_8px_rgba(236,72,153,0.2)] hover:shadow-[0_4px_16px_rgba(236,72,153,0.3)] transition-all uppercase tracking-[0.05em] flex items-center justify-center">
                                        Refresh Data
                                    </button>
                                </form>
                            </div>
                    <?php elseif ($tab === 'fcl'): ?>
                            <div class="bg-white/80 border border-white/60 p-5 rounded-2xl shadow-sm flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-pink-50 text-pink-500 flex items-center justify-center text-xl">
                                        <i class="fa-solid fa-gift"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-black text-gray-800 uppercase text-xs tracking-wider">FCL Access Management</h3>
                                        <p class="text-[11px] text-gray-400 font-medium italic">Define employees eligible for First Class Lounge treats</p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" onsubmit="return confirm('Really clear ALL claimed statuses? This cannot be undone.')">
                                        <input type="hidden" name="reset_served_action" value="all">
                                        <button type="submit" class="bg-white border-[1.5px] border-red-100 text-red-500 px-5 py-[0.65rem] rounded-[0.875rem] text-xs font-[800] hover:bg-red-50 hover:border-red-300 transition-all uppercase tracking-[0.05em] flex items-center gap-2">
                                            <i class="fa-solid fa-rotate-left"></i> Reset All Claims
                                        </button>
                                    </form>
                                    <form method="POST" enctype="multipart/form-data" class="flex">
                                        <input type="hidden" name="action" value="upload_fcl">
                                        <label class="cursor-pointer group">
                                            <span class="bg-pink-500 text-white px-6 py-3 rounded-[0.875rem] text-xs font-[800] hover:bg-pink-600 active:translate-y-0 hover:-translate-y-[1px] shadow-[0_2px_8px_rgba(236,72,153,0.2)] hover:shadow-[0_4px_16px_rgba(236,72,153,0.3)] transition-all uppercase tracking-[0.05em] flex items-center gap-2">
                                                <i class="fa-solid fa-plus mr-1"></i> Update FCL List
                                            </span>
                                            <input type="file" name="attendance_file" required class="hidden" onchange="this.form.submit()">
                                        </label>
                                    </form>
                                </div>
                            </div>
                    <?php endif; ?>
                </div>
        <?php endif; ?>

        <!-- Glass Table Container -->
        <div class="flex-1 overflow-hidden bg-white/70 backdrop-blur-md border border-white/50 rounded-3xl shadow-sm flex flex-col min-h-0">
            
            <div class="overflow-auto flex-1 custom-scrollbar">
                
                <?php if ($tab === 'monitor'): ?>
                        <table class="w-full text-left text-sm relative border-separate border-spacing-0">
                            <thead class="sticky top-0 z-20">
                                <tr>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Personnel Info</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Entry Detail</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Morning Meal</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Midday Meal</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Terminal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50/60">
                                <?php foreach ($monitorData as $row): ?>
                                        <tr class="transition-all hover:bg-white group">
                                            <td class="p-5">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 font-black text-xs group-hover:bg-pink-50 group-hover:text-pink-500 transition-colors">
                                                        <?= substr($row['full_name'], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <div class="font-black text-gray-800 tracking-tight leading-none group-hover:text-pink-600"><?= htmlspecialchars($row['full_name']) ?></div>
                                                        <div class="text-[10px] text-gray-400 font-bold mt-1"><?= htmlspecialchars($row['emp_id']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-5">
                                                <div class="font-bold text-gray-700"><?= date('M d, Y', strtotime($row['log_date'])) ?></div>
                                                <div class="text-[10px] font-black text-pink-500 mt-0.5"><i class="fa-regular fa-clock mr-1"></i><?= $row['log_time'] ?></div>
                                            </td>
                                            <td class="p-5">
                                                <?php if ($row['meal_1']): ?>
                                                        <div class="flex flex-col gap-1">
                                                            <span class="status-pill bg-emerald-100 text-emerald-700 w-fit">Claimed</span>
                                                            <span class="text-[10px] text-gray-400 font-bold"><?= $row['meal_1_datetime'] ?></span>
                                                        </div>
                                                <?php else: ?>
                                                        <span class="text-gray-200"><i class="fa-solid fa-minus"></i></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-5">
                                                <?php if ($row['meal_2']): ?>
                                                        <div class="flex flex-col gap-1">
                                                            <span class="status-pill bg-emerald-100 text-emerald-700 w-fit">Claimed</span>
                                                            <span class="text-[10px] text-gray-400 font-bold"><?= $row['meal_2_datetime'] ?></span>
                                                        </div>
                                                <?php else: ?>
                                                        <span class="text-gray-200"><i class="fa-solid fa-minus"></i></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-5">
                                                <div class="px-2 py-1 bg-gray-50 rounded-lg border border-gray-100 text-[10px] font-bold text-gray-500 w-fit">
                                                    <?= htmlspecialchars($row['device_name']) ?>
                                                </div>
                                            </td>
                                        </tr>
                                <?php endforeach; ?>
                                <?php if (empty($monitorData)): ?>
                                        <tr>
                                            <td colspan="5" class="p-20 text-center">
                                                <div class="flex flex-col items-center opacity-30">
                                                    <i class="fa-solid fa-inbox text-5xl mb-4"></i>
                                                    <p class="font-black text-lg">No active logs detected</p>
                                                </div>
                                            </td>
                                        </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                <?php elseif ($tab === 'schedule'): ?>
                        <table class="w-full text-left text-sm relative border-separate border-spacing-0">
                            <thead class="sticky top-0 z-20">
                                <tr>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Employee</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Plotted Date</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Shift Work</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest text-center">Overtime</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50/60">
                                <?php foreach ($scheduleData['rows'] as $row): ?>
                                        <tr class="transition-all hover:bg-white group">
                                            <td class="p-5">
                                                <div class="font-black text-gray-800 tracking-tight group-hover:text-pink-600"><?= htmlspecialchars($row['full_name']) ?></div>
                                                <div class="text-[10px] text-gray-400 font-bold mt-1 uppercase tracking-tighter"><?= htmlspecialchars($row['bio_id']) ?></div>
                                            </td>
                                            <td class="p-5">
                                                <span class="bg-gray-100 text-gray-600 px-3 py-1.5 rounded-xl font-black text-[10px]"><?= date('D, M d, Y', strtotime($row['plotted_date'])) ?></span>
                                            </td>
                                            <td class="p-5">
                                                <div class="font-mono text-[11px] font-bold text-gray-700 bg-gray-50/50 p-2 rounded-xl border border-gray-100 inline-block">
                                                    <?= htmlspecialchars($row['schedule']) ?>
                                                </div>
                                                <?php if ($row['time_in']): ?>
                                                        <div class="text-[9px] text-pink-500 font-black mt-2 uppercase tracking-widest">
                                                            <i class="fa-solid fa-bolt mr-1"></i> Active: <?= $row['time_in'] ?> to <?= $row['time_out'] ?>
                                                        </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-5 text-center">
                                                <?php if ($row['overtime']): ?>
                                                        <div class="inline-flex items-center gap-1.5 bg-orange-50 text-orange-600 px-3 py-1.5 rounded-full font-black text-[10px] border border-orange-100">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span> YES
                                                        </div>
                                                <?php else: ?>
                                                        <span class="text-gray-300 font-bold text-xs">NO</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                <?php elseif ($tab === 'fcl'): ?>
                        <table class="w-full text-left text-sm relative border-separate border-spacing-0">
                            <thead class="sticky top-0 z-20">
                                <tr>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Staff Code</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Employee Identity</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Department</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest">Notes</th>
                                    <th class="p-5 bg-white/95 backdrop-blur-sm border-b border-gray-100 text-gray-400 font-black text-[10px] uppercase tracking-widest text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50/60">
                                <?php foreach ($fclData as $row): ?>
                                        <tr class="transition-all hover:bg-white group">
                                            <td class="p-5 font-black text-xs text-pink-600 tracking-widest">
                                                <?= htmlspecialchars($row['staff_code']) ?>
                                            </td>
                                            <td class="p-5">
                                                <div class="font-black text-gray-800 tracking-tight group-hover:text-pink-600"><?= htmlspecialchars($row['employee_name']) ?></div>
                                                <div class="text-[10px] text-gray-400 font-bold mt-1">BIO: <?= htmlspecialchars($row['biometric']) ?></div>
                                            </td>
                                            <td class="p-5">
                                                <div class="text-[11px] font-bold text-gray-500 pr-4"><?= htmlspecialchars($row['department']) ?></div>
                                            </td>
                                            <td class="p-5">
                                                <div class="text-[11px] italic text-gray-400 truncate max-w-[150px]" title="<?= htmlspecialchars($row['remarks']) ?>">
                                                    <?= htmlspecialchars($row['remarks']) ?>
                                                </div>
                                            </td>
                                            <td class="p-5 text-right">
                                                <?php if ($row['served']): ?>
                                                        <div class="flex items-center justify-end gap-3">
                                                            <span class="status-pill bg-emerald-100 text-emerald-700">Claimed</span>
                                                            <form method="POST" class="inline">
                                                                <input type="hidden" name="reset_served_action" value="staff">
                                                                <input type="hidden" name="reset_staff_code" value="<?= htmlspecialchars($row['staff_code']) ?>">
                                                                <button type="submit" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-300 hover:text-rose-500 hover:bg-rose-50 transition-all border border-transparent hover:border-rose-100" title="Reset Status">
                                                                    <i class="fa-solid fa-rotate-left text-xs"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                <?php else: ?>
                                                        <span class="status-pill bg-gray-100 text-gray-400">Available</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                <?php endif; ?>

            </div>

            <!-- Enhanced Footer Bar -->
            <div class="px-6 py-4 bg-gray-50/80 border-t border-white/50 flex justify-between items-center shrink-0">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-pink-500 animate-pulse shadow-[0_0_8px_rgba(236,72,153,0.5)]"></span>
                    <span class="text-[10px] font-black text-pink-600 uppercase tracking-widest">System Live</span>
                </div>
                <div class="text-[10px] font-bold text-gray-400 italic">
                    Secured System Connection
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function filterTable(query) {
        query = query.toLowerCase().trim();
        const rows = document.querySelectorAll('table tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>