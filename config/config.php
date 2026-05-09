<?php

declare(strict_types=1);

return [
    'app' => [
        'env' => getenv('APP_ENV') ?: 'production',
        'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
        'api_token' => getenv('APP_API_TOKEN') ?: '',
    ],

    'tencent_cloud' => [
        'secret_id' => getenv('TENCENTCLOUD_SECRET_ID') ?: '',
        'secret_key' => getenv('TENCENTCLOUD_SECRET_KEY') ?: '',
        'region' => getenv('TENCENTCLOUD_REGION') ?: 'ap-guangzhou',
        'endpoint' => getenv('TENCENTCLOUD_IOT_ENDPOINT') ?: 'iotexplorer.tencentcloudapi.com',
        'service' => 'iotexplorer',
        'version' => '2019-04-23',
    ],

    'storage' => [
        'pump_file' => dirname(__DIR__) . '/storage/pumps.json',
    ],

    'pump_defaults' => [
        'product_id' => getenv('DEFAULT_PRODUCT_ID') ?: '',
        'device_name' => getenv('DEFAULT_DEVICE_NAME') ?: '',
        'max_frequency_hz' => 400,
    ],

    /*
     * Adjust these identifiers to match the IoT Explorer thing model of the
     * actual "Dingfeng permanent-magnet high-speed deep-well pump" product.
     */
    'thing_model' => [
        'power_switch' => 'power_switch',
        'target_frequency' => 'target_frequency',
        'work_mode' => 'work_mode',
        'fault_reset' => 'fault_reset',
    ],
];
