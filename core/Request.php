<?php
namespace core;

use src\Config;

class Request { 

    public array $query;
    public array $post;
    public array $headers;
    public array $server;
    public string $method;
    public string $body;
    
    public function __construct() {
        $this->query = $_GET;
        $this->post = $_POST;
        $this->headers = getallheaders();
        $this->server = $_SERVER;
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->body = file_get_contents('php://input');
    }
    
    public function getJsonBody(): array {
        return json_decode($this->body, true) ?? [];
    }

    public function getQuery(): array {
        return $this->query ?? [];
    }

    public function getJsonHeaders(): array {
        return $this->headers ?? [];
    }
    
    public static function getUrl() {
        $url = filter_input(INPUT_GET, 'request');
        $url = str_replace($_ENV['BASE_DIR'], '', $url ?? "");
        return '/'.$url;
    }
    
    public static function getMethod() {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

}
