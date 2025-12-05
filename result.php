<?php
session_start();
$score = $_SESSION['score'] ?? 0;
$total = 5;
$mode = $_SESSION['mode'] === 'single' ? 'Один ответ' : 'Несколько ответов';
$topic = $_SESSION['topic'] ?? 'programming';

// Русские названия тем
$topic_names = [
    'programming' => 'Программирование',
    'art' => 'Художество',
    'history' => 'История'
];
$topic_ru = $topic_names[$topic] ?? $topic;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Результат теста</title>
    <?php include 'style.html'; ?>
</head>
<body>
<div class="container">
    <h2>Результаты теста</h2>
    <p>Тема: <strong><?= $topic_ru ?></strong></p>
    <p>Тип теста: <strong><?= $mode ?></strong></p>
    <p>Правильных ответов: <strong><?= $score ?></strong> из <strong><?= $total ?></strong></p>
    <a href="start.php">Пройти заново</a>
</div>
</body>
</html>
