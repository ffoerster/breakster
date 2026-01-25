<script setup>
defineProps({
    class: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl" :class="$props.class">
        <slot />
    </div>
</template>
