import './bootstrap';
import './room-echo';

import Sortable from 'sortablejs';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('timelineSortable', () => ({
        sortable: null,
        init() {
            this.initSortable();
            
            // Re-init on timeline updates
            this.$watch('$wire.$snapshot', () => {
                this.$nextTick(() => this.initSortable());
            });
            
            // Listen for manual re-init events
            window.addEventListener('timeline:updated', () => {
                this.$nextTick(() => this.initSortable());
            });
        },
        initSortable() {
            if (this.sortable) {
                this.sortable.destroy();
                this.sortable = null;
            }

            const trackIds = this.getTrackIds();
            if (trackIds.length < 2) {
                return;
            }

            this.sortable = Sortable.create(this.$el, {
                animation: 150,
                ghostClass: 'opacity-50',
                handle: '.drag-handle',
                fallbackOnBody: true,
                swapThreshold: 0.65,
                delay: 150,
                delayOnTouchOnly: true,
                touchStartThreshold: 5,
                forceFallback: false,
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
