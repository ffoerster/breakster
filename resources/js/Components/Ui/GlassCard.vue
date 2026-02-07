<script setup>
defineProps({
    class: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div
        class="bg-gray-950/70 backdrop-blur border border-white/10 rounded-2xl shadow-2xl"
        :class="$props.class"
    >
        <slot />
    </div>
</template>
