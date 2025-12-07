<?php
// login.php
require_once 'config.php';

// Если пользователь уже авторизован, перенаправляем на главную
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Валидация
    if (empty($username) || empty($password)) {
        $message = 'Все поля обязательны для заполнения';
        $messageType = 'error';
    } else {
        // Попытка входа
        if (loginUser($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $message = 'Неверное имя пользователя или пароль';
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
                                required>
                        </div>

                        <div class="form-group">
                            <label for="password"><i class="fas fa-key"></i> Пароль</label>
                            <input type="password" id="password" name="password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Войти
                        </button>
                    </form>

                    <div class="auth-links">
                        <p>Нет аккаунта? <a href="register.php">Зарегистрируйтесь</a></p>
                        <p><a href="index.php"><i class="fas fa-arrow-left"></i> Вернуться на главную</a></p>
                    </div>
                </div>

                <div class="form-info">
                    <h3><i class="fas fa-shield-alt"></i> Как работает аутентификация?</h3>
                    <p>При входе система использует функцию <strong>password_verify()</strong> для проверки пароля.</p>
                    <p>Это безопасный метод, который:</p>
                    <ul>
                        <li>Сравнивает введенный пароль с хешем в хранилище</li>
                        <li>Защищает от атак перебором (brute-force)</li>
                        <li>Не требует хранения паролей в открытом виде</li>
                    </ul>
                    <div class="security-tip">
                        <i class="fas fa-lock"></i>
                        <p>Никогда не передавайте свои учетные данные третьим лицам.</p>
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