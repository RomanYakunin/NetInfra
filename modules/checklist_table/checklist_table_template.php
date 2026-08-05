<link rel="stylesheet" href="modules/checklist_table/checklist_table.css">

<div class="toolbar">
    <input type="text" placeholder="Поиск...">
    <button class="btn">Добавить задачу</button>
</div>

<!-- Автоматическая задача: оборудование, которого нет в Zabbix.
     В таблице checklist не хранится — список пересчитывается сверкой
     с мониторингом при каждом открытии страницы. -->
<div class="auto-task" id="zabbixTask" style="display:none;">
    <button type="button" class="auto-task-head" id="zabbixTaskHead">
        <span class="auto-task-arrow">▶</span>
        <span class="auto-task-icon">🚨</span>
        <span class="auto-task-title">Подключить Zabbix</span>
        <span class="auto-task-badge" id="zabbixTaskCount"></span>
        <span class="auto-task-note" id="zabbixTaskNote"></span>
    </button>
    <div class="auto-task-body" id="zabbixTaskBody" style="display:none;"></div>
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

<script src="modules/checklist_table/checklist_table.js"></script>