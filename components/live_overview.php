<?php
// Planner Statistics Widget
// Replaces Live Overview (Announcements/Apps)

$currentUserId = $_SESSION['username'] ?? '';

// Initialize Data Arrays
$chartData = [
    'day' => array_fill(0, 24, 0),    // 0-23 Hours
    'week' => array_fill(0, 7, 0),    // Sun-Sat
    'month' => [],                    // Days 1-31 (filled dynamically)
    'year' => array_fill(0, 12, 0)    // Jan-Dec
];

// Totals for display
$totals = [
    'day' => 0,
    'week' => 0,
    'month' => 0,
    'year' => 0
];

if (isset($conn) && !empty($currentUserId)) {

    // Helper to get base query condition
    // Use facilitator or attendee check

    $baseJoin = "LEFT JOIN \"prtl_AP_Attendees\" pma ON ps.meeting_id = pma.meeting_id AND pma.employee_id = ?";
    $baseWhere = "(ps.facilitator = ? OR pma.employee_id IS NOT NULL)";
    $paramsUser = array($currentUserId, $currentUserId);

    // 1. Day Data (Hourly)
    $today = date('Y-m-d');
    $sqlDay = "SELECT EXTRACT(HOUR FROM start_time) as hr, COUNT(DISTINCT ps.meeting_id) as count 
               FROM \"prtl_AP_Meetings\" ps 
               $baseJoin 
               WHERE $baseWhere AND ps.meeting_date = ?::date
               GROUP BY hr";
    $stmtDay = $conn->prepare($sqlDay);
    $stmtDay->execute(array_merge($paramsUser, [$today]));
    while ($row = $stmtDay->fetch(PDO::FETCH_ASSOC)) {
        $hr = (int)$row['hr'];
        $chartData['day'][$hr] = (int)$row['count'];
        $totals['day'] += (int)$row['count'];
    }

    // 2. Week Data (Daily)
    $sunday = date('Y-m-d', strtotime('last Sunday', strtotime('tomorrow')));
    $saturday = date('Y-m-d', strtotime('next Saturday', strtotime('yesterday')));

    $sqlWeek = "SELECT ps.meeting_date, COUNT(DISTINCT ps.meeting_id) as count 
                FROM \"prtl_AP_Meetings\" ps 
                $baseJoin 
                WHERE $baseWhere AND ps.meeting_date BETWEEN ?::date AND ?::date
                GROUP BY ps.meeting_date";
    $stmtWeek = $conn->prepare($sqlWeek);
    $stmtWeek->execute(array_merge($paramsUser, [$sunday, $saturday]));
    while ($row = $stmtWeek->fetch(PDO::FETCH_ASSOC)) {
        $dayIndex = date('w', strtotime($row['meeting_date'])); // 0 (Sun) - 6 (Sat)
        $chartData['week'][$dayIndex] = (int)$row['count'];
        $totals['week'] += (int)$row['count'];
    }

    // 3. Month Data (Daily)
    $firstDayMonth = date('Y-m-01');
    $lastDayMonth = date('Y-m-t');
    $daysInMonth = date('t');
    $chartData['month'] = array_fill(1, (int)$daysInMonth, 0);

    $sqlMonth = "SELECT EXTRACT(DAY FROM ps.meeting_date) as d, COUNT(DISTINCT ps.meeting_id) as count 
                 FROM \"prtl_AP_Meetings\" ps 
                 $baseJoin 
                 WHERE $baseWhere AND ps.meeting_date BETWEEN ?::date AND ?::date
                 GROUP BY d";
    $stmtMonth = $conn->prepare($sqlMonth);
    $stmtMonth->execute(array_merge($paramsUser, [$firstDayMonth, $lastDayMonth]));
    while ($row = $stmtMonth->fetch(PDO::FETCH_ASSOC)) {
        $d = (int)$row['d'];
        $chartData['month'][$d] = (int)$row['count'];
        $totals['month'] += (int)$row['count'];
    }

    // 4. Year Data (Monthly)
    $firstDayYear = date('Y-01-01');
    $lastDayYear = date('Y-12-31');

    $sqlYear = "SELECT EXTRACT(MONTH FROM ps.meeting_date) as m, COUNT(DISTINCT ps.meeting_id) as count 
                FROM \"prtl_AP_Meetings\" ps 
                $baseJoin 
                WHERE $baseWhere AND ps.meeting_date BETWEEN ?::date AND ?::date
                GROUP BY m";
    $stmtYear = $conn->prepare($sqlYear);
    $stmtYear->execute(array_merge($paramsUser, [$firstDayYear, $lastDayYear]));
    while ($row = $stmtYear->fetch(PDO::FETCH_ASSOC)) {
        // Month 1-12 -> Index 0-11
        $m = (int)$row['m'];
        $chartData['year'][$m - 1] = (int)$row['count'];
        $totals['year'] += (int)$row['count'];
    }
}
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div
    class="h-full flex flex-col min-h-0 bg-white p-6 rounded-[24px] shadow-sm border border-gray-100 relative overflow-hidden h-full">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4 z-20 relative">
        <div>
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-pink-500"></i>
                Planner Stats
            </h3>
            <!-- Total display is now only for Chart Views -->
            <p id="chartHeaderTotal" class="text-xs text-gray-400 font-medium ml-7 mt-0.5 hidden">
                Total: <span id="totalDisplay" class="text-pink-600 font-bold text-sm">0</span> <span
                    id="periodDisplay">Today</span>
            </p>
        </div>

        <!-- Filter Dropdown -->
        <div class="relative">
            <select id="statsFilter" onchange="updateStatsView(this.value)"
                class="appearance-none bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-xl focus:ring-pink-500 focus:border-pink-500 block w-32 p-2.5 pr-8 font-semibold cursor-pointer outline-none shadow-sm z-30 transition-all">
                <option value="day" selected>Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
                <option value="year">This Year</option>
            </select>
            <i
                class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[10px] z-40"></i>
        </div>
    </div>

    <!-- View 1: Big Number Display (Day Only) -->
    <div id="dayStatContainer" class="flex-1 flex flex-col items-center justify-center z-10 relative text-center">
        <div class="relative mb-4">
            <!-- Decorative Backing -->
            <div class="absolute inset-0 bg-pink-100 rounded-full blur-xl opacity-50 scale-150 animate-pulse"></div>

            <div
                class="relative bg-white w-40 h-40 rounded-full border-[6px] border-pink-50 flex items-center justify-center shadow-sm">
                <span id="statNumber"
                    class="text-6xl font-extrabold text-gray-800 tracking-tight transition-all duration-700 transform scale-0 opacity-0">
                    0
                </span>
            </div>

            <div
                class="absolute -bottom-2 right-4 bg-pink-500 text-white w-10 h-10 rounded-full flex items-center justify-center border-4 border-white shadow-md">
                <i class="fa-solid fa-calendar-check text-sm"></i>
            </div>
        </div>

        <p class="text-gray-500 font-medium text-lg mt-4">
            Scheduled Meetings <span id="statLabel">Today</span>
        </p>
        <p class="text-xs text-gray-400 mt-1 max-w-[200px]">
            Based on your planned meetings and appointments.
        </p>
    </div>

    <!-- View 2: Chart Container (Week/Month/Year) -->
    <div id="chartContainer" class="flex-1 w-full min-h-0 relative z-10 hidden">
        <canvas id="plannerChart"></canvas>
    </div>

    <!-- Background Decoration -->
    <div
        class="absolute bottom-0 left-0 w-full h-32 bg-gradient-to-t from-pink-50/50 to-transparent pointer-events-none">
    </div>
    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-purple-50 rounded-full blur-3xl opacity-50"></div>
