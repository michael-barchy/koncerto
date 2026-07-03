<?php

namespace Koncerto;

/**
 * Koncerto Impulsus Bridge
 */
class KoncertoImpulsusController extends KoncertoController
{
    /**
     * @param Koncerto $koncerto
     */
    public function __construct($koncerto)
    {
        parent::__construct($koncerto);
    }

    public function render($template, $context = array())
    {
        if (null !== $this->getRequest()->get('_live') && method_exists($this, 'postMount')) {
            $live = $this->getRequest()->get('_live');
            if (is_string($live)) {
                $data = (array)json_decode($live, true);
            } else {
                $data = (array)$live;
            }
            /** @var array<string, string> $data*/
            $this->postMount($data);
        }

        if (null !== $this->getRequest()->get('_action')) {
            $action = $this->getRequest()->get('_action');
            if (is_string($action) && method_exists($this, $action)) {
                return $this->$action();
            }
        }

        $response = parent::render($template, $context);
        $js = $this->getConfig('impulsus');
        if (null === $js || !is_string($js)) {
            $js = '/impulsus/impulsus.js';
        }

        $impulsus = sprintf(
            '<script type="text/javascript" src="%s"></script>',
            $js
        );

        $root = $this->getRequest()->getPathInfo();
        if ('/' === substr($root, -1)) {
            $root = substr($root, 0, strlen($root) - 1);
        }
        $baseHref = sprintf(
            '<base href=".%s" />',
            $root
        );

        $live = sprintf(
            '<script type="text/javascript" data-name="$live">%s</script>',
            $this->live()
        );

        $head = sprintf(
            "  %s\r\n  %s\r\n  %s\r\n</head>",
            $baseHref,
            $live,
            $impulsus
        );

        $content = $response->getContent();

        $content = str_replace(
            '</head>',
            $head,
            $content
        );

        $response->setContent($content);

        return $response;
    }

    /**
     * @return string
     */
    private function live()
    {
        if (method_exists($this, 'postMount')) {
            $this->postMount();
        }

        $targetsJS = $this->targets();
        $eventsJS = $this->events();

        return <<<JS


window.addEventListener('impulsus:ready', function () {
    var root = document.querySelector('html');
    if (root) {
        root.setAttribute('data-controller', '\$live');
    }
});

window.addEventListener('impulsus:controller', function (event) {
    (function (impulsus) {
        if (impulsus) {
            var models = Array.prototype.slice.call(document.querySelectorAll('[data-model]'));
            models.forEach((function(model) {
                model.setAttribute('data--live-target', model.getAttribute('data-model'));
            }));

            impulsus.controller(function (controller) {
{$targetsJS}
{$eventsJS}
            }, event);
        }
    })(window.Impulsus);
});


JS;
    }

    /**
     * @return string
     */
    private function targets()
    {
        $className = get_class($this);
        $props = array();
        $ref = new \ReflectionClass($className);
        $f = $ref->getFileName();
        if (false !== $f) {
            $parsed = KoncertoAnnotation::parseClass($f);
            foreach ($parsed as $classProp => $annotations) {
                $parts = explode('::', $classProp);
                $propName = array_pop($parts);
                /** @var array<array-key, mixed> $annotations */
                if (!empty($propName) && array_key_exists('liveProp()', $annotations)) {
                    $props[$propName] = $annotations['liveProp()'];
                }
            }
        }

        $targets = array();
        foreach ($props as $propName => $prop) {
            array_push($targets, sprintf(
                "controller.targets[%s].set(%s);",
                json_encode($prop['name']),
                json_encode($this->{$propName})
            ));
        }
        $targetsJS = implode("\r\n", $targets);

        return $targetsJS;
    }

    /**
     * @return string
     */
    private function events()
    {
        $className = get_class($this);
        $actions = array();
        $ref = new \ReflectionClass($className);
        $f = $ref->getFileName();
        if (false !== $f) {
            $parsed = KoncertoAnnotation::parseClass($f);
            foreach ($parsed as $classProp => $annotations) {
                $parts = explode('::', $classProp);
                $propName = array_pop($parts);
                /** @var array<array-key, mixed> $annotations */
                if (!empty($propName) && array_key_exists('liveAction()', $annotations)) {
                    $actions[$propName] = $annotations['liveAction()'];
                }
            }
        }

        $events = array();
        foreach ($actions as $actionName => $action) {
            array_push($events, sprintf(
                <<<JS
                controller.on(%s, function (param) {
                    var state = {};
                    for (var key in controller.targets) {
                        var attr = controller.targets[key].attr('data-model-attr');
                        if (attr) {
                            state[key] = controller.targets[key].attr(attr);
                        } else {
                            state[key] = controller.targets[key].get();
                        }
                    }
                    var params = '&_param=' + param + '&_live=' + JSON.stringify(state);
                    impulsus.xhr(location.href, function (response) {
                        var state = JSON.parse(response);
                        if ('object' === typeof state) {
                            for (var key in state) {
                                if (key in controller.targets) {
                                    var attr = controller.targets[key].attr('data-model-attr');
                                    if (attr) {
                                        controller.targets[key].attr(attr, state[key]);
                                    } else {
                                        controller.targets[key].set(state[key]);
                                    }
                                }
                            }
                        }
                    }, 'POST', %s + params, 'application/x-www-form-urlencoded');
                });
JS
                ,
                json_encode($action['name']),
                json_encode('_action=' . $actionName)
            ));
        }
        $eventsJS = implode("\r\n", $events);

        return $eventsJS;
    }
}
