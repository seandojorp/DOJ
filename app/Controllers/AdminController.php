<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccountService;
use App\Services\Modules;
use App\Services\PreloadService;
use App\Services\ProfileService;

final class AdminController extends Controller
{
    public function dashboard(): never
    {
        $this->render('admin/dashboard', 'Dashboard', [], 'dashboard');
    }

    public function stats(): never
    {
        $pre = PreloadService::stats();
        $acc = AccountService::stats();
        $completion = ProfileService::completionMap();
        $userIds = array_map('intval', array_column(Database::all("SELECT User_ID FROM ems_users WHERE Role = 'user'"), 'User_ID'));
        $buckets = ['0%' => 0, '1–49%' => 0, '50–99%' => 0, '100%' => 0];
        foreach ($userIds as $uid) {
            $p = $completion[$uid] ?? 0;
            $buckets[$p === 0 ? '0%' : ($p < 50 ? '1–49%' : ($p < 100 ? '50–99%' : '100%'))]++;
        }
        Response::ok([
            'preloaded'     => $pre['total'],
            'registered'    => $pre['registered'],
            'unregistered'  => $pre['unregistered'],
            'active'        => $acc['active'],
            'inactive'      => $acc['inactive'],
            'admins'        => $acc['admins'],
            'recent'        => AccountService::recentRegistrations(8),
            'by_month'      => AccountService::registrationsByMonth(6),
            'completion'    => $buckets,
            'activity'      => Database::all(
                'SELECT Audit_ID, Actor_Username, Actor_Role, Action, Affected_Table, Affected_Record_ID, Action_Result, Created_At
                   FROM audit_logs ORDER BY Audit_ID DESC LIMIT 10'
            ),
        ]);
    }

    public function employees(): never
    {
        $this->render('admin/employees', 'Employee Management', ['q' => (string) Request::query('q', '')], 'employees');
    }

    public function employeeList(): never
    {
        $rows = Database::all(
            'SELECT u.User_ID, u.UserName, u.Role, u.Account_Status, u.Created_At, u.Last_Login,
                    p.DOJ_ID, p.Email_Address, p.Cellphone_No, pi.Full_Name, ei.Current_Position, ei.Original_Office
               FROM ems_users u
          LEFT JOIN preload_users p ON p.Preload_Users_ID = u.Preload_Users_ID
          LEFT JOIN `personal-information` pi ON pi.User_ID = u.User_ID
          LEFT JOIN (SELECT User_ID, MAX(Employment_ID) AS mid FROM `employment-information` GROUP BY User_ID) em ON em.User_ID = u.User_ID
          LEFT JOIN `employment-information` ei ON ei.Employment_ID = em.mid
           ORDER BY COALESCE(pi.Full_Name, u.UserName)'
        );
        $completion = ProfileService::completionMap();
        foreach ($rows as &$r) {
            $r['completion'] = $completion[(int) $r['User_ID']] ?? 0;
        }
        Response::ok(['rows' => $rows]);
    }

    public function employeeProfile(string $id): never
    {
        $id = $this->intId($id);
        $acc = AccountService::find($id);
        if (!$acc) {
            throw new HttpException(404, 'Employee account not found.');
        }
        $this->render('admin/employee_profile', $acc['Full_Name'] ?: $acc['UserName'], [
            'employee'   => $acc,
            'completion' => ProfileService::completion($id),
            'modules'    => array_combine(array_keys(Modules::all()), array_map(static fn($s) => Modules::clientDefinition($s), array_keys(Modules::all()))),
        ], 'employees');
    }

    public function records(string $module): never
    {
        $m = Modules::get($module);
        if ($m === null || !empty($m['hidden'])) {
            throw new HttpException(404, 'Unknown information section.');
        }
        $employees = Database::all(
            'SELECT u.User_ID, u.UserName, p.DOJ_ID, pi.Full_Name FROM ems_users u
          LEFT JOIN preload_users p ON p.Preload_Users_ID = u.Preload_Users_ID
          LEFT JOIN `personal-information` pi ON pi.User_ID = u.User_ID
           ORDER BY COALESCE(pi.Full_Name, u.UserName)'
        );
        $defs = [Modules::clientDefinition($module)];
        if (!empty($m['sub'])) {
            $defs[] = Modules::clientDefinition($m['sub']);
        }
        $this->render('admin/records', $m['title'], ['module' => Modules::clientDefinition($module), 'defs' => $defs, 'employees' => $employees], 'mod-' . $module);
    }
}