</div>

<script>
    // Data from PHP
    const rawData = {
        day: <?php echo json_encode(array_values($chartData['day'])); ?>,
        week: <?php echo json_encode(array_values($chartData['week'])); ?>,
        month: <?php echo json_encode(array_values($chartData['month'])); ?>,
        year: <?php echo json_encode(array_values($chartData['year'])); ?>
    };

    const totals = {
        day: <?php echo $totals['day']; ?>,
        week: <?php echo $totals['week']; ?>,
        month: <?php echo $totals['month']; ?>,
        year: <?php echo $totals['year']; ?>
    };

    // Configuration for X-Axis Labels
    const labelsConfig = {
        day: Array.from({ length: 24 }, (_, i) => (i === 0 ? '12 AM' : i === 12 ? '12 PM' : i > 12 ? (i - 12) + ' PM' : i + ' AM')),
        week: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        month: Array.from({ length: <?php echo $daysInMonth ?? 30; ?> }, (_, i) => i + 1),
        year: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    };

    let plannerChart = null;

    function initChart() {
        const ctx = document.getElementById('plannerChart').getContext('2d');

        // Gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(236, 72, 153, 0.5)'); // Pink-500
        gradient.addColorStop(1, 'rgba(236, 72, 153, 0.0)');

        const config = {
            type: 'line', // Default
            data: {
                labels: labelsConfig.week, // Default to week logic for init
                datasets: [{
                    label: 'Meetings',
                    data: rawData.week,
                    borderColor: '#ec4899',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#ec4899',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: false,
                    tension: 0,
                    borderJoinStyle: 'round',
                    spanGaps: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 800,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#fff',
                        titleColor: '#1a1a1a',
                        bodyColor: '#ec4899',
                        borderColor: '#fce7f3',
                        borderWidth: 1,
                        displayColors: false,
                        padding: 10,
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y + ' Meetings';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { display: false },
                        ticks: { stepSize: 1, color: '#9ca3af', font: { size: 10 } },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#9ca3af', font: { size: 10 }, maxTicksLimit: 8 },
                        border: { display: false }
                    }
                }
            }
        };

        plannerChart = new Chart(ctx, config);
        // Do NOT run updateHeader('day') here because typical init state for dropdown is 'day'
        // which means we want the NUMBER view, not the chart.
        // updateStatsView('day') will handle showing the correct container.
    }

    function updateStatsView(period) {
        const dayContainer = document.getElementById('dayStatContainer');
        const chartContainer = document.getElementById('chartContainer');
        const chartHeaderTotal = document.getElementById('chartHeaderTotal');

        // Logic: If 'day', show Big Number. Else, show Chart.
        if (period === 'day') {
            dayContainer.classList.remove('hidden');
            chartContainer.classList.add('hidden');
            chartHeaderTotal.classList.add('hidden');

            // Update Number
            animateNumber(totals['day']);
        } else {
            dayContainer.classList.add('hidden');
            chartContainer.classList.remove('hidden');
            chartHeaderTotal.classList.remove('hidden');

            // Initialize chart if needed (lazy load)
            if (!plannerChart) initChart();

            // Update Chart Data
            plannerChart.data.labels = labelsConfig[period];
            plannerChart.data.datasets[0].data = rawData[period];

            // Adjust Dataset Type/Style dynamically (more stable than changing config.type)
            if (period === 'week') {
                plannerChart.data.datasets[0].type = 'bar';
                plannerChart.data.datasets[0].borderRadius = 6;
                plannerChart.data.datasets[0].fill = false;
                plannerChart.data.datasets[0].tension = 0;
            } else {
                plannerChart.data.datasets[0].type = 'line';
                plannerChart.data.datasets[0].borderRadius = 0;
                plannerChart.data.datasets[0].fill = true;
                // Using 0.3 tension to prevent path artifacts (white lines)
                plannerChart.data.datasets[0].tension = 0.3;
                plannerChart.data.datasets[0].pointRadius = 4;
            }
            plannerChart.update();
            updateChartHeader(period);
        }
    }

    function updateChartHeader(period) {
        const periodText = {
            week: 'This Week',
            month: 'This Month',
            year: 'This Year'
        };
        document.getElementById('totalDisplay').textContent = totals[period];
        document.getElementById('periodDisplay').textContent = periodText[period];
    }

    function animateNumber(val) {
        const numEl = document.getElementById('statNumber');

        // Reset state to force re-animation
        numEl.classList.remove('scale-100', 'opacity-100');
        numEl.classList.add('scale-0', 'opacity-0');
        numEl.textContent = '0';

        // Force reflow
        void numEl.offsetWidth;

        const startValue = 0;
        const duration = 1200;
        const startTime = performance.now();

        // Reveal effect
        numEl.classList.remove('scale-0', 'opacity-0');
        numEl.classList.add('scale-100', 'opacity-100');

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Ease out cubic
            const easeProgress = 1 - Math.pow(1 - progress, 3);

            const currentNum = Math.floor(startValue + (val - startValue) * easeProgress);
            numEl.textContent = currentNum;

            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    }

    // Trigger initial animation for Today view
    document.addEventListener('DOMContentLoaded', () => {
        animateNumber(totals['day']);
    });

</script>