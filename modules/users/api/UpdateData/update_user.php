<?php
// modules/users/api/UpdateData/update_user.php — изменение роли / пароля / статуса (только админ)
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'ID пользователя не указан']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, Login, Role, is_active FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo json_encode(['error' => 'Пользователь не найден']);
        exit;
    }

    $fields = [];
    $params = [];

    // --- Роль ---
    if (isset($_POST['role'])) {
        $role = $_POST['role'];
        if (!in_array($role, ['admin', 'user'], true)) {
            echo json_encode(['error' => 'Недопустимая роль']);
            exit;
        }
        // Нельзя снять с себя права администратора
        if ($id === currentUserId() && $role !== 'admin') {
            echo json_encode(['error' => 'Нельзя снять права администратора с самого себя']);
            exit;
        }
        // Нельзя убрать последнего администратора
        if ($user['Role'] === 'admin' && $role !== 'admin') {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE Role = 'admin' AND is_active = 1")->fetchColumn();
            if ($cnt <= 1) {
                echo json_encode(['error' => 'Это последний администратор — роль изменить нельзя']);
                exit;
            }
        }
        $fields[] = 'Role = ?';
        $params[] = $role;
    }

    // --- Активность (блокировка) ---
    if (isset($_POST['is_active'])) {
        $isActive = (int)$_POST['is_active'] ? 1 : 0;
        if ($id === currentUserId() && !$isActive) {
            echo json_encode(['error' => 'Нельзя заблокировать самого себя']);
            exit;
        }
        if (!$isActive && $user['Role'] === 'admin') {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE Role = 'admin' AND is_active = 1")->fetchColumn();
            if ($cnt <= 1) {
                echo json_encode(['error' => 'Это последний активный администратор — блокировать нельзя']);
                exit;
            }
        }
        $fields[] = 'is_active = ?';
        $params[] = $isActive;
    }

    // --- Сброс пароля ---
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        if (mb_strlen($password) < 6) {
            echo json_encode(['error' => 'Пароль должен быть не короче 6 символов']);
            exit;
        }
        $fields[] = 'Password = ?';
        $params[] = password_hash($password, PASSWORD_DEFAULT);
        // После сброса пароля админом — требуем смену при следующем входе
        $fields[] = 'must_change_password = ?';
        $params[] = !empty($_POST['must_change_password']) ? 1 : 0;
    } elseif (isset($_POST['must_change_password'])) {
        $fields[] = 'must_change_password = ?';
        $params[] = (int)$_POST['must_change_password'] ? 1 : 0;
    }

    if (!$fields) {
        echo json_encode(['error' => 'Нет данных для обновления']);
        exit;
    }

    $params[] = $id;
    $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
