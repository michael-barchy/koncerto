# Koncerto

Koncerto is a lightweight PHP framework.

## Install

The engine can be installed using composer or just by loading the class.

### Using composer

```shell
composer config repositories.koncerto '{"type": "vcs", "no-api": true, "url": "https://github.com/michael-barchy/koncerto"}'
composer require michael-barchy/koncerto:dev-main
```

### Without composer

```shell
git submodule add https://github.com/michael-barchy/koncerto
```

```shell
git submodule update --remote
```

## Usage

### Loading using composer

```php
<?php

require_once('vendor/autoload.php');
```

### Loading without composer
```php
<?php

require_once('koncerto/koncerto.php');
```

## Using koncerto

The configuration argument supports:

* PHP array
* Configuration file location (supports .json, .ini and .php)

```php
<?php

use Koncerto\Koncerto;

$koncerto = new Koncerto(array(
    'documentRoot' => __DIR__,
    'autoload' => array(
        'App\\' => './src'
    )
));

echo $koncerto->response();
```

### Controllers

Controllers are specific classes that responds to a route mapping.

Koncerto uses standard PHPDoc annotations to define routes. No other configuration file is required.

```php
<?php

namespace App\Controller;

use Koncerto\KoncertoResponse;
use Koncerto\KoncertoController;
use Koncerto\KoncertoAnnotation as K;

class HelloController extends KoncertoController
{
    /**
     * @see K::route() {"name": "/"}
     * @return KoncertoResponse
     */
    public function index()
    {
        $response = new KoncertoResponse();
        return $response->setContent('Hello');
    }
}
```

### Template engines

Koncerto can use template engines to render the controllers result. The class comes with pre-configured template engines such as HereTemplate and TinyButStrong. Those engines must be installed in the project. Koncerto only provides wrappers for each engine. Autoload must be configured according to the engine install location.

```php
<?php

use Koncerto\Koncerto;

$koncerto = new Koncerto(array(
    'documentRoot' => __DIR__,
    'templateEngine' => 'Koncerto\\KoncertoHereTemplate',
    'autoload' => array(
        'App\\' => './src',
        'HereTemplate\\' => './here-template'
    )
));

echo $koncerto->response();
```

