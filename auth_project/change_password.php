<?php
// change_password.php
require_once 'config.php';

// Если пользователь не авторизован, перенаправляем
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$username = getCurrentUser();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF токен
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Ошибка безопасности. Пожалуйста, попробуйте еще раз.';
        $messageType = 'error';
    } else {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Валидация
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'Все поля обязательны для заполнения';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = 'Новый пароль должен содержать минимум 6 символов';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Новые пароли не совпадают';
            $messageType = 'error';
        } else {
            // Проверяем текущий пароль
            $users = file(USERS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $userFound = false;

            foreach ($users as $key => $user) {
                $data = explode(':', $user);
                if (trim($data[0]) === $username) {
                    $userFound = true;

                    // Проверяем текущий пароль
                    if (password_verify($currentPassword, $data[2])) {
                        // Хешируем новый пароль
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $data[2] = $hashedPassword;

                        // Обновляем запись
                        $users[$key] = implode(':', $data);
                        file_put_contents(USERS_FILE, implode(PHP_EOL, $users) . PHP_EOL, LOCK_EX);

                        $message = 'Пароль успешно изменен!';
                        $messageType = 'success';
                        logSecurityEvent('PASSWORD_CHANGE_SUCCESS', $username);
                    } else {
                        $message = 'Текущий пароль введен неверно';
                        $messageType = 'error';
                        logSecurityEvent('PASSWORD_CHANGE_FAILED', $username, 'Wrong current password');
                    }
                    break;
                }
            }

            if (!$userFound) {
                $message = 'Пользователь не найден';
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
    <title>Смена пароля - Система авторизации</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .password-strength {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .password-strength ul {
            list-style: none;
            padding-left: 0;
        }

        .password-strength li {
            margin-bottom: 8px;
            padding-left: 25px;
            position: relative;
        }

        .password-strength li i {
            position: absolute;
            left: 0;
            top: 2px;
        }

        .current-password-info {
            background: #e8f4fd;
            border: 1px solid #b3d4fc;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-key"></i> Смена пароля</h1>
            <p>Измените пароль вашего аккаунта</p>
        </header>

        <nav class="navbar">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Личный кабинет</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти (<?php echo escape($username); ?>)</a>
        </nav>

        <main class="main-content">
            <div class="auth-form-container">
                <div class="form-card">
                    <h2><i class="fas fa-lock"></i> Изменение пароля</h2>

                    <?php if ($message): ?>
                        <div class="message <?php echo escape($messageType); ?>">
                            <i
                                class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                            <?php echo escape($message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($messageType !== 'success'): ?>
                        <div class="current-password-info">
                            <i class="fas fa-info-circle"></i>
                            <span>Для изменения пароля необходимо подтвердить текущий пароль.</span>
                        </div>

                        <form method="POST" action="" class="auth-form" id="changePasswordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo escape(generateCSRFToken()); ?>">

                            <div class="form-group">
                                <label for="current_password"><i class="fas fa-key"></i> Текущий пароль</label>
                                <div style="position: relative;">
                                    <input type="password" id="current_password" name="current_password" required>
                                    <button type="button" class="toggle-password" data-target="current_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="new_password"><i class="fas fa-key"></i> Новый пароль</label>
                                <div style="position: relative;">
                                    <input type="password" id="new_password" name="new_password" required minlength="6">
                                    <button type="button" class="toggle-password" data-target="new_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password"><i class="fas fa-key"></i> Подтверждение нового пароля</label>
                                <div style="position: relative;">
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="toggle-password" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="password-match" style="margin-top: 5px; font-size: 14px; display: none;">
                                    <i class="fas fa-check-circle" style="color: #27ae60;"></i>
                                    <span>Пароли совпадают</span>
                                </div>
                                <div id="password-mismatch" style="margin-top: 5px; font-size: 14px; display: none;">
                                    <i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i>
                                    <span>Пароли не совпадают</span>
                                </div>
                            </div>

                            <div class="password-strength">
                                <h4><i class="fas fa-check-circle"></i> Требования к новому паролю:</h4>
                                <ul>
                                    <li id="req-length"><i class="fas fa-circle"></i> Минимум 6 символов</li>
                                    <li id="req-upper"><i class="fas fa-circle"></i> Содержит заглавные буквы</li>
                                    <li id="req-lower"><i class="fas fa-circle"></i> Содержит строчные буквы</li>
                                    <li id="req-number"><i class="fas fa-circle"></i> Содержит цифры</li>
                                    <li id="req-special"><i class="fas fa-circle"></i> Содержит специальные символы</li>
                                </ul>
                            </div>

                            <button type="submit" class="btn btn-primary" id="submitButton">
                                <i class="fas fa-save"></i> Изменить пароль
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="success-message">
                            <h3><i class="fas fa-check-circle"></i> Пароль успешно изменен!</h3>
                            <p>Ваш пароль был обновлен. Теперь вы можете использовать новый пароль для входа в систему.</p>
                            <div class="auth-links">
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-tachometer-alt"></i> Вернуться в личный кабинет
                                </a>
                                <a href="logout.php" class="btn btn-secondary">
                                    <i class="fas fa-sign-out-alt"></i> Выйти и войти с новым паролем
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="auth-links">
                        <p><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Вернуться в личный кабинет</a></p>
                    </div>
                </div>

                <div class="form-info">
                    <h3><i class="fas fa-shield-alt"></i> Безопасность паролей</h3>
                    <p>Рекомендации по созданию безопасного пароля:</p>
                    <ul>
                        <li><i class="fas fa-check"></i> Используйте длинные пароли (от 12 символов)</li>
                        <li><i class="fas fa-check"></i> Не используйте личную информацию</li>
                        <li><i class="fas fa-check"></i> Используйте разные пароли для разных сервисов</li>
                        <li><i class="fas fa-check"></i> Регулярно меняйте пароли</li>
                        <li><i class="fas fa-check"></i> Рассмотрите использование менеджера паролей</li>
                    </ul>

                    <div class="security-tip">
                        <i class="fas fa-lightbulb"></i>
                        <div>
                            <strong>Совет по безопасности:</strong>
                            <p>Рекомендуем менять пароль каждые 3-6 месяцев для поддержания высокого уровня
                                безопасности.</p>
                        </div>
                    </div>

                    <div class="security-tip" style="background: #f8f9fa; border-left-color: #6c757d;">
                        <i class="fas fa-history"></i>
                        <div>
                            <strong>История изменений:</strong>
                            <p>Все изменения паролей регистрируются в системе безопасности для отслеживания
                                подозрительной активности.</p>
                        </div>
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
            // Переключение видимости пароля
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', function () {
                    const target = this.getAttribute('data-target');
                    const input = document.getElementById(target);
                    const icon = this.querySelector('i');

                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.className = 'fas fa-eye-slash';
                    } else {
                        input.type = 'password';
                        icon.className = 'fas fa-eye';
                    }
                });
            });

            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordMatch = document.getElementById('password-match');
            const passwordMismatch = document.getElementById('password-mismatch');
            const submitButton = document.getElementById('submitButton');

            if (newPasswordInput && confirmPasswordInput) {
                // Проверка сложности пароля
                newPasswordInput.addEventListener('input', function () {
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
                    if (confirmPasswordInput.value) {
                        checkPasswordMatch();
                    }
                });

                // Проверка совпадения паролей
                confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            }

            function checkPasswordMatch() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (confirmPassword) {
                    if (newPassword === confirmPassword && newPassword.length >= 6) {
                        passwordMatch.style.display = 'block';
                        passwordMismatch.style.display = 'none';
                        confirmPasswordInput.style.borderColor = '#27ae60';
                        submitButton.disabled = false;
                    } else {
                        passwordMatch.style.display = 'none';
                        passwordMismatch.style.display = 'block';
                        confirmPasswordInput.style.borderColor = '#e74c3c';
                        submitButton.disabled = true;
                    }
                } else {
                    passwordMatch.style.display = 'none';
                    passwordMismatch.style.display = 'none';
                    submitButton.disabled = false;
                }
            }

            // Валидация формы
            const form = document.getElementById('changePasswordForm');
            if (form) {
                form.addEventListener('submit', function (e) {
                    const currentPassword = document.getElementById('current_password').value;
                    const newPassword = newPasswordInput.value;
                    const confirmPassword = confirmPasswordInput.value;

                    if (!currentPassword) {
                        e.preventDefault();
                        alert('Пожалуйста, введите текущий пароль');
                        return;
                    }

                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('Новые пароли не совпадают');
                        return;
                    }

                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('Новый пароль должен содержать минимум 6 символов');
                        return;
                    }

                    // Показываем индикатор загрузки
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Изменение пароля...';
                });
            }
        });
    </script>
</body>

</html>