import './bootstrap';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const statusFor = (element) => element.closest('tr')?.querySelector('[data-row-status]');

const setStatus = (element, message, tone = 'muted', spinner = false) => {
    const status = statusFor(element);

    if (!status) {
        return;
    }

    status.replaceChildren();

    if (spinner) {
        const icon = document.createElement('span');
        icon.className = 'mr-2 inline-block h-3 w-3 animate-spin rounded-full border-2 border-current border-r-transparent align-[-2px]';
        status.append(icon);
    }

    status.append(document.createTextNode(message));
    status.classList.remove('text-white/50', 'text-emerald-200', 'text-amber-200', 'text-rose-200');
    status.classList.add({
        muted: 'text-white/50',
        saving: 'text-amber-200',
        saved: 'text-emerald-200',
        error: 'text-rose-200',
    }[tone] || 'text-white/50');
};

const setSavingOverlay = (element, visible, message = 'Guardando...') => {
    const overlay = element.closest('[data-saving-control]')?.querySelector('[data-saving-overlay]');

    if (!overlay) {
        return;
    }

    overlay.replaceChildren();

    if (visible) {
        const icon = document.createElement('span');
        icon.className = 'mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent';
        overlay.append(icon, document.createTextNode(message));
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        return;
    }

    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
};

const setLocked = (element, locked, message = 'Guardando...') => {
    element.disabled = locked;
    element.classList.toggle('cursor-wait', locked);
    element.classList.toggle('opacity-60', locked);
    element.classList.toggle('ring-2', locked);
    element.classList.toggle('ring-amber-300/40', locked);
    setSavingOverlay(element, locked, message);
};

const unlockAfterCountdown = (element) => {
    let seconds = 3;
    setStatus(element, `Guardado. Disponible en ${seconds}s`, 'saved', true);
    setSavingOverlay(element, true, `Disponible en ${seconds}s`);

    const interval = window.setInterval(() => {
        seconds -= 1;

        if (seconds <= 0) {
            window.clearInterval(interval);
            setLocked(element, false);
            setStatus(element, 'Listo', 'muted');
            return;
        }

        setStatus(element, `Guardado. Disponible en ${seconds}s`, 'saved', true);
        setSavingOverlay(element, true, `Disponible en ${seconds}s`);
    }, 1000);
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
            if (select.disabled) {
                return;
            }

            const previous = select.dataset.originalValue || '';
            const selected = select.selectedOptions[0];

            setLocked(select, true, 'Guardando estado...');
            setStatus(select, 'Guardando estado...', 'saving', true);

            try {
                const payload = await patch(select.dataset.updateUrl, { crm_state: select.value });
                const qualification = select.closest('tr')?.querySelector('[data-lead-qualification]');

                select.dataset.originalValue = payload.crm_state || select.value;
                if (qualification) {
                    qualification.textContent = payload.qualification_name || selected?.dataset.qualification || 'Sin Calificacion';
                    qualification.title = qualification.textContent;
                }

                unlockAfterCountdown(select);
            } catch (error) {
                select.value = previous;
                setLocked(select, false);
                setStatus(select, error.message, 'error');
            }
        });
    });
};

const saveValue = async (input) => {
    if (input.disabled) {
        return;
    }

    const value = input.value.trim();
    const previous = input.dataset.originalValue || '';

    if (value === previous) {
        return;
    }

    setLocked(input, true, 'Guardando valor...');
    setStatus(input, 'Guardando valor...', 'saving', true);

    try {
        const payload = await patch(input.dataset.updateUrl, { value: value === '' ? null : value });
        input.dataset.originalValue = payload.value ?? '';
        input.value = payload.value ?? '';
        unlockAfterCountdown(input);
    } catch (error) {
        setLocked(input, false);
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
    document.querySelectorAll('[data-dismiss-management-notice]').forEach((button) => {
        if (button.dataset.bound === 'true') {
            return;
        }

        button.dataset.bound = 'true';
        button.addEventListener('click', () => {
            button.closest('[data-management-notice]')?.remove();
        });
    });
    bindCrmStateSelects();
    bindValueInputs();
};

document.addEventListener('DOMContentLoaded', mountLeadManagement);
document.addEventListener('livewire:navigated', mountLeadManagement);
