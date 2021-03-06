<?php

namespace Pkit\Http;

use Pkit\Http\Request;
use Pkit\Http\Response;
use Pkit\Utils\Converter;
use Pkit\Utils\Env;
use Pkit\Utils\Text;

class Middlewares
{
  private static ?string $namespace = null;
  private array $middlewares;
  private \Closure $controller;

  public static function config(string $namespace)
  {
    self::$namespace = $namespace;
  }

  private static function getNamespace()
  {
    if (is_null(self::$namespace)) {
      self::$namespace = Env::getEnvOrValue("MIDDLEWARES_NAMESPACE", 'App\\Middlewares');
    }
    return self::$namespace;
  }

  public static function getMiddlewares(array|string $middlewares, string $method)
  {
    $newMiddlewares = [];
    $middlewares = Converter::anyToArray($middlewares);
    foreach ($middlewares as $key => $middleware) {
      if (is_int($key)) {
        $newMiddlewares[] = $middleware;
      }
    }
    $methodsMiddlewares = $middlewares[strtolower($method)] ?? [];
    $methodsMiddlewares = Converter::anyToArray($methodsMiddlewares);

    return array_merge($newMiddlewares, $methodsMiddlewares);
  }

  private static function getNamespaceByClass($class)
  {
    $class = str_replace("/", "\\", $class);
    if (substr($class, 0, 5) == 'Pkit\\') {
      $class = Text::removeFromStart($class, 'Pkit\\');
      return 'Pkit\\Middlewares\\' .  $class;
    } else {
      return self::getNamespace() . '\\' . $class;
    }
  }

  public function __construct(
    \Closure $controller,
    array $middlewares
  ) {
    $this->controller = $controller;
    $this->middlewares = $middlewares;
  }

  public function next(Request $request, Response $response)
  {
    if (empty($this->middlewares)) {
      return call_user_func_array($this->controller, [$request, $response]);
    }

    $middleware = array_shift($this->middlewares);

    $queue = $this;
    $next = function ($request, $response) use ($queue) {
      return $queue->next($request, $response);
    };

    @[$namespace, $params] = explode(":", self::getNamespaceByClass($middleware));
    $object = (new $namespace);
    $object->setParams(explode(",", $params ?? ""));
    return $object->handle($request, $response, $next);
  }
}
