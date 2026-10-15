<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Writes to audit_logs using only its actual columns:
 * Actor_User_ID, Actor_Username, Actor_Role, Action, Affected_Table, Affected_Record_ID,
 * Action_Result, IP_Address (Created_At is set by the database).
 *
 * Never pass passwords, hashes, tokens or field values here — only identifiers.
 */
final class Audit
{
    /**
     * @param array{id?:?int,username?:?string,role?:?string}|null $actor Override the actor (e.g. failed logins, setup).
     */
    public static function log(string $action, ?string $table, int|string|null $recordId, string $result = 'success', ?array $actor = null): void
    {
        try {
            if ($actor === null) {
                $u = Auth::user();
                $actor = $u ? ['id' => $u['User_ID'], 'username' => $u['UserName'], 'role' => $u['Role']] : [];
            }
            $role = $actor['role'] ?? null;
            Database::run(
                'INSERT INTO audit_logs (Actor_User_ID, Actor_Username, Actor_Role, Action, Affected_Table, Affected_Record_ID, Action_Result, IP_Address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    isset($actor['id']) ? (int) $actor['id'] : null,
                    isset($actor['username']) ? mb_substr((string) $actor['username'], 0, 50) : null,
                    in_array($role, ['admin', 'user'], true) ? $role : null,
                    mb_substr($action, 0, 100),
                    $table !== null ? mb_substr($table, 0, 100) : null,
                    $recordId !== null ? mb_substr((string) $recordId, 0, 100) : null,
                    $result === 'failure' ? 'failure' : 'success',
                    Request::clientIp(),
                ]
            );
        } catch (\Throwable $e) {
            // Auditing must never break the user's request; record the problem in the server log instead.
            error_log('Audit log write failed: ' . $e->getMessage());
        }
    }

    /** Count recent events for rate limiting (uses the audit trail; no extra table needed). */
    public static function countRecent(string $action, string $column, string $value, int $minutes): int
    {
        if (!in_array($column, ['IP_Address', 'Actor_Username'], true)) {
            throw new \InvalidArgumentException('Unsupported column.');
        }
        return (int) Database::value(
            'SELECT COUNT(*) FROM audit_logs WHERE Action = ? AND Action_Result = \'failure\' AND ' . $column . ' = ?
                AND Created_At >= (NOW() - INTERVAL ? MINUTE)',
            [$action, $value, $minutes]
        );
    }
}
