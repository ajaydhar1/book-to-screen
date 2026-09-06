document.addEventListener('DOMContentLoaded', () => {

    const theater = document.getElementById('trailer-theater');
    const iframe = document.getElementById('trailer-theater-iframe');
    const title = document.getElementById('trailer-theater-title');

    if (!theater || !iframe || !title) {
        return;
    }

    const openTheater = (key, movieTitle = 'Trailer') => {

        if (!key) {
            return;
        }

        title.textContent = movieTitle;

        iframe.src =
            'https://www.youtube.com/embed/' +
            encodeURIComponent(key) +
            '?autoplay=1&rel=0';

        theater.classList.add('is-open');
        theater.setAttribute('aria-hidden', 'false');

        document.body.classList.add(
            'trailer-theater-open'
        );
    };

    const closeTheater = () => {

        iframe.src = '';

        theater.classList.remove('is-open');
        theater.setAttribute('aria-hidden', 'true');

        document.body.classList.remove(
            'trailer-theater-open'
        );
    };

    document
        .querySelectorAll('.trailer-theater-trigger')
        .forEach(button => {

            button.addEventListener('click', () => {

                openTheater(
                    button.dataset.trailerKey,
                    button.dataset.trailerTitle || 'Trailer'
                );

            });

        });

    document
        .querySelectorAll('[data-theater-close]')
        .forEach(button => {

            button.addEventListener('click', closeTheater);

        });

    document.addEventListener('keydown', event => {

        if (
            event.key === 'Escape' &&
            theater.classList.contains('is-open')
        ) {
            closeTheater();
        }

    });

    document
        .querySelectorAll('[data-random-trailer]')
        .forEach(button => {

            button.addEventListener('click', async () => {

                const originalText = button.textContent;

                try {

                    button.disabled = true;
                    button.textContent = 'Finding a trailer…';

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

                    openTheater(
                        youtubeKey,
                        movie.title || 'Trailer'
                    );

                } catch (error) {

                    console.error(error);

                    alert(
                        'Could not find a trailer right now.'
                    );

                } finally {

                    button.disabled = false;
                    button.textContent = originalText;

                }

            });

        });

});