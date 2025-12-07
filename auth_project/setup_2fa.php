<?php
// setup_2fa.php - Универсальная версия (заменяет старую)
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$username = getCurrentUser();

// --- Вспомогательные функции, если их ещё нет в config.php ---
if (!function_exists('generate2FASecret')) {
    function generate2FASecret($length = 16)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 chars
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
}

if (!function_exists('generateQRCodeUrl')) {
    function generateQRCodeUrl($username, $secret, $issuer = 'MySecureSite')
    {
        $issuerEnc = rawurlencode($issuer);
        $userEnc = rawurlencode($username);
        $otpauth = "otpauth://totp/{$issuerEnc}:{$userEnc}?secret={$secret}&issuer={$issuerEnc}";
        // Используем chart.googleapis (простой вариант)
        return "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . rawurlencode($otpauth);
    }
}

if (!function_exists('base32_decode')) {
    function base32_decode($b32)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper($b32);
        $plain = '';
        $bits = 0;
        $buffer = 0;
        for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
            $val = strpos($alphabet, $b32[$i]);
            if ($val === false)
                continue;
            $buffer = ($buffer << 5) | $val;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $plain .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        return $plain;
    }
}

if (!function_exists('generateTOTPCode')) {
    function generateTOTPCode($secret, $timestamp)
    {
        $key = base32_decode($secret);
        $time = pack('N*', 0) . pack('N*', $timestamp); // 64-bit BE
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $binary =
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff);
        $otp = $binary % 1000000;
        return str_pad($otp, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('verifyTOTP')) {
    function verifyTOTP($secret, $code, $window = 1)
    {
        $time = floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (generateTOTPCode($secret, $time + $i) === (string) $code) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('save2FASecret')) {
    function save2FASecret($username, $secret)
    {
        if (!file_exists(USERS_FILE))
            return false;
        $lines = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $out = [];
        $found = false;
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            // гарантируем минимум 5 элементов: username:email:hash:2fa_flag:2fa_secret
            for ($i = 0; $i < 5; $i++) {
                if (!isset($parts[$i]))
                    $parts[$i] = '';
            }
            if ($parts[0] === $username) {
                $parts[3] = '1';
                $parts[4] = $secret;
                $found = true;
            }
            $out[] = implode(':', $parts);
        }
        if (!$found)
            return false;
        file_put_contents(USERS_FILE, implode(PHP_EOL, $out) . PHP_EOL, LOCK_EX);
        return true;
    }
}

if (!function_exists('setupSimple2FA')) {
    function setupSimple2FA($username)
    {
        $secret = generate2FASecret();
        $qr = generateQRCodeUrl($username, $secret);
        // Сохраняем временно в сессии до подтверждения
        $_SESSION['pending_2fa_secret'] = $secret;
        $_SESSION['pending_2fa_user'] = $username;
        $_SESSION['pending_2fa_time'] = time();
        return ['success' => true, 'secret' => $secret, 'qr_code' => $qr];
    }
}

if (!function_exists('verifySimple2FASetup')) {
    function verifySimple2FASetup($username, $code)
    {
        if (empty($_SESSION['pending_2fa_secret']) || ($_SESSION['pending_2fa_user'] ?? '') !== $username) {
            return false;
        }
        // Проверка на таймаут (10 минут)
        if (isset($_SESSION['pending_2fa_time']) && time() - $_SESSION['pending_2fa_time'] > 600) {
            unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_user'], $_SESSION['pending_2fa_time']);
            return false;
        }
        $secret = $_SESSION['pending_2fa_secret'];
        if (verifyTOTP($secret, $code)) {
            $saved = save2FASecret($username, $secret);
            if ($saved) {
                unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_user'], $_SESSION['pending_2fa_time']);
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('is2FAEnabled')) {
    function is2FAEnabled($username)
    {
        if (!file_exists(USERS_FILE))
            return false;
        $lines = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (($parts[0] ?? '') === $username && isset($parts[3]) && $parts[3] == '1') {
                return true;
            }
        }
        return false;
    }
}
// --- / вспомогательные функции ---

$message = '';
$messageType = '';
$qrCode = '';
$secret = '';

// Если 2FA уже включена
if (is2FAEnabled($username)) {
    $message = 'Двухфакторная аутентификация уже включена для вашего аккаунта.';
    $messageType = 'info';
}

// Обработка POST: 1) начать setup, 2) подтвердить код
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF, если есть функция
    if (function_exists('validateCSRFToken')) {
        $csrf = $_POST['csrf'] ?? '';
        if (!validateCSRFToken($csrf)) {
            $message = 'Ошибка безопасности (CSRF).';
            $messageType = 'error';
        }
    }

    // Если нажали "Настроить"
    if (isset($_POST['setup_2fa']) && empty($message)) {
        $res = setupSimple2FA($username);
        if (!empty($res['success'])) {
            $qrCode = $res['qr_code'] ?? '';
            $secret = $res['secret'] ?? '';
        } else {
            $message = $res['message'] ?? 'Ошибка при генерации 2FA';
            $messageType = 'error';
        }
    }

    // Если отправили код подтверждения
    if (isset($_POST['verify_code']) && empty($message)) {
        $code = trim($_POST['code'] ?? '');
        if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
            $message = 'Введите корректный 6-значный код.';
            $messageType = 'error';
        } else {
            if (verifySimple2FASetup($username, $code)) {
                $message = 'Двухфакторная аутентификация успешно активирована.';
                $messageType = 'success';
            } else {
                $message = 'Неверный код или время действия кода истекло.';
                $messageType = 'error';
            }
        }
    }
}

// Если есть отложенный секрет — показываем QR (чтобы пользователь мог подтвердить)
if (empty($qrCode) && !empty($_SESSION['pending_2fa_secret']) && ($_SESSION['pending_2fa_user'] ?? '') === $username) {
    $secret = $_SESSION['pending_2fa_secret'];
    $qrCode = generateQRCodeUrl($username, $secret);
}

// --- HTML ниже (упрощённый, готов к использованию) ---
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Настройка 2FA</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 15px
        }

        .qr-code {
            max-width: 280px;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 8px;
            background: #fff
        }

        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 6px
        }

        .message.info {
            background: #e7f3fe;
            border-left: 4px solid #2b7cbf
        }

        .message.success {
            background: #e6f7ea;
            border-left: 4px solid #259b4a
        }

        .message.error {
            background: #fff3f3;
            border-left: 4px solid #d64545
        }

        .form-row {
            margin: 10px 0
        }

        .btn {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            border: 0;
            cursor: pointer
        }

        .btn.primary {
            background: #1976d2;
            color: white
        }

        .btn.secondary {
            background: #6c757d;
            color: white
        }

        .code-input {
            font-family: monospace;
            font-size: 1.2rem;
            padding: 8px;
            width: 120px;
            text-align: center
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Настройка двухфакторной аутентификации (2FA)</h1>

        <?php if ($message): ?>
            <div class="message <?php echo escape($messageType); ?>">
                <?php echo escape($message); ?>
            </div>
        <?php endif; ?>

        <?php if (is2FAEnabled($username)): ?>
            <p>2FA уже включена для аккаунта <strong><?php echo escape($username); ?></strong>.</p>
            <p>Если хотите отключить — используйте соответствующую функцию в профиле.</p>
            <p><a href="dashboard.php" class="btn secondary">Вернуться в личный кабинет</a></p>
        <?php else: ?>

            <?php if (!empty($qrCode) && !empty($secret)): ?>
                <h2>1) Сканируйте QR-код</h2>
                <img src="<?php echo escape($qrCode); ?>" alt="QR Code" class="qr-code">
                <p>Или введите секрет вручную: <strong><?php echo chunk_split($secret, 4, ' '); ?></strong></p>

                <h2>2) Подтвердите, введя код приложения</h2>
                <form method="post" action="">
                    <?php if (function_exists('generateCSRFToken')): ?>
                        <input type="hidden" name="csrf" value="<?php echo escape(generateCSRFToken()); ?>">
                    <?php endif; ?>
                    <div class="form-row">
                        <input type="text" name="code" required pattern="\d{6}" maxlength="6" class="code-input"
                            placeholder="123456" autofocus>
                    </div>
                    <div class="form-row">
                        <button type="submit" name="verify_code" class="btn primary">Подтвердить и включить 2FA</button>
                        <a href="dashboard.php" class="btn secondary">Отменить</a>
                    </div>
                </form>

            <?php else: ?>

                <div class="setup-prompt">
                    <p>2FA добавляет дополнительную защиту — при каждом входе потребуется код из приложения (Google
                        Authenticator/Authy).</p>
                    <form method="post" action="">
                        <?php if (function_exists('generateCSRFToken')): ?>
                            <input type="hidden" name="csrf" value="<?php echo escape(generateCSRFToken()); ?>">
                        <?php endif; ?>
                        <button type="submit" name="setup_2fa" class="btn primary">Настроить 2FA</button>
                        <a href="dashboard.php" class="btn secondary">Отменить</a>
                    </form>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>
</body>

</html>