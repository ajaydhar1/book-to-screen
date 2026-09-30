(() => {
    const storageKey = 'b2s:saved-adaptations:v1';
    let memoryIds = [];

    const normalizeId = value => {
        const normalized = String(value ?? '').trim();

        if (!/^\d+$/.test(normalized)) {
            return null;
        }

        const numericId = Number(normalized);

        return Number.isSafeInteger(numericId) && numericId > 0
            ? String(numericId)
            : null;
    };

    const normalizeIds = values => [...new Set(
        (Array.isArray(values) ? values : [])
            .map(normalizeId)
            .filter(Boolean)
    )];

    const getIds = () => {
        try {
            const storedValue = window.localStorage.getItem(storageKey);

            if (storedValue === null) {
                return [...memoryIds];
            }

            const parsedValue = JSON.parse(storedValue);
            memoryIds = normalizeIds(parsedValue);
            return [...memoryIds];
        } catch {
            return [...memoryIds];
        }
    };

    const setIds = ids => {
        memoryIds = normalizeIds(ids);

        try {
            window.localStorage.setItem(storageKey, JSON.stringify(memoryIds));
        } catch {
            // Keep the in-memory list usable when browser storage is unavailable.
        }

        refreshControls();
        return [...memoryIds];
    };

    const isSaved = id => {
        const normalizedId = normalizeId(id);
        return normalizedId !== null && getIds().includes(normalizedId);
    };

    const updateControl = (control, savedIds) => {
        const id = normalizeId(control.dataset.saveTmdbId);
        const isTheaterControl = control.hasAttribute('data-theater-save');

        if (isTheaterControl) {
            control.hidden = id === null;
        }

        if (id === null) {
            return;
        }

        const saved = savedIds.includes(id);
        control.textContent = saved ? 'Saved' : 'Save';
        control.setAttribute('aria-pressed', saved ? 'true' : 'false');
        control.dataset.saved = saved ? 'true' : 'false';
    };

    const refreshControls = () => {
        const savedIds = getIds();

        document.querySelectorAll('[data-save-tmdb-id]').forEach(control => {
            updateControl(control, savedIds);
        });
    };

    const toggle = id => {
        const normalizedId = normalizeId(id);

        if (normalizedId === null) {
            return false;
        }

        const ids = getIds();
        const saved = !ids.includes(normalizedId);
        const nextIds = saved
            ? [...ids, normalizedId]
            : ids.filter(savedId => savedId !== normalizedId);

        setIds(nextIds);
        window.dispatchEvent(new CustomEvent('b2s:list-change', {
            detail: { id: normalizedId, saved, ids: [...memoryIds] }
        }));
        return saved;
    };

    const api = {
        storageKey,
        getIds,
        isSaved,
        save: id => setIds([...getIds(), normalizeId(id)]),
        remove: id => {
            const normalizedId = normalizeId(id);
            return normalizedId === null
                ? getIds()
                : setIds(getIds().filter(savedId => savedId !== normalizedId));
        },
        toggle,
        refresh: refreshControls,
        setTheaterId: id => {
            const control = document.querySelector('[data-theater-save]');

            if (control) {
                control.dataset.saveTmdbId = normalizeId(id) || '';
                updateControl(control, getIds());
            }
        }
    };

    window.B2SMyList = api;

    document.addEventListener('click', event => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const control = event.target.closest('[data-save-tmdb-id]');

        if (!control || control.hidden) {
            return;
        }

        event.preventDefault();
        toggle(control.dataset.saveTmdbId);
    });

    window.addEventListener('storage', event => {
        if (event.key === storageKey || event.key === null) {
            refreshControls();
        }
    });

    const initialize = () => {
        refreshControls();

        const cards = document.getElementById('my-list-cards');

        if (cards) {
            loadMyListCards(cards);
        }
    };

    const loadMyListCards = async cards => {
        const emptyState = document.getElementById('my-list-empty');
        const ids = getIds();

        if (ids.length === 0) {
            emptyState.hidden = false;
            return;
        }

        try {
            const response = await fetch(
                `/my-list.php?fragment=1&ids=${encodeURIComponent(ids.join(','))}`,
                { headers: { Accept: 'text/html' } }
            );

            if (!response.ok) {
                throw new Error('Could not load saved adaptations.');
            }

            cards.innerHTML = await response.text();
            refreshControls();
            emptyState.hidden = cards.children.length > 0;
        } catch (error) {
            console.error(error);
            emptyState.hidden = false;
        }

        window.addEventListener('b2s:list-change', event => {
            if (event.detail.saved) {
                return;
            }

            cards.querySelectorAll('[data-my-list-card]').forEach(card => {
                if (normalizeId(card.dataset.myListCard) === event.detail.id) {
                    card.remove();
                }
            });

            emptyState.hidden = cards.children.length > 0;
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();