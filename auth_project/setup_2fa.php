<?php
session_start();
require_once 'config.php';

// Если пользователь не залогинен и нет данных для настройки 2FA
if (!isLoggedIn() && !isset($_SESSION['setup_2fa_user'])) {
    $_SESSION['setup_2fa_required'] = true;
    header('Location: login.php');
    exit;
}

// Определяем username
if (isLoggedIn()) {
    $username = getCurrentUser();
} elseif (isset($_SESSION['setup_2fa_user'])) {
    $username = $_SESSION['setup_2fa_user'];
} else {
    $username = null;
}

$message = '';
$messageType = '';
$secret = '';
$qrCode = '';
$showSetupOptions = true; // Флаг для показа кнопок настройки

// Если 2FA уже включена
if ($username && is2FAEnabled($username)) {
    $message = 'Двухфакторная аутентификация уже настроена для вашего аккаунта.';
    $messageType = 'info';
    $showSetupOptions = false;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF токен
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Ошибка безопасности. Пожалуйста, попробуйте еще раз.';
        $messageType = 'error';
    } elseif (isset($_POST['action'])) {
        if ($_POST['action'] === 'generate') {
            // Генерация нового секрета
            $setupResult = setup2FA($username);

            if ($setupResult['success']) {
                $secret = $setupResult['secret'];
                $qrCode = $setupResult['qr_code'];
                $message = $setupResult['message'];
                $messageType = 'success';
                $showSetupOptions = false;
            } else {
                $message = $setupResult['message'];
                $messageType = 'error';
            }
        } elseif ($_POST['action'] === 'verify') {
            // Верификация кода
            if (empty($_POST['code'])) {
                $message = 'Введите код из приложения';
                $messageType = 'error';
            } else {
                $code = sanitizeInput(trim($_POST['code']));

                if (confirm2FASetup($username, $code)) {
                    if (isset($_SESSION['setup_2fa_user'])) {
                        // Если пользователь настраивал 2FA при регистрации
                        $_SESSION['user'] = $username;
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();
                        unset($_SESSION['setup_2fa_user']);

                        header('Location: dashboard.php');
                        exit;
                    } else {
                        // Если пользователь уже был залогинен
                        $message = '2FA успешно настроена! Теперь при входе потребуется код.';
                        $messageType = 'success';
                        logSecurityEvent('2FA_SETUP_SUCCESS', $username);
                        $showSetupOptions = false;
                    }
                } else {
                    $message = 'Неверный код. Попробуйте еще раз.';
                    $messageType = 'error';
                }
            }
        }
    }
} elseif ($username && isset($_SESSION['pending_2fa_secret'])) {
    // Показываем существующий секрет если он есть
    $secret = $_SESSION['pending_2fa_secret'];
    $qrCode = generateQRCodeUrl($username, $secret);
    $showSetupOptions = false;
}

