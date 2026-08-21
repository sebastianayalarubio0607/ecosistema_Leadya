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

const reactivePathPrefixes = ['/meta', '/google-ads', '/customers'];
let reactiveViewsReady = false;
let reactivePageAbortController = null;

const isReactivePage = () => Boolean(document.querySelector('[data-reactive-page="1"]'));

const isReactiveUrl = (url) => {
    let nextUrl;

    try {
        nextUrl = new URL(url, window.location.href);
    } catch {
        return false;
    }

    if (nextUrl.origin !== window.location.origin) {
        return false;
    }

    return reactivePathPrefixes.some((prefix) => (
        nextUrl.pathname === prefix || nextUrl.pathname.startsWith(`${prefix}/`)
    ));
};

const reactiveRegion = (name, root = document) => (
    root.querySelector(`[data-reactive-region="${name}"]`)
);

const isInsideReactiveRegion = (element) => (
    Boolean(element?.closest('[data-reactive-region]'))
);

const formSnapshot = (form) => {
    try {
        return new URLSearchParams(new FormData(form)).toString();
    } catch {
        return '';
    }
};

const snapshotReactiveForms = () => {
    document.querySelectorAll('[data-reactive-region] form').forEach((form) => {
        form.dataset.reactiveInitial = formSnapshot(form);
    });
};

const hasActiveReactiveEditor = () => {
    const active = document.activeElement;

    if (!active || active === document.body || !isInsideReactiveRegion(active)) {
        return false;
    }

    return active.isContentEditable || ['INPUT', 'SELECT', 'TEXTAREA'].includes(active.tagName);
};

const hasDirtyReactiveForm = () => (
    Array.from(document.querySelectorAll('[data-reactive-region] form')).some((form) => (
        form.dataset.reactiveInitial !== undefined && form.dataset.reactiveInitial !== formSnapshot(form)
    ))
);

const shouldSkipReactivePoll = () => (
    document.hidden || hasActiveReactiveEditor() || hasDirtyReactiveForm()
);

const captureTableScrollPositions = () => (
    Array.from(document.querySelectorAll('[data-sortable-table-wrap]')).map((element) => element.scrollLeft)
);

const restoreTableScrollPositions = (positions) => {
    document.querySelectorAll('[data-sortable-table-wrap]').forEach((element, index) => {
        element.scrollLeft = positions[index] ?? 0;
    });
};

const runInlineScripts = (root) => {
    root.querySelectorAll('script').forEach((oldScript) => {
        const script = document.createElement('script');

        Array.from(oldScript.attributes).forEach((attribute) => {
            script.setAttribute(attribute.name, attribute.value);
        });

        script.textContent = oldScript.textContent;
        oldScript.replaceWith(script);
    });
};

const replaceReactiveRegions = (nextDocument) => {
    const scrollPositions = captureTableScrollPositions();
    let replaced = false;

    ['page-header', 'page-flashes', 'page-content'].forEach((name) => {
        const current = reactiveRegion(name);
        const next = reactiveRegion(name, nextDocument);

        if (!current || !next) {
            return;
        }

        current.replaceWith(next);
        replaced = true;
    });

    if (!replaced) {
        return false;
    }

    const content = reactiveRegion('page-content');

    if (content) {
        runInlineScripts(content);
    }

    restoreTableScrollPositions(scrollPositions);
    setupSortableTables();
    snapshotReactiveForms();

    document.dispatchEvent(new CustomEvent('reactive-page:updated'));

    return true;
};

