<?php

/**
 * Topics (หัวข้อย่อย) and the structured content blocks inside them.
 *
 * Blocks live in one flat table: nullable columns carry the type-specific payload
 * and saveBlock() decides which are required per type. Nothing is ever stored or
 * rendered as raw HTML — includes/lms-blocks.php escapes every field — so admins
 * cannot inject script and the app needs no sanitizer and no build step.
 */
final class LmsContent
{
    public const BLOCK_TYPES = ['heading', 'paragraph', 'list', 'image', 'code', 'youtube', 'callout'];

    private const BLOCK_LABELS = [
        'heading'   => 'หัวข้อย่อย',
        'paragraph' => 'ย่อหน้า',
        'list'      => 'รายการ',
        'image'     => 'รูปภาพ',
        'code'      => 'กล่องโค้ด',
        'youtube'   => 'วิดีโอ YouTube',
        'callout'   => 'กล่องเน้นข้อความ',
    ];

    // ── Topics ──────────────────────────────────────────────────────────────

    /** @return array<int,array> Topics of a level with block + review-question counts. */
    public static function topics(int $levelId, bool $publishedOnly = false): array
    {
        $sql = 'SELECT t.*,
                       (SELECT COUNT(*) FROM lms_blocks b WHERE b.topic_id = t.id) AS block_count,
                       (SELECT COUNT(*) FROM lms_questions q
                         WHERE q.topic_id = t.id AND q.phase = \'review\' AND q.is_active = 1) AS review_count
                FROM lms_topics t
                WHERE t.level_id = ?' . ($publishedOnly ? ' AND t.is_published = 1' : '') . '
                ORDER BY t.sort_order, t.id';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$levelId]);
        return $stmt->fetchAll();
    }

    /** A topic joined with its level's title/colour, or null. */
    public static function findTopic(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT t.*, l.title AS level_title, l.accent_color AS level_color,
                    l.sort_order AS level_sort, l.review_pass_correct, l.is_published AS level_published
             FROM lms_topics t JOIN lms_levels l ON l.id = t.level_id
             WHERE t.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array{ok:bool,error?:string} */
    public static function addTopic(int $levelId, string $title, string $summary = ''): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อหัวข้อ'];
        }
        if (!LmsLevel::find($levelId)) {
            return ['ok' => false, 'error' => 'ไม่พบระดับที่เลือก'];
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO lms_topics (level_id, slug, title, summary, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $levelId,
            'topic-' . bin2hex(random_bytes(4)),
            mb_substr($title, 0, 200),
            trim($summary) === '' ? null : mb_substr(trim($summary), 0, 500),
            LmsOrder::nextFor('lms_topics', 'level_id', $levelId),
        ]);
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function updateTopic(int $id, string $title, string $summary): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อหัวข้อ'];
        }
        if (!self::findTopic($id)) {
            return ['ok' => false, 'error' => 'ไม่พบหัวข้อที่ต้องการแก้ไข'];
        }
        Database::pdo()
            ->prepare('UPDATE lms_topics SET title = ?, summary = ? WHERE id = ?')
            ->execute([
                mb_substr($title, 0, 200),
                trim($summary) === '' ? null : mb_substr(trim($summary), 0, 500),
                $id,
            ]);
        return ['ok' => true];
    }

    public static function toggleTopicPublished(int $id): void
    {
        Database::pdo()->prepare('UPDATE lms_topics SET is_published = 1 - is_published WHERE id = ?')->execute([$id]);
    }

    public static function moveTopic(int $id, string $dir): void
    {
        $topic = self::findTopic($id);
        if ($topic) {
            LmsOrder::swap('lms_topics', 'level_id', (int) $topic['level_id'], $id, $dir);
        }
    }

    /**
     * Deletes a topic and (by FK cascade) its blocks and review questions.
     * Refuses once any of its questions has been served in a real attempt — the
     * Major::delete() "refuse when referenced" pattern.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function deleteTopic(int $id): array
    {
        if (!self::findTopic($id)) {
            return ['ok' => false, 'error' => 'ไม่พบหัวข้อที่ต้องการลบ'];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM lms_attempt_questions aq
             JOIN lms_questions q ON q.id = aq.question_id
             WHERE q.topic_id = ?'
        );
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'ลบไม่ได้เพราะมีนักศึกษาเคยทำแบบทดสอบของหัวข้อนี้แล้ว กรุณาปิดเผยแพร่แทน'];
        }

        // Blocks cascade away with the topic, but their uploaded images would be orphaned.
        foreach (self::blocks($id) as $b) {
            LmsFile::remove('lms/blocks', $b['image_file'] ?? null);
        }
        Database::pdo()->prepare('DELETE FROM lms_topics WHERE id = ?')->execute([$id]);
        return ['ok' => true];
    }

    // ── Blocks ──────────────────────────────────────────────────────────────

    public static function blocks(int $topicId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM lms_blocks WHERE topic_id = ? ORDER BY sort_order, id');
        $stmt->execute([$topicId]);
        return $stmt->fetchAll();
    }

    public static function findBlock(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM lms_blocks WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Creates or updates one content block. $d carries the raw POST fields plus an
     * optional 'image' entry from $_FILES.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function saveBlock(array $d, ?int $id = null): array
    {
        $type = (string) ($d['block_type'] ?? '');
        if (!in_array($type, self::BLOCK_TYPES, true)) {
            return ['ok' => false, 'error' => 'ชนิดบล็อกไม่ถูกต้อง'];
        }

        $existing = $id !== null ? self::findBlock($id) : null;
        if ($id !== null && !$existing) {
            return ['ok' => false, 'error' => 'ไม่พบบล็อกที่ต้องการแก้ไข'];
        }
        $topicId = (int) ($existing['topic_id'] ?? $d['topic_id'] ?? 0);
        if (!self::findTopic($topicId)) {
            return ['ok' => false, 'error' => 'ไม่พบหัวข้อของบล็อกนี้'];
        }

        $text = trim((string) ($d['text_content'] ?? ''));
        $meta = trim((string) ($d['meta'] ?? '')) ?: null;
        $row  = [
            'text_content' => null, 'image_url' => null, 'image_file' => null,
            'link_url'     => null, 'source_url' => null, 'source_label' => null, 'meta' => null,
        ];

        switch ($type) {
            case 'heading':
                if ($text === '') {
                    return ['ok' => false, 'error' => 'กรุณากรอกข้อความหัวข้อ'];
                }
                $row['text_content'] = mb_substr($text, 0, 300);
                $row['meta'] = in_array($meta, ['h2', 'h3'], true) ? $meta : 'h2';
                break;

            case 'paragraph':
                if ($text === '') {
                    return ['ok' => false, 'error' => 'กรุณากรอกเนื้อหาย่อหน้า'];
                }
                $row['text_content'] = mb_substr($text, 0, 8000);
                break;

            case 'list':
                if ($text === '') {
                    return ['ok' => false, 'error' => 'กรุณากรอกรายการอย่างน้อย 1 บรรทัด'];
                }
                $row['text_content'] = mb_substr($text, 0, 8000);
                $row['meta'] = in_array($meta, ['ul', 'ol'], true) ? $meta : 'ul';
                break;

            case 'code':
                if ($text === '') {
                    return ['ok' => false, 'error' => 'กรุณากรอกโค้ด'];
                }
                $row['text_content'] = mb_substr($text, 0, 8000);
                $row['meta'] = $meta !== null ? mb_substr($meta, 0, 40) : null;
                break;

            case 'callout':
                if ($text === '') {
                    return ['ok' => false, 'error' => 'กรุณากรอกข้อความในกล่องเน้น'];
                }
                $row['text_content'] = mb_substr($text, 0, 4000);
                $row['meta'] = in_array($meta, ['info', 'tip', 'warn'], true) ? $meta : 'info';
                $row['link_url'] = self::safeUrl($d['link_url'] ?? null);
                break;

            case 'youtube':
                $link = self::safeUrl($d['link_url'] ?? null);
                if ($link === null || self::youtubeId($link) === null) {
                    return ['ok' => false, 'error' => 'ลิงก์ YouTube ไม่ถูกต้อง'];
                }
                $row['link_url'] = $link;
                $row['text_content'] = $text === '' ? null : mb_substr($text, 0, 300);  // caption
                break;

            case 'image':
                $keepFile = $existing['image_file'] ?? null;
                $upload   = $d['image'] ?? null;
                $hasUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

                if ($hasUpload) {
                    $res = LmsFile::store($upload, 'lms/blocks', 'block', $topicId, LmsFile::IMAGE_TYPES);
                    if (!$res['ok']) {
                        return $res;
                    }
                    LmsFile::remove('lms/blocks', $keepFile);
                    $row['image_file'] = $res['file'];
                } else {
                    $url = self::safeUrl($d['image_url'] ?? null);
                    if ($url !== null && !str_starts_with(strtolower($url), 'https://')) {
                        // An http:// image is hard-blocked as mixed content on the https production site.
                        return ['ok' => false, 'error' => 'ลิงก์รูปภาพต้องขึ้นต้นด้วย https:// เท่านั้น'];
                    }
                    if ($url === null && $keepFile === null) {
                        return ['ok' => false, 'error' => 'กรุณาใส่ลิงก์รูปภาพ (https://) หรืออัปโหลดไฟล์รูป'];
                    }
                    $row['image_url']  = $url;
                    $row['image_file'] = $url !== null ? null : $keepFile;
                    if ($url !== null) {
                        LmsFile::remove('lms/blocks', $keepFile);
                    }
                }

                // Copyright attribution is mandatory on every image, uploaded or hotlinked.
                $srcUrl   = self::safeUrl($d['source_url'] ?? null);
                $srcLabel = trim((string) ($d['source_label'] ?? ''));
                if ($srcUrl === null || $srcLabel === '') {
                    return ['ok' => false, 'error' => 'รูปภาพต้องระบุแหล่งที่มา (ลิงก์ต้นทาง และข้อความเครดิต) เพื่อให้ถูกต้องตามลิขสิทธิ์'];
                }
                $row['source_url']   = $srcUrl;
                $row['source_label'] = mb_substr($srcLabel, 0, 200);
                $row['text_content'] = $text === '' ? null : mb_substr($text, 0, 300);  // caption
                break;
        }

        $pdo = Database::pdo();
        if ($id === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO lms_blocks (topic_id, block_type, text_content, image_url, image_file, link_url,
                                         source_url, source_label, meta, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $topicId, $type, $row['text_content'], $row['image_url'], $row['image_file'],
                $row['link_url'], $row['source_url'], $row['source_label'], $row['meta'],
                LmsOrder::nextFor('lms_blocks', 'topic_id', $topicId),
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE lms_blocks SET block_type = ?, text_content = ?, image_url = ?, image_file = ?,
                        link_url = ?, source_url = ?, source_label = ?, meta = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $type, $row['text_content'], $row['image_url'], $row['image_file'],
                $row['link_url'], $row['source_url'], $row['source_label'], $row['meta'], $id,
            ]);
        }
        return ['ok' => true];
    }

    public static function moveBlock(int $id, string $dir): void
    {
        $block = self::findBlock($id);
        if ($block) {
            LmsOrder::swap('lms_blocks', 'topic_id', (int) $block['topic_id'], $id, $dir);
        }
    }

    /** @return array{ok:bool,error?:string} */
    public static function deleteBlock(int $id): array
    {
        $block = self::findBlock($id);
        if (!$block) {
            return ['ok' => false, 'error' => 'ไม่พบบล็อกที่ต้องการลบ'];
        }
        LmsFile::remove('lms/blocks', $block['image_file'] ?? null);
        Database::pdo()->prepare('DELETE FROM lms_blocks WHERE id = ?')->execute([$id]);
        return ['ok' => true];
    }

    // ── Render helpers (used by includes/lms-blocks.php) ────────────────────

    /**
     * Returns the URL only when it is a plain http(s) absolute URL — this is what
     * keeps javascript: and data: URIs out of every href/src the LMS renders.
     */
    public static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || mb_strlen($url) > 500) {
            return null;
        }
        return preg_match('#^https?://[^\s<>"\']+$#i', $url) ? $url : null;
    }

    /** The 11-character video id from any common YouTube URL shape, or null. */
    public static function youtubeId(?string $url): ?string
    {
        $url = self::safeUrl($url);
        if ($url === null) {
            return null;
        }
        $patterns = [
            '#[?&]v=([A-Za-z0-9_-]{11})#',
            '#youtu\.be/([A-Za-z0-9_-]{11})#',
            '#youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{11})#',
            '#youtube\.com/shorts/([A-Za-z0-9_-]{11})#',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $url, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    public static function blockTypeLabel(string $type): string
    {
        return self::BLOCK_LABELS[$type] ?? $type;
    }
}
