<?php
/**
 * SAYFALAMA YARDIMCILARI
 * ----------------------------------------------------------------------------
 * Admin listelerinde (projeler / kullanıcılar) tekrarlanan sayfalama mantığını
 * ve bağlantı üretimini tek yerde toplar (DRY).
 */

/**
 * Sayfa numarasını güvenceye alır, toplam kayıt/sayfa sayısını hesaplar ve
 * kayıt listesi için kullanılacak OFFSET değerini döndürür.
 *
 * @param PDO    $pdo
 * @param string $countSql     Toplam kayıt sayısını döndüren SELECT COUNT(*).
 * @param array  $countParams  $countSql için bağlanacak parametreler.
 * @param int    $limit        Sayfa başına kayıt sayısı.
 * @param int    $requestedPage İstenen sayfa numarası.
 *
 * @return array{total: int, page: int, totalPages: int, offset: int}
 */
function paginate_query(PDO $pdo, string $countSql, array $countParams, int $limit, int $requestedPage): array
{
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $limit));
    $page       = min(max(1, $requestedPage), $totalPages);

    return [
        "total"      => $total,
        "page"       => $page,
        "totalPages" => $totalPages,
        "offset"     => ($page - 1) * $limit,
    ];
}

/**
 * Sayfalama bağlantılarını HTML olarak üretir.
 *
 * @param int    $page       Aktif sayfa.
 * @param int    $totalPages Toplam sayfa sayısı.
 * @param string $search     Korunacak arama parametresi (boş olabilir).
 *
 * @return string
 */
function render_pagination_links(int $page, int $totalPages, string $search): string
{
    if ($totalPages <= 1) {
        return "";
    }

    $buildQuery = static function (int $targetPage) use ($search): string {
        $query = "p=" . $targetPage;
        if ($search !== "") {
            $query .= "&search=" . urlencode($search);
        }
        return "?" . $query;
    };

    $html  = '<div style="margin-top:20px;display:flex;justify-content:center;'
           . 'align-items:center;gap:6px;flex-wrap:wrap;">';

    if ($page > 1) {
        $html .= '<a href="' . $buildQuery($page - 1) . '" class="btn btn-view">&laquo;</a>';
    }

    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $page;
        $class  = $active ? "btn btn-search" : "btn btn-view";
        $style  = $active ? ' style="color:#fff;"' : "";
        $html  .= '<a href="' . $buildQuery($i) . '" class="' . $class . '"' . $style . '>' . $i . '</a>';
    }

    if ($page < $totalPages) {
        $html .= '<a href="' . $buildQuery($page + 1) . '" class="btn btn-view">&raquo;</a>';
    }

    $html .= '</div>';

    return $html;
}
