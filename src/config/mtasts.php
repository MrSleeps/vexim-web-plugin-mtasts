<?php
return [
    'mta_sts_mode' => env('MTA_STS_MODE', 'testing'), // Can either be enforce or testing
    'mta_sts_max_age' => env('MTA_STS_MAX_AGE','86400')
];