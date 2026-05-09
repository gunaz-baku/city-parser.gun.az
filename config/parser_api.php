<?php

return [

    'rate_limit_per_minute' => (int) env('PARSER_API_RATE_LIMIT', 180),

    /** HTTP Basic üçün e-poçt (users.email; parser:ensure-basic-user). */
    'basic_username' => env('PARSER_BASIC_USERNAME'),
];
