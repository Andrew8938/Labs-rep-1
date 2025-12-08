<?php
// test_2fa.php - Проверка работы 2FA функций
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Пытаемся подключить config.php
if (!file_exists('config.php')) {
    die("❌ Файл config.php не найден в текущей директории!");
}

require_once 'config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Тестирование 2FA</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; padding: 20px; }";
echo "pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }";
echo ".success { color: green; }";
echo ".error { color: red; }";
echo "</style>";
echo "</head><body>";
echo "<h1>Тестирование 2FA функций</h1>";
echo "<pre>";

// Проверяем, подключен ли config.php
echo "1. Проверка подключения config.php:\n";
if (defined('USERS_FILE')) {
    echo "   ✅ config.php подключен успешно\n";
    echo "   USERS_FILE: " . USERS_FILE . "\n";
} else {
    echo "   ❌ config.php НЕ подключен\n";
}

// Проверяем сессию
echo "\n2. Проверка сессии:\n";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "   ✅ Сессия активна\n";
    echo "   ID сессии: " . session_id() . "\n";
} else {
    echo "   ❌ Сессия не активна\n";
}

// Проверяем основные функции
echo "\n3. Проверка основных функций:\n";

$functions_to_check = [
    'setup2FA',
    'generate2FASecret',
    'generateQRCodeUrl',
    'base32_decode',
    'verifyTOTP',
    'is2FAEnabled'
];

foreach ($functions_to_check as $func) {
    if (function_exists($func)) {
        echo "   ✅ $func() существует\n";
    } else {
        echo "   ❌ $func() НЕ существует\n";
    }
}

// Тестируем функции 2FA
echo "\n4. Тестирование функций:\n";

if (function_exists('generate2FASecret')) {
    try {
        $secret = generate2FASecret();
        echo "   ✅ generate2FASecret() работает\n";
        echo "   Сгенерированный секрет: $secret\n";
        echo "   Длина секрета: " . strlen($secret) . "\n";
    } catch (Exception $e) {
        echo "   ❌ generate2FASecret() ошибка: " . $e->getMessage() . "\n";
    }
}

if (function_exists('generateQRCodeUrl')) {
    $testUsername = 'testuser';
    $testSecret = 'ABCDEFGHIJKLMNOP';
    $qrUrl = generateQRCodeUrl($testUsername, $testSecret);
    echo "   ✅ generateQRCodeUrl() работает\n";
    echo "   QR URL (обрезано): " . substr($qrUrl, 0, 100) . "...\n";
}

// Проверяем, есть ли файлы
echo "\n5. Проверка файлов:\n";
$files_to_check = [
    USERS_FILE,
    'config.php',
    'setup_2fa.php',
    'verify_2fa.php',
    'login.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file существует\n";
    } else {
        echo "   ❌ $file НЕ существует\n";
    }
}

// Тест setup2FA с реальным пользователем
echo "\n6. Тест setup2FA():\n";
if (function_exists('setup2FA')) {
    // Проверяем сессионные переменные перед тестом
    echo "   Перед тестом:\n";
    echo "   - pending_2fa_secret: " . (isset($_SESSION['pending_2fa_secret']) ? 'Есть' : 'Нет') . "\n";
    echo "   - pending_2fa_user: " . ($_SESSION['pending_2fa_user'] ?? 'Нет') . "\n";

    // Создаем тестового пользователя в сессии
    $_SESSION['user'] = 'testuser';

    $result = setup2FA('testuser');
    echo "   Результат setup2FA('testuser'):\n";
    echo "   - Успех: " . ($result['success'] ? '✅ Да' : '❌ Нет') . "\n";
    if (isset($result['message'])) {
        echo "   - Сообщение: " . $result['message'] . "\n";
    }
    if (isset($result['secret'])) {
        echo "   - Секрет: " . $result['secret'] . "\n";
    }

    // Проверяем сессионные переменные после теста
    echo "   После теста:\n";
    echo "   - pending_2fa_secret: " . (isset($_SESSION['pending_2fa_secret']) ? 'Есть (' . substr($_SESSION['pending_2fa_secret'], 0, 8) . '...)' : 'Нет') . "\n";
    echo "   - pending_2fa_user: " . ($_SESSION['pending_2fa_user'] ?? 'Нет') . "\n";
    echo "   - pending_2fa_time: " . (isset($_SESSION['pending_2fa_time']) ? date('H:i:s', $_SESSION['pending_2fa_time']) : 'Нет') . "\n";
} else {
    echo "   ❌ Функция setup2FA() недоступна\n";
}

// Проверяем, есть ли реальные пользователи
echo "\n7. Содержимое users.txt:\n";
if (file_exists(USERS_FILE)) {
    $lines = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "   Всего пользователей: " . count($lines) . "\n";
    if (count($lines) > 0) {
        echo "   Первый пользователь: " . htmlspecialchars($lines[0]) . "\n";

        // Парсим данные первого пользователя
        $data = explode(':', $lines[0]);
        echo "   Данные пользователя:\n";
        echo "   - Username: " . ($data[0] ?? 'Нет') . "\n";
        echo "   - Email: " . ($data[1] ?? 'Нет') . "\n";
        echo "   - 2FA включена: " . (($data[3] ?? '0') == '1' ? '✅ Да' : '❌ Нет') . "\n";
        echo "   - 2FA секрет: " . (isset($data[4]) && !empty($data[4]) ? 'Есть (' . substr($data[4], 0, 8) . '...)' : 'Нет') . "\n";
    }
} else {
    echo "   ❌ Файл users.txt не существует\n";
}

echo "</pre>";

// Ссылки для перехода
echo "<h3>Перейти на:</h3>";
echo "<ul>";
echo "<li><a href='setup_2fa.php'>setup_2fa.php</a> - Настройка 2FA</li>";
echo "<li><a href='login.php'>login.php</a> - Вход</li>";
echo "<li><a href='index.php'>index.php</a> - Главная</li>";
echo "<li><a href='register.php'>register.php</a> - Регистрация</li>";
echo "</ul>";

echo "<h3>Диагностика PHP:</h3>";
echo "<pre>";
echo "PHP версия: " . PHP_VERSION . "\n";
echo "Расширения: \n";
$extensions = ['openssl', 'mbstring', 'json', 'session'];
foreach ($extensions as $ext) {
    echo "  - $ext: " . (extension_loaded($ext) ? '✅' : '❌') . "\n";
}
echo "</pre>";

echo "</body></html>";
?>