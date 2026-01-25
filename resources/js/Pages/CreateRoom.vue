<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import GlassCard from '../Components/Ui/GlassCard.vue';

const props = defineProps({
    initial_display_name: {
        type: String,
        default: '',
    },
    packs: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    display_name: props.initial_display_name,
    pack_id: null,
});

const submit = () => {
    form.post('/room/create');
};
</script>

<template>
    <AppLayout container-class="w-full max-w-md">
        <GlassCard class="p-8">
            <h1 class="text-3xl font-bold text-center mb-8 text-gray-900 dark:text-gray-50">Create a Room</h1>

            <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <label for="displayName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Your Name</label>
                            <input
                                id="displayName"
                                v-model="form.display_name"
                                type="text"
                                class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-accent-500 focus:border-transparent text-gray-900 dark:text-gray-50 placeholder-gray-400 dark:placeholder-gray-500"
                                placeholder="Enter your display name"
                                maxlength="30"
                                required
                                autocomplete="nickname"
                            />
                            <p v-if="form.errors.display_name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.display_name }}
                            </p>
                        </div>

                        <div>
                            <div class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Choose a Pack</div>
                            <div class="space-y-2">
                                <label
                                    v-for="pack in packs"
                                    :key="pack.id"
                                    class="flex items-center p-4 rounded-lg cursor-pointer transition-all"
                                    :class="
                                        form.pack_id === pack.id
                                            ? 'bg-accent-500 ring-2 ring-accent-400 ring-offset-2 ring-offset-gray-50 dark:ring-offset-gray-950'
                                            : 'bg-gray-100 dark:bg-gray-800/30 hover:bg-gray-200 dark:hover:bg-gray-800/50 border border-gray-200 dark:border-gray-700'
                                    "
                                >
                                    <input v-model="form.pack_id" type="radio" name="pack" :value="pack.id" class="sr-only" />
                                    <div class="flex-1">
                                        <div class="font-medium" :class="form.pack_id === pack.id ? 'text-white' : 'text-gray-900 dark:text-gray-50'">
                                            {{ pack.name }}
                                        </div>
                                        <div
                                            class="text-sm"
                                            :class="form.pack_id === pack.id ? 'text-accent-100' : 'text-gray-600 dark:text-gray-400'"
                                        >
                                            {{ pack.tracks_count }} tracks
                                            <span v-if="(pack.spotify_tracks_count ?? 0) > 0 || (pack.tidal_tracks_count ?? 0) > 0">
                                                <span class="mx-1">•</span>
                                                <span v-if="(pack.spotify_tracks_count ?? 0) > 0">{{ pack.spotify_tracks_count }} Spotify</span>
                                                <span
                                                    v-if="(pack.spotify_tracks_count ?? 0) > 0 && (pack.tidal_tracks_count ?? 0) > 0"
                                                    class="mx-1"
                                                >
                                                    •
                                                </span>
                                                <span v-if="(pack.tidal_tracks_count ?? 0) > 0">{{ pack.tidal_tracks_count }} Tidal</span>
                                            </span>
                                        </div>
                                    </div>
                                    <svg
                                        v-if="form.pack_id === pack.id"
                                        class="w-6 h-6 text-white"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </label>
                            </div>
                            <p v-if="form.errors.pack_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.pack_id }}</p>
                        </div>

                        <button
                            type="submit"
                            class="w-full py-4 px-6 bg-accent-500 hover:bg-accent-600 text-white font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Creating…' : 'Create Room' }}
                        </button>

            </form>

            <div class="mt-6 text-center">
                <Link href="/room/join" class="text-accent-500 hover:text-accent-600 transition">
                    Or join an existing room
                </Link>
            </div>
        </GlassCard>
    </AppLayout>
</template>
