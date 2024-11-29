<?php

namespace Core;

use Exception;

class Route
{
    public static function get(string $uri, $callback, $classMethod = null)
    {
        return self::register('GET', $uri, $callback, $classMethod);
    }

    public static function post(string $uri, $callback, $classMethod = null)
    {
        return self::register('POST', $uri, $callback, $classMethod);
    }

    private static function register(string $methodAllowed, string $uri, $callback, $classMethod = null)
    {
        global $URLs, $requestMethod, $requestURI;

        if (substr($uri, 0, 1) != '/') {
            $uri = '/' . $uri;
        }

        $URLs[$uri] = $callback;

        // Convert route pattern (e.g., 'posts/{title}/{id}') into regex
        $pattern = self::uriPattern($uri);

        // Check for request match
        if ($requestMethod === $methodAllowed && preg_match($pattern, $requestURI, $matches)) {
            ErrorHandling::check_405($requestMethod, $methodAllowed);

            // Remove the first match (full match) from $matches
            array_shift($matches);

            try {
                if (is_callable($callback)) {
                    return call_user_func($callback, ...$matches);
                }

                $controller = new $callback();
                return call_user_func_array([$controller, $classMethod], $matches);
            } catch (\Throwable $th) {
                ErrorHandling::handleException($th);
            }
        }
    }

    // Add name to a route
    public static function name(string $uri, string $name)
    {
        global $namedRoutes;

        if (substr($uri, 0, 1) != '/') {
            $uri = '/' . $uri;
        }

        $namedRoutes[$name] = $uri;
    }

    // Generate URL by route name
    public static function route(string $name, array|string $params = [])
    {
        global $namedRoutes;

        if (!isset($namedRoutes[$name])) {
            throw new Exception("Route name '$name' not found.");
        }

        $uri = $namedRoutes[$name];

        // Replace placeholders with params
        if (is_array($params)) {
            foreach ($params as $key => $value) {
                $uri = str_replace("{" . $key . "}", $value, $uri);
            }
        } elseif (is_string($params)) {
            $uri = preg_replace('/\{[^}]+\}/', $params, $uri, 1);
        }

        return self::currentDomain() . $uri;
    }

    // Get the current domain
    private static function currentDomain()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        return $protocol . "://" . $_SERVER['HTTP_HOST'];
    }

    // Convert URI to regex pattern
    private static function uriPattern(string $uri)
    {
        return "#^" . preg_replace('/\{([^\/]+)\}/', '([^/]+)', $uri) . "$#";
    }
}
