/**
 * Progressively enhances collection search and pagination with live updates.
 */

(() => {
    const form = document.querySelector('[data-live-search]');

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const searchInput = form.querySelector('input[name="q"]');
    const statusSelect = form.querySelector('select[name="status"]');
    const platformInput = form.querySelector('input[name="platform"]');
    const resetButton = form.querySelector('[data-reset-filters]');
    let requestController = null;
    let debounceTimer = null;

    const requestResults = async (url, historyMode = 'push') => {
        const panel = document.querySelector('.collection-panel');
        if (!(panel instanceof HTMLElement)) {
            return;
        }

        requestController?.abort();
        requestController = new AbortController();
        const activeController = requestController;
        panel.classList.add('is-loading');
        panel.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                signal: activeController.signal,
            });

            if (!response.ok) {
                throw new Error('Search request failed.');
            }

            const documentResult = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = documentResult.querySelector('.collection-panel');
            if (!(replacement instanceof HTMLElement)) {
                throw new Error('Search results were unavailable.');
            }

            panel.replaceWith(replacement);
            if (historyMode === 'push') {
                window.history.pushState({}, '', url);
            } else if (historyMode === 'replace') {
                window.history.replaceState({}, '', url);
            }
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            window.location.assign(url);
        } finally {
            if (requestController === activeController) {
                const currentPanel = document.querySelector('.collection-panel');
                currentPanel?.classList.remove('is-loading');
                currentPanel?.removeAttribute('aria-busy');
            }
        }
    };

    const formUrl = () => {
        const url = new URL('/', window.location.origin);
        const parameters = new URLSearchParams(new FormData(form));
        parameters.delete('page');
        url.search = parameters.toString();
        return url.toString();
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        requestResults(formUrl());
    });

    searchInput?.addEventListener('input', () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => requestResults(formUrl(), 'replace'), 300);
    });

    searchInput?.addEventListener('search', () => requestResults(formUrl(), 'replace'));

    statusSelect?.addEventListener('change', () => requestResults(formUrl()));

    resetButton?.addEventListener('click', () => {
        if (searchInput instanceof HTMLInputElement) {
            searchInput.value = '';
        }
        if (statusSelect instanceof HTMLSelectElement) {
            statusSelect.value = 'all';
        }
        if (platformInput instanceof HTMLInputElement) {
            platformInput.value = 'all';
        }

        document.querySelectorAll('.platform-tab').forEach((tab) => {
            const isAllPlatforms = tab.textContent?.trim().startsWith('All platforms') ?? false;
            tab.classList.toggle('is-active', isAllPlatforms);
            if (isAllPlatforms) {
                tab.setAttribute('aria-current', 'page');
            } else {
                tab.removeAttribute('aria-current');
            }
        });

        requestResults(formUrl());
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const paginationLink = target.closest('.pagination a');
        if (!(paginationLink instanceof HTMLAnchorElement)) {
            return;
        }

        event.preventDefault();
        requestResults(paginationLink.href);
    });

    window.addEventListener('popstate', () => {
        const parameters = new URL(window.location.href).searchParams;
        if (searchInput instanceof HTMLInputElement) {
            searchInput.value = parameters.get('q') ?? '';
        }
        if (statusSelect instanceof HTMLSelectElement) {
            statusSelect.value = parameters.get('status') ?? 'all';
        }
        requestResults(window.location.href, 'none');
    });
})();
