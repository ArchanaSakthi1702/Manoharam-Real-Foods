<?php

function calculateEndDate(
    string $startDate,
    int $weeks,
    bool $excludeSaturday,
    bool $excludeSunday
): string {

    $requiredDays = $weeks * 7;

    $current = new DateTime($startDate);

    $completedDays = 0;

    while ($completedDays < $requiredDays) {

        $day = (int)$current->format('N');

        $isSkipped =
            ($day === 6 && $excludeSaturday) ||
            ($day === 7 && $excludeSunday);

        if (!$isSkipped) {
            $completedDays++;
        }

        if ($completedDays < $requiredDays) {
            $current->modify('+1 day');
        }
    }

    return $current->format('Y-m-d');
}