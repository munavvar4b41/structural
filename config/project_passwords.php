<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Argon2id cost
    |--------------------------------------------------------------------------
    |
    | The project passphrase is never stored. These limits are the cost of
    | deriving the key that encrypts project passwords. Production uses
    | libsodium's interactive Argon2id limits. Tests lower them.
    |
    */

    'ops_limit' => (int) env('PROJECT_PASSWORD_PWHASH_OPS', SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE),

    'mem_limit' => (int) env('PROJECT_PASSWORD_PWHASH_MEM', SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE),

];
