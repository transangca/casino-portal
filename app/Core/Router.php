<?php
namespace App\Core;

/**
 * Router đơn giản dựa trên pattern kiểu Laravel.
 */
class Router
{
    private array $routes = [];
    private string $groupPrefix = '';
    private array $groupMiddleware = [];

    public function get(string $path, $handler): void    { $this->add('GET', $path, $handler); }
    public function post(string $path, $handler): void   { $this->add('POST', $path, $handler); }
    public function any(string $path, $handler): void    { $this->add('*', $path, $handler); }

    public function group(string $prefix, array $middleware, callable $fn): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevMw     = $this->groupMiddleware;
        $this->groupPrefix    = $prevPrefix . $prefix;
        $this->groupMiddleware = array_merge($prevMw, $middleware);
        $fn($this);
        $this->groupPrefix    = $prevPrefix;
        $this->groupMiddleware = $prevMw;
    }

    private function add(string $method, string $path, $handler): void
    {
        $path = $this->groupPrefix . $path;
        $path = '/' . trim($path, '/');

        $this->routes[] = [
            'method'    => $method,
            'pattern'   => $path,
            'handler'   => $handler,
            'middleware'=> $this->groupMiddleware,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== '*' && $route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }

            foreach ($route['middleware'] as $mw) {
                if ($mw === 'auth' && Auth::guest()) {
                    flash('error', 'Vui lòng đăng nhập để tiếp tục.');
                    redirect(url('/login'));
                }
                if ($mw === 'guest' && Auth::check()) {
                    redirect(url('/'));
                }
                if ($mw === 'admin' && !Auth::isAdmin()) {
                    abort(403, 'Bạn không có quyền truy cập');
                }
            }

            $this->call($route['handler'], $params);
            return;
        }

        abort(404, 'Không tìm thấy trang bạn yêu cầu');
    }

    /** Trả về mảng tham số nếu khớp, null nếu không khớp */
    private function match(string $pattern, string $path): ?array
    {
        if ($pattern === $path) {
            return [];
        }

        $pSegs = array_values(array_filter(explode('/', $pattern), 'strlen'));
        $sSegs = array_values(array_filter(explode('/', $path), 'strlen'));

        // Tham số nhiều đoạn cho phép path dài hơn pattern
        $hasWildcard = false;
        foreach ($pSegs as $seg) {
            if (str_starts_with($seg, '{') && str_ends_with($seg, '...}')) {
                $hasWildcard = true;
                break;
            }
        }

        if (!$hasWildcard && count($pSegs) !== count($sSegs)) {
            return null;
        }
        if ($hasWildcard && count($sSegs) < count($pSegs)) {
            return null;
        }

        $params = [];
        foreach ($pSegs as $i => $seg) {
            // Tham số nhiều đoạn: {action...} → gom phần còn lại
            if (str_starts_with($seg, '{') && str_ends_with($seg, '...}')) {
                $name = substr($seg, 1, -4);
                $params[$name] = implode('/', array_slice($sSegs, $i));
                return $params;
            }

            if (str_starts_with($seg, '{') && str_ends_with($seg, '}')) {
                $name = substr($seg, 1, -1);
                $params[$name] = urldecode($sSegs[$i]);
                continue;
            }
            if ($seg !== $sSegs[$i]) {
                return null;
            }
        }

        return $params;
    }

    private function call($handler, array $params): void
    {
        if (is_callable($handler)) {
            $handler(...array_values($params));
            return;
        }

        [$class, $method] = explode('@', $handler);
        $fqcn = 'App\\Controllers\\' . $class;
        if (!class_exists($fqcn)) {
            abort(500, "Controller không tồn tại: $class");
        }
        $controller = new $fqcn();
        if (!method_exists($controller, $method)) {
            abort(500, "Method không tồn tại: $method");
        }
        $controller->$method(...array_values($params));
    }
}
