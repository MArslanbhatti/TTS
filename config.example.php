<?php
return [
 'azure_key' => 'REPLACE_WITH_AZURE_SPEECH_RESOURCE_KEY',
 'azure_region' => 'eastus', // replace with the exact region of your Speech resource
 'daily_requests' => 10,
 'daily_characters' => 5000,
 'max_chars_per_request' => 1000,
 'storage_dir' => __DIR__ . '/usage',
 'secret' => 'REPLACE_WITH_A_LONG_RANDOM_SECRET',
];
