<?php

namespace Inc;

/**
 * Whole-site password gate (session-based).
 */
class SiteAuthGate
{
    private const SESSION_KEY = 'site_auth_ok';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if ($secure) {
            ini_set('session.cookie_secure', '1');
        }

        session_start();
    }

    public static function isAuthenticated(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    public static function handleLoginPost(): void
    {
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
        $next = self::sanitizeNext($_POST['next'] ?? '/');

        if (hash_equals(SITE_PASSWORD, $password)) {
            session_regenerate_id(true);
            $_SESSION[self::SESSION_KEY] = true;
            header('Location: ' . $next);
            exit;
        }

        header('Location: /login?next=' . rawurlencode($next) . '&error=1');
        exit;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: /login');
        exit;
    }

    public static function showLoginForm(string $next, bool $error): void
    {
        $nextAttr = htmlspecialchars($next, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $errorBlock = $error
            ? '<p class="login-error">Senha incorreta.</p>'
            : '';
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Acesso — ' . htmlspecialchars(SITE_NAME, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;}'
            . 'form{background:#1e293b;padding:2rem;border-radius:12px;min-width:280px;max-width:90vw;box-shadow:0 8px 32px rgba(0,0,0,.35);}'
            . 'label{display:block;margin-bottom:.5rem;font-size:.9rem;}'
            . 'input{width:100%;padding:.65rem;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#f8fafc;box-sizing:border-box;}'
            . 'button{margin-top:1rem;width:100%;padding:.75rem;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer;}'
            . 'button:hover{background:#1d4ed8;}'
            . '.login-error{color:#f87171;margin:0 0 1rem;font-size:.9rem;}'
            . '</style></head><body><form method="post" action="/login">'
            . $errorBlock
            . '<label for="password">Senha</label>'
            . '<input type="password" id="password" name="password" required autocomplete="current-password" autofocus>'
            . '<input type="hidden" name="next" value="' . $nextAttr . '">'
            . '<button type="submit">Entrar</button>'
            . '</form></body></html>';
    }

    public static function isSafeInternalRedirect(string $path): bool
    {
        if ($path === '' || $path[0] !== '/') {
            return false;
        }
        if (str_starts_with($path, '//')) {
            return false;
        }
        if (preg_match('#\s#', $path)) {
            return false;
        }
        return true;
    }

    /**
     * Returns path + query for redirect; must be same-origin relative URL only.
     */
    public static function sanitizeNext(string $next): string
    {
        $next = trim($next);
        if ($next === '') {
            return '/';
        }
        $path = $next;
        if (($qpos = strpos($next, '?')) !== false) {
            $path = substr($next, 0, $qpos);
        }
        if (!self::isSafeInternalRedirect($path)) {
            return '/';
        }
        return $next;
    }

    public static function nextFromRequest(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        $query = parse_url($uri, PHP_URL_QUERY);
        $built = $query !== null && $query !== '' ? $path . '?' . $query : $path;
        return self::sanitizeNext($built);
    }
}
