<?php
// core/Router.php

class Router
{
    protected $routes = [];
    protected $fallback404;

    public function get($uri, $callback) { $this->routes['GET'][$uri] = $callback; }
    public function post($uri, $callback) { $this->routes['POST'][$uri] = $callback; }

    public function set404($callback) {
        $this->fallback404 = $callback;
    }

    public function resolve() {
        $uri = $_SERVER['REQUEST_URI'];
        $method = $_SERVER['REQUEST_METHOD'];

        if (strpos($uri, '?') !== false) {
            $uri = explode('?', $uri)[0];
        }

        $uri = rtrim($uri, '/');
        if ($uri === '' || $uri === '/home') {
            $uri = '/';
        }

        if (isset($this->routes[$method][$uri])) {
            $callback = $this->routes[$method][$uri];
            if (is_callable($callback)) return call_user_func($callback);
            if (is_array($callback)) {
                $controller = new $callback[0];
                $method = $callback[1];
                return call_user_func_array([$controller, $method], []);
            }
        }

        if ($this->fallback404) {
            call_user_func($this->fallback404);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario']) && $uri !== '/dashboard') {
            header("Location: /home", true, 302);
            exit;
        }

        http_response_code(404);
        echo "<h1>404 - Página Não Encontrada</h1>";
        echo "<p>A rota {$uri} para o método {$method} não foi encontrada.</p>";
    }
}
