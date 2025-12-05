<?php
session_start();

if (!isset($_SESSION['step'])) {
    $_SESSION['step'] = 1;
    $_SESSION['score'] = 0;
    $_SESSION['mode'] = $_POST['mode'] ?? 'single';
    $_SESSION['topic'] = $_POST['topic'] ?? 'programming';
}

$topic = $_SESSION['topic'];
$mode = $_SESSION['mode'];

// Русские названия тем
$topic_names = [
    'programming' => 'Программирование',
    'art' => 'Художество',
    'history' => 'История'
];
$topic_ru = $topic_names[$topic] ?? $topic;

// --- Вопросы (3 темы × 2 типа) ---
$questions_map = [
    'programming' => [
        'single' => [
            1 => ["question" => "Какой тег используется для вставки изображения?", "options" => ['<img>', '<src>', '<image>', '<pic>'], "answers" => ['<img>']],
            2 => ["question" => "Какой язык выполняется на стороне сервера?", "options" => ['PHP', 'HTML', 'CSS', 'JS'], "answers" => ['PHP']],
            3 => ["question" => "Какой массив хранит данные POST?", "options" => ['$_DATA', '$_POST', '$_FORM', '$_SEND'], "answers" => ['$_POST']],
            4 => ["question" => "Что делает функция echo?", "options" => ['Выводит текст', 'Сохраняет файл', 'Удаляет переменную', 'Запускает цикл'], "answers" => ['Выводит текст']],
            5 => ["question" => "Какой атрибут указывает ссылку в HTML?", "options" => ['href', 'src', 'alt', 'id'], "answers" => ['href']],
        ],
        'multiple' => [
            1 => ["question" => "Какие языки — языки программирования?", "options" => ['HTML', 'Python', 'CSS', 'C++'], "answers" => ['Python', 'C++']],
            2 => ["question" => "Какие технологии — веб-разработка?", "options" => ['PHP', 'MySQL', 'C#', 'CSS'], "answers" => ['PHP', 'MySQL', 'CSS']],
            3 => ["question" => "Какие массивы — супер-глобальные?", "options" => ['$_POST', '$_GET', '$_COOKIE', '$_SESSION'], "answers" => ['$_POST', '$_GET', '$_COOKIE', '$_SESSION']],
            4 => ["question" => "Какие типы данных есть в PHP?", "options" => ['string', 'float', 'null', 'number'], "answers" => ['string', 'float', 'null']],
            5 => ["question" => "Какие языки относятся к backend?", "options" => ['PHP', 'Python', 'Java', 'HTML'], "answers" => ['PHP', 'Python', 'Java']],
        ],
    ],
    'art' => [
        'single' => [
            1 => ["question" => "Кто написал «Мону Лизу»?", "options" => ['Леонардо да Винчи', 'Ван Гог', 'Пикассо', 'Рембрандт'], "answers" => ['Леонардо да Винчи']],
            2 => ["question" => "Что такое палитра?", "options" => ['Инструмент для смешивания красок', 'Кисть', 'Цвет', 'Стиль'], "answers" => ['Инструмент для смешивания красок']],
            3 => ["question" => "Основной цвет?", "options" => ['Красный', 'Оранжевый', 'Зелёный', 'Фиолетовый'], "answers" => ['Красный']],
            4 => ["question" => "К какому стилю принадлежит Ван Гог?", "options" => ['Импрессионизм', 'Барокко', 'Кубизм', 'Готика'], "answers" => ['Импрессионизм']],
            5 => ["question" => "Что делает художник?", "options" => ['Рисует', 'Считает', 'Пишет код', 'Печатает'], "answers" => ['Рисует']],
        ],
        'multiple' => [
            1 => ["question" => "Какие цвета являются основными?", "options" => ['Красный', 'Жёлтый', 'Синий', 'Зелёный'], "answers" => ['Красный', 'Жёлтый', 'Синий']],
            2 => ["question" => "Какие инструменты чаще всего используют художники?", "options" => ['Кисть', 'Палитра', 'Мольберт', 'Карандаш'], "answers" => ['Кисть', 'Палитра', 'Мольберт', 'Карандаш']],
            3 => ["question" => "Какие материалы используются в живописи?", "options" => ['Масло', 'Гуашь', 'Акварель', 'Глина'], "answers" => ['Масло', 'Гуашь', 'Акварель']],
            4 => ["question" => "Какие художники — импрессионисты?", "options" => ['Моне', 'Дега', 'Ван Гог', 'Пикассо'], "answers" => ['Моне', 'Дега', 'Ван Гог']],
            5 => ["question" => "Какие техники — графика?", "options" => ['Гравюра', 'Литография', 'Пастель', 'Акварель'], "answers" => ['Гравюра', 'Литография']],
        ],
    ],
    'history' => [
        'single' => [
            1 => ["question" => "Когда началась Вторая мировая война?", "options" => ['1939', '1941', '1914', '1945'], "answers" => ['1939']],
            2 => ["question" => "Кто был первым президентом США?", "options" => ['Дж. Вашингтон', 'Т. Джефферсон', 'Ф. Рузвельт', 'А. Линкольн'], "answers" => ['Дж. Вашингтон']],
            3 => ["question" => "Когда распался СССР?", "options" => ['1991', '1989', '1995', '2000'], "answers" => ['1991']],
            4 => ["question" => "Где находились пирамиды?", "options" => ['Египет', 'Греция', 'Италия', 'Индия'], "answers" => ['Египет']],
            5 => ["question" => "Кто завоевал Галлию?", "options" => ['Цезарь', 'Наполеон', 'Ганнибал', 'Александр'], "answers" => ['Цезарь']],
        ],
        'multiple' => [
            1 => ["question" => "Какие страны входили в Антанту?", "options" => ['Россия', 'Франция', 'Англия', 'Германия'], "answers" => ['Россия', 'Франция', 'Англия']],
            2 => ["question" => "Какие цивилизации древнейшие?", "options" => ['Шумеры', 'Египтяне', 'Греки', 'Римляне'], "answers" => ['Шумеры', 'Египтяне']],
            3 => ["question" => "Какие события XX века?", "options" => ['Распад СССР', 'Первая мировая', 'Крестовые походы', 'Изобретение Интернета'], "answers" => ['Распад СССР', 'Первая мировая', 'Изобретение Интернета']],
            4 => ["question" => "Какие страны участвовали во Второй мировой?", "options" => ['СССР', 'США', 'Германия', 'Китай'], "answers" => ['СССР', 'США', 'Германия', 'Китай']],
            5 => ["question" => "Кто были монархи?", "options" => ['Пётр I', 'Елизавета II', 'Гитлер', 'Наполеон'], "answers" => ['Пётр I', 'Елизавета II']],
        ],
    ]
];

