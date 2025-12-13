<?php
try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3307;dbname=test;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            price INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
    ");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add'])) {
            $title = trim($_POST['title']);
            $price = intval($_POST['price']);
            if ($title !== '' && $price > 0) {
                $stmt = $pdo->prepare("INSERT INTO products (title, price) VALUES (:title, :price)");
                $stmt->execute(['title' => $title, 'price' => $price]);
            }
        } elseif (isset($_POST['delete'])) {
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute(['id' => $id]);
        }
    }

    $products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $expensive = $pdo->query("SELECT * FROM products WHERE price > 10000")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<div style='color:red'>Ошибка: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление товарами</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">

    <h1 class="mb-4">Управление товарами</h1>

    <div class="card mb-4">
        <div class="card-header">Добавить товар</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="add" value="1">
                <div class="mb-3">
                    <label class="form-label">Название товара</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Цена (тг)</label>
                    <input type="number" name="price" class="form-control" required min="1">
                </div>
                <button class="btn btn-primary">Добавить</button>
            </form>
        </div>
    </div>

    <h2>Все товары</h2>
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Цена</th>
            <th>Создано</th>
            <th>Действие</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['title']) ?></td>
                <td><?= $p['price'] ?> тг</td>
                <td><?= $p['created_at'] ?></td>
                <td>
                    <form method="POST" style="display:inline-block">
                        <input type="hidden" name="delete" value="1">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button class="btn btn-sm btn-danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2 class="mt-5">Товары дороже 10 000 тг</h2>
    <table class="table table-bordered">
        <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Цена</th>
            <th>Создано</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($expensive as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['title']) ?></td>
                <td><?= $p['price'] ?> тг</td>
                <td><?= $p['created_at'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</div>
</body>
</html>
