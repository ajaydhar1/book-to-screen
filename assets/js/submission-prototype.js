(() => {
    'use strict';

    const flow = document.getElementById('prototype-flow');
    if (!flow) return;

    const byId = (id) => document.getElementById(id);
    const panels = [...flow.querySelectorAll('[data-step]')];
    const bookFields = [...flow.querySelectorAll('[data-book-field]')];
    const lookupButton = byId('lookup-book');
    const lookupStatus = byId('lookup-status');
    const origins = new Map();
    let currentStep = 1;
    let pendingMetadata = null;
    let appliedMetadata = null;
    let activeRequest = null;
    let lastLookupAt = 0;

    function setLookupStatus(message, state = '') {
        lookupStatus.textContent = message;
        lookupStatus.dataset.state = state;
    }

    function updateOrigin(field) {
        const origin = origins.get(field.id);
        let text = field.value.trim() ? 'Author-entered' : 'Not entered';
        if (origin && field.value === origin.value) text = field.id === 'genre' ? `Suggested by ${origin.provider}` : `From ${origin.provider}`;
        else if (origin) text = field.value.trim()
            ? `Edited by you (originally ${origin.provider})`
            : `Removed by you (originally ${origin.provider})`;
        byId(`origin-${field.id}`).textContent = text;
    }

    function cancelLookup() {
        if (activeRequest) activeRequest.abort();
        activeRequest = null;
        lookupButton.disabled = false;
        lookupButton.textContent = 'Look Up Book';
        byId('lookup-result').hidden = true;
        pendingMetadata = null;
        byId('isbn').removeAttribute('aria-invalid');
    }

    function applyMetadata(metadata) {
        appliedMetadata = metadata;
        for (const field of bookFields) {
            const key = field.dataset.bookField;
            const value = Array.isArray(metadata[key])
                ? metadata[key].join(key === 'authors' ? '; ' : ', ')
                : (metadata[key] || '');
            field.value = value;
            origins.delete(field.id);
            if (value) origins.set(field.id, { value, provider: metadata.provider });
            field.removeAttribute('aria-invalid');
            updateOrigin(field);
        }
        byId('isbn').value = metadata.isbn;
        byId('lookup-result').hidden = true;
        pendingMetadata = null;
        updateGenreChoices();
        setLookupStatus(`Details populated from ${metadata.provider}. Review and edit them below; missing fields can be entered manually.`, 'success');
    }

    function usableMetadata(metadata) {
        const textFields = ['title', 'subtitle', 'publisher', 'publication_date', 'description'];
        return metadata && /^97[89]\d{10}$/.test(metadata.isbn)
            && ['Google Books', 'Open Library'].includes(metadata.provider)
            && typeof metadata.title === 'string' && metadata.title.trim() !== ''
            && Array.isArray(metadata.authors) && metadata.authors.every((value) => typeof value === 'string')
            && ['genres', 'subjects', 'provider_terms'].every((key) =>
                Array.isArray(metadata[key]) && metadata[key].every((value) => typeof value === 'string'))
            && textFields.every((key) => metadata[key] === null || typeof metadata[key] === 'string');
    }

    async function lookupBook() {
        const isbn = byId('isbn').value.trim();
        if (!isbn) {
            setLookupStatus('Enter an ISBN to look up a book, or choose manual entry.', 'invalid_isbn');
            byId('isbn').setAttribute('aria-invalid', 'true');
            byId('isbn').focus();
            return;
        }
        if (Date.now() - lastLookupAt < 1100) {
            setLookupStatus('Please wait a moment before another lookup. Manual entry is always available.');
            return;
        }
        cancelLookup();
        lastLookupAt = Date.now();
        const controller = new AbortController();
        activeRequest = controller;
        lookupButton.disabled = true;
        lookupButton.textContent = 'Looking up...';
        setLookupStatus('Looking up your book with Google Books, then Open Library if needed...', 'loading');
        const timer = setTimeout(() => controller.abort(), 16000);
        try {
            const response = await fetch(`lookup.php?${new URLSearchParams({ isbn })}`, {
                signal: controller.signal,
                cache: 'no-store',
                credentials: 'omit',
                referrerPolicy: 'no-referrer',
                headers: { Accept: 'application/json' },
            });
            if (activeRequest !== controller) return;
            const result = await response.json();
            if (!['success', 'not_found', 'invalid_isbn', 'api_error', 'invalid_request'].includes(result.status)
                || typeof result.message !== 'string') {
                throw new Error('Unexpected lookup response');
            }
            setLookupStatus(result.message, result.status);
            if (result.status === 'invalid_isbn') byId('isbn').setAttribute('aria-invalid', 'true');
            if (result.status === 'success') {
                if (!response.ok || !usableMetadata(result.metadata)) throw new Error('Unusable book metadata');
                if (bookFields.some((field) => field.value.trim() !== '')) {
                    pendingMetadata = result.metadata;
                    byId('lookup-preview').textContent = `${result.metadata.title} — ${result.metadata.authors.join(', ') || 'Author unavailable'}. You already have book details. Replace them, or keep your current entries.`;
                    byId('lookup-result').hidden = false;
                } else {
                    applyMetadata(result.metadata);
                }
            }
        } catch (error) {
            if (activeRequest !== controller) return;
            setLookupStatus(
                error.name === 'AbortError'
                    ? 'The lookup took too long. Try again later or enter your book manually.'
                    : 'We could not retrieve book details. Try again later or enter your book manually.',
                'api_error'
            );
        } finally {
            clearTimeout(timer);
            if (activeRequest === controller) {
                activeRequest = null;
                lookupButton.disabled = false;
                lookupButton.textContent = 'Look Up Book';
            }
        }
    }

    function updateConditionalFields() {
        const authorization = byId('authorization').value;
        const role = byId('role').value;
        byId('authority-details-field').hidden = authorization !== 'representative';
        byId('rights-name-field').hidden = ['unknown', 'self'].includes(byId('rights-controller').value);
        byId('arrangement-details-field').hidden = ['unknown', 'none'].includes(byId('arrangement').value);
        byId('authority-note').textContent = authorization === 'recommendation'
            ? 'Recommendation only: this does not imply owner participation, permission to negotiate or available rights.'
            : role === 'scout'
                ? 'Scouts may recommend a book without a mandate, but must not claim owner participation or rights availability. Any authority is self-declared.'
                : 'A name, email address or author role does not prove screen-rights ownership. All authority here is self-declared.';
    }

    function validateStep(step) {
        const panel = panels.find((item) => Number(item.dataset.step) === step);
        const fields = [...panel.querySelectorAll('input, select, textarea')].filter((field) => {
            const wrapper = field.closest('.field');
            return !wrapper || !wrapper.hidden;
        });
        const invalid = fields.find((field) => {
            field.removeAttribute('aria-invalid');
            return !field.checkValidity() || (field.required && field.value.trim() === '')
                || (field.id === 'website' && field.value !== '' && !/^https?:\/\/[^\s]+$/i.test(field.value));
        });
        if (!invalid) return true;
        const label = panel.querySelector(`label[for="${invalid.id}"]`).textContent.replace(' *', '');
        byId('validation-message').hidden = false;
        byId('validation-message').textContent = invalid.id === 'email'
            ? 'Please enter a valid sample email address, such as reader@example.com.'
            : invalid.id === 'website'
                ? 'Please enter an http:// or https:// public website, or leave it blank.'
                : `Please check ${label.toLowerCase()} before continuing.`;
        invalid.setAttribute('aria-invalid', 'true');
        invalid.focus();
        return false;
    }

    function addReview(list, label, value, origin = '') {
        const row = document.createElement('div');
        const term = document.createElement('dt');
        const description = document.createElement('dd');
        term.textContent = label;
        description.textContent = value || 'Not provided';
        if (origin) {
            const source = document.createElement('small');
            source.className = 'field-origin';
            source.textContent = origin;
            description.append(source);
        }
        row.append(term, description);
        list.append(row);
    }

    function selectedLabel(id) {
        const select = byId(id);
        return select.selectedOptions[0].textContent;
    }

    function renderReview() {
        const bookReview = byId('book-review');
        const adaptationReview = byId('adaptation-review');
        bookReview.replaceChildren();
        adaptationReview.replaceChildren();
        addReview(bookReview, 'ISBN entered (optional)', byId('isbn').value.trim());
        if (appliedMetadata) addReview(bookReview, 'Metadata lookup edition', `${appliedMetadata.isbn} · ${appliedMetadata.provider}`);
        for (const field of bookFields) {
            addReview(bookReview, flow.querySelector(`label[for="${field.id}"]`).textContent.replace(' *', ''),
                field.value.trim(), byId(`origin-${field.id}`).textContent);
        }
        for (const [id, label] of [
            ['pitch', 'Adaptation pitch'], ['submitter-name', 'Submitter name'], ['email', 'Email (not verified)'],
            ['website', 'Public website (not visited)'],
        ]) addReview(adaptationReview, label, byId(id).value.trim());
        for (const [id, label] of [
            ['screen-format', 'Preferred format'], ['role', 'Submitter role'],
            ['authorization', 'Self-declared authorization'], ['rights-controller', 'Rights controller (self-declared)'],
            ['arrangement', 'Known arrangements (self-declared)'],
        ]) addReview(adaptationReview, label, selectedLabel(id));
        for (const [id, wrapper, label] of [
            ['authority-details', 'authority-details-field', 'Authorization details'],
            ['rights-name', 'rights-name-field', 'Rights controller name'],
            ['arrangement-details', 'arrangement-details-field', 'Arrangement details'],
        ]) {
            if (!byId(wrapper).hidden) addReview(adaptationReview, label, byId(id).value.trim());
        }
    }

    function activateStep(step, focusHeading = true) {
        if (step === 3) renderReview();
        currentStep = step;
        for (const panel of panels) panel.hidden = Number(panel.dataset.step) !== step;
        for (const button of flow.querySelectorAll('[data-step-nav]')) {
            if (Number(button.dataset.stepNav) === step) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
        }
        if (focusHeading) byId(`step-${step}-title`).focus();
        flow.dispatchEvent(new CustomEvent('submission-step', { detail: step }));
    }

    function showStep(step) {
        if (step > currentStep) {
            for (let previous = currentStep; previous < step; previous++) {
                activateStep(previous, false);
                if (!validateStep(previous)) return;
            }
        }
        byId('validation-message').hidden = true;
        activateStep(step);
    }

    lookupButton.addEventListener('click', lookupBook);
    byId('isbn').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (!lookupButton.disabled) lookupBook();
        }
    });
    byId('isbn').addEventListener('input', () => {
        if (activeRequest || pendingMetadata) {
            cancelLookup();
            setLookupStatus('ISBN changed. Look up this edition when ready, or continue manually.');
        }
        byId('isbn').removeAttribute('aria-invalid');
    });
    byId('manual-entry').addEventListener('click', () => {
        cancelLookup();
        byId('isbn').value = '';
        setLookupStatus('Manual entry selected. Your existing book details are kept; enter or edit them below.');
        byId('title').focus();
    });
    byId('apply-lookup').addEventListener('click', () => {
        if (pendingMetadata) applyMetadata(pendingMetadata);
    });
    byId('discard-lookup').addEventListener('click', () => {
        pendingMetadata = null;
        byId('lookup-result').hidden = true;
        setLookupStatus('Your current book details have been kept. You can continue or edit them manually.');
    });
    function updateGenreChoices() {
        const selected = byId('genre').value.split(',').map((value) => value.trim().toLowerCase());
        for (const button of flow.querySelectorAll('[data-genre]')) {
            button.setAttribute('aria-pressed', String(selected.includes(button.dataset.genre.toLowerCase())));
        }
    }
    for (const button of flow.querySelectorAll('[data-genre]')) {
        button.addEventListener('click', () => {
            const field = byId('genre');
            const values = field.value.split(',').map((value) => value.trim()).filter(Boolean);
            const label = button.dataset.genre;
            field.value = values.some((value) => value.toLowerCase() === label.toLowerCase())
                ? values.filter((value) => value.toLowerCase() !== label.toLowerCase()).join(', ')
                : [...values, label].join(', ');
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }
    for (const field of bookFields) field.addEventListener('input', () => {
        updateOrigin(field);
        if (field.id === 'genre') updateGenreChoices();
    });
    for (const id of ['role', 'authorization', 'rights-controller', 'arrangement']) {
        byId(id).addEventListener('change', updateConditionalFields);
    }
    for (const button of flow.querySelectorAll('[data-next], [data-back], [data-step-nav]')) {
        button.addEventListener('click', () => showStep(Number(button.dataset.next || button.dataset.back || button.dataset.stepNav)));
    }
    for (const field of flow.querySelectorAll('input, select, textarea')) {
        field.addEventListener('input', () => {
            field.removeAttribute('aria-invalid');
            byId('validation-message').hidden = true;
            byId('demo-complete').hidden = true;
        });
    }
    byId('complete-demo').addEventListener('click', () => {
        if (flow.dataset.mode === 'local-intake') {
            if (!validateStep(1) || !validateStep(2)) return;
            const fields = {};
            for (const field of flow.querySelectorAll('input, select, textarea')) {
                fields[field.id] = field.type === 'checkbox' ? field.checked : field.value;
            }
            fields.provenance = {};
            for (const [field, origin] of origins) {
                fields.provenance[field] = {
                    provider: origin.provider,
                    state: byId(field).value === origin.value ? 'suggested' : (byId(field).value.trim() ? 'edited' : 'removed'),
                };
            }
            flow.dispatchEvent(new CustomEvent('local-submission', { detail: fields }));
            return;
        }
        byId('demo-complete').hidden = false;
        byId('demo-complete').focus();
    });
    window.addEventListener('pagehide', () => {
        cancelLookup();
        appliedMetadata = null;
        origins.clear();
        for (const field of flow.querySelectorAll('input, select, textarea')) {
            if (field.tagName === 'SELECT') field.selectedIndex = 0;
            else if (field.type === 'checkbox') field.checked = false;
            else field.value = '';
            field.removeAttribute('aria-invalid');
        }
        for (const field of bookFields) updateOrigin(field);
        updateGenreChoices();
        byId('book-review').replaceChildren();
        byId('adaptation-review').replaceChildren();
        byId('demo-complete').hidden = true;
        byId('validation-message').hidden = true;
        setLookupStatus('Lookup is optional. Your book details can always be entered below.');
        updateConditionalFields();
        activateStep(1, false);
    });
    updateConditionalFields();
    flow.hidden = false;
})();
