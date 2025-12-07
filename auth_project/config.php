<?php
// config.php - Полностью рабочая версия без синтаксических ошибок
session_start();

// Настройки для файлового хранения
define('USERS_FILE', 'users.txt');
define('RESET_TOKENS_FILE', 'reset_tokens.txt');
define('LOGIN_ATTEMPTS_FILE', 'login_attempts.txt');
define('TWO_FA_SECRETS_FILE', 'twofa_secrets.txt');

// ============================================
// 1. Функции для работы с пользователями
// ============================================

function registerUser($username, $email, $password)
{
    // Проверяем, существует ли пользователь
    if (userExists($username)) {
        return ['success' => false, 'message' => 'Пользователь с таким именем уже существует'];
    }

    // Проверяем, существует ли email
    if (emailExists($email)) {
        return ['success' => false, 'message' => 'Пользователь с таким email уже существует'];
    }

    // Проверка сложности пароля
    $passwordErrors = validatePasswordStrength($password);
    if (!empty($passwordErrors)) {
        return ['success' => false, 'message' => implode('. ', $passwordErrors)];
    }

    // Хешируем пароль
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Сохраняем пользователя в формате: username:email:hash:2fa_enabled:2fa_secret
    $userData = $username . ':' . $email . ':' . $hashedPassword . ':0:' . PHP_EOL;

    $result = file_put_contents(USERS_FILE, $userData, FILE_APPEND | LOCK_EX);
    if ($result !== false) {
        return ['success' => true, 'message' => 'Регистрация успешна! Теперь вы можете войти.'];
    } else {
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
        if (isset($data[0]) && $data[0] === $username) {
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
        if (isset($data[1]) && $data[1] === $email) {
            return true;
        }
    }

    return false;
}

function loginUser($login, $password)
{
    if (!file_exists(USERS_FILE)) {
        return ['success' => false, 'message' => 'Пользователь не найден'];
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        $data = explode(':', $user);

        $username = $data[0] ?? null;
        $email = $data[1] ?? null;
        $hash = $data[2] ?? null;

        // Проверяем логин: введённый login может быть username ИЛИ email
        if ($login === $username || $login === $email) {

            // Проверка пароля
            if (password_verify($password, $hash)) {

                $_SESSION['user'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();

                // Проверяем, требуется ли 2FA
                if (isset($data[3]) && $data[3] == 1) {
                    $_SESSION['requires_2fa'] = true;
                    return ['success' => true, 'requires_2fa' => true];
                }

                return ['success' => true, 'requires_2fa' => false];
            }

            // Если пароль неверный
            return ['success' => false, 'message' => 'Неверный пароль'];
        }
    }

    return ['success' => false, 'message' => 'Пользователь не найден'];
}


function isLoggedIn()
{
    return isset($_SESSION['user']) && !isset($_SESSION['requires_2fa']);
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
    // Логируем выход
    if (isset($_SESSION['user'])) {
        logSecurityEvent('LOGOUT', $_SESSION['user']);
    }

    session_unset();
    session_destroy();
    session_start(); // Начинаем новую сессию
}

// ============================================
// 2. Функции для сброса пароля
// ============================================

function initiatePasswordReset($email)
{
    // Проверяем, существует ли пользователь с таким email
    $username = getUsernameByEmail($email);
    if (!$username) {
        // Для безопасности не сообщаем, что email не существует
        return ['success' => true, 'message' => 'Если email существует, инструкции отправлены.'];
    }

    // Генерируем токен
    $token = bin2hex(random_bytes(32));
    $expires = time() + 3600; // Токен действителен 1 час

    // Сохраняем токен
    $resetData = $email . ':' . $token . ':' . $expires . PHP_EOL;
    if (file_put_contents(RESET_TOKENS_FILE, $resetData, FILE_APPEND | LOCK_EX) === false) {
        return ['success' => false, 'message' => 'Ошибка при создании токена сброса'];
    }

    // Для демо показываем ссылку
    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=$token";

    return [
        'success' => true,
        'message' => 'Инструкции по сбросу пароля отправлены на email.',
        'demo_link' => $resetLink // Только для демо
    ];
}

function getUsernameByEmail($email)
{
    if (!file_exists(USERS_FILE)) {
        return null;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        $data = explode(':', $user);
        if (isset($data[1]) && $data[1] === $email) {
            return $data[0] ?? null;
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

            if ($storedToken === $token && $expires > time()) {
                return $email;
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

    // Обновляем пароль пользователя
    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $updatedUsers = [];
    $found = false;

    foreach ($users as $user) {
        $data = explode(':', $user);
        if (isset($data[1]) && $data[1] === $email) {
            $data[2] = password_hash($newPassword, PASSWORD_DEFAULT);
            $found = true;
        }
        $updatedUsers[] = implode(':', $data);
    }

    if ($found) {
        if (file_put_contents(USERS_FILE, implode(PHP_EOL, $updatedUsers) . PHP_EOL, LOCK_EX) !== false) {
            // Удаляем использованный токен
            removeResetToken($token);
            return ['success' => true, 'message' => 'Пароль успешно изменен'];
        }
    }

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
        if (count($parts) >= 2 && $parts[1] !== $token) {
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

    // Очищаем старые попытки (старше 15 минут)
    if (isset($attemptsData[$ip])) {
        $attemptsData[$ip] = array_filter($attemptsData[$ip], function ($attempt) use ($currentTime) {
            return ($currentTime - $attempt) < 900; // 15 минут
        });
    } else {
        $attemptsData[$ip] = [];
    }

    // Добавляем новую попытку
    $attemptsData[$ip][] = $currentTime;

    // Сохраняем попытки
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

    // Очищаем старые попытки
    $recentAttempts = array_filter($attempts, function ($attempt) use ($currentTime) {
        return ($currentTime - $attempt) < 900; // 15 минут
    });

    // Если больше 5 попыток за 15 минут - блокируем
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
// 4. Функции 2FA
// ============================================

function setupSimple2FA($username)
{
    // Генерируем секретный ключ
    $secret = bin2hex(random_bytes(10));

    // Сохраняем секрет
    $twofaData = $username . ':' . $secret . ':0' . PHP_EOL;

    if (file_put_contents(TWO_FA_SECRETS_FILE, $twofaData, FILE_APPEND | LOCK_EX) !== false) {
        // Генерируем QR-код URL
        $qrCodeUri = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" .
            urlencode("otpauth://totp/AuthSystem:" . $username . "?secret=" . $secret . "&issuer=AuthSystem");

        return [
            'success' => true,
            'secret' => $secret,
            'qr_code' => $qrCodeUri
        ];
    }

    return ['success' => false, 'message' => 'Ошибка настройки 2FA'];
}

function verifySimple2FASetup($username, $code)
{
    if (!file_exists(TWO_FA_SECRETS_FILE)) {
        return false;
    }

    $secrets = file(TWO_FA_SECRETS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($secrets as $line) {
        $data = explode(':', $line);
        if (isset($data[0]) && $data[0] === $username) {
            // Упрощенная проверка: сравниваем код с секретом
            // В реальном проекте используйте TOTP алгоритм
            $secret = $data[1] ?? '';

            // Для демо просто проверяем, что код не пустой
            if (!empty($code) && strlen($code) === 6) {
                updateSimple2FAStatus($username, $secret, 1);
                enable2FAForUser($username);
                return true;
            }
        }
    }

    return false;
}

function updateSimple2FAStatus($username, $secret, $verified)
{
    if (!file_exists(TWO_FA_SECRETS_FILE)) {
        return;
    }

    $secrets = file(TWO_FA_SECRETS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $updatedSecrets = [];

    foreach ($secrets as $line) {
        $data = explode(':', $line);
        if (isset($data[0]) && $data[0] === $username) {
            $updatedSecrets[] = $username . ':' . $secret . ':' . $verified;
        } else {
            $updatedSecrets[] = $line;
        }
    }

    file_put_contents(TWO_FA_SECRETS_FILE, implode(PHP_EOL, $updatedSecrets) . PHP_EOL, LOCK_EX);
}

function enable2FAForUser($username)
{
    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $updatedUsers = [];

    foreach ($users as $user) {
        $data = explode(':', $user);
        if (isset($data[0]) && $data[0] === $username) {
            $data[3] = '1'; // Включаем 2FA
        }
        $updatedUsers[] = implode(':', $data);
    }

    $result = file_put_contents(USERS_FILE, implode(PHP_EOL, $updatedUsers) . PHP_EOL, LOCK_EX);
    return $result !== false;
}

function is2FAEnabled($username)
{
    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        $data = explode(':', $user);
        if (isset($data[0]) && $data[0] === $username && isset($data[3]) && $data[3] == 1) {
            return true;
        }
    }

    return false;
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

    // Проверка на распространенные пароли
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
        // Сессия истекла
        logout();
        return false;
    }

    updateLastActivity();
    return true;
}

function logSecurityEvent($event, $userId = null, $details = null)
{
    $logFile = 'security.log';
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

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
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
    // Проверка таймаута сессии
    checkSessionTimeout();

    // Обновление времени активности
    updateLastActivity();

    // Генерация CSRF токена, если его нет
    if (empty($_SESSION['csrf_token'])) {
        generateCSRFToken();
    }

    // Проверка реферера для важных действий
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