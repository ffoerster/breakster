<script setup>
import { useEventListener, useScrollLock } from '@vueuse/core';
import { useFocusTrap } from '@vueuse/integrations/useFocusTrap';
import { useDrag } from '@vueuse/gesture';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        default: '',
    },
    showCloseButton: {
        type: Boolean,
        default: true,
    },
    closeLabel: {
        type: String,
        default: 'Close',
    },
    allowDragToClose: {
        type: Boolean,
        default: true,
    },
    maxWidthClass: {
        type: String,
        default: 'max-w-6xl',
    },
});

const emit = defineEmits(['close']);

const closeButtonEl = ref(null);
const panelEl = ref(null);
const dragHandleEl = ref(null);
const sheetDragY = ref(0);
let restoreFocusEl = null;
const isScrollLocked = useScrollLock(document.body, false);
const titleId = `bottom-sheet-title-${Math.random().toString(36).slice(2)}`;

const { activate: activateFocusTrap, deactivate: deactivateFocusTrap } = useFocusTrap(panelEl, {
    immediate: false,
    escapeDeactivates: false,
    clickOutsideDeactivates: false,
    returnFocusOnDeactivate: false,
});

let stopKeydownListener = null;

let appRootEl = null;
let previousAriaHidden = null;
let hadInert = false;

const close = () => {
    sheetDragY.value = 0;
    emit('close');
};

const onKeydown = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();
        close();
        return;
    }
};

const labelledById = computed(() => {
    if (!props.title) {
        return null;
    }

    return titleId;
});

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) {
            stopKeydownListener?.();
            stopKeydownListener = null;

            deactivateFocusTrap();

            isScrollLocked.value = false;

            if (appRootEl) {
                if (previousAriaHidden === null) {
                    appRootEl.removeAttribute('aria-hidden');
                } else {
                    appRootEl.setAttribute('aria-hidden', previousAriaHidden);
                }

                if (!hadInert) {
                    appRootEl.removeAttribute('inert');
                }

                appRootEl = null;
                previousAriaHidden = null;
                hadInert = false;
            }

            if (restoreFocusEl && restoreFocusEl.focus) {
                restoreFocusEl.focus();
            }

            restoreFocusEl = null;

            sheetDragY.value = 0;
            return;
        }

        restoreFocusEl = document.activeElement;

        isScrollLocked.value = true;

        appRootEl = document.getElementById('app');
        if (appRootEl) {
            previousAriaHidden = appRootEl.getAttribute('aria-hidden');
            hadInert = appRootEl.hasAttribute('inert');
            appRootEl.setAttribute('aria-hidden', 'true');
            appRootEl.setAttribute('inert', '');
        }

        stopKeydownListener = useEventListener(document, 'keydown', onKeydown, { passive: false });

        await nextTick();

        activateFocusTrap();

        const root = panelEl.value;
        const focusTarget = closeButtonEl.value
            ?? root?.querySelector?.('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');

        focusTarget?.focus?.();
    },
    { immediate: true },
);

useDrag(
    ({ movement: [, my], velocity: [, vy], last, event }) => {
        if (!props.open || !props.allowDragToClose) {
            return;
        }

        event?.preventDefault?.();

        sheetDragY.value = Math.max(0, my);

        if (!last) {
            return;
        }

        const shouldClose = sheetDragY.value > 120 || vy > 0.6;
        if (shouldClose) {
            close();
            return;
        }

        sheetDragY.value = 0;
    },
    {
        target: dragHandleEl,
        filterTaps: true,
        axis: 'y',
        eventOptions: { passive: false },
    },
);

onBeforeUnmount(() => {
    stopKeydownListener?.();
    stopKeydownListener = null;

    deactivateFocusTrap();

    isScrollLocked.value = false;

    if (appRootEl) {
        if (previousAriaHidden === null) {
            appRootEl.removeAttribute('aria-hidden');
        } else {
            appRootEl.setAttribute('aria-hidden', previousAriaHidden);
        }

        if (!hadInert) {
            appRootEl.removeAttribute('inert');
        }
    }

    sheetDragY.value = 0;
});
</script>

<template>
    <Teleport to="body">
        <Transition name="sheet-fade">
            <div
                v-if="open"
                class="fixed inset-0 z-50"
                aria-live="polite"
            >
                <div
                    class="absolute inset-0 bg-black/60"
                    @click="close"
                />

                <Transition name="sheet-slide">
                    <div
                        class="absolute inset-x-0 bottom-0"
                        :class="open ? 'pointer-events-auto' : 'pointer-events-none'"
                        :style="{ '--sheet-drag-y': `${sheetDragY}px` }"
                    >
                        <div class="mx-auto w-full px-4" :class="maxWidthClass">
                            <div
                                ref="panelEl"
                                class="rounded-t-3xl border border-accent-500/20 bg-gray-950/90 backdrop-blur shadow-2xl"
                                style="padding-bottom: env(safe-area-inset-bottom, 0px)"
                                role="dialog"
                                aria-modal="true"
                                :aria-labelledby="labelledById"
                            >
                                <div class="pt-3">
                                    <div
                                        ref="dragHandleEl"
                                        class="mx-auto h-1.5 w-12 rounded-full bg-white/20"
                                        style="touch-action: pan-x"
                                        aria-hidden="true"
                                    />
                                </div>

                                <div class="flex items-center justify-between gap-3 px-4 pt-4 pb-3">
                                    <div
                                        v-if="title"
                                        :id="titleId"
                                        class="text-sm font-semibold text-white/85 font-display"
                                    >
                                        {{ title }}
                                    </div>
                                    <div v-else />

                                    <button
                                        v-if="showCloseButton"
                                        ref="closeButtonEl"
                                        type="button"
                                        class="btn-interactive btn-secondary min-h-[44px] px-4 py-2 rounded-xl text-white font-bold"
                                        @click="close"
                                    >
                                        {{ closeLabel }}
                                    </button>
                                </div>

                                <div class="px-4 pb-6 max-h-[75vh] overflow-auto">
                                    <slot />
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
