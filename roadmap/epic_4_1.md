EPIC: Improve timeline drag & drop placement UX

Goals:
- Only the "current card" is draggable.
- Timeline cards are static (not draggable).
- Drop zones are obvious and usable on mobile.
- Placement feels deterministic and snappy.
- Add “Undo” before lock (if placement not finalized yet).

Implementation:
1) Vue: Use @sortablejs/vue.draggable.next OR native pointer-based drag.
2) Render explicit drop slots:
   - Start slot, between each card, end slot.
3) CurrentCard:
   - draggable ghost/clone
   - large hit area
   - “Drag me” hint
4) On drop:
   - call POST /room/{code}/place with inserted_after_track_id|null
   - optimistic UI: show pending placement, disable further dragging
   - if server rejects, revert + toast
5) Add “Undo placement” (until placement window ends):
   - POST /room/{code}/place/undo OR reuse /place with null state
   - or store placement as draft until lock moment
6) Edge cases:
   - mobile scroll vs drag (use press delay)
   - prevent accidental taps (drag threshold)

Acceptance:
- On phone: drag and drop works reliably.
- No DOM glitches, no lost drag state on websocket updates.
- Placement is obvious and fast.
Tests:
- minimal frontend unit test (optional)
- backend: placement endpoint accepts correct afterTrackId.
