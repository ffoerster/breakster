import './bootstrap';
import './room-echo';

import Sortable from 'sortablejs';

document.addEventListener('alpine:init', () => {
    // Existing timeline reordering
    window.Alpine.data('timelineSortable', () => ({
        sortable: null,
        init() {
            this.initSortable();

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

    // New drag-and-drop placement for current card
    window.Alpine.data('dragPlacement', (isLocked) => ({
        isDragging: false,
        currentDropZone: null,
        isLocked: isLocked,

        init() {
            const mysteryCard = document.getElementById('current-mystery-card');
            if (!mysteryCard || this.isLocked) {
                return;
            }

            // Make mystery card draggable
            mysteryCard.setAttribute('draggable', 'true');

            // Desktop drag events
            mysteryCard.addEventListener('dragstart', (e) => this.handleDragStart(e));
            mysteryCard.addEventListener('dragend', (e) => this.handleDragEnd(e));

            // Touch events for mobile
            let touchStartY = 0;
            let touchStartX = 0;
            let clone = null;

            mysteryCard.addEventListener('touchstart', (e) => {
                if (this.isLocked) {
                    return;
                }

                const touch = e.touches[0];
                touchStartX = touch.clientX;
                touchStartY = touch.clientY;

                // Create visual clone for dragging
                clone = mysteryCard.cloneNode(true);
                clone.style.position = 'fixed';
                clone.style.pointerEvents = 'none';
                clone.style.opacity = '0.8';
                clone.style.zIndex = '9999';
                clone.style.width = mysteryCard.offsetWidth + 'px';
                clone.style.left = mysteryCard.getBoundingClientRect().left + 'px';
                clone.style.top = mysteryCard.getBoundingClientRect().top + 'px';
                document.body.appendChild(clone);

                mysteryCard.style.opacity = '0.3';
                this.isDragging = true;
            });

            mysteryCard.addEventListener('touchmove', (e) => {
                if (!this.isDragging || !clone) {
                    return;
                }

                e.preventDefault();
                const touch = e.touches[0];
                clone.style.left = (touch.clientX - mysteryCard.offsetWidth / 2) + 'px';
                clone.style.top = (touch.clientY - 40) + 'px';

                // Detect drop zone under touch
                const dropZones = document.querySelectorAll('[data-drop-zone]');
                let foundZone = null;

                dropZones.forEach((zone) => {
                    const rect = zone.getBoundingClientRect();
                    if (
                        touch.clientX >= rect.left &&
                        touch.clientX <= rect.right &&
                        touch.clientY >= rect.top &&
                        touch.clientY <= rect.bottom
                    ) {
                        foundZone = zone;
                    }
                });

                this.highlightDropZone(foundZone);
            });

            mysteryCard.addEventListener('touchend', (e) => {
                if (!this.isDragging) {
                    return;
                }

                if (clone) {
                    clone.remove();
                    clone = null;
                }

                mysteryCard.style.opacity = '1';
                this.isDragging = false;

                if (this.currentDropZone) {
                    this.handleDrop(this.currentDropZone);
                }

                this.clearDropZoneHighlights();
            });

            // Set up drop zones for desktop
            const dropZones = document.querySelectorAll('[data-drop-zone]');
            dropZones.forEach((zone) => {
                zone.addEventListener('dragover', (e) => this.handleDragOver(e));
                zone.addEventListener('dragleave', (e) => this.handleDragLeave(e));
                zone.addEventListener('drop', (e) => this.handleDropEvent(e));
            });
        },

        handleDragStart(e) {
            if (this.isLocked) {
                e.preventDefault();
                return;
            }
            this.isDragging = true;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', e.target.innerHTML);
        },

        handleDragEnd(e) {
            this.isDragging = false;
            this.clearDropZoneHighlights();
        },

        handleDragOver(e) {
            if (!this.isDragging || this.isLocked) {
                return;
            }
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.highlightDropZone(e.currentTarget);
        },

        handleDragLeave(e) {
            if (e.currentTarget === this.currentDropZone) {
                e.currentTarget.classList.remove('border-purple-500', 'bg-purple-500/20', 'scale-105');
                e.currentTarget.classList.add('border-purple-500/30');
                this.currentDropZone = null;
            }
        },

        handleDropEvent(e) {
            if (!this.isDragging || this.isLocked) {
                return;
            }
            e.preventDefault();
            this.handleDrop(e.currentTarget);
        },

        handleDrop(dropZone) {
            const insertAfterIdStr = dropZone.getAttribute('data-insert-after-id');
            const insertAfterId = insertAfterIdStr === '' ? null : Number.parseInt(insertAfterIdStr, 10);

            // Call Livewire method
            this.$wire.submitPlacement(insertAfterId);

            this.clearDropZoneHighlights();
        },

        highlightDropZone(zone) {
            if (this.currentDropZone && this.currentDropZone !== zone) {
                this.currentDropZone.classList.remove('border-purple-500', 'bg-purple-500/20', 'scale-105');
                this.currentDropZone.classList.add('border-purple-500/30');
            }

            if (zone) {
                zone.classList.remove('border-purple-500/30');
                zone.classList.add('border-purple-500', 'bg-purple-500/20', 'scale-105');
                this.currentDropZone = zone;
            }
        },

        clearDropZoneHighlights() {
            const dropZones = document.querySelectorAll('[data-drop-zone]');
            dropZones.forEach((zone) => {
                zone.classList.remove('border-purple-500', 'bg-purple-500/20', 'scale-105');
                zone.classList.add('border-purple-500/30');
            });
            this.currentDropZone = null;
        },
    }));
});
