<?php
// Начинаем сессию только если она еще не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Настройки для файлового хранения
define('USERS_FILE', 'users.txt');
define('RESET_TOKENS_FILE', 'reset_tokens.txt');
define('LOGIN_ATTEMPTS_FILE', 'login_attempts.txt');
define('SECURITY_LOG', 'security.log');

// ============================================
// 1. Функции для работы с пользователями
// ============================================

function registerUser($username, $email, $password)
{
    $username = trim($username);
    $email = trim($email);

    if (userExists($username)) {
        return ['success' => false, 'message' => 'Пользователь с таким именем уже существует'];
    }

    if (emailExists($email)) {
        return ['success' => false, 'message' => 'Пользователь с таким email уже существует'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Некорректный email адрес'];
    }

    $passwordErrors = validatePasswordStrength($password);
    if (!empty($passwordErrors)) {
        return ['success' => false, 'message' => implode('. ', $passwordErrors)];
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $userData = $username . ':' . $email . ':' . $hashedPassword . ':0:' . PHP_EOL;

    $result = file_put_contents(USERS_FILE, $userData, FILE_APPEND | LOCK_EX);
    if ($result !== false) {
        logSecurityEvent('REGISTRATION_SUCCESS', $username, $email);
        return ['success' => true, 'message' => 'Регистрация успешна! Теперь вы можете войти.'];
    } else {
        logSecurityEvent('REGISTRATION_FAILED', $username, 'File write error');
        return ['success' => false, 'message' => 'Ошибка при сохранении данных'];
    }
}

function userExists($username)
{
    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        $data = explode(':', $user);
        if (isset($data[0]) && trim($data[0]) === trim($username)) {
            return true;
        }
    }

    return false;
}

function emailExists($email)
{
    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        $data = explode(':', $user);
        if (isset($data[1]) && trim($data[1]) === trim($email)) {
            return true;
        }
    }

    return false;
}

