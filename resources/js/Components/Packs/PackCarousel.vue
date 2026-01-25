<script setup>
import { Swiper, SwiperSlide } from 'swiper/vue';
import { EffectCoverflow, Keyboard, Navigation } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/effect-coverflow';
import 'swiper/css/navigation';

const props = defineProps({
  packs: { type: Array, required: true },
  modelValue: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:modelValue']);

const modules = [EffectCoverflow, Keyboard, Navigation];

const select = (id) => emit('update:modelValue', id);

const onSlideChange = (swiper) => {
  const index = swiper.activeIndex;
  const pack = props.packs[index];
  if (pack) {
    emit('update:modelValue', pack.id);
  }
};

const onSwiperReady = (swiper) => {
  const index = swiper.activeIndex;
  const pack = props.packs[index];
  if (pack && !props.modelValue) {
    emit('update:modelValue', pack.id);
  }
};
</script>

<template>
  <div class="w-full overflow-hidden">
    <Swiper
      :modules="modules"
      effect="coverflow"
      :grab-cursor="true"
      :centered-slides="true"
      :slides-per-view="'auto'"
      :keyboard="{ enabled: true }"
      :coverflow-effect="{ rotate: 0, stretch: 0, depth: 120, modifier: 1, slideShadows: false }"
      navigation
      class="overflow-visible!"
      @swiper="onSwiperReady"
      @slideChange="onSlideChange"
    >
      <SwiperSlide
        v-for="pack in packs"
        :key="pack.id"
        class="w-56! sm:w-64! md:w-72! lg:w-80!"
      >
        <button
          type="button"
          class="group relative block aspect-3/4 w-full overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 shadow-sm"
          :aria-pressed="modelValue === pack.id"
          @click="select(pack.id)"
        >
          <img :src="pack.image_url" :alt="pack.name" class="h-full w-full object-cover" />

          <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/70 to-transparent p-3">
            <div class="text-white font-semibold truncate">{{ pack.name }}</div>
            <div class="text-white/80 text-xs truncate">
              {{ pack.tracks_count }} tracks
              <span v-if="(pack.spotify_tracks_count ?? 0) > 0 || (pack.tidal_tracks_count ?? 0) > 0">
                <span class="mx-1">•</span>
                <span v-if="(pack.spotify_tracks_count ?? 0) > 0">{{ pack.spotify_tracks_count }} Spotify</span>
                <span v-if="(pack.spotify_tracks_count ?? 0) > 0 && (pack.tidal_tracks_count ?? 0) > 0" class="mx-1">•</span>
                <span v-if="(pack.tidal_tracks_count ?? 0) > 0">{{ pack.tidal_tracks_count }} Tidal</span>
              </span>
            </div>
          </div>

          <div
            class="absolute inset-0 ring-2 ring-accent-400 ring-offset-2 ring-offset-gray-50 dark:ring-offset-gray-950 pointer-events-none"
            v-if="modelValue === pack.id"
          />
        </button>
      </SwiperSlide>
    </Swiper>
  </div>
  <div class="sr-only" aria-live="polite">Selected pack ID: {{ modelValue ?? 'none' }}</div>
</template>
