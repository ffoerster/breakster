import { useSound } from "@vueuse/sound";
import { ref } from "vue";
import { useSoundSettings } from "./useSoundSettings";

export function useGameSounds() {
    const { isSoundEnabled } = useSoundSettings();

    // Track if user has interacted (audio unlock for mobile)
    const hasUserInteracted = ref(false);

    // Sound instances using @vueuse/sound
    // The soundEnabled option is reactive and respects the global mute setting
    const amenSound = useSound("/audio/amen.mp3", {
        volume: 0.7,
        interrupt: true,
        soundEnabled: isSoundEnabled,
    });

    const clickSound = useSound("/audio/click.wav", {
        volume: 0.5,
        interrupt: true,
        soundEnabled: isSoundEnabled,
    });

    const revealSound = useSound("/audio/reveal.wav", {
        volume: 0.6,
        interrupt: false,
        soundEnabled: isSoundEnabled,
    });

    const correctSound = useSound("/audio/correct.wav", {
        volume: 0.7,
        interrupt: true,
        soundEnabled: isSoundEnabled,
    });

    const wrongSound = useSound("/audio/wrong.wav", {
        volume: 0.6,
        interrupt: true,
        soundEnabled: isSoundEnabled,
    });

    // Unified play function with mobile audio unlock guard
    function playSoundIfUnlocked(soundInstance) {
        if (!hasUserInteracted.value) {
            return;
        }

        soundInstance.play();
    }

    // Exported play functions
    const playAmen = () => playSoundIfUnlocked(amenSound);
    const playClick = () => playSoundIfUnlocked(clickSound);
    const playReveal = () => playSoundIfUnlocked(revealSound);
    const playCorrect = () => playSoundIfUnlocked(correctSound);
    const playWrong = () => playSoundIfUnlocked(wrongSound);

    // Audio unlock handler - call on first user interaction
    function unlockAudio() {
        if (hasUserInteracted.value) {
            return;
        }

        hasUserInteracted.value = true;

        // Resume AudioContext for iOS/Safari
        if (typeof window !== "undefined" && (window.AudioContext || window.webkitAudioContext)) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            const silentContext = new AudioContextClass();

            silentContext
                .resume()
                .then(() => silentContext.close())
                .catch(() => {
                    // Ignore errors
                });
        }
    }

    return {
        // Play functions
        playAmen,
        playClick,
        playReveal,
        playCorrect,
        playWrong,

        // Audio unlock
        unlockAudio,
        hasUserInteracted,

        // Settings (re-exported for convenience)
        isSoundEnabled,
    };
}
