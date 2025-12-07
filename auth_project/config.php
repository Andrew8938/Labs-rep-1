<?php
// config.php
session_start();

// Настройки
define('USERS_FILE', 'users.txt');

/**
 * Регистрация нового пользователя
 */
function registerUser($username, $password)
{
    // Проверяем, существует ли пользователь
    if (userExists($username)) {
        return ['success' => false, 'message' => 'Пользователь с таким именем уже существует'];
    }

    // Хешируем пароль
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Сохраняем пользователя в формате: username:hash
    $userData = $username . ':' . $hashedPassword . PHP_EOL;

    if (file_put_contents(USERS_FILE, $userData, FILE_APPEND | LOCK_EX) !== false) {
        return ['success' => true, 'message' => 'Регистрация успешна! Теперь вы можете войти.'];
    } else {
        return ['success' => false, 'message' => 'Ошибка при сохранении данных'];
    }
}

/**
 * Проверка существования пользователя
 */
function userExists($username)
{
    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        list($storedUsername, ) = explode(':', $user, 2);
        if ($storedUsername === $username) {
            return true;
        }
    }

    return false;
}

/**
 * Аутентификация пользователя
 */
function loginUser($username, $password)
{
    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        list($storedUsername, $storedHash) = explode(':', $user, 2);

        if ($storedUsername === $username) {
            // Проверяем пароль
            if (password_verify($password, $storedHash)) {
                $_SESSION['user'] = $username;
                $_SESSION['login_time'] = time();
                return true;
            }
            break;
        }
    }

    return false;
}

/**
 * Проверка авторизации
 */
function isLoggedIn()
{
    return isset($_SESSION['user']);
}

/**
 * Получение имени текущего пользователя
 */
function getCurrentUser()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * Выход из системы
 */
function logout()
{
    session_unset();
    session_destroy();
}

/**
 * Защита от XSS атак
 */
function escape($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>