<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stale call session cleanup (calls:cleanup-stale)
    |--------------------------------------------------------------------------
    |
    | Ringing timeout: unanswered pending/calling sessions. Also used by
    | GET /calls/pending-invite so offline→online recovery stays available
    | for as long as the session can still ring.
    | Empty room grace: ongoing sessions with no LiveKit participants.
    | Max ongoing: safety cap for calls that never received POST /end.
    |
    */

    'ringing_timeout_seconds' => (int) env('CALL_RINGING_TIMEOUT_SECONDS', 90),

    'empty_room_grace_seconds' => (int) env('CALL_EMPTY_ROOM_GRACE_SECONDS', 120),

    'max_ongoing_hours' => (int) env('CALL_MAX_ONGOING_HOURS', 6),

];
