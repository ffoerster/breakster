<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Placement Timing
     |--------------------------------------------------------------------------
     |
     | These values control the placement timing during rounds.
     | placement_delay: Seconds before placement unlocks after round starts
     | placement_duration: Seconds for the placement window after unlock
     |
     */
    'placement_delay' => (int) env('GAME_PLACEMENT_DELAY', 3),
    'placement_duration' => (int) env('GAME_PLACEMENT_DURATION', 120),
];
