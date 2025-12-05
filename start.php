<?php
session_start();
session_destroy(); // очистка старых данных
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Онлайн-тест</title>
    <?php include 'style.html'; ?>
</head>
<body>
<div class="container">
    <h2>Онлайн-тест</h2>
    <form method="post" action="question.php">
        <p><strong>Выберите тему теста:</strong></p>
        <label><input type="radio" name="topic" value="programming" required> Программирование</label>
        <label><input type="radio" name="topic" value="art" required> Художество</label>
        <label><input type="radio" name="topic" value="history" required> История</label>

        <p><strong>Выберите тип теста:</strong></p>
        <label><input type="radio" name="mode" value="single" required> Один правильный ответ</label>
        <label><input type="radio" name="mode" value="multiple" required> Несколько правильных ответов</label>

        <input type="submit" value="Начать тест">
    </form>
</div>
</body>
</html>

