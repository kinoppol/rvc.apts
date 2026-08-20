<?php

/**
 * Every derived fact about a learner's position on the ladder.
 *
 * There is no lms_progress table and no unlocked/completed flag anywhere: unlock,
 * topic completion and all percentages are computed here from lms_attempts on each
 * request, which is the codebase's strongest rule (see CLAUDE.md).
 *
 * forUser() costs exactly four queries no matter how many levels exist, and is cached
 * per request the same way current_user() is — the header bell, the sidebar and the
 * page body all share one set.
 */
final class LmsProgress
{
    /**
     * @return array{levels:array<int,array>,ladder:array<int,array>,openAttempts:array<int,array>}
     */
    public static function forUser(int $userId): array
    {
        static $cache = [];
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }

        $pdo    = Database::pdo();
        $ladder = LmsLevel::ladder();
        $counts = LmsLevel::topicCounts();

        // 1. Best score + try count per (level, phase) for pre/post.
        $stmt = $pdo->prepare(
            'SELECT level_id, phase,
                    MAX(correct_count * 100 / question_count) AS best,
                    COUNT(*) AS tries
             FROM lms_attempts
             WHERE user_id = ? AND submitted_at IS NOT NULL AND topic_id IS NULL AND question_count > 0
             GROUP BY level_id, phase'
        );
        $stmt->execute([$userId]);
        $scores = [];
        foreach ($stmt->fetchAll() as $r) {
            $scores[(int) $r['level_id']][$r['phase']] = [
                'best'  => (float) $r['best'],
                'tries' => (int) $r['tries'],
            ];
        }

        // 2. Distinct topics cleared per level (review quiz at or above the level's threshold).
        $stmt = $pdo->prepare(
            'SELECT a.level_id, COUNT(DISTINCT a.topic_id) AS done
             FROM lms_attempts a
             JOIN lms_levels l ON l.id = a.level_id
             WHERE a.user_id = ? AND a.submitted_at IS NOT NULL AND a.topic_id IS NOT NULL
               AND a.correct_count >= l.review_pass_correct
             GROUP BY a.level_id'
        );
        $stmt->execute([$userId]);
        $topicsDone = [];
        foreach ($stmt->fetchAll() as $r) {
            $topicsDone[(int) $r['level_id']] = (int) $r['done'];
        }

        // 3. Attempts still open — an unsubmitted attempt blocks a fresh draw.
        $stmt = $pdo->prepare(
            'SELECT id, level_id, phase, topic_id, created_at
             FROM lms_attempts WHERE user_id = ? AND submitted_at IS NULL ORDER BY id'
        );
        $stmt->execute([$userId]);
        $openAttempts = $stmt->fetchAll();
        $openByKey = [];
        foreach ($openAttempts as $a) {
            $openByKey[self::attemptKey((int) $a['level_id'], (string) $a['phase'], $a['topic_id'])] = $a;
        }

        // 4. Promotion requests — the newest row per level is the live one.
        $stmt = $pdo->prepare(
            'SELECT p.id, p.level_id, p.status, p.admin_feedback, p.reviewed_at, p.created_at,
                    g.name AS granted_group_name
             FROM lms_promotions p
             LEFT JOIN user_groups g ON g.id = p.granted_group_id
             WHERE p.user_id = ? ORDER BY p.id'
        );
        $stmt->execute([$userId]);
        $promotions = [];
        foreach ($stmt->fetchAll() as $r) {
            $promotions[(int) $r['level_id']] = $r;  // later rows overwrite earlier ones
        }

