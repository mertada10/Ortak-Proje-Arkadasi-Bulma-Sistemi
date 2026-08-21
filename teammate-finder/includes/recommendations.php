<?php
/**
 * ÖNERİLEN TAKIM ARKADAŞLARI — eşleştirme motoru
 * ----------------------------------------------------------------------------
 * Mevcut kullanıcının bölümü, becerileri ve ilgi alanlarına göre diğer
 * kullanıcıları puanlar ve en uygun olanları döndürür.
 */

/**
 * Beceri/ilgi alanı etiketlerini normalleştirir (küçük harf, ayraç temizliği).
 */
function normalize_tags(string $input): array
{
    $input = mb_strtolower(trim($input));
    $input = preg_replace('/[\/;|]+/', ',', $input);
    $input = str_replace('-', ',', $input);

    $tokens = array_filter(array_map('trim', explode(',', $input)));

    return array_values(array_unique($tokens));
}

/**
 * Bir adayın puanını ve uyumluluk yüzdesini hesaplar.
 *
 * @return array{score: int, compatibility: int}
 */
function score_candidate(array $currentUser, array $candidate): array
{
    $score         = 0;
    $compatibility = 0;

    $myDept        = trim($currentUser["department"] ?? "");
    $candidateDept = trim($candidate["department"] ?? "");
    $deptMatch     = (
        $myDept !== ""
        && mb_strtolower($myDept) === mb_strtolower($candidateDept)
    );

    if ($deptMatch) {
        $score        += 10;
        $compatibility += 30;
    }

    $mySkills       = normalize_tags($currentUser["skills"] ?? "");
    $candidateSkills = normalize_tags($candidate["skills"] ?? "");
    $commonSkills   = array_intersect($mySkills, $candidateSkills);
    $score          += count($commonSkills) * 5;

    $myInterests        = normalize_tags($currentUser["interests"] ?? "");
    $candidateInterests = normalize_tags($candidate["interests"] ?? "");
    $commonInterests    = array_intersect($myInterests, $candidateInterests);
    $score              += count($commonInterests) * 4;

    if (!empty($candidate["profile_image"])) {
        $score += 2;
    }
    if (!empty(trim($candidate["about"] ?? ""))) {
        $score += 2;
    }

    $skillRatio    = count($mySkills) > 0 ? count($commonSkills) / count($mySkills) : 0;
    $compatibility += (int)round($skillRatio * 35);

    $interestRatio = count($myInterests) > 0 ? count($commonInterests) / count($myInterests) : 0;
    $compatibility += (int)round($interestRatio * 35);

    return [
        "score"         => $score,
        "compatibility" => min(100, $compatibility),
    ];
}

/**
 * Adaylara gönderilmiş bekleyen istek bulunan kullanıcı ID'lerini toplar.
 *
 * Döngü içinde ayrı sorgu çalıştırmak (N+1) yerine tek sorguyla toplu çeker.
 *
 * @param PDO           $pdo
 * @param int           $currentUserId
 * @param array<int, array<string, mixed>> $users
 *
 * @return array<int, true> Bloke edilecek kullanıcı ID'leri
 */
function fetch_pending_contact_ids(PDO $pdo, int $currentUserId, array $users): array
{
    if (count($users) === 0) {
        return [];
    }

    $candidateIds   = array_map(static fn($user) => (int)$user["id"], $users);
    $idPlaceholders = implode(",", array_fill(0, count($candidateIds), "?"));

    $stmt = $pdo->prepare("
        SELECT sender_id, receiver_id
        FROM requests
        WHERE status = 'pending'
          AND (sender_id = ? OR receiver_id = ?)
          AND (sender_id IN ($idPlaceholders) OR receiver_id IN ($idPlaceholders))
    ");
    $stmt->execute(array_merge([$currentUserId, $currentUserId], $candidateIds, $candidateIds));

    $blockedIds = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $otherId = ((int)$row["sender_id"] === $currentUserId)
            ? (int)$row["receiver_id"]
            : (int)$row["sender_id"];
        $blockedIds[$otherId] = true;
    }

    return $blockedIds;
}

/**
 * Mevcut kullanıcı için en uygun takım arkadaşlarını döndürür (en fazla 3).
 *
 * @param PDO      $pdo
 * @param array<string, mixed> $currentUser Oturumdaki kullanıcı, "id" dahil.
 *
 * @return array<int, array<string, mixed>>
 */
function recommend_teammates(PDO $pdo, array $currentUser): array
{
    $currentUserId = (int)$currentUser["id"];

    $stmt = $pdo->prepare("
        SELECT id, name, surname, username, department, skills, interests, about, profile_image
        FROM users
        WHERE id <> ?
    ");
    $stmt->execute([$currentUserId]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1");
    $stmt->execute([$currentUserId]);
    $myTeamId = $stmt->fetchColumn();

    $pendingContactIds = $myTeamId
        ? fetch_pending_contact_ids($pdo, $currentUserId, $candidates)
        : [];

    $recommended = [];
    foreach ($candidates as $candidate) {
        if (isset($pendingContactIds[$candidate["id"]])) {
            continue;
        }

        $result = score_candidate($currentUser, $candidate);
        $candidate["score"]         = $result["score"];
        $candidate["compatibility"] = $result["compatibility"];

        if ($result["score"] > 0) {
            $recommended[] = $candidate;
        }
    }

    usort($recommended, static fn($a, $b) => $b["score"] <=> $a["score"]);

    return array_slice($recommended, 0, 3);
}
