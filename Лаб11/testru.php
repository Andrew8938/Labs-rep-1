<?php
// Получаем параметры из формы (или ставим значения по умолчанию)
$r = isset($_GET['r']) ? (int)$_GET['r'] : 120;
$g = isset($_GET['g']) ? (int)$_GET['g'] : 200;
$b = isset($_GET['b']) ? (int)$_GET['b'] : 255;

// Ограничиваем диапазон 0–255
$r = max(0, min(255, $r));
$g = max(0, min(255, $g));
$b = max(0, min(255, $b));

$rgb = "rgb($r, $g, $b)";
$hex = sprintf("#%02X%02X%02X", $r, $g, $b);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Конструктор цветовых схем</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .box {
            width: 200px;
            height: 200px;
            border: 2px solid black;
            margin-bottom: 20px;
        }
        input {
            width: 60px;
            padding: 5px;
            margin-right: 10px;
        }
        button {
            padding: 8px 14px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<h2>Конструктор цветовых схем</h2>

<!-- Квадрат с цветом -->
<div class="box" style="background: <?= $rgb ?>"></div>

<!-- Форма для ввода -->
<form method="get">
    <label>R:</label>
    <input type="number" name="r" min="0" max="255" value="<?= $r ?>">

    <label>G:</label>
    <input type="number" name="g" min="0" max="255" value="<?= $g ?>">

    <label>B:</label>
    <input type="number" name="b" min="0" max="255" value="<?= $b ?>">

    <button type="submit">Применить</button>
</form>

<br>

<b>RGB:</b> <?= $rgb ?><br>
<b>HEX:</b> <?= $hex ?>

</body>
</html>
