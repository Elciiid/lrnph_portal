<?php
date_default_timezone_set('Asia/Manila');
$hour = date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour >= 12 && $hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>
<div class="flex justify-between items-end mb-2">
    <div class="welcome-text">
        <h1 class="text-[1.4rem] lg:text-[1.8rem] font-semibold mb-1 text-[#2c2c2c]"><?php echo $greeting; ?>,
            <?php echo explode(' ', $userName)[0]; ?>!
        </h1>
        <p class="text-[#888] text-[0.85rem] lg:text-[1rem] line-clamp-2 md:line-clamp-none">Welcome back to your
            administration workspace. Manage content and settings easily.</p>
    </div>
</div>