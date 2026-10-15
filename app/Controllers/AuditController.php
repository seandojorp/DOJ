<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/** Administrator-only audit log viewer (server-side paging for DataTables). */
final class AuditController extends Controller
{
    public function page(): never
    {
        $actions = array_column(Database::all('SELECT DISTINCT Action FROM audit_logs ORDER BY Action'), 'Action');
        $this->render('admin/audit', 'Audit Logs', ['actions' => $actions], 'audit');
    }

    public function index(): never
    {
        $where = [];
        $params = [];

        $s = Request::query('search');
        $search = is_array($s) && is_string($s['value'] ?? null) ? trim($s['value']) : '';
        if ($search !== '') {
            $like = '%' . addcslashes(mb_substr($search, 0, 100), '%_\\') . '%';
            $where[] = '(Actor_Username LIKE ? OR Action LIKE ? OR Affected_Table LIKE ? OR Affected_Record_ID LIKE ? OR IP_Address LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $result = Request::query('result');
        if (in_array($result, ['success', 'failure'], true)) {
            $where[] = 'Action_Result = ?';
            $params[] = $result;
        }
        $action = Request::query('action');
        if (is_string($action) && $action !== '' && preg_match('/^[A-Z0-9_]{1,100}$/', $action)) {
            $where[] = 'Action = ?';
            $params[] = $action;
        }
        $from = Validator::date((string) Request::query('date_from', ''));
        if ($from) { $where[] = 'Created_At >= ?'; $params[] = $from . ' 00:00:00'; }
        $to = Validator::date((string) Request::query('date_to', ''));
        if ($to) { $where[] = 'Created_At <= ?'; $params[] = $to . ' 23:59:59'; }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $columns = ['Created_At', 'Actor_Username', 'Actor_Role', 'Action', 'Affected_Table', 'Affected_Record_ID', 'Action_Result', 'IP_Address'];
        $o = Request::query('order');
        $o = is_array($o) && is_array($o[0] ?? null) ? $o[0] : [];
        $orderCol = (int) ($o['column'] ?? 0);
        $orderDir = strtolower((string) ($o['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $orderBy = ($columns[$orderCol] ?? 'Created_At') === 'Created_At' ? 'Audit_ID ' . $orderDir : $columns[$orderCol] . ' ' . $orderDir . ', Audit_ID DESC';

        $start = max(0, (int) Request::query('start', 0));
        $length = (int) Request::query('length', 25);
        $length = $length < 1 || $length > 500 ? 25 : $length;

        $total = (int) Database::value('SELECT COUNT(*) FROM audit_logs');
        $filtered = $where ? (int) Database::value('SELECT COUNT(*) FROM audit_logs' . $sqlWhere, $params) : $total;
        $rows = Database::all(
            'SELECT Audit_ID, Actor_User_ID, Actor_Username, Actor_Role, Action, Affected_Table, Affected_Record_ID, Action_Result, IP_Address, Created_At
               FROM audit_logs' . $sqlWhere . ' ORDER BY ' . $orderBy . ' LIMIT ' . $length . ' OFFSET ' . $start,
            $params
        );
        Response::json([
            'draw'            => (int) Request::query('draw', 0),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows,
        ]);
    }
}
