<script setup>
import { reactive, watch } from "vue";

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
    onUpdate: {
        type: Function,
        required: true,
    },
    isUpdating: {
        type: Boolean,
        default: false,
    },
});

const local = reactive({
    placement_timer_enabled: props.settings.placement_timer_enabled ?? false,
    placement_timer_duration: props.settings.placement_timer_duration ?? 120,
    placement_delay_seconds: props.settings.placement_delay_seconds ?? 3,
    max_rounds: props.settings.max_rounds,
    amen_enabled: props.settings.amen_enabled ?? true,
});

const maxRoundsUnlimited = () => local.max_rounds === null || local.max_rounds === undefined;

watch(
    () => props.settings,
    (s) => {
        local.placement_timer_enabled = s.placement_timer_enabled ?? false;
        local.placement_timer_duration = s.placement_timer_duration ?? 120;
        local.placement_delay_seconds = s.placement_delay_seconds ?? 3;
        local.max_rounds = s.max_rounds;
        local.amen_enabled = s.amen_enabled ?? true;
    },
);

const send = (overrides = {}) => {
    props.onUpdate({ ...local, ...overrides });
};

const toggleTimer = () => {
    local.placement_timer_enabled = !local.placement_timer_enabled;
    send();
};

const setTimerDuration = (value) => {
    const clamped = Math.min(300, Math.max(10, parseInt(value, 10) || 120));
    local.placement_timer_duration = clamped;
    send();
};

const setDelay = (value) => {
    const clamped = Math.min(30, Math.max(1, parseInt(value, 10) || 3));
    local.placement_delay_seconds = clamped;
    send();
};

const stepDelay = (delta) => {
    setDelay(local.placement_delay_seconds + delta);
};

const toggleMaxRounds = () => {
    if (maxRoundsUnlimited()) {
        local.max_rounds = 10;
    } else {
        local.max_rounds = null;
    }
    send();
};

const setMaxRounds = (value) => {
    const parsed = parseInt(value, 10);
    if (!parsed || parsed < 1) {
        local.max_rounds = null;
    } else {
        local.max_rounds = Math.min(100, parsed);
    }
    send();
};

const toggleAmen = () => {
    local.amen_enabled = !local.amen_enabled;
    send();
};
</script>

<template>
    <div class="space-y-5">
        <h3 class="text-sm font-medium text-white/70 font-display">Game Settings</h3>

        <!-- Placement Timer Toggle -->
        <div class="space-y-3">
            <label class="flex items-center justify-between gap-3 cursor-pointer">
                <div>
                    <span class="text-sm text-white/90">Round timer</span>
                    <p class="text-xs text-white/50 mt-0.5">Limit time for placing guesses</p>
                </div>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="local.placement_timer_enabled"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 focus:ring-offset-gray-950"
                    :class="local.placement_timer_enabled ? 'bg-accent-500' : 'bg-white/10'"
                    :disabled="isUpdating"
                    @click="toggleTimer"
                >
                    <span
                        :class="local.placement_timer_enabled ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    />
                </button>
            </label>

            <!-- Timer Duration (only when timer enabled) -->
            <div v-if="local.placement_timer_enabled" class="pl-1 space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-white/70">Duration</span>
                    <span class="font-mono-party text-accent-200"
                        >{{ local.placement_timer_duration }}s</span
                    >
                </div>
                <input
                    type="range"
                    :value="local.placement_timer_duration"
                    min="10"
                    max="300"
                    step="10"
                    class="w-full h-1.5 rounded-full appearance-none cursor-pointer bg-white/10 accent-accent-500"
                    :disabled="isUpdating"
                    @change="setTimerDuration($event.target.value)"
                />
                <div class="flex justify-between text-[10px] text-white/40">
                    <span>10s</span>
                    <span>300s</span>
                </div>
            </div>
        </div>

        <!-- Listening Delay -->
        <div>
            <label class="flex items-center justify-between gap-3">
                <div>
                    <span class="text-sm text-white/90">Listening delay</span>
                    <p class="text-xs text-white/50 mt-0.5">Seconds before placement unlocks</p>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="w-8 h-8 rounded-lg bg-white/10 text-white/70 font-bold text-lg flex items-center justify-center hover:bg-white/20 transition disabled:opacity-30"
                        :disabled="isUpdating || local.placement_delay_seconds <= 1"
                        @click="stepDelay(-1)"
                    >
                        &minus;
                    </button>
                    <span class="w-10 text-center font-mono-party text-accent-200 text-sm"
                        >{{ local.placement_delay_seconds }}s</span
                    >
                    <button
                        type="button"
                        class="w-8 h-8 rounded-lg bg-white/10 text-white/70 font-bold text-lg flex items-center justify-center hover:bg-white/20 transition disabled:opacity-30"
                        :disabled="isUpdating || local.placement_delay_seconds >= 30"
                        @click="stepDelay(1)"
                    >
                        +
                    </button>
                </div>
            </label>
        </div>

        <!-- Max Rounds -->
        <div class="space-y-3">
            <label class="flex items-center justify-between gap-3 cursor-pointer">
                <div>
                    <span class="text-sm text-white/90">Round limit</span>
                    <p class="text-xs text-white/50 mt-0.5">Max number of rounds to play</p>
                </div>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="!maxRoundsUnlimited()"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 focus:ring-offset-gray-950"
                    :class="!maxRoundsUnlimited() ? 'bg-accent-500' : 'bg-white/10'"
                    :disabled="isUpdating"
                    @click="toggleMaxRounds"
                >
                    <span
                        :class="!maxRoundsUnlimited() ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    />
                </button>
            </label>

            <div v-if="!maxRoundsUnlimited()" class="pl-1">
                <div class="flex items-center gap-2">
                    <input
                        type="number"
                        :value="local.max_rounds"
                        min="1"
                        max="100"
                        class="w-20 bg-white/10 border border-white/20 rounded-lg px-3 py-1.5 text-sm text-white font-mono-party text-center focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent"
                        :disabled="isUpdating"
                        @change="setMaxRounds($event.target.value)"
                    />
                    <span class="text-sm text-white/50">rounds</span>
                </div>
            </div>
        </div>

        <!-- AMEN Feature Toggle -->
        <label class="flex items-center justify-between gap-3 cursor-pointer">
            <div>
                <span class="text-sm text-white/90">AMEN</span>
                <p class="text-xs text-white/50 mt-0.5">Allow players to guess the track</p>
            </div>
            <button
                type="button"
                role="switch"
                :aria-checked="local.amen_enabled"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 focus:ring-offset-gray-950"
                :class="local.amen_enabled ? 'bg-accent-500' : 'bg-white/10'"
                :disabled="isUpdating"
                @click="toggleAmen"
            >
                <span
                    :class="local.amen_enabled ? 'translate-x-5' : 'translate-x-0'"
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                />
            </button>
        </label>
    </div>
</template>
