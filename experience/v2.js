(() => {
    const sequences = [...document.querySelectorAll('[data-sequence]')];
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (!sequences.length || reduceMotion.matches) return;

    const clamp = (value) => Math.min(1, Math.max(0, value));
    const smoothRange = (start, end, value) => {
        const amount = clamp((value - start) / (end - start));
        return amount * amount * (3 - 2 * amount);
    };
    let framePending = false;

    const updateTimeline = () => {
        const scrollY = window.scrollY;
        for (const sequence of sequences) {
            const start = scrollY + sequence.getBoundingClientRect().top;
            const travel = Math.max(1, sequence.offsetHeight - window.innerHeight);
            const progress = clamp((scrollY - start) / travel);
            sequence.style.setProperty('--progress', progress.toFixed(4));

            const beats = sequence.querySelectorAll('[data-beat]');
            const position = progress * Math.max(0, beats.length - 1);
            beats.forEach((beat, index) => {
                const linearWeight = clamp(1 - Math.abs(position - index));
                const weight = linearWeight * linearWeight * (3 - 2 * linearWeight);
                beat.style.setProperty('--beat-weight', weight.toFixed(3));
                beat.style.setProperty('--beat-direction', index < position ? '-1' : index > position ? '1' : '0');
            });

            if (sequence.classList.contains('sequence--pages')) {
                sequence.style.setProperty('--opening', smoothRange(.04, .5, progress).toFixed(3));
                sequence.style.setProperty('--depth', smoothRange(.25, .85, progress).toFixed(3));
            } else if (sequence.classList.contains('sequence--change')) {
                sequence.style.setProperty('--frame', smoothRange(.12, .76, progress).toFixed(3));
                sequence.style.setProperty('--beam', smoothRange(.48, 1, progress).toFixed(3));
            }
        }
        framePending = false;
    };

    const requestTimelineUpdate = () => {
        if (framePending) return;
        framePending = true;
        window.requestAnimationFrame(updateTimeline);
    };

    window.addEventListener('scroll', requestTimelineUpdate, { passive: true });
    window.addEventListener('resize', requestTimelineUpdate, { passive: true });
    window.addEventListener('pageshow', requestTimelineUpdate);
    updateTimeline();
})();