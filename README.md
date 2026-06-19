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