$questions = $questions_map[$topic][$mode];
$step = $_SESSION['step'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $selected = $_POST['answer'];
    $correct = $questions[$step]['answers'];
    if (!is_array($selected)) $selected = [$selected];
    sort($selected); sort($correct);
    if ($selected === $correct) $_SESSION['score']++;
    $_SESSION['step']++;
    if ($_SESSION['step'] > count($questions)) {
        header("Location: result.php");
        exit;
    } else $step = $_SESSION['step'];
}

$current = $questions[$step];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вопрос</title>
    <?php include 'style.html'; ?>
</head>
<body>
<div class="container">
    <h2>Тема: <?= $topic_ru ?> | Вопрос <?= $step ?> из <?= count($questions) ?></h2>
    <p><strong><?= htmlspecialchars($current['question']) ?></strong></p>
    <form method="post">
        <?php foreach ($current['options'] as $option): ?>
            <?php if ($mode === 'single'): ?>
                <label><input type="radio" name="answer" value="<?= htmlspecialchars($option) ?>" required> <?= htmlspecialchars($option) ?></label>
            <?php else: ?>
                <label><input type="checkbox" name="answer[]" value="<?= htmlspecialchars($option) ?>"> <?= htmlspecialchars($option) ?></label>
            <?php endif; ?>
        <?php endforeach; ?>
        <input type="submit" value="Ответить">
    </form>
</div>
</body>
</html>
