import './bootstrap';

const parseSortableValue = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw || raw === '-') {
        return { type: 'empty', value: '' };
    }

    const digitsOnlyCandidate = raw.replace(/[$%\s.,-]/g, '');
    const looksNumeric = /^\d+$/.test(digitsOnlyCandidate);
    const numericCandidate = raw
        .replace(/[^\d,.-]/g, '')
        .replace(/\.(?=\d{3}(\D|$))/g, '')
        .replace(',', '.');
    const number = Number.parseFloat(numericCandidate);

    if (looksNumeric && numericCandidate && Number.isFinite(number)) {
        return { type: 'number', value: number };
    }

    return { type: 'text', value: raw.toLocaleLowerCase('es') };
};

const compareSortableValues = (left, right, direction) => {
    if (left.type === 'empty' && right.type !== 'empty') return 1;
    if (right.type === 'empty' && left.type !== 'empty') return -1;

    const multiplier = direction === 'asc' ? 1 : -1;

    if (left.type === 'number' && right.type === 'number') {
        return (left.value - right.value) * multiplier;
    }

    return String(left.value).localeCompare(String(right.value), 'es', {
        numeric: true,
        sensitivity: 'base',
    }) * multiplier;
};

const setupSortableTables = () => {
    document.querySelectorAll('[data-sortable-table]').forEach((table) => {
        const tbody = table.tBodies[0];
        if (!tbody) return;

        Array.from(tbody.rows).forEach((row, index) => {
            row.dataset.originalIndex = String(index);
        });

        table.querySelectorAll('[data-sort-header]').forEach((button) => {
            if (button.dataset.sortReady === '1') return;
            button.dataset.sortReady = '1';

            button.addEventListener('click', () => {
                const columnIndex = Number.parseInt(button.dataset.columnIndex, 10);
                if (!Number.isInteger(columnIndex)) return;

                const status = table.closest('[data-sortable-table-wrap]')?.querySelector('[data-sort-status]');
                status?.classList.remove('hidden');

                window.requestAnimationFrame(() => {
                    const currentDirection = button.dataset.sortDirection || 'none';
                    const nextDirection = currentDirection === 'asc' ? 'desc' : 'asc';
                    const rows = Array.from(tbody.rows);

                    table.querySelectorAll('[data-sort-header]').forEach((header) => {
                        header.dataset.sortDirection = 'none';
                        header.closest('th')?.setAttribute('aria-sort', 'none');
                        const icon = header.querySelector('[data-sort-icon]');
                        if (icon) icon.textContent = 'sort';
                    });

                    button.dataset.sortDirection = nextDirection;
                    button.closest('th')?.setAttribute('aria-sort', nextDirection === 'asc' ? 'ascending' : 'descending');
                    const activeIcon = button.querySelector('[data-sort-icon]');
                    if (activeIcon) activeIcon.textContent = nextDirection;

                    rows.sort((a, b) => {
                        const left = parseSortableValue(a.cells[columnIndex]?.textContent);
                        const right = parseSortableValue(b.cells[columnIndex]?.textContent);
                        const result = compareSortableValues(left, right, nextDirection);

                        if (result !== 0) return result;

                        return Number(a.dataset.originalIndex || 0) - Number(b.dataset.originalIndex || 0);
                    });

                    rows.forEach((row) => tbody.appendChild(row));
                    window.setTimeout(() => status?.classList.add('hidden'), 120);
                });
            });
        });
    });
};

document.addEventListener('DOMContentLoaded', setupSortableTables);
document.addEventListener('livewire:navigated', setupSortableTables);
document.addEventListener('livewire:init', () => {
    Livewire.hook('morphed', () => requestAnimationFrame(setupSortableTables));
});
