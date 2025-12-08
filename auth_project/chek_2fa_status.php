<?php
require_once 'config.php';

echo "<h1>Проверка статуса 2FA</h1>";
echo "<pre>";

// Проверяем текущего пользователя
echo "Текущий пользователь сессии: " . ($_SESSION['user'] ?? 'НЕТ') . "\n";
echo "requires_2fa: " . ($_SESSION['requires_2fa'] ?? 'НЕТ') . "\n";
echo "twofa_pending_user: " . ($_SESSION['twofa_pending_user'] ?? 'НЕТ') . "\n\n";

// Тест для конкретного пользователя
$testUsername = 'Andrew_24'; // Замените на ваше имя пользователя

echo "Проверка 2FA для пользователя: $testUsername\n";
echo "is2FAEnabled('$testUsername'): " . (is2FAEnabled($testUsername) ? '✅ Да' : '❌ Нет') . "\n";

// Проверяем данные пользователя в файле
if (file_exists(USERS_FILE)) {
    $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($users as $user) {
        $data = explode(':', $user);
        if (trim($data[0]) === $testUsername) {
            echo "\nДанные пользователя из файла:\n";
            echo "Username: " . ($data[0] ?? '') . "\n";
            echo "Email: " . ($data[1] ?? '') . "\n";
            echo "2FA статус (поле 3): " . ($data[3] ?? '0') . "\n";
            echo "2FA секрет (поле 4): " . (isset($data[4]) && !empty($data[4]) ? 'Есть' : 'Нет') . "\n";
            if (isset($data[4]) && !empty($data[4])) {
                echo "Секрет (первые 8 символов): " . substr($data[4], 0, 8) . "...\n";
            }
            break;
        }
    }
}

echo "\n\nТест функции isLoggedIn(): " . (isLoggedIn() ? '✅ Да, залогинен' : '❌ Нет') . "\n";

echo "</pre>";

// Ссылки
echo "<p><a href='login.php'>Вернуться на страницу входа</a></p>";
echo "<p><a href='setup_2fa.php'>Настройка 2FA</a></p>";
?>