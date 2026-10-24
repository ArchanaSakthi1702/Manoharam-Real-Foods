<?php

function getSubscriptionPlan(
    PDO $pdo,
    int $planId
): array {

    $stmt = $pdo->prepare("
        SELECT *
        FROM subscription_plans
        WHERE id = ?
    ");

    $stmt->execute([$planId]);

    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception(
            "Subscription plan not found"
        );
    }

    if ($plan["status"] !== "ACTIVE") {
        throw new Exception(
            "Subscription plan is inactive"
        );
    }

    return $plan;
}