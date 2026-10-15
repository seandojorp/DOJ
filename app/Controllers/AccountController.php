<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AccountService;
use App\Services\PreloadService;

/** Administrator-only management of ems_users (Registered Accounts). */
final class AccountController extends Controller
{
    public function page(): never
    {
        $this->render('admin/accounts', 'Registered Accounts', ['stats' => AccountService::stats()], 'accounts');
    }

    public function index(): never
    {
        Response::ok([
            'rows'         => AccountService::list(),
            'stats'        => AccountService::stats(),
            'unregistered' => array_map(
                static fn($r) => ['id' => (int) $r['Preload_Users_ID'], 'label' => $r['DOJ_ID'] . ' — ' . $r['Email_Address']],
                PreloadService::list('unregistered')
            ),
        ]);
    }

    public function show(string $id): never
    {
        $id = $this->intId($id);
        $acc = AccountService::find($id);
        if (!$acc) {
            throw new HttpException(404, 'Account not found.');
        }
        $acc['linked_records'] = AccountService::linkedRecordCounts($id);
        Response::ok($acc);
    }

    public function store(): never
    {
        try {
            $id = AccountService::adminCreate(Request::all());
        } catch (HttpException $e) {
            Audit::log('ACCOUNT_CREATE', 'ems_users', null, 'failure');
            throw $e;
        }
        Audit::log('ACCOUNT_CREATE', 'ems_users', $id);
        Response::ok(AccountService::find($id), 'Account created.', 201);
    }

    public function update(string $id): never
    {
        $id = $this->intId($id);
        try {
            $changed = AccountService::adminUpdate($id, Request::all(), (int) Auth::id());
        } catch (HttpException $e) {
            Audit::log('ACCOUNT_UPDATE', 'ems_users', $id, 'failure');
            throw $e;
        }
        // Log one event per kind of change, without recording the values themselves.
        Audit::log('ACCOUNT_UPDATE', 'ems_users', $id);
        foreach ($changed as $what) {
            if (in_array($what, ['doj_id', 'cellphone', 'email'], true)) {
                Audit::log('ACCOUNT_IDENTITY_CHANGE_' . strtoupper($what), 'ems_users', $id);
            } elseif ($what === 'role') {
                Audit::log('ACCOUNT_ROLE_CHANGE', 'ems_users', $id);
            }
        }
        Response::ok(AccountService::find($id), 'Account updated.');
    }

    public function resetPassword(string $id): never
    {
        $id = $this->intId($id);
        try {
            AccountService::resetPassword($id, Request::raw('password'), Request::raw('confirm_password'));
        } catch (HttpException $e) {
            Audit::log('PASSWORD_RESET_BY_ADMIN', 'ems_users', $id, 'failure');
            throw $e;
        }
        Audit::log('PASSWORD_RESET_BY_ADMIN', 'ems_users', $id);
        Response::ok(null, 'Password reset. The user has been signed out of all sessions.');
    }

    public function status(string $id): never
    {
        $id = $this->intId($id);
        $status = Request::str('status');
        try {
            AccountService::setStatus($id, $status, (int) Auth::id());
        } catch (HttpException $e) {
            Audit::log('ACCOUNT_STATUS_CHANGE', 'ems_users', $id, 'failure');
            throw $e;
        }
        Audit::log($status === 'active' ? 'ACCOUNT_ACTIVATE' : 'ACCOUNT_DEACTIVATE', 'ems_users', $id);
        Response::ok(AccountService::find($id), $status === 'active' ? 'Account activated.' : 'Account deactivated.');
    }

    public function destroy(string $id): never
    {
        $id = $this->intId($id);
        try {
            AccountService::delete($id, (int) Auth::id());
        } catch (HttpException $e) {
            Audit::log('ACCOUNT_DELETE', 'ems_users', $id, 'failure');
            throw $e;
        }
        Audit::log('ACCOUNT_DELETE', 'ems_users', $id);
        Response::ok(null, 'Account deleted. The employee is now listed as not registered.');
    }
}
