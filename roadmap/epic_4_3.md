EPIC: Add minimal sound design (client-side only)

Goals:
- Provide tactile feedback without being annoying.
- Respect mute / low volume.
- Works on mobile (requires user interaction before autoplay).

Sounds:
- amen.mp3 already exists
- add:
  - click.mp3 (placing a card)
  - reveal.mp3 (reveal stinger)
  - correct.mp3 / wrong.mp3 (optional; for AMEN result)

Implementation:
1) Add setting in UI:
   - "Sound: On/Off" (stored in localStorage)
   - Default: On
2) Create AudioService in Vue:
   - lazy-load Audio objects
   - play(name, { volume })
   - guard: only play after first user interaction (unlock audio)
3) Trigger points:
   - On successful AMEN claim: play amen sample
   - On successful placement: play click
   - On reveal event received: play reveal
   - On amen.resolved: play correct/wrong (optional)

Acceptance:
- Sounds play reliably after first interaction.
- Mute toggle works and persists.
- No server involvement.
