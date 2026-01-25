import './bootstrap';

import Sortable from 'sortablejs';

const initialized = new WeakSet();

function initBreaksterSortable(el) {
    if (initialized.has(el)) {
        return;
    }

    if (el.dataset.breaksterDisabled === '1') {
        return;
    }

    const livewireId = el.dataset.breaksterLivewireId;
    if (!livewireId) {
        return;
    }

    initialized.add(el);

    Sortable.create(el, {
        animation: 150,
        draggable: '[data-breakster-new-track]',
        ghostClass: 'opacity-60',
        onEnd: () => {
            const newCard = el.querySelector('[data-breakster-new-track]');
            if (!newCard) {
                return;
            }

            let afterId = null;
            let prev = newCard.previousElementSibling;
            while (prev) {
                const trackId = prev.getAttribute('data-track-id');
                if (trackId) {
                    afterId = Number.parseInt(trackId, 10);
                    break;
                }
                prev = prev.previousElementSibling;
            }

            if (!window.Livewire) {
                return;
            }

            const component = window.Livewire.find(livewireId);
            if (!component) {
                return;
            }

            component.call('submitPlacement', Number.isFinite(afterId) ? afterId : null);
        },
    });
}

function initAll() {
    document
        .querySelectorAll('[data-breakster-sortable]')
        .forEach((el) => initBreaksterSortable(el));
}

document.addEventListener('DOMContentLoaded', () => {
    initAll();

    const observer = new MutationObserver(() => initAll());
    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
});
