(() => {
    const root = document.querySelector('[data-issue-browser]');
    if (!root || document.documentElement.dataset.issueBrowserInit === '1') return;
    document.documentElement.dataset.issueBrowserInit = '1';

    const cards = [...root.querySelectorAll('[data-issue-card]')].filter(card => !card.closest('.ib-detail'));
    const details = [...root.querySelectorAll('[data-ib-detail]')];
    const zoom = root.querySelector('[data-ib-zoom]');
    const zoomImage = zoom?.querySelector('[data-ib-zoom-image]');
    const zoomCounter = zoom?.querySelector('[data-ib-zoom-counter]');
    const controllers = new Map();
    let zoomController = null;

    const closeZoom = () => {
        if (!zoom || zoom.hidden) return;
        zoom.hidden = true;
        zoomController = null;
        document.body.classList.remove('is-zoom-open');
        if (zoomImage) {
            zoomImage.removeAttribute('src');
            zoomImage.alt = '';
        }
    };

    const syncZoom = controller => {
        if (!zoom || !zoomImage || zoomController !== controller) return;
        const image = controller.currentImage();
        if (!image) return;
        zoomImage.src = image.getAttribute('src') || '';
        zoomImage.alt = image.alt || '';
        if (zoomCounter) {
            zoomCounter.textContent = (controller.index() + 1) + ' / ' + controller.length();
        }
    };

    const openZoom = controller => {
        if (!zoom || !zoomImage || !controller.currentImage()) return;
        zoomController = controller;
        zoom.hidden = false;
        document.body.classList.add('is-zoom-open');
        syncZoom(controller);
        zoom.focus?.({ preventScroll: true });
    };

    const showIssue = (issueId, remember) => {
        closeZoom();
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

    // Image stage: one slide at a time. Clicking the photo opens an in-page
    // zoom overlay; clicking the left/right edges or using arrow keys steps
    // through the images. The stage itself stays read-only; add/remove stays
    // in the forms below.
    for (const detail of details) {
        const stage = detail.querySelector('[data-ib-stage]');
        if (!stage) continue;
        const slides = [...stage.querySelectorAll('[data-ib-slide]')];
        const thumbs = [...stage.querySelectorAll('[data-ib-thumb]')];
        const counter = stage.querySelector('[data-ib-counter]');
        const originalLink = stage.querySelector('[data-ib-original-link]');
        const prev = stage.querySelector('[data-ib-prev]');
        const next = stage.querySelector('[data-ib-next]');
        if (slides.length === 0) continue;

        let index = 0;
        const currentImage = () => slides[index]?.querySelector('img') ?? null;

        const render = () => {
            slides.forEach((slide, slideIndex) => {
                slide.hidden = slideIndex !== index;
            });
            thumbs.forEach((thumb, thumbIndex) => {
                thumb.classList.toggle('is-active', thumbIndex === index);
            });
            if (counter) counter.textContent = (index + 1) + ' / ' + slides.length;
            const image = currentImage();
            if (originalLink && image) originalLink.href = image.getAttribute('src') || '#';
        };

        const controller = {
            detail,
            index: () => index,
            length: () => slides.length,
            currentImage,
            go: value => {
                index = (value + slides.length) % slides.length;
                render();
                syncZoom(controller);
            },
            step: delta => controller.go(index + delta),
        };
        controllers.set(detail, controller);

        prev?.addEventListener('click', () => controller.step(-1));
        next?.addEventListener('click', () => controller.step(1));
        stage.querySelector('[data-ib-zone-prev]')?.addEventListener('click', () => controller.step(-1));
        stage.querySelector('[data-ib-zone-next]')?.addEventListener('click', () => controller.step(1));
        if (slides.length <= 1) {
            if (prev) prev.disabled = true;
            if (next) next.disabled = true;
        }
        for (const thumb of thumbs) {
            thumb.addEventListener('click', () => {
                const targetIndex = Number.parseInt(thumb.dataset.ibThumb, 10) || 0;
                controller.go(targetIndex);
            });
        }
        stage.querySelector('[data-ib-zoom-open]')?.addEventListener('click', () => openZoom(controller));
        for (const image of slides.map(slide => slide.querySelector('img')).filter(Boolean)) {
            image.addEventListener('click', () => openZoom(controller));
        }

        render();
    }

    // Zoom overlay: backdrop/× closes, its edge buttons and keyboard arrows
    // move through the same image set as the stage.
    zoom?.querySelector('[data-ib-zoom-close]')?.addEventListener('click', closeZoom);
    zoom?.addEventListener('click', event => {
        if (event.target === zoom) closeZoom();
    });
    zoom?.querySelector('[data-ib-zoom-prev]')?.addEventListener('click', () => zoomController?.step(-1));
    zoom?.querySelector('[data-ib-zoom-next]')?.addEventListener('click', () => zoomController?.step(1));

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && zoomController) {
            event.preventDefault();
            closeZoom();
            return;
        }
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        const target = event.target;
        if (target instanceof Element && target.closest('input, textarea, select, [contenteditable="true"]')) return;
        if (document.querySelector('dialog[open]')) return;

        const delta = event.key === 'ArrowLeft' ? -1 : 1;
        if (zoomController) {
            event.preventDefault();
            zoomController.step(delta);
            return;
        }
        const visible = [...controllers.entries()].find(([detail]) => !detail.hidden);
        if (!visible) return;
        event.preventDefault();
        visible[1].step(delta);
    });

    // Deep links from the ledger (?page=issue-browser&issue=12#issue-detail-12)
    // and plain fragments both resolve to the same panel.
    const hashMatch = location.hash.match(/^#issue-detail-(\d+)$/);
    if (hashMatch && details.some(detail => detail.dataset.ibDetail === hashMatch[1])) {
        showIssue(hashMatch[1], false);
    }
})();
