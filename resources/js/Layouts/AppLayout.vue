<script setup>
import BrandLink from '../Components/Ui/BrandLink.vue';

defineProps({
    containerClass: {
        type: String,
        default: 'w-full max-w-6xl',
    },
    mainClass: {
        type: String,
        default: 'flex-1 flex items-center justify-center px-4 py-8',
    },
    showFooter: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <header class="py-4 px-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <slot name="header-left">
                    <BrandLink />
                </slot>
            </div>
            <div>
                <slot name="header-right" />
            </div>
        </header>

        <main :class="mainClass">
            <div :class="containerClass">
                <slot />
            </div>
        </main>

        <footer v-if="showFooter" class="py-4 text-center text-gray-500 text-sm">A timeline music game</footer>
    </div>
</template>
