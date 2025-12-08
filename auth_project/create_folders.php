<?php
// create_folders.php - скрипт для создания необходимых папок

echo "<h1>Создание папок для системы авторизации</h1>";

// Папки, которые нужно создать
$folders = ['user_settings'];

foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        if (mkdir($folder, 0755, true)) {
            echo "<p style='color: green;'>✓ Папка '$folder' создана успешно</p>";

            // Проверяем права
            if (is_writable($folder)) {
                echo "<p style='color: green;'>✓ Папка '$folder' доступна для записи</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Папка '$folder' создана, но нет прав на запись</p>";
                chmod($folder, 0755);
            }
        } else {
            echo "<p style='color: red;'>✗ Ошибка при создании папки '$folder'</p>";

            // Пробуем создать с другими правами
            if (mkdir($folder, 0777, true)) {
                echo "<p style='color: green;'>✓ Папка '$folder' создана с правами 0777</p>";
            }
        }
    } else {
        echo "<p style='color: blue;'>✓ Папка '$folder' уже существует</p>";

        // Проверяем права
        if (is_writable($folder)) {
            echo "<p style='color: green;'>✓ Папка '$folder' доступна для записи</p>";
        } else {
            echo "<p style='color: red;'>✗ Папка '$folder' существует, но нет прав на запись</p>";
            chmod($folder, 0755);
        }
    }
}

// Проверяем текущую папку
echo "<h2>Информация о текущей папке</h2>";
echo "<p>Текущая директория: " . getcwd() . "</p>";
echo "<p>Путь к скрипту: " . __DIR__ . "</p>";

// Проверяем существование файлов
echo "<h2>Проверка файлов</h2>";
$files = ['change_password.php', 'notification_settings.php', 'config.php', 'index.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ Файл '$file' существует</p>";
        echo "<p>Размер: " . filesize($file) . " байт</p>";
    } else {
        echo "<p style='color: red;'>✗ Файл '$file' НЕ СУЩЕСТВУЕТ</p>";
    }
}

echo "<h2>Дальнейшие действия</h2>";
echo "<p>1. <a href='index.php'>Перейти на главную</a></p>";
echo "<p>2. <a href='dashboard.php'>Перейти в личный кабинет</a></p>";
echo "<p>3. <a href='change_password.php'>Попробовать смену пароля</a></p>";

?>