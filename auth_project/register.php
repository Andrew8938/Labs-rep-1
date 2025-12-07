<?php
// register.php - Исправленная версия
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF токен
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Ошибка безопасности. Пожалуйста, попробуйте еще раз.';
        $messageType = 'error';
        logSecurityEvent('CSRF_FAILURE', null, 'registration attempt');
    } else {
        $username = sanitizeInput(trim($_POST['username']));
        $email = sanitizeInput(trim($_POST['email']));
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Валидация
        if (empty($username) || empty($email) || empty($password)) {
            $message = 'Все поля обязательны для заполнения';
            $messageType = 'error';
        } elseif (strlen($username) < 3) {
            $message = 'Имя пользователя должно содержать минимум 3 символа';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Введите корректный email адрес';
            $messageType = 'error';
        } elseif (strlen($password) < 6) {
            $message = 'Пароль должен содержать минимум 6 символов';
            $messageType = 'error';
        } elseif ($password !== $confirm_password) {
            $message = 'Пароли не совпадают';
            $messageType = 'error';
        } else {
            // Регистрация пользователя (теперь с email)
            $result = registerUser($username, $email, $password);

            if ($result['success']) {
                $message = $result['message'];
                $messageType = 'success';
            } else {
                $message = $result['message'];
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Система авторизации</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-user-plus"></i> Регистрация</h1>
            <p>Создайте новый аккаунт в системе</p>
        </header>

        <nav class="navbar">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Личный кабинет</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти (<?php echo escape(getCurrentUser()); ?>)</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Вход</a>
                <a href="register.php" class="active"><i class="fas fa-user-plus"></i> Регистрация</a>
            <?php endif; ?>
        </nav>

        <main class="main-content">
            <div class="auth-form-container">
                <div class="form-card">
                    <h2><i class="fas fa-user-circle"></i> Создание аккаунта</h2>

                    <?php if ($message): ?>
                        <div class="message <?php echo $messageType; ?>">
                            <i
                                class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                            <?php echo escape($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="auth-form">
                        <input type="hidden" name="csrf_token" value="<?php echo escape(generateCSRFToken()); ?>">

                        <div class="form-group">
                            <label for="username"><i class="fas fa-user"></i> Имя пользователя</label>
                            <input type="text" id="username" name="username"
                                value="<?php echo isset($_POST['username']) ? escape($_POST['username']) : ''; ?>"
                                required minlength="3">
                            <small>Минимум 3 символа</small>
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email адрес</label>
                            <input type="email" id="email" name="email"
                                value="<?php echo isset($_POST['email']) ? escape($_POST['email']) : ''; ?>" required>
                            <small>На этот email можно будет сбросить пароль</small>
                        </div>

                        <div class="form-group">
                            <label for="password"><i class="fas fa-key"></i> Пароль</label>
                            <input type="password" id="password" name="password" required minlength="6">
                            <small>Минимум 6 символов. Рекомендуется использовать заглавные и строчные буквы, цифры и
                                специальные символы.</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-key"></i> Подтверждение пароля</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Зарегистрироваться
                        </button>
                    </form>

                    <div class="auth-links">
                        <p>Уже есть аккаунт? <a href="login.php">Войдите в систему</a></p>
                        <p><a href="index.php"><i class="fas fa-arrow-left"></i> Вернуться на главную</a></p>
                    </div>
                </div>

                <div class="form-info">
                    <h3><i class="fas fa-info-circle"></i> Безопасность паролей</h3>
                    <p>Система использует функцию <strong>password_hash()</strong> для безопасного хранения паролей.</p>
                    <p>Это означает, что:</p>
                    <ul>
                        <li><i class="fas fa-check"></i> Ваш пароль никогда не хранится в открытом виде</li>
                        <li><i class="fas fa-check"></i> Используется современный алгоритм хеширования</li>
                        <li><i class="fas fa-check"></i> Каждый хеш уникален, даже для одинаковых паролей</li>
                    </ul>

                    <div class="password-requirements">
                        <h4><i class="fas fa-check-circle"></i> Требования к паролю:</h4>
                        <ul>
                            <li id="req-length"><i class="fas fa-circle"></i> Минимум 6 символов</li>
                            <li id="req-upper"><i class="fas fa-circle"></i> Содержит заглавные буквы</li>
                            <li id="req-lower"><i class="fas fa-circle"></i> Содержит строчные буквы</li>
                            <li id="req-number"><i class="fas fa-circle"></i> Содержит цифры</li>
                            <li id="req-special"><i class="fas fa-circle"></i> Содержит специальные символы</li>
                        </ul>
                    </div>

                    <div class="security-tip">
                        <i class="fas fa-shield-alt"></i>
                        <p>Рекомендуем использовать сложные пароли, состоящие из букв, цифр и специальных символов.</p>
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
        // Скрипт для проверки сложности пароля в реальном времени
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            if (passwordInput) {
                passwordInput.addEventListener('input', function () {
                    const password = this.value;
                    const requirements = {
                        length: password.length >= 6,
                        upper: /[A-Z]/.test(password),
                        lower: /[a-z]/.test(password),
                        number: /\d/.test(password),
                        special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                    };

                    Object.keys(requirements).forEach(req => {
                        const element = document.getElementById(`req-${req}`);
                        if (element) {
                            const icon = element.querySelector('i');
                            if (requirements[req]) {
                                icon.className = 'fas fa-check-circle';
                                icon.style.color = '#27ae60';
                            } else {
                                icon.className = 'fas fa-circle';
                                icon.style.color = '#95a5a6';
                            }
                        }
                    });

                    // Проверка совпадения паролей
                    if (confirmPasswordInput && confirmPasswordInput.value) {
                        checkPasswordMatch();
                    }
                });
            }

            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            }

            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirm = confirmPasswordInput.value;

                if (confirm) {
                    if (password === confirm) {
                        confirmPasswordInput.style.borderColor = '#27ae60';
                        confirmPasswordInput.style.boxShadow = '0 0 0 2px rgba(39, 174, 96, 0.2)';
                    } else {
                        confirmPasswordInput.style.borderColor = '#e74c3c';
                        confirmPasswordInput.style.boxShadow = '0 0 0 2px rgba(231, 76, 60, 0.2)';
                    }
                }
            }
        });
    </script>
</body>

</html>