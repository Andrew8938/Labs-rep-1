<?php
// login.php - Версия с обязательной 2FA при каждом входе
session_start();
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

// Проверяем, включена ли обязательная 2FA в системе
$is2FARequiredGlobally = true; // Всегда требуем 2FA

// Очищаем старые данные 2FA при заходе на страницу логина
if (isset($_SESSION['twofa_pending_user']) && time() - ($_SESSION['twofa_timestamp'] ?? 0) > 300) {
    unset($_SESSION['twofa_pending_user']);
    unset($_SESSION['twofa_username']);
    unset($_SESSION['twofa_temp_data']);
    unset($_SESSION['twofa_timestamp']);
}

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
                // Всегда требуем 2FA при каждом входе (обязательная настройка)
                if ($is2FARequiredGlobally) {
                    // Проверяем, настроена ли у пользователя 2FA
                    if (isset($loginResult['user_id']) && is2FAEnabledById($loginResult['user_id'])) {
                        // 2FA настроена - перенаправляем на верификацию
                        $_SESSION['twofa_pending_user'] = $loginResult['user_id'];
                        $_SESSION['twofa_username'] = $username;
                        $_SESSION['twofa_timestamp'] = time();
                        
                        // Сохраняем данные пользователя для восстановления сессии после 2FA
                        $_SESSION['twofa_temp_data'] = [
                            'user_id' => $loginResult['user_id'],
                            'username' => $username,
                            'timestamp' => time()
                        ];
                        
                        // Логируем начало процесса 2FA
                        logSecurityEvent('2FA_INITIATED', $loginResult['user_id'], 
                            "Начало 2FA для пользователя {$username}");
                        
                        header('Location: verify_2fa.php');
                        exit;
                    } else {
                        // 2FA не настроена у пользователя
                        $message = 'Для входа необходимо настроить двухфакторную аутентификацию.';
                        $messageType = 'error';
                        
                        // Сохраняем данные для настройки 2FA
                        $_SESSION['setup_2fa_user'] = $loginResult['user_id'];
                        $_SESSION['setup_2fa_username'] = $username;
                        
                        // Логируем попытку входа без настроенной 2FA
                        logSecurityEvent('2FA_NOT_SETUP', $loginResult['user_id'], 
                            "Пользователь {$username} пытается войти без настроенной 2FA");
                    }
                } else {
                    // Если бы 2FA не была обязательной (запасной вариант)
                    $_SESSION['user_id'] = $loginResult['user_id'];
                    $_SESSION['username'] = $username;
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    clearLoginAttempts($ip);
                    
                    logSecurityEvent('LOGIN_SUCCESS', $loginResult['user_id'], 
                        "Пользователь {$username} вошел в систему без 2FA");
                    
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
                
                logSecurityEvent('LOGIN_FAILURE', null, 
                    "Неудачная попытка входа для пользователя: {$username}");
            }
        }
    }
}

// Получаем информацию о блокировке для отображения
$attemptsCount = count(getLoginAttempts($ip));

// Проверяем, не перенаправлялись ли мы с verify_2fa.php
if (isset($_SESSION['twofa_redirect_message'])) {
    $message = $_SESSION['twofa_redirect_message'];
    $messageType = 'error';
    unset($_SESSION['twofa_redirect_message']);
}

