<?php
require_once 'config.php';

$message = '';
$messageType = '';
$validToken = false;
$token = $_GET['token'] ?? '';

if ($token) {
    $email = validateResetToken($token);
    $validToken = ($email !== false);

    if (!$validToken) {
        $message = 'Неверная или просроченная ссылка для сброса пароля';
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($newPassword) || strlen($newPassword) < 8) {
        $message = 'Пароль должен содержать минимум 8 символов';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Пароли не совпадают';
        $messageType = 'error';
    } else {
        $result = resetPassword($token, $newPassword);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';

        if ($result['success']) {
            $validToken = false; // Токен использован
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новый пароль - Система авторизации</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-lock"></i> Установка нового пароля</h1>
            <p>Создайте новый пароль для вашего аккаунта</p>
        </header>

        <main class="main-content">
            <div class="auth-form-container">
                <div class="form-card">
                    <?php if (!$validToken && empty($token)): ?>
                        <h2><i class="fas fa-exclamation-triangle"></i> Требуется токен</h2>
                        <p>Для сброса пароля необходимо перейти по ссылке из письма.</p>
                        <div class="auth-links">
                            <a href="forgot_password.php" class="btn btn-primary">
                                <i class="fas fa-key"></i> Запросить сброс пароля
                            </a>
                        </div>
                    <?php elseif (!$validToken): ?>
                        <h2><i class="fas fa-exclamation-triangle"></i> Недействительная ссылка</h2>
                        <div class="message error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo escape($message); ?>
                        </div>
                        <div class="auth-links">
                            <p>Ссылка для сброса пароля недействительна или просрочена.</p>
                            <a href="forgot_password.php" class="btn btn-primary">
                                <i class="fas fa-redo"></i> Запросить новую ссылку
                            </a>
                        </div>
                    <?php else: ?>
                        <h2><i class="fas fa-lock-open"></i> Новый пароль</h2>

                        <?php if ($message): ?>
                            <div class="message <?php echo $messageType; ?>">
                                <i
                                    class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                                <?php echo escape($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($messageType !== 'success'): ?>
                            <form method="POST" action="" class="auth-form">
                                <input type="hidden" name="token" value="<?php echo escape($token); ?>">

                                <div class="form-group">
                                    <label for="new_password"><i class="fas fa-key"></i> Новый пароль</label>
                                    <input type="password" id="new_password" name="new_password" required minlength="8">
                                    <small>Минимум 8 символов. Рекомендуем использовать буквы, цифры и специальные
                                        символы.</small>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password"><i class="fas fa-key"></i> Подтверждение пароля</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Установить новый пароль
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="success-message">
                                <h3><i class="fas fa-check-circle"></i> Пароль успешно изменен!</h3>
                                <p>Теперь вы можете войти в систему с новым паролем.</p>
                                <div class="auth-links">
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Войти в систему
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="auth-links">
                        <p><a href="index.php"><i class="fas fa-arrow-left"></i> Вернуться на главную</a></p>
                    </div>
                </div>

                <div class="form-info">
                    <h3><i class="fas fa-lightbulb"></i> Советы по созданию пароля</h3>
                    <ul>
                        <li><i class="fas fa-check"></i> Используйте минимум 8 символов</li>
                        <li><i class="fas fa-check"></i> Комбинируйте заглавные и строчные буквы</li>
                        <li><i class="fas fa-check"></i> Добавляйте цифры и специальные символы (@, #, $, и т.д.)</li>
                        <li><i class="fas fa-check"></i> Избегайте личной информации (даты рождения, имена)</li>
                        <li><i class="fas fa-check"></i> Не используйте один пароль для разных сервисов</li>
                    </ul>
                    <div class="password-strength">
                        <h4><i class="fas fa-chart-line"></i> Проверка сложности пароля:</h4>
                        <ul id="password-rules">
                            <li id="rule-length"><i class="fas fa-circle"></i> Длина не менее 8 символов</li>
                            <li id="rule-upper"><i class="fas fa-circle"></i> Содержит заглавные буквы</li>
                            <li id="rule-lower"><i class="fas fa-circle"></i> Содержит строчные буквы</li>
                            <li id="rule-number"><i class="fas fa-circle"></i> Содержит цифры</li>
                            <li id="rule-special"><i class="fas fa-circle"></i> Содержит специальные символы</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; 2023 Система авторизации. Все права защищены.</p>
        </footer>
    </div>

    <script>
        // Скрипт для проверки сложности пароля в реальном времени
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('new_password');
            if (!passwordInput) return;

            passwordInput.addEventListener('input', function () {
                const password = this.value;
                const rules = {
                    length: password.length >= 8,
                    upper: /[A-Z]/.test(password),
                    lower: /[a-z]/.test(password),
                    number: /\d/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };

                Object.keys(rules).forEach(rule => {
                    const element = document.getElementById(`rule-${rule}`);
                    if (element) {
                        const icon = element.querySelector('i');
                        if (rules[rule]) {
                            icon.className = 'fas fa-check-circle';
                            icon.style.color = '#27ae60';
                        } else {
                            icon.className = 'fas fa-circle';
                            icon.style.color = '#95a5a6';
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>