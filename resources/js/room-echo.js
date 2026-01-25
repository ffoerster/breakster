/**
 * Room real-time updates via Laravel Echo
 */

// Initialize room channel subscription
document.addEventListener('DOMContentLoaded', () => {
    const roomCodeElement = document.querySelector('[data-room-code]');

    if (!roomCodeElement) {
        return;
    }

    const roomCode = roomCodeElement.dataset.roomCode;

    if (!window.Echo || !roomCode) {
        console.warn('Echo or room code not available');
        return;
    }

    console.log(`Subscribing to room.${roomCode}`);

    // Debounce helper
    let refreshTimeout = null;
    const debounceRefresh = (eventName, data) => {
        clearTimeout(refreshTimeout);
        refreshTimeout = setTimeout(() => {
            window.Livewire.dispatch(eventName, data);
        }, 100);
    };

    // Subscribe to room channel (once)
    const channel = window.Echo.channel(`room.${roomCode}`);

    // Listen for players updated (join/leave)
    channel.listen('.players.updated', (event) => {
        console.log('Players updated:', event);
        debounceRefresh('roomPlayersUpdated', event);
    });

    // Listen for round started
    channel.listen('.round.started', (event) => {
        console.log('Round started:', event);
        window.Livewire.dispatch('roundStarted', event);
        
        // Also dispatch browser event for Alpine
        window.dispatchEvent(new CustomEvent('round-started', {
            detail: event
        }));
    });

    // Listen for placement submitted
    channel.listen('.placement.submitted', (event) => {
        console.log('Placement submitted:', event);
        debounceRefresh('placementSubmitted', event);
    });

    // Listen for round revealed
    channel.listen('.round.revealed', (event) => {
        console.log('Round revealed:', event);
        window.Livewire.dispatch('roundRevealed', event);
    });

    // Listen for room state changes
    channel.listen('.room.state.changed', (event) => {
        console.log('Room state changed:', event);
        window.Livewire.dispatch('roomStateChanged', event);
    });

    // Cleanup on navigation away
    window.addEventListener('beforeunload', () => {
        console.log(`Leaving room.${roomCode}`);
        window.Echo.leave(`room.${roomCode}`);
    });
});
