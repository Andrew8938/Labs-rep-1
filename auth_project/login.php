<?php
// login.php - Исправленная версия
require_once 'config.php';

// Проверяем блокировку по IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$isBlocked = isLoginBlocked($ip);

// Если пользователь уже авторизован, перенаправляем на главную
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF токен
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Ошибка безопасности. Пожалуйста, попробуйте еще раз.';
        $messageType = 'error';
        logSecurityEvent('CSRF_FAILURE', null, 'login attempt');
    } elseif ($isBlocked) {
        // Если IP заблокирован
        $message = 'Слишком много неудачных попыток входа. Попробуйте позже.';
        $messageType = 'error';
    } else {
        $username = sanitizeInput(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';

        // Валидация
        if (empty($username) || empty($password)) {
            $message = 'Все поля обязательны для заполнения';
            $messageType = 'error';
        } else {
            // Попытка входа
            $loginResult = loginUser($username, $password);

            if ($loginResult['success']) {
                if (isset($loginResult['requires_2fa']) && $loginResult['requires_2fa']) {
                    // Требуется 2FA
                    header('Location: verify_2fa.php');
                    exit;
                } else {
                    // Вход успешен
                    clearLoginAttempts($ip);
                    header('Location: index.php');
                    exit;
                }
            } else {
                // Неудачная попытка входа
                recordLoginAttempt($ip);
                $message = $loginResult['message'] ?? 'Неверное имя пользователя или пароль';
                $messageType = 'error';

                // Показываем количество оставшихся попыток
                $attempts = count(getLoginAttempts($ip));
                $remainingAttempts = max(0, 5 - $attempts);

                if ($remainingAttempts > 0) {
                    $message .= " (Осталось попыток: $remainingAttempts)";
                } else {
                    $message .= " IP адрес заблокирован на 15 минут.";
                }
            }
        }
    }
}

