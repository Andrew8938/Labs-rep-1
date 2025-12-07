<?php
require_once 'config.php';

$message = '';
$messageType = '';
$demoLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Пожалуйста, введите корректный email адрес';
        $messageType = 'error';
    } else {
        $result = initiatePasswordReset($email);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        $demoLink = $result['demo_link'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сброс пароля - Система авторизации</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-key"></i> Сброс пароля</h1>
            <p>Восстановление доступа к аккаунту</p>
        </header>

        <nav class="navbar">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Вход</a>
            <a href="register.php"><i class="fas fa-user-plus"></i> Регистрация</a>
        </nav>

        <main class="main-content">
            <div class="auth-form-container">
                <div class="form-card">
                    <h2><i class="fas fa-unlock-alt"></i> Забыли пароль?</h2>
                    <p>Введите email, указанный при регистрации. Мы отправим вам инструкции по сбросу пароля.</p>

                    <?php if ($message): ?>
                        <div class="message <?php echo $messageType; ?>">
                            <i
                                class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                            <?php echo escape($message); ?>
                            <?php if ($demoLink): ?>
                                <div class="demo-link">
                                    <p><strong>Демо-версия:</strong> Для тестирования перейдите по ссылке:</p>
                                    <a href="<?php echo escape($demoLink); ?>"
                                        target="_blank"><?php echo escape($demoLink); ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="auth-form">
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email адрес</label>
                            <input type="email" id="email" name="email"
                                value="<?php echo isset($_POST['email']) ? escape($_POST['email']) : ''; ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Отправить инструкции
                        </button>
                    </form>

                    <div class="auth-links">
                        <p><a href="login.php"><i class="fas fa-sign-in-alt"></i> Вспомнили пароль? Войти</a></p>
                        <p><a href="register.php"><i class="fas fa-user-plus"></i> Нет аккаунта? Зарегистрироваться</a>
                        </p>
                    </div>
                </div>

                <div class="form-info">
                    <h3><i class="fas fa-shield-alt"></i> Безопасность сброса пароля</h3>
                    <p>Процесс сброса пароля защищен:</p>
                    <ul>
                        <li><i class="fas fa-check-circle"></i> Уникальные токены с ограниченным сроком действия</li>
                        <li><i class="fas fa-check-circle"></i> Ссылка для сброса отправляется только на
                            зарегистрированный email</li>
                        <li><i class="fas fa-check-circle"></i> После сброса все существующие сессии завершаются</li>
                        <li><i class="fas fa-check-circle"></i> Защита от перебора токенов</li>
                    </ul>
                    <div class="security-tip">
                        <i class="fas fa-clock"></i>
                        <p>Ссылка для сброса пароля действительна <strong>1 час</strong>. После использования токен
                            становится недействительным.</p>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; 2023 Система авторизации. Все права защищены.</p>
            <p class="footer-info">Производственная система с дополнительными функциями безопасности</p>
        </footer>
    </div>
</body>

</html>