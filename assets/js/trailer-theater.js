document.addEventListener('DOMContentLoaded', () => {

    const theater = document.getElementById('trailer-theater');
    const playerElement = document.getElementById('trailer-theater-iframe');
    const title = document.getElementById('trailer-theater-title');

    if (!theater || !playerElement || !title) {
        return;
    }

    let player = null;
    let continuousMode = false;
    let loadingNextVideo = false;


    /*
     * Wait for the YouTube IFrame API.
     */
    const youtubeReady = new Promise(resolve => {

        if (
            window.YT &&
            typeof window.YT.Player === 'function'
        ) {
            resolve();
            return;
        }

        window.onYouTubeIframeAPIReady = () => {
            resolve();
        };

        const script = document.createElement('script');

        script.src =
            'https://www.youtube.com/iframe_api';

        document.head.appendChild(script);

    });


    /*
     * Fetch a random trailer.
     */
    const getRandomTrailer = async () => {

        const response = await fetch(
            '/random-trailer.php',
            {
                cache: 'no-store'
            }
        );

        if (!response.ok) {
            throw new Error(
                'Random trailer request failed.'
            );
        }

        const movie = await response.json();

        const youtubeKey =
            String(movie.youtube_key || '').trim();

        if (!youtubeKey) {
            throw new Error(
                'No trailer was returned.'
            );
        }

        return {
            key: youtubeKey,
            title: movie.title || 'Trailer'
        };

    };


    /*
     * Play a video.
     */
    const openTheater = async (
        key,
        movieTitle = 'Trailer',
        continuous = false
    ) => {

        if (!key) {
            return;
        }

        continuousMode = continuous;

        title.textContent = movieTitle;

        theater.classList.add('is-open');
        theater.setAttribute('aria-hidden', 'false');

        document.body.classList.add(
            'trailer-theater-open'
        );

        await youtubeReady;

        /*
         * Existing player:
         * just load another video.
         */
        if (player) {

            player.loadVideoById(key);

            return;
        }

        /*
         * First video:
         * create the YouTube player.
         */
        player = new YT.Player(
            'trailer-theater-iframe',
            {
                videoId: key,

                playerVars: {
                    autoplay: 1,
                    rel: 0
                },

                events: {

                    onReady: event => {

                        event.target.playVideo();

                    },

                    onStateChange: event => {

                        if (
                            event.data ===
                                YT.PlayerState.ENDED &&
                            continuousMode
                        ) {
                            loadNextRandomTrailer();
                        }

                    }

                }

            }
        );

    };


    /*
     * Automatically play another trailer.
     */
    const loadNextRandomTrailer = async () => {

        if (loadingNextVideo) {
            return;
        }

        loadingNextVideo = true;

        try {

            const movie =
                await getRandomTrailer();

            title.textContent =
                movie.title;

            continuousMode = true;

            player.loadVideoById(
                movie.key
            );

        } catch (error) {

            console.error(
                'Could not load next trailer:',
                error
            );

        } finally {

            loadingNextVideo = false;

        }

    };


    /*
     * Close theater.
     */
    const closeTheater = () => {

        continuousMode = false;

        if (player) {
            player.stopVideo();
        }

        theater.classList.remove('is-open');
        theater.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'trailer-theater-open'
        );

    };


    /*
     * Regular trailer cards:
     * play one trailer only.
     */
    document
        .querySelectorAll('.trailer-theater-trigger')
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    openTheater(
                        button.dataset.trailerKey,
                        button.dataset.trailerTitle ||
                            'Trailer',
                        false
                    );

                }
            );

        });


    /*
     * Close buttons.
     */
    document
        .querySelectorAll('[data-theater-close]')
        .forEach(button => {

            button.addEventListener(
                'click',
                closeTheater
            );

        });


    /*
     * Escape key.
     */
    document.addEventListener(
        'keydown',
        event => {

            if (
                event.key === 'Escape' &&
                theater.classList.contains(
                    'is-open'
                )
            ) {
                closeTheater();
            }

        }
    );


    /*
     * Watch a Trailer:
     * continuous random mode.
     */
    document
        .querySelectorAll('[data-random-trailer]')
        .forEach(button => {

            button.addEventListener(
                'click',
                async () => {

                    const originalText =
                        button.textContent;

                    try {

                        button.disabled = true;

                        button.textContent =
                            'Starting trailers…';

                        const movie =
                            await getRandomTrailer();

                        await openTheater(
                            movie.key,
                            movie.title,
                            true
                        );

                    } catch (error) {

                        console.error(error);

                        alert(
                            'Could not find a trailer right now.'
                        );

                    } finally {

                        button.disabled = false;

                        button.textContent =
                            originalText;

                    }

                }
            );

        });

});