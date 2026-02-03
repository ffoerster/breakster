EPIC: Strengthen host mode for in-room play

Goals:
- Host has a clear control surface:
  - Start round
  - Reveal
  - Next round
  - (Optional) Retry play (Spotify host playback if enabled)
- Players have a clean “listen + place” view.
- Add a “Ready check” step (optional but recommended):
  - simple “Ready” button for players; host sees count

Implementation (minimum):
1) Host-only control panel in UI
2) Player “Ready” button that appears once track started and before unlock
4) Broadcast ready updates

Acceptance:
- Host can manage flow without chaos.
- Players know what to do.