// Если секрет сгенерирован, но не верифицирован более 10 минут - очищаем
if (isset($_SESSION['pending_2fa_time']) && (time() - $_SESSION['pending_2fa_time']) > 600) {
    unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_user'], $_SESSION['pending_2fa_time']);
    if (!$message) {
        $message = 'Время настройки 2FA истекло. Сгенерируйте новый QR-код.';
        $messageType = 'warning';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройка 2FA - Система авторизации</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 30px auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .setup-step {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .step-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            border-radius: 50%;
            margin-right: 10px;
            font-weight: bold;
        }

        .qr-code-container {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .qr-code {
            max-width: 250px;
            margin: 0 auto;
        }

        .secret-code {
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 16px;
            word-break: break-all;
            margin: 15px 0;
        }

        .backup-codes {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .code-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .code-item {
            font-family: monospace;
            padding: 5px;
            background: white;
            border: 1px dashed #ddd;
            border-radius: 4px;
            text-align: center;
        }

        .setup-options {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            margin: 30px auto;
            max-width: 600px;
        }

        .option-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }

        .option-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border: 2px solid #e0e6ff;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            min-width: 150px;
        }

        .option-btn:hover {
            background: #eef2ff;
            border-color: #667eea;
            transform: translateY(-5px);
        }

        .option-btn i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #667eea;
        }

        .option-btn span {
            font-size: 16px;
            font-weight: 600;
        }

        .info-box {
            background: #f0f8ff;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }

        .instructions-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .instructions-list ol {
            margin-left: 20px;
        }

        .instructions-list li {
            margin-bottom: 10px;
        }

        .debug-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-shield-alt"></i> Настройка 2FA</h1>
            <p>Настройте двухфакторную аутентификацию для защиты аккаунта</p>
        </header>

        <nav class="navbar">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Личный кабинет</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти (<?php echo escape(getCurrentUser()); ?>)</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Вход</a>
                <a href="setup_2fa.php" class="active"><i class="fas fa-shield-alt"></i> Настройка 2FA</a>
                <a href="help_2fa.php"><i class="fas fa-question-circle"></i> Помощь</a>
            <?php endif; ?>
        </nav>

        <main class="main-content">
            <?php if ($message): ?>
                <div class="message <?php echo escape($messageType); ?>">
                    <i
                        class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'info' ? 'info-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle')); ?>"></i>
                    <?php echo escape($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($showSetupOptions && !$secret): ?>
                <!-- Блок с кнопками как на изображении -->
                <div class="setup-options">
                    <div class="info-box">
                        <h3><i class="fas fa-shield-alt"></i> Что такое двухфакторная аутентификация?</h3>
                        <p>2FA добавляет второй уровень защиты к вашему аккаунту:</p>

                        <div class="instructions-list">
                            <ol>
                                <li><strong>Первый фактор:</strong> ваш пароль</li>
                                <li><strong>Второй фактор:</strong> 6-значный код из приложения аутентификатора</li>
                            </ol>
                        </div>

                        <p><i class="fas fa-info-circle"></i> После включения 2FA при каждом входе потребуется ввести код из
                            приложения.</p>
                    </div>

                    <div class="option-buttons">
                        <a href="#" onclick="document.getElementById('setupForm').submit(); return false;"
                            class="option-btn">
                            <i class="fas fa-cog"></i>
                            <span>Настроить 2FA</span>
                        </a>
                        <a href="help_2fa.php" class="option-btn">
                            <i class="fas fa-question-circle"></i>
                            <span>Помощь</span>
                        </a>
                    </div>

                    <!-- Скрытая форма для настройки -->
                    <form method="POST" action="" id="setupForm" style="display: none;">
                        <input type="hidden" name="csrf_token" value="<?php echo escape(generateCSRFToken()); ?>">
                        <input type="hidden" name="action" value="generate">
                    </form>

                    <?php if (!isLoggedIn()): ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                            <p><i class="fas fa-exclamation-triangle"></i> Для настройки 2FA необходимо войти в систему.</p>
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Войти
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($secret): ?>
                <div class="setup-container">
                    <div>
                        <div class="setup-step">
                            <h3><span class="step-number">1</span> Установите приложение</h3>
                            <p>Скачайте Google Authenticator на ваш телефон:</p>
                            <p>
                                <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2"
                                    target="_blank" class="btn btn-outline">
                                    <i class="fab fa-google-play"></i> Android
                                </a>
                                <a href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank"
                                    class="btn btn-outline">
                                    <i class="fab fa-apple"></i> iOS
                                </a>
                            </p>
                        </div>
                    </div>

                    <div>
                        <div class="setup-step">
                            <h3><span class="step-number">2</span> Отсканируйте QR-код</h3>
                            <div class="qr-code-container">
                                <img src="<?php echo escape($qrCode); ?>" alt="QR Code" class="qr-code">
                                <p>Откройте Google Authenticator и отсканируйте QR-код</p>

                                <p>Или введите секрет вручную:</p>
                                <div class="secret-code"><?php echo escape($secret); ?></div>
                            </div>
                        </div>

                        <div class="setup-step">
                            <h3><span class="step-number">3</span> Подтвердите настройку</h3>
                            <p>Введите 6-значный код из приложения:</p>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo escape(generateCSRFToken()); ?>">
                                <input type="hidden" name="action" value="verify">

                                <div class="form-group">
                                    <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" placeholder="123456"
                                        required style="font-size: 20px; text-align: center; letter-spacing: 3px;"
                                        autocomplete="off" autofocus>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> Подтвердить 2FA
                                </button>
                            </form>

                            <p style="margin-top: 15px; font-size: 14px; color: #666;">
                                <a href="setup_2fa.php" class="btn btn-outline btn-sm">
                                    <i class="fas fa-redo"></i> Сгенерировать новый код
                                </a>
                            </p>
                        </div>

                        <div class="backup-codes">
                            <h4><i class="fas fa-key"></i> Сохраните резервные коды</h4>
                            <p>Эти коды можно использовать если у вас нет доступа к телефону:</p>
                            <div class="code-list">
                                <?php
                                $backupCodes = generateBackupCodes(6);
                                foreach ($backupCodes as $code): ?>
                                    <div class="code-item"><?php echo escape($code); ?></div>
                                <?php endforeach; ?>
                            </div>
                            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                                <i class="fas fa-exclamation-triangle"></i> Сохраните эти коды в безопасном месте!
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ОТЛАДОЧНЫЙ БЛОК -->
                <div class="debug-panel" style="max-width: 800px; margin: 30px auto;">
                    <h4><i class="fas fa-bug"></i> Тестовый режим</h4>
                    <p>Секрет для отладки: <strong><?php echo escape($secret); ?></strong></p>
                    <p>Текущий код (обновляется каждые 30 секунд):
                        <strong id="currentCode"><?php echo getCurrentTOTPCode($secret); ?></strong>
                    </p>
                    <button onclick="copyCode()" class="btn btn-sm btn-outline">
                        <i class="fas fa-copy"></i> Скопировать код
                    </button>
                    <small style="display:block; margin-top:10px;">
                        Код обновится через <span id="countdown">30</span> секунд
                    </small>
                </div>
            <?php endif; ?>

            <?php if (is2FAEnabled($username) && !$secret): ?>
                <div class="setup-step" style="max-width: 800px; margin: 30px auto;">
                    <h3><i class="fas fa-check-circle" style="color: #28a745;"></i> 2FA уже настроена</h3>
                    <p>Двухфакторная аутентификация уже активирована для вашего аккаунта.</p>
                    <p>При следующем входе потребуется код из Google Authenticator.</p>

                    <div style="margin-top: 20px;">
                        <?php if (isLoggedIn()): ?>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-tachometer-alt"></i> Личный кабинет
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Войти
                            </a>
                        <?php endif; ?>
                        <a href="disable_2fa.php" class="btn btn-outline">
                            <i class="fas fa-ban"></i> Отключить 2FA
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Автофокус на поле ввода кода
            const codeInput = document.querySelector('input[name="code"]');
            if (codeInput) {
                codeInput.focus();

                // Автоотправка при вводе 6 цифр
                codeInput.addEventListener('input', function () {
                    if (this.value.length === 6) {
                        this.form.submit();
                    }
                });
            }

            // Копирование секрета по клику
            const secretElement = document.querySelector('.secret-code');
            if (secretElement) {
                secretElement.addEventListener('click', function () {
                    const text = this.textContent;
                    navigator.clipboard.writeText(text).then(function () {
                        const originalText = secretElement.innerHTML;
                        secretElement.innerHTML = '<i class="fas fa-check"></i> Скопировано!';
                        setTimeout(function () {
                            secretElement.innerHTML = originalText;
                        }, 2000);
                    });
                });
                secretElement.style.cursor = 'pointer';
                secretElement.title = 'Нажмите для копирования';
            }

            // Обработка кнопки "Настроить 2FA"
            const setupBtn = document.querySelector('.option-btn[onclick]');
            if (setupBtn && !<?php echo isLoggedIn() ? 'true' : 'false'; ?>) {
                setupBtn.onclick = function () {
                    alert('Для настройки 2FA необходимо войти в систему.');
                    window.location.href = 'login.php';
                    return false;
                };
            }

            // Таймер для отладочного блока
            const countdownElement = document.getElementById('countdown');
            const codeElement = document.getElementById('currentCode');

            if (countdownElement && codeElement) {
                let countdown = 30;

                function updateCountdown() {
                    countdown--;
                    countdownElement.textContent = countdown;

                    if (countdown <= 0) {
                        // Обновляем страницу для получения нового кода
                        location.reload();
                    }
                }

                // Запускаем таймер
                setInterval(updateCountdown, 1000);
            }
        });

        function copyCode() {
            const codeElement = document.getElementById('currentCode');
            if (codeElement) {
                const code = codeElement.textContent;
                navigator.clipboard.writeText(code).then(function () {
                    alert('Код скопирован: ' + code);
                });
            }
        }
    </script>
</body>

</html>