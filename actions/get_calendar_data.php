<?php
// Separate component to just generate the grid HTML for AJAX calls
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$currentMonth = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$currentDay = date('j');
$todayMonth = date('m');
$todayYear = date('Y');

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfWeek = date('w', strtotime("$currentYear-$currentMonth-01")); // 0 (Sun) - 6 (Sat)
$monthName = date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$dateRange = date('M 1, Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) . " - " . date('M t, Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear));

// Return JSON response
$response = [
    'monthName' => $monthName,
    'dateRange' => $dateRange,
    'year' => $currentYear,
    'month' => $currentMonth,
    'gridHtml' => ''
];

// Generate Grid HTML
$gridHtml = '';

// Previous month padding
for ($i = 0; $i < $firstDayOfWeek; $i++) {
    $gridHtml .= '<div class="h-[24px] w-[24px]"></div>';
}

// Days of current month
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

    $classes = ['rounded-full', 'cursor-pointer', 'h-[24px]', 'w-[24px]', 'flex', 'items-center', 'justify-center', 'mx-auto', 'text-[0.7rem]', 'transition-all', 'relative']; // Added relative

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
    $gridHtml .= "<div class='$classStr' $titleAttr>$day</div>";
}
$response['gridHtml'] = $gridHtml;

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>