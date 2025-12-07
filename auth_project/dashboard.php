<?php
// dashboard.php
require_once 'config.php';

// Если пользователь не авторизован, перенаправляем на страницу входа
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$username = getCurrentUser();
$loginTime = isset($_SESSION['login_time']) ? $_SESSION['login_time'] : time();
$sessionDuration = time() - $loginTime;
$hours = floor($sessionDuration / 3600);
$minutes = floor(($sessionDuration % 3600) / 60);
$seconds = $sessionDuration % 60;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - Система авторизации</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-tachometer-alt"></i> Личный кабинет</h1>
            <p>Защищенная страница для авторизованных пользователей</p>
        </header>

        <nav class="navbar">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Личный кабинет</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти (<?php echo escape($username); ?>)</a>
        </nav>

        <main class="main-content">
            <div class="dashboard">
                <div class="welcome-card">
                    <h2><i class="fas fa-user-circle"></i> Добро пожаловать, <?php echo escape($username); ?>!</h2>
                    <p>Вы успешно вошли в систему и теперь имеете доступ к защищенным страницам.</p>

                    <div class="user-info">
                        <div class="info-box">
                            <i class="fas fa-user"></i>
                            <h3>Имя пользователя</h3>
                            <p><?php echo escape($username); ?></p>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-clock"></i>
                            <h3>Время входа</h3>
                            <p><?php echo date('H:i:s d.m.Y', $loginTime); ?></p>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-history"></i>
                            <h3>Длительность сессии</h3>
                            <p><?php printf("%02d:%02d:%02d", $hours, $minutes, $seconds); ?></p>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-shield-alt"></i>
                            <h3>Статус</h3>
                            <p class="status-active">Активен</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-content">
                    <div class="dashboard-card">
                        <h3><i class="fas fa-info-circle"></i> Информация о системе</h3>
                        <p>Вы находитесь на защищенной странице, доступ к которой имеют только авторизованные
                            пользователи.</p>
                        <p>Система аутентификации использует следующие технологии безопасности:</p>
                        <ul>
                            <li><strong>password_hash()</strong> - для безопасного хранения паролей</li>
                            <li><strong>password_verify()</strong> - для проверки паролей</li>
                            <li><strong>Сессии PHP</strong> - для отслеживания состояния входа</li>
                            <li><strong>Защита от XSS</strong> - через htmlspecialchars()</li>
                        </ul>
                    </div>

                    <div class="dashboard-card">
                        <h3><i class="fas fa-cogs"></i> Управление аккаунтом</h3>
                        <div class="actions">
                            <a href="#" class="btn btn-secondary"><i class="fas fa-key"></i> Сменить пароль</a>
                            <a href="#" class="btn btn-secondary"><i class="fas fa-envelope"></i> Настройки
                                уведомлений</a>
                            <a href="logout.php" class="btn btn-primary"><i class="fas fa-sign-out-alt"></i> Выйти из
                                системы</a>
                        </div>
                        <p class="note"><i class="fas fa-exclamation-triangle"></i> Функции смены пароля и настроек
                            находятся в разработке.</p>
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