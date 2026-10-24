<?php

function validateSkipDates(
    array $skipDates,
    string $startDate,
    string $endDate,
    bool $excludeSaturday,
    bool $excludeSunday
): void {

    if (
        count($skipDates) !==
        count(array_unique($skipDates))
    ) {
        throw new Exception(
            "Duplicate skip dates are not allowed"
        );
    }

    foreach ($skipDates as $skipDate) {

        $date = DateTime::createFromFormat(
            'Y-m-d',
            $skipDate
        );

        if (
            !$date ||
            $date->format('Y-m-d') !== $skipDate
        ) {
            throw new Exception(
                "Invalid skip date: $skipDate"
            );
        }

        if (
            $skipDate < $startDate ||
            $skipDate > $endDate
        ) {
            throw new Exception(
                "Skip date $skipDate must be between start_date and end_date"
            );
        }

        $day = (int)$date->format('N');

        if (
            $excludeSaturday &&
            $day === 6
        ) {
            throw new Exception(
                "$skipDate already excluded by Saturday setting"
            );
        }

        if (
            $excludeSunday &&
            $day === 7
        ) {
            throw new Exception(
                "$skipDate already excluded by Sunday setting"
            );
        }
    }
}


function insertWeekendSkips(
    PDO $pdo,
    int $subscriptionId,
    string $startDate,
    string $endDate,
    bool $excludeSaturday,
    bool $excludeSunday
): void {

    $stmt = $pdo->prepare("
        INSERT INTO subscription_skips (
            subscription_id,
            skip_date,
            refund_amount,
            reason,
            skip_type
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $current = new DateTime($startDate);
    $end = new DateTime($endDate);

    while ($current <= $end) {

        $day = (int)$current->format('N');

        $shouldSkip =
            ($day === 6 && $excludeSaturday) ||
            ($day === 7 && $excludeSunday);

        if ($shouldSkip) {

            $stmt->execute([
                $subscriptionId,
                $current->format('Y-m-d'),
                0,
                $day === 6
                    ? 'Saturday excluded'
                    : 'Sunday excluded',
                $day === 6
                    ? 'SATURDAY'
                    : 'SUNDAY'
            ]);
        }

        $current->modify('+1 day');
    }
}


function insertCustomSkips(
    PDO $pdo,
    int $subscriptionId,
    array $skipDates,
    float $refundAmount
): void {

    $stmt = $pdo->prepare("
        INSERT INTO subscription_skips (
            subscription_id,
            skip_date,
            refund_amount,
            reason,
            skip_type
        )
        VALUES (?, ?, ?, ?, 'CUSTOM')
    ");

    foreach ($skipDates as $date) {

        $stmt->execute([
            $subscriptionId,
            $date,
            round($refundAmount, 2),
            'Customer selected skip date'
        ]);
    }
}