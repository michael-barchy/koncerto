# Koncerto framework

Koncerto is a Tiny Php Framework.

Its concept sits with TinyButStrong template engine: a whole framework in a single script file.

## Framework concepts

Koncerto shares many concepts with frameworks like Symfony, including :

* Request
* Response
* Routing
* Controllers
* Templates

## Routing

Routing in Koncerto is done using @phpdoc comments. The syntax is really simple.

```@internal {"route":{"name":"/"}}```

* `route` : indicates that the method is a route method, it should return a `KoncertoResponse`
* `name` : indicates the path to the route (aka `PATH_INFO`)

## Controller

A controller is a simple PHP class located inside `_controller` directory.

It extends `KoncertoController` that includes helper functions such as `render`.

## Templates

Rendering a template completes the MVP using TinyButStrong template engine.

Templates are located inside `_templates` folder.

# Testing

You can test Koncerto locally using the PHP Web server.

```./bin/koncerto serve```

# Recommandations

* Extend `KoncertoController` for faster developement
* Use `.tbs.html` extension for templates
* Redirect requests to `/` (and `/index.php`) towards `koncerto.php` using `RewriteRule` when possible