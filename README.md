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

### Impulsus

```php
<?php

namespace App\Controller;

use Koncerto\KoncertoImpulsusController;

class HelloController extends KoncertoImpulsusController
{
}
```

#### Data binding

```php
<?php

namespace App\Controller;

use Koncerto\KoncertoImpulsusController;

class HelloController extends KoncertoImpulsusController
{
    /**
     * @see K::liveProp() {"name": "ip"}
     * @var string
     */
    public $ip;

    public function postMount()
    {
        $this->ip = $_SERVER['REMOTE_ADDR'];
    }
}
```

```html
<span data-model="ip"></span>
```

#### Live actions

```php
    /**
     * @see K::liveProp() {"name": "counter"}
     * @var int
     */
    public $counter = 0;

    /**
     * @param array<string, string> $data
     * @return void
     */
    public function postMount($data = array())
    {
        $this->counter = isset($data['counter']) ? intval($data['counter']) : 0;
    }

    /**
     * @see K::liveAction() {"name": "incrementCounter"}
     * @return KoncertoResponse
     */
    public function incrementCounter()
    {
        $this->counter++;

        return $this->json(array('counter' => $this->counter));
    }
```

```html
    <h2>Counter is <span data-model="counter"></span></h2>
    <button data-action="click->$live#incrementCounter">Increment Counter</button>
```

### ORM

#### Entity

```php
<?php

namespace App\Entity;
use Koncerto\KoncertoAnnotation as K;

/**
 * @see K::entity()
 */
class Task
{
    /** @var int */
    public $id;

    /** @var string */
    public $description;

    /** @var bool */
    public $done = false;
}
```

#### EntityManager

```php
koncerto = new Koncerto(array(
    'entityManager' => array(
        'default' => 'sqlite:///' . __DIR__ . '/db.sqlite'
    )
));
```

```php
$tasks = $this->getEntityManager()->findAll(Task::class);

$task = $this->getEntityManager()->find(Task::class, $id);

$task->done = true;
$this->getEntityManager()->persist(Task::class, $task);

$this->getEntityManager()->remove(Task::class, $task->id);
```

## Recommendations

* Template engine :
    - HereTemplate for simple templates
    - TinyButStrong for complex templates
* UX : Bulma (cdn or npm)
* Live rendering : Impulsus
* ORM : PDO (fallbacks for older databases extensions are in the roadmap)
