<?php
/**
 * Bu dosyayı, çapraz kaynak erişimi olası olan tüm API uçlarının en başında
 * `require_once` ile çağırın.
 */

// İzin verilen köken (origin) listesi.
// Kendi yayın (prodüksiyon) adresinizi buraya ekleyin.
// ÖNEMLİ: Origin "scheme://host:port" formatındadır; port dahil birebir eşleşmelidir.
const ALLOWED_ORIGINS = [
    "http://localhost",
    "http://localhost:80",
    "http://127.0.0.1",
    "http://127.0.0.1:80",
    "https://localhost",
    "https://127.0.0.1",
    // Örnek: "https://teammate-finder.example.com",
];

$corsOrigin = $_SERVER["HTTP_ORIGIN"] ?? "";

if ($corsOrigin !== "") {

    if (in_array($corsOrigin, ALLOWED_ORIGINS, true)) {
        header("Access-Control-Allow-Origin: " . $corsOrigin);
        header("Access-Control-Allow-Credentials: true");
        header("Vary: Origin");
    }
}

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    if ($corsOrigin !== "" && in_array($corsOrigin, ALLOWED_ORIGINS, true)) {
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");
        header("Access-Control-Max-Age: 86400");
    }
    http_response_code(204);
    exit;
}