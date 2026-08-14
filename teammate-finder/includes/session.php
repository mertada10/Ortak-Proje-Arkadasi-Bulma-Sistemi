<?php

if (!function_exists("session_secure_start")) {
    function session_secure_start() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        session_set_cookie_params([
            "lifetime" => 0,
            "path"     => "/",
            "domain"   => "",
            "secure"   => is_https_request(),
            "httponly" => true,
            "samesite" => "Lax",
        ]);
        return session_start();
    }
}

if (!function_exists("is_https_request")) {
    function is_https_request() {
        return (
            !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"
        ) || (($_SERVER["SERVER_PORT"] ?? "") === "443");
    }
}