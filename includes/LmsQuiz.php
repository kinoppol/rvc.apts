<?php

/**
 * The quiz engine: draw, present, submit, score. No AJAX — one POST starts an
 * attempt, one GET renders it, one POST submits it, all through student/lms-quiz.php.
 *
 * Both randomisations are persisted in the database rather than in the session or in
 * hidden form fields: question order in lms_attempt_questions.sort_order, choice order
 * in choice_order. A refresh, a back button or a different device therefore all show
 * the exact same paper — and the draw cannot be tampered with or replayed for a
 * friendlier one, because start() resumes an open attempt instead of re-drawing.
 */
final class LmsQuiz
{
    /**
     * Starts a new attempt, or resumes the open one.
     * @return array{ok:bool,error?:string,attempt_id?:int}
     */
    public static function start(int $userId, int $levelId, string $phase, ?int $topicId = null): array
    {
        if (is_impersonating()) {
            return ['ok' => false, 'error' => 'ไม่สามารถทำแบบทดสอบขณะดูในฐานะผู้ใช้อื่น'];
        }
        if (!in_array($phase, LmsQuestion::PHASES, true)) {
            return ['ok' => false, 'error' => 'ประเภทแบบทดสอบไม่ถูกต้อง'];
        }

        $level = LmsLevel::find($levelId);
        if (!$level || (int) $level['is_published'] !== 1) {
            return ['ok' => false, 'error' => 'ไม่พบระดับนี้ หรือยังไม่เปิดให้เรียน'];
        }
        if (!LmsProgress::canAccessLevel($userId, $levelId)) {
            return ['ok' => false, 'error' => 'ยังไม่ปลดล็อกระดับนี้ กรุณาผ่านแบบทดสอบหลังเรียนของระดับก่อนหน้า'];
        }

        if ($phase === 'review') {
            $topic = $topicId ? LmsContent::findTopic($topicId) : null;
            if (!$topic || (int) $topic['level_id'] !== $levelId || (int) $topic['is_published'] !== 1) {
                return ['ok' => false, 'error' => 'ไม่พบหัวข้อนี้ หรือยังไม่เปิดให้เรียน'];
            }
            $need = 3;
        } else {
            $topicId = null;
            if ($phase === 'post' && !LmsProgress::canTakePostTest($userId, $levelId)) {
                return ['ok' => false, 'error' => 'ต้องผ่านแบบทดสอบทบทวนให้ครบทุกหัวข้อก่อนจึงจะทำแบบทดสอบหลังเรียนได้'];
            }
            $need = (int) ($phase === 'pre' ? $level['pre_question_count'] : $level['post_question_count']);
        }

        // Resume rather than re-draw: this is what stops a student abandoning an
        // unfavourable draw and rolling a new one.
        $open = self::openAttempt($userId, $levelId, $phase, $topicId);
        if ($open) {
            return ['ok' => true, 'attempt_id' => (int) $open['id']];
        }

        $available = LmsQuestion::activeCount($levelId, $phase, $topicId);
        if ($available < $need) {
            return ['ok' => false, 'error' => 'แบบทดสอบยังไม่พร้อมใช้งาน (จำนวนคำถามในคลังไม่พอ)'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO lms_attempts (user_id, level_id, topic_id, phase, question_count) VALUES (?, ?, ?, ?, ?)'
            )->execute([$userId, $levelId, $topicId, $phase, $need]);
            $attemptId = (int) $pdo->lastInsertId();

            // ORDER BY RAND() over a bank of a few dozen rows is trivially cheap.
            $sql = 'SELECT q.id FROM lms_questions q
                    WHERE q.level_id = ? AND q.phase = ? AND q.is_active = 1
                      AND (SELECT COUNT(*) FROM lms_choices c WHERE c.question_id = q.id AND c.is_correct = 1) = 1
                      AND (SELECT COUNT(*) FROM lms_choices c WHERE c.question_id = q.id) >= 2';
            $params = [$levelId, $phase];
            if ($topicId !== null) {
                $sql .= ' AND q.topic_id = ?';
                $params[] = $topicId;
            }
            $sql .= ' ORDER BY RAND() LIMIT ' . $need;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $questionIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (count($questionIds) < $need) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'แบบทดสอบยังไม่พร้อมใช้งาน (จำนวนคำถามในคลังไม่พอ)'];
            }

