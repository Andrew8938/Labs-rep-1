<?php
// logout.php
require_once 'config.php';

// Завершаем сессию
logout();

// Перенаправляем на главную страницу
header('Location: index.php');
exit;
?>