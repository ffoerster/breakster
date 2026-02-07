<script setup>
import GlassCard from '../Ui/GlassCard.vue';

defineProps({
    room: {
        type: Object,
        required: true,
    },
    me: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <GlassCard class="p-8">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
            <div>
                <h1 class="text-3xl font-bold font-display">Room <span class="font-mono-party">{{ room.code }}</span></h1>
                <div class="mt-3">
                    <div class="text-sm text-white/55 uppercase tracking-wide font-display">Music Pack</div>
                    <p class="text-xl font-semibold text-accent-400">{{ room.pack.name }}</p>
                    <p v-if="room.pack.description" class="text-sm text-white/60 mt-1">
                        {{ room.pack.description }}
                    </p>
                </div>
            </div>
            <div class="text-left md:text-right">
                <div class="text-sm text-white/55 font-display">You are</div>
                <div class="text-lg font-bold">
                    {{ me.display_name }}
                    <span v-if="me.is_host" class="ml-2 px-2 py-1 bg-yellow-500/20 text-yellow-400 text-xs rounded-full">
                        Host
                    </span>
                </div>
            </div>
        </div>
    </GlassCard>
</template>
