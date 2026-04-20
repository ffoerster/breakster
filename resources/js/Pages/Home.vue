<script setup>
import { Link } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import GlassCard from "../Components/Ui/GlassCard.vue";

const howTo = [
    {
        step: "1",
        title: "Listen",
        description: "Hear a mystery track — no title, artist, or year revealed.",
    },
    {
        step: "2",
        title: "Place",
        description: "Drag it into your timeline in chronological order.",
    },
    {
        step: "3",
        title: "Score",
        description: "Earn points for correct placements — faster guesses score more.",
    },
];
</script>

<template>
    <AppLayout
        container-class="w-full max-w-5xl"
        main-class="flex-1 px-4 py-10"
        :show-footer="true"
    >
        <div class="space-y-10">
            <div class="text-center">
                <div
                    class="inline-flex items-center justify-center w-20 h-20 rounded-2xl border border-white/10 bg-white/5 shadow-2xl"
                >
                    <svg
                        class="w-10 h-10 text-accent-300"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"
                        />
                    </svg>
                </div>

                <h1 class="mt-6 text-5xl sm:text-7xl font-display text-white">Breakster</h1>
                <p class="mt-4 text-lg sm:text-xl text-white/75 max-w-2xl mx-auto">
                    A timeline music game for DnB nerds, DJs, and crate diggers.
                </p>

                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center max-w-md mx-auto">
                    <Link
                        href="/room/create"
                        class="btn-interactive btn-primary flex-1 min-h-[48px] px-6 py-4 rounded-xl font-bold text-center shadow-lg shadow-black/20"
                    >
                        Create Room
                    </Link>
                    <Link
                        href="/room/join"
                        class="btn-interactive btn-secondary flex-1 min-h-[48px] px-6 py-4 rounded-xl font-bold text-center"
                    >
                        Join Room
                    </Link>
                </div>
            </div>

            <GlassCard class="p-8">
                <h2 class="text-sm font-display text-white/70 tracking-widest text-center">
                    HOW TO PLAY
                </h2>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div
                        v-for="card in howTo"
                        :key="card.step"
                        class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center"
                    >
                        <div
                            class="w-12 h-12 rounded-full mx-auto mb-4 border border-white/10 bg-accent-500/10 flex items-center justify-center"
                        >
                            <span class="text-2xl font-display text-accent-300">{{
                                card.step
                            }}</span>
                        </div>
                        <div class="text-lg font-bold text-white">{{ card.title }}</div>
                        <p class="mt-2 text-sm text-white/70">
                            {{ card.description }}
                        </p>
                    </div>
                </div>
            </GlassCard>

            <div class="text-center text-sm text-white/55">
                Tip: add it to your home screen for the full party experience.
            </div>
        </div>
    </AppLayout>
</template>
