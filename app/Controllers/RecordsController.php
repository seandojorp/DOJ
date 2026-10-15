<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\EmployeeRecords;
use App\Services\Modules;
use App\Services\PersonalInfoService;
use App\Services\PhotoService;
use App\Services\ProfileService;

/**
 * Employee-information API used by both roles.
 *
 * Ownership rule: for regular users the target employee is ALWAYS the signed-in account
 * (any submitted user_id is ignored), and every record touched by id is checked to belong to
 * that account. Administrators may name any existing account with user_id.
 */
final class RecordsController extends Controller
{
    /** Resolve which employee (User_ID) a request operates on. */
    private function targetUserId(): int
    {
        $me = Auth::user();
        if ($me['Role'] !== 'admin') {
            return $me['User_ID'];
        }
        $raw = Request::input('user_id');
        if ($raw === null || $raw === '') {
            throw new HttpException(400, 'Select an employee.');
        }
        $id = $this->intId($raw);
        if (!Database::value('SELECT 1 FROM ems_users WHERE User_ID = ?', [$id])) {
            throw new HttpException(404, 'Employee account not found.');
        }
        return $id;
    }

    private function module(string $slug): array
    {
        $m = Modules::get($slug);
        if ($m === null || ($m['special'] ?? null) === 'personal') {
            throw new HttpException(404, 'Unknown information section.');
        }
        return $m;
    }

    /** Load a record and enforce ownership for regular users (404 rather than 403, to avoid probing). */
    private function ownedRecord(string $slug, int $id): array
    {
        $row = EmployeeRecords::find($slug, $id);
        $me = Auth::user();
        if ($row === null) {
            throw new HttpException(404, 'Record not found.');
        }
        if ($me['Role'] !== 'admin' && (int) $row['User_ID'] !== $me['User_ID']) {
            Audit::log('ACCESS_DENIED_FOREIGN_RECORD', Modules::get($slug)['table'], $id, 'failure');
            throw new HttpException(404, 'Record not found.');
        }
        return $row;
    }

    public function index(string $module): never
    {
        $m = $this->module($module);
        $uid = $this->targetUserId();
        $meta = [];
        if ($module === 'children') {
            $meta['next_child_number'] = EmployeeRecords::nextChildNumber($uid);
        }
        Response::ok(['rows' => EmployeeRecords::listForUser($module, $uid), 'single' => !empty($m['single'])] + $meta);
    }

    public function all(string $module): never
    {
        if ($module === 'personal') {
            Response::ok(['rows' => PersonalInfoService::listAll()]);
        }
        $this->module($module);
        Response::ok(['rows' => EmployeeRecords::listAll($module)]);
    }

    public function store(string $module): never
    {
        $m = $this->module($module);
        $uid = $this->targetUserId();
        [$data, $errors] = Modules::validate($module, Request::all());
        if ($errors) {
            Audit::log('RECORD_CREATE', $m['table'], null, 'failure');
            throw new ValidationException($errors);
        }
        $id = EmployeeRecords::create($module, $uid, $data);
        Audit::log('RECORD_CREATE', $m['table'], $id);
        Response::ok(EmployeeRecords::find($module, $id), $m['title'] . ' saved.', 201);
    }

    public function update(string $module, string $id): never
    {
        $m = $this->module($module);
        $id = $this->intId($id);
        $this->ownedRecord($module, $id);
        [$data, $errors] = Modules::validate($module, Request::all());
        if ($errors) {
            Audit::log('RECORD_UPDATE', $m['table'], $id, 'failure');
            throw new ValidationException($errors);
        }
        EmployeeRecords::update($module, $id, $data); // User_ID is never part of $data, so ownership cannot be reassigned
        Audit::log('RECORD_UPDATE', $m['table'], $id);
        Response::ok(EmployeeRecords::find($module, $id), $m['title'] . ' updated.');
    }

    /** Administrators only (enforced by the route guard). */
    public function destroy(string $module, string $id): never
    {
        $m = $this->module($module);
        $id = $this->intId($id);
        $this->ownedRecord($module, $id);
        EmployeeRecords::delete($module, $id);
        Audit::log('RECORD_DELETE', $m['table'], $id);
        Response::ok(null, 'Record deleted.');
    }

    // ---- personal-information ------------------------------------------------------

    public function personalShow(): never
    {
        $uid = $this->targetUserId();
        $row = PersonalInfoService::get($uid);
        Response::ok([
            'record'   => $row,
            'identity' => PersonalInfoService::identity($uid),
            'photo'    => $row && $row['has_photo'] ? url('/photo/' . $uid) . '?t=' . time() : null,
        ]);
    }

    public function personalSave(): never
    {
        $uid = $this->targetUserId();
        $photo = null;
        $file = Request::file('Photo');
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $photo = PhotoService::processUpload($file);
        }
        try {
            $result = PersonalInfoService::save($uid, Request::all(), $photo);
        } catch (HttpException $e) {
            Audit::log('RECORD_SAVE', 'personal-information', null, 'failure');
            throw $e;
        }
        Audit::log($result['created'] ? 'RECORD_CREATE' : 'RECORD_UPDATE', 'personal-information', $result['id']);
        if ($photo !== null) {
            Audit::log('PHOTO_UPDATE', 'personal-information', $result['id']);
        }
        Response::ok(['id' => $result['id']], 'Personal information saved.', $result['created'] ? 201 : 200);
    }

    public function photo(string $id): never
    {
        $id = $this->intId($id);
        $me = Auth::user();
        if ($me['Role'] !== 'admin' && $id !== $me['User_ID']) {
            throw new HttpException(404, 'Not found.');
        }
        $bytes = PersonalInfoService::photo($id);
        if ($bytes === null) {
            throw new HttpException(404, 'Not found.');
        }
        header('Content-Type: ' . PhotoService::mimeOf($bytes));
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, no-store');
        header('Content-Disposition: inline; filename="photo"');
        echo $bytes;
        exit;
    }

    public function completion(): never
    {
        Response::ok(ProfileService::completion($this->targetUserId()));
    }
}
