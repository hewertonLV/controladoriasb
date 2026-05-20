<?php

return [

    'avatar' => [
        'max_kb' => (int) env('PROFILE_AVATAR_MAX_KB', 2048),
        'output_size' => (int) env('PROFILE_AVATAR_OUTPUT_SIZE', 512),
        'max_quality' => (float) env('PROFILE_AVATAR_MAX_QUALITY', 0.92),
        'min_quality' => (float) env('PROFILE_AVATAR_MIN_QUALITY', 0.5),
    ],

];
