<script setup>
import { useOnline } from "@vueuse/core";
import BrandLink from "../Components/Ui/BrandLink.vue";
import PwaInstallPrompt from "../Components/Ui/PwaInstallPrompt.vue";
import { Toaster } from "vue-sonner";

const isOnline = useOnline();

const toastOptions = {
    unstyled: true,
    classes: {
        toast: "rounded-2xl border border-accent-500/25 bg-gray-950/90 backdrop-blur shadow-2xl px-4 py-3 text-white",
        title: "text-sm font-bold text-white",
        description: "text-xs text-gray-200/80 mt-1",
        actionButton:
            "btn-interactive btn-secondary min-h-[36px] px-3 py-2 rounded-xl text-white font-bold",
        cancelButton:
            "btn-interactive btn-secondary min-h-[36px] px-3 py-2 rounded-xl text-white/90 font-bold",
        closeButton: "btn-interactive btn-secondary rounded-lg text-white/90",
        success: "border-accent-400/40",
        error: "border-red-500/40",
        warning: "border-amber-500/40",
        info: "border-accent-500/40",
    },
};

defineProps({
    containerClass: {
        type: String,
        default: "w-full max-w-6xl",
    },
    mainClass: {
        type: String,
        default: "flex-1 flex items-center justify-center px-4 py-8",
    },
    showFooter: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div class="min-h-screen flex flex-col arcade-shell">
        <Toaster
            position="top-center"
            :toast-options="toastOptions"
            :visible-toasts="3"
            :duration="2400"
            offset="calc(env(safe-area-inset-top, 0px) + 12px)"
        />

        <div v-if="!isOnline" class="bg-amber-500/15 border-b border-amber-500/30">
            <div
                class="mx-auto w-full max-w-6xl px-6 py-2 text-sm text-amber-200 flex items-center justify-between gap-4"
            >
                <span class="font-medium"
                    >You're offline. Changes won't sync until you're back online.</span
                >
                <span class="text-xs text-amber-200/70">Offline</span>
            </div>
        </div>

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

        <footer v-if="showFooter" class="py-4 text-center text-gray-500 text-sm">
            A timeline music game
        </footer>

        <PwaInstallPrompt />
    </div>
</template>
