<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Services\Modules;
use App\Services\PersonalInfoService;
use App\Services\ProfileService;

/** Regular-user pages. Every query is scoped to the signed-in account's User_ID. */
final class UserController extends Controller
{
    public function dashboard(): never
    {
        $uid = (int) Auth::id();
        $personal = PersonalInfoService::get($uid);
        $employment = Database::one(
            'SELECT `Original_Office`, `Reassignment_Office`, `Detailed_Office`, `Date_of_Entry`, `Current_Position`, `Salary_Grade`, `Date_Latest_Promotion`
               FROM `employment-information` WHERE `User_ID` = ? ORDER BY `Employment_ID` DESC LIMIT 1',
            [$uid]
        );
        $this->render('user/dashboard', 'Dashboard', [
            'personal'   => $personal,
            'employment' => $employment,
            'completion' => ProfileService::completion($uid),
        ], 'dashboard');
    }

    public function profile(): never
    {
        $this->render('user/profile', 'My Employee Profile', [
            'completion' => ProfileService::completion((int) Auth::id()),
        ], 'profile');
    }

    public function section(string $module): never
    {
        $m = Modules::get($module);
        if ($m === null || !empty($m['hidden'])) {
            throw new HttpException(404, 'Unknown information section.');
        }
        $defs = [Modules::clientDefinition($module)];
        if (!empty($m['sub'])) {
            $defs[] = Modules::clientDefinition($m['sub']);
        }
        $this->render('user/section', $m['title'], ['module' => Modules::clientDefinition($module), 'defs' => $defs], 'mod-' . $module);
    }
}