// Получаем информацию о блокировке для отображения
$attemptsCount = count(getLoginAttempts($ip));
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Система авторизации</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-sign-in-alt"></i> Вход в систему</h1>
            <p>Авторизуйтесь для доступа к защищенным страницам</p>
        </header>

        <nav class="navbar">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Личный кабинет</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти (<?php echo escape(getCurrentUser()); ?>)</a>
            <?php else: ?>
                <a href="login.php" class="active"><i class="fas fa-sign-in-alt"></i> Вход</a>
                <a href="register.php"><i class="fas fa-user-plus"></i> Регистрация</a>
            <?php endif; ?>
        </nav>

        <main class="main-content">
            <div class="auth-form-container">
                <div class="form-card">
                    <h2><i class="fas fa-lock"></i> Авторизация</h2>

                    <?php if ($isBlocked): ?>
                        <div class="message error">
                            <i class="fas fa-ban"></i>
                            <h3>Доступ временно заблокирован</h3>
                            <p>Обнаружено слишком много неудачных попыток входа с вашего IP-адреса.</p>
                            <p>Попробуйте снова через 15 минут.</p>
                            <p class="small">(Защита от брутфорс-атак)</p>
                        </div>

                        <div class="recovery-options">
                            <h4><i class="fas fa-life-ring"></i> Восстановление доступа:</h4>
                            <ul>
                                <li><a href="forgot_password.php"><i class="fas fa-key"></i> Сбросить пароль</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <?php if ($message): ?>
                            <div class="message <?php echo escape($messageType); ?>">
                                <i
                                    class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                                <?php echo escape($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($attemptsCount > 0 && !$isBlocked): ?>
                            <div class="warning-info">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Неудачных попыток: <?php echo $attemptsCount; ?> из 5</span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="auth-form">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(generateCSRFToken()); ?>">

                            <div class="form-group">
                                <label for="username"><i class="fas fa-user"></i> Имя пользователя или Email</label>
                                <input type="text" id="username" name="username"
                                    value="<?php echo isset($_POST['username']) ? escape($_POST['username']) : ''; ?>"
                                    required <?php echo $isBlocked ? 'disabled' : ''; ?>>
                            </div>

                            <div class="form-group">
                                <label for="password"><i class="fas fa-key"></i> Пароль</label>
                                <input type="password" id="password" name="password" required <?php echo $isBlocked ? 'disabled' : ''; ?>>
                            </div>

                            <div class="form-group remember-me">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Запомнить меня</label>
                            </div>

                            <button type="submit" class="btn btn-primary" <?php echo $isBlocked ? 'disabled' : ''; ?>>
                                <i class="fas fa-sign-in-alt"></i> Войти
                            </button>
                        </form>

                        <div class="auth-links">
                            <p><a href="forgot_password.php"><i class="fas fa-key"></i> Забыли пароль?</a></p>
                            <p>Нет аккаунта? <a href="register.php">Зарегистрируйтесь</a></p>
                            <p><a href="index.php"><i class="fas fa-arrow-left"></i> Вернуться на главную</a></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-info">
                    <h3><i class="fas fa-shield-alt"></i> Как работает аутентификация?</h3>
                    <p>При входе система использует функцию <strong>password_verify()</strong> для проверки пароля.</p>
                    <p>Это безопасный метод, который:</p>
                    <ul>
                        <li><i class="fas fa-check"></i> Сравнивает введенный пароль с хешем в хранилище</li>
                        <li><i class="fas fa-check"></i> Защищает от атак перебором (brute-force)</li>
                        <li><i class="fas fa-check"></i> Не требует хранения паролей в открытом виде</li>
                    </ul>

                    <div class="security-features">
                        <h4><i class="fas fa-check-circle"></i> Активные меры защиты:</h4>
                        <ul>
                            <li><i class="fas fa-ban"></i> Ограничение попыток входа (5 попыток за 15 минут)</li>
                            <li><i class="fas fa-clock"></i> Временная блокировка при подозрительной активности</li>
                            <li><i class="fas fa-history"></i> Отслеживание IP-адресов</li>
                            <li><i class="fas fa-bell"></i> Уведомления о необычных действиях</li>
                        </ul>
                    </div>

                    <div class="security-tip">
                        <i class="fas fa-user-shield"></i>
                        <p>Если вы подозреваете, что кто-то пытается получить доступ к вашему аккаунту, немедленно
                            смените пароль.</p>
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
        // Автоматическое отключение формы при блокировке
        document.addEventListener('DOMContentLoaded', function () {
            const isBlocked = <?php echo $isBlocked ? 'true' : 'false'; ?>;
            const attempts = <?php echo $attemptsCount; ?>;

            if (isBlocked) {
                // Показываем таймер разблокировки
                let timeLeft = 15 * 60; // 15 минут в секундах
                const timerElement = document.createElement('div');
                timerElement.className = 'timer';
                timerElement.innerHTML = '<i class="fas fa-clock"></i> До разблокировки: <span id="countdown">15:00</span>';

                const messageElement = document.querySelector('.message.error');
                if (messageElement) {
                    messageElement.appendChild(timerElement);
                }

                // Таймер обратного отсчета
                const countdownElement = document.getElementById('countdown');
                if (countdownElement) {
                    const timerInterval = setInterval(function () {
                        timeLeft--;
                        if (timeLeft <= 0) {
                            clearInterval(timerInterval);
                            countdownElement.textContent = '00:00';
                            // Перезагружаем страницу через 2 секунды
                            setTimeout(function () {
                                location.reload();
                            }, 2000);
                        } else {
                            const minutes = Math.floor(timeLeft / 60);
                            const seconds = timeLeft % 60;
                            countdownElement.textContent =
                                minutes.toString().padStart(2, '0') + ':' +
                                seconds.toString().padStart(2, '0');
                        }
                    }, 1000);
                }
            } else if (attempts > 0) {
                // Показываем предупреждение о количестве оставшихся попыток
                const warningElement = document.querySelector('.warning-info');
                if (warningElement) {
                    warningElement.style.display = 'block';
                }
            }
        });
    </script>
</body>

</html>