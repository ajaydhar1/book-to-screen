<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Film Score Radio</title>

<link rel="icon" type="image/png" href="/favicon.png">

<style>
    :root {
        color-scheme: dark;
        --bg: #08090c;
        --panel: rgba(255, 255, 255, 0.055);
        --panel-strong: rgba(255, 255, 255, 0.085);
        --border: rgba(255, 255, 255, 0.12);
        --text: #f5f3ee;
        --muted: #9b9da6;
        --accent: #e7dfce;
    }

    * {
        box-sizing: border-box;
    }

    html {
        min-height: 100%;
        background: var(--bg);
    }

    body {
        min-height: 100vh;
        margin: 0;
        color: var(--text);
        background:
            radial-gradient(circle at 50% -15%, rgba(255,255,255,.09), transparent 32rem),
            linear-gradient(180deg, #0d0f14 0%, #08090c 58%, #050608 100%);
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    button,
    input {
        font: inherit;
    }

    button {
        color: inherit;
    }

    .score-page {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .score-shell {
        width: min(1180px, calc(100% - 48px));
        margin: 0 auto;
        padding: 72px 0 48px;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .score-header {
        text-align: center;
        margin-bottom: 36px;
    }

    .score-eyebrow {
        margin: 0 0 12px;
        color: var(--muted);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .22em;
        text-transform: uppercase;
    }

    .score-header h1 {
        margin: 0;
        font-size: clamp(42px, 7vw, 86px);
        line-height: .95;
        letter-spacing: -.055em;
        font-weight: 750;
    }

    .score-description {
        max-width: 620px;
        margin: 18px auto 0;
        color: var(--muted);
        font-size: clamp(15px, 2vw, 18px);
        line-height: 1.6;
    }

    .score-player {
        border: 1px solid var(--border);
        border-radius: 28px;
        padding: 20px;
        background: var(--panel);
        box-shadow: 0 28px 90px rgba(0,0,0,.38);
        backdrop-filter: blur(18px);
    }

    .score-video {
        position: relative;
        aspect-ratio: 16 / 9;
        overflow: hidden;
        border-radius: 18px;
        background: #000;
    }

    .score-video iframe,
    #scoreYT {
        width: 100%;
        height: 100%;
        border: 0;
    }

    .score-meta {
        padding: 22px 4px 14px;
        text-align: center;
    }

    .score-now-label {
        margin: 0 0 8px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .18em;
        text-transform: uppercase;
    }

    .score-title {
        min-height: 1.25em;
        margin: 0;
        font-size: clamp(22px, 4vw, 38px);
        line-height: 1.15;
        letter-spacing: -.025em;
    }

    .score-controls {
        display: grid;
        grid-template-columns: 54px 64px minmax(180px, 1fr) 54px 54px;
        gap: 10px;
        align-items: stretch;
    }

    .score-btn {
        min-height: 54px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--panel-strong);
        cursor: pointer;
        transition: transform .12s ease, background .15s ease, border-color .15s ease;
    }

    .score-btn:hover {
        background: rgba(255,255,255,.13);
        border-color: rgba(255,255,255,.22);
    }

    .score-btn:active {
        transform: scale(.97);
    }

    .score-btn svg {
        width: 20px;
        height: 20px;
        display: block;
        margin: auto;
    }

    .score-play {
        background: var(--text);
        color: #0a0b0e;
        border-color: transparent;
    }

    .score-play:hover {
        background: #fff;
    }

    .score-random {
        padding: 0 24px;
        background: linear-gradient(180deg, rgba(245,243,238,.98), rgba(218,213,203,.92));
        color: #101114;
        border-color: transparent;
        font-weight: 800;
        letter-spacing: .01em;
    }

    .score-random:hover {
        background: #fff;
    }

    .score-progress {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 14px;
        align-items: center;
        padding: 18px 6px 2px;
    }

    .score-range {
        width: 100%;
        accent-color: var(--accent);
        cursor: pointer;
    }

    .score-time {
        min-width: 86px;
        color: var(--muted);
        font-size: 12px;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .score-footer {
        margin-top: 22px;
        color: #747781;
        font-size: 12px;
        text-align: center;
    }

    .score-status {
        display: inline-block;
    }

    @media (max-width: 700px) {
        .score-shell {
            width: min(100% - 28px, 1180px);
            padding: 42px 0 30px;
            justify-content: flex-start;
        }

        .score-header {
            margin-bottom: 24px;
        }

        .score-player {
            padding: 12px;
            border-radius: 22px;
        }

        .score-video {
            border-radius: 14px;
        }

        .score-controls {
            grid-template-columns: repeat(4, 1fr);
        }

        .score-random {
            grid-column: 1 / -1;
            grid-row: 1;
            min-height: 58px;
        }

        .score-btn:not(.score-random) {
            grid-row: 2;
        }

        .score-progress {
            grid-template-columns: 1fr;
            gap: 7px;
            padding-top: 15px;
        }

        .score-time {
            min-width: 0;
            text-align: center;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            scroll-behavior: auto !important;
            transition: none !important;
        }
    }
</style>
</head>

<body>
<div class="score-page">
    <main class="score-shell">

        <header class="score-header">
            <p class="score-eyebrow">Your life has a score</p>
            <h1>Film Score Radio</h1>
            <p class="score-description">
                A magical ✨ cinematic soundtrack for whatever you're doing right now.
            </p>
        </header>

        <section class="score-player" aria-label="Film score player">

            <div class="score-video">
                <div id="scoreYT"></div>
            </div>

            <div class="score-meta">
                <p class="score-now-label">Now Playing</p>
                <h2 class="score-title" id="scoreTitle">Loading playlist…</h2>
            </div>

            <div class="score-controls">
                <button class="score-btn" id="prevBtn" type="button" aria-label="Previous score" title="Previous">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 5v14M19 6l-9 6 9 6V6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <button class="score-btn score-play" id="playBtn" type="button" aria-label="Play" title="Play">
                    <svg id="playIcon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M8 5v14l11-7L8 5z"/>
                    </svg>
                </button>

                <button class="score-btn score-random" id="randomBtn" type="button">
                    Random Score
                </button>

                <button class="score-btn" id="nextBtn" type="button" aria-label="Next score" title="Next">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M18 19V5M5 18l9-6-9-6v12z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <button class="score-btn" id="muteBtn" type="button" aria-label="Mute" title="Mute">
                    <svg id="volumeIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 9v6h4l5 4V5L8 9H4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M16 9a4 4 0 0 1 0 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="score-progress">
                <input
                    class="score-range"
                    id="seek"
                    type="range"
                    min="0"
                    max="1000"
                    value="0"
                    step="1"
                    aria-label="Seek"
                >
                <div class="score-time" id="time">0:00 / 0:00</div>
            </div>

        </section>

        <div class="score-footer">
            <span class="score-status" id="status">YouTube playlist</span>
        </div>

    </main>
</div>

<script>
(() => {
    'use strict';

    const PLAYLIST_ID = 'PLujlkA91w1IPYB83IzRb3tYAvN0vduPLz';
    const STORAGE_KEY = 'film_score_radio_v1';

    const titleEl   = document.getElementById('scoreTitle');
    const statusEl  = document.getElementById('status');
    const playBtn   = document.getElementById('playBtn');
    const prevBtn   = document.getElementById('prevBtn');
    const nextBtn   = document.getElementById('nextBtn');
    const randomBtn = document.getElementById('randomBtn');
    const muteBtn   = document.getElementById('muteBtn');
    const seek      = document.getElementById('seek');
    const timeEl    = document.getElementById('time');

    let player = null;
    let polling = null;
    let seeking = false;

    function loadState() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null') || {};
        } catch {
            return {};
        }
    }

    function saveState(partial) {
        try {
            const current = loadState();
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({ ...current, ...partial })
            );
        } catch {}
    }

    function formatTime(seconds) {
        seconds = Math.max(0, Math.floor(Number(seconds) || 0));
        const minutes = Math.floor(seconds / 60);
        const remainder = seconds % 60;
        return `${minutes}:${String(remainder).padStart(2, '0')}`;
    }

    function loadYouTubeAPI() {
        return new Promise((resolve, reject) => {
            if (window.YT && window.YT.Player) {
                resolve();
                return;
            }

            const existing = document.querySelector(
                'script[src="https://www.youtube.com/iframe_api"]'
            );

            const previousReady = window.onYouTubeIframeAPIReady;

            window.onYouTubeIframeAPIReady = () => {
                if (typeof previousReady === 'function') {
                    try {
                        previousReady();
                    } catch {}
                }
                resolve();
            };

            if (!existing) {
                const script = document.createElement('script');
                script.src = 'https://www.youtube.com/iframe_api';
                script.onerror = reject;
                document.head.appendChild(script);
            }
        });
    }

    function waitForPlaylist(tries = 40) {
        return new Promise((resolve, reject) => {
            const tick = remaining => {
                try {
                    const playlist = player?.getPlaylist?.();

                    if (Array.isArray(playlist) && playlist.length) {
                        resolve(playlist);
                        return;
                    }
                } catch {}

                if (remaining <= 0) {
                    reject(new Error('Playlist did not become available.'));
                    return;
                }

                setTimeout(() => tick(remaining - 1), 150);
            };

            tick(tries);
        });
    }

    function updateTitle() {
        try {
            const data = player?.getVideoData?.();
            const title = data?.title || 'Film Score Radio';

            titleEl.textContent = title;
            document.title = `${title} — Film Score Radio`;
        } catch {
            titleEl.textContent = 'Film Score Radio';
        }
    }

    function updatePlayIcon() {
        const isPlaying =
            player &&
            window.YT &&
            player.getPlayerState?.() === YT.PlayerState.PLAYING;

        playBtn.innerHTML = isPlaying
            ? `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                   <path d="M6 5h4v14H6zM14 5h4v14h-4z"/>
               </svg>`
            : `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                   <path d="M8 5v14l11-7L8 5z"/>
               </svg>`;

        playBtn.setAttribute('aria-label', isPlaying ? 'Pause' : 'Play');
        playBtn.title = isPlaying ? 'Pause' : 'Play';
    }

    function updateMuteIcon() {
        let muted = false;

        try {
            muted = !!player?.isMuted?.();
        } catch {}

        muteBtn.innerHTML = muted
            ? `<svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                   <path d="M4 9v6h4l5 4V5L8 9H4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                   <path d="M20 9l-6 6M14 9l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
               </svg>`
            : `<svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                   <path d="M4 9v6h4l5 4V5L8 9H4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                   <path d="M16 9a4 4 0 0 1 0 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
               </svg>`;

        muteBtn.setAttribute('aria-label', muted ? 'Unmute' : 'Mute');
        muteBtn.title = muted ? 'Unmute' : 'Mute';
    }

    function persistProgress() {
        if (!player) return;

        try {
            saveState({
                index: Math.max(0, player.getPlaylistIndex?.() ?? 0),
                seconds: Math.max(0, player.getCurrentTime?.() ?? 0)
            });
        } catch {}
    }

    function startPolling() {
        stopPolling();

        polling = setInterval(() => {
            if (!player || seeking) return;

            try {
                const current = player.getCurrentTime?.() || 0;
                const duration = player.getDuration?.() || 0;

                if (duration > 0) {
                    seek.value = Math.round((current / duration) * 1000);
                    timeEl.textContent =
                        `${formatTime(current)} / ${formatTime(duration)}`;
                }

                persistProgress();
            } catch {}
        }, 500);
    }

    function stopPolling() {
        if (polling) {
            clearInterval(polling);
            polling = null;
        }
    }

    async function playRandomScore() {
        if (!player) return;

        let playlist = [];

        try {
            playlist = player.getPlaylist?.() || [];
        } catch {}

        if (!playlist.length) {
            try {
                playlist = await waitForPlaylist();
            } catch {
                return;
            }
        }

        if (playlist.length === 1) {
            player.playVideoAt?.(0);
            return;
        }

        const currentIndex = Math.max(
            0,
            player.getPlaylistIndex?.() ?? 0
        );

        let nextIndex = currentIndex;

        while (nextIndex === currentIndex) {
            nextIndex = Math.floor(Math.random() * playlist.length);
        }

        saveState({
            index: nextIndex,
            seconds: 0
        });

        player.playVideoAt?.(nextIndex);
        player.unMute?.();
        player.setVolume?.(100);
    }

    function handleStateChange(event) {
        if (!window.YT) return;

        if (
            event.data === YT.PlayerState.PLAYING ||
            event.data === YT.PlayerState.PAUSED ||
            event.data === YT.PlayerState.CUED
        ) {
            updatePlayIcon();
            updateMuteIcon();
            updateTitle();
            persistProgress();
        }

        if (event.data === YT.PlayerState.PLAYING) {
            startPolling();
        }

        if (event.data === YT.PlayerState.ENDED) {
            updateTitle();
            persistProgress();
        }
    }

    playBtn.addEventListener('click', () => {
        if (!player || !window.YT) return;

        const state = player.getPlayerState?.();

        if (state === YT.PlayerState.PLAYING) {
            player.pauseVideo?.();
        } else {
            player.unMute?.();
            player.setVolume?.(100);
            player.playVideo?.();
        }

        updatePlayIcon();
        updateMuteIcon();
    });

    prevBtn.addEventListener('click', () => {
        if (!player) return;

        const currentTime = player.getCurrentTime?.() || 0;

        if (currentTime > 3) {
            player.seekTo?.(0, true);
        } else {
            player.previousVideo?.();
        }

        player.playVideo?.();
    });

    nextBtn.addEventListener('click', () => {
        player?.nextVideo?.();
        player?.playVideo?.();
    });

    randomBtn.addEventListener('click', playRandomScore);

    muteBtn.addEventListener('click', () => {
        if (!player) return;

        if (player.isMuted?.()) {
            player.unMute?.();
        } else {
            player.mute?.();
        }

        updateMuteIcon();
    });

    seek.addEventListener('pointerdown', () => {
        seeking = true;
    });

    seek.addEventListener('pointerup', () => {
        seeking = false;
    });

    seek.addEventListener('input', () => {
        if (!player) return;

        const duration = player.getDuration?.() || 0;
        const percentage = Number(seek.value) / 1000;

        if (duration > 0) {
            const target = duration * percentage;
            timeEl.textContent =
                `${formatTime(target)} / ${formatTime(duration)}`;
            player.seekTo?.(target, true);
        }
    });

    window.addEventListener('beforeunload', persistProgress);

    document.addEventListener('DOMContentLoaded', async () => {
        try {
            await loadYouTubeAPI();

            player = new YT.Player('scoreYT', {
                width: '100%',
                height: '100%',
                playerVars: {
                    autoplay: 0,
                    controls: 0,
                    playsinline: 1,
                    modestbranding: 1,
                    rel: 0
                },
                events: {
                    onReady: async () => {
                        const saved = loadState();
                        const hasSavedTrack =
                            Number.isInteger(saved.index) &&
                            saved.index >= 0;

                        statusEl.textContent = 'Loading soundtrack…';

                        player.cuePlaylist({
                            listType: 'playlist',
                            list: PLAYLIST_ID,
                            index: hasSavedTrack ? saved.index : 0,
                            startSeconds: hasSavedTrack
                                ? Math.max(0, Number(saved.seconds) || 0)
                                : 0
                        });

                        try {
                            const playlist = await waitForPlaylist();

                            statusEl.textContent =
                                `${playlist.length} scores in playlist`;

                            if (!hasSavedTrack && playlist.length > 1) {
                                const randomIndex =
                                    Math.floor(Math.random() * playlist.length);

                                saveState({
                                    index: randomIndex,
                                    seconds: 0
                                });

                                player.cuePlaylist({
                                    listType: 'playlist',
                                    list: PLAYLIST_ID,
                                    index: randomIndex,
                                    startSeconds: 0
                                });
                            }
                        } catch {
                            statusEl.textContent = 'YouTube playlist';
                        }

                        player.unMute?.();
                        player.setVolume?.(100);

                        updateTitle();
                        updatePlayIcon();
                        updateMuteIcon();
                        startPolling();
                    },

                    onStateChange: handleStateChange,

                    onError: error => {
                        console.error('YouTube player error:', error.data);
                        statusEl.textContent = 'This score could not be played';
                    }
                }
            });
        } catch (error) {
            console.error(error);
            titleEl.textContent = 'YouTube could not be loaded';
            statusEl.textContent = 'Player unavailable';
        }
    });
})();
</script>
</body>
</html>