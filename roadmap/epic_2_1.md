EPIC: Strengthen host mode for in-room play

Goals:
- Host has a clear control surface:
  - Start round
  - Reveal
  - Next round
  - (Optional) Retry play (Spotify host playback if enabled)
- Players have a clean “listen + place” view.
- Add a “Ready check” step (optional but recommended):
  - after track start, host can hit “Everyone ready?” and see confirmations
  - OR a simple “Ready” button for players; host sees count

Implementation (minimum):
1) Host-only control panel in UI
2) Player “Ready” button that appears once track started and before unlock
3) State:
   - round_ready_confirmations table OR store on round JSON
   - simplest: round_ready_count + per-player flag in DB
4) Broadcast ready updates

Acceptance:
- Host can manage flow without chaos.
- Players know what to do.
