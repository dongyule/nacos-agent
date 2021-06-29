<?php

return [
    'enable' => true,
    'host' => '172.17.0.115',
    'port' => '8848',
    // The service info.
    'service' => [
        'service_name' => 'test.php',
        'group_name' => 'api',
        'namespace_id' => 'public',
        'protect_threshold' => 0.5,
    ],
    // The client info.
    'client' => [
        'service_name' => 'test.php',
        'group_name' => 'api',
        'weight' => 80,
        'cluster' => 'DEFAULT',
        'ephemeral' => true,
        'beat_enable' => true,
        'beat_interval' => 5,
        'namespace_id' => 'public', // It must be equal with service.namespaceId.
    ],
    'config_reload_interval' => 3,
    'listener_config' => [
        // dataId, group, tenant, type, content
        //[
        //    'tenant' => 'tenant', // corresponding with service.namespaceId
        //    'data_id' => 'hyperf-service-config',
        //    'group' => 'DEFAULT_GROUP',
        //],
        //[
        //    'data_id' => 'hyperf-service-config-yml',
        //    'group' => 'DEFAULT_GROUP',
        //    'type' => 'yml',
        //],
    ],
];