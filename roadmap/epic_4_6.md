EPIC: Haptic feedback & touch gesture enhancements

Goals:
- Make mobile interactions feel native
- Add satisfying tactile feedback
- Improve gesture-based navigation

Implementation:
1) Haptic feedback service:
   - Wrap navigator.vibrate() with patterns:
     - light: 10ms (UI tap)
     - medium: 25ms (placement)
     - heavy: [50, 30, 50] (reveal/AMEN)
     - error: [100, 50, 100] (wrong)
   - Check navigator.vibrate support
   - Respect OS haptic settings via prefers-reduced-motion
2) Trigger points:
   - Card pickup: light
   - Card drop (success): medium
   - AMEN button press: heavy
   - Reveal moment: heavy
   - Correct placement: medium
   - Wrong placement: error pattern
3) Gesture enhancements:
   - Swipe left/right on timeline for horizontal scroll
   - Long-press on any card for detail modal
   - Pull-down on room view for manual state refresh
   - Use @vueuse/gesture for gesture detection
4) Settings:
   - Add "Haptics: On/Off" toggle (localStorage)
   - Group with existing sound toggle

Acceptance:
- Haptics work on Android and iOS (where supported)
- Gestures feel natural and don't conflict with drag-drop
- Settings persist across sessions
