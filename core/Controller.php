<?php

class Controller {
    public function view($view, $data = []) {
        extract($data);
        require_once __DIR__ . '/../app/Views/' . $view . '.php';
    }
}