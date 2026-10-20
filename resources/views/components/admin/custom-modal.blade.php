{{-- Reusable Admin Custom Modal Component --}}
<div id="adminAppModalBackdrop" class="admin-app-modal-backdrop" style="display: none;" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="admin-app-modal-dialog">
        <div class="admin-app-modal-header">
            <div id="adminAppModalIcon" class="admin-app-modal-icon">
                {{-- Dynamic Lucide SVG Icon injected via JS --}}
            </div>
            <div>
                <h3 id="adminAppModalTitle" class="admin-app-modal-title">Confirmation</h3>
                <span id="adminAppModalSubtitle" class="admin-app-modal-subtitle">Bansud Central School</span>
            </div>
            <button type="button" id="adminAppModalCloseX" class="admin-app-modal-close" aria-label="Close modal">
                <x-lucide-x class="modal-close-icon" />
            </button>
        </div>

        <div class="admin-app-modal-body">
            <p id="adminAppModalMessage" class="admin-app-modal-message"></p>
        </div>

        <div class="admin-app-modal-footer">
            <button type="button" id="adminAppModalCancelBtn" class="btn-secondary admin-app-modal-btn">Cancel</button>
            <button type="button" id="adminAppModalConfirmBtn" class="btn-primary admin-app-modal-btn">Confirm</button>
        </div>
    </div>
</div>

<style>
.admin-app-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    opacity: 0;
    transition: opacity 0.2s ease-in-out;
}
.admin-app-modal-backdrop.show {
    opacity: 1;
}
.admin-app-modal-dialog {
    background: #ffffff;
    width: 100%;
    max-width: 440px;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
    overflow: hidden;
    transform: scale(0.97);
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid #E2E8F0;
}
.admin-app-modal-backdrop.show .admin-app-modal-dialog {
    transform: scale(1);
}
.admin-app-modal-header {
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
}
.admin-app-modal-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.admin-app-modal-icon svg {
    width: 22px;
    height: 22px;
}
.admin-app-modal-icon.icon-confirm,
.admin-app-modal-icon.icon-warning {
    background: #FEF3C7;
    color: #B45309;
}
.admin-app-modal-icon.icon-danger {
    background: #FEE2E2;
    color: #B91C1C;
}
.admin-app-modal-icon.icon-success {
    background: #DCFCE7;
    color: #15803D;
}
.admin-app-modal-icon.icon-info {
    background: #DBEAFE;
    color: #1D4ED8;
}
.admin-app-modal-title {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
    margin: 0;
    line-height: 1.2;
}
.admin-app-modal-subtitle {
    font-size: 11px;
    font-weight: 600;
    color: #64748B;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.admin-app-modal-close {
    position: absolute;
    right: 14px;
    top: 14px;
    background: transparent;
    border: none;
    color: #94A3B8;
    cursor: pointer;
    line-height: 1;
    padding: 6px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s, background-color 0.15s;
}
.admin-app-modal-close:hover {
    color: #0F172A;
    background-color: #E2E8F0;
}
.admin-app-modal-close .modal-close-icon {
    width: 18px;
    height: 18px;
}
.admin-app-modal-body {
    padding: 20px;
}
.admin-app-modal-message {
    color: #334155;
    font-size: 14px;
    line-height: 1.5;
    margin: 0;
    white-space: pre-line;
}
.admin-app-modal-footer {
    padding: 14px 20px;
    background: #F8FAFC;
    border-top: 1px solid #E2E8F0;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.admin-app-modal-btn {
    min-width: 90px;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
}
</style>

