<?php
// Set current date context (default to today if not set)
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$currentMonth = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$currentDay = date('j');
$todayMonth = date('m');
$todayYear = date('Y');

// Calculate Next/Prev Month
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$monthName = date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfWeek = date('w', strtotime("$currentYear-$currentMonth-01")); // 0 (Sun) - 6 (Sat)
?>

<div class="bg-white p-4 rounded-[20px] shadow-sm h-full flex flex-col relative w-full overflow-hidden">
    <!-- Header -->
    <div class="flex justify-between items-center mb-4">
        <button onclick="changeMonth(-1)"
            class="bg-transparent border border-[#eeeeee] rounded-full w-8 h-8 flex items-center justify-center cursor-pointer text-[#888] text-[0.8rem] hover:bg-[#f8f9fa] transition-colors">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        <h4 id="calendarMonthName" class="text-[1rem] font-bold text-gray-800 w-32 text-center whitespace-nowrap">
            <?php echo $monthName; ?>
        </h4>
        <button onclick="changeMonth(1)"
            class="bg-transparent border border-[#eeeeee] rounded-full w-8 h-8 flex items-center justify-center cursor-pointer text-[#888] text-[0.8rem] hover:bg-[#f8f9fa] transition-colors">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
    </div>

    <!-- Calendar Grid Container -->
    <div class="flex-1 flex flex-col min-h-0">
        <!-- Days Header -->
        <div class="grid grid-cols-7 gap-1 text-center text-[0.85rem] mb-2">
            <div class="text-[#bbb] text-[0.75rem] font-medium">S</div>
            <div class="text-[#bbb] text-[0.75rem] font-medium">M</div>
            <div class="text-[#bbb] text-[0.75rem] font-medium">T</div>
            <div class="text-[#bbb] text-[0.75rem] font-medium">W</div>
            <div class="text-[#bbb] text-[0.75rem] font-medium">T</div>
            <div class="text-[#bbb] text-[0.75rem] font-medium">F</div>
            <div class="text-[#bbb] text-[0.75rem] font-medium">S</div>
        </div>

        <!-- Days Grid -->
        <div id="calendarGrid" class="grid grid-cols-7 gap-1 text-center">
            <?php
            // Previous month padding
            for ($i = 0; $i < $firstDayOfWeek; $i++) {
                echo '<div class="h-[28px] w-[28px]"></div>'; // Empty placeholder
            }

            // Holidays Definition
            $fixedHolidays = [
                '01-01' => "New Year's Day",
                '02-25' => "EDSA Revolution Anniversary",
                '04-09' => "Araw ng Kagitingan",
                '05-01' => "Labor Day",
                '06-12' => "Independence Day",
                '08-21' => "Ninoy Aquino Day",
                '11-01' => "All Saints' Day",
                '11-02' => "All Souls' Day",
                '11-30' => "Bonifacio Day",
                '12-08' => "Feast of the Immaculate Conception",
                '12-24' => "Christmas Eve",
                '12-25' => "Christmas Day",
                '12-30' => "Rizal Day",
                '12-31' => "Last Day of the Year"
            ];
            $movableHolidays = [
                '2024-03-28' => "Maundy Thursday",
                '2024-03-29' => "Good Friday",
                '2024-03-30' => "Black Saturday",
                '2024-08-26' => "National Heroes Day",
                '2025-01-29' => "Lunar New Year",
                '2025-04-17' => "Maundy Thursday",
                '2025-04-18' => "Good Friday",
                '2025-04-19' => "Black Saturday",
                '2025-08-25' => "National Heroes Day",
                '2026-02-17' => "Lunar New Year",
                '2026-04-02' => "Maundy Thursday",
                '2026-04-03' => "Good Friday",
                '2026-04-04' => "Black Saturday",
                '2026-08-31' => "National Heroes Day",
                '2027-02-06' => "Lunar New Year",
                '2027-03-25' => "Maundy Thursday",
                '2027-03-26' => "Good Friday",
                '2027-03-27' => "Black Saturday",
                '2027-08-30' => "National Heroes Day",
                '2028-01-27' => "Lunar New Year",
                '2028-04-13' => "Maundy Thursday",
                '2028-04-14' => "Good Friday",
                '2028-04-15' => "Black Saturday",
                '2028-08-28' => "National Heroes Day"
            ];

            // Days of current month
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $isToday = ($day == $currentDay && $currentMonth == $todayMonth && $currentYear == $todayYear);

                // Holiday Check
                $dateStr = sprintf("%04d-%02d-%02d", $currentYear, $currentMonth, $day);
                $mdStr = sprintf("%02d-%02d", $currentMonth, $day);
                $holidayName = '';

                if (isset($fixedHolidays[$mdStr]))
                    $holidayName = $fixedHolidays[$mdStr];
                elseif (isset($movableHolidays[$dateStr]))
                    $holidayName = $movableHolidays[$dateStr];

                $classes = ['rounded-full', 'cursor-pointer', 'h-[28px]', 'w-[28px]', 'flex', 'items-center', 'justify-center', 'mx-auto', 'text-[0.75rem]', 'transition-all', 'relative'];

                // Title Attribute
                $titleAttr = $holidayName ? "title=\"$holidayName\"" : "";

                if ($isToday) {
                    $classes[] = 'bg-pink-500 text-white shadow-md shadow-pink-500/30 font-bold';
                } else {
                    $classes[] = 'hover:bg-gray-50 text-gray-700';
                }

                if ($holidayName) {
                    $classes[] = 'ring-2 ring-pink-400 ring-offset-1 z-10'; // Pink outline
                    if (!$isToday)
                        $classes[] = 'font-bold text-pink-600 bg-pink-50/50';
                }

                $classStr = implode(' ', $classes);
                echo "<div class='$classStr' $titleAttr>$day</div>";
            }
            ?>
        </div>
    </div>

    <!-- Calendar Footer Controls -->
    <div class="flex flex-col gap-3 pt-4 mt-auto">
        <!-- Clock Display -->
        <div class="flex gap-2">
            <div id="clockTime"
                class="flex-1 bg-gray-50 rounded-xl py-2 px-3 text-center text-[0.75rem] text-gray-400 font-mono tracking-wider">
                --:--
            </div>
            <div id="clockDate"
                class="flex-1 bg-gray-50 rounded-xl py-2 px-3 text-center text-[0.75rem] text-gray-400 font-medium">
                --
            </div>
        </div>

        <!-- Date Range Info -->
        <div class="flex items-center justify-center">
            <div id="calendarDateRange"
                class="text-[0.7rem] font-bold text-gray-500 bg-gray-50 px-3 py-2 rounded-xl w-full text-center uppercase tracking-wide">
                <?php echo date('M 1, Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) . " - " . date('M t, Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); ?>
            </div>
        </div>
    </div>
