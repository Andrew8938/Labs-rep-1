<?php
session_start();
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$title = "Помощь по двухфакторной аутентификации";
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .help-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .step-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }

        .step-number {
            display: inline-block;
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            border-radius: 50%;
            margin-right: 10px;
            font-weight: bold;
        }

        .faq-item {
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .faq-question {
            font-weight: bold;
            color: #2c3e50;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .faq-answer {
            padding: 10px 0 0 30px;
            color: #555;
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-question-circle"></i> Помощь по 2FA</h1>
            <p>Инструкции по настройке и использованию двухфакторной аутентификации</p>
        </header>

        <nav class="navbar">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Вход</a>
            <a href="setup_2fa.php"><i class="fas fa-shield-alt"></i> Настройка 2FA</a>
            <a href="help_2fa.php" class="active"><i class="fas fa-question-circle"></i> Помощь по 2FA</a>
        </nav>

        <main class="main-content">
            <div class="help-section">
                <h2><i class="fas fa-info-circle"></i> Что такое 2FA?</h2>
                <p>Двухфакторная аутентификация (2FA) - это дополнительный уровень безопасности, который требует не
                    только пароля, но и временного кода, генерируемого на вашем мобильном устройстве.</p>
            </div>

            <div class="help-section">
                <h2><i class="fas fa-mobile-alt"></i> Как настроить 2FA</h2>

                <div class="step-box">
                    <div class="step-number">1</div>
                    <h3>Установите приложение Google Authenticator</h3>
                    <p>Скачайте и установите приложение на свой смартфон:</p>
                    <p>
                        <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2"
                            target="_blank">
                            <i class="fab fa-google-play"></i> Google Play (Android)
                        </a> |
                        <a href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank">
                            <i class="fab fa-app-store"></i> App Store (iOS)
                        </a>
                    </p>
                </div>

                <div class="step-box">
                    <div class="step-number">2</div>
                    <h3>Перейдите к настройке 2FA</h3>
                    <p>Войдите в систему и перейдите на страницу <a href="setup_2fa.php">"Настройка 2FA"</a>.</p>
                </div>

                <div class="step-box">
                    <div class="step-number">3</div>
                    <h3>Отсканируйте QR-код</h3>
                    <p>Откройте приложение Google Authenticator, нажмите "+" и отсканируйте QR-код с экрана.</p>
                </div>

                <div class="step-box">
                    <div class="step-number">4</div>
                    <h3>Подтвердите настройку</h3>
                    <p>Введите 6-значный код из приложения для подтверждения.</p>
                </div>
            </div>

            <div class="help-section">
                <h2><i class="fas fa-question"></i> Частые вопросы (FAQ)</h2>

                <div class="faq-item">
                    <div class="faq-question">
                        <i class="fas fa-question-circle"></i>
                        Почему 2FA обязательна при каждом входе?
                    </div>
                    <div class="faq-answer">
                        Для максимальной безопасности вашего аккаунта. Даже если кто-то узнает ваш пароль, без кода 2FA
                        доступ будет невозможен.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <i class="fas fa-question-circle"></i>
                        Что делать, если я потерял телефон с Google Authenticator?
                    </div>
                    <div class="faq-answer">
                        Обратитесь в службу поддержки. При настройке 2FA сохраните резервные коды в безопасном месте.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <i class="fas fa-question-circle"></i>
                        Коды 2FA не работают. Что делать?
                    </div>
                    <div class="faq-answer">
                        Проверьте время на вашем телефоне (должно быть синхронизировано). Если проблема persists,
                        используйте резервные коды или обратитесь в поддержку.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <i class="fas fa-question-circle"></i>
                        Можно ли отключить 2FA?
                    </div>
                    <div class="faq-answer">
                        Нет, двухфакторная аутентификация является обязательной и отключать ее нельзя в целях
                        безопасности.
                    </div>
                </div>
            </div>

            <div class="help-section">
                <h2><i class="fas fa-life-ring"></i> Нужна дополнительная помощь?</h2>
                <p>Если у вас остались вопросы или возникли проблемы с настройкой 2FA:</p>
                <ul>
                    <li>Обратитесь в службу поддержки: <a href="contact.php">contact.php</a></li>
                    <li>Проверьте настройки времени на вашем устройстве</li>
                    <li>Убедитесь, что установлена последняя версия Google Authenticator</li>
                    <li>Используйте резервные коды, если вы их сохранили</li>
                </ul>

                <div style="text-align: center; margin-top: 20px;">
                    <a href="setup_2fa.php" class="btn btn-primary">
                        <i class="fas fa-cog"></i> Перейти к настройке 2FA
                    </a>
                    <a href="login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> Вернуться ко входу
                    </a>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Система авторизации с обязательной 2FA</p>
            <p class="footer-info">Справочная информация по двухфакторной аутентификации</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Раскрытие/скрытие ответов на вопросы FAQ
            const faqQuestions = document.querySelectorAll('.faq-question');
            faqQuestions.forEach(question => {
                question.addEventListener('click', function () {
                    const answer = this.nextElementSibling;
                    answer.style.display = answer.style.display === 'block' ? 'none' : 'block';
                });
            });

            // По умолчанию скрываем все ответы
            document.querySelectorAll('.faq-answer').forEach(answer => {
                answer.style.display = 'none';
            });
        });
    </script>
</body>

</html>