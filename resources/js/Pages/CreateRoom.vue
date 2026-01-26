<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import GlassCard from '../Components/Ui/GlassCard.vue';
import PackCarousel from '../Components/Packs/PackCarousel.vue';

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
                            <PackCarousel v-model="form.pack_id" :packs="packs" />
                            <p v-if="form.errors.pack_id" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.pack_id }}</p>
                        </div>

                        <button
                            type="submit"
                            class="btn-interactive w-full min-h-[44px] py-4 px-6 bg-accent-500 hover:bg-accent-600 text-white font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
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
