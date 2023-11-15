# Laminas config cloud parameters

This Composer library facilitates the use of Laminas config placeholders, enabling the substitution of values with those supplied by cloud services dedicated to variable storage using Symfony ParameterBag.

## How to use

### In a Laminas MVC application
1. Configure the Cloud providers in your configuration:
    
    ```php
    <?php

    use Dvsa\LaminasConfigCloudParameters\Provider\SecretsManager;
    use Dvsa\LaminasConfigCloudParameters\Cast\Boolean;

    return [
        'config' => [
            'providers' => [
                SecretsManager::class => [
                    'example-secret',
                    // ...
                ],
                
                // ...
            ],

            'casts' => [
                'parameter_1' => Boolean::class,

                // ...
            ],
        ],
        // ...
    ];
    ```
1. Register the module with the Laminas ModuleManager:

    ```php
    <?php
    // module.config.php
    
    return [
        'Dvsa\LaminasConfigCloudParameters',
        // ...
    ];
    ```

1. Placeholders can be then added to the Laminas config:

    ```php
    return [
        'foo' => '%bar%', // Will be replaced by a parameter with the key 'bar' from the cloud variable storage service.
    ];
    ```

## Available cloud parameter providers

### AWS
#### Secrets Manager

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

#### Parameter Store

Parameters will be loaded recursively by path. The key will be parameter name without the path provided as the key.

**Example configuration:**

```php 
<?php

use Dvsa\LaminasConfigCloudParameters\Provider\ParameterStore;

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
