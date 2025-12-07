<?php
// register.php
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Валидация
    if (empty($username) || empty($password)) {
        $message = 'Все поля обязательны для заполнения';
        $messageType = 'error';
    } elseif (strlen($username) < 3) {
        $message = 'Имя пользователя должно содержать минимум 3 символа';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Пароль должен содержать минимум 6 символов';
        $messageType = 'error';
    } elseif ($password !== $confirm_password) {
        $message = 'Пароли не совпадают';
        $messageType = 'error';
    } else {
        // Регистрация пользователя
        $result = registerUser($username, $password);

        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
        } else {
            $message = $result['message'];
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
                        <div class="form-group">
                            <label for="username"><i class="fas fa-user"></i> Имя пользователя</label>
                            <input type="text" id="username" name="username"
                                value="<?php echo isset($_POST['username']) ? escape($_POST['username']) : ''; ?>"
                                required minlength="3">
                            <small>Минимум 3 символа</small>
                        </div>

                        <div class="form-group">
                            <label for="password"><i class="fas fa-key"></i> Пароль</label>
                            <input type="password" id="password" name="password" required minlength="6">
                            <small>Минимум 6 символов</small>
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
                        <li>Ваш пароль никогда не хранится в открытом виде</li>
                        <li>Используется современный алгоритм хеширования</li>
                        <li>Каждый хеш уникален, даже для одинаковых паролей</li>
                    </ul>
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
</body>

</html>