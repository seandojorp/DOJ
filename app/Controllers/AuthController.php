<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\AccountService;

final class AuthController extends Controller
{
    /** Used to keep response time similar when the username does not exist. */
    private const DUMMY_HASH = '$2y$10$KcMSCLhUYCw7F.P9cXmYve6/a/YbBNB45C/DS3q2/n0E9wksnEJk2';

    public function home(): never
    {
        $u = Auth::user();
        Response::redirect($u ? ($u['Role'] === 'admin' ? '/admin/dashboard' : '/user/dashboard') : '/login');
    }

    public function showLogin(): never
    {
        if ($u = Auth::user()) {
            Response::redirect($u['Role'] === 'admin' ? '/admin/dashboard' : '/user/dashboard');
        }
        View::render('auth/login', [
            'title'   => 'Sign In',
            'expired' => (bool) Session::pull('flash_expired', false),
            'notice'  => (string) Session::pull('flash_notice', ''),
        ], 'auth');
    }

    public function login(): never
    {
        $username = mb_substr(Request::str('username'), 0, 50);
        $password = Request::raw('password');
        if ($username === '' || $password === '') {
            throw new ValidationException(array_filter([
                'username' => $username === '' ? 'Username is required.' : null,
                'password' => $password === '' ? 'Password is required.' : null,
            ]));
        }

        $window = (int) Config::get('security.login_window_minutes', 15);
        $ipFails = Audit::countRecent('LOGIN_FAILURE', 'IP_Address', Request::clientIp(), $window);
        $userFails = Audit::countRecent('LOGIN_FAILURE', 'Actor_Username', $username, $window);
        if ($ipFails >= (int) Config::get('security.login_max_per_ip', 20)
            || $userFails >= (int) Config::get('security.login_max_per_username', 5)) {
            Audit::log('LOGIN_BLOCKED_RATE_LIMIT', 'ems_users', null, 'failure', ['username' => $username]);
            throw new HttpException(429, "Too many failed sign-in attempts. Please wait $window minutes before trying again, or contact the administrator.");
        }

        $account = Database::one('SELECT User_ID, Preload_Users_ID, UserName, password_hash, Role, Account_Status FROM ems_users WHERE UserName = ?', [$username]);
        $valid = password_verify($password, $account['password_hash'] ?? self::DUMMY_HASH);

        if (!$account || !$valid) {
            Audit::log('LOGIN_FAILURE', 'ems_users', $account['User_ID'] ?? null, 'failure', [
                'id' => $account['User_ID'] ?? null, 'username' => $username, 'role' => $account['Role'] ?? null,
            ]);
            usleep(random_int(150000, 350000));
            throw new HttpException(401, 'Invalid username or password.');
        }
        $actor = ['id' => (int) $account['User_ID'], 'username' => $account['UserName'], 'role' => $account['Role']];

        if ($account['Account_Status'] !== 'active') {
            Audit::log('LOGIN_REJECTED_INACTIVE', 'ems_users', $account['User_ID'], 'failure', $actor);
            throw new HttpException(403, 'This account is inactive. Please contact the system administrator.');
        }
        if ($account['Role'] === 'user' && $account['Preload_Users_ID'] === null) {
            Audit::log('LOGIN_REJECTED_UNLINKED', 'ems_users', $account['User_ID'], 'failure', $actor);
            throw new HttpException(403, 'This account is not linked to an employee record. Please contact the system administrator.');
        }

        if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
            $account['password_hash'] = Auth::hash($password);
            Database::run('UPDATE ems_users SET password_hash = ? WHERE User_ID = ?', [$account['password_hash'], $account['User_ID']]);
        }
        Database::run('UPDATE ems_users SET Last_Login = NOW() WHERE User_ID = ?', [$account['User_ID']]);
        $intended = (string) Session::pull('intended', '');
        Auth::login($account);
        Audit::log('LOGIN_SUCCESS', 'ems_users', $account['User_ID'], 'success', $actor);

        $home = $account['Role'] === 'admin' ? '/admin/dashboard' : '/user/dashboard';
        $prefix = $account['Role'] === 'admin' ? '/admin' : '/user';
        $target = ($intended !== '' && str_starts_with($intended, $prefix)) ? $intended : $home;
        Response::ok(['redirect' => url($target), 'role' => $account['Role']], 'Signed in successfully.');
    }

    public function showRegister(): never
    {
        if (Auth::user()) {
            Response::redirect('/');
        }
        View::render('auth/register', ['title' => 'DOJ Employee Account Registration'], 'auth');
    }

    public function register(): never
    {
        $window = (int) Config::get('security.login_window_minutes', 15);
        if (Audit::countRecent('REGISTER_FAILURE', 'IP_Address', Request::clientIp(), $window) >= (int) Config::get('security.register_max_per_ip', 10)) {
            Audit::log('REGISTER_BLOCKED_RATE_LIMIT', 'ems_users', null, 'failure', []);
            throw new HttpException(429, "Too many unsuccessful registration attempts. Please wait $window minutes and try again.");
        }
        try {
            $id = AccountService::register([
                'doj_id'           => Request::str('doj_id'),
                'cellphone'        => Request::str('cellphone'),
                'email'            => Request::str('email'),
                'username'         => Request::str('username'),
                'password'         => Request::raw('password'),
                'confirm_password' => Request::raw('confirm_password'),
            ]);
        } catch (HttpException $e) {
            // Identity mismatches count toward the rate limit; plain form mistakes do not.
            if (!($e instanceof ValidationException) || isset($e->errors['username'])) {
                Audit::log('REGISTER_FAILURE', 'ems_users', null, 'failure', ['username' => mb_substr(Request::str('username'), 0, 50)]);
            }
            throw $e;
        }
        Audit::log('REGISTER_SUCCESS', 'ems_users', $id, 'success', ['id' => $id, 'username' => Request::str('username'), 'role' => 'user']);
        Session::set('flash_notice', 'Registration successful. You may now sign in.');
        Response::ok(['redirect' => url('/login')], 'Registration successful. You may now sign in.', 201);
    }

    public function logout(): never
    {
        Audit::log('LOGOUT', 'ems_users', Auth::id(), 'success');
        Auth::logout();
        Session::start();
        Session::set('flash_notice', 'You have been signed out.');
        Response::redirect('/login');
    }

    public function settings(): never
    {
        $this->render('shared/settings', 'Account Settings', [], 'settings');
    }

    public function changePassword(): never
    {
        $uid = (int) Auth::id();
        try {
            $newHash = AccountService::changeOwnPassword($uid, Request::raw('current_password'), Request::raw('new_password'), Request::raw('confirm_password'));
        } catch (ValidationException $e) {
            Audit::log('PASSWORD_CHANGE', 'ems_users', $uid, 'failure');
            throw $e;
        }
        Auth::refreshFingerprint($newHash); // keeps this session; every other session for the account is now invalid
        Audit::log('PASSWORD_CHANGE', 'ems_users', $uid, 'success');
        Response::ok(null, 'Your password has been changed. Other sessions using this account have been signed out.');
    }
}
