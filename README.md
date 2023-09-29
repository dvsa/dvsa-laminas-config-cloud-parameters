# Laminas config aggregator cloud parameters

## How to use

### In a Laminas MVC application
1. Configure the Cloud providers in your configuration
    For example, to use AWS Secrets Manager and AWS Parameter Store, configuration would look like this:
    
    ```php
    <?php
        'config' => [
            'providers' => [
                FQCN::class => [
                    // Ids to retreive from the parameter provider.
                ],
                
                // ...
            ],
        ],
    ```
1. Add this library to your Laminas module list:

    ```php
    <?php
    // module.config.php
    
    return [
        'Laminas\Router',
        'Dvsa\LaminasConfigCloudParameters',
    ];
    ```

## Available cloud parameter providers

### AWS
### Secrets Manager

Only secrets that are stored in key/value pairs are supported.

**Example configuration:**

```php 
<?php

return [
    'config' => [
        'providers' => [
            SecretsManager::class => [
                'global-secrets',
                sprintf('environment-%s-secrets', $environment),
            ],
            
            // ...
        ],
    ],
];
```

### Parameter Store

Parameters will be loaded recursively by path. The key will be parameter name without the path provided as the key.

**Example configuration:**

```php 
<?php

return [
    'config' => [
        'providers' => [
            ParameterStore::class => [
                '/global',
                sprintf('/env-%s', $environment),
            ],
            
            // ...
        ],
    ],
];
```
