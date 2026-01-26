<script setup>
defineProps({
    maxWidthClass: {
        type: String,
        default: 'max-w-6xl',
    },
});
</script>

<template>
    <div class="fixed inset-x-0 bottom-0 z-50">
        <div class="mx-auto w-full px-4" :class="maxWidthClass">
            <div
                class="rounded-2xl border border-white/10 bg-gray-900/70 backdrop-blur shadow-lg"
                style="padding-bottom: env(safe-area-inset-bottom, 0px)"
            >
                <div class="p-3">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
