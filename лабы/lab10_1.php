<?php
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

echo "<h2>Генератор пароля</h2>";

echo "<form method='post'>
    <label>Длина пароля (введите число от 4 до 30):</label>
    <input type='number' name='len' value='8' min='4' max='30'>
    <input type='submit' value='Сгенерировать'>
</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['len'])) {
    $len = (int)$_POST['len'];
    echo "<p><b>Пароль:</b> " . generatePassword($len) . "</p>";
}
?>


