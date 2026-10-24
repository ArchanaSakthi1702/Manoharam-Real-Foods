<?php

function calculateSubscriptionPricing(
    float $pricePerWeek,
    int $weeks,
    int $customSkipCount
): array {

    $baseAmount = round(
        $pricePerWeek * $weeks,
        2
    );

    $totalDeliveries = $weeks * 7;

    $perDayAmount =
        $baseAmount / $totalDeliveries;

    $discountAmount = round(
        $customSkipCount * $perDayAmount,
        2
    );

    $totalAmount = round(
        $baseAmount - $discountAmount,
        2
    );

    return [
        "base_amount" => $baseAmount,
        "discount_amount" => $discountAmount,
        "per_day_amount" => $perDayAmount,
        "total_amount" => $totalAmount
    ];
}