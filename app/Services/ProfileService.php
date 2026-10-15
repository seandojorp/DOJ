<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Profile completion calculations. */
final class ProfileService
{
    /** Section => record count for one employee. */
    public static function sectionCounts(int $userId): array
    {
        $parts = [];
        $params = [];
        foreach (Modules::all() as $slug => $m) {
            $parts[] = 'SELECT ' . Database::pdo()->quote($slug) . ' AS s, COUNT(*) AS c FROM ' . Database::ident($m['table']) . ' WHERE `User_ID` = ?';
            $params[] = $userId;
        }
        $counts = [];
        foreach (Database::all(implode(' UNION ALL ', $parts), $params) as $r) {
            $counts[$r['s']] = (int) $r['c'];
        }
        return $counts;
    }

    /**
     * @return array{percent:int, done:int, total:int, sections:array}
     */
    public static function completion(int $userId): array
    {
        $counts = self::sectionCounts($userId);
        $sections = [];
        $done = 0;
        $total = 0;
        foreach (Modules::all() as $slug => $m) {
            $count = $counts[$slug] ?? 0;
            $sections[$slug] = ['title' => $m['title'], 'icon' => $m['icon'], 'count' => $count,
                                'required' => !empty($m['required']), 'complete' => $count > 0, 'hidden' => !empty($m['hidden'])];
            if (!empty($m['required'])) {
                $total++;
                if ($count > 0) $done++;
            }
        }
        return ['percent' => $total ? (int) round($done * 100 / $total) : 0, 'done' => $done, 'total' => $total, 'sections' => $sections];
    }

    /** Completion percentage for many users at once (for listings). */
    public static function completionMap(): array
    {
        $required = array_filter(Modules::all(), static fn($m) => !empty($m['required']));
        $parts = [];
        foreach ($required as $m) {
            $parts[] = 'SELECT DISTINCT `User_ID` FROM ' . Database::ident($m['table']);
        }
        $rows = Database::all('SELECT x.User_ID, COUNT(*) AS c FROM (' . implode(' UNION ALL ', $parts) . ') x GROUP BY x.User_ID');
        $total = count($required);
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['User_ID']] = (int) round(((int) $r['c']) * 100 / $total);
        }
        return $map;
    }
}
