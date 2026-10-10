(() => {
    const scene = document.querySelector('.scene--morph');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (!scene || reduceMotion.matches) return;

    let framePending = false;
    const updateProgress = () => {
        const bounds = scene.getBoundingClientRect();
        const travel = Math.max(1, scene.offsetHeight - window.innerHeight);
        const progress = Math.min(1, Math.max(0, -bounds.top / travel));
        scene.style.setProperty('--morph', progress.toFixed(4));
        framePending = false;
    };
    const requestUpdate = () => {
        if (!framePending) {
            framePending = true;
            window.requestAnimationFrame(updateProgress);
        }
    };

    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate, { passive: true });
    updateProgress();
})();