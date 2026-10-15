<?php
declare(strict_types=1);

/**
 * DOJ-NPS EMS — end-to-end acceptance test suite.
 *
 * Drives the running application over HTTP (exactly as a browser would, including CSRF tokens and
 * session cookies) and checks database state directly.
 *
 * !! Run ONLY against a disposable TEST database that was freshly imported from the authoritative
 * !! schema with no data. The suite creates the first administrator and test records.
 *
 * Usage:
 *   php tests/run_tests.php http://localhost/doj-ems
 *   php tests/run_tests.php http://127.0.0.1:8080
 *
 * The database connection is read from config/config.php (the same database the app uses).
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
$base = rtrim($argv[1] ?? 'http://localhost/doj-ems', '/');
$config = require dirname(__DIR__) . '/config/config.php';
$db = $config['db'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$q = static function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s; };

if ((int) $q('SELECT COUNT(*) FROM ems_users')->fetchColumn() > 0 || (int) $q('SELECT COUNT(*) FROM preload_users')->fetchColumn() > 0) {
    fwrite(STDERR, "ABORTED: the database is not empty. Run this suite only against a freshly imported, empty TEST database.\n");
    exit(2);
}

// ---------------------------------------------------------------------------------------------
final class Client
{
    public string $jar;
    public string $csrf = '';
    public function __construct(public string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'ems');
    }
    public function handle(string $method, string $path, mixed $body = null, array $headers = [], bool $json = true)
    {
        $ch = curl_init($this->base . $path);
        $defaults = ['Accept' => 'Accept: application/json', 'X-CSRF-Token' => 'X-CSRF-Token: ' . $this->csrf];
        foreach ($headers as $hdr) {
            unset($defaults[strtok($hdr, ':')]); // explicit headers replace the defaults
        }
        $h = array_merge(array_values($defaults), $headers);
        $opts = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
                 CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30];
        if ($body !== null) {
            if ($json && is_array($body)) {
                $h[] = 'Content-Type: application/json';
                $opts[CURLOPT_POSTFIELDS] = json_encode($body);
            } else {
                $opts[CURLOPT_POSTFIELDS] = $body;
            }
        }
        $opts[CURLOPT_HTTPHEADER] = $h;
        curl_setopt_array($ch, $opts);
        return $ch;
    }
    public static function parse($ch, string $raw): array
    {
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $head = substr($raw, 0, $size);
        $body = substr($raw, $size);
        preg_match('/^Location:\s*(.+)$/mi', $head, $m);
        return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'json' => json_decode($body, true), 'body' => $body,
                'location' => isset($m[1]) ? trim($m[1]) : null, 'headers' => $head];
    }
    public function req(string $method, string $path, mixed $body = null, array $headers = [], bool $json = true): array
    {
        $ch = $this->handle($method, $path, $body, $headers, $json);
        $raw = (string) curl_exec($ch);
        $r = self::parse($ch, $raw);
        curl_close($ch);
        return $r;
    }
    public function page(string $path): array
    {
        $r = $this->req('GET', $path, null, ['Accept: text/html']);
        if (preg_match('/<meta name="csrf-token" content="([a-f0-9]+)"/', $r['body'], $m)) {
            $this->csrf = $m[1];
        }
        return $r;
    }
    public function sessionId(): ?string
    {
        foreach (file($this->jar) ?: [] as $line) {
            $p = explode("\t", trim($line));
            if (count($p) === 7 && $p[5] === 'DOJEMS_SID') return $p[6];
        }
        return null;
    }
    public function login(string $u, string $p): array
    {
        $this->page('/login');
        $r = $this->req('POST', '/api/auth/login', ['username' => $u, 'password' => $p]);
        if ($r['status'] === 200) { // the CSRF token rotates on login; pick up the new one
            $this->page($r['json']['data']['role'] === 'admin' ? '/admin/settings' : '/user/settings');
        }
        return $r;
    }
}

/** Fire the same kind of request from several clients at once. */
function parallel(array $clients, string $method, string $path, array $bodies): array
{
    $mh = curl_multi_init();
    $handles = [];
    foreach ($clients as $i => $c) {
        $handles[$i] = $c->handle($method, $path, $bodies[$i]);
        curl_multi_add_handle($mh, $handles[$i]);
    }
    do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);
    $out = [];
    foreach ($handles as $i => $ch) {
        $out[$i] = Client::parse($ch, (string) curl_multi_getcontent($ch));
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);
    return $out;
}

