EPIC: Enhanced drag-drop feedback & animations

Goals:
- Make placement feel tactile and satisfying
- Provide clear visual feedback during drag
- Reduce placement errors on mobile

Implementation:
1) Magnetic snap effect:
   - When dragging within 40px of drop zone, animate card toward slot
   - Use @vueuse/motion spring physics for smooth attraction
2) Ghost preview:
   - Show semi-transparent card in target slot while dragging
   - Update ghost position in real-time as drag moves between zones
3) Drag start feedback:
   - Scale card to 1.05x on pickup
   - Add subtle rotation (+-2deg) based on drag direction
   - Reduce opacity of original slot
4) Drop animations:
   - Success: pulse scale 1.0 -> 1.1 -> 1.0 with subtle glow
   - Error: shake animation + haptic (if enabled)
5) CSS transitions:
   - Use will-change: transform for smooth 60fps
   - Add touch-action: none to prevent scroll interference

Acceptance:
- Drag feels responsive on both desktop and mobile
- Drop zones are visually obvious during drag
- No jank or frame drops during animation