            $choiceStmt = $pdo->prepare('SELECT id FROM lms_choices WHERE question_id = ?');
            $insert = $pdo->prepare(
                'INSERT INTO lms_attempt_questions (attempt_id, question_id, choice_order, sort_order) VALUES (?, ?, ?, ?)'
            );
            foreach ($questionIds as $i => $qid) {
                $choiceStmt->execute([$qid]);
                $choiceIds = array_map('intval', $choiceStmt->fetchAll(PDO::FETCH_COLUMN));
                shuffle($choiceIds);
                $insert->execute([$attemptId, $qid, implode(',', $choiceIds), $i + 1]);
            }

            $pdo->commit();
            return ['ok' => true, 'attempt_id' => $attemptId];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'เริ่มทำแบบทดสอบไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'];
        }
    }

    /** The user's unsubmitted attempt for this exact quiz, or null. */
    public static function openAttempt(int $userId, int $levelId, string $phase, ?int $topicId): ?array
    {
        $sql = 'SELECT * FROM lms_attempts
                WHERE user_id = ? AND level_id = ? AND phase = ? AND submitted_at IS NULL
                  AND topic_id ' . ($topicId === null ? 'IS NULL' : '= ?') . '
                ORDER BY id DESC LIMIT 1';
        $params = [$userId, $levelId, $phase];
        if ($topicId !== null) {
            $params[] = $topicId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    /** Ownership is checked in SQL — another student's attempt id simply returns null. */
    public static function attempt(int $attemptId, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, l.title AS level_title, l.accent_color, l.pass_percent, l.review_pass_correct,
                    t.title AS topic_title
             FROM lms_attempts a
             JOIN lms_levels l ON l.id = a.level_id
             LEFT JOIN lms_topics t ON t.id = a.topic_id
             WHERE a.id = ? AND a.user_id = ?'
        );
        $stmt->execute([$attemptId, $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * The paper as the student sees it while taking the quiz.
     *
     * lms_choices.is_correct and lms_questions.explanation are deliberately absent from
     * this query's column list — that is the mechanical guarantee the answer key cannot
     * reach the page source. Do not add them here.
     *
     * @return array<int,array{id:int,sort_order:int,question_text:string,choices:array<int,array{id:int,choice_text:string}>}>
     */
    public static function questionsForTaking(int $attemptId): array
    {
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT aq.id, aq.sort_order, aq.choice_order, aq.selected_choice_id, q.question_text
             FROM lms_attempt_questions aq
             JOIN lms_questions q ON q.id = aq.question_id
             WHERE aq.attempt_id = ? ORDER BY aq.sort_order'
        );
        $stmt->execute([$attemptId]);
        $rows = $stmt->fetchAll();

        return self::hydrateChoices($rows, false);
    }

    /** Same paper plus the answer key and explanations — only ever called after submission. */
    public static function questionsForResult(int $attemptId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT aq.id, aq.sort_order, aq.choice_order, aq.selected_choice_id, aq.is_correct,
                    q.question_text, q.explanation
             FROM lms_attempt_questions aq
             JOIN lms_questions q ON q.id = aq.question_id
             WHERE aq.attempt_id = ? ORDER BY aq.sort_order'
        );
        $stmt->execute([$attemptId]);
        return self::hydrateChoices($stmt->fetchAll(), true);
    }

    /**
     * Grades and closes an attempt.
     * @param array $answers attempt_question_id => choice_id, straight from $_POST['q']
     * @return array{ok:bool,error?:string}
     */
    public static function submit(int $userId, int $attemptId, array $answers): array
    {
        $attempt = self::attempt($attemptId, $userId);
        if (!$attempt) {
            return ['ok' => false, 'error' => 'ไม่พบแบบทดสอบนี้'];
        }
        if ($attempt['submitted_at'] !== null) {
            return ['ok' => false, 'error' => 'แบบทดสอบนี้ถูกส่งคำตอบแล้ว'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, choice_order FROM lms_attempt_questions WHERE attempt_id = ?');
            $stmt->execute([$attemptId]);
            $rows = $stmt->fetchAll();

            $correctStmt = $pdo->prepare('SELECT is_correct FROM lms_choices WHERE id = ?');
            $saveStmt    = $pdo->prepare(
                'UPDATE lms_attempt_questions SET selected_choice_id = ?, is_correct = ?, answered_at = ? WHERE id = ?'
            );

            foreach ($rows as $row) {
                $allowed = array_map('intval', explode(',', (string) $row['choice_order']));
                $picked  = (int) ($answers[$row['id']] ?? 0);

                // A choice id belonging to a different question is rejected outright,
                // so a hand-crafted POST cannot smuggle in a known-correct option.
                if ($picked <= 0 || !in_array($picked, $allowed, true)) {
                    $saveStmt->execute([null, 0, null, $row['id']]);
                    continue;
                }
                $correctStmt->execute([$picked]);
                $isCorrect = (int) $correctStmt->fetchColumn() === 1 ? 1 : 0;
                $saveStmt->execute([$picked, $isCorrect, date('Y-m-d H:i:s'), $row['id']]);
            }

            // Compare-and-swap: closes the double-submit race for good.
            $close = $pdo->prepare(
                'UPDATE lms_attempts
                 SET correct_count = (SELECT COUNT(*) FROM lms_attempt_questions WHERE attempt_id = ? AND is_correct = 1),
                     submitted_at = NOW()
                 WHERE id = ? AND submitted_at IS NULL'
            );
            $close->execute([$attemptId, $attemptId]);
            if ($close->rowCount() !== 1) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'แบบทดสอบนี้ถูกส่งคำตอบแล้ว'];
            }

            $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'ส่งคำตอบไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'];
        }
    }

    /** Submitted attempts for one quiz, newest first. */
    public static function historyFor(int $userId, int $levelId, string $phase, ?int $topicId = null): array
    {
        $sql = 'SELECT * FROM lms_attempts
                WHERE user_id = ? AND level_id = ? AND phase = ? AND submitted_at IS NOT NULL
                  AND topic_id ' . ($topicId === null ? 'IS NULL' : '= ?') . '
                ORDER BY id DESC';
        $params = [$userId, $levelId, $phase];
        if ($topicId !== null) {
            $params[] = $topicId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** correct/total as a whole percentage. */
    public static function percent(array $attempt): int
    {
        $total = (int) $attempt['question_count'];
        return $total > 0 ? (int) round((int) $attempt['correct_count'] * 100 / $total) : 0;
    }

    /** Did this submitted attempt clear its bar? Review quizzes are scored by count, pre/post by percent. */
    public static function passed(array $attempt): bool
    {
        if ($attempt['phase'] === 'review') {
            return (int) $attempt['correct_count'] >= (int) ($attempt['review_pass_correct'] ?? 2);
        }
        return self::percent($attempt) >= (int) ($attempt['pass_percent'] ?? 80);
    }

    /**
     * Resolves each row's choice_order into real choice rows, in this attempt's shuffled order.
     * One extra query for the whole paper rather than one per question.
     */
    private static function hydrateChoices(array $rows, bool $withKey): array
    {
        if (!$rows) {
            return [];
        }
        $ids = [];
        foreach ($rows as $r) {
            foreach (explode(',', (string) $r['choice_order']) as $cid) {
                $cid = (int) $cid;
                if ($cid > 0) {
                    $ids[$cid] = true;
                }
            }
        }
        $byId = [];
        if ($ids) {
            $ids  = array_keys($ids);
            $ph   = implode(',', array_fill(0, count($ids), '?'));
            $cols = $withKey ? 'id, choice_text, is_correct' : 'id, choice_text';
            $stmt = Database::pdo()->prepare("SELECT {$cols} FROM lms_choices WHERE id IN ({$ph})");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $c) {
                $byId[(int) $c['id']] = $c;
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $choices = [];
            foreach (explode(',', (string) $r['choice_order']) as $cid) {
                $cid = (int) $cid;
                // A choice the admin has since deleted simply drops out; the frozen
                // is_correct on the attempt row still gives the right verdict.
                if (isset($byId[$cid])) {
                    $choices[] = $byId[$cid];
                }
            }
            $r['choices'] = $choices;
            unset($r['choice_order']);
            $out[] = $r;
        }
        return $out;
    }
}
