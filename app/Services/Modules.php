<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Validator;

/**
 * Employee information modules, defined from the authoritative SQL schema.
 *
 * Column names (including legacy spellings such as Eligibility_Tittle, Tittle, Position_Tittle and
 * Mothly_Salary) are preserved exactly; only the labels shown in the UI are human-readable.
 * Every column in these tables is NOT NULL without a default, so every field is required. Fields
 * marked 'na' accept the literal text "N/A" for items that do not apply.
 *
 * The definitions drive both server-side validation and the dynamic forms in the browser.
 */
final class Modules
{
    private static ?array $defs = null;

    public static function all(): array
    {
        if (self::$defs !== null) {
            return self::$defs;
        }
        $ldType = Config::get('lookups.ld_type', []);
        $ldConducted = Config::get('lookups.ld_conducted', []);
        $opts = static fn(array $values): array => array_combine($values, $values);

        return self::$defs = [
            'personal' => [
                'title' => 'Personal Information', 'icon' => 'fa-id-card', 'table' => 'personal-information',
                'pk' => 'Personal_ID', 'single' => true, 'required' => true, 'special' => 'personal',
                'fields' => [
                    'Full_Name'       => ['label' => 'Full Name', 'group' => 'Personal Details', 'max' => 255, 'col' => 12, 'list' => true,
                                          'help' => 'Surname, First Name, Middle Name, Name Extension (e.g. DELA CRUZ, JUAN SANTOS JR.)'],
                    'Birthday'        => ['label' => 'Date of Birth', 'type' => 'date', 'group' => 'Personal Details', 'past' => true, 'list' => true],
                    'Place_of_Birth'  => ['label' => 'Place of Birth', 'group' => 'Personal Details', 'max' => 255],
                    'Sex'             => ['label' => 'Sex', 'type' => 'select', 'options' => $opts(['Male', 'Female']), 'group' => 'Personal Details', 'list' => true],
                    'Civil_Status'    => ['label' => 'Civil Status', 'type' => 'select', 'group' => 'Personal Details', 'list' => true,
                                          'options' => $opts(['Single', 'Married', 'Widowed', 'Separated', 'Annulled', 'Solo Parent', 'Others'])],
                    'Citizenship'     => ['label' => 'Citizenship', 'group' => 'Personal Details', 'max' => 255,
                                          'datalist' => ['Filipino', 'Dual Citizenship (by birth)', 'Dual Citizenship (by naturalization)']],
                    'Height'          => ['label' => 'Height (m)', 'type' => 'decimal', 'min' => 0.5, 'maxv' => 2.5, 'step' => '0.01', 'group' => 'Personal Details'],
                    'Weight'          => ['label' => 'Weight (kg)', 'type' => 'decimal', 'min' => 2, 'maxv' => 400, 'step' => '0.01', 'group' => 'Personal Details'],
                    'Blood_type'      => ['label' => 'Blood Type', 'type' => 'select', 'group' => 'Personal Details',
                                          'options' => $opts(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'N/A'])],
                    'GSIS_No'         => ['label' => 'GSIS ID No.', 'group' => 'Government Identification', 'max' => 255, 'na' => true],
                    'PAGIBIG_No'      => ['label' => 'PAG-IBIG ID No.', 'group' => 'Government Identification', 'max' => 255, 'na' => true],
                    'PHILHEALTH_No'   => ['label' => 'PhilHealth No.', 'group' => 'Government Identification', 'max' => 255, 'na' => true],
                    'SSS_No'          => ['label' => 'SSS No.', 'group' => 'Government Identification', 'max' => 255, 'na' => true],
                    'TIN_No'          => ['label' => 'TIN', 'group' => 'Government Identification', 'max' => 255, 'na' => true],
                    'Agency_Employee_No' => ['label' => 'Agency Employee No.', 'group' => 'Government Identification', 'max' => 255, 'na' => true],
                    'Residential_No'  => ['label' => 'House/Block/Lot No.', 'group' => 'Residential Address', 'max' => 255, 'na' => true],
                    'Residential_Street' => ['label' => 'Street', 'group' => 'Residential Address', 'max' => 255, 'na' => true],
                    'Residential_Sub_Village' => ['label' => 'Subdivision/Village', 'group' => 'Residential Address', 'max' => 255, 'na' => true],
                    'Residential_Barangay' => ['label' => 'Barangay', 'group' => 'Residential Address', 'max' => 255],
                    'Residential_City_Municipality' => ['label' => 'City/Municipality', 'group' => 'Residential Address', 'max' => 255],
                    'Residential_Province' => ['label' => 'Province', 'group' => 'Residential Address', 'max' => 255],
                    'Residential_Zip_Code' => ['label' => 'ZIP Code', 'type' => 'zip', 'group' => 'Residential Address', 'max' => 10],
                    'Permanent_No'    => ['label' => 'House/Block/Lot No.', 'group' => 'Permanent Address', 'max' => 255, 'na' => true],
                    'Permanent_Street' => ['label' => 'Street', 'group' => 'Permanent Address', 'max' => 255, 'na' => true],
                    'Permanent_Sub_Village' => ['label' => 'Subdivision/Village', 'group' => 'Permanent Address', 'max' => 255, 'na' => true],
                    'Permanent_Barangay' => ['label' => 'Barangay', 'group' => 'Permanent Address', 'max' => 255],
                    'Permanent_City_Municipality' => ['label' => 'City/Municipality', 'group' => 'Permanent Address', 'max' => 255],
                    'Permanent_Province' => ['label' => 'Province', 'group' => 'Permanent Address', 'max' => 255],
                    'Permanent_Zip_Code' => ['label' => 'ZIP Code', 'type' => 'zip', 'group' => 'Permanent Address', 'max' => 10],
                    'Telephone_No'    => ['label' => 'Telephone No.', 'group' => 'Contact Information', 'max' => 255, 'na' => true],
                    'Mobile_No'       => ['label' => 'Mobile No.', 'type' => 'phone', 'group' => 'Contact Information', 'identity' => 'Cellphone_No', 'list' => true],
                    'Email_Address'   => ['label' => 'Email Address', 'type' => 'email', 'group' => 'Contact Information', 'max' => 255, 'identity' => 'Email_Address', 'list' => true],
                ],
            ],

            'family' => [
                'title' => 'Family Background', 'icon' => 'fa-people-roof', 'table' => 'family_background',
                'pk' => 'FB_ID', 'single' => true, 'required' => true, 'sub' => 'children',
                'fields' => [
                    'Spouse_Surname'      => ['label' => "Spouse's Surname", 'group' => 'Spouse', 'max' => 255, 'na' => true, 'list' => true],
                    'Spouse_FN'           => ['label' => "Spouse's First Name", 'group' => 'Spouse', 'max' => 255, 'na' => true, 'list' => true],
                    'Spouse_MN'           => ['label' => "Spouse's Middle Name", 'group' => 'Spouse', 'max' => 255, 'na' => true],
                    'Spouse_Occupation'   => ['label' => 'Occupation', 'group' => 'Spouse', 'max' => 255, 'na' => true],
                    'Spouse_Emp_Bus_Name' => ['label' => 'Employer / Business Name', 'group' => 'Spouse', 'max' => 255, 'na' => true],
                    'Spouse_Bus_Add'      => ['label' => 'Business Address', 'group' => 'Spouse', 'max' => 255, 'na' => true],
                    'Spouse_Mobile_No'    => ['label' => 'Telephone / Mobile No.', 'group' => 'Spouse', 'max' => 255, 'na' => true],
                    'Father_Surname'      => ['label' => "Father's Surname", 'group' => 'Father', 'max' => 255, 'na' => true, 'list' => true],
                    'Father_FN'           => ['label' => "Father's First Name", 'group' => 'Father', 'max' => 255, 'na' => true, 'list' => true],
                    'Father_MN'           => ['label' => "Father's Middle Name", 'group' => 'Father', 'max' => 255, 'na' => true],
                    'Father_Ext_Name'     => ['label' => 'Name Extension (Jr., Sr.)', 'group' => 'Father', 'max' => 255, 'na' => true],
                    'Mother_Surname'      => ['label' => "Mother's Maiden Surname", 'group' => "Mother's Maiden Name", 'max' => 255, 'na' => true, 'list' => true],
                    'Mother_FN'           => ['label' => "Mother's First Name", 'group' => "Mother's Maiden Name", 'max' => 255, 'na' => true, 'list' => true],
                    'Mother_MN'           => ['label' => "Mother's Middle Name", 'group' => "Mother's Maiden Name", 'max' => 255, 'na' => true],
                ],
            ],

            'children' => [
                'title' => 'Children', 'icon' => 'fa-children', 'table' => 'children-information',
                'pk' => 'CI_ID', 'single' => false, 'required' => false, 'hidden' => true, 'order' => '`Child_Number` ASC',
                'fields' => [
                    'Child_Number' => ['label' => 'Child No.', 'type' => 'int', 'min' => 1, 'maxv' => 50, 'list' => true, 'col' => 3],
                    'Full_Name'    => ['label' => 'Full Name', 'max' => 255, 'list' => true, 'col' => 6],
                    'Birthday'     => ['label' => 'Date of Birth', 'type' => 'date', 'past' => true, 'list' => true, 'col' => 3],
                ],
            ],

            'education' => [
                'title' => 'Educational Background', 'icon' => 'fa-graduation-cap', 'table' => 'educational-background',
                'pk' => 'EB_ID', 'single' => false, 'required' => true, 'order' => '`Date_From` ASC',
                'fields' => [
                    'Level'              => ['label' => 'Level', 'type' => 'select', 'list' => true,
                                             'options' => $opts(['Elementary', 'Secondary', 'Vocational / Trade Course', 'College', 'Graduate Studies'])],
                    'Name_of_School'     => ['label' => 'Name of School', 'max' => 255, 'list' => true, 'col' => 12, 'help' => 'Write in full'],
                    'Course'             => ['label' => 'Basic Education / Degree / Course', 'max' => 255, 'list' => true, 'col' => 12, 'help' => 'Write in full'],
                    'Date_From'          => ['label' => 'Period of Attendance — From', 'type' => 'date', 'list' => true],
                    'Date_To'            => ['label' => 'Period of Attendance — To', 'type' => 'date', 'after' => 'Date_From', 'list' => true],
                    'Unit_Earned'        => ['label' => 'Highest Level / Units Earned', 'max' => 255, 'na' => true, 'help' => 'If not graduated'],
                    'Year_Graduated'     => ['label' => 'Year Graduated', 'type' => 'year', 'max' => 255, 'na' => true, 'list' => true],
                    'Scholarship_Honors' => ['label' => 'Scholarship / Academic Honors Received', 'max' => 255, 'na' => true],
                ],
            ],

            'eligibility' => [
                'title' => 'Civil Service Eligibility', 'icon' => 'fa-award', 'table' => 'civil_service_eligibility',
                'pk' => 'CS_Eligibility_ID', 'single' => false, 'required' => true, 'order' => '`Date_of_Exam_Conferment` DESC',
                'fields' => [
                    'Eligibility_Tittle'      => ['label' => 'Career Service / Eligibility', 'max' => 255, 'list' => true, 'col' => 12,
                                                  'datalist' => ['Career Service Professional', 'Career Service Sub-Professional', 'RA 1080 (Board/Bar)', 'PD 907 (Honor Graduate)', 'Barangay Official Eligibility', 'CES / CSEE']],
                    'Rating'                  => ['label' => 'Rating (%)', 'type' => 'decimal', 'min' => 0, 'maxv' => 100, 'step' => '0.01', 'list' => true],
                    'Date_of_Exam_Conferment' => ['label' => 'Date of Examination / Conferment', 'type' => 'date', 'list' => true],
                    'Place_of_Examination'    => ['label' => 'Place of Examination / Conferment', 'max' => 255, 'list' => true],
                    'Employee_ID'             => ['label' => 'Employee ID No.', 'type' => 'int', 'min' => 0, 'maxv' => 2147483647],
                    'License_Number'          => ['label' => 'License Number (if applicable)', 'max' => 255, 'na' => true],
                    'Date_of_Validity'        => ['label' => 'License Date of Validity', 'type' => 'date'],
                ],
            ],

            'work' => [
                'title' => 'Work Experience', 'icon' => 'fa-briefcase', 'table' => 'work_experience',
                'pk' => 'Work_Experience_ID', 'single' => false, 'required' => true, 'order' => '`Date_From` DESC',
                'fields' => [
                    'Date_From'             => ['label' => 'Inclusive Dates — From', 'type' => 'date', 'list' => true],
                    'Date_To'               => ['label' => 'Inclusive Dates — To', 'type' => 'date', 'after' => 'Date_From', 'list' => true],
                    'Position_Tittle'       => ['label' => 'Position Title', 'max' => 255, 'list' => true, 'col' => 12, 'help' => 'Write in full; do not abbreviate'],
                    'Name_of_Office'        => ['label' => 'Department / Agency / Office / Company', 'max' => 255, 'list' => true, 'col' => 12],
                    'Mothly_Salary'         => ['label' => 'Monthly Salary (₱)', 'type' => 'decimal', 'min' => 0, 'maxv' => 999999.99, 'step' => '0.01', 'list' => true],
                    'Salary_Grade'          => ['label' => 'Salary Grade & Step', 'type' => 'sg', 'max' => 4, 'na' => true, 'help' => 'Format 00-0, e.g. 11-2 (max 4 characters)'],
                    'Status_of_Appointment' => ['label' => 'Status of Appointment', 'max' => 255, 'list' => true,
                                                'datalist' => ['Permanent', 'Temporary', 'Coterminous', 'Fixed Term', 'Contractual', 'Substitute', 'Provisional', 'Casual', 'Job Order', 'Contract of Service']],
                    'Govt_Private'          => ['label' => 'Government Service', 'type' => 'select', 'options' => $opts(['Government', 'Private']), 'max' => 50],
                ],
            ],

            'voluntary' => [
                'title' => 'Voluntary Work', 'icon' => 'fa-hand-holding-heart', 'table' => 'voluntary_work',
                'pk' => 'Voluntary_Work_ID', 'single' => false, 'required' => false, 'order' => '`Date_From` DESC',
                'fields' => [
                    'Name_Address_Organization' => ['label' => 'Name & Address of Organization', 'max' => 255, 'list' => true, 'col' => 12],
                    'Date_From'                 => ['label' => 'Inclusive Dates — From', 'type' => 'date', 'list' => true],
                    'Date_To'                   => ['label' => 'Inclusive Dates — To', 'type' => 'date', 'after' => 'Date_From', 'list' => true],
                    'Number_Hours'              => ['label' => 'Number of Hours', 'type' => 'int', 'min' => 0, 'maxv' => 100000, 'list' => true],
                    'Position_Nature_Work'      => ['label' => 'Position / Nature of Work', 'max' => 255, 'list' => true],
                ],
            ],

            'learning' => [
                'title' => 'Learning and Development', 'icon' => 'fa-chalkboard-user', 'table' => 'learning_development',
                'pk' => 'Learning_Development_ID', 'single' => false, 'required' => true, 'order' => '`Date_From` DESC',
                'fields' => [
                    'Tittle'              => ['label' => 'Title of Learning & Development Intervention / Training', 'max' => 255, 'list' => true, 'col' => 12],
                    'Date_From'           => ['label' => 'Inclusive Dates — From', 'type' => 'date', 'list' => true],
                    'Date_To'             => ['label' => 'Inclusive Dates — To', 'type' => 'date', 'after' => 'Date_From', 'list' => true],
                    'Number_Hours'        => ['label' => 'Number of Hours', 'type' => 'int', 'min' => 1, 'maxv' => 100000, 'list' => true],
                    'Type'                => ['label' => 'Type of L&D', 'type' => 'select', 'options' => $ldType, 'list' => true, 'help' => 'Managerial, Supervisory, Technical, etc.'],
                    'Conducted_Sponsored' => ['label' => 'Conducted / Sponsored By', 'type' => 'select', 'options' => $ldConducted, 'list' => true, 'col' => 12],
                ],
            ],

            'employment' => [
                'title' => 'Employment Information', 'icon' => 'fa-building-columns', 'table' => 'employment-information',
                'pk' => 'Employment_ID', 'single' => true, 'required' => true,
                'fields' => [
                    'Original_Office'             => ['label' => 'Original Office', 'group' => 'Office Assignment', 'max' => 255, 'list' => true],
                    'Reassignment_Office'         => ['label' => 'Reassignment Office', 'group' => 'Office Assignment', 'max' => 255, 'na' => true],
                    'Detailed_Office'             => ['label' => 'Detailed Office', 'group' => 'Office Assignment', 'max' => 255, 'na' => true],
                    'Date_of_Entry'               => ['label' => 'Date of Entry', 'type' => 'date', 'group' => 'Entry', 'list' => true],
                    'Entry_Position'              => ['label' => 'Entry Position', 'group' => 'Entry', 'max' => 255],
                    'Date_Previous_Promotion'     => ['label' => 'Date of Previous Promotion', 'type' => 'date', 'group' => 'Promotions', 'after' => 'Date_of_Entry',
                                                      'help' => 'If not yet promoted, use the date of entry.'],
                    'Position_Previous_Promotion' => ['label' => 'Position at Previous Promotion', 'group' => 'Promotions', 'max' => 255, 'na' => true],
                    'Date_Latest_Promotion'       => ['label' => 'Date of Latest Promotion', 'type' => 'date', 'group' => 'Promotions', 'after' => 'Date_Previous_Promotion',
                                                      'help' => 'If not yet promoted, use the date of entry.'],
                    'Current_Position'            => ['label' => 'Current Position', 'group' => 'Current Assignment', 'max' => 255, 'list' => true],
                    'Salary_Grade'                => ['label' => 'Salary Grade', 'group' => 'Current Assignment', 'max' => 255, 'list' => true],
                ],
            ],

            'other' => [
                'title' => 'Other Information', 'icon' => 'fa-star', 'table' => 'other_information',
                'pk' => 'Other_Information_ID', 'single' => false, 'required' => true, 'order' => '`Other_Information_ID` ASC',
                'fields' => [
                    'Skill_Hobbies'            => ['label' => 'Special Skills and Hobbies', 'max' => 255, 'list' => true, 'col' => 12],
                    'Distinction_Recognition'  => ['label' => 'Non-Academic Distinctions / Recognition', 'max' => 255, 'na' => true, 'list' => true, 'col' => 12],
                    'Association_Organization' => ['label' => 'Membership in Association / Organization', 'max' => 255, 'na' => true, 'list' => true, 'col' => 12],
                ],
            ],
        ];
    }

    public static function get(string $slug): ?array
    {
        $all = self::all();
        return isset($all[$slug]) ? ['slug' => $slug] + $all[$slug] : null;
    }

    /** Modules shown in navigation, in display order. */
    public static function navigation(): array
    {
        $out = [];
        foreach (self::all() as $slug => $m) {
            if (empty($m['hidden'])) {
                $out[$slug] = $m;
            }
        }
        return $out;
    }

    /** Definition subset safe to send to the browser. */
    public static function clientDefinition(string $slug): array
    {
        $m = self::get($slug);
        if ($m === null) {
            return [];
        }
        unset($m['table'], $m['order']);
        return $m;
    }

    /**
     * Validate and normalize input for a module.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>} [clean data, field errors]
     */
    public static function validate(string $slug, array $input, array $skipFields = []): array
    {
        $m = self::get($slug);
        if ($m === null) {
            throw new \InvalidArgumentException('Unknown module.');
        }
        $data = [];
        $errors = [];
        foreach ($m['fields'] as $name => $f) {
            if (in_array($name, $skipFields, true)) {
                continue;
            }
            $type = $f['type'] ?? 'text';
            $label = $f['label'];
            $raw = $input[$name] ?? '';
            $raw = is_scalar($raw) ? Validator::cleanText((string) $raw) : '';
            $naHint = !empty($f['na']) ? ' Enter N/A if not applicable.' : '';

            if ($raw === '') {
                $errors[$name] = $label . ' is required.' . $naHint;
                continue;
            }
            $isNa = !empty($f['na']) && strcasecmp($raw, 'N/A') === 0;

            switch ($type) {
                case 'date':
                    $v = Validator::date($raw);
                    if ($v === null) { $errors[$name] = 'Enter a valid date for ' . $label . '.'; break; }
                    if (!empty($f['past']) && $v > date('Y-m-d')) { $errors[$name] = $label . ' cannot be in the future.'; break; }
                    $data[$name] = $v;
                    break;
                case 'int':
                    $v = Validator::intVal($raw, (int) ($f['min'] ?? 0), (int) ($f['maxv'] ?? 2147483647));
                    if ($v === null) { $errors[$name] = $label . ' must be a whole number between ' . ($f['min'] ?? 0) . ' and ' . ($f['maxv'] ?? 2147483647) . '.'; break; }
                    $data[$name] = $v;
                    break;
                case 'decimal':
                    $v = Validator::decimal($raw, (float) ($f['min'] ?? 0), (float) ($f['maxv'] ?? 999.99));
                    if ($v === null) { $errors[$name] = $label . ' must be a number between ' . ($f['min'] ?? 0) . ' and ' . ($f['maxv'] ?? 999.99) . '.'; break; }
                    $data[$name] = $v;
                    break;
                case 'select':
                    $options = $f['options'] ?? [];
                    $key = array_key_exists($raw, $options) ? $raw : null;
                    if ($key === null) { $errors[$name] = 'Select a valid ' . $label . '.'; break; }
                    $data[$name] = is_int(array_key_first($options)) ? (int) $key : (string) $key;
                    break;
                case 'email':
                    $v = Validator::email($raw);
                    if ($v === null || mb_strlen($v) > ($f['max'] ?? 255)) { $errors[$name] = 'Enter a valid email address.'; break; }
                    $data[$name] = $v;
                    break;
                case 'phone':
                    $v = Validator::phMobile($raw);
                    if ($v === null) { $errors[$name] = 'Enter a valid Philippine mobile number (e.g. 09171234567).'; break; }
                    $data[$name] = $v;
                    break;
                case 'zip':
                    if (!preg_match('/^\d{4}$/', $raw)) { $errors[$name] = 'ZIP Code must be 4 digits.'; break; }
                    $data[$name] = $raw;
                    break;
                case 'year':
                    if ($isNa) { $data[$name] = 'N/A'; break; }
                    if (!preg_match('/^(19|20)\d{2}$/', $raw) || (int) $raw > (int) date('Y') + 6) { $errors[$name] = $label . ' must be a 4-digit year or N/A.'; break; }
                    $data[$name] = $raw;
                    break;
                case 'sg':
                    if ($isNa) { $data[$name] = 'N/A'; break; }
                    if (!preg_match('/^[0-9A-Za-z][0-9A-Za-z\/-]{0,3}$/', $raw)) { $errors[$name] = $label . ' must be up to 4 characters, e.g. 11-2.'; break; }
                    $data[$name] = $raw;
                    break;
                default:
                    $max = (int) ($f['max'] ?? 255);
                    if (mb_strlen($raw) > $max) { $errors[$name] = $label . ' must not exceed ' . $max . ' characters.'; break; }
                    $data[$name] = $isNa ? 'N/A' : $raw;
            }
        }
        // Chronology rules (e.g. Date_To must not be earlier than Date_From).
        foreach ($m['fields'] as $name => $f) {
            if (!empty($f['after']) && isset($data[$name], $data[$f['after']]) && $data[$name] < $data[$f['after']]) {
                $errors[$name] = $f['label'] . ' cannot be earlier than ' . $m['fields'][$f['after']]['label'] . '.';
            }
        }
        return [$data, $errors];
    }
}
