<?php

return [
    /*
    | The one account the roles:bootstrap-owner command may ever promote.
    | Deliberately NOT env-driven: changing the initial owner is a code change.
    */
    'bootstrap_owner_email' => 'denusklo@gmail.com',

    // Seconds a mutation waits for the cross-instance DB lock before failing closed.
    'lock_wait_seconds' => 5,

    // Name of the dedicated lock-holding connection (cloned from the default
    // connection's config at runtime; credentials are never logged).
    'lock_connection' => null, // null => 'role_lock'

    // TEST ONLY. Allows (a) drivers without row locks (sqlite) and (b) using the
    // default connection as the lock connection. Gives NO mutual exclusion.
    'lock_unsafe_test_mode' => false,
];