$pass = 0; $fail = 0; $section = '';
function section(string $s): void { global $section; $section = $s; echo "\n\033[1m== $s ==\033[0m\n"; }
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  \033[32m✔\033[0m $name\n"; }
    else { $fail++; echo "  \033[31m✘ $name\033[0m" . ($detail !== '' ? "  → $detail" : '') . "\n"; }
}
function brief(array $r): string { return $r['status'] . ' ' . substr(json_encode($r['json'] ?? $r['body']), 0, 300); }

function pngBytes(): string
{
    $im = imagecreatetruecolor(300, 360);
    imagefill($im, 0, 0, imagecolorallocate($im, 18, 48, 74));
    imagefilledellipse($im, 150, 140, 140, 160, imagecolorallocate($im, 212, 175, 55));
    ob_start(); imagepng($im); return (string) ob_get_clean();
}

const ADMIN_PW = 'Adm1n!Secure#2026';
const USER_PW  = 'Empl0yee!Pass#1';

// =============================================================================================
section('First-run administrator setup');
$anon = new Client($base);
$r = $anon->page('/');
check('Uninitialized system redirects to /setup', $r['status'] === 302 && str_ends_with((string) $r['location'], '/setup'), brief($r));
$r = $anon->page('/login');
check('Login page also redirects to /setup before initialization', $r['status'] === 302 && str_ends_with((string) $r['location'], '/setup'));
$anon->page('/setup');
$r = $anon->req('POST', '/api/setup', ['username' => 'sysadmin', 'password' => 'short', 'confirm_password' => 'short']);
check('Weak administrator password rejected (422)', $r['status'] === 422 && isset($r['json']['errors']['password']), brief($r));
$r = $anon->req('POST', '/api/setup', ['username' => 'sysadmin', 'password' => ADMIN_PW, 'confirm_password' => ADMIN_PW . 'x']);
check('Mismatched confirmation rejected', $r['status'] === 422 && isset($r['json']['errors']['confirm_password']));
$r = (new Client($base))->req('POST', '/api/setup', ['username' => 'sysadmin', 'password' => ADMIN_PW, 'confirm_password' => ADMIN_PW]);
check('Setup without CSRF token rejected (419)', $r['status'] === 419, brief($r));

// Concurrent submissions: exactly one administrator may be created.
$racers = [];
$bodies = [];
for ($i = 0; $i < 5; $i++) {
    $c = new Client($base); $c->page('/setup'); $racers[] = $c;
    $bodies[] = ['username' => "sysadmin$i", 'password' => ADMIN_PW, 'confirm_password' => ADMIN_PW];
}
$res = parallel($racers, 'POST', '/api/setup', $bodies);
$created = array_filter($res, static fn($x) => $x['status'] === 201);
check('5 simultaneous setup submissions create exactly one administrator', count($created) === 1
    && (int) $q("SELECT COUNT(*) FROM ems_users WHERE Role='admin'")->fetchColumn() === 1,
    implode(',', array_map(static fn($x) => $x['status'], $res)));
$adminName = $q("SELECT UserName FROM ems_users WHERE Role='admin'")->fetchColumn();
$adminRow = $q('SELECT * FROM ems_users WHERE UserName = ?', [$adminName])->fetch();
check('First admin has NULL Preload_Users_ID, role admin, active', $adminRow['Preload_Users_ID'] === null && $adminRow['Role'] === 'admin' && $adminRow['Account_Status'] === 'active');
check('Password stored as a bcrypt/argon hash (not plaintext)', str_starts_with($adminRow['password_hash'], '$2y$') || str_starts_with($adminRow['password_hash'], '$argon2'));
check('SYSTEM_INITIALIZED durable marker written to audit_logs', (int) $q("SELECT COUNT(*) FROM audit_logs WHERE Action='SYSTEM_INITIALIZED' AND Action_Result='success'")->fetchColumn() === 1);
$c = new Client($base);
$r = $c->page('/setup');
check('GET /setup after initialization redirects to /login', $r['status'] === 302 && str_ends_with((string) $r['location'], '/login'));
$c->page('/login');
$r = $c->req('POST', '/api/setup', ['username' => 'intruder', 'password' => ADMIN_PW, 'confirm_password' => ADMIN_PW]);
check('POST /api/setup after initialization is refused (409)', $r['status'] === 409 && !$q("SELECT 1 FROM ems_users WHERE UserName='intruder'")->fetchColumn(), brief($r));

// =============================================================================================
section('Login and sessions');
$admin = new Client($base);
$admin->page('/login');
$sidBefore = $admin->sessionId();
$r = $admin->req('POST', '/api/auth/login', ['username' => $adminName, 'password' => 'wrong-password']);
check('Wrong password rejected with generic message (401)', $r['status'] === 401 && $r['json']['message'] === 'Invalid username or password.');
$r = $admin->req('POST', '/api/auth/login', ['username' => "' OR '1'='1", 'password' => "' OR '1'='1"]);
check('SQL-injection style credentials rejected (401)', $r['status'] === 401);
$r = $admin->login($adminName, ADMIN_PW);
check('Administrator signs in and is sent to /admin/dashboard', $r['status'] === 200 && str_ends_with($r['json']['data']['redirect'], '/admin/dashboard'), brief($r));
check('Session ID regenerated on login (fixation defence)', $admin->sessionId() !== null && $admin->sessionId() !== $sidBefore);
check('Last_Login updated on successful login', $q('SELECT Last_Login FROM ems_users WHERE UserName = ?', [$adminName])->fetchColumn() !== null);
$r = $admin->req('GET', '/api/admin/stats');
check('Admin API reachable when signed in', $r['status'] === 200 && isset($r['json']['data']['preloaded']));
$r = $admin->req('POST', '/api/admin/preload', ['DOJ_ID' => 'X'], ['X-CSRF-Token: bogus']);
check('State-changing request with a wrong CSRF token rejected (419)', $r['status'] === 419);
$r = $admin->page('/admin/dashboard');
check('Admin dashboard renders', $r['status'] === 200 && str_contains($r['body'], 'Administrator Dashboard'));
foreach (['/admin/employees', '/admin/preload-users', '/admin/accounts', '/admin/audit-logs', '/admin/settings', '/admin/records/personal',
          '/admin/records/family', '/admin/records/education', '/admin/records/eligibility', '/admin/records/work', '/admin/records/voluntary',
          '/admin/records/learning', '/admin/records/employment', '/admin/records/other'] as $p) {
    $r = $admin->page($p);
    check("Admin page $p renders (200)", $r['status'] === 200, (string) $r['status'] . substr(strip_tags($r['body']), 0, 200));
}
$r = (new Client($base))->req('GET', '/api/admin/stats');
check('Admin API without a session returns 401', $r['status'] === 401);
$r = (new Client($base))->page('/admin/dashboard');
check('Admin page without a session redirects to /login', $r['status'] === 302 && str_ends_with((string) $r['location'], '/login'));

// =============================================================================================
section('Preloaded Users');
$emp = [
    'A' => ['DOJ_ID' => 'doj-2026-001', 'Cellphone_No' => '+63 917 123 4567', 'Email_Address' => 'Maria.Santos@Example.gov.ph'],
    'B' => ['DOJ_ID' => 'DOJ-2026-002', 'Cellphone_No' => '09181234567', 'Email_Address' => 'juan.delacruz@example.gov.ph'],
    'C' => ['DOJ_ID' => 'DOJ-2026-003', 'Cellphone_No' => '09191234567', 'Email_Address' => 'ana.reyes@example.gov.ph'],
    'D' => ['DOJ_ID' => 'DOJ-2026-004', 'Cellphone_No' => '09201234567', 'Email_Address' => 'pedro.cruz@example.gov.ph'],
    'E' => ['DOJ_ID' => 'DOJ-2026-005', 'Cellphone_No' => '09211234567', 'Email_Address' => 'liza.garcia@example.gov.ph'],
];
$pid = [];
foreach ($emp as $k => $e) {
    $r = $admin->req('POST', '/api/admin/preload', $e);
    $pid[$k] = (int) ($r['json']['data']['Preload_Users_ID'] ?? 0);
    check("Create preloaded employee $k (201)", $r['status'] === 201, brief($r));
}
$rowA = $q('SELECT * FROM preload_users WHERE Preload_Users_ID = ?', [$pid['A']])->fetch();
check('Cellphone normalized (+63 917 123 4567 → 09171234567)', $rowA['Cellphone_No'] === '09171234567', $rowA['Cellphone_No']);
check('Email normalized to lowercase, DOJ ID trimmed/uppercased', $rowA['Email_Address'] === 'maria.santos@example.gov.ph' && $rowA['DOJ_ID'] === 'DOJ-2026-001');
$emp['A'] = ['DOJ_ID' => 'DOJ-2026-001', 'Cellphone_No' => '09171234567', 'Email_Address' => 'maria.santos@example.gov.ph'];
$r = $admin->req('POST', '/api/admin/preload', ['DOJ_ID' => 'DOJ-2026-001', 'Cellphone_No' => '09990000001', 'Email_Address' => 'new1@example.gov.ph']);
check('Duplicate DOJ ID rejected on its own', $r['status'] === 422 && array_keys($r['json']['errors']) === ['DOJ_ID'], brief($r));
$r = $admin->req('POST', '/api/admin/preload', ['DOJ_ID' => 'DOJ-NEW-1', 'Cellphone_No' => '0917-123-4567', 'Email_Address' => 'new2@example.gov.ph']);
check('Duplicate cellphone rejected on its own (after normalization)', $r['status'] === 422 && array_keys($r['json']['errors']) === ['Cellphone_No'], brief($r));
$r = $admin->req('POST', '/api/admin/preload', ['DOJ_ID' => 'DOJ-NEW-2', 'Cellphone_No' => '09990000003', 'Email_Address' => 'MARIA.SANTOS@example.gov.ph ']);
check('Duplicate email rejected on its own (case-insensitive)', $r['status'] === 422 && array_keys($r['json']['errors']) === ['Email_Address'], brief($r));
$r = $admin->req('POST', '/api/admin/preload', ['DOJ_ID' => '', 'Cellphone_No' => '12345', 'Email_Address' => 'not-an-email']);
check('Missing/invalid fields reported per field', $r['status'] === 422 && count($r['json']['errors']) === 3);
$r = $admin->req('GET', '/api/admin/preload?status=unregistered');
check('List filter: 5 unregistered, status derived from ems_users', $r['status'] === 200 && count($r['json']['data']['rows']) === 5 && $r['json']['data']['stats']['registered'] === 0);
$r = $admin->req('PUT', "/api/admin/preload/{$pid['E']}", ['DOJ_ID' => 'DOJ-2026-005', 'Cellphone_No' => '09211234568', 'Email_Address' => 'liza.garcia@example.gov.ph']);
check('Edit preloaded employee', $r['status'] === 200 && $r['json']['data']['Cellphone_No'] === '09211234568');
$emp['E']['Cellphone_No'] = '09211234568';
$r = $admin->req('PUT', "/api/admin/preload/{$pid['E']}", ['DOJ_ID' => 'DOJ-2026-002', 'Cellphone_No' => '09211234568', 'Email_Address' => 'liza.garcia@example.gov.ph']);
check('Edit cannot take another employee\'s DOJ ID', $r['status'] === 422 && isset($r['json']['errors']['DOJ_ID']));

// =============================================================================================
section('Employee registration');
$reg = static function (Client $c, array $e, string $user, string $pw = USER_PW, ?string $confirm = null) {
    $c->page('/register');
    return $c->req('POST', '/api/auth/register', ['doj_id' => $e['DOJ_ID'], 'cellphone' => $e['Cellphone_No'], 'email' => $e['Email_Address'],
        'username' => $user, 'password' => $pw, 'confirm_password' => $confirm ?? $pw]);
};
$r = $reg(new Client($base), ['DOJ_ID' => $emp['A']['DOJ_ID'], 'Cellphone_No' => $emp['B']['Cellphone_No'], 'Email_Address' => $emp['A']['Email_Address']], 'mixeduser');
check('Values from DIFFERENT preload records are rejected (generic message)', $r['status'] === 422 && $r['json']['message'] === App_GENERIC(), brief($r));
$r = $reg(new Client($base), ['DOJ_ID' => 'DOJ-NOPE', 'Cellphone_No' => '09170000000', 'Email_Address' => 'nobody@example.gov.ph'], 'ghost');
check('Unknown employee gets the same generic message (no enumeration)', $r['status'] === 422 && $r['json']['message'] === App_GENERIC());
foreach ([['short1!A', 'too short'], ['alllowercase1!', 'no uppercase'], ['ALLUPPERCASE1!', 'no lowercase'], ['NoDigitsHere!!', 'no number'], ['NoSpecial12345', 'no special'], ['1234567890', 'digits only']] as [$pw, $why]) {
    $r = $reg(new Client($base), $emp['A'], 'maria.santos', $pw);
    check("Password policy rejects '$why'", $r['status'] === 422 && isset($r['json']['errors']['password']));
}
$r = $reg(new Client($base), $emp['A'], 'maria.santos', USER_PW, USER_PW . '!');
check('Password confirmation mismatch rejected', $r['status'] === 422 && isset($r['json']['errors']['confirm_password']));
$r = $reg(new Client($base), $emp['A'], 'maria.santos');
check('Matching identity registers successfully (201)', $r['status'] === 201, brief($r));
$u = $q('SELECT * FROM ems_users WHERE UserName = ?', ['maria.santos'])->fetch();
check('New account is role=user, active, linked to the matching preload row', $u && $u['Role'] === 'user' && $u['Account_Status'] === 'active' && (int) $u['Preload_Users_ID'] === $pid['A']);
$r = $reg(new Client($base), $emp['A'], 'maria.second');
check('Second registration for the same employee rejected', $r['status'] === 422 && (int) $q('SELECT COUNT(*) FROM ems_users WHERE Preload_Users_ID = ?', [$pid['A']])->fetchColumn() === 1);
$r = $reg(new Client($base), $emp['B'], 'MARIA.SANTOS');
check('Username uniqueness enforced (case-insensitive)', $r['status'] === 422 && isset($r['json']['errors']['username']), brief($r));
// Concurrency: five simultaneous registrations for employee C with different usernames.
$racers = []; $bodies = [];
for ($i = 0; $i < 5; $i++) {
    $c = new Client($base); $c->page('/register'); $racers[] = $c;
    $bodies[] = ['doj_id' => $emp['C']['DOJ_ID'], 'cellphone' => $emp['C']['Cellphone_No'], 'email' => $emp['C']['Email_Address'],
                 'username' => "ana.reyes$i", 'password' => USER_PW, 'confirm_password' => USER_PW];
}
$res = parallel($racers, 'POST', '/api/auth/register', $bodies);
check('5 simultaneous registrations for one employee create exactly one account',
    count(array_filter($res, static fn($x) => $x['status'] === 201)) === 1 && (int) $q('SELECT COUNT(*) FROM ems_users WHERE Preload_Users_ID = ?', [$pid['C']])->fetchColumn() === 1,
    implode(',', array_map(static fn($x) => $x['status'], $res)));
$userC = $q('SELECT UserName FROM ems_users WHERE Preload_Users_ID = ?', [$pid['C']])->fetchColumn();
$r = $reg(new Client($base), $emp['B'], 'juan.delacruz');
check('Employee B registers', $r['status'] === 201);
check('No ordinary user has a NULL Preload_Users_ID', (int) $q("SELECT COUNT(*) FROM ems_users WHERE Role='user' AND Preload_Users_ID IS NULL")->fetchColumn() === 0);

// =============================================================================================
section('Role-based access control');
$maria = new Client($base);
$r = $maria->login('maria.santos', USER_PW);
check('User signs in and is sent to /user/dashboard', $r['status'] === 200 && str_ends_with($r['json']['data']['redirect'], '/user/dashboard'));
foreach (['/api/admin/stats', '/api/admin/preload', '/api/admin/accounts', '/api/admin/audit-logs', '/api/admin/employees', '/api/admin/records/work'] as $p) {
    $r = $maria->req('GET', $p);
    check("User blocked from $p (403)", $r['status'] === 403);
}
$r = $maria->req('POST', '/api/admin/preload', ['DOJ_ID' => 'HACK', 'Cellphone_No' => '09170000001', 'Email_Address' => 'h@x.ph']);
check('User cannot create preloaded users (403)', $r['status'] === 403 && !$q("SELECT 1 FROM preload_users WHERE DOJ_ID='HACK'")->fetchColumn());
$r = $maria->req('PUT', '/api/admin/accounts/' . $u['User_ID'], ['username' => 'maria', 'role' => 'admin']);
check('User cannot change own role through the admin API (403)', $r['status'] === 403 && $q('SELECT Role FROM ems_users WHERE User_ID = ?', [$u['User_ID']])->fetchColumn() === 'user');
$r = $maria->page('/admin/dashboard');
check('User blocked from admin pages (403)', $r['status'] === 403);
foreach (['/user/dashboard', '/user/profile', '/user/settings', '/user/profile/personal', '/user/profile/family', '/user/profile/education', '/user/profile/eligibility',
          '/user/profile/work', '/user/profile/voluntary', '/user/profile/learning', '/user/profile/employment', '/user/profile/other'] as $p) {
    $r = $maria->page($p);
    check("User page $p renders", $r['status'] === 200, (string) $r['status']);
}
$r = $admin->page('/user/dashboard');
check('Admin is not served the user dashboard (403)', $r['status'] === 403);

// =============================================================================================
section('Employee information — own records and ownership');
$juan = new Client($base);
$juan->login('juan.delacruz', USER_PW);
$juanId = (int) $q("SELECT User_ID FROM ems_users WHERE UserName='juan.delacruz'")->fetchColumn();
$mariaId = (int) $u['User_ID'];

$edu = ['Level' => 'College', 'Name_of_School' => 'University of the Philippines', 'Course' => 'BS Legal Management', 'Date_From' => '2010-06-01',
        'Date_To' => '2014-04-01', 'Unit_Earned' => 'N/A', 'Year_Graduated' => '2014', 'Scholarship_Honors' => 'Cum Laude'];
$r = $maria->req('POST', '/api/records/education', $edu + ['user_id' => $juanId, 'User_ID' => $juanId]);
check('User creates an education record (201)', $r['status'] === 201, brief($r));
$eduId = (int) ($r['json']['data']['EB_ID'] ?? 0);
check('Submitted user_id/User_ID ignored — record belongs to the signed-in user', (int) $q('SELECT User_ID FROM `educational-background` WHERE EB_ID = ?', [$eduId])->fetchColumn() === $mariaId);
$r = $maria->req('POST', '/api/records/education', ['Level' => 'Graduate Studies', 'Name_of_School' => 'Ateneo Law School', 'Course' => 'Juris Doctor', 'Date_From' => '2014-06-01',
        'Date_To' => '2018-04-01', 'Unit_Earned' => 'N/A', 'Year_Graduated' => '2018', 'Scholarship_Honors' => 'N/A']);
check('Multiple education records allowed per user', $r['status'] === 201 && (int) $q('SELECT COUNT(*) FROM `educational-background` WHERE User_ID = ?', [$mariaId])->fetchColumn() === 2);
$r = $juan->req('PUT', "/api/records/education/$eduId", $edu + ['Name_of_School' => 'Hacked']);
check("Another user cannot update this record by changing the id (404)", $r['status'] === 404 && $q('SELECT Name_of_School FROM `educational-background` WHERE EB_ID = ?', [$eduId])->fetchColumn() === 'University of the Philippines');
$r = $juan->req('GET', "/api/records/education?user_id=$mariaId");
check("Another user's user_id parameter is ignored when listing", $r['status'] === 200 && count($r['json']['data']['rows']) === 0);
$r = $maria->req('DELETE', "/api/records/education/$eduId");
check('Regular users cannot delete records (403)', $r['status'] === 403 && $q('SELECT 1 FROM `educational-background` WHERE EB_ID = ?', [$eduId])->fetchColumn());
$r = $maria->req('PUT', "/api/records/education/$eduId", array_merge($edu, ['Name_of_School' => 'UP Diliman']));
check('User updates own record', $r['status'] === 200 && $r['json']['data']['Name_of_School'] === 'UP Diliman');
$r = $maria->req('POST', '/api/records/education', array_merge($edu, ['Date_From' => '2015-01-01', 'Date_To' => '2014-01-01']));
check('Date_To earlier than Date_From rejected', $r['status'] === 422 && isset($r['json']['errors']['Date_To']));
$r = $maria->req('POST', '/api/records/education', ['Level' => 'College']);
check('Incomplete rows are not inserted (all NOT NULL fields required)', $r['status'] === 422 && count($r['json']['errors']) >= 6);
$r = $maria->req('POST', '/api/records/education', array_merge($edu, ['Level' => 'Kindergarten']));
check('Dropdown values validated server-side', $r['status'] === 422 && isset($r['json']['errors']['Level']));

$fam = ['Spouse_Surname' => 'N/A', 'Spouse_FN' => 'N/A', 'Spouse_MN' => 'N/A', 'Spouse_Occupation' => 'N/A', 'Spouse_Emp_Bus_Name' => 'N/A', 'Spouse_Bus_Add' => 'N/A',
        'Spouse_Mobile_No' => 'N/A', 'Father_Surname' => 'Santos', 'Father_FN' => 'Jose', 'Father_MN' => 'Reyes', 'Father_Ext_Name' => 'N/A',
        'Mother_Surname' => 'Cruz', 'Mother_FN' => 'Elena', 'Mother_MN' => 'Lopez'];
$r = $maria->req('POST', '/api/records/family', $fam);
check('Family background saved', $r['status'] === 201, brief($r));
$r = $maria->req('POST', '/api/records/family', $fam);
check('Second family background row refused (single-record section)', $r['status'] === 409);
foreach ([1 => 'Miguel Santos', 2 => 'Isabel Santos'] as $n => $name) {
    $r = $maria->req('POST', '/api/records/children', ['Child_Number' => $n, 'Full_Name' => $name, 'Birthday' => "201$n-05-0$n"]);
    check("Child #$n saved (children-information)", $r['status'] === 201, brief($r));
}
$r = $maria->req('POST', '/api/records/children', ['Child_Number' => 3, 'Full_Name' => 'Future Kid', 'Birthday' => date('Y-m-d', strtotime('+1 year'))]);
check('Future birthday rejected', $r['status'] === 422);
$r = $maria->req('POST', '/api/records/eligibility', ['Eligibility_Tittle' => 'RA 1080 (Bar)', 'Rating' => '85.40', 'Date_of_Exam_Conferment' => '2018-11-01',
        'Place_of_Examination' => 'Manila', 'Employee_ID' => '1001', 'License_Number' => '12345', 'Date_of_Validity' => '2030-11-01']);
check('Civil service eligibility saved with DECIMAL(5,2) rating', $r['status'] === 201 && $q('SELECT Rating FROM civil_service_eligibility WHERE User_ID = ?', [$mariaId])->fetchColumn() == '85.40', brief($r));
$r = $maria->req('POST', '/api/records/eligibility', ['Eligibility_Tittle' => 'X', 'Rating' => '120', 'Date_of_Exam_Conferment' => '2018-11-01',
        'Place_of_Examination' => 'Manila', 'Employee_ID' => '1001', 'License_Number' => 'N/A', 'Date_of_Validity' => '2030-11-01']);
check('Rating above 100 rejected', $r['status'] === 422 && isset($r['json']['errors']['Rating']));
$r = $maria->req('POST', '/api/records/work', ['Date_From' => '2019-01-02', 'Date_To' => '2024-12-31', 'Position_Tittle' => 'Prosecutor I', 'Name_of_Office' => 'Office of the City Prosecutor',
        'Mothly_Salary' => '75952.00', 'Salary_Grade' => '27-1', 'Status_of_Appointment' => 'Permanent', 'Govt_Private' => 'Government']);
check('Work experience saved (Position_Tittle / Mothly_Salary legacy columns)', $r['status'] === 201, brief($r));
$r = $maria->req('POST', '/api/records/voluntary', ['Name_Address_Organization' => 'IBP Legal Aid, Quezon City', 'Date_From' => '2016-01-01', 'Date_To' => '2016-12-31',
        'Number_Hours' => '120', 'Position_Nature_Work' => 'Volunteer paralegal']);
check('Voluntary work saved', $r['status'] === 201);
$r = $maria->req('POST', '/api/records/learning', ['Tittle' => 'Basic Course on Prosecution', 'Date_From' => '2019-03-01', 'Date_To' => '2019-03-15',
        'Number_Hours' => '80', 'Type' => '3', 'Conducted_Sponsored' => '1']);
check('Learning & development saved (INT Type / Conducted_Sponsored codes)', $r['status'] === 201, brief($r));
$r = $maria->req('POST', '/api/records/employment', ['Original_Office' => 'OCP Quezon City', 'Reassignment_Office' => 'N/A', 'Detailed_Office' => 'N/A', 'Date_of_Entry' => '2019-01-02',
        'Entry_Position' => 'Prosecutor I', 'Date_Previous_Promotion' => '2021-01-04', 'Position_Previous_Promotion' => 'Prosecutor II', 'Date_Latest_Promotion' => '2024-01-02',
        'Current_Position' => 'Prosecutor III', 'Salary_Grade' => 'SG 29']);
check('Employment information saved', $r['status'] === 201, brief($r));
$r = $maria->req('POST', '/api/records/other', ['Skill_Hobbies' => 'Legal research; chess', 'Distinction_Recognition' => 'N/A', 'Association_Organization' => 'Integrated Bar of the Philippines']);
check('Other information saved', $r['status'] === 201);
$r = $maria->req('GET', '/api/records/children');
check('Children listed in order with next child number', $r['status'] === 200 && count($r['json']['data']['rows']) === 2 && $r['json']['data']['next_child_number'] === 3);
$r = $maria->req('GET', '/api/records/does_not_exist');
check('Unknown section returns 404 (no table-name injection)', $r['status'] === 404);
$r = $maria->req('GET', '/api/records/' . rawurlencode('education`;DROP TABLE audit_logs;--'));
check('Malicious section name is rejected', in_array($r['status'], [404], true) && $q("SHOW TABLES LIKE 'audit_logs'")->fetchColumn());

// =============================================================================================
section('Personal information, photo and protected identity');
$png = pngBytes();
$tmp = tempnam(sys_get_temp_dir(), 'png') . '.png';
file_put_contents($tmp, $png);
$personal = [
    'Full_Name' => 'SANTOS, MARIA <script>alert(1)</script> CRUZ', 'Birthday' => '1990-05-14', 'Place_of_Birth' => 'Quezon City', 'Sex' => 'Female', 'Civil_Status' => 'Single',
    'Citizenship' => 'Filipino', 'Height' => '1.60', 'Weight' => '55.50', 'Blood_type' => 'O+', 'GSIS_No' => '1234567890', 'PAGIBIG_No' => '1212-3434-5656',
    'PHILHEALTH_No' => '12-345678901-2', 'SSS_No' => 'N/A', 'TIN_No' => '123-456-789-000', 'Agency_Employee_No' => 'NPS-0001',
    'Residential_No' => '12', 'Residential_Street' => 'Mabini St.', 'Residential_Sub_Village' => 'N/A', 'Residential_Barangay' => 'Krus na Ligas',
    'Residential_City_Municipality' => 'Quezon City', 'Residential_Province' => 'Metro Manila', 'Residential_Zip_Code' => '1101',
    'Permanent_No' => '12', 'Permanent_Street' => 'Mabini St.', 'Permanent_Sub_Village' => 'N/A', 'Permanent_Barangay' => 'Krus na Ligas',
    'Permanent_City_Municipality' => 'Quezon City', 'Permanent_Province' => 'Metro Manila', 'Permanent_Zip_Code' => '1101',
    'Telephone_No' => 'N/A', 'Mobile_No' => '09999999999', 'Email_Address' => 'attacker@evil.example',
];
$r = $maria->req('POST', '/api/personal', $personal, [], false);
check('Personal information without a photo is refused on first save', $r['status'] === 422 && isset($r['json']['errors']['Photo']), brief($r));
$r = $maria->req('POST', '/api/personal', $personal + ['Photo' => new CURLFile($tmp, 'image/png', 'me.png')], [], false);
check('Personal information with photo saved (201)', $r['status'] === 201, brief($r));
$pi = $q('SELECT Mobile_No, Email_Address, LENGTH(Photo) AS len, Full_Name FROM `personal-information` WHERE User_ID = ?', [$mariaId])->fetch();
check('User cannot override registered cellphone/email via personal fields', $pi['Mobile_No'] === '09171234567' && $pi['Email_Address'] === 'maria.santos@example.gov.ph', json_encode($pi));
check('Photo stored (re-encoded) in LONGBLOB', (int) $pi['len'] > 100);
$r = $maria->req('POST', '/api/personal', array_merge($personal, ['Civil_Status' => 'Married']), [], false);
check('Second save updates the single personal-information row', $r['status'] === 200 && (int) $q('SELECT COUNT(*) FROM `personal-information` WHERE User_ID = ?', [$mariaId])->fetchColumn() === 1);
$fake = tempnam(sys_get_temp_dir(), 'f') . '.png';
file_put_contents($fake, "<?php echo 'x'; ?>");
$r = $maria->req('POST', '/api/personal', $personal + ['Photo' => new CURLFile($fake, 'image/png', 'evil.png')], [], false);
check('Non-image file disguised as PNG rejected', $r['status'] === 422 && isset($r['json']['errors']['Photo']));
$r = $maria->req('GET', "/photo/$mariaId", null, ['Accept: image/*']);
check('Owner can view own photo', $r['status'] === 200 && str_contains($r['headers'], 'image/jpeg'));
$r = $juan->req('GET', "/photo/$mariaId", null, ['Accept: image/*']);
check("Other users cannot view someone else's photo", $r['status'] === 404);
$r = $admin->req('GET', "/photo/$mariaId", null, ['Accept: image/*']);
check('Administrator can view any photo', $r['status'] === 200);
$r = $maria->page('/user/dashboard');
check('Stored HTML is escaped on server-rendered pages (XSS)', $r['status'] === 200 && !str_contains($r['body'], '<script>alert(1)</script>') && str_contains($r['body'], '&lt;script&gt;'));
$r = $maria->req('GET', '/api/profile/completion');
check('Profile completion reaches 100% with all required sections', $r['status'] === 200 && $r['json']['data']['percent'] === 100, brief($r));

// =============================================================================================
section('Administrator management of employee information');
$r = $admin->req('GET', "/api/records/work?user_id=$mariaId");
check('Admin lists a specific employee\'s records', $r['status'] === 200 && count($r['json']['data']['rows']) === 1);
$r = $admin->req('POST', '/api/records/work', ['user_id' => $juanId, 'Date_From' => '2015-01-01', 'Date_To' => '2018-12-31', 'Position_Tittle' => 'Legal Assistant',
        'Name_of_Office' => 'DOJ Main', 'Mothly_Salary' => '30000', 'Salary_Grade' => '11-2', 'Status_of_Appointment' => 'Permanent', 'Govt_Private' => 'Government']);
check('Admin inserts a record for another employee', $r['status'] === 201 && (int) $q('SELECT COUNT(*) FROM work_experience WHERE User_ID = ?', [$juanId])->fetchColumn() === 1, brief($r));
$wid = (int) $r['json']['data']['Work_Experience_ID'];
$r = $admin->req('GET', '/api/admin/records/work');
check('Admin cross-employee listing includes both employees', $r['status'] === 200 && count($r['json']['data']['rows']) === 2);
$r = $admin->req('DELETE', "/api/records/work/$wid");
check('Admin deletes an employee record', $r['status'] === 200 && !$q('SELECT 1 FROM work_experience WHERE Work_Experience_ID = ?', [$wid])->fetchColumn());
$r = $admin->req('GET', '/api/records/work');
check('Admin must name an employee (no implicit target)', $r['status'] === 400);
$r = $admin->req('GET', '/api/admin/employees');
check('Employee search list returns completion', $r['status'] === 200 && count($r['json']['data']['rows']) >= 3);
$r = $admin->page("/admin/employees/$mariaId");
check('Admin employee profile page renders', $r['status'] === 200 && str_contains($r['body'], 'DOJ-2026-001'));

// =============================================================================================
section('Account management by administrator');
$r = $admin->req('PUT', "/api/admin/accounts/$mariaId", ['username' => 'maria.santos', 'role' => 'user', 'doj_id' => 'DOJ-2026-001',
        'cellphone' => '0917 765 4321', 'email' => 'maria.santos@doj.gov.ph']);
check('Admin edits registered cellphone/email', $r['status'] === 200, brief($r));
$pi = $q('SELECT Mobile_No, Email_Address FROM `personal-information` WHERE User_ID = ?', [$mariaId])->fetch();
check('Change synchronized to personal-information contact fields', $pi['Mobile_No'] === '09177654321' && $pi['Email_Address'] === 'maria.santos@doj.gov.ph', json_encode($pi));
check('Account remains linked to the same preload record', (int) $q('SELECT Preload_Users_ID FROM ems_users WHERE User_ID = ?', [$mariaId])->fetchColumn() === $pid['A']);
$r = $admin->req('PUT', "/api/admin/accounts/$mariaId", ['username' => 'juan.delacruz', 'role' => 'user', 'doj_id' => 'DOJ-2026-001', 'cellphone' => '09177654321', 'email' => 'maria.santos@doj.gov.ph']);
check('Username collision on edit rejected', $r['status'] === 422 && isset($r['json']['errors']['username']));
$r = $admin->req('PUT', "/api/admin/accounts/$mariaId", ['username' => 'maria.santos', 'role' => 'user', 'doj_id' => 'DOJ-2026-002', 'cellphone' => '09177654321', 'email' => 'maria.santos@doj.gov.ph']);
check("Cannot assign another employee's DOJ ID", $r['status'] === 422 && isset($r['json']['errors']['doj_id']));
$adminId = (int) $adminRow['User_ID'];
$r = $admin->req('POST', "/api/admin/accounts/$adminId/status", ['status' => 'inactive']);
check('Admin cannot deactivate own account', $r['status'] === 422 && $q('SELECT Account_Status FROM ems_users WHERE User_ID = ?', [$adminId])->fetchColumn() === 'active');
$r = $admin->req('DELETE', "/api/admin/accounts/$adminId");
check('Admin cannot delete own account', $r['status'] === 422);
$r = $admin->req('POST', '/api/admin/accounts', ['role' => 'admin', 'username' => 'second.admin', 'password' => ADMIN_PW, 'confirm_password' => ADMIN_PW]);
check('Admin creates a second administrator', $r['status'] === 201, brief($r));
$secondId = (int) $r['json']['data']['User_ID'];
$r = $admin->req('POST', '/api/admin/accounts', ['role' => 'user', 'username' => 'orphan.user', 'password' => USER_PW, 'confirm_password' => USER_PW]);
check('User accounts cannot be created without a preload link', $r['status'] === 422 && isset($r['json']['errors']['preload_id']));
$r = $admin->req('POST', '/api/admin/accounts', ['role' => 'user', 'preload_id' => $pid['A'], 'username' => 'dup.link', 'password' => USER_PW, 'confirm_password' => USER_PW]);
check('Cannot create a second account for an already-registered employee', $r['status'] === 422);
$r = $admin->req('POST', '/api/admin/accounts', ['role' => 'user', 'preload_id' => $pid['D'], 'username' => 'pedro.cruz', 'password' => USER_PW, 'confirm_password' => USER_PW]);
check('Admin creates a user account for an unregistered employee', $r['status'] === 201);
$pedroId = (int) $r['json']['data']['User_ID'];
$second = new Client($base);
$second->login('second.admin', ADMIN_PW);
$r = $second->req('POST', "/api/admin/accounts/$adminId/status", ['status' => 'inactive']);
check('Second admin can deactivate the first (another active admin remains)', $r['status'] === 200);
$r = $admin->req('GET', '/api/admin/stats');
check('Deactivated admin\'s existing session is ended immediately (401)', $r['status'] === 401);
$r = (new Client($base))->login($adminName, ADMIN_PW);
check('Inactive account cannot sign in (403)', $r['status'] === 403);
$r = $second->req('PUT', "/api/admin/accounts/$secondId", ['username' => 'second.admin', 'role' => 'user']);
check('Cannot change own role', $r['status'] === 422);
$r = $second->req('POST', "/api/admin/accounts/$adminId/status", ['status' => 'active']);
check('Re-activate first admin', $r['status'] === 200);
$admin = new Client($base);
$admin->login($adminName, ADMIN_PW);
$r = $admin->req('POST', "/api/admin/accounts/$secondId/status", ['status' => 'inactive']);
$r2 = $q("SELECT COUNT(*) FROM ems_users WHERE Role='admin' AND Account_Status='active'")->fetchColumn();
check('Deactivating one of two admins is allowed', $r['status'] === 200 && (int) $r2 === 1);
$r = $admin->req('PUT', "/api/admin/accounts/$secondId", ['username' => 'second.admin', 'role' => 'admin']);
check('Editing an inactive admin works', $r['status'] === 200);
// last-admin guard: second admin inactive → first is the last active admin
$r = $admin->req('POST', "/api/admin/accounts/$mariaId/status", ['status' => 'inactive']);
check('Deactivate a regular user', $r['status'] === 200);
$r = $maria->req('GET', '/api/records/education');
check("Deactivated user's live session is rejected (401)", $r['status'] === 401);
$r = (new Client($base))->login('maria.santos', USER_PW);
check('Deactivated user cannot sign in (403)', $r['status'] === 403 && str_contains($r['json']['message'], 'inactive'));
$admin->req('POST', "/api/admin/accounts/$mariaId/status", ['status' => 'active']);
$r = $admin->req('DELETE', "/api/admin/accounts/$mariaId");
check('Account with employee records cannot be deleted (409, RESTRICT honored)', $r['status'] === 409 && $q('SELECT 1 FROM ems_users WHERE User_ID = ?', [$mariaId])->fetchColumn());
$r = $admin->req('DELETE', "/api/admin/accounts/$pedroId");
check('Account without records can be deleted', $r['status'] === 200 && !$q('SELECT 1 FROM ems_users WHERE User_ID = ?', [$pedroId])->fetchColumn());
check('…and its preloaded employee becomes Not Registered again', (bool) $q('SELECT 1 FROM preload_users WHERE Preload_Users_ID = ?', [$pid['D']])->fetchColumn());
$r = $admin->req('DELETE', "/api/admin/preload/{$pid['A']}");
check('Preloaded user linked to an account cannot be deleted (409)', $r['status'] === 409 && $q('SELECT 1 FROM preload_users WHERE Preload_Users_ID = ?', [$pid['A']])->fetchColumn());
$r = $admin->req('DELETE', "/api/admin/preload/{$pid['E']}");
check('Unregistered preloaded user can be deleted', $r['status'] === 200);

// =============================================================================================
section('Passwords and session invalidation');
$juan2 = new Client($base);
$juan2->login('juan.delacruz', USER_PW);
$r = $juan->req('POST', '/api/account/password', ['current_password' => 'wrong', 'new_password' => 'N3w!Password#2026', 'confirm_password' => 'N3w!Password#2026']);
check('Wrong current password rejected', $r['status'] === 422 && isset($r['json']['errors']['current_password']));
$r = $juan->req('POST', '/api/account/password', ['current_password' => USER_PW, 'new_password' => 'weakpass', 'confirm_password' => 'weakpass']);
check('Weak new password rejected', $r['status'] === 422 && isset($r['json']['errors']['new_password']));
$r = $juan->req('POST', '/api/account/password', ['current_password' => USER_PW, 'new_password' => 'N3w!Password#2026', 'confirm_password' => 'N3w!Password#2026']);
check('User changes own password', $r['status'] === 200, brief($r));
check('Current session stays signed in after the change', $juan->req('GET', '/api/records/education')['status'] === 200);
check('Other sessions of the same account are signed out', $juan2->req('GET', '/api/records/education')['status'] === 401);
check('Old password no longer works', (new Client($base))->login('juan.delacruz', USER_PW)['status'] === 401);
check('New password works', (new Client($base))->login('juan.delacruz', 'N3w!Password#2026')['status'] === 200);
$r = $admin->req('POST', "/api/admin/accounts/$juanId/password", ['password' => 'Reset!Pass#2026x', 'confirm_password' => 'Reset!Pass#2026x']);
check('Admin resets a user password', $r['status'] === 200);
check("Admin reset signs the user's sessions out", $juan->req('GET', '/api/records/education')['status'] === 401);
$r = $juan->login('juan.delacruz', 'Reset!Pass#2026x');
check('User signs in with the reset password', $r['status'] === 200);
$juan->page('/user/settings');
$r = $juan->req('PUT', "/api/admin/accounts/$juanId", ['username' => 'juan.renamed', 'role' => 'user']);
check('User cannot rename own account', $r['status'] === 403 && $q('SELECT UserName FROM ems_users WHERE User_ID = ?', [$juanId])->fetchColumn() === 'juan.delacruz');

// =============================================================================================
section('Login rate limiting');
$rl = new Client($base);
$rl->page('/login');
$codes = [];
for ($i = 0; $i < 6; $i++) {
    $codes[] = $rl->req('POST', '/api/auth/login', ['username' => $userC, 'password' => 'Wrong#Pass' . $i])['status'];
}
check('6th failed attempt within the window is blocked (429)', $codes[5] === 429, implode(',', $codes));
$r = $rl->req('POST', '/api/auth/login', ['username' => $userC, 'password' => USER_PW]);
check('Correct password is also blocked while rate-limited', $r['status'] === 429);

// =============================================================================================
section('Audit logging');
$r = $admin->req('GET', '/api/admin/audit-logs?draw=1&start=0&length=10&search%5Bvalue%5D=' . rawurlencode("' OR 1=1 --"));
check('Audit search with SQL meta-characters is safe', $r['status'] === 200 && $r['json']['recordsFiltered'] === 0);
$r = $admin->req('GET', '/api/admin/audit-logs?draw=2&start=0&length=500&result=failure');
check('Audit log filtered listing works', $r['status'] === 200 && $r['json']['recordsFiltered'] > 0);
foreach (['LOGIN_SUCCESS', 'LOGIN_FAILURE', 'REGISTER_SUCCESS', 'REGISTER_FAILURE', 'PRELOAD_CREATE', 'PRELOAD_UPDATE', 'PRELOAD_DELETE', 'ACCOUNT_CREATE',
          'ACCOUNT_UPDATE', 'ACCOUNT_DEACTIVATE', 'ACCOUNT_DELETE', 'PASSWORD_CHANGE', 'PASSWORD_RESET_BY_ADMIN', 'RECORD_CREATE', 'RECORD_UPDATE', 'RECORD_DELETE',
          'LOGIN_BLOCKED_RATE_LIMIT', 'ACCESS_DENIED'] as $a) {
    check("Audit event $a recorded", (int) $q('SELECT COUNT(*) FROM audit_logs WHERE Action = ?', [$a])->fetchColumn() > 0);
}
$leak = 0;
foreach ([ADMIN_PW, USER_PW, 'N3w!Password#2026', 'Reset!Pass#2026x', '$2y$'] as $secret) {
    $like = '%' . $secret . '%';
    $leak += (int) $q('SELECT COUNT(*) FROM audit_logs WHERE Action LIKE ? OR Actor_Username LIKE ? OR Affected_Table LIKE ? OR Affected_Record_ID LIKE ?', [$like, $like, $like, $like])->fetchColumn();
}
check('No passwords or password hashes in audit_logs', $leak === 0);

// =============================================================================================
section('Database integrity');
$fk = (int) $q("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND DELETE_RULE IN ('CASCADE','SET NULL')")->fetchColumn();
check('No foreign key cascades deletes (RESTRICT relationships intact)', $fk === 0);
check('Exactly 13 tables present', (int) $q('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn() === 13);
check('One personal-information row per user', (int) $q('SELECT COUNT(*) FROM (SELECT User_ID FROM `personal-information` GROUP BY User_ID HAVING COUNT(*) > 1) x')->fetchColumn() === 0);
check('Every ems_users.Preload_Users_ID is unique', (int) $q('SELECT COUNT(*) FROM (SELECT Preload_Users_ID FROM ems_users WHERE Preload_Users_ID IS NOT NULL GROUP BY Preload_Users_ID HAVING COUNT(*) > 1) x')->fetchColumn() === 0);

// ---------------------------------------------------------------------------------------------
echo "\n\033[1mResult: $pass passed, $fail failed\033[0m\n";
exit($fail === 0 ? 0 : 1);

function App_GENERIC(): string
{
    return 'We could not verify your employee details, or an account already exists for this employee. '
        . 'Check that your DOJ ID, cellphone number and email address exactly match your HR record, or contact the system administrator.';
}
