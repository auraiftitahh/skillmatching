document.querySelectorAll('.mobile-navigation a').forEach((link) => {
    link.addEventListener('click', () => {
        link.closest('details').open = false;
    });
});

const roleSelector = document.querySelector('[data-registration-role]');
const institutionFields = document.getElementById('institution-fields');
if (roleSelector && institutionFields) {
    const updateInstitutionFields = () => {
        const isInstitution = roleSelector.value === 'sertifikasi';
        institutionFields.hidden = !isInstitution;
        institutionFields.disabled = !isInstitution;
        institutionFields.querySelectorAll('[data-required-for-institution]').forEach((input) => {
            input.required = isInstitution;
        });
    };
    roleSelector.addEventListener('change', updateInstitutionFields);
    updateInstitutionFields();
}

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        button.textContent = showPassword ? 'Sembunyikan' : 'Lihat';
        button.setAttribute('aria-pressed', String(showPassword));
        const label = document.querySelector(`label[for="${input.id}"]`).textContent.toLowerCase();
        button.setAttribute('aria-label', `${showPassword ? 'Sembunyikan' : 'Tampilkan'} ${label}`);
    });
});

document.querySelectorAll('[data-pending-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
            button.textContent = button.dataset.pendingLabel;
        });
        form.querySelector('[data-pending-status]').textContent = 'Permintaan sedang diproses. Mohon tunggu.';
    });
});
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});
