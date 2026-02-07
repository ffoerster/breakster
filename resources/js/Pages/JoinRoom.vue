<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import GlassCard from '../Components/Ui/GlassCard.vue';

const props = defineProps({
    initial_display_name: {
        type: String,
        default: '',
    },
    initial_code: {
        type: String,
        default: '',
    },
});

const form = useForm({
    code: props.initial_code,
    display_name: props.initial_display_name,
});

const submit = () => {
    form.post('/room/join');
};
</script>

<template>
    <AppLayout container-class="w-full max-w-md">
        <GlassCard class="p-8">
            <h1 class="text-3xl font-bold text-center mb-8 text-white font-display">Join a Room</h1>

            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <label for="code" class="block text-sm font-medium text-white/70 mb-2">Room Code</label>
                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg focus:ring-2 focus:ring-accent-500 focus:border-transparent text-white placeholder-white/35 text-center text-2xl tracking-widest uppercase font-mono-party"
                        placeholder="ABC123"
                        maxlength="8"
                        required
                        autocapitalize="characters"
                        autocomplete="off"
                    />
                    <p v-if="form.errors.code" class="mt-1 text-sm text-red-400">{{ form.errors.code }}</p>
                </div>

                <div>
                    <label for="displayName" class="block text-sm font-medium text-white/70 mb-2">Your Name</label>
                    <input
                        id="displayName"
                        v-model="form.display_name"
                        type="text"
                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg focus:ring-2 focus:ring-accent-500 focus:border-transparent text-white placeholder-white/35"
                        placeholder="Enter your display name"
                        maxlength="30"
                        required
                        autocomplete="nickname"
                    />
                    <p v-if="form.errors.display_name" class="mt-1 text-sm text-red-400">
                        {{ form.errors.display_name }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="btn-interactive btn-primary w-full min-h-[44px] py-4 px-6 text-white font-bold rounded-lg transition disabled:cursor-not-allowed"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Joining…' : 'Join Room' }}
                </button>
            </form>

            <div class="mt-6 text-center">
                <Link href="/room/create" class="text-accent-500 hover:text-accent-600 transition">
                    Or create a new room
                </Link>
            </div>
        </GlassCard>
    </AppLayout>
</template>
