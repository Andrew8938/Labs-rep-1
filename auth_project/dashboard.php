<?php
// dashboard.php
require_once 'config.php';

// Если пользователь не авторизован, перенаправляем на страницу входа
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$username = getCurrentUser();
$loginTime = isset($_SESSION['login_time']) ? $_SESSION['login_time'] : time();
$currentTime = time();
$sessionDuration = $currentTime - $loginTime;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - Система авторизации</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .timeout-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .timeout-notification button {
            background: white;
            color: #3498db;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-tachometer-alt"></i> Личный кабинет</h1>
            <p>Защищенная страница для авторизованных пользователей</p>
        </header>

        <nav class="navbar">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Личный кабинет</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти (<?php echo escape($username); ?>)</a>
        </nav>

        <main class="main-content">
            <div class="dashboard">
                <div class="welcome-card">
                    <h2><i class="fas fa-user-circle"></i> Добро пожаловать, <?php echo escape($username); ?>!</h2>
                    <p>Вы успешно вошли в систему и теперь имеете доступ к защищенным страницам.</p>

                    <div class="user-info">
                        <div class="info-box">
                            <i class="fas fa-user"></i>
                            <h3>Имя пользователя</h3>
                            <p><?php echo escape($username); ?></p>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-clock"></i>
                            <h3>Время входа</h3>
                            <p id="login-time"><?php echo date('H:i:s d.m.Y', $loginTime); ?></p>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-history"></i>
                            <h3>Длительность сессии</h3>
                            <p id="session-duration"><?php echo formatDuration($sessionDuration); ?></p>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-shield-alt"></i>
                            <h3>Статус</h3>
                            <p class="status-active">Активен</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-content">
                    <div class="dashboard-card">
                        <h3><i class="fas fa-info-circle"></i> Информация о системе</h3>
                        <p>Вы находитесь на защищенной странице, доступ к которой имеют только авторизованные
                            пользователи.</p>
                        <p>Система аутентификации использует следующие технологии безопасности:</p>
                        <ul>
                            <li><strong>password_hash()</strong> - для безопасного хранения паролей</li>
                            <li><strong>password_verify()</strong> - для проверки паролей</li>
                            <li><strong>Сессии PHP</strong> - для отслеживания состояния входа</li>
                            <li><strong>Защита от XSS</strong> - через htmlspecialchars()</li>
                            <li><strong>Двухфакторная аутентификация (2FA)</strong> - дополнительный уровень
                                безопасности</li>
                        </ul>

                        <div class="session-info">
                            <h4><i class="fas fa-clock"></i> Информация о сессии:</h4>
                            <div class="session-stats">
                                <div class="stat-item">
                                    <span class="stat-label">ID сессии:</span>
                                    <span class="stat-value"><?php echo substr(session_id(), 0, 10) . '...'; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Время до таймаута:</span>
                                    <span id="timeout-timer" class="stat-value">15:00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <h3><i class="fas fa-cogs"></i> Управление аккаунтом</h3>
                        <div class="actions">
                            <a href="change_password.php" class="btn btn-secondary"><i class="fas fa-key"></i> Сменить
                                пароль</a>
                            <a href="notification_settings.php" class="btn btn-secondary"><i
                                    class="fas fa-envelope"></i> Настройки уведомлений</a>
                            <a href="setup_2fa.php" class="btn btn-secondary"><i class="fas fa-shield-alt"></i>
                                Настройка 2FA</a>
                            <a href="logout.php" class="btn btn-primary"><i class="fas fa-sign-out-alt"></i> Выйти из
                                системы</a>
                        </div>
                        <div class="note">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>Сессия автоматически завершится через 15 минут неактивности. Для продления сессии просто
                                обновите страницу или выполните любое действие.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; 2023 Система авторизации. Все права защищены.</p>
            <p class="footer-info">Демонстрация работы с password_hash() и password_verify()</p>
        </footer>
    </div>

    <script>
        // Функция для форматирования времени
        function formatDuration(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;

            if (hours > 0) {
                return hours.toString().padStart(2, '0') + ':' +
                    minutes.toString().padStart(2, '0') + ':' +
                    secs.toString().padStart(2, '0');
            } else {
                return minutes.toString().padStart(2, '0') + ':' +
                    secs.toString().padStart(2, '0');
            }
        }

        // Основной таймер для обновления длительности сессии
        let sessionStartTime = <?php echo $loginTime; ?>; // Время входа в секундах
        let sessionDurationElement = document.getElementById('session-duration');
        let timeoutTimerElement = document.getElementById('timeout-timer');

        function updateSessionTimer() {
            // Текущее время в секундах
            const now = Math.floor(Date.now() / 1000);

            // Обновляем длительность сессии
            const duration = now - sessionStartTime;
            sessionDurationElement.textContent = formatDuration(duration);

            // Обновляем таймер до таймаута (15 минут = 900 секунд)
            const timeoutSeconds = 900 - (duration % 900); // Остаток до следующего таймаута
            const timeoutMinutes = Math.floor(timeoutSeconds / 60);
            const timeoutSecs = timeoutSeconds % 60;
            timeoutTimerElement.textContent =
                timeoutMinutes.toString().padStart(2, '0') + ':' +
                timeoutSecs.toString().padStart(2, '0');

            // Меняем цвет, если осталось мало времени
            if (timeoutSeconds < 300) { // Меньше 5 минут
                timeoutTimerElement.style.color = '#e74c3c';
                timeoutTimerElement.style.fontWeight = 'bold';
            } else if (timeoutSeconds < 600) { // Меньше 10 минут
                timeoutTimerElement.style.color = '#f39c12';
            } else {
                timeoutTimerElement.style.color = '#27ae60';
            }
        }

        // Обновляем каждую секунду
        setInterval(updateSessionTimer, 1000);

        // Запускаем сразу
        updateSessionTimer();

        // Обновляем время последней активности при любом действии пользователя
        document.addEventListener('click', function () {
            updateLastActivity();
        });

        document.addEventListener('keypress', function () {
            updateLastActivity();
        });

        document.addEventListener('scroll', function () {
            updateLastActivity();
        });

        // Функция для обновления времени активности (опционально, можно отправлять на сервер)
        function updateLastActivity() {
            // Здесь можно отправить AJAX запрос на сервер для обновления времени активности
            // fetch('update_activity.php');

            // Локально просто пересчитываем таймер
            updateSessionTimer();
        }

        // Автоматическое обновление таймера при смене вкладки
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                updateSessionTimer();
            }
        });

        // Показываем уведомление при приближении таймаута
        let notificationShown = false;

        function checkTimeoutNotification() {
            const now = Math.floor(Date.now() / 1000);
            const duration = now - sessionStartTime;
            const remaining = 900 - (duration % 900);

            if (remaining <= 300 && !notificationShown) { // 5 минут
                showTimeoutNotification(remaining);
                notificationShown = true;
            } else if (remaining > 300) {
                notificationShown = false;
            }
        }

        function showTimeoutNotification(seconds) {
            const minutes = Math.ceil(seconds / 60);

            // Создаем уведомление
            const notification = document.createElement('div');
            notification.className = 'timeout-notification';
            notification.innerHTML = `
            <i class="fas fa-clock"></i>
            <div>
                <strong>Внимание!</strong>
                <p>Сессия завершится через ${minutes} минут.</p>
                <button onclick="this.parentElement.parentElement.remove()">OK</button>
            </div>
        `;

            document.body.appendChild(notification);

            // Автоматически скрываем через 10 секунд
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 10000);
        }

        // Проверяем уведомления каждые 30 секунд
        setInterval(checkTimeoutNotification, 30000);
        checkTimeoutNotification();
    </script>
</body>

</html>