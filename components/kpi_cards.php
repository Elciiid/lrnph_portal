<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($stats as $stat): ?>
        <?php
        $accentColor = $stat['color'] ?? 'pink';
        // Simple mapping for gradient/bg colors based on the 'color' key
        $bgClass = "bg-$accentColor-50";
        $textClass = "text-$accentColor-600";
        $iconBg = "bg-$accentColor-500";
        ?>
        <div
            class="bg-white p-5 rounded-[24px] shadow-sm border border-gray-50 relative flex flex-col gap-4 group hover:shadow-xl transition-all duration-300">
            <div class="flex justify-between items-start">
                <div
                    class="w-12 h-12 rounded-xl <?php echo $iconBg; ?> text-white flex items-center justify-center shadow-lg shadow-<?php echo $accentColor; ?>-200">
                    <i class="fa-solid <?php echo $stat['icon'] ?? 'fa-chart-simple'; ?> text-lg"></i>
                </div>
            </div>

            <div>
                <div class="text-[0.8rem] font-bold text-gray-400 uppercase tracking-wider mb-1">
                    <?php echo $stat['title']; ?>
                </div>
                <div class="text-[1.8rem] font-extrabold text-gray-900 leading-none">
                    <?php echo $stat['value']; ?>
                </div>
            </div>

            <div class="flex items-center gap-2 text-[0.75rem] font-semibold">
                <span class="px-2 py-1 rounded-lg <?php echo $bgClass; ?> <?php echo $textClass; ?>">
                    <?php echo $stat['change']; ?>
                </span>
                <span class="text-gray-400">
                    <?php echo $stat['period']; ?>
                </span>
            </div>
        </div>
    <?php endforeach; ?>
</div>