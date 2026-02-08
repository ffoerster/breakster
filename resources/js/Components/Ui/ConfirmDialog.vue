<script setup>
import BottomSheet from "./BottomSheet.vue";

defineProps({
	open: {
		type: Boolean,
		required: true,
	},
	title: {
		type: String,
		required: true,
	},
	message: {
		type: String,
		required: true,
	},
	confirmLabel: {
		type: String,
		default: "Confirm",
	},
	cancelLabel: {
		type: String,
		default: "Cancel",
	},
	danger: {
		type: Boolean,
		default: false,
	},
	isConfirming: {
		type: Boolean,
		default: false,
	},
	error: {
		type: String,
		default: "",
	},
});

const emit = defineEmits(["confirm", "cancel"]);

const confirm = () => {
	emit("confirm");
};

const cancel = () => {
	emit("cancel");
};
</script>

<template>
    <BottomSheet
        :open="open"
        :title="title"
        :show-close-button="false"
        :allow-drag-to-close="false"
        max-width-class="max-w-xl"
        @close="cancel"
    >
        <div class="space-y-6">
            <p class="text-sm text-white/80 leading-relaxed">
                {{ message }}
            </p>

            <div v-if="error" class="rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                {{ error }}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button
                    type="button"
                    class="btn-interactive btn-secondary min-h-11 px-4 py-3 rounded-xl font-bold text-white"
                    :disabled="isConfirming"
                    @click="cancel"
                >
                    {{ cancelLabel }}
                </button>

                <button
                    type="button"
                    class="btn-interactive min-h-11 px-4 py-3 rounded-xl font-bold text-white disabled:opacity-60"
                    :class="danger ? 'btn-danger' : 'btn-primary'"
                    :disabled="isConfirming"
                    @click="confirm"
                >
                    <span v-if="isConfirming">Working...</span>
                    <span v-else>{{ confirmLabel }}</span>
                </button>
            </div>
        </div>
    </BottomSheet>
</template>