// Проверяем, не перенаправлялись ли мы с setup_2fa.php
if (isset($_SESSION['setup_2fa_required'])) {
    $message = 'Для входа в систему необходимо настроить двухфакторную аутентификацию.';
    $messageType = 'error';
    unset($_SESSION['setup_2fa_required']);
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Система с обязательной 2FA</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .twofa-notice {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .twofa-notice i {
            font-size: 24px;
        }
        .twofa-notice h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
        }
        .twofa-notice p {
            margin: 0;
            opacity: 0.9;
        }
        .security-features ul {
            list-style: none;
            padding-left: 0;
        }
        .security-features li {
            margin-bottom: 8px;
            padding-left: 25px;
            position: relative;
        }
        .security-features li i {
            position: absolute;
            left: 0;
            top: 2px;
            color: #28a745;
        }
        .timer {
            background: rgba(255,255,255,0.1);
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            text-align: center;
        }
        .warning-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .warning-info i {
            color: #ffc107;
        }
        .recovery-options {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .recovery-options h4 {
            margin-top: 0;
            color: #495057;
        }
        .recovery-options ul {
            list-style: none;
            padding-left: 0;
        }
        .recovery-options li {
            margin-bottom: 10px;
        }
        .recovery-options a {
            color: #007bff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .recovery-options a:hover {
            text-decoration: underline;
        }
        .form-card {
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .auth-links {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .auth-links a {
            color: #007bff;
            text-decoration: none;
        }
        .auth-links a:hover {
            text-decoration: underline;
        }
        .current-user-info {
            background: #e8f4f8;
            border: 1px solid #b3d4fc;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 14px;
        }
        .mandatory-2fa-banner {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            border: 2px solid #dc3545;
        }
        .mandatory-2fa-banner i {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .mandatory-2fa-banner h3 {
            margin: 10px 0;
            font-size: 20px;
        }
        .setup-2fa-prompt {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .setup-2fa-prompt h3 {
            color: #856404;
            margin-top: 0;
        }
        .setup-2fa-prompt .btn {
            margin-top: 15px;
            background: #ffc107;
            color: #856404;
            border: none;
        }
        .setup-2fa-prompt .btn:hover {
            background: #e0a800;
        }
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .info-box li {
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-sign-in-alt"></i> Вход в систему</h1>
            <p>Обязательная двухфакторная аутентификация при каждом входе</p>
        </header>

        <nav class="navbar">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Личный кабинет</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти (<?php echo escape(getCurrentUser()); ?>)</a>
            <?php else: ?>
                <a href="login.php" class="active"><i class="fas fa-sign-in-alt"></i> Вход</a>
                <a href="register.php"><i class="fas fa-user-plus"></i> Регистрация</a>
                <a href="setup_2fa.php"><i class="fas fa-shield-alt"></i> Настройка 2FA</a>
                <a href="help_2fa.php"><i class="fas fa-question-circle"></i> Помощь по 2FA</a>
            <?php endif; ?>
        </nav>

        <main class="main-content">
            <div class="auth-form-container">
                <div class="form-card">
                    <h2><i class="fas fa-lock"></i> Авторизация с обязательной 2FA</h2>

                    <!-- Баннер обязательной 2FA -->
                    <div class="mandatory-2fa-banner">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Обязательная двухфакторная аутентификация</h3>
                        <p>Для входа в систему необходимо пройти двухфакторную аутентификацию.</p>
                        <p><small>Требуется при каждом входе для максимальной безопасности</small></p>
                    </div>

                    <!-- Информация о текущем пользователе -->
                    <?php if (isLoggedIn()): ?>
                        <div class="current-user-info">
                            <i class="fas fa-info-circle"></i>
                            <span>Вы уже вошли как: <strong><?php echo escape(getCurrentUser()); ?></strong></span>
                            <a href="logout.php" style="float: right; font-size: 12px;">
                                <i class="fas fa-sign-out-alt"></i> Выйти
                            </a>
                        </div>
                    <?php endif; ?>

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
                                <li><a href="reset_attempts.php"><i class="fas fa-unlock"></i> Запрос разблокировки</a></li>
                                <li><a href="contact.php"><i class="fas fa-headset"></i> Связаться с поддержкой</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <?php if ($message): ?>
                            <div class="message <?php echo escape($messageType); ?>">
                                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                                <?php echo escape($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($attemptsCount > 0 && !$isBlocked): ?>
                            <div class="warning-info">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Неудачных попыток: <?php echo $attemptsCount; ?> из 5</span>
                            </div>
                        <?php endif; ?>

                        <!-- Если пользователь пытался войти без настроенной 2FA -->
                        <?php if (isset($_SESSION['setup_2fa_user'])): ?>
                            <div class="setup-2fa-prompt">
                                <h3><i class="fas fa-exclamation-triangle"></i> Требуется настройка 2FA</h3>
                                <p>У вас не настроена двухфакторная аутентификация. Для входа в систему необходимо настроить 2FA.</p>
                                <a href="setup_2fa.php" class="btn">
                                    <i class="fas fa-cog"></i> Настроить 2FA сейчас
                                </a>
                                <p style="margin-top: 15px; font-size: 14px;">
                                    <a href="help_2fa.php"><i class="fas fa-question-circle"></i> Нужна помощь по настройке 2FA?</a>
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if (!isLoggedIn()): ?>
                            <div class="info-box">
                                <h4><i class="fas fa-info-circle"></i> Процесс входа с обязательной 2FA:</h4>
                                <ol>
                                    <li>Введите имя пользователя и пароль</li>
                                    <li>После проверки учетных данных будет запущена двухфакторная аутентификация</li>
                                    <li>Введите 6-значный код из приложения Google Authenticator</li>
                                    <li>Получите доступ к системе</li>
                                </ol>
                                <p><strong>Примечание:</strong> 2FA требуется при каждом входе в систему.</p>
                            </div>

                            <form method="POST" action="" class="auth-form" id="loginForm">
                                <input type="hidden" name="csrf_token" value="<?php echo escape(generateCSRFToken()); ?>">

                                <div class="form-group">
                                    <label for="username"><i class="fas fa-user"></i> Имя пользователя или Email</label>
                                    <input type="text" id="username" name="username"
                                        value="<?php echo isset($_POST['username']) ? escape($_POST['username']) : ''; ?>"
                                        required <?php echo $isBlocked ? 'disabled' : ''; ?>
                                        placeholder="Введите имя пользователя или email"
                                        autocomplete="username">
                                </div>

                                <div class="form-group">
                                    <label for="password"><i class="fas fa-key"></i> Пароль</label>
                                    <div style="position: relative;">
                                        <input type="password" id="password" name="password" required 
                                               <?php echo $isBlocked ? 'disabled' : ''; ?>
                                               placeholder="Введите ваш пароль"
                                               autocomplete="current-password">
                                        <button type="button" id="togglePassword" 
                                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group remember-me">
                                    <input type="checkbox" id="remember" name="remember">
                                    <label for="remember">Запомнить это устройство (только для 2FA)</label>
                                </div>

                                <button type="submit" class="btn btn-primary" <?php echo $isBlocked ? 'disabled' : ''; ?> id="loginButton">
                                    <i class="fas fa-sign-in-alt"></i> Войти и продолжить с 2FA
                                </button>
                                
                                <div style="text-align: center; margin-top: 10px; font-size: 14px; color: #666;">
                                    <i class="fas fa-shield-alt"></i> После входа потребуется код 2FA
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="message info">
                                <i class="fas fa-info-circle"></i>
                                Вы уже авторизованы в системе. Если хотите войти под другим пользователем, 
                                <a href="logout.php">выйдите из текущего аккаунта</a>.
                            </div>
                        <?php endif; ?>

                        <div class="auth-links">
                            <?php if (!isLoggedIn()): ?>
                                <p><a href="forgot_password.php"><i class="fas fa-key"></i> Забыли пароль?</a></p>
                                <p><a href="setup_2fa.php"><i class="fas fa-shield-alt"></i> Настроить двухфакторную аутентификацию</a></p>
                                <p><a href="help_2fa.php"><i class="fas fa-question-circle"></i> Помощь по настройке и использованию 2FA</a></p>
                                <p>Нет аккаунта? <a href="register.php">Зарегистрируйтесь</a></p>
                            <?php endif; ?>
                            <p><a href="index.php"><i class="fas fa-arrow-left"></i> Вернуться на главную</a></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-info">
                    <h3><i class="fas fa-shield-alt"></i> Обязательная 2FA система</h3>
                    
                    <div class="info-box">
                        <h4><i class="fas fa-exclamation-circle"></i> Важная информация:</h4>
                        <ul>
                            <li><strong>2FA обязательна при каждом входе</strong></li>
                            <li>Требуется приложение Google Authenticator</li>
                            <li>Коды 2FA обновляются каждые 30 секунд</li>
                            <li>Резервные коды можно сохранить при настройке</li>
                        </ul>
                    </div>

                    <p>Система использует <strong>обязательную двухфакторную аутентификацию</strong> для максимальной безопасности.</p>
                    
                    <div class="security-features">
                        <h4><i class="fas fa-check-circle"></i> Преимущества обязательной 2FA:</h4>
                        <ul>
                            <li><i class="fas fa-check"></i> Защита от компрометации паролей</li>
                            <li><i class="fas fa-check"></i> Предотвращение несанкционированного доступа</li>
                            <li><i class="fas fa-check"></i> Соответствие стандартам безопасности</li>
                            <li><i class="fas fa-check"></i> Защита от фишинговых атак</li>
                            <li><i class="fas fa-check"></i> Оповещения о попытках входа</li>
                        </ul>
                    </div>

                    <div class="security-features">
                        <h4><i class="fas fa-check-circle"></i> Активные меры защиты:</h4>
                        <ul>
                            <li><i class="fas fa-ban"></i> Ограничение попыток входа (5 попыток за 15 минут)</li>
                            <li><i class="fas fa-clock"></i> Временная блокировка при подозрительной активности</li>
                            <li><i class="fas fa-history"></i> Отслеживание IP-адресов и сессий</li>
                            <li><i class="fas fa-bell"></i> Уведомления о необычных действиях</li>
                            <li><i class="fas fa-shield-alt"></i> Обязательная двухфакторная аутентификация</li>
                            <li><i class="fas fa-lock"></i> Защита от CSRF-атак</li>
                        </ul>
                    </div>

                    <div class="security-tip">
                        <i class="fas fa-user-shield"></i>
                        <div>
                            <strong>Советы по безопасности с 2FA:</strong>
                            <ol style="margin: 5px 0 0 0; padding-left: 20px;">
                                <li>Установите Google Authenticator на свой телефон</li>
                                <li>Сохраните резервные коды в безопасном месте</li>
                                <li>Не передавайте коды 2FA третьим лицам</li>
                                <li>Обновляйте приложение регулярно</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Информация о 2FA -->
                    <div class="security-tip" style="background: #e8f4fd; border-left-color: #17a2b8;">
                        <i class="fas fa-mobile-alt"></i>
                        <div>
                            <strong>Что такое двухфакторная аутентификация?</strong>
                            <p>2FA добавляет второй уровень защиты к вашему аккаунту:</p>
                            <ul style="margin: 5px 0 0 0; padding-left: 20px;">
                                <li>Первый фактор: ваш пароль</li>
                                <li>Второй фактор: 6-значный код из приложения</li>
                            </ul>
                            <p style="margin-top: 10px; font-size: 14px;">
                                <a href="setup_2fa.php" style="color: #17a2b8; text-decoration: underline;">
                                    <i class="fas fa-cog"></i> Настроить 2FA
                                </a> | 
                                <a href="help_2fa.php" style="color: #17a2b8; text-decoration: underline;">
                                    <i class="fas fa-question-circle"></i> Помощь
                                </a>
                            </p>
                        </div>
                    </div>

                    <!-- Статус системы -->
                    <div class="security-tip" style="background: #f0f9ff; border-left-color: #4caf50;">
                        <i class="fas fa-server"></i>
                        <div>
                            <strong>Статус системы:</strong>
                            <ul style="margin: 5px 0 0 0; padding-left: 20px;">
                                <li>Пользователь в сессии: <strong><?php echo isLoggedIn() ? escape(getCurrentUser()) : 'Нет'; ?></strong></li>
                                <li>Режим 2FA: <strong>Обязательный при каждом входе</strong></li>
                                <li>IP статус: <strong><?php echo $isBlocked ? 'Заблокирован' : 'Активен'; ?></strong></li>
                                <li>Попыток входа: <strong><?php echo $attemptsCount; ?>/5</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Система авторизации с обязательной 2FA</p>
            <p class="footer-info">Демонстрация работы системы с обязательной двухфакторной аутентификацией</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const isBlocked = <?php echo $isBlocked ? 'true' : 'false'; ?>;
            const attempts = <?php echo $attemptsCount; ?>;
            const isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;

            // Переключение видимости пароля
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    togglePassword.innerHTML = type === 'password' ? 
                        '<i class="fas fa-eye"></i>' : 
                        '<i class="fas fa-eye-slash"></i>';
                });
            }

            // Автофокус на поле ввода
            if (!isBlocked && !isLoggedIn) {
                const usernameInput = document.getElementById('username');
                if (usernameInput && !usernameInput.value) {
                    usernameInput.focus();
                }
            }

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
                            // Показываем уведомление о разблокировке
                            const unlockMsg = document.createElement('div');
                            unlockMsg.className = 'message success';
                            unlockMsg.innerHTML = '<i class="fas fa-check-circle"></i> IP адрес разблокирован. Вы можете попробовать войти снова.';
                            
                            const formCard = document.querySelector('.form-card');
                            if (formCard) {
                                formCard.insertBefore(unlockMsg, formCard.firstChild);
                            }
                            
                            // Перезагружаем страницу через 3 секунды
                            setTimeout(function () {
                                location.reload();
                            }, 3000);
                        } else {
                            const minutes = Math.floor(timeLeft / 60);
                            const seconds = timeLeft % 60;
                            countdownElement.textContent =
                                minutes.toString().padStart(2, '0') + ':' +
                                seconds.toString().padStart(2, '0');
                        }
                    }, 1000);
                }
            } else if (attempts > 0 && !isBlocked && !isLoggedIn) {
                // Показываем предупреждение о количестве оставшихся попыток
                const warningElement = document.querySelector('.warning-info');
                if (warningElement) {
                    warningElement.style.display = 'flex';
                }
            }

            // Обработка отправки формы
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            
            if (loginForm && loginButton && !isBlocked && !isLoggedIn) {
                loginForm.addEventListener('submit', function(e) {
                    // Обновляем текст кнопки для указания на 2FA
                    loginButton.disabled = true;
                    loginButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Проверка и переход к 2FA...';
                    
                    // Плавное затемнение кнопки
                    loginButton.style.opacity = '0.7';
                    loginButton.style.cursor = 'wait';
                    
                    // Показываем уведомление о переходе к 2FA
                    const infoBox = document.createElement('div');
                    infoBox.className = 'message info';
                    infoBox.style.marginTop = '15px';
                    infoBox.innerHTML = '<i class="fas fa-info-circle"></i> После проверки пароля потребуется ввести код двухфакторной аутентификации.';
                    
                    loginForm.appendChild(infoBox);
                });
            }
            
            // Добавляем информацию о 2FA при фокусе на поле пароля
            if (passwordInput && !isBlocked && !isLoggedIn) {
                passwordInput.addEventListener('focus', function() {
                    const existingInfo = document.querySelector('.password-2fa-info');
                    if (!existingInfo) {
                        const infoDiv = document.createElement('div');
                        infoDiv.className = 'password-2fa-info';
                        infoDiv.style.fontSize = '13px';
                        infoDiv.style.color = '#666';
                        infoDiv.style.marginTop = '5px';
                        infoDiv.innerHTML = '<i class="fas fa-info-circle"></i> После пароля потребуется код из Google Authenticator';
                        
                        const formGroup = passwordInput.closest('.form-group');
                        if (formGroup) {
                            formGroup.appendChild(infoDiv);
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>