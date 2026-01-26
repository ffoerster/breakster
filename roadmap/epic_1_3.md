EPIC: Add placement timer and auto-placement on timeout

Goal:
- After round starts, placement unlocks at placement_unlocks_at.
- Then players have N seconds to place (e.g., 12s).
- If time expires without placement:
  - auto-place at end (or a defined default)
  - player cannot change after timeout

Data:
- rounds.placement_ends_at (timestamp)
- optional: rounds.auto_placed_count (metrics)

Backend:
1) When starting round:
   - set placement_unlocks_at and placement_ends_at = unlock + duration
2) Add scheduled resolver:
   - Option A: queue delayed job at placement_ends_at to auto-place missing players
   - Option B: host reveal triggers auto-place first if missing
   (Prefer A for fairness, but B is simpler)
3) Auto-placement rule:
   - place at end of current timeline (insert_after = last track id or null if empty)
4) Broadcast event when auto-placement happens so UI updates.

Frontend:
- Countdown display “Time left: …”
- When time reaches 0:
  - lock UI
  - show “Auto-placed” badge if applicable

Acceptance:
- Players who do nothing still get a placement.
- No one can place after placement_ends_at.
