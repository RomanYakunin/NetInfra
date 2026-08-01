<div class="toolbar">
    <input type="text" placeholder="Поиск...">
    <button class="btn">Добавить задачу</button>
</div>
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Описание</th>
                <th>Статус</th>
                <th>Создана</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?= htmlspecialchars($task['id']) ?></td>
                    <td><?= htmlspecialchars($task['description']) ?></td>
                    <td><?= htmlspecialchars($task['status']) ?></td>
                    <td><?= htmlspecialchars($task['created_at']) ?></td>
                    <td></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="pagination">
    <span class="page-info">Всего задач: <?= count($tasks) ?></span>
</div>