</div>

<script>
    let currentCalMonth = <?php echo $currentMonth; ?>;
    let currentCalYear = <?php echo $currentYear; ?>;

    function changeMonth(offset) {
        currentCalMonth += offset;
        if (currentCalMonth > 12) {
            currentCalMonth = 1;
            currentCalYear++;
        } else if (currentCalMonth < 1) {
            currentCalMonth = 12;
            currentCalYear--;
        }

        // Fetch new data
        fetch(`actions/get_calendar_data.php?month=${currentCalMonth}&year=${currentCalYear}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('calendarMonthName').textContent = data.monthName;
                document.getElementById('calendarDateRange').textContent = data.dateRange;
                document.getElementById('calendarGrid').innerHTML = data.gridHtml;
            })
            .catch(error => console.error('Error fetching calendar:', error));
    }

    function updateClock() {
        const now = new Date();
        try {
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
            const dateOptions = { month: 'short', day: 'numeric', year: 'numeric' };

            const timeString = new Intl.DateTimeFormat('en-US', timeOptions).format(now);
            const dateString = new Intl.DateTimeFormat('en-US', dateOptions).format(now);

            document.getElementById('clockTime').textContent = timeString;
            document.getElementById('clockDate').textContent = dateString;
        } catch (e) {
            console.error("Clock error", e);
        }
    }

    setInterval(updateClock, 1000);
    updateClock();
</script>