<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Device session lifetime
    |--------------------------------------------------------------------------
    |
    | How long a device stays usable (day-to-day PIN unlock) after
    | activation — a separate clock from the one-time activation code's
    | own short deadline (DeviceAssociation::expires_at). Null means the
    | session never expires on its own (still revocable at any time by
    | staff via DeviceAssociationController::revoke()).
    |
    */
    'session_days' => env('DEVICE_SESSION_DAYS', 365),

];
