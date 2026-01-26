EPIC: Mobile-first UI improvements for gameplay pages

Goals:
- Controls reachable with thumb (bottom area).
- Large hit targets for all actions (>=44px).
- Responsive layout for:
  - Lobby
  - Round (play + countdown + current card)
  - Reveal
- Avoid hover-only interactions and tiny text.

Implementation:
1) Audit pages:
   - CreateRoom, JoinRoom, Room/Show (Lobby/Round/Reveal)
2) Layout:
   - sticky bottom action bar for primary actions:
     - Play/Open Provider (host)
     - AMEN (if available)
     - Submit/Undo placement
3) Typography:
   - scale year on cards (ties into 4.1)
4) Accessibility:
   - focus styles
   - button disabled states
5) Performance:
   - reduce rerenders, use shallow state updates

Acceptance:
- One-handed use on iPhone-sized screens.
- No overflow issues.
- Tap targets feel comfortable.
