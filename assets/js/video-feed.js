(() => {
    const feed = document.querySelector('[data-video-feed]');

    if (!feed) {
        return;
    }

    const source = feed.dataset.videoFeedSource || 'b2s-trailers';
    const bootstrap = window.VideoFeedBootstrap || {};
    const seenIds = new Set(
        Array.isArray(bootstrap.seenIds)
            ? bootstrap.seenIds.map(value => String(value))
            : []
    );
    const state = {
        source,
        loading: false,
        hasMore: bootstrap.hasMore === true,
        activeId: null,
        activeScore: -Infinity,
        manualPlaybackUntil: 0,
        youtubeReady: null,
        playerMap: new Map(),
        feedSoundEnabled: false,
    };

    const classifyVideoItem = item => {
        const rect = item.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const visibleTop = Math.max(rect.top, 0);
        const visibleBottom = Math.min(rect.bottom, viewportHeight);
        const visiblePx = Math.max(0, visibleBottom - visibleTop);
        const visibleRatio = rect.height > 0 ? visiblePx / rect.height : 0;
        const centerDelta = Math.abs((rect.top + rect.height / 2) - (viewportHeight / 2));

        return visibleRatio * 100 - (centerDelta / 12);
    };

    const getItemById = id => {
        const matchId = String(id);

        return Array.from(document.querySelectorAll('[data-video-item]')).find(item => {
            return String(item.dataset.tmdbId) === matchId;
        }) || null;
    };

    const updateDescriptionToggle = button => {
        const item = button.closest('.video-feed-item');
        const description = item && item.querySelector('[data-description]');

        if (!description) {
            return;
        }

        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        description.classList.toggle('video-feed-item__description--collapsed', isExpanded);
        button.textContent = isExpanded ? 'More' : 'Less';
        button.setAttribute('aria-expanded', String(!isExpanded));
    };

    const updateDescriptionToggleVisibility = description => {
        const button = description.closest('.video-feed-item__description-wrap')
            ?.querySelector('.video-feed-item__more');

        if (!button) {
            return;
        }

        const wasCollapsed = description.classList.contains('video-feed-item__description--collapsed');

        if (!wasCollapsed) {
            description.classList.add('video-feed-item__description--collapsed');
        }

        button.hidden = description.scrollHeight <= description.clientHeight;

        if (!wasCollapsed) {
            description.classList.remove('video-feed-item__description--collapsed');
        }
    };

    const observedDescriptions = new WeakSet();
    const descriptionResizeObserver = typeof ResizeObserver === 'function'
        ? new ResizeObserver(entries => {
            entries.forEach(entry => updateDescriptionToggleVisibility(entry.target));
        })
        : null;

    const bindDescriptionControls = () => {
        document.querySelectorAll('[data-description]').forEach(description => {
            updateDescriptionToggleVisibility(description);

            if (descriptionResizeObserver && !observedDescriptions.has(description)) {
                descriptionResizeObserver.observe(description);
                observedDescriptions.add(description);
            }
        });
    };

    const ensureYoutubeApi = () => {
        if (state.youtubeReady) {
            return state.youtubeReady;
        }

        state.youtubeReady = new Promise(resolve => {
            if (window.YT && typeof window.YT.Player === 'function') {
                resolve();
                return;
            }

            const existing = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = () => {
                if (typeof existing === 'function') {
                    existing();
                }
                resolve();
            };

            const script = document.createElement('script');
            script.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(script);
        });

        return state.youtubeReady;
    };

    const syncSoundToggle = () => {
        const toggle = document.querySelector('[data-video-feed-sound-toggle]');

        if (!toggle) {
            return;
        }

        const icon = state.feedSoundEnabled ? '🔊' : '🔇';
        const label = state.feedSoundEnabled ? 'Sound On' : 'Sound Off';
        toggle.innerHTML = `<span class="video-feed-sound-toggle__icon" aria-hidden="true">${icon}</span> <span class="video-feed-sound-toggle__label">${label}</span>`;
        toggle.setAttribute('aria-pressed', state.feedSoundEnabled ? 'true' : 'false');
        toggle.classList.toggle('is-on', state.feedSoundEnabled);
    };

    const applyFeedSoundPreferenceToPlayer = player => {
        if (!player || typeof player.mute !== 'function') {
            return;
        }

        try {
            if (state.feedSoundEnabled) {
                player.unMute();
                return;
            }

            player.mute();
        } catch (error) {
            // Browsers may reject muting before user interaction; do not attempt a brittle DOM workaround.
        }
    };

    const applyFeedSoundPreference = () => {
        state.playerMap.forEach(player => {
            applyFeedSoundPreferenceToPlayer(player);
        });

        syncSoundToggle();
    };

    const setFeedSoundEnabled = enabled => {
        state.feedSoundEnabled = Boolean(enabled);
        applyFeedSoundPreference();
    };

    const ensurePlayer = async item => {
        const tmdbId = item.dataset.tmdbId;
        const host = item.querySelector('[data-video-player-host]');
        const key = item.dataset.videoKey;

        if (!host || !key) {
            return null;
        }

        if (state.playerMap.has(tmdbId)) {
            return state.playerMap.get(tmdbId);
        }

        await ensureYoutubeApi();

        const player = new YT.Player(host, {
            videoId: key,
            playerVars: {
                autoplay: 1,
                mute: 1,
                playsinline: 1,
                controls: 1,
                rel: 0,
                modestbranding: 1,
            },
            events: {
                onReady: event => {
                    const playerInstance = event.target;
                    applyFeedSoundPreferenceToPlayer(playerInstance);

                    if (String(state.activeId) === String(tmdbId)) {
                        try {
                            playerInstance.playVideo();
                        } catch (error) {
                            // Browser autoplay restrictions may block the automatic start.
                        }
                    }
                },
                onStateChange: event => {
                    const currentPlayer = event.target;

                    if (event.data === YT.PlayerState.PLAYING && String(state.activeId) !== String(tmdbId)) {
                        currentPlayer.pauseVideo();
                    }
                },
            },
        });

        state.playerMap.set(tmdbId, player);
        return player;
    };

    const pausePlayer = id => {
        const player = state.playerMap.get(String(id));

        if (!player || typeof player.pauseVideo !== 'function') {
            return;
        }

        try {
            player.pauseVideo();
        } catch (error) {
            // Ignore YouTube API timing issues; the feed will continue to function.
        }
    };

    const activateItem = async item => {
        if (!item) {
            return;
        }

        const tmdbId = item.dataset.tmdbId;

        if (state.activeId === tmdbId) {
            const player = state.playerMap.get(String(tmdbId));

            if (player && typeof player.playVideo === 'function') {
                applyFeedSoundPreferenceToPlayer(player);

                try {
                    player.playVideo();
                } catch (error) {
                    // Ignore play errors so the page remains usable if autoplay is blocked.
                }
            }

            return;
        }

        if (state.activeId !== null) {
            pausePlayer(state.activeId);
        }

        state.activeId = tmdbId;

        const player = await ensurePlayer(item);

        if (!player || typeof player.playVideo !== 'function') {
            return;
        }

        applyFeedSoundPreferenceToPlayer(player);

        try {
            player.playVideo();
        } catch (error) {
            // Browser autoplay restrictions may block the automatic start.
        }
    };

    const selectBestVisibleItem = () => {
        const items = Array.from(document.querySelectorAll('[data-video-item]'));

        if (items.length === 0) {
            return null;
        }

        let bestItem = null;
        let bestScore = -Infinity;

        items.forEach(item => {
            const score = classifyVideoItem(item);

            if (score > bestScore) {
                bestScore = score;
                bestItem = item;
            }
        });

        if (!bestItem || bestScore < 25) {
            return null;
        }

        if (state.activeId !== null) {
            const currentItem = getItemById(state.activeId);
            const currentScore = currentItem ? classifyVideoItem(currentItem) : -Infinity;

            if (bestScore < currentScore + 12 && bestItem.dataset.tmdbId !== state.activeId) {
                return null;
            }
        }

        if (Date.now() < state.manualPlaybackUntil && bestItem.dataset.tmdbId !== state.activeId) {
            return null;
        }

        return bestItem;
    };

    const updateActiveVideo = () => {
        const bestItem = selectBestVisibleItem();

        if (!bestItem) {
            return;
        }

        activateItem(bestItem);
    };

    const makeDetailItem = item => {
        const article = document.createElement('article');
        article.className = 'video-feed-item';
        article.dataset.videoItem = 'true';
        article.dataset.tmdbId = String(item.tmdb_id);
        article.dataset.videoKey = item.trailer_youtube_key || '';
        article.dataset.videoTitle = item.title || 'Untitled';

        const author = item.source_author || '';
        const bookTitle = item.book_title || '';
        const authorUrl = item.author_url || '/trailers.php';
        const bookUrl = item.book_url || '';
        const soundtrackUrl = item.soundtrack_url || '';
        const posterHtml = item.poster_url
            ? `<img class="video-feed-item__poster" src="${item.poster_url}" alt="${(item.title || 'Trailer').replace(/"/g, '&quot;')} poster" loading="lazy" decoding="async">`
            : '<div class="video-feed-item__poster-fallback">Trailer</div>';

        const metaHtml = bookTitle
            ? `<p class="video-feed-item__meta">Based on <span class="video-feed-item__book-title">${(bookTitle || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>${author ? ` by <a href="${authorUrl}" class="video-feed-item__author-link">${author.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</a>` : ''}</p>`
            : author
                ? `<p class="video-feed-item__meta">By <a href="${authorUrl}" class="video-feed-item__author-link">${author.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</a></p>`
                : '';

        const yearHtml = item.release_year
            ? `<p class="video-feed-item__year">${item.release_year}</p>`
            : '';

        const description = (item.overview || '').trim();
        const descriptionHtml = description
            ? `<div class="video-feed-item__description-wrap"><div id="desc-${item.tmdb_id}" class="video-feed-item__description video-feed-item__description--collapsed" data-description>${description.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div><button type="button" class="video-feed-item__more" aria-expanded="false" aria-controls="desc-${item.tmdb_id}" hidden>More</button></div>`
            : '';

        const actions = `
            <div class="video-feed-item__actions">
                ${bookUrl ? `<a class="video-feed-item__action" href="${bookUrl}" target="_blank" rel="noopener noreferrer">Read</a>` : ''}
                ${soundtrackUrl ? `<a class="video-feed-item__action" href="${soundtrackUrl}" target="_blank" rel="noopener noreferrer">Listen</a>` : ''}
                <button type="button" class="b2s-save-button video-feed-item__save" data-save-tmdb-id="${item.tmdb_id}" aria-pressed="false">Save</button>
            </div>
        `;

        article.innerHTML = `
            <div class="video-feed-item__video-shell">
                <div class="video-feed-item__player" data-video-player-host>
                    ${posterHtml}
                </div>
            </div>
            <div class="video-feed-item__content">
                <h2 class="video-feed-item__title">${(item.title || 'Untitled').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</h2>
                ${metaHtml}
                ${yearHtml}
                ${descriptionHtml}
                ${actions}
            </div>
        `;

        return article;
    };

    const renderMoreItems = items => {
        if (!Array.isArray(items) || items.length === 0) {
            return;
        }

        items.forEach(item => {
            const article = makeDetailItem(item);
            feed.appendChild(article);
            seenIds.add(String(item.tmdb_id));
        });

        bindDescriptionControls();
        updateActiveVideo();
    };

    const loadMoreItems = async () => {
        if (state.loading || !state.hasMore) {
            return;
        }

        state.loading = true;

        try {
            const url = new URL('/video-feed-data.php', window.location.origin);
            url.searchParams.set('source', state.source);
            url.searchParams.set('limit', '6');
            url.searchParams.set('seen', Array.from(seenIds).join(','));

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Could not fetch the next feed batch.');
            }

            const payload = await response.json();
            const nextItems = Array.isArray(payload.items) ? payload.items : [];
            state.hasMore = !!payload.has_more;
            renderMoreItems(nextItems);
        } catch (error) {
            console.error(error);
        } finally {
            state.loading = false;
        }
    };

    const initializeLoadMoreObserver = () => {
        const sentinel = document.getElementById('video-feed-sentinel');

        if (!sentinel) {
            return;
        }

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    loadMoreItems();
                }
            });
        }, {
            root: null,
            rootMargin: '200px 0px',
            threshold: 0.1,
        });

        observer.observe(sentinel);
    };

    bindDescriptionControls();
    syncSoundToggle();

    feed.addEventListener('click', event => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const descriptionButton = target.closest('.video-feed-item__more');

        if (descriptionButton && feed.contains(descriptionButton)) {
            updateDescriptionToggle(descriptionButton);
        }
    });

    document.addEventListener('click', event => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const soundToggle = target.closest('[data-video-feed-sound-toggle]');

        if (soundToggle) {
            setFeedSoundEnabled(!state.feedSoundEnabled);
            return;
        }

        const playerHost = target.closest('[data-video-player-host]');

        if (playerHost) {
            state.manualPlaybackUntil = Date.now() + 1500;
        }
    });

    window.addEventListener('scroll', () => {
        window.requestAnimationFrame(updateActiveVideo);
    }, { passive: true });

    window.addEventListener('resize', () => {
        window.requestAnimationFrame(updateActiveVideo);
    });

    initializeLoadMoreObserver();
    window.requestAnimationFrame(updateActiveVideo);
})();
