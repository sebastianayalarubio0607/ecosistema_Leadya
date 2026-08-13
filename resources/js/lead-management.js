import './bootstrap';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const statusFor = (element) => element.closest('tr')?.querySelector('[data-row-status]');

const setStatus = (element, message, tone = 'muted') => {
    const status = statusFor(element);

    if (!status) {
        return;
    }

    status.textContent = message;
    status.classList.remove('text-white/50', 'text-emerald-200', 'text-amber-200', 'text-rose-200');
    status.classList.add({
        muted: 'text-white/50',
        saving: 'text-amber-200',
        saved: 'text-emerald-200',
        error: 'text-rose-200',
    }[tone] || 'text-white/50');
};

const errorMessage = async (response) => {
    try {
        const payload = await response.json();
        const errors = payload.errors || {};
        const first = Object.values(errors).flat()[0];

        return first || payload.message || 'No se pudo guardar.';
    } catch {
        return 'No se pudo guardar.';
    }
};

const patch = async (url, payload) => {
    const response = await fetch(url, {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error(await errorMessage(response));
    }

    return response.json();
};

const bindCrmStateSelects = () => {
    document.querySelectorAll('[data-lead-crm-state]').forEach((select) => {
        if (select.dataset.bound === 'true') {
            return;
        }

        select.dataset.bound = 'true';
        select.addEventListener('change', async () => {
            const previous = select.dataset.originalValue || '';
            const selected = select.selectedOptions[0];

            setStatus(select, 'Guardando...', 'saving');
            select.disabled = true;

            try {
                const payload = await patch(select.dataset.updateUrl, { crm_state: select.value });
                const qualification = select.closest('tr')?.querySelector('[data-lead-qualification]');

                select.dataset.originalValue = payload.crm_state || select.value;
                if (qualification) {
                    qualification.textContent = payload.qualification_name || selected?.dataset.qualification || 'Sin Calificacion';
                    qualification.title = qualification.textContent;
                }

                setStatus(select, 'Guardado', 'saved');
            } catch (error) {
                select.value = previous;
                setStatus(select, error.message, 'error');
            } finally {
                select.disabled = false;
            }
        });
    });
};

const saveValue = async (input) => {
    const value = input.value.trim();
    const previous = input.dataset.originalValue || '';

    if (value === previous) {
        return;
    }

    setStatus(input, 'Guardando...', 'saving');

    try {
        const payload = await patch(input.dataset.updateUrl, { value: value === '' ? null : value });
        input.dataset.originalValue = payload.value ?? '';
        input.value = payload.value ?? '';
        setStatus(input, 'Guardado', 'saved');
    } catch (error) {
        setStatus(input, error.message, 'error');
    }
};

const bindValueInputs = () => {
    document.querySelectorAll('[data-lead-value]').forEach((input) => {
        if (input.dataset.bound === 'true') {
            return;
        }

        let timeout = null;

        input.dataset.bound = 'true';
        input.addEventListener('input', () => {
            window.clearTimeout(timeout);
            timeout = window.setTimeout(() => saveValue(input), 700);
        });
        input.addEventListener('change', () => {
            window.clearTimeout(timeout);
            saveValue(input);
        });
        input.addEventListener('blur', () => {
            window.clearTimeout(timeout);
            saveValue(input);
        });
    });
};

const mountLeadManagement = () => {
    bindCrmStateSelects();
    bindValueInputs();
};

document.addEventListener('DOMContentLoaded', mountLeadManagement);
document.addEventListener('livewire:navigated', mountLeadManagement);
