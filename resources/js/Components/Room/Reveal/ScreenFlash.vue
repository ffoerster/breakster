<script setup>
import { ref, watch } from "vue";
import { usePreferredReducedMotion } from "@vueuse/core";

const props = defineProps({
    active: {
        type: Boolean,
        default: false,
    },
});

const prefersReducedMotion = usePreferredReducedMotion();
const showFlash = ref(false);

watch(
    () => props.active,
    (isActive) => {
        if (isActive && prefersReducedMotion.value !== "reduce") {
            showFlash.value = true;
            setTimeout(() => {
                showFlash.value = false;
            }, 400);
        }
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="showFlash"
            class="fixed inset-0 pointer-events-none z-[9999] animate-edge-flash-red"
        />
    </Teleport>
</template>
