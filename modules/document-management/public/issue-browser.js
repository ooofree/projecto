(() => {
    const root = document.querySelector('[data-issue-browser]');
    if (!root || document.documentElement.dataset.issueBrowserInit === '1') return;
    document.documentElement.dataset.issueBrowserInit = '1';

    const cards = [...root.querySelectorAll('[data-issue-card]')].filter(card => !card.closest('.ib-detail'));
    const details = [...root.querySelectorAll('[data-ib-detail]')];

    const showIssue = (issueId, remember) => {
        for (const detail of details) {
            detail.hidden = detail.dataset.ibDetail !== issueId;
        }
        for (const card of cards) {
            const active = card.dataset.issueCard === issueId;
            card.classList.toggle('is-active', active);
            if (active) {
                card.setAttribute('aria-current', 'true');
            } else {
                card.removeAttribute('aria-current');
            }
        }
        if (remember) {
            const url = '?page=issue-browser&issue=' + encodeURIComponent(issueId) + '#issue-detail-' + issueId;
            try {
                history.replaceState(null, '', url);
            } catch (error) {
                // file:// previews and hardened contexts may reject replaceState.
            }
        }
    };

    for (const card of cards) {
        card.addEventListener('click', event => {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
            event.preventDefault();
            showIssue(card.dataset.issueCard, true);
            if (window.innerWidth <= 1080) {
                const detail = root.querySelector('[data-ib-detail="' + card.dataset.issueCard + '"]');
                detail?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    // Image stage: one slide at a time, thumbs, counter, fullscreen. The stage
    // is a read-only viewer; adding/removing images stays in the forms below.
    for (const detail of details) {
        const stage = detail.querySelector('[data-ib-stage]');
        if (!stage) continue;
        const slides = [...stage.querySelectorAll('[data-ib-slide]')];
        if (slides.length === 0) continue;

        const thumbs = [...stage.querySelectorAll('[data-ib-thumb]')];
        const counter = stage.querySelector('[data-ib-counter]');
        const originalLink = stage.querySelector('[data-ib-original-link]');
        const prev = stage.querySelector('[data-ib-prev]');
        const next = stage.querySelector('[data-ib-next]');
        let index = 0;

        const render = () => {
            slides.forEach((slide, slideIndex) => {
                slide.hidden = slideIndex !== index;
            });
            thumbs.forEach((thumb, thumbIndex) => {
                thumb.classList.toggle('is-active', thumbIndex === index);
            });
            if (counter) counter.textContent = (index + 1) + ' / ' + slides.length;
            const source = slides[index]?.querySelector('[data-ib-original]');
            if (originalLink && source) originalLink.href = source.href;
        };

        const step = delta => {
            index = (index + delta + slides.length) % slides.length;
            render();
        };

        prev?.addEventListener('click', () => step(-1));
        next?.addEventListener('click', () => step(1));
        if (slides.length <= 1) {
            if (prev) prev.disabled = true;
            if (next) next.disabled = true;
        }
        for (const thumb of thumbs) {
            thumb.addEventListener('click', () => {
                index = Number.parseInt(thumb.dataset.ibThumb, 10) || 0;
                render();
            });
        }

        stage.querySelector('[data-ib-fullscreen]')?.addEventListener('click', () => {
            if (document.fullscreenElement) {
                document.exitFullscreen?.();
            } else if (stage.requestFullscreen) {
                stage.requestFullscreen();
            } else {
                const source = slides[index]?.querySelector('[data-ib-original]');
                if (source) window.open(source.href, '_blank', 'noopener');
            }
        });
        document.addEventListener('fullscreenchange', () => {
            const button = stage.querySelector('[data-ib-fullscreen]');
            if (button) button.textContent = document.fullscreenElement ? '退出全屏' : '全屏查看';
        });

        render();
    }

    // Deep links from the ledger (?page=issue-browser&issue=12#issue-detail-12)
    // and plain fragments both resolve to the same panel.
    const hashMatch = location.hash.match(/^#issue-detail-(\d+)$/);
    if (hashMatch && details.some(detail => detail.dataset.ibDetail === hashMatch[1])) {
        showIssue(hashMatch[1], false);
    }
})();
