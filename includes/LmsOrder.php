<?php

/**
 * Shared sort_order helper for the LMS up/down reorder buttons.
 *
 * The codebase has sort_order columns but no reorder UI, so this is a new pattern.
 * Every swap first renumbers the sibling set to 1..N, which makes it robust against
 * the colliding zeros that seeded or hand-inserted rows leave behind.
 *
 * $table and $parentCol are never user input — they are checked against a hardcoded
 * whitelist before being interpolated, since identifiers cannot be bound.
 */
final class LmsOrder
{
    /** table => parent column (null when the whole table is one ordered set). */
    private const TABLES = [
        'lms_levels'    => null,
        'lms_topics'    => 'level_id',
        'lms_blocks'    => 'topic_id',
        'lms_questions' => 'topic_id',
    ];

    /**
     * Moves one row up or down within its sibling set.
     *
     * @param string      $table     one of self::TABLES
     * @param string|null $parentCol the scoping column, or null for a whole-table set
     * @param int         $parentId  ignored when $parentCol is null
     * @param string      $dir       'up' | 'down'
     */
    public static function swap(string $table, ?string $parentCol, int $parentId, int $id, string $dir): void
    {
        if (!array_key_exists($table, self::TABLES) || self::TABLES[$table] !== $parentCol) {
            return;
        }
        if ($dir !== 'up' && $dir !== 'down') {
            return;
        }

        $ids = self::normalize($table, $parentCol, $parentId);
        $pos = array_search($id, $ids, true);
        if ($pos === false) {
            return;
        }
        $target = $dir === 'up' ? $pos - 1 : $pos + 1;
        if ($target < 0 || $target >= count($ids)) {
            return;  // already at the end of the list
        }

        // Positions are 1..N after normalize(), so swapping means trading those two numbers.
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare("UPDATE `{$table}` SET sort_order = ? WHERE id = ?");
        $stmt->execute([$target + 1, $ids[$pos]]);
        $stmt->execute([$pos + 1,    $ids[$target]]);
    }

    /**
     * Renumbers a sibling set to 1..N in its current display order.
     * @return int[] the row ids in that order
     */
    public static function normalize(string $table, ?string $parentCol, int $parentId): array
    {
        if (!array_key_exists($table, self::TABLES) || self::TABLES[$table] !== $parentCol) {
            return [];
        }
        $pdo = Database::pdo();

        if ($parentCol === null) {
            $ids = $pdo->query("SELECT id FROM `{$table}` ORDER BY sort_order, id")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // lms_questions is ordered per topic but only the review phase is reorderable.
            $extra = $table === 'lms_questions' ? " AND phase = 'review'" : '';
            $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE `{$parentCol}` = ?{$extra} ORDER BY sort_order, id");
            $stmt->execute([$parentId]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        $ids = array_map('intval', $ids);

        $stmt = $pdo->prepare("UPDATE `{$table}` SET sort_order = ? WHERE id = ?");
        foreach ($ids as $i => $rowId) {
            $stmt->execute([$i + 1, $rowId]);
        }
        return $ids;
    }

    /** Next sort_order for a new row in a sibling set. */
    public static function nextFor(string $table, ?string $parentCol, int $parentId): int
    {
        if (!array_key_exists($table, self::TABLES) || self::TABLES[$table] !== $parentCol) {
            return 0;
        }
        if ($parentCol === null) {
            return (int) Database::pdo()->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM `{$table}`")->fetchColumn();
        }
        $stmt = Database::pdo()->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM `{$table}` WHERE `{$parentCol}` = ?");
        $stmt->execute([$parentId]);
        return (int) $stmt->fetchColumn();
    }
}
