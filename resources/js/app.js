import './bootstrap';

import Sortable from 'sortablejs';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('timelineSortable', () => ({
        sortable: null,
        init() {
            if (this.sortable) {
                this.sortable.destroy();
            }

            const trackIds = this.getTrackIds();
            if (trackIds.length < 2) {
                return;
            }

            this.sortable = Sortable.create(this.$el, {
                animation: 150,
                ghostClass: 'opacity-60',
                onEnd: () => {
                    const ordered = this.getTrackIds();
                    if (ordered.length < 2) {
                        return;
                    }

                    this.$wire.reorderTimeline(ordered);
                },
            });
        },
        getTrackIds() {
            return Array.from(this.$el.querySelectorAll('[data-track-id]'))
                .map((el) => Number.parseInt(el.getAttribute('data-track-id'), 10))
                .filter((id) => Number.isFinite(id));
        },
    }));
});
