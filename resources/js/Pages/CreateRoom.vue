<script setup>
import { Link, useForm } from '@inertiajs/vue3';

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
    <div class="min-h-screen flex flex-col">
        <header class="py-4 px-6">
            <Link href="/" class="text-2xl font-bold text-purple-400 hover:text-purple-300 transition">
                Breakster
            </Link>
        </header>

        <main class="flex-1 flex items-center justify-center px-4 py-8">
            <div class="w-full max-w-md">
                <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
                    <h1 class="text-3xl font-bold text-center mb-8">Create a Room</h1>

                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <label for="displayName" class="block text-sm font-medium text-gray-300 mb-2">Your Name</label>
                            <input
                                id="displayName"
                                v-model="form.display_name"
                                type="text"
                                class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-white placeholder-gray-400"
                                placeholder="Enter your display name"
                                maxlength="30"
                                required
                                autocomplete="nickname"
                            />
                            <p v-if="form.errors.display_name" class="mt-1 text-sm text-red-400">
                                {{ form.errors.display_name }}
                            </p>
                        </div>

                        <div>
                            <div class="block text-sm font-medium text-gray-300 mb-2">Choose a Pack</div>
                            <div class="space-y-2">
                                <label
                                    v-for="pack in packs"
                                    :key="pack.id"
                                    class="flex items-center p-4 rounded-lg cursor-pointer transition-all"
                                    :class="
                                        form.pack_id === pack.id
                                            ? 'bg-purple-600 ring-2 ring-purple-400 ring-offset-2 ring-offset-gray-800'
                                            : 'bg-gray-700/30 hover:bg-gray-700/50'
                                    "
                                >
                                    <input v-model="form.pack_id" type="radio" name="pack" :value="pack.id" class="sr-only" />
                                    <div class="flex-1">
                                        <div class="font-medium" :class="form.pack_id === pack.id ? 'text-white' : ''">
                                            {{ pack.name }}
                                        </div>
                                        <div
                                            class="text-sm"
                                            :class="form.pack_id === pack.id ? 'text-purple-200' : 'text-gray-400'"
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
                            <p v-if="form.errors.pack_id" class="mt-1 text-sm text-red-400">{{ form.errors.pack_id }}</p>
                        </div>

                        <button
                            type="submit"
                            class="w-full py-4 px-6 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Creating…' : 'Create Room' }}
                        </button>
                    </form>

                    <div class="mt-6 text-center">
                        <Link href="/room/join" class="text-purple-400 hover:text-purple-300 transition">
                            Or join an existing room
                        </Link>
                    </div>
                </div>
            </div>
        </main>

        <footer class="py-4 text-center text-gray-500 text-sm">A timeline music game</footer>
    </div>
</template>
