<?php
// api/Import/import_nodes.php – обработчик импорта Excel
require_once dirname(__FILE__, 3) . '/vendor/autoload.php';   // автозагрузка PhpSpreadsheet
require_once dirname(__FILE__, 3) . '/config/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Перенаправляем обратно на страницу импорта с результатом
$redirect = '../import.php?result=error';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['excel_file'])) {
    header('Location: ' . $redirect . '&message=Файл не загружен');
    exit;
}

$file = $_FILES['excel_file']['tmp_name'];
$ext = pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION);
if (!in_array($ext, ['xlsx', 'xls'])) {
    header('Location: ' . $redirect . '&message=Неверный формат файла');
    exit;
}

try {
    $spreadsheet = IOFactory::load($file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    if (count($rows) < 2) {
        header('Location: ' . $redirect . '&message=Файл пуст или содержит только заголовки');
        exit;
    }

    // Первая строка – заголовки
    $headers = array_map('trim', $rows[0]);

    // Проверяем обязательные столбцы (хотя бы KY_number и id_location)
    $required = ['KY_number', 'id_location'];
    foreach ($required as $req) {
        if (!in_array($req, $headers)) {
            header('Location: ' . $redirect . '&message=Отсутствует обязательный столбец: ' . $req);
            exit;
        }
    }

    $pdo->beginTransaction();
    $successCount = 0;
    $errorRows = [];

    // Начинаем со второй строки (индекс 1)
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (empty(array_filter($row))) continue;   // пропускаем пустые строки

        $data = [];
        foreach ($headers as $idx => $header) {
            if ($header === '') continue;
            $data[$header] = $row[$idx] ?? '';
        }

        // Простейшая валидация
        if (empty($data['KY_number'])) {
            $errorRows[] = $i + 1;   // номер строки как в Excel
            continue;
        }

        // Приводим типы
        if (isset($data['id_location'])) $data['id_location'] = (int)$data['id_location'] ?: null;
        if (isset($data['node_type_id'])) $data['node_type_id'] = (int)$data['node_type_id'] ?: null;

        // Добавляем статус по умолчанию (позже будет пересчитан)
        $data['status'] = 'inactive';

        // Строим INSERT
        $columns = '`' . implode('`, `', array_keys($data)) . '`';
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO nodes ($columns) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $successCount++;
    }

    $pdo->commit();

    $errorsStr = $errorRows ? implode(', ', $errorRows) : '';
    header('Location: ' . '../import.php?result=success&count=' . $successCount . '&errors=' . urlencode($errorsStr));
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: ' . $redirect . '&message=' . urlencode($e->getMessage()));
}