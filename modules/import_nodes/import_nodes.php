<?php
// import_test.php – тестовая страница импорта Excel/CSV

// Подключаем конфиг БД (если понадобится для будущей вставки)
require_once 'config/db.php';

$error = '';
$rows = [];
$headers = [];


// Обработка загруженного файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    // Проверяем, что файл загружен без ошибок
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ошибка загрузки файла.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        try {
            if ($ext === 'csv') {
                // Чтение CSV
                if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
                    // Первая строка – заголовки
                    $headers = fgetcsv($handle, 1000, ',');
                    if ($headers === false) {
                        $error = 'Не удалось прочитать заголовки.';
                    } else {
                        // Читаем остальные строки
                        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                            // Пропускаем пустые строки
                            if (count($data) == 1 && empty($data[0])) continue;
                            // Дополняем массив до длины заголовков (на случай, если строка короче)
                            $data = array_pad($data, count($headers), '');
                            $rows[] = $data;
                        }
                    }
                    fclose($handle);
                } else {
                    $error = 'Не удалось открыть файл.';
                }
            } elseif ($ext === 'xlsx') {
                // Чтение Excel с помощью PhpSpreadsheet (если установлен)
                if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                    $sheet = $spreadsheet->getActiveSheet();
                    $dataArray = $sheet->toArray();

                    if (count($dataArray) > 0) {
                        $headers = $dataArray[0];
                        // Удаляем первую строку (заголовки)
                        array_shift($dataArray);
                        foreach ($dataArray as $row) {
                            // Пропускаем пустые строки
                            if (count(array_filter($row, function($v) { return !empty($v); })) == 0) continue;
                            $rows[] = $row;
                        }
                    }
                } else {
                    $error = 'Библиотека PhpSpreadsheet не установлена. Установите её через composer или загрузите файл в формате CSV.';
                }
            } else {
                $error = 'Неподдерживаемый формат файла. Разрешены только .csv и .xlsx.';
            }
        } catch (Exception $e) {
            $error = 'Ошибка обработки файла: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тестовый импорт данных</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="modules/header/header.css">
    <link rel="stylesheet" href="modules/sidebar/sidebar.css">
    <link rel="stylesheet" href="modules/nodes_page/nodes_page.css">
    <style>
        .container { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .import-form { background: var(--bg-card); padding: 2rem; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .import-form h2 { margin-bottom: 1rem; color: var(--accent); }
        .import-form input[type="file"] { margin-bottom: 1rem; }
        .error { color: var(--danger); margin-bottom: 1rem; }
        .table-wrapper { overflow-x: auto; background: var(--bg-card); border-radius: 8px; box-shadow: var(--shadow); }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 0.6rem 1rem; border-bottom: 1px solid var(--border-color); text-align: left; }
        th { background: var(--bg-header); color: var(--text-primary); font-weight: 600; position: sticky; top: 0; }
        tr:hover { background: var(--table-hover); }
        .back-link { display: inline-block; margin-top: 1rem; color: var(--accent); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php if ($page === 'import_nodes'): ?>
    <link rel="stylesheet" href="modules/import_nodes/import_nodes.css">
    <script src="modules/import_nodes/import_nodes.js"></script>
<?php endif; ?>
    <div class="container">
        <h1>Тестовый импорт коммутационных узлов</h1>

        <div class="import-form">
            <h2>Загрузите файл Excel (.xlsx или .csv)</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="excel_file" accept=".csv,.xlsx" required>
                <br>
                <button type="submit" class="btn">Загрузить и отобразить</button>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($rows)): ?>
            <h2>Результат импорта (<?= count($rows) ?> строк)</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($headers as $header): ?>
                                <th><?= htmlspecialchars($header) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?= htmlspecialchars($cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error): ?>
            <p>Файл не содержит данных.</p>
        <?php endif; ?>

        <a href="/" class="back-link">← Вернуться в приложение</a>
    </div>
</body>
</html>