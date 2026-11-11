(() => {
    const theater = document.getElementById('trailer-theater');
    const screen = document.querySelector('.trailer-theater-screen');
    const title = document.getElementById('trailer-theater-title');
    const status = document.createElement('p');
    status.className = 'cinema-theater-status';
    status.setAttribute('role', 'alert');
    status.hidden = true;
    screen.before(status);

    let player = null;
    let playerReady = false;
    let continuous = false;
    let loadingNext = false;
    let session = 0;
    let youtubePromise = null;

    function youtubeReady() {
        if (window.YT && typeof window.YT.Player === 'function') {
            return Promise.resolve();
        }
        if (youtubePromise) return youtubePromise;
        youtubePromise = new Promise((resolve, reject) => {
            const timeout = setTimeout(() => reject(new Error('YouTube player timed out.')), 15000);
            window.onYouTubeIframeAPIReady = () => {
                clearTimeout(timeout);
                resolve();
            };
            const script = document.createElement('script');
            script.src = 'https://www.youtube.com/iframe_api';
            script.onerror = () => {
                clearTimeout(timeout);
                reject(new Error('Could not load the YouTube player.'));
            };
            document.head.appendChild(script);
        }).catch(error => {
            youtubePromise = null;
            throw error;
        });
        return youtubePromise;
    }

    async function randomTrailer() {
        const response = await fetch('/random-trailer.php', { cache: 'no-store' });
        if (!response.ok) throw new Error('Random trailer request failed.');
        const movie = await response.json();
        const key = String(movie.youtube_key || '').trim();
        if (!key) throw new Error('No trailer was returned.');
        return { key, title: movie.title || 'Trailer', id: movie.tmdb_id };
    }

    function showError(error) {
        console.error('Trailer theater:', error);
        status.textContent = 'The trailer could not be played. Please try again later.';
        status.hidden = false;
    }

    function close() {
        session++;
        continuous = false;
        if (player && typeof player.destroy === 'function') player.destroy();
        player = null;
        playerReady = false;
        screen.innerHTML = '<div id="trailer-theater-iframe"></div>';
        theater.classList.remove('is-open');
        theater.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('trailer-theater-open');
    }

    async function open(key, movieTitle, id, playContinuously) {
        const currentSession = ++session;
        continuous = playContinuously;
        title.textContent = movieTitle;
        status.hidden = true;
        if (window.B2SMyList) window.B2SMyList.setTheaterId(id);
        theater.classList.add('is-open');
        theater.setAttribute('aria-hidden', 'false');
        document.body.classList.add('trailer-theater-open');

        try {
            await youtubeReady();
            if (session !== currentSession) return;
            if (player && playerReady) {
                player.loadVideoById(key);
                return;
            }
            player = new window.YT.Player('trailer-theater-iframe', {
                videoId: key,
                playerVars: { autoplay: 1, rel: 0 },
                events: {
                    onReady: event => {
                        if (session !== currentSession) {
                            event.target.destroy();
                            return;
                        }
                        playerReady = true;
                        event.target.playVideo();
                    },
                    onError: () => showError(new Error('YouTube reported a playback error.')),
                    onStateChange: event => {
                        if (event.data === window.YT.PlayerState.ENDED && continuous) {
                            nextTrailer();
                        }
                    }
                }
            });
        } catch (error) {
            if (session === currentSession) showError(error);
        }
    }

    async function nextTrailer() {
        if (loadingNext) return;
        loadingNext = true;
        const currentSession = session;
        try {
            const movie = await randomTrailer();
            if (currentSession !== session || !continuous || !playerReady) return;
            title.textContent = movie.title;
            if (window.B2SMyList) window.B2SMyList.setTheaterId(movie.id);
            player.loadVideoById(movie.key);
        } catch (error) {
            if (currentSession === session) showError(error);
        } finally {
            loadingNext = false;
        }
    }

    document.addEventListener('click', async event => {
        const trigger = event.target.closest('.trailer-theater-trigger');
        if (trigger) {
            open(trigger.dataset.trailerKey, trigger.dataset.trailerTitle || 'Trailer', trigger.dataset.tmdbId, false);
            return;
        }
        if (event.target.closest('[data-theater-close]')) {
            close();
            return;
        }
        const random = event.target.closest('[data-random-trailer]');
        if (!random) return;
        const text = random.textContent;
        random.disabled = true;
        random.textContent = 'Starting trailers…';
        try {
            const movie = await randomTrailer();
            open(movie.key, movie.title, movie.id, true);
        } catch (error) {
            console.error('Could not load a random trailer:', error);
            alert('Could not find a trailer right now.');
        } finally {
            random.disabled = false;
            random.textContent = text;
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && theater.classList.contains('is-open')) close();
    });
})();
