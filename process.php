<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Неверный метод запроса. Откройте форму и отправьте данные через неё.";
    exit;
}

$a_raw = trim((string) ($_POST['a'] ?? ''));
$b_raw = trim((string) ($_POST['b'] ?? ''));
$c_raw = trim((string) ($_POST['c'] ?? ''));


function is_number($s) {
    if ($s === '') return false;
    $s = str_replace(',', '.', $s);
    return is_numeric($s);
}

$errors = [];

if (!is_number($a_raw)) $errors[] = "Поле 'Число 1' должно быть числом.";
if (!is_number($b_raw)) $errors[] = "Поле 'Число 2' должно быть числом.";
if (!is_number($c_raw)) $errors[] = "Поле 'Число 3' должно быть числом.";

if (!empty($errors)) {
    echo "<h3 class='error'>Ошибка ввода:</h3>";
    echo "<ul>";
    foreach ($errors as $err) {
        echo "<li>" . htmlspecialchars($err) . "</li>";
    }
    echo "</ul>";
    echo "<p><a href='form.html'>Вернуться к форме</a></p>";
    exit;
}


$a = (float) str_replace(',', '.', $a_raw);
$b = (float) str_replace(',', '.', $b_raw);
$c = (float) str_replace(',', '.', $c_raw);


$sum = $a + $b + $c;
$average = $sum / 3.0;

$average_display = round($average, 6);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Результат — Среднее арифметическое</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 700px; }
    .result { margin-top: 12px; padding: 14px; background: #f8f8ff; border-radius: 6px; }
    .small { color: #555; font-size: 0.95em; }
  </style>
</head>
<body>
  <h2>Результат вычисления</h2>

  <div class="result">
    <p>Введённые числа:</p>
    <ul>
      <li>Число 1: <?php echo htmlspecialchars($a_raw); ?></li>
      <li>Число 2: <?php echo htmlspecialchars($b_raw); ?></li>
      <li>Число 3: <?php echo htmlspecialchars($c_raw); ?></li>
    </ul>

    <p class="small">
      Сумма: <?php echo $a . " + " . $b . " + " . $c; ?> = <?php echo $sum; ?>
      <br>
      Среднее арифметическое = (<?php echo $sum; ?>) / 3 = <strong><?php echo $average_display; ?></strong>
    </p>
  </div>

  <p style="margin-top:18px;"><a href="form.html">Ввести другие числа</a></p>
</body>
</html>