function loginUser($login, $password)
{
    $login = trim($login);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (isLoginBlocked($ip)) {
        return ['success' => false, 'message' => 'Слишком много неудачных попыток входа. Попробуйте позже.'];
    }

    if (!file_exists(USERS_FILE)) {
        recordLoginAttempt($ip);
        return ['success' => false, 'message' => 'Пользователь не найден'];
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $found = false;
    $username = null;

    foreach ($users as $user) {
        $data = explode(':', $user);
        while (count($data) < 5) {
            $data[] = '';
        }

        $storedUsername = trim($data[0]);
        $storedEmail = trim($data[1]);
        $hash = $data[2];
        $is2FAEnabled = $data[3];
        $twoFASecret = trim($data[4]);

        if ($login === $storedUsername || $login === $storedEmail) {
            $found = true;
            $username = $storedUsername;

            if (password_verify($password, $hash)) {
                clearLoginAttempts($ip);

                // ✅ ИСПРАВЛЕНИЕ: Правильная проверка 2FA
                if ($is2FAEnabled === '1' && !empty($twoFASecret)) {
                    $_SESSION['twofa_pending_user'] = $username;
                    $_SESSION['twofa_pending_time'] = time();
                    $_SESSION['pending_email'] = $storedEmail;
                    $_SESSION['requires_2fa'] = true; // ✅ Устанавливаем флаг 2FA

                    logSecurityEvent('LOGIN_2FA_REQUIRED', $username);
                    return [
                        'success' => true,
                        'requires_2fa' => true,
                        'username' => $username,
                        'user_id' => $username // ✅ Добавляем user_id для совместимости
                    ];
                }

                // Если 2FA не включена
                $_SESSION['user'] = $username;
                $_SESSION['email'] = $storedEmail;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                unset($_SESSION['requires_2fa'], $_SESSION['twofa_pending_user']); // ✅ Очищаем 2FA флаги

                logSecurityEvent('LOGIN_SUCCESS', $username);
                return [
                    'success' => true,
                    'requires_2fa' => false,
                    'user_id' => $username // ✅ Добавляем user_id для совместимости
                ];
            }
            break;
        }
    }

    recordLoginAttempt($ip);

    if ($found) {
        logSecurityEvent('LOGIN_FAILED_WRONG_PASSWORD', $login);
        return ['success' => false, 'message' => 'Неверный пароль'];
    } else {
        logSecurityEvent('LOGIN_FAILED_USER_NOT_FOUND', $login);
        return ['success' => false, 'message' => 'Пользователь не найден'];
    }
}

// ✅ ИСПРАВЛЕНИЕ: Правильная функция isLoggedIn()
function isLoggedIn()
{
    // Пользователь залогинен если:
    // 1. Есть user в сессии
    // 2. НЕТ requires_2fa (или false)
    // 3. НЕТ twofa_pending_user
    return isset($_SESSION['user']) &&
        !isset($_SESSION['requires_2fa']) &&
        !isset($_SESSION['twofa_pending_user']);
}

function requires2FA()
{
    return isset($_SESSION['requires_2fa']) && $_SESSION['requires_2fa'];
}

function getCurrentUser()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function getCurrentEmail()
{
    return isset($_SESSION['email']) ? $_SESSION['email'] : null;
}

function logout()
{
    if (isset($_SESSION['user'])) {
        logSecurityEvent('LOGOUT', $_SESSION['user']);
    }

    session_unset();
    session_destroy();
    session_start();
}

// ============================================
// 2. Функции для сброса пароля
// ============================================

function initiatePasswordReset($email)
{
    $email = trim($email);
    $username = getUsernameByEmail($email);

    if (!$username) {
        logSecurityEvent('PASSWORD_RESET_REQUEST', null, "Email not found: $email");
        return ['success' => true, 'message' => 'Если email существует, инструкции отправлены.'];
    }

    $token = bin2hex(random_bytes(32));
    $expires = time() + 3600;
    $resetData = $email . ':' . $token . ':' . $expires . PHP_EOL;

    if (file_put_contents(RESET_TOKENS_FILE, $resetData, FILE_APPEND | LOCK_EX) === false) {
        logSecurityEvent('PASSWORD_RESET_FAILED', $username, 'Token creation failed');
        return ['success' => false, 'message' => 'Ошибка при создании токена сброса'];
    }

    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";
    logSecurityEvent('PASSWORD_RESET_REQUEST', $username);

    return [
        'success' => true,
        'message' => 'Инструкции по сбросу пароля отправлены на email.',
        'demo_link' => $resetLink
    ];
}

function getUsernameByEmail($email)
{
    $email = trim($email);

    if (!file_exists(USERS_FILE)) {
        return null;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        $data = explode(':', $user);
        if (isset($data[1]) && trim($data[1]) === $email) {
            return isset($data[0]) ? trim($data[0]) : null;
        }
    }

    return null;
}

function validateResetToken($token)
{
    if (!file_exists(RESET_TOKENS_FILE)) {
        return false;
    }

    $tokens = file(RESET_TOKENS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($tokens as $line) {
        $parts = explode(':', $line);
        if (count($parts) >= 3) {
            list($email, $storedToken, $expires) = $parts;

            if (trim($storedToken) === trim($token) && $expires > time()) {
                return trim($email);
            }
        }
    }

    return false;
}

function resetPassword($token, $newPassword)
{
    $email = validateResetToken($token);
    if (!$email) {
        return ['success' => false, 'message' => 'Неверный или просроченный токен'];
    }

    $passwordErrors = validatePasswordStrength($newPassword);
    if (!empty($passwordErrors)) {
        return ['success' => false, 'message' => implode('. ', $passwordErrors)];
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $updatedUsers = [];
    $found = false;
    $username = null;

    foreach ($users as $user) {
        $data = explode(':', $user);
        if (isset($data[1]) && trim($data[1]) === $email) {
            $data[2] = password_hash($newPassword, PASSWORD_DEFAULT);
            $found = true;
            $username = isset($data[0]) ? trim($data[0]) : null;
        }
        $updatedUsers[] = implode(':', $data);
    }

    if ($found) {
        if (file_put_contents(USERS_FILE, implode(PHP_EOL, $updatedUsers) . PHP_EOL, LOCK_EX) !== false) {
            removeResetToken($token);
            logSecurityEvent('PASSWORD_RESET_SUCCESS', $username);
            return ['success' => true, 'message' => 'Пароль успешно изменен'];
        }
    }

    logSecurityEvent('PASSWORD_RESET_FAILED', $username, 'Password update failed');
    return ['success' => false, 'message' => 'Ошибка при изменении пароля'];
}

function removeResetToken($token)
{
    if (!file_exists(RESET_TOKENS_FILE)) {
        return;
    }

    $tokens = file(RESET_TOKENS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $updatedTokens = [];

    foreach ($tokens as $line) {
        $parts = explode(':', $line);
        if (count($parts) >= 2 && trim($parts[1]) !== trim($token)) {
            $updatedTokens[] = $line;
        }
    }

    file_put_contents(RESET_TOKENS_FILE, implode(PHP_EOL, $updatedTokens) . PHP_EOL, LOCK_EX);
}

// ============================================
// 3. Функции для ограничения попыток входа
// ============================================

function recordLoginAttempt($ip)
{
    if (!file_exists(LOGIN_ATTEMPTS_FILE)) {
        $attemptsData = [];
    } else {
        $data = file_get_contents(LOGIN_ATTEMPTS_FILE);
        $attemptsData = json_decode($data, true);
        if ($attemptsData === null) {
            $attemptsData = [];
        }
    }

    $currentTime = time();

    if (isset($attemptsData[$ip])) {
        $attemptsData[$ip] = array_filter($attemptsData[$ip], function ($attempt) use ($currentTime) {
            return ($currentTime - $attempt) < 900;
        });
    } else {
        $attemptsData[$ip] = [];
    }

    $attemptsData[$ip][] = $currentTime;
    file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode($attemptsData), LOCK_EX);

    return count($attemptsData[$ip]);
}

function getLoginAttempts($ip)
{
    if (!file_exists(LOGIN_ATTEMPTS_FILE)) {
        return [];
    }

    $data = file_get_contents(LOGIN_ATTEMPTS_FILE);
    $attemptsData = json_decode($data, true);
    if ($attemptsData === null) {
        return [];
    }

    return isset($attemptsData[$ip]) ? $attemptsData[$ip] : [];
}

function isLoginBlocked($ip)
{
    $attempts = getLoginAttempts($ip);
    $currentTime = time();

    $recentAttempts = array_filter($attempts, function ($attempt) use ($currentTime) {
        return ($currentTime - $attempt) < 900;
    });

    return count($recentAttempts) >= 5;
}

function clearLoginAttempts($ip)
{
    if (!file_exists(LOGIN_ATTEMPTS_FILE)) {
        return;
    }

    $data = file_get_contents(LOGIN_ATTEMPTS_FILE);
    $attemptsData = json_decode($data, true);
    if ($attemptsData === null) {
        return;
    }

    if (isset($attemptsData[$ip])) {
        unset($attemptsData[$ip]);
        file_put_contents(LOGIN_ATTEMPTS_FILE, json_encode($attemptsData), LOCK_EX);
    }
}

// ============================================
// 4. ФУНКЦИИ 2FA (ДОБАВЛЕНА НУЖНАЯ ФУНКЦИЯ)
// ============================================

function is2FAEnabled($username)
{
    $username = trim($username);

    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        $data = explode(':', $user);
        while (count($data) < 5) {
            $data[] = '';
        }

        if (
            trim($data[0]) === $username &&
            $data[3] === '1' &&
            !empty(trim($data[4]))
        ) {
            return true;
        }
    }

    return false;
}

// ✅ ДОБАВЛЕНА НОВАЯ ФУНКЦИЯ: Проверка 2FA по username
function is2FAEnabledById($username)
{
    return is2FAEnabled($username);
}

function getUser2FASecret($username)
{
    $username = trim($username);

    if (!file_exists(USERS_FILE)) {
        return null;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        $data = explode(':', $user);
        while (count($data) < 5) {
            $data[] = '';
        }

        if (trim($data[0]) === $username) {
            $secret = trim($data[4]);
            return !empty($secret) ? $secret : null;
        }
    }

    return null;
}

function generate2FASecret($length = 16)
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';

    if (function_exists('random_int')) {
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
    } else {
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[mt_rand(0, 31)];
        }
    }

    return $secret;
}

