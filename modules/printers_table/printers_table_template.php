<div class="tabs-inline">
    <?php foreach (['95','175','845','835'] as $model): ?>
        <a href="?page=printers&tab=<?= $model ?>" class="tab <?= $tab === $model ? 'active' : '' ?>"><?= $model ?></a>
    <?php endforeach; ?>
</div>

<div class="table-wrapper">
    <div class="toolbar">
        <button class="btn" onclick="openAddForm('printer')">Добавить принтер</button>
    </div>
    <table>
        <thead><tr><?php foreach ($headers as $h): ?><th><?= $h ?></th><?php endforeach; ?><th></th></tr></thead>
        <tbody>
            <?php foreach ($printers as $printer): ?>
            <tr>
                <?php foreach ($visibleCols as $col): ?>
                    <td><?= htmlspecialchars($printer[$col] ?? '') ?></td>
                <?php endforeach; ?>
                <td></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>