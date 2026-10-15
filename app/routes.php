<?php
declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\PreloadController;
use App\Controllers\RecordsController;
use App\Controllers\SetupController;
use App\Controllers\UserController;
use App\Core\Router;

$r = new Router();

// ---- Public -------------------------------------------------------------------
$r->get('/', [AuthController::class, 'home'], 'guest');
$r->get('/setup', [SetupController::class, 'show'], 'guest');
$r->post('/api/setup', [SetupController::class, 'store'], 'guest');
$r->get('/login', [AuthController::class, 'showLogin'], 'guest');
$r->post('/api/auth/login', [AuthController::class, 'login'], 'guest');
$r->get('/register', [AuthController::class, 'showRegister'], 'guest');
$r->post('/api/auth/register', [AuthController::class, 'register'], 'guest');

// ---- Any signed-in account ------------------------------------------------------
$r->post('/logout', [AuthController::class, 'logout'], 'auth');
$r->post('/api/account/password', [AuthController::class, 'changePassword'], 'auth');
$r->get('/photo/{id}', [RecordsController::class, 'photo'], 'auth');
$r->get('/api/personal', [RecordsController::class, 'personalShow'], 'auth');
$r->post('/api/personal', [RecordsController::class, 'personalSave'], 'auth');
$r->get('/api/profile/completion', [RecordsController::class, 'completion'], 'auth');
$r->get('/api/records/{module}', [RecordsController::class, 'index'], 'auth');
$r->post('/api/records/{module}', [RecordsController::class, 'store'], 'auth');
$r->put('/api/records/{module}/{id}', [RecordsController::class, 'update'], 'auth');
$r->delete('/api/records/{module}/{id}', [RecordsController::class, 'destroy'], 'admin'); // regular users cannot delete records

// ---- Administrator pages --------------------------------------------------------
$r->get('/admin', [AdminController::class, 'dashboard'], 'admin');
$r->get('/admin/dashboard', [AdminController::class, 'dashboard'], 'admin');
$r->get('/admin/employees', [AdminController::class, 'employees'], 'admin');
$r->get('/admin/employees/{id}', [AdminController::class, 'employeeProfile'], 'admin');
$r->get('/admin/preload-users', [PreloadController::class, 'page'], 'admin');
$r->get('/admin/accounts', [AccountController::class, 'page'], 'admin');
$r->get('/admin/records/{module}', [AdminController::class, 'records'], 'admin');
$r->get('/admin/audit-logs', [AuditController::class, 'page'], 'admin');
$r->get('/admin/settings', [AuthController::class, 'settings'], 'admin');

// ---- Administrator API ----------------------------------------------------------
$r->get('/api/admin/stats', [AdminController::class, 'stats'], 'admin');
$r->get('/api/admin/employees', [AdminController::class, 'employeeList'], 'admin');
$r->get('/api/admin/records/{module}', [RecordsController::class, 'all'], 'admin');
$r->get('/api/admin/preload', [PreloadController::class, 'index'], 'admin');
$r->post('/api/admin/preload', [PreloadController::class, 'store'], 'admin');
$r->get('/api/admin/preload/{id}', [PreloadController::class, 'show'], 'admin');
$r->put('/api/admin/preload/{id}', [PreloadController::class, 'update'], 'admin');
$r->delete('/api/admin/preload/{id}', [PreloadController::class, 'destroy'], 'admin');
$r->get('/api/admin/accounts', [AccountController::class, 'index'], 'admin');
$r->post('/api/admin/accounts', [AccountController::class, 'store'], 'admin');
$r->get('/api/admin/accounts/{id}', [AccountController::class, 'show'], 'admin');
$r->put('/api/admin/accounts/{id}', [AccountController::class, 'update'], 'admin');
$r->delete('/api/admin/accounts/{id}', [AccountController::class, 'destroy'], 'admin');
$r->post('/api/admin/accounts/{id}/password', [AccountController::class, 'resetPassword'], 'admin');
$r->post('/api/admin/accounts/{id}/status', [AccountController::class, 'status'], 'admin');
$r->get('/api/admin/audit-logs', [AuditController::class, 'index'], 'admin');

// ---- Regular-user pages ---------------------------------------------------------
$r->get('/user', [UserController::class, 'dashboard'], 'user');
$r->get('/user/dashboard', [UserController::class, 'dashboard'], 'user');
$r->get('/user/profile', [UserController::class, 'profile'], 'user');
$r->get('/user/profile/{module}', [UserController::class, 'section'], 'user');
$r->get('/user/settings', [AuthController::class, 'settings'], 'user');

return $r;
