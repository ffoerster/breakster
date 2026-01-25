<script setup>
defineProps({
    message: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center px-6">
        <div class="w-full max-w-xl bg-gray-900/40 backdrop-blur rounded-2xl border border-white/10 p-8 shadow-xl">
            <div class="text-sm text-gray-400 mb-2">Inertia + Vue</div>
            <h1 class="text-3xl font-bold mb-3">Breakster frontend migration</h1>
            <p class="text-gray-200">
                {{ message }}
            </p>
        </div>
    </div>
</template>
