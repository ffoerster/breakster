<script setup>
import { useEventListener, useMediaQuery } from '@vueuse/core';
import { computed, onMounted, ref } from 'vue';

import BottomActionBar from './BottomActionBar.vue';
import BottomSheet from './BottomSheet.vue';

const STORAGE_KEY = 'pwa_install_dismissed_until';
const DISMISS_FOR_DAYS = 7;

const deferredPrompt = ref(null);
const isPromptAvailable = ref(false);
const isInstallSheetOpen = ref(false);

const isSmallScreen = useMediaQuery('(max-width: 640px)');
const isCoarsePointer = useMediaQuery('(pointer: coarse)');

const now = () => Date.now();

const isStandalone = () => {
    const standaloneMatch = window.matchMedia?.('(display-mode: standalone)')?.matches === true;
    const iosStandalone = window.navigator?.standalone === true;

    return standaloneMatch || iosStandalone;
};

const isIos = () => {
    const ua = window.navigator?.userAgent ?? '';

    return /iPad|iPhone|iPod/i.test(ua);
};

const dismissedUntil = ref(0);

const loadDismissedUntil = () => {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? Number.parseInt(raw, 10) : 0;

    dismissedUntil.value = Number.isFinite(parsed) ? parsed : 0;
};

const dismiss = () => {
    const until = now() + DISMISS_FOR_DAYS * 24 * 60 * 60 * 1000;
    window.localStorage.setItem(STORAGE_KEY, String(until));
    dismissedUntil.value = until;
    isInstallSheetOpen.value = false;
};

const canShowBanner = computed(() => {
    if (!isSmallScreen.value && !isCoarsePointer.value) {
        return false;
    }

    if (now() < dismissedUntil.value) {
        return false;
    }

    if (isStandalone()) {
        return false;
    }

    return isPromptAvailable.value || isIos();
});

const primaryLabel = computed(() => (isPromptAvailable.value ? 'Install' : 'How to install'));

const handlePrimary = async () => {
    if (!isPromptAvailable.value || !deferredPrompt.value) {
        isInstallSheetOpen.value = true;
        return;
    }

    deferredPrompt.value.prompt();

    try {
        await deferredPrompt.value.userChoice;
    } finally {
        deferredPrompt.value = null;
        isPromptAvailable.value = false;
        dismiss();
    }
};

onMounted(() => {
    loadDismissedUntil();
});

useEventListener(window, 'beforeinstallprompt', (e) => {
    e.preventDefault();

    deferredPrompt.value = e;
    isPromptAvailable.value = true;
});

useEventListener(window, 'appinstalled', () => {
    deferredPrompt.value = null;
    isPromptAvailable.value = false;
    dismiss();
});
</script>

<template>
    <BottomActionBar v-if="canShowBanner" max-width-class="max-w-6xl">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-sm font-bold text-white">Install Breakster</div>
                <div class="text-xs text-white/75 truncate">Faster launches and a full-screen experience.</div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    class="btn-interactive min-h-[40px] px-4 py-2 rounded-xl bg-accent-500 hover:bg-accent-600 text-white font-bold"
                    @click="handlePrimary"
                >
                    {{ primaryLabel }}
                </button>
                <button
                    type="button"
                    class="btn-interactive min-h-[40px] px-3 py-2 rounded-xl bg-gray-800/50 text-white/90 font-bold"
                    @click="dismiss"
                >
                    Not now
                </button>
            </div>
        </div>
    </BottomActionBar>

    <BottomSheet
        :open="isInstallSheetOpen"
        title="Install Breakster"
        close-label="Done"
        max-width-class="max-w-md"
        @close="isInstallSheetOpen = false"
    >
        <div class="space-y-4 text-sm text-gray-200">
            <p class="text-gray-200/90">
                iOS doesn't support an automatic install prompt. Add Breakster to your Home Screen:
            </p>

            <ol class="space-y-2 list-decimal list-inside text-gray-200/85">
                <li>Tap the Share button in Safari.</li>
                <li>Choose "Add to Home Screen".</li>
                <li>Tap "Add".</li>
            </ol>

            <div class="pt-2">
                <button
                    type="button"
                    class="btn-interactive w-full min-h-[44px] px-4 py-3 rounded-xl bg-gray-800/70 text-white font-bold"
                    @click="dismiss"
                >
                    Hide for a week
                </button>
            </div>
        </div>
    </BottomSheet>
</template>
