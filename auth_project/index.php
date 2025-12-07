<?php
// index.php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная - Система авторизации</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-lock"></i> Система авторизации</h1>
            <p>Безопасная аутентификация с использованием password_hash()</p>
        </header>

        <nav class="navbar">
            <a href="index.php" class="active"><i class="fas fa-home"></i> Главная</a>
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Личный кабинет</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти (<?php echo escape(getCurrentUser()); ?>)</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Вход</a>
                <a href="register.php"><i class="fas fa-user-plus"></i> Регистрация</a>
            <?php endif; ?>
        </nav>

        <main class="main-content">
            <div class="welcome-section">
                <div class="welcome-text">
                    <h2><i class="fas fa-shield-alt"></i> Добро пожаловать!</h2>
                    <p>Это демонстрация системы авторизации с использованием современных методов безопасности PHP.</p>

                    <?php if (isLoggedIn()): ?>
                        <div class="success-message">
                            <h3><i class="fas fa-check-circle"></i> Вы успешно вошли в систему!</h3>
                            <p>Теперь у вас есть доступ к защищенным страницам.</p>
                            <a href="dashboard.php" class="btn btn-primary">Перейти в личный кабинет</a>
                        </div>
                    <?php else: ?>
                        <div class="info-message">
                            <h3><i class="fas fa-info-circle"></i> Для доступа к полному функционалу</h3>
                            <p>Войдите в систему или зарегистрируйтесь, если у вас еще нет аккаунта.</p>
                            <div class="action-buttons">
                                <a href="login.php" class="btn btn-primary">Войти в систему</a>
                                <a href="register.php" class="btn btn-secondary">Зарегистрироваться</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="features">
                    <h3><i class="fas fa-star"></i> Особенности системы:</h3>
                    <ul>
                        <li><i class="fas fa-check"></i> Безопасное хранение паролей с помощью password_hash()</li>
                        <li><i class="fas fa-check"></i> Проверка паролей через password_verify()</li>
                        <li><i class="fas fa-check"></i> Защита от XSS атак</li>
                        <li><i class="fas fa-check"></i> Управление сессиями</li>
                        <li><i class="fas fa-check"></i> Адаптивный дизайн</li>
                    </ul>
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