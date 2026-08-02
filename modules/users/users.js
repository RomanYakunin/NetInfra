// modules/users/users.js — страница «Пользователи»
(function () {
    'use strict';

    let usersCache = [];

    const ROLE_LABELS = {
        admin: 'Администратор',
        user:  'Пользователь'
    };

    function esc(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type);
        else alert(msg);
    }

    // ---------- Загрузка списка ----------
    async function loadUsers() {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" class="users-empty">Загрузка…</td></tr>';

        try {
            const resp = await fetch('?ajax=get_users');
            const data = await resp.json();
            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="6" class="users-empty users-error">${esc(data.error)}</td></tr>`;
                return;
            }
            usersCache = data.data || [];
            renderUsers();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="6" class="users-empty users-error">Ошибка загрузки</td></tr>';
        }
    }

    // ---------- Отрисовка таблицы ----------
    function renderUsers() {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        const query = (document.getElementById('usersSearch')?.value || '').trim().toLowerCase();
        const rows = usersCache.filter(u => !query || (u.login || '').toLowerCase().includes(query));

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="users-empty">Пользователи не найдены</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(u => {
            const created = u.created_at ? String(u.created_at).slice(0, 16).replace('T', ' ') : '—';
            const roleClass = u.role === 'admin' ? 'role-admin' : 'role-user';
            const activeBadge = u.is_active
                ? '<span class="users-badge active">Активен</span>'
                : '<span class="users-badge blocked">Заблокирован</span>';
            const mustChange = u.must_change_password
                ? '<span class="users-badge warn">Требуется</span>'
                : '<span class="users-muted">—</span>';

            return `
            <tr data-user-id="${u.id}">
                <td class="users-login">${esc(u.login)}</td>
                <td><span class="users-badge ${roleClass}">${esc(ROLE_LABELS[u.role] || u.role)}</span></td>
                <td>${activeBadge}</td>
                <td>${mustChange}</td>
                <td class="users-muted">${esc(created)}</td>
                <td class="users-actions">
                    <button type="button" class="btn small" data-act="edit" title="Редактировать">✎</button>
                    <button type="button" class="btn small secondary" data-act="toggle" title="${u.is_active ? 'Заблокировать' : 'Разблокировать'}">${u.is_active ? '🔒' : '🔓'}</button>
                    <button type="button" class="btn small danger" data-act="delete" title="Удалить">🗑</button>
                </td>
            </tr>`;
        }).join('');

        // Обработчики кнопок строки
        tbody.querySelectorAll('tr[data-user-id]').forEach(tr => {
            const id = parseInt(tr.dataset.userId, 10);
            tr.querySelectorAll('button[data-act]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const act = btn.dataset.act;
                    if (act === 'edit') openUserForm(id);
                    else if (act === 'toggle') toggleUserActive(id);
                    else if (act === 'delete') deleteUser(id);
                });
            });
        });
    }

    // ---------- Форма пользователя ----------
    function openUserForm(userId = null) {
        const modal = document.getElementById('userFormModal');
        const form = document.getElementById('userForm');
        if (!modal || !form) return;

        form.reset();
        hideFormError();

        const isEdit = userId !== null;
        const user = isEdit ? usersCache.find(u => u.id === userId) : null;

        document.getElementById('userFormTitle').textContent = isEdit
            ? `Редактировать: ${user ? user.login : ''}`
            : 'Добавить пользователя';
        document.getElementById('userFormId').value = isEdit ? userId : '';

        // При редактировании логин менять нельзя (он уникальный ключ входа)
        const loginGroup = document.getElementById('userLoginGroup');
        const loginInput = document.getElementById('userFormLogin');
        loginInput.value = user ? user.login : '';
        loginInput.disabled = isEdit;
        loginGroup.style.display = '';

        // Пароль: при создании обязателен, при редактировании — только для сброса
        document.getElementById('userPasswordLabel').textContent = isEdit ? 'Новый пароль' : 'Пароль';
        document.getElementById('userPasswordHint').textContent = isEdit
            ? 'Оставьте пустым, чтобы не менять пароль'
            : 'Минимум 6 символов';
        document.getElementById('userFormPassword').value = '';
        document.getElementById('userFormPassword').required = !isEdit;

        document.getElementById('userFormRole').value = user ? user.role : 'user';
        document.getElementById('userFormMustChange').checked = user ? !!user.must_change_password : false;

        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');
    }

    function closeUserForm() {
        const modal = document.getElementById('userFormModal');
        if (modal) modal.classList.remove('visible');
        hideFormError();
    }

    function showFormError(msg) {
        const el = document.getElementById('userFormError');
        if (!el) return;
        el.textContent = msg;
        el.style.display = '';
    }
    function hideFormError() {
        const el = document.getElementById('userFormError');
        if (el) { el.textContent = ''; el.style.display = 'none'; }
    }

    async function submitUserForm(e) {
        e.preventDefault();
        hideFormError();

        const form = e.target;
        const id = document.getElementById('userFormId').value;
        const isEdit = !!id;

        const fd = new FormData();
        if (isEdit) fd.append('id', id);
        else fd.append('login', document.getElementById('userFormLogin').value.trim());

        const password = document.getElementById('userFormPassword').value;
        if (password) fd.append('password', password);
        fd.append('role', document.getElementById('userFormRole').value);
        fd.append('must_change_password', document.getElementById('userFormMustChange').checked ? '1' : '0');

        if (!isEdit && !password) {
            showFormError('Пароль обязателен');
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const url = isEdit ? '?ajax=update_user' : '?ajax=add_user';
            const resp = await fetch(url, { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                closeUserForm();
                toast(isEdit ? 'Пользователь обновлён' : 'Пользователь добавлен', 'success');
                await loadUsers();
            } else {
                showFormError(data.error || 'Ошибка сохранения');
            }
        } catch (err) {
            showFormError('Ошибка сети');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    // ---------- Блокировка / разблокировка ----------
    async function toggleUserActive(userId) {
        const user = usersCache.find(u => u.id === userId);
        if (!user) return;
        const next = user.is_active ? 0 : 1;
        const verb = next ? 'разблокировать' : 'заблокировать';
        if (!confirm(`Вы уверены, что хотите ${verb} пользователя «${user.login}»?`)) return;

        const fd = new FormData();
        fd.append('id', userId);
        fd.append('is_active', next);

        try {
            const resp = await fetch('?ajax=update_user', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                toast(next ? 'Пользователь разблокирован' : 'Пользователь заблокирован', 'success');
                await loadUsers();
            } else {
                toast(data.error || 'Ошибка', 'error');
            }
        } catch (e) { toast('Ошибка сети', 'error'); }
    }

    // ---------- Удаление ----------
    async function deleteUser(userId) {
        const user = usersCache.find(u => u.id === userId);
        if (!user) return;
        if (!confirm(`Удалить пользователя «${user.login}»? Действие необратимо.`)) return;

        const fd = new FormData();
        fd.append('id', userId);

        try {
            const resp = await fetch('?ajax=delete_user', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                toast('Пользователь удалён', 'success');
                await loadUsers();
            } else {
                toast(data.error || 'Ошибка', 'error');
            }
        } catch (e) { toast('Ошибка сети', 'error'); }
    }

    // ---------- Инициализация ----------
    function init() {
        if (!document.getElementById('usersTable')) return;   // не наша страница

        document.getElementById('addUserBtn')?.addEventListener('click', () => openUserForm(null));
        document.getElementById('userForm')?.addEventListener('submit', submitUserForm);
        document.getElementById('usersSearch')?.addEventListener('input', renderUsers);

        loadUsers();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Нужны разметке (onclick) и внешнему коду
    window.closeUserForm = closeUserForm;
    window.openUserForm = openUserForm;
})();