function generateQRCodeUrl($username, $secret, $issuer = 'AuthSystem')
{
    $otpauth = "otpauth://totp/" . rawurlencode($issuer) . ":" . rawurlencode($username) .
        "?secret=" . $secret . "&issuer=" . rawurlencode($issuer);

    return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($otpauth);
}

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

function generateTOTPCode($secret, $timestamp)
{
    $key = base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', floor($timestamp / 30));
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0xF;
    $binary = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    );
    return str_pad($binary % 1000000, 6, '0', STR_PAD_LEFT);
}

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

function setup2FA($username)
{
    $username = trim($username);

    if (is2FAEnabled($username)) {
        return ['success' => false, 'message' => '2FA уже включена для вашего аккаунта.'];
    }

    $secret = generate2FASecret();

    $_SESSION['pending_2fa_secret'] = $secret;
    $_SESSION['pending_2fa_user'] = $username;
    $_SESSION['pending_2fa_time'] = time();

    $qrCode = generateQRCodeUrl($username, $secret);

    return [
        'success' => true,
        'secret' => $secret,
        'qr_code' => $qrCode,
        'message' => 'Секрет сгенерирован. Отсканируйте QR-код.'
    ];
}

function confirm2FASetup($username, $code)
{
    $username = trim($username);

    if (
        !isset($_SESSION['pending_2fa_secret']) ||
        !isset($_SESSION['pending_2fa_user']) ||
        $_SESSION['pending_2fa_user'] !== $username
    ) {
        return false;
    }

    if (
        isset($_SESSION['pending_2fa_time']) &&
        (time() - $_SESSION['pending_2fa_time']) > 600
    ) {
        unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_user'], $_SESSION['pending_2fa_time']);
        return false;
    }

    $secret = $_SESSION['pending_2fa_secret'];

    if (verifyTOTP($secret, $code)) {
        if (save2FASecret($username, $secret)) {
            unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_user'], $_SESSION['pending_2fa_time']);
            return true;
        }
    }

    return false;
}

