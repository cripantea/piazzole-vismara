<?php

return [
    'amazon' => [
        'aws_bucket_prefix' => env('AWS_BUCKET_PREFIX'),
        'aws_path' => env('AWS_PATH'),
    ],
    'ttd' => [
        'aws_bucket_prefix' => env('AWS_BUCKET_PREFIX'),
        'aws_path' => env('TTD_PATH'),
        'aws_filename' => env('TTD_FILENAME'),
    ]
];
