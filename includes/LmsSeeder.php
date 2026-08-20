<?php

/**
 * Imports the bundled Thai curriculum from database/seed_lms/level{1..5}.php.
 *
 * Deliberately NOT a .sql file: Migration::parseStatements() strips comments and then
 * splits naively on ';' with no quote awareness, so a single semicolon inside Thai
 * lesson prose (or a URL, or a code sample) would cut a statement in half and fail the
 * migration mid-deploy. Everything here goes in through prepared statements instead,
 * which makes semicolons, apostrophes, quotes, emoji and newlines all non-issues.
 *
 * Idempotent by slug: levels, topics and questions are matched on their stable slug and
 * updated in place, so re-running corrects content rather than duplicating it. Content
 * blocks have no natural key, so by default a topic that already has blocks is skipped —
 * that protects anything an admin has edited by hand. $overwriteBlocks forces a rewrite.
 *
 * Import is an explicit admin action on admin/lms.php, never part of `php migrate.php`,
 * so a deploy never silently changes content.
 */
final class LmsSeeder
{
    private const DIR    = __DIR__ . '/../database/seed_lms';
    private const LEVELS = [1, 2, 3, 4, 5];

    /**
     * Dry run: what an import would do, without touching anything.
     * @return array<int,array{title:string,state:string,topics:int,questions:int,blocksSkipped:int}>
     */
    public static function preview(): array
    {
        $out = [];
        foreach (self::LEVELS as $n) {
            $data = self::load($n);
            if ($data === null) {
                continue;
            }
            $existing = LmsLevel::findBySlug((string) $data['slug']);

            $questions = count($data['pre'] ?? []) + count($data['post'] ?? []);
            $blocksSkipped = 0;
            foreach ($data['topics'] ?? [] as $t) {
                $questions += count($t['questions'] ?? []);
                if ($existing) {
                    $topic = self::findTopicBySlug((int) $existing['id'], (string) $t['slug']);
                    if ($topic && LmsContent::blocks((int) $topic['id'])) {
                        $blocksSkipped++;
                    }
                }
            }

            $out[] = [
                'title'         => (string) $data['title'],
                'state'         => $existing ? 'อัปเดตของเดิม' : 'เพิ่มใหม่',
                'topics'        => count($data['topics'] ?? []),
                'questions'     => $questions,
                'blocksSkipped' => $blocksSkipped,
            ];
        }
        return $out;
    }

    /**
     * Runs the whole import in one transaction.
     * @return array{ok:bool,error?:string,summary?:string}
     */
    public static function run(bool $overwriteBlocks = false): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $nLevels = $nTopics = $nBlocks = $nQuestions = $nSkipped = 0;

            foreach (self::LEVELS as $n) {
                $data = self::load($n);
                if ($data === null) {
                    continue;
                }
                $levelId = self::upsertLevel($data, $n);
                $nLevels++;

                foreach ($data['topics'] ?? [] as $i => $t) {
                    $topicId = self::upsertTopic($levelId, $t, $i + 1);
                    $nTopics++;

                    $hasBlocks = (bool) LmsContent::blocks($topicId);
                    if ($hasBlocks && !$overwriteBlocks) {
                        $nSkipped++;
                    } else {
                        if ($hasBlocks) {
                            foreach (LmsContent::blocks($topicId) as $old) {
                                LmsFile::remove('lms/blocks', $old['image_file'] ?? null);
                            }
                            $pdo->prepare('DELETE FROM lms_blocks WHERE topic_id = ?')->execute([$topicId]);
                        }
                        foreach ($t['blocks'] ?? [] as $j => $b) {
                            self::insertBlock($topicId, $b, $j + 1);
                            $nBlocks++;
                        }
                    }

                    foreach ($t['questions'] ?? [] as $j => $q) {
                        // Review slugs are only unique within their topic in the seed files
                        // ('q1'..'q3' each time), but lms_questions is unique on
                        // (level_id, phase, slug) — so scope them with the topic slug.
                        self::upsertQuestion($levelId, $topicId, 'review', $q, $j + 1, (string) $t['slug'] . '-');
                        $nQuestions++;
                    }
                }

                foreach (['pre', 'post'] as $phase) {
                    foreach ($data[$phase] ?? [] as $j => $q) {
                        self::upsertQuestion($levelId, null, $phase, $q, $j + 1);
                        $nQuestions++;
                    }
                }
            }

