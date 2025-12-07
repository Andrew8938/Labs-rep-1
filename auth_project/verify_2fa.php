<?php
// verify_2fa.php - Универсальная версия (заменяет старую)
require_once 'config.php';

// Ожидаем, что loginUser() при обнаружении включённой 2FA
// ставит сессию: $_SESSION['twofa_pending_user'] и $_SESSION['twofa_pending_time']
if (empty($_SESSION['twofa_pending_user'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['twofa_pending_user'];
$message = '';
$messageType = '';

// Подключаем те же вспомогательные функции, если их нет
// (base32_decode, generateTOTPCode, verifyTOTP) — они уже описаны в setup_2fa.php и/или config.php
if (!function_exists('base32_decode')) {
    // Копируем реализацию (как в setup_2fa)
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
        $time = pack('N*', 0) . pack('N*', $timestamp);
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
            if (generateTOTPCode($secret, $time + $i) === (string) $code)
                return true;
        }
        return false;
    }
}

// Функция для получения сохранённого секрета пользователя
if (!function_exists('getUser2FASecret')) {
    function getUser2FASecret($username)
    {
        if (!file_exists(USERS_FILE))
            return null;
        $lines = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (($parts[0] ?? '') === $username) {
                return $parts[4] ?? null;
            }
        }
        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (function_exists('validateCSRFToken')) {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($csrf)) {
            $message = 'Ошибка безопасности (CSRF).';
            $messageType = 'error';
        }
    }

    if (empty($message)) {
        $code = trim($_POST['code'] ?? '');
        if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
            $message = 'Введите корректный 6-значный код.';
            $messageType = 'error';
        } else {
            $secret = getUser2FASecret($username);
            if (empty($secret)) {
                $message = 'Секрет 2FA не найден. Обратитесь к администратору.';
                $messageType = 'error';
            } else {
                if (verifyTOTP($secret, $code)) {
                    // Успех: логиним пользователя окончательно
                    $_SESSION['user'] = $username;
                    $_SESSION['login_time'] = time();
                    $_SESSION['last_activity'] = time();
                    // Удаляем pending
                    unset($_SESSION['twofa_pending_user'], $_SESSION['twofa_pending_time']);
                    if (function_exists('logSecurityEvent')) {
                        logSecurityEvent('2FA_SUCCESS', $username);
                    }
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $message = 'Неверный код. Попробуйте снова.';
                    $messageType = 'error';
                    if (function_exists('logSecurityEvent')) {
                        logSecurityEvent('2FA_FAILURE', $username);
                    }
                }
            }
        }
    }
}

// Если прошло слишком много времени (например >5 минут) — отказываем
if (isset($_SESSION['twofa_pending_time']) && (time() - $_SESSION['twofa_pending_time'] > 300)) {
    unset($_SESSION['twofa_pending_user'], $_SESSION['twofa_pending_time']);
    header('Location: login.php');
    exit;
}

// --- HTML ---
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Подтверждение 2FA</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 700px;
            margin: 20px auto;
            padding: 15px
        }

        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 6px
        }

        .message.error {
            background: #fff3f3;
            border-left: 4px solid #d64545
        }

        .input-code {
            font-family: monospace;
            font-size: 1.4rem;
            padding: 8px;
            width: 160px;
            text-align: center
        }

        .btn {
            padding: 8px 12px;
            border-radius: 6px
        }

        .btn.primary {
            background: #1976d2;
            color: #fff;
            border: 0
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Подтверждение 2FA</h1>
        <p>Вход для: <strong><?php echo escape($username); ?></strong></p>

        <?php if ($message): ?>
            <div class="message <?php echo escape($messageType); ?>"><?php echo escape($message); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php if (function_exists('generateCSRFToken')): ?>
                <input type="hidden" name="csrf_token" value="<?php echo escape(generateCSRFToken()); ?>">
            <?php endif; ?>
            <div style="margin:10px 0">
                <input type="text" name="code" required pattern="\d{6}" maxlength="6" placeholder="123456"
                    class="input-code" autofocus>
            </div>
            <div>
                <button type="submit" class="btn primary">Подтвердить и войти</button>
                <a href="login.php" style="margin-left:10px">Отменить</a>
            </div>
        </form>

        <p style="margin-top:20px;color:#666">Если код не приходит — проверьте синхронизацию времени на устройстве.</p>
    </div>
</body>

</html>