const loadReactivePage = async ({
    url = window.location.href,
    method = 'GET',
    body = null,
    push = false,
    replace = false,
    skipIfEditing = false,
} = {}) => {
    if (!isReactivePage() || !isReactiveUrl(url)) {
        return false;
    }

    if (skipIfEditing && shouldSkipReactivePoll()) {
        return false;
    }

    if (reactivePageAbortController) {
        reactivePageAbortController.abort();
    }

    const abortController = new AbortController();
    reactivePageAbortController = abortController;

    try {
        const response = await fetch(url, {
            method,
            body: method === 'GET' ? null : body,
            credentials: 'same-origin',
            redirect: 'follow',
            signal: abortController.signal,
            headers: {
                Accept: 'text/html, application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('text/html')) {
            window.location.href = response.url || url;
            return true;
        }

        const html = await response.text();
        const nextDocument = new DOMParser().parseFromString(html, 'text/html');

        if (!reactiveRegion('page-content', nextDocument)) {
            window.location.href = response.url || url;
            return true;
        }

        if (nextDocument.title) {
            document.title = nextDocument.title;
        }

        const finalUrl = response.url || url;
        const didReplace = replaceReactiveRegions(nextDocument);

        if (didReplace && finalUrl !== window.location.href) {
            if (replace) {
                window.history.replaceState({}, '', finalUrl);
            } else if (push) {
                window.history.pushState({}, '', finalUrl);
            }
        }

        return didReplace;
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Reactive page update failed', error);
        }

        return false;
    } finally {
        if (reactivePageAbortController === abortController) {
            reactivePageAbortController = null;
        }
    }
};

const handleReactiveClick = (event) => {
    const link = event.target.closest('a[href]');

    if (
        !link
        || event.defaultPrevented
        || event.button !== 0
        || event.metaKey
        || event.ctrlKey
        || event.shiftKey
        || event.altKey
        || !isInsideReactiveRegion(link)
        || link.hasAttribute('download')
        || link.hasAttribute('wire:navigate')
        || (link.target && link.target !== '_self')
    ) {
        return;
    }

    const href = link.getAttribute('href') || '';

    if (href.startsWith('#') || !isReactiveUrl(link.href)) {
        return;
    }

    event.preventDefault();
    loadReactivePage({ url: link.href, push: true });
};

const handleReactiveSubmit = (event) => {
    const form = event.target;

    if (
        !(form instanceof HTMLFormElement)
        || event.defaultPrevented
        || !isInsideReactiveRegion(form)
        || form.dataset.reactiveNative === 'true'
        || (form.target && form.target !== '_self')
    ) {
        return;
    }

    const submitter = event.submitter;
    const method = (submitter?.getAttribute('formmethod') || form.getAttribute('method') || 'GET').toUpperCase();
    const action = new URL(submitter?.getAttribute('formaction') || form.getAttribute('action') || window.location.href, window.location.href);

    if (!isReactiveUrl(action.href)) {
        return;
    }

    const formData = new FormData(form);

    if (submitter?.name) {
        formData.append(submitter.name, submitter.value);
    }

    event.preventDefault();

    if (method === 'GET') {
        action.search = new URLSearchParams(formData).toString();
        loadReactivePage({ url: action.href, push: true });
        return;
    }

    loadReactivePage({
        url: action.href,
        method,
        body: formData,
        push: true,
    });
};

const setupReactiveViews = () => {
    if (!isReactivePage()) {
        return;
    }

    snapshotReactiveForms();

    if (reactiveViewsReady) {
        return;
    }

    reactiveViewsReady = true;
    document.addEventListener('click', handleReactiveClick);
    document.addEventListener('submit', handleReactiveSubmit);
    window.addEventListener('popstate', () => {
        loadReactivePage({ url: window.location.href, replace: true });
    });
};

const setupInteractiveEnhancements = () => {
    setupSortableTables();
    setupReactiveViews();
};

document.addEventListener('DOMContentLoaded', setupInteractiveEnhancements);
document.addEventListener('livewire:navigated', setupInteractiveEnhancements);
document.addEventListener('livewire:init', () => {
    Livewire.on('reactive-view-refresh', () => {
        loadReactivePage({ skipIfEditing: true });
    });

    Livewire.hook('morphed', () => requestAnimationFrame(setupInteractiveEnhancements));
});
