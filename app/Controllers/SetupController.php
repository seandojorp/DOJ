<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\Services\SetupService;

final class SetupController extends Controller
{
    public function show(): never
    {
        View::render('auth/setup', ['title' => 'Create Administrator Account'], 'auth');
    }

    public function store(): never
    {
        $errors = [];
        $rawUser = Request::str('username');
        $username = Validator::username($rawUser);
        if ($rawUser === '') {
            $errors['username'] = 'Username is required.';
        } elseif ($username === null) {
            $errors['username'] = 'Username must be 4–50 characters: letters, numbers, dot, dash or underscore.';
        }
        $errors += Auth::validateNewPassword(Request::raw('password'), Request::raw('confirm_password'));
        if ($errors) {
            throw new ValidationException($errors);
        }
        SetupService::createAdministrator($username, Request::raw('password'));
        Response::ok(['redirect' => url('/login')], 'Administrator account created. Please sign in.', 201);
    }
}