<script>
(() => {
    const backdrop = document.getElementById('adminAppModalBackdrop');
    const titleEl = document.getElementById('adminAppModalTitle');
    const msgEl = document.getElementById('adminAppModalMessage');
    const iconEl = document.getElementById('adminAppModalIcon');
    const confirmBtn = document.getElementById('adminAppModalConfirmBtn');
    const cancelBtn = document.getElementById('adminAppModalCancelBtn');
    const closeX = document.getElementById('adminAppModalCloseX');

    let currentConfirmCallback = null;

    // Official Lucide outline SVG vectors
    const ICONS = {
        warning: `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        danger: `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>`,
        success: `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>`,
        info: `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>`,
    };

    function closeModal() {
        backdrop.classList.remove('show');
        setTimeout(() => {
            backdrop.style.display = 'none';
            currentConfirmCallback = null;
        }, 200);
    }

    window.showAdminConfirmModal = function(options) {
        const {
            title = 'Confirmation Required',
            message = 'Are you sure you want to proceed?',
            type = 'warning',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            onConfirm = null,
        } = options;

        titleEl.textContent = title;
        msgEl.textContent = message;
        confirmBtn.textContent = confirmText;
        cancelBtn.textContent = cancelText;
        cancelBtn.style.display = 'inline-block';

        iconEl.className = 'admin-app-modal-icon icon-' + type;
        iconEl.innerHTML = ICONS[type] || ICONS.warning;

        if (type === 'danger') {
            confirmBtn.className = 'admin-app-modal-btn';
            confirmBtn.style.background = '#B91C1C';
            confirmBtn.style.color = '#ffffff';
            confirmBtn.style.border = 'none';
        } else if (type === 'success') {
            confirmBtn.className = 'admin-app-modal-btn';
            confirmBtn.style.background = '#15803D';
            confirmBtn.style.color = '#ffffff';
            confirmBtn.style.border = 'none';
        } else {
            confirmBtn.className = 'btn-primary admin-app-modal-btn';
            confirmBtn.style.background = '#1D4ED8';
            confirmBtn.style.color = '#ffffff';
            confirmBtn.style.border = 'none';
        }

        currentConfirmCallback = onConfirm;

        backdrop.style.display = 'flex';
        requestAnimationFrame(() => {
            backdrop.classList.add('show');
            confirmBtn.focus();
        });
    };

    window.showAdminAlertModal = function(options) {
        const {
            title = 'Notice',
            message = '',
            type = 'info',
            buttonText = 'OK',
            onDismiss = null,
        } = options;

        titleEl.textContent = title;
        msgEl.textContent = message;
        confirmBtn.textContent = buttonText;
        cancelBtn.style.display = 'none';

        iconEl.className = 'admin-app-modal-icon icon-' + type;
        iconEl.innerHTML = ICONS[type] || ICONS.info;

        confirmBtn.className = 'btn-primary admin-app-modal-btn';
        confirmBtn.style.background = '#1D4ED8';
        confirmBtn.style.color = '#ffffff';
        confirmBtn.style.border = 'none';

        currentConfirmCallback = onDismiss;

        backdrop.style.display = 'flex';
        requestAnimationFrame(() => {
            backdrop.classList.add('show');
            confirmBtn.focus();
        });
    };

    confirmBtn.addEventListener('click', () => {
        const cb = currentConfirmCallback;
        closeModal();
        if (typeof cb === 'function') {
            cb();
        }
    });

    cancelBtn.addEventListener('click', closeModal);
    closeX.addEventListener('click', closeModal);

    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && backdrop.classList.contains('show')) {
            closeModal();
        }
    });

    // Intercept forms with data-confirm-message attribute
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form || !form.dataset || !form.dataset.confirmMessage) {
            return;
        }

        if (form.dataset.confirmed === 'true') {
            return;
        }

        e.preventDefault();

        window.showAdminConfirmModal({
            title: form.dataset.confirmTitle || 'Please Confirm',
            message: form.dataset.confirmMessage,
            type: form.dataset.confirmType || 'warning',
            confirmText: form.dataset.confirmBtn || 'Confirm',
            onConfirm: () => {
                form.dataset.confirmed = 'true';
                form.submit();
            }
        });
    });
})();
</script>