        $levels    = [];
        $prevPass  = true;  // the first rung is always open
        foreach ($ladder as $level) {
            $lid   = (int) $level['id'];
            $pass  = (int) $level['pass_percent'];
            $pre   = $scores[$lid]['pre']  ?? ['best' => null, 'tries' => 0];
            $post  = $scores[$lid]['post'] ?? ['best' => null, 'tries' => 0];

            $topicsTotal  = $counts[$lid]['published'] ?? 0;
            $done         = min($topicsDone[$lid] ?? 0, $topicsTotal);
            $allTopicsDone = $topicsTotal > 0 && $done >= $topicsTotal;

            $postPassed = $post['best'] !== null && $post['best'] >= $pass;
            $unlocked   = $prevPass;
            $promotion  = $promotions[$lid] ?? null;

            $levels[$lid] = [
                'level'         => $level,
                'unlocked'      => $unlocked,
                'bestPre'       => $pre['best']  !== null ? (int) round($pre['best'])  : null,
                'bestPost'      => $post['best'] !== null ? (int) round($post['best']) : null,
                'preTries'      => $pre['tries'],
                'postTries'     => $post['tries'],
                'postPassed'    => $postPassed,
                'topicsDone'    => $done,
                'topicsTotal'   => $topicsTotal,
                'allTopicsDone' => $allTopicsDone,
                'canPostTest'   => $unlocked && $allTopicsDone,
                'openPre'       => $openByKey[self::attemptKey($lid, 'pre', null)]  ?? null,
                'openPost'      => $openByKey[self::attemptKey($lid, 'post', null)] ?? null,
                'promotion'     => $promotion,
                'promoted'      => $promotion !== null && $promotion['status'] === 'approved',
            ];

            // Only a published level can gate the one above it — an unpublished rung
            // in the middle must not strand every level beyond it.
            if ((int) $level['is_published'] === 1) {
                $prevPass = $postPassed;
            }
        }

        // canRequestPromotion needs the fully-built row, so it is a second pass.
        foreach ($levels as $lid => &$st) {
            $st['canRequestPromotion'] = self::promotionAllowed($st);
        }
        unset($st);

        $cache[$userId] = ['levels' => $levels, 'ladder' => $ladder, 'openAttempts' => $openAttempts];
        return $cache[$userId];
    }

    public static function levelState(int $userId, int $levelId): ?array
    {
        return self::forUser($userId)['levels'][$levelId] ?? null;
    }

    public static function canAccessLevel(int $userId, int $levelId): bool
    {
        $st = self::levelState($userId, $levelId);
        return $st !== null && $st['unlocked'] && (int) $st['level']['is_published'] === 1;
    }

    public static function canTakePostTest(int $userId, int $levelId): bool
    {
        $st = self::levelState($userId, $levelId);
        return $st !== null && $st['unlocked'] && (int) $st['level']['is_published'] === 1 && $st['canPostTest'];
    }

    public static function canRequestPromotion(int $userId, int $levelId): bool
    {
        $st = self::levelState($userId, $levelId);
        return $st !== null && $st['canRequestPromotion'];
    }

    /** Which topics of a level this user has already cleared. @return array<int,bool> keyed by topic_id */
    public static function topicStatus(int $userId, int $levelId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.topic_id, MAX(a.correct_count) AS best, COUNT(*) AS tries
             FROM lms_attempts a
             WHERE a.user_id = ? AND a.level_id = ? AND a.topic_id IS NOT NULL AND a.submitted_at IS NOT NULL
             GROUP BY a.topic_id'
        );
        $stmt->execute([$userId, $levelId]);
        $level = LmsLevel::find($levelId);
        $need  = (int) ($level['review_pass_correct'] ?? 2);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int) $r['topic_id']] = [
                'passed' => (int) $r['best'] >= $need,
                'best'   => (int) $r['best'],
                'tries'  => (int) $r['tries'],
            ];
        }
        return $out;
    }

    /** Headline numbers for the student dashboard strip. */
    public static function summaryFor(int $userId): array
    {
        $data      = self::forUser($userId);
        $published = LmsLevel::published();
        $passed    = 0;
        $current   = null;
        foreach ($published as $l) {
            $st = $data['levels'][(int) $l['id']] ?? null;
            if (!$st) {
                continue;
            }
            if ($st['postPassed']) {
                $passed++;
            } elseif ($current === null && $st['unlocked']) {
                $current = $st;
            }
        }
        return [
            'totalLevels'  => count($published),
            'passedLevels' => $passed,
            'current'      => $current,
        ];
    }

    /**
     * Whether the student may submit the skill mission for this level right now.
     * Re-checked server-side in LmsPromotion::submit() — this is only for the UI.
     */
    private static function promotionAllowed(array $st): bool
    {
        if (!$st['unlocked'] || (int) $st['level']['is_published'] !== 1 || !$st['postPassed']) {
            return false;
        }
        if ($st['level']['promo_group_id'] === null) {
            return false;  // admin has not wired this level to a group yet
        }
        $p = $st['promotion'];
        if ($p === null) {
            return true;
        }
        return !in_array($p['status'], ['pending', 'approved'], true);
    }

    private static function attemptKey(int $levelId, string $phase, ?int $topicId): string
    {
        return $levelId . '|' . $phase . '|' . ($topicId === null ? '' : (int) $topicId);
    }
}
