<?php
/**
 * Aibuke - Grup Çalışma Odaları API
 * POST: action = create|join|leave|send|list|messages|match
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
session_start();
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$studentId = $_SESSION['student_id'];
$student = getStudentInfo($pdo, $studentId);
$schoolId = $student['school_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 15+ dakikadır aktif olmayan üyeleri odalardan çıkar
$pdo->exec("DELETE gm FROM aibuke_group_members gm WHERE gm.last_active_at < NOW() - INTERVAL 15 MINUTE");

// Son üyesi çıkan odaları emptied_at ile işaretle
$pdo->exec("UPDATE aibuke_study_groups g SET g.emptied_at = NOW() WHERE g.is_active = 1 AND g.emptied_at IS NULL AND g.expires_at > NOW() AND (SELECT COUNT(*) FROM aibuke_group_members WHERE group_id = g.id) = 0");

// 5+ dakikadır boş olan odaları otomatik kapat
$pdo->exec("UPDATE aibuke_study_groups SET is_active = 0, emptied_at = NULL WHERE is_active = 1 AND emptied_at IS NOT NULL AND emptied_at < NOW() - INTERVAL 5 MINUTE");

switch ($action) {

    // ─── Grup Listesi ───
    case 'list':
        // Öğrencinin aktif grupları
        $stmt = $pdo->prepare("
            SELECT g.*, gm.joined_at,
                   (SELECT COUNT(*) FROM aibuke_group_members WHERE group_id = g.id) as member_count
            FROM aibuke_study_groups g
            JOIN aibuke_group_members gm ON g.id = gm.group_id
            WHERE gm.student_id = ? AND g.is_active = 1 AND g.expires_at > NOW()
            ORDER BY gm.joined_at DESC
        ");
        $stmt->execute([$studentId]);
        $myGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Katılabilecek gruplar (aynı okul, dolu olmayan, boş olmayan)
        $stmt = $pdo->prepare("
            SELECT g.*,
                   (SELECT COUNT(*) FROM aibuke_group_members WHERE group_id = g.id) as member_count
            FROM aibuke_study_groups g
            WHERE g.school_id = ? AND g.is_active = 1 AND g.expires_at > NOW() AND g.emptied_at IS NULL
            AND g.id NOT IN (SELECT group_id FROM aibuke_group_members WHERE student_id = ?)
            HAVING member_count > 0 AND member_count < g.max_members
            ORDER BY g.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$schoolId, $studentId]);
        $available = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['my_groups' => $myGroups, 'available' => $available], JSON_UNESCAPED_UNICODE);
        break;

    // ─── Grup Oluştur ───
    case 'create':
        $topic = trim($_POST['topic'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        if (!$topic) { echo json_encode(['error' => 'Konu gerekli']); exit; }

        // Max 2 aktif grup
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_group_members gm JOIN aibuke_study_groups g ON gm.group_id = g.id WHERE gm.student_id = ? AND g.is_active = 1 AND g.expires_at > NOW()");
        $stmt->execute([$studentId]);
        if ((int)$stmt->fetchColumn() >= 2) {
            echo json_encode(['error' => 'En fazla 2 aktif grubun olabilir']);
            exit;
        }

        $groupName = $student['first_name'] . "'in " . $topic . " Odası";
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        $stmt = $pdo->prepare("INSERT INTO aibuke_study_groups (school_id, group_name, topic, subject, created_by, expires_at) VALUES (?, ?, ?, ?, 'student', ?)");
        $stmt->execute([$schoolId, $groupName, $topic, $subject, $expiresAt]);
        $groupId = $pdo->lastInsertId();

        // Oluşturanı üye yap
        $pdo->prepare("INSERT INTO aibuke_group_members (group_id, student_id) VALUES (?, ?)")->execute([$groupId, $studentId]);

        // Aibüke karşılama mesajı
        $welcomeMsg = "Merhaba! {$topic} çalışma odası açıldı. Birlikte çalışmak çok daha etkili olacak! Sorularınızı buraya yazabilirsiniz, ben de yardımcı olacağım. 📚";
        $pdo->prepare("INSERT INTO aibuke_group_messages (group_id, student_id, content, is_ai) VALUES (?, NULL, ?, 1)")->execute([$groupId, $welcomeMsg]);

        echo json_encode(['success' => true, 'group_id' => $groupId], JSON_UNESCAPED_UNICODE);
        break;

    // ─── Gruba Katıl ───
    case 'join':
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM aibuke_study_groups WHERE id = ? AND school_id = ? AND is_active = 1 AND expires_at > NOW()");
        $stmt->execute([$groupId, $schoolId]);
        $group = $stmt->fetch();
        if (!$group) { echo json_encode(['error' => 'Grup bulunamadı']); exit; }

        // Doluluk
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_group_members WHERE group_id = ?");
        $stmt->execute([$groupId]);
        if ((int)$stmt->fetchColumn() >= $group['max_members']) {
            echo json_encode(['error' => 'Grup dolu']);
            exit;
        }

        // Zaten üye mi
        $stmt = $pdo->prepare("SELECT id FROM aibuke_group_members WHERE group_id = ? AND student_id = ?");
        $stmt->execute([$groupId, $studentId]);
        if ($stmt->fetch()) { echo json_encode(['error' => 'Zaten üyesin']); exit; }

        $pdo->prepare("INSERT INTO aibuke_group_members (group_id, student_id) VALUES (?, ?)")->execute([$groupId, $studentId]);
        $pdo->prepare("UPDATE aibuke_study_groups SET emptied_at = NULL WHERE id = ?")->execute([$groupId]);

        // Katılım mesajı
        $joinMsg = $student['first_name'] . " gruba katıldı! Hoş geldin! 🎉";
        $pdo->prepare("INSERT INTO aibuke_group_messages (group_id, student_id, content, is_ai) VALUES (?, NULL, ?, 1)")->execute([$groupId, $joinMsg]);

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        break;

    // ─── Gruptan Ayrıl ───
    case 'leave':
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $pdo->prepare("DELETE FROM aibuke_group_members WHERE group_id = ? AND student_id = ?")->execute([$groupId, $studentId]);

        // Kalan üye var mı
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM aibuke_group_members WHERE group_id = ?");
        $stmt->execute([$groupId]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->prepare("UPDATE aibuke_study_groups SET emptied_at = NOW() WHERE id = ?")->execute([$groupId]);
        }

        echo json_encode(['success' => true]);
        break;

    // ─── Mesaj Gönder ───
    case 'send':
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if (!$content || mb_strlen($content) > 1000) {
            echo json_encode(['error' => 'Mesaj boş veya çok uzun']);
            exit;
        }

        // Üyelik kontrolü + heartbeat
        $stmt = $pdo->prepare("SELECT id FROM aibuke_group_members WHERE group_id = ? AND student_id = ?");
        $stmt->execute([$groupId, $studentId]);
        if (!$stmt->fetch()) { echo json_encode(['error' => 'Bu grubun üyesi değilsin']); exit; }
        $pdo->prepare("UPDATE aibuke_group_members SET last_active_at = NOW() WHERE group_id = ? AND student_id = ?")->execute([$groupId, $studentId]);

        // Mesajı kaydet
        $pdo->prepare("INSERT INTO aibuke_group_messages (group_id, student_id, content, is_ai) VALUES (?, ?, ?, 0)")
            ->execute([$groupId, $studentId, $content]);

        // Aibüke @ ile etiketlendiyse veya soru sorulduysa yanıt ver
        $shouldRespond = mb_strpos(mb_strtolower($content), '@aibuke') !== false
            || mb_strpos(mb_strtolower($content), 'aibüke') !== false
            || preg_match('/\?$/', trim($content));

        $aiResponse = null;
        if ($shouldRespond) {
            try {
                require_once __DIR__ . '/../includes/Beyin.php';
                $beyin = new Beyin();

                // Son 5 mesajı context olarak al
                $stmtH = $pdo->prepare("SELECT gm.content, gm.is_ai, s.first_name FROM aibuke_group_messages gm LEFT JOIN students s ON gm.student_id = s.id WHERE gm.group_id = ? ORDER BY gm.created_at DESC LIMIT 5");
                $stmtH->execute([$groupId]);
                $history = array_reverse($stmtH->fetchAll(PDO::FETCH_ASSOC));

                $historyText = '';
                foreach ($history as $h) {
                    $who = $h['is_ai'] ? 'Aibüke' : ($h['first_name'] ?? 'Öğrenci');
                    $historyText .= htmlspecialchars("{$who}: {$h['content']}") . "\n";
                }

                $systemPrompt = "Sen Aibüke Öğretmen'sin, bir grup çalışma odasındasın. Gruba kısa ve yararlı cevaplar ver. Tüm üyelere yardımcı ol. Türkçe konuş. Max 150 kelime.";
                $userPrompt = "Grup sohbeti:\n{$historyText}\n\nSon mesaja kısa bir yanıt ver.";

                $result = $beyin->callPublic($systemPrompt, $userPrompt, 0.7, 512);
                if ($result['success'] && !empty($result['text'])) {
                    $aiResponse = $result['text'];
                    $pdo->prepare("INSERT INTO aibuke_group_messages (group_id, student_id, content, is_ai) VALUES (?, NULL, ?, 1)")
                        ->execute([$groupId, $aiResponse]);
                }
            } catch (\Throwable $e) { /* sessizce geç */ }
        }

        echo json_encode(['success' => true, 'ai_response' => $aiResponse], JSON_UNESCAPED_UNICODE);
        break;

    // ─── Grup Mesajları ───
    case 'messages':
        $groupId = (int) ($_GET['group_id'] ?? 0);
        $after = (int) ($_GET['after'] ?? 0);

        // Üyelik kontrolü + heartbeat
        $stmt = $pdo->prepare("SELECT id FROM aibuke_group_members WHERE group_id = ? AND student_id = ?");
        $stmt->execute([$groupId, $studentId]);
        if (!$stmt->fetch()) { echo json_encode(['error' => 'Yetkisiz']); exit; }
        $pdo->prepare("UPDATE aibuke_group_members SET last_active_at = NOW() WHERE group_id = ? AND student_id = ?")->execute([$groupId, $studentId]);

        $query = "SELECT gm.*, s.first_name, s.nickname FROM aibuke_group_messages gm LEFT JOIN students s ON gm.student_id = s.id WHERE gm.group_id = ?";
        $params = [$groupId];
        if ($after > 0) {
            $query .= " AND gm.id > ?";
            $params[] = $after;
        }
        $query .= " ORDER BY gm.created_at ASC LIMIT 100";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Üye listesi
        $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.nickname FROM aibuke_group_members gm JOIN students s ON gm.student_id = s.id WHERE gm.group_id = ?");
        $stmt->execute([$groupId]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['messages' => $messages, 'members' => $members], JSON_UNESCAPED_UNICODE);
        break;

    // ─── Kankamla Beraber — Otomatik Eşleştirme ───
    case 'match':
        $subject = trim($_POST['subject'] ?? '');

        // Aynı okul, benzer seviye, aynı konuyla ilgilenen öğrenciler
        $stmt = $pdo->prepare("
            SELECT s.id, s.first_name, s.class, sp.exam_type,
                   (SELECT COUNT(*) FROM aibuke_group_members gm2 JOIN aibuke_study_groups g2 ON gm2.group_id = g2.id WHERE gm2.student_id = s.id AND g2.is_active = 1) as active_groups
            FROM students s
            JOIN aibuke_student_profiles sp ON s.id = sp.student_id
            WHERE s.school_id = ? AND s.id != ?
            AND sp.exam_type = (SELECT exam_type FROM aibuke_student_profiles WHERE student_id = ?)
            HAVING active_groups < 2
            ORDER BY RAND()
            LIMIT 3
        ");
        $stmt->execute([$schoolId, $studentId, $studentId]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['matches' => $matches, 'friends' => []], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Geçersiz aksiyon']);
}
