<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PreloadService;

/** Administrator-only CRUD for preload_users. */
final class PreloadController extends Controller
{
    public function page(): never
    {
        $this->render('admin/preload', 'Preloaded Users', ['stats' => PreloadService::stats()], 'preload');
    }

    public function index(): never
    {
        $status = Request::query('status');
        $status = in_array($status, ['registered', 'unregistered'], true) ? $status : null;
        Response::ok(['rows' => PreloadService::list($status), 'stats' => PreloadService::stats()]);
    }

    public function show(string $id): never
    {
        $row = PreloadService::find($this->intId($id));
        if (!$row) {
            throw new HttpException(404, 'Preloaded employee not found.');
        }
        Response::ok($row);
    }

    public function store(): never
    {
        try {
            $id = PreloadService::create(Request::all());
        } catch (HttpException $e) {
            Audit::log('PRELOAD_CREATE', 'preload_users', null, 'failure');
            throw $e;
        }
        Audit::log('PRELOAD_CREATE', 'preload_users', $id);
        Response::ok(PreloadService::find($id), 'Preloaded employee added.', 201);
    }

    public function update(string $id): never
    {
        $id = $this->intId($id);
        try {
            PreloadService::update($id, Request::all());
        } catch (HttpException $e) {
            Audit::log('PRELOAD_UPDATE', 'preload_users', $id, 'failure');
            throw $e;
        }
        Audit::log('PRELOAD_UPDATE', 'preload_users', $id);
        Response::ok(PreloadService::find($id), 'Preloaded employee updated.');
    }

    public function destroy(string $id): never
    {
        $id = $this->intId($id);
        try {
            PreloadService::delete($id);
        } catch (HttpException $e) {
            Audit::log('PRELOAD_DELETE', 'preload_users', $id, 'failure');
            throw $e;
        }
        Audit::log('PRELOAD_DELETE', 'preload_users', $id);
        Response::ok(null, 'Preloaded employee deleted.');
    }
}
