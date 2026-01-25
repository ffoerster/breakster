<script setup>
import { Link, useForm } from '@inertiajs/vue3';

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
    <div class="min-h-screen flex flex-col">
        <header class="py-4 px-6">
            <Link href="/" class="text-2xl font-bold text-purple-400 hover:text-purple-300 transition">
                Breakster
            </Link>
        </header>

        <main class="flex-1 flex items-center justify-center px-4 py-8">
            <div class="w-full max-w-md">
                <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
                    <h1 class="text-3xl font-bold text-center mb-8">Join a Room</h1>

                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-300 mb-2">Room Code</label>
                            <input
                                id="code"
                                v-model="form.code"
                                type="text"
                                class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-white placeholder-gray-400 text-center text-2xl tracking-widest uppercase"
                                placeholder="ABC123"
                                maxlength="8"
                                required
                                autocapitalize="characters"
                                autocomplete="off"
                            />
                            <p v-if="form.errors.code" class="mt-1 text-sm text-red-400">{{ form.errors.code }}</p>
                        </div>

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

                        <button
                            type="submit"
                            class="w-full py-4 px-6 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Joining…' : 'Join Room' }}
                        </button>
                    </form>

                    <div class="mt-6 text-center">
                        <Link href="/room/create" class="text-purple-400 hover:text-purple-300 transition">
                            Or create a new room
                        </Link>
                    </div>
                </div>
            </div>
        </main>

        <footer class="py-4 text-center text-gray-500 text-sm">A timeline music game</footer>
    </div>
</template>
