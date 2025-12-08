<?php

// notification_settings.php
require_once 'config.php';

// Если пользователь не авторизован, перенаправляем
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$username = getCurrentUser();
$message = '';
$messageType = '';
$currentSettings = [
    'email_notifications' => true,
    'security_alerts' => true,
    'login_notifications' => true,
    'password_change_notifications' => true,
    'newsletter' => false
];

// Загружаем сохраненные настройки (если есть)
$settingsFile = 'user_settings/' . md5($username) . '.json';
if (file_exists($settingsFile)) {
    $savedSettings = json_decode(file_get_contents($settingsFile), true);
    if ($savedSettings) {
        $currentSettings = array_merge($currentSettings, $savedSettings);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF токен
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Ошибка безопасности. Пожалуйста, попробуйте еще раз.';
        $messageType = 'error';
    } else {
        // Получаем новые настройки
        $newSettings = [
            'email_notifications' => isset($_POST['email_notifications']),
            'security_alerts' => isset($_POST['security_alerts']),
            'login_notifications' => isset($_POST['login_notifications']),
            'password_change_notifications' => isset($_POST['password_change_notifications']),
            'newsletter' => isset($_POST['newsletter']),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Сохраняем в файл
        $settingsDir = 'user_settings';
        if (!is_dir($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }

        if (file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT), LOCK_EX)) {
            $currentSettings = $newSettings;
            $message = 'Настройки успешно сохранены!';
            $messageType = 'success';
            logSecurityEvent('SETTINGS_UPDATED', $username, 'Notification settings updated');
        } else {
            $message = 'Ошибка при сохранении настроек';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки уведомлений - Система авторизации</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-category {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .settings-category h3 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }

        .setting-item:hover {
            background: #f0f7ff;
        }

        .setting-item:last-child {
            border-bottom: none;
        }

        .setting-info {
            flex: 1;
        }

        .setting-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .setting-description {
            font-size: 14px;
            color: #7f8c8d;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background-color: #3498db;
        }

        input:checked+.toggle-slider:before {
            transform: translateX(30px);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e8f4fd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #3498db;
        }

        .settings-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .preview-email {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #b3d4fc;
        }

        .preview-email h4 {
            margin-top: 0;
            color: #2c3e50;
        }

        .settings-summary {
            background: #f0f9ff;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .settings-summary ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-bell"></i> Настройки уведомлений</h1>
            <p>Управляйте уведомлениями вашего аккаунта</p>
        </header>

        <nav class="navbar">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Личный кабинет</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти (<?php echo escape($username); ?>)</a>
        </nav>

        <main class="main-content">
            <div class="settings-container">
                <div class="settings-card">
                    <h2><i class="fas fa-cogs"></i> Настройки уведомлений</h2>

                    <?php if ($message): ?>
                        <div class="message <?php echo escape($messageType); ?>">
                            <i
                                class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                            <?php echo escape($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="settings-summary">
                        <h3><i class="fas fa-info-circle"></i> Обзор настроек</h3>
                        <p>На этой странице вы можете настроить типы уведомлений, которые будет получать ваш аккаунт.
                        </p>
                        <ul>
                            <li>Включенные уведомления: <strong>
                                    <?php
                                    $enabledCount = array_sum(array_map(function ($val) {
                                        return is_bool($val) ? $val : 0;
                                    }, $currentSettings));
                                    echo $enabledCount;
                                    ?>
                                </strong></li>
                            <li>Последнее обновление:
                                <strong><?php echo $currentSettings['updated_at'] ?? 'Никогда'; ?></strong>
                            </li>
                        </ul>
                    </div>

                    <form method="POST" action="" id="settingsForm">
                        <input type="hidden" name="csrf_token" value="<?php echo escape(generateCSRFToken()); ?>">

                        <div class="settings-category">
                            <h3><i class="fas fa-shield-alt"></i> Уведомления безопасности</h3>

                            <div class="setting-item">
                                <div style="display: flex; align-items: center;">
                                    <div class="notification-icon">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    <div class="setting-info">
                                        <div class="setting-title">Уведомления о входе</div>
                                        <div class="setting-description">Получать уведомления о каждом входе в ваш
                                            аккаунт</div>
                                    </div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="login_notifications" <?php echo $currentSettings['login_notifications'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div style="display: flex; align-items: center;">
                                    <div class="notification-icon">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <div class="setting-info">
                                        <div class="setting-title">Изменение пароля</div>
                                        <div class="setting-description">Уведомлять при изменении пароля вашего аккаунта
                                        </div>
                                    </div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="password_change_notifications" <?php echo $currentSettings['password_change_notifications'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div style="display: flex; align-items: center;">
                                    <div class="notification-icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="setting-info">
                                        <div class="setting-title">Предупреждения безопасности</div>
                                        <div class="setting-description">Важные уведомления о безопасности аккаунта
                                        </div>
                                    </div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="security_alerts" <?php echo $currentSettings['security_alerts'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-category">
                            <h3><i class="fas fa-envelope"></i> Email уведомления</h3>

                            <div class="setting-item">
                                <div style="display: flex; align-items: center;">
                                    <div class="notification-icon">
                                        <i class="fas fa-envelope-open-text"></i>
                                    </div>
                                    <div class="setting-info">
                                        <div class="setting-title">Основные уведомления</div>
                                        <div class="setting-description">Получать основные уведомления на email</div>
                                    </div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="email_notifications" <?php echo $currentSettings['email_notifications'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div style="display: flex; align-items: center;">
                                    <div class="notification-icon">
                                        <i class="fas fa-newspaper"></i>
                                    </div>
                                    <div class="setting-info">
                                        <div class="setting-title">Рассылка новостей</div>
                                        <div class="setting-description">Получать новости и обновления системы</div>
                                    </div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="newsletter" <?php echo $currentSettings['newsletter'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="preview-email">
                            <h4><i class="fas fa-eye"></i> Пример уведомления</h4>
                            <p>При включенных уведомлениях вы будете получать email такого формата:</p>
                            <div
                                style="background: white; padding: 15px; border-radius: 5px; margin-top: 10px; border: 1px solid #ddd;">
                                <strong>Тема:</strong> Новый вход в ваш аккаунт<br>
                                <strong>Дата:</strong> <?php echo date('d.m.Y H:i'); ?><br>
                                <strong>IP-адрес:</strong> 192.168.1.1<br>
                                <strong>Устройство:</strong> Chrome на Windows 10<br>
                                <hr style="margin: 10px 0;">
                                <small>Если это были не вы, немедленно смените пароль и сообщите в поддержку.</small>
                            </div>
                        </div>

                        <div class="settings-actions">
                            <button type="submit" class="btn btn-primary" id="saveButton">
                                <i class="fas fa-save"></i> Сохранить настройки
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetToDefaults()">
                                <i class="fas fa-undo"></i> Сбросить к умолчаниям
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Отмена
                            </a>
                        </div>
                    </form>

                    <div class="auth-links" style="margin-top: 30px;">
                        <p><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Вернуться в личный кабинет</a></p>
                    </div>
                </div>

                <div class="form-info">
                    <h3><i class="fas fa-question-circle"></i> О настройках уведомлений</h3>

                    <div class="security-tip">
                        <i class="fas fa-lightbulb"></i>
                        <div>
                            <strong>Рекомендации по настройкам:</strong>
                            <p>Для максимальной безопасности рекомендуем включить все уведомления о безопасности.</p>
                        </div>
                    </div>

                    <div class="security-tip" style="background: #f0f9ff; border-left-color: #17a2b8;">
                        <i class="fas fa-bell"></i>
                        <div>
                            <strong>Частота уведомлений:</strong>
                            <p>Уведомления отправляются только при важных событиях и не будут спамить ваш почтовый ящик.
                            </p>
                        </div>
                    </div>

                    <div class="security-tip" style="background: #f8f9fa; border-left-color: #6c757d;">
                        <i class="fas fa-history"></i>
                        <div>
                            <strong>История настроек:</strong>
                            <p>Все изменения настроек сохраняются и могут быть отслежены в журнале безопасности.</p>
                        </div>
                    </div>

                    <div class="settings-help">
                        <h4><i class="fas fa-life-ring"></i> Помощь по настройкам</h4>
                        <ul>
                            <li><i class="fas fa-check"></i> Уведомления о входе помогают отслеживать доступ к аккаунту
                            </li>
                            <li><i class="fas fa-check"></i> Предупреждения безопасности включают подозрительные
                                действия</li>
                            <li><i class="fas fa-check"></i> Email уведомления приходят на адрес, указанный при
                                регистрации</li>
                            <li><i class="fas fa-check"></i> Настройки применяются немедленно после сохранения</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Система авторизации</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const saveButton = document.getElementById('saveButton');
            const form = document.getElementById('settingsForm');

            // Обновляем счетчик включенных настроек
            function updateEnabledCount() {
                const checkboxes = form.querySelectorAll('input[type="checkbox"]');
                let enabledCount = 0;

                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        enabledCount++;
                    }
                });

                // Можно обновить отображение счетчика, если добавить элемент для него
                const counterElement = document.querySelector('.enabled-count');
                if (counterElement) {
                    counterElement.textContent = enabledCount;
                }
            }

            // Обновляем при изменении чекбоксов
            form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', updateEnabledCount);
            });

            // Обработка отправки формы
            if (form) {
                form.addEventListener('submit', function (e) {
                    // Показываем индикатор загрузки
                    saveButton.disabled = true;
                    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';

                    // Форма будет отправлена
                });
            }

            // Инициализируем счетчик
            updateEnabledCount();
        });

        function resetToDefaults() {
            if (confirm('Сбросить все настройки к значениям по умолчанию?')) {
                const checkboxes = document.querySelectorAll('input[type="checkbox"]');

                // Значения по умолчанию
                const defaults = {
                    'email_notifications': true,
                    'security_alerts': true,
                    'login_notifications': true,
                    'password_change_notifications': true,
                    'newsletter': false
                };

                checkboxes.forEach(checkbox => {
                    const name = checkbox.getAttribute('name');
                    if (defaults.hasOwnProperty(name)) {
                        checkbox.checked = defaults[name];
                    }
                });

                // Обновляем UI
                document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    cb.dispatchEvent(new Event('change'));
                });

                alert('Настройки сброшены к значениям по умолчанию. Не забудьте сохранить изменения!');
            }
        }
    </script>
</body>

</html>