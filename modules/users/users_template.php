<!-- modules/users/users_template.php -->
<link rel="stylesheet" href="modules/users/users.css">

<div class="users-page">
    <div class="users-toolbar">
        <input type="text" id="usersSearch" class="users-search" placeholder="Поиск по логину...">
        <button type="button" class="btn" id="addUserBtn">+ Добавить пользователя</button>
    </div>

    <div class="table-wrapper">
        <table class="users-table" id="usersTable">
            <thead>
                <tr>
                    <th>Логин</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Смена пароля</th>
                    <th>Создан</th>
                    <th style="width:1%;">Действия</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <tr><td colspan="6" class="users-empty">Загрузка…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Модальное окно добавления/редактирования пользователя -->
<div class="add-form-modal" id="userFormModal">
    <div class="modal-content">
        <h3 id="userFormTitle">Добавить пользователя</h3>
        <form id="userForm" autocomplete="off">
            <input type="hidden" name="id" id="userFormId">

            <div class="form-group" id="userLoginGroup">
                <label>Логин</label>
                <input type="text" name="login" id="userFormLogin" required autocomplete="off">
            </div>

            <div class="form-group">
                <label id="userPasswordLabel">Пароль</label>
                <input type="password" name="password" id="userFormPassword" autocomplete="new-password">
                <small class="users-hint" id="userPasswordHint">Минимум 6 символов</small>
            </div>

            <div class="form-group">
                <label>Роль</label>
                <select name="role" id="userFormRole">
                    <option value="user">Пользователь (только просмотр)</option>
                    <option value="admin">Администратор</option>
                </select>
            </div>

            <div class="form-group users-checkbox-row">
                <label>
                    <input type="checkbox" name="must_change_password" id="userFormMustChange" value="1">
                    Требовать смену пароля при следующем входе
                </label>
            </div>

            <div class="form-error" id="userFormError" style="display:none;"></div>

            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeUserForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script src="modules/users/users.js"></script>
