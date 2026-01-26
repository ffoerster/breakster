EPIC: Add difficulty settings per room (tolerance & scoring rules)

Goal:
- Host selects difficulty in lobby before game start.
- Difficulty affects scoring/validation.

Suggested modes:
- EASY: year tolerance ±1 counts as correct
- STRICT: exact year only
- HARDCORE: exact year + optional label bonus (future-proof)

Data:
- rooms.difficulty (enum/string: easy|strict|hardcore) default strict

Backend:
1) Enforce immutability after start (like playback provider).
2) In scoring logic:
   - compute year correctness based on tolerance
   - persist awarded points on each round result

Frontend:
- Lobby selector (host only)
- Show difficulty to all players

Acceptance:
- Difficulty visible and applied.
- Tests cover tolerance boundaries (year-1, year, year+1).
