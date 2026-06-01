<?php
/**
 * ROUTER — the dispatcher. This is the heart of "where does a URL go?".
 *
 * In routes.php you register routes like:
 *   $router->get('/targets', [TargetController::class, 'index']);
 *   $router->get('/targets/{id}', [TargetController::class, 'show']);
 *   $router->post('/targets', [TargetController::class, 'store']);
 *
 * A {placeholder} in the path becomes an argument to your controller method,
 * in order: '/targets/{id}' → show($id).
 *
 * To add a new page you add ONE line here and write the method it points to.
 * That's the whole "add a case" step you're used to.
 */
class Router
{
    /** @var array<int, array{method:string, regex:string, params:array<int,string>, handler:array}> */
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): void
    {
        // Turn '/examples/{id}/edit' into a regex and remember the param names.
        $params = [];
        $regex = preg_replace_callback('#\{(\w+)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $path);

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
        ];
    }

    /** Match the current request to a route and run it. */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        // Treat HEAD as GET: match the same routes and run the handler, so HEAD
        // probes (uptime monitors, /health) get the real status code instead of
        // a 404. The web server drops the body from the HEAD response.
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path   = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
        if ($path === '/') {
            $path = '/';
        }

        // Any form submission (POST) must carry a valid CSRF token.
        if ($method === 'POST') {
            csrf_verify();
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches); // drop the full match; keep captured params

                [$class, $action] = $route['handler'];
                $controller = new $class();
                echo $controller->$action(...$matches);
                return;
            }
        }

        // Nothing matched → 404.
        http_response_code(404);
        echo view('errors/404', ['title' => 'Not found']);
    }
}
