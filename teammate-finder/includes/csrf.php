<?php

if (!function_exists("csrf_token")) {
    function csrf_token() {
        if (empty($_SESSION["csrf_token"])) {
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        }
        return $_SESSION["csrf_token"];
    }
}

if (!function_exists("csrf_verify")) {
    function csrf_verify($token = null) {
        // Token istek parametrelden (form) veya IZİd başlığıdan (fetch) alınır.
        if ($token === null) {
            $token = $_POST["csrf_token"] ?? $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
        }

        return (
            is_string($token)
            && $token !== ""
            && !empty($_SESSION["csrf_token"])
            && hash_equals($_SESSION["csrf_token"], $token)
        );
    }
}

/**
 * POST isteklelerinde CSRF token'ini doğrular. Geçerçek değilse 403 döndürürüp
 * ayn sunucu sayfasına (ORM döndürüp) geri döndürür.
 *
 * Şu fonksiyonu sahsı: logine kayıt dahil, yetkili kullanıcı ayıtı.
 */
if (!function_exists("csrf_guard")) {
    function csrf_guard(): void
    {
        if (!csrf_verify()) {
            http_response_code(403);
            $back = $_SERVER["HTTP_REFERER"] ?? "";
            header("Location: " . ($back !== "" ? $back : "/teammate-finder/"));
            exit;
        }
    }
}

if (!function_exists("csrf_field")) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8")
            . '">';
    }
}