function save2FASecret($username, $secret)
{
    $username = trim($username);

    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $updatedUsers = [];
    $found = false;

    foreach ($users as $user) {
        $data = explode(':', $user);
        while (count($data) < 5) {
            $data[] = '';
        }

        if (trim($data[0]) === $username) {
            $data[3] = '1';
            $data[4] = $secret;
            $found = true;
        }
        $updatedUsers[] = implode(':', $data);
    }

    if ($found) {
        $result = file_put_contents(USERS_FILE, implode(PHP_EOL, $updatedUsers) . PHP_EOL, LOCK_EX);
        return $result !== false;
    }

    return false;
}

function verify2FALogin($username, $code)
{
    $username = trim($username);
    $secret = getUser2FASecret($username);

    if (empty($secret)) {
        return false;
    }

    return verifyTOTP($secret, $code);
}

function disable2FA($username)
{
    $username = trim($username);

    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $updatedUsers = [];
    $found = false;

    foreach ($users as $user) {
        $data = explode(':', $user);
        while (count($data) < 5) {
            $data[] = '';
        }

        if (trim($data[0]) === $username) {
            $data[3] = '0';
            $data[4] = '';
            $found = true;
        }
        $updatedUsers[] = implode(':', $data);
    }

    if ($found) {
        $result = file_put_contents(USERS_FILE, implode(PHP_EOL, $updatedUsers) . PHP_EOL, LOCK_EX);
        return $result !== false;
    }

    return false;
}

function generateBackupCodes($count = 8)
{
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $code = strtoupper(bin2hex(random_bytes(5)));
        $formattedCode = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 2);
        $codes[] = $formattedCode;
    }
    return $codes;
}

// ============================================
// 5. Функции безопасности
// ============================================

function escape($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function validatePasswordStrength($password)
{
    $errors = [];

    if (strlen($password) < 6) {
        $errors[] = 'Пароль должен содержать минимум 6 символов';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Пароль должен содержать хотя бы одну заглавную букву';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Пароль должен содержать хотя бы одну строчную букву';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Пароль должен содержать хотя бы одну цифру';
    }

    $commonPasswords = ['password', '123456', 'qwerty', 'admin', 'welcome'];
    if (in_array(strtolower($password), $commonPasswords)) {
        $errors[] = 'Этот пароль слишком распространен и ненадежен';
    }

    return $errors;
}

function sanitizeInput($input)
{
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }

    $input = trim($input);
    $input = strip_tags($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $input;
}

function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $token);
}

function updateLastActivity()
{
    $_SESSION['last_activity'] = time();
}

function checkSessionTimeout($timeoutMinutes = 15)
{
    if (
        isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > ($timeoutMinutes * 60)
    ) {
        logout();
        return false;
    }

    updateLastActivity();
    return true;
}

function logSecurityEvent($event, $userId = null, $details = null)
{
    $timestamp = date('Y-m-d H:i:s');
    $userId = $userId ?? (isset($_SESSION['user']) ? $_SESSION['user'] : 'guest');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $logEntry = sprintf(
        "[%s] %s | User: %s | IP: %s | UA: %s | Details: %s\n",
        $timestamp,
        $event,
        $userId,
        $ip,
        $userAgent,
        $details ?? 'none'
    );

    file_put_contents(SECURITY_LOG, $logEntry, FILE_APPEND | LOCK_EX);
}

function checkReferrer()
{
    $allowedDomains = [$_SERVER['HTTP_HOST']];

    if (isset($_SERVER['HTTP_REFERER'])) {
        $referrerHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);

        if (!in_array($referrerHost, $allowedDomains)) {
            logSecurityEvent('POSSIBLE_PHISHING_ATTEMPT', null, $_SERVER['REMOTE_ADDR']);
            return false;
        }
    }

    return true;
}

function initSecurity()
{
    checkSessionTimeout();
    updateLastActivity();

    if (empty($_SESSION['csrf_token'])) {
        generateCSRFToken();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkReferrer();
    }
}

// ============================================
// 6. Вспомогательные функции
// ============================================

function formatDuration($seconds)
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;

    if ($hours > 0) {
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
    } else {
        return sprintf("%02d:%02d", $minutes, $seconds);
    }
}

// Инициализируем безопасность
initSecurity();
?>