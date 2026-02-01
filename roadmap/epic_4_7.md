EPIC: Enhanced reveal phase animations

Goals:
- Make the reveal moment dramatic and satisfying
- Celebrate correct placements
- Create memorable "big miss" moments

Implementation:
1) Card flip animation:
   - 3D CSS flip (rotateY) to reveal actual year
   - Stagger timing: 100ms between each player's result
   - Use perspective for depth effect
2) Year counter:
   - Animate from guessed position year to actual year
   - Counter speed based on delta (bigger miss = faster count)
   - Sound tick optional (if sound enabled)
3) Result feedback:
   - Correct: green glow pulse + confetti burst (small)
   - Close (within tolerance): yellow pulse
   - Wrong: red shake + screen edge flash
   - Big miss (>10 years): dramatic screen shake
4) AMEN reveal:
   - If AMEN claimed: spotlight effect on claimer
   - Success: gold particle explosion + sound
   - Fail: sad trombone visual (wilting effect)
5) Leaderboard entrance:
   - Staggered slide-in (already exists, enhance timing)
   - Position change indicators (+2, -1) with color
   - Winner crown animation

Acceptance:
- Reveal feels like a "moment"
- Animations complete within 3 seconds
- Works smoothly on mid-range phones
- Respects prefers-reduced-motion
