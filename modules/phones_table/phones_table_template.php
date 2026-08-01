<div class="tabs-inline">
    <a href="?page=phones&tab=phones" class="tab <?= $tab === 'phones' ? 'active' : '' ?>">Телефоны</a>
    <a href="?page=phones&tab=renames" class="tab <?= $tab === 'renames' ? 'active' : '' ?>">Переименование телефонов</a>
</div>

<?php if ($tab === 'phones'): ?>
<div class="phones-layout">
    <div class="buildings-sidebar" id="buildingsSidebar">
        <div class="buildings-header"><h3>Здания</h3></div>
        <ul class="buildings-list">
            <li class="building-item <?= $buildingFilter == 0 ? 'active' : '' ?>" onclick="filterByBuilding(0)">Все здания</li>
            <?php foreach ($buildings as $b): ?>
                <li class="building-item <?= $buildingFilter == $b['id'] ? 'active' : '' ?>" onclick="filterByBuilding(<?= $b['id'] ?>)">
                    <?= htmlspecialchars($b['name']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><?php foreach ($headers as $h): ?><th><?= $h ?></th><?php endforeach; ?><th></th></tr></thead>
            <tbody>
                <?php foreach ($phones as $phone): ?>
                <tr>
                    <?php foreach ($visibleCols as $col): ?>
                        <td><?= htmlspecialchars($phone[$col] ?? '') ?></td>
                    <?php endforeach; ?>
                    <td></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="table-wrapper">
    <table>
        <thead><tr><th>Телефон</th><th>Старое имя</th><th>Новое имя</th><th>Дата изменения</th></tr></thead>
        <tbody>
            <?php foreach ($renames as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['phone_name']) ?></td>
                <td><?= htmlspecialchars($r['old_name']) ?></td>
                <td><?= htmlspecialchars($r['new_name']) ?></td>
                <td><?= htmlspecialchars($r['changed_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
function filterByBuilding(buildingId) {
    const url = new URL(window.location);
    url.searchParams.set('page', 'phones');
    url.searchParams.set('tab', 'phones');
    if (buildingId > 0) url.searchParams.set('building', buildingId);
    else url.searchParams.delete('building');
    window.location = url.toString();
}
</script>