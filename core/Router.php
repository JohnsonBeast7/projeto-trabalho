<?php
// core/Router.php

class Router
{
    protected $routes = [];
    protected $fallback404;

    public function get($uri, $callback) {
        $this->routes['GET'][$uri] = $callback;
    }

    public function post($uri, $callback) {
        $this->routes['POST'][$uri] = $callback;
    }

    public function set404($callback) {
        $this->fallback404 = $callback;
    }

    public function resolve() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Remove query string (ex: ?x=y)
        if (strpos($uri, '?') !== false) {
            $uri = explode('?', $uri)[0];
        }

        // Remove barra final, se houver
        $uri = rtrim($uri, '/');

        // Normaliza rota para raiz
        if ($uri === '' || $uri === '/home') {
            $uri = '/';
        }

        // Verifica se a rota existe
        if (isset($this->routes[$method][$uri])) {
            $callback = $this->routes[$method][$uri];

            if (is_callable($callback)) {
                return call_user_func($callback);
            }

            if (is_array($callback)) {
                $controller = new $callback[0];
                $action = $callback[1];
                return call_user_func_array([$controller, $action], []);
            }
        }

        // Se existe um fallback definido, usa ele
        if ($this->fallback404) {
            call_user_func($this->fallback404);
            return;
        }

        // Redirecionamento padrão para /home como último recurso
        header("Location: /home", true, 302);
        exit;
    }
}
