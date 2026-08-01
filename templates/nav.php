<button data-tab="nodes" class="active">Узлы</button>
<button data-tab="warehouse">Склад</button>
<button data-tab="checklist">Чек-лист</button>
<button data-tab="logs">Журнал</button>
<?php if (isSuperAdmin()): ?>
<button data-tab="users">Пользователи</button>
<?php endif; ?>
<div class="user-info">
    <span><?= currentUser()['full_name'] ?></span>
    <button class="btn small secondary" id="logoutBtn">Выйти</button>
</div>