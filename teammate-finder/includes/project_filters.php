<?php
/**
 * PROJE İLANI SORGULARI — ortak SQL parçaları ve yardımcıları
 * ----------------------------------------------------------------------------
 * index.php ile api/search-projects.php arasında tekrarlanan "aktif proje ilanı"
 * filtreleme ve listeleme mantığını tek yerde toplar (DRY).
 */

/**
 * Yayında olan ve üyeleri tamamlanmamış proje ilanlarını seçmek için kullanılan
 * ortak SQL parçalarını döndürür.
 *
 * - "join": kabul edilen üye sayısını tek gruplama ile hesaplayan LEFT JOIN
 *           (correlated subquery yerine).
 * - "where": aktiflik filtre koşulu.
 *
 * @return array{join: string, where: string}
 */
function active_project_clauses(): array
{
    $acceptedCountJoin = "
        LEFT JOIN (
            SELECT project_id, COUNT(*) AS accepted_count
            FROM requests
            WHERE status = 'accepted'
            GROUP BY project_id
        ) req_count ON req_count.project_id = projects.id
    ";

    $activeWhere = "
        WHERE projects.expires_at IS NOT NULL
          AND projects.expires_at >= NOW()
          AND COALESCE(req_count.accepted_count, 0) < COALESCE(projects.members_needed, 1)
    ";

    return [
        "join"  => $acceptedCountJoin,
        "where" => $activeWhere,
    ];
}

/**
 * Süresi dolan veya üyeleri tamamlanan ilanları tek yazma işlemiyle kapatır.
 *
 * @param PDO $pdo
 */
function close_expired_project_ads(PDO $pdo): void
{
    $pdo->exec("
        UPDATE projects p
        LEFT JOIN (
            SELECT project_id, COUNT(*) AS accepted_count
            FROM requests
            WHERE status = 'accepted'
            GROUP BY project_id
        ) rc ON rc.project_id = p.id
        SET p.expires_at = NULL
        WHERE p.expires_at IS NOT NULL
          AND (
              p.expires_at < NOW()
              OR COALESCE(rc.accepted_count, 0) >= COALESCE(p.members_needed, 1)
          )
    ");
}

/**
 * "Aktif proje ilanı" listesi ve ilgili sayfalama bilgilerini döndürür.
 *
 * Arama filtresi uygulandığında başlık, açıklama ve gerekli beceri alanlarında
 * arama yapar. İsteğin tamamı LIMIT/OFFSET ile sınırlanır.
 *
 * @param PDO    $pdo
 * @param string $search Arama metni (boş olabilir).
 * @param int    $page   İstenen sayfa (>= 1).
 * @param int    $perPage Sayfa başına kayıt sayısı.
 *
 * @return array{
 *     items:      array<int, array<string, mixed>>,
 *     total:      int,
 *     page:       int,
 *     totalPages: int
 * }
 */
function fetch_active_projects(PDO $pdo, string $search, int $page, int $perPage): array
{
    $clauses   = active_project_clauses();
    $params    = [];
    $where     = $clauses["where"];

    if ($search !== "") {
        $keyword = "%{$search}%";
        $where  .= " AND (projects.title LIKE ? OR projects.description LIKE ? OR projects.required_skills LIKE ?)";
        $params  = [$keyword, $keyword, $keyword];
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM projects " . $clauses["join"] . " " . $where);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min(max(1, $page), $totalPages);
    $offset     = ($page - 1) * $perPage;

    // LIMIT/OFFSET güvenli tamsayılar olduğundan doğrudan eklenir.
    $stmt = $pdo->prepare("
        SELECT projects.*, users.name, users.surname, users.department, users.profile_image
        FROM projects
        JOIN users ON projects.user_id = users.id
        " . $clauses["join"] . "
        " . $where . "
        ORDER BY projects.updated_at DESC
        LIMIT " . $perPage . " OFFSET " . $offset . "
    ");
    $stmt->execute($params);

    return [
        "items"      => $stmt->fetchAll(PDO::FETCH_ASSOC),
        "total"      => $total,
        "page"       => $page,
        "totalPages" => $totalPages,
    ];
}
