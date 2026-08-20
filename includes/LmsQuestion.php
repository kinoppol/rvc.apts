<?php

/**
 * The question bank. One table serves all three phases:
 *   review — 3 questions per topic, the ทบทวน quiz at the end of a lesson
 *   pre    — the before-you-start measurement for a level
 *   post   — the ≥80% gate that unlocks the next level
 *
 * Pre and post can never overlap because `phase` is a single column, so a question
 * belongs to exactly one bank. Banks are deliberately larger than the draw size —
 * LmsQuiz picks N at random per attempt.
 */
final class LmsQuestion
{
    public const PHASES = ['review', 'pre', 'post'];

    private const MIN_CHOICES = 2;
    private const MAX_CHOICES = 6;

    /**
     * All questions in one bank, with choice stats and how often each has been served.
     * @return array<int,array>
     */
    public static function bank(int $levelId, string $phase, ?int $topicId = null, bool $activeOnly = false): array
    {
        if (!in_array($phase, self::PHASES, true)) {
            return [];
        }
        $sql = 'SELECT q.*,
                       (SELECT COUNT(*) FROM lms_choices c WHERE c.question_id = q.id) AS choice_count,
                       (SELECT COUNT(*) FROM lms_choices c WHERE c.question_id = q.id AND c.is_correct = 1) AS correct_count,
                       (SELECT c.choice_text FROM lms_choices c WHERE c.question_id = q.id AND c.is_correct = 1 ORDER BY c.sort_order, c.id LIMIT 1) AS correct_text,
                       (SELECT COUNT(*) FROM lms_attempt_questions aq WHERE aq.question_id = q.id) AS used_count
                FROM lms_questions q
                WHERE q.level_id = ? AND q.phase = ?';
        $params = [$levelId, $phase];
        if ($topicId !== null) {
            $sql .= ' AND q.topic_id = ?';
            $params[] = $topicId;
        }
        if ($activeOnly) {
            $sql .= ' AND q.is_active = 1';
        }
        $sql .= ' ORDER BY q.sort_order, q.id';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM lms_questions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function choices(int $questionId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM lms_choices WHERE question_id = ? ORDER BY sort_order, id');
        $stmt->execute([$questionId]);
        return $stmt->fetchAll();
    }

    /**
     * Creates or updates a question together with its full choice set.
     *
     * The choices are deleted and re-inserted wholesale, which is only safe because
     * lms_attempt_questions.selected_choice_id has no FK and its is_correct is frozen
     * at submit time — past results survive an answer-key rewrite untouched.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function save(array $d, ?int $id = null): array
    {
        $text = trim((string) ($d['question_text'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกโจทย์คำถาม'];
        }

        $phase = (string) ($d['phase'] ?? '');
        if (!in_array($phase, self::PHASES, true)) {
            return ['ok' => false, 'error' => 'ประเภทแบบทดสอบไม่ถูกต้อง'];
        }

        $levelId = (int) ($d['level_id'] ?? 0);
        $topicId = $phase === 'review' ? (int) ($d['topic_id'] ?? 0) : null;
        if ($id !== null) {
            $existing = self::find($id);
            if (!$existing) {
                return ['ok' => false, 'error' => 'ไม่พบคำถามที่ต้องการแก้ไข'];
            }
            // Phase, level and topic are structural — never moved by an edit.
            $phase   = (string) $existing['phase'];
            $levelId = (int) $existing['level_id'];
            $topicId = $existing['topic_id'] !== null ? (int) $existing['topic_id'] : null;
        } else {
            if (!LmsLevel::find($levelId)) {
                return ['ok' => false, 'error' => 'ไม่พบระดับที่เลือก'];
            }
            if ($phase === 'review') {
                $topic = $topicId ? LmsContent::findTopic($topicId) : null;
                if (!$topic || (int) $topic['level_id'] !== $levelId) {
                    return ['ok' => false, 'error' => 'ไม่พบหัวข้อของคำถามทบทวนนี้'];
                }
            }
        }

        // Normalise the posted choice rows: text[] plus a single correct index.
        $texts   = array_values((array) ($d['choice_text'] ?? []));
        $correct = $d['correct_index'] ?? null;
        $choices = [];
        foreach ($texts as $i => $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;  // blank rows are how an admin removes a choice
            }
            $choices[] = ['text' => mb_substr($t, 0, 500), 'correct' => (string) $correct === (string) $i];
        }

        if (count($choices) < self::MIN_CHOICES) {
            return ['ok' => false, 'error' => 'ต้องมีตัวเลือกอย่างน้อย ' . self::MIN_CHOICES . ' ข้อ'];
        }
        if (count($choices) > self::MAX_CHOICES) {
            return ['ok' => false, 'error' => 'มีตัวเลือกได้สูงสุด ' . self::MAX_CHOICES . ' ข้อ'];
        }
        $correctCount = count(array_filter($choices, fn ($c) => $c['correct']));
        if ($correctCount !== 1) {
            return ['ok' => false, 'error' => 'ต้องเลือกคำตอบที่ถูกต้องเพียงข้อเดียว'];
        }

        $explanation = trim((string) ($d['explanation'] ?? ''));
        $explanation = $explanation === '' ? null : mb_substr($explanation, 0, 2000);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($id === null) {
                $stmt = $pdo->prepare(
                    'INSERT INTO lms_questions (level_id, topic_id, phase, question_text, explanation, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $levelId, $topicId, $phase, mb_substr($text, 0, 2000), $explanation,
                    $phase === 'review'
                        ? LmsOrder::nextFor('lms_questions', 'topic_id', (int) $topicId)
                        : self::nextPhaseOrder($levelId, $phase),
                ]);
                $id = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare('UPDATE lms_questions SET question_text = ?, explanation = ? WHERE id = ?')
                    ->execute([mb_substr($text, 0, 2000), $explanation, $id]);
                $pdo->prepare('DELETE FROM lms_choices WHERE question_id = ?')->execute([$id]);
            }

            $ins = $pdo->prepare('INSERT INTO lms_choices (question_id, choice_text, is_correct, sort_order) VALUES (?, ?, ?, ?)');
            foreach ($choices as $i => $c) {
                $ins->execute([$id, $c['text'], $c['correct'] ? 1 : 0, $i + 1]);
            }
            $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'บันทึกคำถามไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'];
        }
    }

    public static function toggleActive(int $id): void
    {
        Database::pdo()->prepare('UPDATE lms_questions SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    }

    /**
     * Refuses once the question has been served in a real attempt — the RESTRICT FK on
     * lms_attempt_questions.question_id would block it anyway, so this turns a raw SQL
     * error into a Thai message that names the right alternative.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function delete(int $id): array
    {
        if (!self::find($id)) {
            return ['ok' => false, 'error' => 'ไม่พบคำถามที่ต้องการลบ'];
        }
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM lms_attempt_questions WHERE question_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'ลบไม่ได้เพราะคำถามนี้เคยถูกใช้ในแบบทดสอบแล้ว กรุณาปิดใช้งานแทนการลบ'];
        }
        Database::pdo()->prepare('DELETE FROM lms_questions WHERE id = ?')->execute([$id]);
        return ['ok' => true];
    }

    public static function move(int $id, string $dir): void
    {
        $q = self::find($id);
        if ($q && $q['phase'] === 'review' && $q['topic_id'] !== null) {
            LmsOrder::swap('lms_questions', 'topic_id', (int) $q['topic_id'], $id, $dir);
        }
    }

    /** How many usable questions a bank holds — a question with no correct answer can never be drawn. */
    public static function activeCount(int $levelId, string $phase, ?int $topicId = null): int
    {
        if (!in_array($phase, self::PHASES, true)) {
            return 0;
        }
        $sql = 'SELECT COUNT(*) FROM lms_questions q
                WHERE q.level_id = ? AND q.phase = ? AND q.is_active = 1
                  AND (SELECT COUNT(*) FROM lms_choices c WHERE c.question_id = q.id AND c.is_correct = 1) = 1
                  AND (SELECT COUNT(*) FROM lms_choices c WHERE c.question_id = q.id) >= ' . self::MIN_CHOICES;
        $params = [$levelId, $phase];
        if ($topicId !== null) {
            $sql .= ' AND q.topic_id = ?';
            $params[] = $topicId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private static function nextPhaseOrder(int $levelId, string $phase): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lms_questions WHERE level_id = ? AND phase = ?'
        );
        $stmt->execute([$levelId, $phase]);
        return (int) $stmt->fetchColumn();
    }
}
