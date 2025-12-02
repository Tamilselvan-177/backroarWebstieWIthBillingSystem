<?php

namespace Core;

class Router
{
    private $routes = [];
    private $notFound;
    private $prefixMiddleware = [];

    public function get($uri, $controller, $method)
    {
        $this->addRoute('GET', $uri, $controller, $method);
    }

    public function post($uri, $controller, $method)
    {
        $this->addRoute('POST', $uri, $controller, $method);
    }

    private function addRoute($httpMethod, $uri, $controller, $method)
    {
        $this->routes[] = [
            'method' => $httpMethod,
            'uri' => $uri,
            'controller' => $controller,
            'action' => $method
        ];
    }

    public function setNotFound($controller, $method)
    {
        $this->notFound = [
            'controller' => $controller,
            'action' => $method
        ];
    }

    public function guardPrefix($prefix, $middlewareClass)
    {
        $this->prefixMiddleware[] = [
            'prefix' => rtrim($prefix, '/'),
            'middleware' => $middlewareClass
        ];
    }

    public function dispatch()
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = $this->getUri();

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $this->matchUri($route['uri'], $requestUri, $params)) {
                foreach ($this->prefixMiddleware as $mw) {
                    if (strpos($requestUri, $mw['prefix']) === 0) {
                        $middleware = new $mw['middleware']();
                        if (method_exists($middleware, 'handle')) {
                            $middleware->handle();
                        }
                    }
                }
                return $this->callController($route['controller'], $route['action'], $params);
            }
        }

        // 404 Not Found
        if ($this->notFound) {
            return $this->callController($this->notFound['controller'], $this->notFound['action'], []);
        }

        http_response_code(404);
        echo "404 - Page Not Found";
    }

    private function getUri()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove /public from URI if present
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if (strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }
        
        return '/' . trim($uri, '/');
    }

    private function matchUri($routeUri, $requestUri, &$params)
    {
        $params = [];
        
        // Convert route pattern to regex
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $requestUri, $matches)) {
            array_shift($matches); // Remove full match
            
            // Extract parameter names
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $routeUri, $paramNames);
            
            // Map parameter names to values
            foreach ($paramNames[1] as $index => $name) {
                $params[$name] = $matches[$index] ?? null;
            }
            
            return true;
        }

        return false;
    }

    private function callController($controllerClass, $method, $params)
    {
        $controller = new $controllerClass();
        
        if (!method_exists($controller, $method)) {
            die("Method {$method} not found in controller " . get_class($controller));
        }

        $orderedParams = array_values($params);

        return call_user_func_array([$controller, $method], $orderedParams);
    }
}
