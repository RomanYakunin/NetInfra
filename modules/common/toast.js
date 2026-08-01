function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = 'position:fixed; top:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px;';
        document.body.appendChild(container);
    }

    const typeDefaults = {
        success: {
            title: 'Успешно',
            icon: '✅',
            gradient: 'linear-gradient(135deg, #43a047 0%, #1b5e20 100%)',
            borderLeft: '4px solid #a5d6a7',
            shadow: '0 6px 16px rgba(0,100,0,0.35)'
        },
        error: {
            title: 'Ошибка',
            icon: '❌',
            gradient: 'linear-gradient(135deg, #e53935 0%, #b71c1c 100%)',
            borderLeft: '4px solid #ef9a9a',
            shadow: '0 6px 16px rgba(200,0,0,0.35)'
        },
        warning: {
            title: 'Внимание',
            icon: '⚠️',
            gradient: 'linear-gradient(135deg, #fb8c00 0%, #e65100 100%)',
            borderLeft: '4px solid #ffcc80',
            shadow: '0 6px 16px rgba(255,100,0,0.35)'
        },
        info: {
            title: 'Информация',
            icon: 'ℹ️',
            gradient: 'linear-gradient(135deg, #1976d2 0%, #0d47a1 100%)',
            borderLeft: '4px solid #90caf9',
            shadow: '0 6px 16px rgba(0,80,200,0.35)'
        },
        incomplete: {
            title: 'Незаполненные данные',
            icon: '⚠️',
            gradient: 'linear-gradient(135deg, #ffa726 0%, #f57c00 100%)',
            borderLeft: '4px solid #ffe0b2',
            shadow: '0 6px 16px rgba(255, 152, 0, 0.35)'
        }
    };

    const settings = typeDefaults[type] || typeDefaults.info;

    const toast = document.createElement('div');
    toast.className = 'custom-toast ' + type;
    toast.setAttribute('role', 'alert');

    toast.innerHTML = `
        <div class="toast-header" style="display:flex; align-items:center; gap:10px; margin-bottom:8px; padding-bottom:6px; border-bottom:1px solid rgba(255,255,255,0.2);">
            <span style="font-size:1.1rem;">${settings.icon}</span>
            <strong style="flex:1; font-weight:600;">${settings.title}</strong>
            <small style="opacity:0.7; font-size:0.8rem; white-space:nowrap;">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</small>
        </div>
        <div style="font-size:0.95rem; line-height:1.4;">${message}</div>
    `;

    Object.assign(toast.style, {
        padding: '14px 22px',
        borderRadius: '10px',
        color: '#fff',
        fontWeight: '500',
        maxWidth: '420px',
        wordWrap: 'break-word',
        boxShadow: settings.shadow,
        cursor: 'pointer',
        transition: 'opacity 0.3s ease, transform 0.3s ease',
        opacity: '1',
        transform: 'translateX(0)',
        animation: 'slideInRight 0.3s ease',
        background: settings.gradient,
        borderLeft: settings.borderLeft
    });

    container.appendChild(toast);

    // Задержка зависит от длины сообщения
    const baseDelay = 4000; // минимум 4 секунды
    const extra = Math.max(0, message.length - 50);
    const delay = Math.min(baseDelay + extra * 10, 12000); // до 12 секунд

    const hide = () => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    };

    setTimeout(hide, delay);
    toast.addEventListener('click', hide);
}

// Анимация (добавьте, если ещё нет)
if (!document.getElementById('toast-animation-style')) {
    const style = document.createElement('style');
    style.id = 'toast-animation-style';
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
}
window.showToast = showToast;