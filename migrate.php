<?php

require_once __DIR__ . '/db.php';

header("Content-Type: application/json");

try {
    $sqlFile = __DIR__ . '/migration_phase1_phase2.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }

    $queries = file_get_contents($sqlFile);

    // Split and execute statement by statement
    $statements = array_filter(
        array_map('trim', explode(';', $queries)),
        fn($query) => !empty($query) && stripos($query, 'USE ') !== 0
    );

    $executed = 0;
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
            $executed++;
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Database migration executed successfully",
        "statements_executed" => $executed
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Migration failed: " . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
