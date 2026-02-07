<script setup>
import { usePreferredReducedMotion } from "@vueuse/core";
import { computed, onMounted, ref } from "vue";
import { useHaptics } from "../../../Composables/useHaptics";

const haptics = useHaptics();

const props = defineProps({
	placement: {
		type: Object,
		required: true,
	},
	actualYear: {
		type: Number,
		required: true,
	},
	index: {
		type: Number,
		required: true,
	},
});

const emit = defineEmits([
	"triggerConfetti",
	"triggerScreenFlash",
	"animationComplete",
]);

const prefersReducedMotion = usePreferredReducedMotion();
const displayedYear = ref(null);
const isAnimating = ref(false);
const showResult = ref(false);
const hasTriggeredFeedback = ref(false);

const guessedYear = computed(() => {
	if (props.placement.inserted_after_year) {
		return parseInt(props.placement.inserted_after_year, 10);
	}
	return props.actualYear;
});

const yearDelta = computed(() =>
	Math.abs(props.actualYear - guessedYear.value),
);
const isBigMiss = computed(
	() => !props.placement.is_correct && yearDelta.value > 10,
);

const animateYearCounter = () => {
	if (prefersReducedMotion.value === "reduce") {
		displayedYear.value = props.actualYear;
		showResult.value = true;
		triggerFeedback();
		return;
	}

	isAnimating.value = true;
	const startYear = guessedYear.value;
	const endYear = props.actualYear;
	const delta = endYear - startYear;

	if (delta === 0) {
		displayedYear.value = endYear;
		isAnimating.value = false;
		showResult.value = true;
		triggerFeedback();
		return;
	}

	const duration = Math.min(800, Math.max(300, Math.abs(delta) * 30));
	const startTime = performance.now();

	const animate = (currentTime) => {
		const elapsed = currentTime - startTime;
		const progress = Math.min(1, elapsed / duration);
		const eased = 1 - (1 - progress) ** 3;

		displayedYear.value = Math.round(startYear + delta * eased);

		if (progress < 1) {
			requestAnimationFrame(animate);
		} else {
			displayedYear.value = endYear;
			isAnimating.value = false;
			showResult.value = true;
			triggerFeedback();
		}
	};

	displayedYear.value = startYear;
	requestAnimationFrame(animate);
};

const triggerFeedback = () => {
	if (hasTriggeredFeedback.value) {
		return;
	}

	hasTriggeredFeedback.value = true;

	if (props.placement.is_correct) {
		emit("triggerConfetti");
		haptics.medium();
	} else if (isBigMiss.value) {
		emit("triggerScreenFlash");
		haptics.error();
	}

	emit("animationComplete");
};

onMounted(() => {
	const baseDelay = 100;
	const delay = props.index * baseDelay;
	setTimeout(animateYearCounter, delay);
});
</script>

<template>
    <div
        class="flex items-center justify-between p-4 rounded-lg border transition-all duration-300"
        :class="[
            placement.is_correct
                ? 'bg-green-500/20 border-green-500'
                : 'bg-red-500/20 border-red-500',
            showResult && placement.is_correct ? 'animate-pulse-glow-green' : '',
            showResult && isBigMiss ? 'animate-shake-dramatic' : ''
        ]"
    >
        <div>
            <div class="font-medium flex items-center gap-2">
                {{ placement.player.display_name }}
                <span
                    v-if="placement.is_auto_placed"
                    class="px-2 py-0.5 text-xs font-medium rounded bg-gray-600 text-gray-300"
                >
                    Auto-placed
                </span>
            </div>
            <div class="text-sm text-gray-400">
                <span v-if="placement.inserted_after_track_id === null">Placed at beginning</span>
                <span v-else>Placed after {{ placement.inserted_after_year ?? 'unknown' }}</span>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div
                class="text-2xl font-black tabular-nums min-w-[4ch] text-right"
                :class="isAnimating ? 'text-gray-300' : (placement.is_correct ? 'text-green-400' : 'text-red-400')"
            >
                {{ displayedYear ?? '----' }}
            </div>

            <div class="min-w-[5rem] text-right">
                <div
                    v-if="showResult && placement.is_correct"
                    class="text-green-400 font-bold animate-scale-pop"
                >
                    +{{ placement.points_earned }} pts
                </div>
                <div v-else-if="showResult" class="text-red-400">
                    0 pts
                </div>
            </div>
        </div>
    </div>
</template>
