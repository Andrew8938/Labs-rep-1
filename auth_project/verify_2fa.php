<?php
// verify_2fa.php - Упрощенная версия
require_once 'config.php';

// Проверяем, нужно ли подтверждение 2FA
if (empty($_SESSION['twofa_pending_user'])) {
    // Если пользователь уже вошел, перенаправляем в кабинет
    if (isLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
    // Иначе - на страницу входа
    header('Location: login.php');
    exit;
}

$username = $_SESSION['twofa_pending_user'];
$message = '';
$messageType = '';

// Проверка таймаута (5 минут)
if (
    isset($_SESSION['twofa_pending_time']) &&
    (time() - $_SESSION['twofa_pending_time']) > 300
) {
    unset($_SESSION['twofa_pending_user'], $_SESSION['twofa_pending_time']);
    $message = 'Время для ввода кода истекло. Пожалуйста, войдите снова.';
    $messageType = 'error';
    logSecurityEvent('2FA_TIMEOUT', $username);
}

// Обработка POST запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf)) {
        $message = 'Ошибка безопасности (CSRF).';
        $messageType = 'error';
    } else {
        $code = trim($_POST['code'] ?? '');
        if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
            $message = 'Введите корректный 6-значный код из приложения.';
            $messageType = 'error';
        } else {
            if (verify2FALogin($username, $code)) {
                // Успешная верификация
                $_SESSION['user'] = $username;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();

                // Восстанавливаем email из сессии, если есть
                if (isset($_SESSION['pending_email'])) {
                    $_SESSION['email'] = $_SESSION['pending_email'];
                    unset($_SESSION['pending_email']);
                }

                // Очищаем pending данные
                unset($_SESSION['twofa_pending_user'], $_SESSION['twofa_pending_time']);

                logSecurityEvent('2FA_SUCCESS', $username);

                // Перенаправляем в кабинет
                header('Location: dashboard.php');
                exit;
            } else {
                $message = '❌ Неверный код. Попробуйте снова.';
                $messageType = 'error';
                logSecurityEvent('2FA_FAILURE', $username);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Подтверждение двухфакторной аутентификации</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .user-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 18px;
        }

        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid;
        }

        .message.error {
            background: #ffe6e6;
            border-color: #ff3333;
            color: #cc0000;
        }

        .message.success {
            background: #e6ffe6;
            border-color: #33cc33;
            color: #006600;
        }

        .code-input {
            font-family: monospace;
            font-size: 32px;
            letter-spacing: 10px;
            padding: 15px;
            width: 220px;
            text-align: center;
            border: 2px solid #667eea;
            border-radius: 10px;
            margin: 20px auto;
            display: block;
            outline: none;
            transition: all 0.3s;
        }

        .code-input:focus {
            border-color: #764ba2;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            margin: 10px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .instructions {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }

        .instructions h3 {
            margin-top: 0;
            color: #667eea;
        }

        .timer {
            color: #666;
            font-size: 14px;
            margin: 10px 0;
        }

        .backup-link {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .backup-link a {
            color: #667eea;
            text-decoration: none;
        }

        .backup-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔐 Подтверждение входа</h1>
        <p>Требуется двухфакторная аутентификация</p>

        <div class="user-info">
            Пользователь: <strong><?php echo escape($username); ?></strong>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo escape($messageType); ?>">
                <?php echo escape($message); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" id="verifyForm">
            <input type="hidden" name="csrf_token" value="<?php echo escape(generateCSRFToken()); ?>">

            <div class="instructions">
                <h3>📱 Как получить код:</h3>
                <ol>
                    <li>Откройте приложение Google Authenticator</li>
                    <li>Найдите запись для этого сайта</li>
                    <li>Введите 6-значный код ниже</li>
                </ol>
            </div>

            <input type="text" name="code" id="code" required pattern="\d{6}" maxlength="6" placeholder="123456"
                class="code-input" autofocus>

            <div class="timer">
                ⏰ Код обновляется каждые 30 секунд
            </div>

            <button type="submit" class="btn">✅ Подтвердить и войти</button>
            <a href="login.php" class="btn btn-secondary">Отмена</a>
        </form>

        <div class="backup-link">
            <p>Нет доступа к приложению? <a href="recover_account.php">Использовать резервный код</a></p>
        </div>
    </div>

    <script>
        // Автоматический переход между цифрами
        document.getElementById('code').addEventListener('input', function (e) {
            if (this.value.length === 6) {
                document.getElementById('verifyForm').submit();
            }
        });

        // Автофокус и очистка при ошибке
        document.getElementById('code').focus();
        <?php if (!empty($message) && $messageType === 'error'): ?>
            setTimeout(function () {
                document.getElementById('code').value = '';
                document.getElementById('code').focus();
            }, 100);
        <?php endif; ?>
    </script>
</body>

</html>