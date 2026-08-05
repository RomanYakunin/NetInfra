<?php
/**
 * modules/users/force_password_change.php
 *
 * Экран принудительной смены пароля. Показывается вместо интерфейса,
 * пока у учётной записи стоит признак must_change_password.
 *
 * Нужен отдельно от формы входа: пользователь мог войти раньше, чем
 * признак поставили, либо просто обновить страницу на шаге смены —
 * без серверной проверки этот шаг обходился бы одним F5.
 */
$forceLogin = $_SESSION['login'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Смена пароля</title>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <style>
        body {
            margin: 0; height: 100vh;
            display: flex; justify-content: center; align-items: center;
            background: #f0f2f5; color: #333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-box {
            background: #fff; padding: 40px; border-radius: 8px; width: 380px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2); text-align: center;
        }
        .login-box h2 { margin: 0 0 8px; color: #2c3e50; }
        .login-hint { margin: 0 0 22px; font-size: 14px; color: #666; line-height: 1.5; }
        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
        .form-group input {
            width: 100%; padding: 10px; border: 1px solid #ddd;
            border-radius: 4px; font-size: 15px; box-sizing: border-box;
        }
        .form-group input:focus { border-color: #3498db; outline: none; }
        button {
            width: 100%; padding: 12px; font-size: 16px; margin-top: 6px;
            background: #3498db; color: #fff; border: none;
            border-radius: 4px; cursor: pointer;
        }
        button:hover { background: #2980b9; }
        .error { color: #e74c3c; margin-bottom: 14px; font-size: 14px; min-height: 18px; }
        .logout-link {
            display: inline-block; margin-top: 16px;
            font-size: 13px; color: #7f8c8d; text-decoration: none;
        }
        .logout-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Смена пароля</h2>
        <p class="login-hint">
            Учётная запись <b><?= htmlspecialchars($forceLogin) ?></b> требует смены пароля
            при первом входе. Без этого продолжить не получится.
        </p>
        <div class="error" id="change-error"></div>
        <form id="force-change-form">
            <div class="form-group">
                <label>Текущий пароль</label>
                <input type="password" id="current-password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label>Новый пароль</label>
                <input type="password" id="new-password" required autocomplete="new-password" minlength="6">
            </div>
            <div class="form-group">
                <label>Подтвердите пароль</label>
                <input type="password" id="confirm-password" required autocomplete="new-password" minlength="6">
            </div>
            <button type="submit">Сменить пароль</button>
        </form>
        <a class="logout-link" href="?logout=1">Выйти</a>
    </div>

    <script>
        document.getElementById('force-change-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const err = document.getElementById('change-error');
            err.textContent = '';

            const current = document.getElementById('current-password').value;
            const pass    = document.getElementById('new-password').value;
            const confirm = document.getElementById('confirm-password').value;

            if (pass !== confirm)  { err.textContent = 'Пароли не совпадают'; return; }
            if (pass.length < 6)   { err.textContent = 'Пароль должен быть не короче 6 символов'; return; }
            if (pass === current)  { err.textContent = 'Новый пароль совпадает с текущим'; return; }

            const fd = new FormData();
            fd.append('current_password', current);
            fd.append('new_password', pass);
            fd.append('confirm_password', confirm);

            try {
                const res = await fetch('?ajax=change_password', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) location.href = '/';
                else err.textContent = data.error || 'Не удалось сменить пароль';
            } catch {
                err.textContent = 'Ошибка сети';
            }
        });
    </script>
</body>
</html>
