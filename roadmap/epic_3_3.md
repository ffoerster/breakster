EPIC: End-of-game summary screen with stats

Goals:
- After last round, show summary:
  - final scoreboard
  - most accurate player (avg year error)
  - biggest miss (max year delta)
  - AMEN stats:
    - who claimed how often
    - confirmed vs false
    - points gained/lost
  - "Tune of the night" vote (optional later)

Backend:
1) Compute summary from persisted round results (do not rely on client).
2) Expose endpoint:
   GET /room/{code}/summary
3) Persist minimal metrics per round:
   - player placement delta (abs(year_guess - year_actual))
   - amen_awarded_points
   - auto_placed flag

Frontend:
- Summary page with:
  - cards + shareable layout
  - copy-to-clipboard share text (optional)

Acceptance:
- Summary renders reliably even after refresh.
- Stats match the game history.
