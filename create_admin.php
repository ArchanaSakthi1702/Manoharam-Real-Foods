<?php
/**
 * Manoharam Real Foods - Admin Account Creator
 * 
 * Supports:
 *  1. Web Browser GUI: Open in browser (e.g. http://localhost/Manoharam-Real-Foods-main/create_admin.php)
 *  2. Interactive / Argument CLI: Run `php create_admin.php` or `php create_admin.php "Name" "email@test.com" "password" "phone"`
 *  3. REST / JSON API: POST JSON payload with secret_key, name, email, password
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$expectedSecret = $_ENV['ADMIN_SECRET_KEY'] ?? "manoharam_admin_secret_2026";
$isCli = (php_sapi_name() === 'cli');

// ==========================================
// 1. CLI Execution Mode
// ==========================================
if ($isCli) {
    echo "========================================\n";
    echo "  MANOHARAM REAL FOODS - ADMIN CREATOR  \n";
    echo "========================================\n\n";

    // Check if arguments were passed
    $args = array_slice($argv, 1);
    if (count($args) >= 3) {
        $name = trim($args[0]);
        $email = trim($args[1]);
        $password = $args[2];
        $phone = isset($args[3]) ? trim($args[3]) : '';
        $address = isset($args[4]) ? trim($args[4]) : '';
        $secretKey = isset($args[5]) ? trim($args[5]) : $expectedSecret;
    } else {
        // Interactive prompt
        echo "Enter Admin Full Name: ";
        $name = trim(fgets(STDIN));

        echo "Enter Admin Email: ";
        $email = trim(fgets(STDIN));

        echo "Enter Admin Password: ";
        $password = trim(fgets(STDIN));

        echo "Enter Phone Number (optional): ";
        $phone = trim(fgets(STDIN));

        echo "Enter Address (optional): ";
        $address = trim(fgets(STDIN));

        echo "Enter Admin Secret Key (Press enter for default): ";
        $inputSecret = trim(fgets(STDIN));
        $secretKey = !empty($inputSecret) ? $inputSecret : $expectedSecret;
    }

    if (empty($name) || empty($email) || empty($password)) {
        echo "\n[ERROR] Name, email, and password cannot be empty.\n";
        exit(1);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "\n[ERROR] Invalid email format: $email\n";
        exit(1);
    }

    if ($secretKey !== $expectedSecret) {
        echo "\n[ERROR] Invalid admin secret key provided.\n";
        exit(1);
    }

    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo "\n[ERROR] A user with email '$email' already exists (User ID: {$existing['id']}, Role: {$existing['role']}).\n";
            exit(1);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $insert = $pdo->prepare("
            INSERT INTO users (name, email, phone, address, password, role, status)
            VALUES (?, ?, ?, ?, ?, 'ADMIN', 'active')
        ");
        $insert->execute([$name, $email, $phone, $address, $hashedPassword]);
        $newId = $pdo->lastInsertId();

        echo "\n[SUCCESS] Admin account created successfully!\n";
        echo " - Admin ID: $newId\n";
        echo " - Name: $name\n";
        echo " - Email: $email\n";
        echo " - Role: ADMIN\n";
        echo " - Admin Panel URL: http://localhost:5174/\n\n";
        exit(0);
    } catch (Exception $e) {
        echo "\n[DATABASE ERROR] " . $e->getMessage() . "\n";
        exit(1);
    }
}

// ==========================================
// 2. HTTP / Web Execution Mode
// ==========================================
$message = null;
$messageType = null;
$createdUser = null;

$isJsonRequest = (
    stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false ||
    stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = [];
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $inputData = json_decode(file_get_contents("php://input"), true) ?? [];
    } else {
        $inputData = $_POST;
    }

    $name = trim($inputData['name'] ?? '');
    $email = trim($inputData['email'] ?? '');
    $password = $inputData['password'] ?? '';
    $phone = trim($inputData['phone'] ?? '');
    $address = trim($inputData['address'] ?? '');
    $secretKey = trim($inputData['secret_key'] ?? $inputData['admin_secret_key'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $message = "Full name, email, and password are required.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } elseif ($secretKey !== $expectedSecret) {
        $message = "Unauthorized: Invalid Admin Secret Key.";
        $messageType = "error";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = "An account with this email address already exists.";
                $messageType = "error";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, phone, address, password, role, status)
                    VALUES (?, ?, ?, ?, ?, 'ADMIN', 'active')
                ");
                $stmt->execute([$name, $email, $phone, $address, $hashedPassword]);
                $newId = (int)$pdo->lastInsertId();

                $createdUser = [
                    "id" => $newId,
                    "name" => $name,
                    "email" => $email,
                    "role" => "ADMIN"
                ];

                $message = "Administrator account created successfully!";
                $messageType = "success";
            }
        } catch (Exception $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = "error";
        }
    }

    if ($isJsonRequest) {
        header("Content-Type: application/json");
        if ($messageType === "success") {
            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => $message,
                "user" => $createdUser
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => $message
            ]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account - Manoharam Real Foods</title>
    <style>
        :root {
            --chili-500: #d63031;
            --chili-600: #b71516;
            --ink-900: #1a1a1a;
            --ink-700: #333333;
            --ink-500: #666666;
            --cream-50: #fdfbf7;
            --cream-100: #f7f3ec;
            --cream-200: #eee7db;
            --cream-300: #e2d7c5;
            --green-600: #16a34a;
            --green-50: #f0fdf4;
            --red-50: #fef2f2;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        body {
            background-color: var(--cream-100);
            color: var(--ink-900);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .container {
            width: 100%;
            max-width: 500px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--cream-300);
            overflow: hidden;
        }
        .header {
            padding: 28px 32px 20px;
            border-bottom: 1px solid var(--cream-200);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .logo-badge {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--chili-500);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(214, 48, 49, 0.3);
        }
        .header-title h1 {
            font-size: 19px;
            font-weight: 700;
            color: var(--ink-900);
        }
        .header-title p {
            font-size: 13px;
            color: var(--ink-500);
        }
        .content {
            padding: 28px 32px 32px;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .alert.error {
            background: var(--red-50);
            border: 1px solid #fecaca;
            color: var(--chili-600);
        }
        .alert.success {
            background: var(--green-50);
            border: 1px solid #bbf7d0;
            color: var(--green-600);
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--ink-700);
            margin-bottom: 6px;
        }
        .hint {
            font-size: 11px;
            color: var(--ink-500);
            margin-top: 4px;
        }
        input {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1px solid var(--cream-300);
            background: var(--cream-50);
            font-size: 14px;
            color: var(--ink-900);
            outline: none;
            transition: border-color 0.15s ease, background 0.15s ease;
        }
        input:focus {
            border-color: var(--chili-500);
            background: #ffffff;
        }
        .btn-submit {
            width: 100%;
            padding: 13px;
            background: var(--chili-500);
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(214, 48, 49, 0.25);
            transition: background 0.15s ease;
            margin-top: 8px;
        }
        .btn-submit:hover {
            background: var(--chili-600);
        }
        .btn-panel {
            display: inline-block;
            text-align: center;
            width: 100%;
            padding: 12px;
            background: var(--ink-900);
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            text-decoration: none;
            margin-top: 10px;
            transition: background 0.15s ease;
        }
        .btn-panel:hover {
            background: #000000;
        }
        .footer-note {
            margin-top: 20px;
            font-size: 12px;
            color: var(--ink-500);
            text-align: center;
            line-height: 1.5;
        }
        .badge {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 11px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="logo-badge">M</div>
        <div class="header-title">
            <h1>Create Admin Account</h1>
            <p>Manoharam Real Foods Portal</p>
        </div>
    </div>

    <div class="content">
        <?php if ($message): ?>
            <div class="alert <?= htmlspecialchars($messageType) ?>">
                <strong><?= $messageType === 'success' ? 'Success!' : 'Notice:' ?></strong>
                <span><?= htmlspecialchars($message) ?></span>
                <?php if ($createdUser): ?>
                    <div style="margin-top: 8px; font-size: 13px; color: var(--ink-700);">
                        <strong>Admin User ID:</strong> <?= htmlspecialchars($createdUser['id']) ?><br>
                        <strong>Email:</strong> <?= htmlspecialchars($createdUser['email']) ?>
                    </div>
                    <a href="http://localhost:5174/login" target="_blank" class="btn-panel">
                        Open Admin Dashboard (Port 5174) &rarr;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$createdUser): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g. Master Administrator" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email">Admin Email *</label>
                <input type="email" id="email" name="email" required placeholder="e.g. admin@manoharam.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <p class="hint">This email will be used to log in at the admin dashboard.</p>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required placeholder="••••••••••••">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number (Optional)</label>
                <input type="text" id="phone" name="phone" placeholder="e.g. 9876543210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="address">Address (Optional)</label>
                <input type="text" id="address" name="address" placeholder="e.g. Sivakasi, Tamil Nadu" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="secret_key">Admin Secret Key *</label>
                <input type="password" id="secret_key" name="secret_key" required value="<?= htmlspecialchars($_POST['secret_key'] ?? $expectedSecret) ?>">
                <p class="hint">Pre-filled with the active <span class="badge">ADMIN_SECRET_KEY</span> from your <span class="badge">.env</span> file.</p>
            </div>

            <button type="submit" class="btn-submit">Create Administrator</button>
        </form>
        <?php else: ?>
            <p style="text-align: center; margin-top: 15px;">
                <a href="create_admin.php" style="color: var(--chili-500); text-decoration: none; font-size: 13px; font-weight: 600;">
                    + Create another admin account
                </a>
            </p>
        <?php endif; ?>

        <div class="footer-note">
            Frontend apps:
            <br>
            <strong>Admin Panel:</strong> <a href="http://localhost:5174" target="_blank" style="color: var(--ink-700);">http://localhost:5174</a>
            &nbsp;|&nbsp;
            <strong>Storefront:</strong> <a href="http://localhost:5173" target="_blank" style="color: var(--ink-700);">http://localhost:5173</a>
        </div>
    </div>
</div>

</body>
</html>
