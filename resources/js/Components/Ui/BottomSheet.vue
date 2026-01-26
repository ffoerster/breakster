<script setup>
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
    maxWidthClass: {
        type: String,
        default: 'max-w-6xl',
    },
});

const emit = defineEmits(['close']);

const closeButtonEl = ref(null);
let restoreFocusEl = null;
let originalBodyOverflow = '';
const titleId = `bottom-sheet-title-${Math.random().toString(36).slice(2)}`;

const close = () => {
    emit('close');
};

const onKeydown = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();
        close();
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
            document.removeEventListener('keydown', onKeydown);
            document.body.style.overflow = originalBodyOverflow;

            if (restoreFocusEl && restoreFocusEl.focus) {
                restoreFocusEl.focus();
            }

            restoreFocusEl = null;
            return;
        }

        restoreFocusEl = document.activeElement;
        originalBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeydown);

        await nextTick();
        closeButtonEl.value?.focus?.();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = originalBodyOverflow;
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
                    >
                        <div class="mx-auto w-full px-4" :class="maxWidthClass">
                            <div
                                class="rounded-t-3xl border border-white/10 bg-gray-950/90 backdrop-blur shadow-2xl"
                                style="padding-bottom: env(safe-area-inset-bottom, 0px)"
                                role="dialog"
                                aria-modal="true"
                                :aria-labelledby="labelledById"
                            >
                                <div class="flex items-center justify-between gap-3 px-4 pt-4 pb-3">
                                    <div
                                        v-if="title"
                                        :id="titleId"
                                        class="text-sm font-semibold text-gray-200"
                                    >
                                        {{ title }}
                                    </div>
                                    <div v-else />

                                    <button
                                        ref="closeButtonEl"
                                        type="button"
                                        class="btn-interactive min-h-[44px] px-4 py-2 rounded-xl bg-gray-800/70 text-white font-bold"
                                        @click="close"
                                    >
                                        Close
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
