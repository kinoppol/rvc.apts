<?php

/**
 * The 5-rung LMS ladder (ระดับเริ่มต้น … ระดับเชี่ยวชาญ).
 *
 * sort_order doubles as the ladder position: the "previous level" is simply the row
 * before this one, so there is no prev_level_id column and no self-join anywhere.
 * Nothing about a student's progress is stored here — see LmsProgress, which derives
 * unlock state from lms_attempts at read time.
 */
final class LmsLevel
{
    /** All levels in ladder order. Cached per request — read by the header, the sidebar and the page body. */
    public static function ladder(): array
    {
        static $rows = null;
        if ($rows === null) {
            $rows = Database::pdo()->query('SELECT * FROM lms_levels ORDER BY sort_order, id')->fetchAll();
        }
        return $rows;
    }

    /** Published levels only, in ladder order. Cached via ladder(). */
    public static function published(): array
    {
        return array_values(array_filter(self::ladder(), fn ($l) => (int) $l['is_published'] === 1));
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM lms_levels WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM lms_levels WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Creates or updates a level. Does NOT touch mission_brief / promo_group_id —
     * those are edited separately through updateMission().
     *
     * @return array{ok:bool,error?:string}
     */
    public static function save(array $d, ?int $id = null): array
    {
        $title = trim($d['title'] ?? '');
        if ($title === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อระดับ'];
        }

        $pass = (int) ($d['pass_percent'] ?? 80);
        if ($pass < 1 || $pass > 100) {
            return ['ok' => false, 'error' => 'เกณฑ์ผ่านต้องอยู่ระหว่าง 1-100 เปอร์เซ็นต์'];
        }
        $preCount  = (int) ($d['pre_question_count'] ?? 10);
        $postCount = (int) ($d['post_question_count'] ?? 10);
        if ($preCount < 1 || $preCount > 50 || $postCount < 1 || $postCount > 50) {
            return ['ok' => false, 'error' => 'จำนวนข้อสอบก่อน/หลังเรียนต้องอยู่ระหว่าง 1-50 ข้อ'];
        }
        $reviewPass = (int) ($d['review_pass_correct'] ?? 2);
        if ($reviewPass < 1 || $reviewPass > 3) {
            return ['ok' => false, 'error' => 'เกณฑ์ผ่านแบบทดสอบทบทวนต้องอยู่ระหว่าง 1-3 ข้อ'];
        }

        $slug = trim($d['slug'] ?? '');
        if ($slug === '') {
            $slug = 'level-' . bin2hex(random_bytes(4));
        }
        $color = trim($d['accent_color'] ?? '#2563EB');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#2563EB';
        }
        $icon     = trim($d['icon'] ?? '') ?: 'bi-mortarboard';
        $subtitle = trim($d['subtitle'] ?? '') ?: null;
        $desc     = trim($d['description'] ?? '') ?: null;

        $pdo = Database::pdo();
        try {
            if ($id === null) {
                $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lms_levels')->fetchColumn();
                $stmt = $pdo->prepare(
                    'INSERT INTO lms_levels (slug, title, subtitle, description, icon, accent_color, pass_percent,
                                             pre_question_count, post_question_count, review_pass_correct, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$slug, $title, $subtitle, $desc, $icon, $color, $pass, $preCount, $postCount, $reviewPass, $next]);
            } else {
                if (!self::find($id)) {
                    return ['ok' => false, 'error' => 'ไม่พบระดับที่ต้องการแก้ไข'];
                }
                $stmt = $pdo->prepare(
                    'UPDATE lms_levels SET title = ?, subtitle = ?, description = ?, icon = ?, accent_color = ?,
                            pass_percent = ?, pre_question_count = ?, post_question_count = ?, review_pass_correct = ?
                     WHERE id = ?'
                );
                $stmt->execute([$title, $subtitle, $desc, $icon, $color, $pass, $preCount, $postCount, $reviewPass, $id]);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'มีระดับที่ใช้รหัส (slug) นี้อยู่แล้ว'];
            }
            throw $e;
        }
        return ['ok' => true];
    }

    /**
     * Sets the skill mission and the group granted when a mission is approved.
     * @return array{ok:bool,error?:string}
     */
    public static function updateMission(int $id, string $brief, ?int $groupId): array
    {
        if (!self::find($id)) {
            return ['ok' => false, 'error' => 'ไม่พบระดับที่ต้องการแก้ไข'];
        }
        if ($groupId !== null && !UserGroup::find($groupId)) {
            return ['ok' => false, 'error' => 'ไม่พบกลุ่มที่เลือก'];
        }
        $brief = trim($brief);
        Database::pdo()
            ->prepare('UPDATE lms_levels SET mission_brief = ?, promo_group_id = ? WHERE id = ?')
            ->execute([$brief === '' ? null : mb_substr($brief, 0, 5000), $groupId, $id]);
        return ['ok' => true];
    }

    public static function togglePublished(int $id): void
    {
        Database::pdo()->prepare('UPDATE lms_levels SET is_published = 1 - is_published WHERE id = ?')->execute([$id]);
    }

    /** Moves a level one rung up or down the ladder. */
    public static function move(int $id, string $dir): void
    {
        LmsOrder::swap('lms_levels', null, 0, $id, $dir);
    }

    /**
     * Topic counts for every level in one grouped query.
     * @return array<int,array{topics:int,published:int}> keyed by level_id
     */
    public static function topicCounts(): array
    {
        $rows = Database::pdo()->query(
            'SELECT level_id, COUNT(*) AS topics, SUM(is_published = 1) AS published
             FROM lms_topics GROUP BY level_id'
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['level_id']] = ['topics' => (int) $r['topics'], 'published' => (int) $r['published']];
        }
        return $out;
    }

    /**
     * Whether a level is ready to be published: enough active pre/post questions,
     * every published topic carrying 3 review questions, and no text shared between
     * the pre and post banks.
     *
     * @return array{preCount:int,postCount:int,preOk:bool,postOk:bool,topicsMissingReview:array<int,string>,duplicateTexts:array<int,string>,ready:bool}
     */
    public static function readiness(int $id): array
    {
        $level = self::find($id);
        if (!$level) {
            return ['preCount' => 0, 'postCount' => 0, 'preOk' => false, 'postOk' => false,
                    'topicsMissingReview' => [], 'duplicateTexts' => [], 'ready' => false];
        }
        $pdo = Database::pdo();

        $preCount  = LmsQuestion::activeCount($id, 'pre');
        $postCount = LmsQuestion::activeCount($id, 'post');

        $stmt = $pdo->prepare(
            'SELECT t.title, COUNT(q.id) AS n
             FROM lms_topics t
             LEFT JOIN lms_questions q ON q.topic_id = t.id AND q.phase = \'review\' AND q.is_active = 1
             WHERE t.level_id = ? AND t.is_published = 1
             GROUP BY t.id
             HAVING n < 3
             ORDER BY t.sort_order'
        );
        $stmt->execute([$id]);
        $missing = array_map(fn ($r) => (string) $r['title'], $stmt->fetchAll());

        // Soft warning only: identical wording in both banks defeats the point of a
        // before/after measurement even though `phase` keeps them structurally distinct.
        $stmt = $pdo->prepare(
            'SELECT a.question_text
             FROM lms_questions a
             JOIN lms_questions b ON b.level_id = a.level_id AND b.phase = \'post\' AND b.question_text = a.question_text
             WHERE a.level_id = ? AND a.phase = \'pre\''
        );
        $stmt->execute([$id]);
        $dupes = array_map(fn ($r) => (string) $r['question_text'], $stmt->fetchAll());

        $preOk  = $preCount  >= (int) $level['pre_question_count'];
        $postOk = $postCount >= (int) $level['post_question_count'];

        return [
            'preCount'            => $preCount,
            'postCount'           => $postCount,
            'preOk'               => $preOk,
            'postOk'              => $postOk,
            'topicsMissingReview' => $missing,
            'duplicateTexts'      => $dupes,
            'ready'               => $preOk && $postOk && !$missing,
        ];
    }

    /** The rung below $levelId, or null when it is the first. Pure array walk, no query. */
    public static function prevOf(array $ladder, int $levelId): ?array
    {
        $prev = null;
        foreach ($ladder as $l) {
            if ((int) $l['id'] === $levelId) {
                return $prev;
            }
            $prev = $l;
        }
        return null;
    }
}