            $pdo->commit();
            $summary = "นำเข้าเรียบร้อย: {$nLevels} ระดับ, {$nTopics} หัวข้อ, {$nBlocks} บล็อกเนื้อหา, {$nQuestions} คำถาม";
            if ($nSkipped > 0) {
                $summary .= " (ข้าม {$nSkipped} หัวข้อที่มีเนื้อหาอยู่แล้ว)";
            }
            return ['ok' => true, 'summary' => $summary];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'นำเข้าเนื้อหาไม่สำเร็จ: ' . mb_substr($e->getMessage(), 0, 200)];
        }
    }

    /** @return array<string,mixed>|null */
    private static function load(int $levelNo): ?array
    {
        // Exact-case filename: Windows would forgive a mismatch, Linux production will not.
        $path = self::DIR . '/level' . $levelNo . '.php';
        if (!is_file($path)) {
            return null;
        }
        $data = require $path;
        return is_array($data) && !empty($data['slug']) ? $data : null;
    }

    private static function upsertLevel(array $d, int $sortOrder): int
    {
        $pdo = Database::pdo();
        $existing = LmsLevel::findBySlug((string) $d['slug']);

        $cols = [
            (string) $d['title'],
            $d['subtitle']     ?? null,
            $d['description']  ?? null,
            $d['icon']         ?? 'bi-mortarboard',
            $d['accent_color'] ?? '#2563EB',
            (int) ($d['pass_percent'] ?? 80),
            (int) ($d['pre_question_count'] ?? 10),
            (int) ($d['post_question_count'] ?? 10),
            (int) ($d['review_pass_correct'] ?? 2),
            $d['mission_brief'] ?? null,
        ];

        if ($existing) {
            // is_published and promo_group_id are admin decisions — never overwritten.
            $pdo->prepare(
                'UPDATE lms_levels SET title = ?, subtitle = ?, description = ?, icon = ?, accent_color = ?,
                        pass_percent = ?, pre_question_count = ?, post_question_count = ?, review_pass_correct = ?,
                        mission_brief = ?, sort_order = ?
                 WHERE id = ?'
            )->execute([...$cols, $sortOrder, (int) $existing['id']]);
            return (int) $existing['id'];
        }

        $pdo->prepare(
            'INSERT INTO lms_levels (slug, title, subtitle, description, icon, accent_color, pass_percent,
                                     pre_question_count, post_question_count, review_pass_correct,
                                     mission_brief, sort_order, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([(string) $d['slug'], ...$cols, $sortOrder, (int) ($d['is_published'] ?? 0)]);
        return (int) $pdo->lastInsertId();
    }

    private static function upsertTopic(int $levelId, array $t, int $sortOrder): int
    {
        $pdo = Database::pdo();
        $existing = self::findTopicBySlug($levelId, (string) $t['slug']);

        if ($existing) {
            $pdo->prepare('UPDATE lms_topics SET title = ?, summary = ?, sort_order = ? WHERE id = ?')
                ->execute([(string) $t['title'], $t['summary'] ?? null, $sortOrder, (int) $existing['id']]);
            return (int) $existing['id'];
        }
        $pdo->prepare('INSERT INTO lms_topics (level_id, slug, title, summary, sort_order) VALUES (?, ?, ?, ?, ?)')
            ->execute([$levelId, (string) $t['slug'], (string) $t['title'], $t['summary'] ?? null, $sortOrder]);
        return (int) $pdo->lastInsertId();
    }

    private static function insertBlock(int $topicId, array $b, int $sortOrder): void
    {
        Database::pdo()->prepare(
            'INSERT INTO lms_blocks (topic_id, block_type, text_content, image_url, link_url,
                                     source_url, source_label, meta, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $topicId,
            (string) $b['type'],
            $b['text']         ?? null,
            $b['image_url']    ?? null,
            $b['link_url']     ?? null,
            $b['source_url']   ?? null,
            $b['source_label'] ?? null,
            $b['meta']         ?? null,
            $sortOrder,
        ]);
    }

    private static function upsertQuestion(int $levelId, ?int $topicId, string $phase, array $q, int $sortOrder, string $slugPrefix = ''): void
    {
        $pdo  = Database::pdo();
        $slug = $slugPrefix . (string) ($q['slug'] ?? '');

        $stmt = $pdo->prepare('SELECT id FROM lms_questions WHERE level_id = ? AND phase = ? AND slug = ?');
        $stmt->execute([$levelId, $phase, $slug]);
        $id = (int) ($stmt->fetchColumn() ?: 0);

        if ($id > 0) {
            $pdo->prepare('UPDATE lms_questions SET topic_id = ?, question_text = ?, explanation = ?, sort_order = ? WHERE id = ?')
                ->execute([$topicId, (string) $q['text'], $q['explanation'] ?? null, $sortOrder, $id]);
            $pdo->prepare('DELETE FROM lms_choices WHERE question_id = ?')->execute([$id]);
        } else {
            $pdo->prepare(
                'INSERT INTO lms_questions (level_id, topic_id, phase, slug, question_text, explanation, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$levelId, $topicId, $phase, $slug, (string) $q['text'], $q['explanation'] ?? null, $sortOrder]);
            $id = (int) $pdo->lastInsertId();
        }

        $ins = $pdo->prepare('INSERT INTO lms_choices (question_id, choice_text, is_correct, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($q['choices'] as $i => $c) {
            $ins->execute([$id, (string) $c['t'], !empty($c['correct']) ? 1 : 0, $i + 1]);
        }
    }

    private static function findTopicBySlug(int $levelId, string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM lms_topics WHERE level_id = ? AND slug = ?');
        $stmt->execute([$levelId, $slug]);
        return $stmt->fetch() ?: null;
    }
}
