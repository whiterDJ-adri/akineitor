<?php
function render(string $view, array $data = []): void {
    extract($data, EXTR_SKIP);
    $VIEW_FILE = __DIR__ . '/views/' . $view . '.php';
    if (!is_file($VIEW_FILE)) { http_response_code(500); echo "Vista no encontrada"; return; }
    require __DIR__ . '/views/layout.php';
}

function partial(string $view, array $data = []): void {
    extract($data, EXTR_SKIP);
    require __DIR__ . '/views/' . $view . '.php';
}

function redirect(string $url): void {
    header("Location: {$url}");
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) { $_SESSION['_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['_csrf'];
}
function csrf_check(string $token): bool {
    return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}
