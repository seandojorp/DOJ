<?php
declare(strict_types=1);
/**
 * Phase 1 check: verifies the imported database matches the authoritative fresh-install schema
 * (13 tables, required columns, unique keys, RESTRICT foreign keys). Read-only; changes nothing.
 *
 *   php tools/verify_schema.php
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
$c = (require dirname(__DIR__) . '/config/config.php')['db'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']), $c['user'], $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$ok = 0; $bad = 0;
$check = function (string $label, bool $pass, string $info = '') use (&$ok, &$bad) {
    $pass ? $ok++ : $bad++;
    echo ($pass ? "  [OK]   " : "  [FAIL] ") . $label . ($info !== '' ? "  ($info)" : '') . PHP_EOL;
};
$expected = [
    'preload_users' => ['Preload_Users_ID', 'DOJ_ID', 'Cellphone_No', 'Email_Address'],
    'ems_users' => ['User_ID', 'Preload_Users_ID', 'UserName', 'password_hash', 'Role', 'Account_Status', 'Created_At', 'Updated_At', 'Last_Login'],
    'audit_logs' => ['Audit_ID', 'Actor_User_ID', 'Actor_Username', 'Actor_Role', 'Action', 'Affected_Table', 'Affected_Record_ID', 'Action_Result', 'IP_Address', 'Created_At'],
    'personal-information' => ['Personal_ID', 'User_ID', 'Photo', 'Full_Name', 'Birthday', 'Mobile_No', 'Email_Address'],
    'family_background' => ['FB_ID', 'User_ID', 'Spouse_Surname', 'Mother_MN'],
    'children-information' => ['CI_ID', 'User_ID', 'Child_Number', 'Full_Name', 'Birthday'],
    'educational-background' => ['EB_ID', 'User_ID', 'Level', 'Scholarship_Honors'],
    'civil_service_eligibility' => ['CS_Eligibility_ID', 'User_ID', 'Eligibility_Tittle', 'Rating', 'Employee_ID'],
    'work_experience' => ['Work_Experience_ID', 'User_ID', 'Position_Tittle', 'Mothly_Salary', 'Govt_Private'],
    'voluntary_work' => ['Voluntary_Work_ID', 'User_ID', 'Position_Nature_Work'],
    'learning_development' => ['Learning_Development_ID', 'User_ID', 'Tittle', 'Type', 'Conducted_Sponsored'],
    'employment-information' => ['Employment_ID', 'User_ID', 'Original_Office', 'Salary_Grade'],
    'other_information' => ['Other_Information_ID', 'User_ID', 'Skill_Hobbies', 'Association_Organization'],
];
echo "Database: {$c['name']} on {$c['host']}\n\nTables (SHOW TABLES)\n";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$check('13 tables present', count($tables) === 13, count($tables) . ' found');
foreach ($expected as $t => $cols) {
    if (!in_array($t, $tables, true)) { $check("table `$t`", false, 'missing'); continue; }
    $have = array_column($pdo->query('DESCRIBE `' . $t . '`')->fetchAll(), 'Field');
    $missing = array_diff($cols, $have);
    $check("`$t` columns", !$missing, $missing ? 'missing: ' . implode(', ', $missing) : count($have) . ' columns');
}
echo "\nKeys and types\n";
$create = fn(string $t) => (string) $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch()['Create Table'];
$cu = $create('ems_users'); $cp = $create('preload_users');
$check('ems_users UNIQUE(UserName)', (bool) preg_match('/UNIQUE KEY `[^`]+` \(`UserName`\)/', $cu));
$check('ems_users UNIQUE(Preload_Users_ID)', (bool) preg_match('/UNIQUE KEY `[^`]+` \(`Preload_Users_ID`\)/', $cu));
foreach (['DOJ_ID', 'Cellphone_No', 'Email_Address'] as $col) {
    $check("preload_users UNIQUE($col)", (bool) preg_match('/UNIQUE KEY `[^`]+` \(`' . $col . '`\)/', $cp));
}
$check('personal-information UNIQUE(User_ID)', (bool) preg_match('/UNIQUE KEY `[^`]+` \(`User_ID`\)/', $create('personal-information')));
$check('civil_service_eligibility.Rating is DECIMAL(5,2)', (bool) preg_match('/`Rating` decimal\(5,2\)/i', $create('civil_service_eligibility')));
$check('voluntary_work.Position_Nature_Work is VARCHAR(255)', (bool) preg_match('/`Position_Nature_Work` varchar\(255\)/i', $create('voluntary_work')));
echo "\nForeign keys\n";
$fks = $pdo->query("SELECT TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()")->fetchAll();
$check('foreign keys present', count($fks) >= 11, count($fks) . ' found');
foreach ($fks as $fk) {
    $check("{$fk['TABLE_NAME']} → {$fk['REFERENCED_TABLE_NAME']} ON DELETE {$fk['DELETE_RULE']}", in_array($fk['DELETE_RULE'], ['RESTRICT', 'NO ACTION'], true));
}
echo "\nData\n";
$users = (int) $pdo->query('SELECT COUNT(*) FROM ems_users')->fetchColumn();
echo "  ems_users rows: $users, preload_users rows: " . $pdo->query('SELECT COUNT(*) FROM preload_users')->fetchColumn() . "\n";
echo "\n$ok passed, $bad failed\n";
exit($bad ? 1 : 0);
