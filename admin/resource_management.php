<?php
/**
 * Resource Management Page for Admin
 *
 * @package   theme_remui_kids
 * @copyright 2026 Kodeit
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lang_init.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/url/locallib.php');
require_once($CFG->dirroot . '/theme/remui_kids/classes/local/secure_file_token.php');

global $DB, $USER, $CFG, $PAGE, $OUTPUT;

require_login();

// Check if user is admin
if (!is_siteadmin()) {
    echo "<h1>Access Denied</h1>";
    echo "<p>You must be an administrator to access this page.</p>";
    echo "<p><a href='" . $CFG->wwwroot . "'>Go Back</a></p>";
    exit;
}

// Ensure database table exists and fetch records
$dbman = $DB->get_manager();
$res_meta_table = new xmldb_table('theme_remui_kids_res_meta');
if (!$dbman->table_exists($res_meta_table)) {
    $res_meta_table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $res_meta_table->add_field('res_type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
    $res_meta_table->add_field('res_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $res_meta_table->add_field('unit', XMLDB_TYPE_CHAR, '50', null, null, null, null);
    $res_meta_table->add_field('lesson', XMLDB_TYPE_CHAR, '50', null, null, null, null);
    $res_meta_table->add_field('preview_image', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $res_meta_table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $res_meta_table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $res_meta_table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $res_meta_table->add_key('res_type_id', XMLDB_KEY_UNIQUE, ['res_type', 'res_id']);
    $dbman->create_table($res_meta_table);
}

// Fetch all metadata mapping
$metadata_records = $DB->get_records('theme_remui_kids_res_meta');
$res_meta_map = [];
if (!empty($metadata_records)) {
    foreach ($metadata_records as $rec) {
        $res_meta_map[$rec->res_type . '_' . $rec->res_id] = $rec;
    }
}

$PAGE->set_url('/theme/remui_kids/admin/resource_management.php');
$PAGE->set_title('Resource Management');
$PAGE->set_heading('');
$PAGE->set_pagelayout('standard');

// Helper function to get section type
function get_section_type($section_name) {
    if (empty($section_name)) {
        return null;
    }
    $main_section = explode(' > ', $section_name)[0];
    $main_section_lower = strtolower(trim($main_section));
    if ($main_section_lower === 'plan') {
        return 'plan';
    } else if ($main_section_lower === 'teach') {
        return 'teach';
    } else if ($main_section_lower === 'assess') {
        return 'assess';
    }
    return null;
}

// Get all active courses for admin
$sql = "SELECT DISTINCT c.id, c.fullname, c.shortname, c.category
        FROM {course} c
        WHERE c.id != 1
        AND c.visible = 1
        ORDER BY c.fullname ASC";
$teacher_courses = $DB->get_records_sql($sql);

$teacher_resources = [];
$all_courses_data = [];

if (!empty($teacher_courses)) {
    foreach ($teacher_courses as $course_rec) {
        $courseid = $course_rec->id;
        $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
        if (!$course) continue;

        $all_courses_data[$courseid] = $course;
        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        $modinfo = get_fast_modinfo($courseid);

        foreach ($sections as $section) {
            if ((int)$section->section === 0) continue;
            if ($section->component === 'mod_subsection') continue;

            $section_hidden = ((int)$section->visible === 0);
            $section_name = (string)($section->name ?? '');
            $section_display_name = $section_name !== '' ? $section_name : ('Section ' . $section->section);

            $is_resource_sec = $section_hidden || 
                stripos($section_name, 'teacher resource') !== false || 
                stripos($section_name, 'teacher material') !== false || 
                stripos($section_name, 'instructor resource') !== false;

            if ($is_resource_sec) {
                if (!empty($section->sequence)) {
                    $module_ids = explode(',', $section->sequence);
                    foreach ($module_ids as $module_id) {
                        try {
                            $cm = $modinfo->get_cm($module_id);
                            if (!$cm || !empty($cm->deletioninprogress)) continue;
                            
                            $module_table = $cm->modname;
                            $module_exists = $DB->record_exists($module_table, ['id' => $cm->instance]);
                            if (!$module_exists) continue;

                            if ($cm->modname === 'subsection') {
                                $subsection = $DB->get_record('course_sections', ['component' => 'mod_subsection', 'itemid' => $cm->instance]);
                                if ($subsection && !empty($subsection->sequence)) {
                                    $sub_module_ids = explode(',', $subsection->sequence);
                                    foreach ($sub_module_ids as $sub_module_id) {
                                        try {
                                            $sub_cm = $modinfo->get_cm($sub_module_id);
                                            if (!$sub_cm || $sub_cm->modname === 'subsection' || !empty($sub_cm->deletioninprogress)) continue;
                                            
                                            $sub_module_table = $sub_cm->modname;
                                            $sub_module_exists = $DB->record_exists($sub_module_table, ['id' => $sub_cm->instance]);
                                            if (!$sub_module_exists) continue;

                                            $teacher_resources[] = [
                                                'cm' => $sub_cm,
                                                'section_name' => $section_display_name . ' > ' . ($subsection->name ?? ''),
                                                'course_id' => $courseid,
                                                'course' => $course
                                            ];
                                        } catch (Exception $e) {
                                            continue;
                                        }
                                    }
                                }
                            } else {
                                $teacher_resources[] = [
                                    'cm' => $cm,
                                    'section_name' => $section_display_name,
                                    'course_id' => $courseid,
                                    'course' => $course
                                ];
                            }
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                }
            }

            // Individual hidden modules in visible sections
            if (!$section_hidden && !empty($section->sequence)) {
                $module_ids = explode(',', $section->sequence);
                foreach ($module_ids as $module_id) {
                    try {
                        $cm = $modinfo->get_cm($module_id);
                        if ($cm && $cm->visible == 0 && $cm->modname !== 'subsection' && empty($cm->deletioninprogress)) {
                            $module_table = $cm->modname;
                            if ($DB->record_exists($module_table, ['id' => $cm->instance])) {
                                $teacher_resources[] = [
                                    'cm' => $cm,
                                    'section_name' => $section_display_name,
                                    'course_id' => $courseid,
                                    'course' => $course
                                ];
                            }
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
            }
        }
    }
}

// Helpers
if (!function_exists('get_category_info_for_course')) {
    function get_category_info_for_course($course, $DB) {
        $course_category = $DB->get_record('course_categories', ['id' => $course->category], '*', IGNORE_MISSING);
        if (!$course_category) {
            return ['main' => 'Uncategorized', 'main_id' => 0, 'direct' => 'Uncategorized', 'direct_id' => 0];
        }
        $direct_category = $course_category;
        $current_cat = $course_category;
        while ($current_cat && $current_cat->parent != 0) {
            $current_cat = $DB->get_record('course_categories', ['id' => $current_cat->parent], '*', IGNORE_MISSING);
            if (!$current_cat) break;
        }
        if ($current_cat && $current_cat->parent == 0) {
            return [
                'main' => $current_cat->name,
                'main_id' => $current_cat->id,
                'direct' => $direct_category->name,
                'direct_id' => $direct_category->id
            ];
        }
        return ['main' => 'Uncategorized', 'main_id' => 0, 'direct' => 'Uncategorized', 'direct_id' => 0];
    }
}

if (!function_exists('theme_remui_kids_teacher_generate_file_url')) {
    function theme_remui_kids_teacher_generate_file_url(\stored_file $file, int $userid): moodle_url {
        $token = \theme_remui_kids\local\secure_file_token::generate($file->get_id(), $userid);
        return new moodle_url('/theme/remui_kids/teacher/file_proxy.php', [
            'fileid' => $file->get_id(),
            'userid' => $userid,
            'expires' => $token['expires'],
            'token' => $token['token'],
        ]);
    }
}

if (!function_exists('theme_remui_kids_teacher_generate_preview_url')) {
    function theme_remui_kids_teacher_generate_preview_url(\stored_file $file, int $userid, string $file_extension): ?string {
        global $CFG;
        $file_extension_lower = strtolower($file_extension);
        $mimetype = $file->get_mimetype();
        if (in_array($file_extension_lower, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'bmp', 'webp']) || strpos($mimetype, 'image/') === 0) {
            return theme_remui_kids_teacher_generate_file_url($file, $userid)->out(false);
        }
        if ($file_extension_lower === 'pdf') {
            $file_url = theme_remui_kids_teacher_generate_file_url($file, $userid);
            $absolute_url = $file_url->out(false);
            if (strpos($absolute_url, 'http') !== 0) {
                $absolute_url = $CFG->wwwroot . $absolute_url;
            }
            return $absolute_url;
        }
        return null;
    }
}

if (!function_exists('theme_remui_kids_teacher_resource_filter_type')) {
    function theme_remui_kids_teacher_resource_filter_type(string $extension): string {
        $ext = strtoupper(trim($extension));
        $videoextensions = ['HTML', 'HTM', 'MP4', 'AVI', 'MOV', 'WMV', 'MKV', 'WEBM'];
        $imageextensions = ['PNG', 'JPG', 'JPEG', 'GIF', 'SVG', 'BMP', 'WEBP'];
        if (in_array($ext, $videoextensions, true)) return 'videos';
        if (in_array($ext, $imageextensions, true)) return 'images';
        if (in_array($ext, ['PPTX', 'PPT'], true)) return 'pptx';
        if (in_array($ext, ['DOCX', 'DOC'], true)) return 'docx';
        if (in_array($ext, ['XLSX', 'XLS'], true)) return 'xlsx';
        if ($ext === 'CSV') return 'csv';
        if ($ext === 'LINK') return 'url';
        return strtolower($ext);
    }
}

if (!function_exists('theme_remui_kids_teacher_strip_extension')) {
    function theme_remui_kids_teacher_strip_extension(string $name): string {
        return preg_replace('/\.(pdf|html|htm|docx|doc|pptx|ppt|pps|ppsx|xlsx|xls|xlsm|csv|png|jpg|jpeg|gif|svg|bmp|webp|mp4|avi|mov|wmv|mkv|webm|mp3|wav|aac|flac|ogg|zip|rar|tar|gz|7z|odt|ods)$/i', '', $name);
    }
}

// Flatten resources
$all_resources = [];
$available_file_types = [];
$category_resource_count = [];
$category_info_map = [];
$category_tree = [];
$filestorage = get_file_storage();

foreach ($teacher_resources as $resource) {
    $cm = $resource['cm'];
    $course = $resource['course'];
    $cat_info = get_category_info_for_course($course, $DB);

    if ($cat_info['main_id'] > 0) {
        $category_info_map[$cat_info['main_id']] = [
            'name' => $cat_info['main'],
            'id' => $cat_info['main_id'],
            'parent_id' => 0
        ];
    }

    if ($cm->modname === 'folder') {
        $context = context_module::instance($cm->id);
        $files = $filestorage->get_area_files($context->id, 'mod_folder', 'content', 0, 'sortorder, filepath, filename', false);
        foreach ($files as $file) {
            $file_ext = strtoupper(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (!empty($file_ext) && !in_array($file_ext, $available_file_types)) {
                $available_file_types[] = $file_ext;
            }
            if ($cat_info['main_id'] > 0) {
                if (!isset($category_resource_count[$cat_info['main_id']])) {
                    $category_resource_count[$cat_info['main_id']] = 0;
                }
                $category_resource_count[$cat_info['main_id']]++;
            }
            $folder_name = format_string($cm->name);
            $section_type = get_section_type($resource['section_name']);
            $all_resources[] = [
                'type' => 'file',
                'file' => $file,
                'category' => $cat_info['main'],
                'category_id' => $cat_info['main_id'],
                'direct_category' => $cat_info['direct'],
                'direct_category_id' => $cat_info['direct_id'],
                'section' => $resource['section_name'],
                'folder_name' => $folder_name,
                'folder_tag' => $section_type,
                'folder_cmid' => $cm->id,
                'course' => $course
            ];
        }
    } else {
        $resource_type = strtoupper($cm->modname);
        if (!in_array($resource_type, $available_file_types)) {
            $available_file_types[] = $resource_type;
        }
        if ($cat_info['main_id'] > 0) {
            if (!isset($category_resource_count[$cat_info['main_id']])) {
                $category_resource_count[$cat_info['main_id']] = 0;
            }
            $category_resource_count[$cat_info['main_id']]++;
        }
        $folder_name = ($cm->modname === 'folder') ? format_string($cm->name) : '';
        $section_type = get_section_type($resource['section_name']);
        $all_resources[] = [
            'type' => 'resource',
            'cm' => $cm,
            'category' => $cat_info['main'],
            'category_id' => $cat_info['main_id'],
            'direct_category' => $cat_info['direct'],
            'direct_category_id' => $cat_info['direct_id'],
            'section' => $resource['section_name'],
            'folder_name' => $folder_name,
            'folder_tag' => $section_type,
            'folder_cmid' => ($cm->modname === 'folder') ? $cm->id : null,
            'course' => $course
        ];
    }
}

// Resource counts
$resource_counts = [
    'all' => count($all_resources),
    'plan' => 0,
    'teach' => 0,
    'assess' => 0
];
foreach ($all_resources as $resource_item) {
    $folder_tag = isset($resource_item['folder_tag']) ? strtolower($resource_item['folder_tag']) : '';
    if ($folder_tag === 'plan') {
        $resource_counts['plan']++;
    } else if ($folder_tag === 'teach') {
        $resource_counts['teach']++;
    } else if ($folder_tag === 'assess') {
        $resource_counts['assess']++;
    }
}

$courses_with_resources = [];
foreach ($all_resources as $resource_item) {
    if (isset($resource_item['course']) && isset($resource_item['course']->id)) {
        $courses_with_resources[$resource_item['course']->id] = true;
    }
}

$teacher_grade_numbers = [];
foreach ($all_resources as $resource_item) {
    if (!isset($resource_item['course'])) continue;
    $course_name = ($resource_item['course']->fullname ?? '') . ' ' . ($resource_item['course']->shortname ?? '');
    if (preg_match_all('/\bGrade\s*(\d{1,2})\b/i', $course_name, $gm)) {
        foreach ($gm[1] as $g) {
            $g = (int)$g;
            if ($g >= 1 && $g <= 12 && !in_array($g, $teacher_grade_numbers, true)) {
                $teacher_grade_numbers[] = $g;
            }
        }
    }
}
sort($teacher_grade_numbers);

// Rebuild category tree for courses
$all_teacher_category_ids = [];
if (!empty($teacher_courses)) {
    foreach ($teacher_courses as $course) {
        if (isset($course->category) && $course->category > 0) {
            $all_teacher_category_ids[$course->category] = true;
        }
    }
}

foreach (array_keys($all_teacher_category_ids) as $cat_id) {
    $current_cat = $DB->get_record('course_categories', ['id' => $cat_id], 'id, parent, name, path', IGNORE_MISSING);
    if (!$current_cat) continue;

    $main_cat = $current_cat;
    while ($main_cat && $main_cat->parent != 0) {
        $main_cat = $DB->get_record('course_categories', ['id' => $main_cat->parent], 'id, parent, name, path', IGNORE_MISSING);
        if (!$main_cat) break;
    }

    if ($main_cat && $main_cat->parent == 0) {
        $main_cat_id = $main_cat->id;
        $main_category_record = $DB->get_record('course_categories', ['id' => $main_cat_id], 'path', MUST_EXIST);
        if (!$main_category_record) continue;

        $path_pattern = $main_category_record->path . '/%';
        $all_descendant_categories = $DB->get_records_sql(
            "SELECT id FROM {course_categories} WHERE (id = ? OR path LIKE ?) AND visible = 1",
            [$main_cat_id, $path_pattern]
        );
        $descendant_category_ids = array_keys($all_descendant_categories);

        if (!empty($descendant_category_ids)) {
            list($in_sql, $params) = $DB->get_in_or_equal($descendant_category_ids);
            
            $courses_in_category = $DB->get_records_sql(
                "SELECT DISTINCT c.id, c.fullname, c.shortname 
                 FROM {course} c
                 WHERE c.category $in_sql 
                 AND c.id > 1
                 AND c.visible = 1
                 ORDER BY c.fullname ASC",
                $params
            );

            $filtered_courses = [];
            if (!empty($courses_in_category)) {
                $course_ids_to_check = array_keys($courses_in_category);
                $courses_with_hidden_sections = [];
                if (!empty($course_ids_to_check)) {
                    list($course_ids_sql, $course_ids_params) = $DB->get_in_or_equal($course_ids_to_check);
                    $hidden_sections = $DB->get_records_sql(
                        "SELECT DISTINCT course FROM {course_sections} WHERE course $course_ids_sql AND section > 0 AND visible = 0",
                        $course_ids_params
                    );
                    foreach ($hidden_sections as $section) {
                        $courses_with_hidden_sections[$section->course] = true;
                    }
                }

                foreach ($courses_in_category as $course_id => $course_record) {
                    $has_resources = isset($courses_with_resources[$course_id]);
                    $has_hidden_sections = isset($courses_with_hidden_sections[$course_id]);
                    if ($has_resources || $has_hidden_sections) {
                        $filtered_courses[$course_id] = $course_record;
                    }
                }
            }

            if (!empty($filtered_courses)) {
                if (!isset($category_tree[$main_cat_id])) {
                    $category_tree[$main_cat_id] = [];
                }
                foreach ($filtered_courses as $course_id => $course_record) {
                    $category_tree[$main_cat_id][$course_id] = $course_record->fullname;
                }
                if (!isset($category_info_map[$main_cat_id])) {
                    $category_info_map[$main_cat_id] = [
                        'name' => $main_cat->name,
                        'id' => $main_cat_id,
                        'parent_id' => 0
                    ];
                }
            }
        }
    }
}

$has_ksa_resources = false;
$has_gcc_resources = false;
foreach ($all_resources as $resource_item) {
    $category = isset($resource_item['category']) ? strtolower($resource_item['category']) : '';
    if (strpos($category, 'ksa') !== false) $has_ksa_resources = true;
    if (strpos($category, 'gcc') !== false) $has_gcc_resources = true;
}

$show_ksa = $has_ksa_resources || (!$has_ksa_resources && !$has_gcc_resources);
$show_gcc = $has_gcc_resources || (!$has_ksa_resources && !$has_gcc_resources);
$default_curriculum = 'ksa';
if ($show_gcc && !$show_ksa) {
    $default_curriculum = 'gcc';
}

echo $OUTPUT->header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    /* Reuse view_course.php CSS styles */
    #page-header, #page-header-content, .region-main-settings-menu, .page-header-headings {
        display: none !important;
    }
    body {
        overflow-x: hidden;
        width: 100% !important;
    }
    #page-wrapper, #page, .page, .container-fluid, .container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
        width: 100% !important;
    }
    .col-12 {
        padding-left: 0 !important;
        padding-right: 0 !important;
        width: 100% !important;
    }
    .admin-sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 260px !important;
        height: 100vh !important;
        background: white !important;
        border-right: 1px solid #e9ecef !important;
        z-index: 1000 !important;
        overflow-y: auto !important;
        box-shadow: 2px 0 10px rgba(0,0,0,0.05) !important;
    }
    .main-content {
        margin-left: 260px !important;
        padding: 20px 50px 30px 30px !important;
        min-height: 100vh;
        width: calc(100vw - 260px) !important;
        box-sizing: border-box !important;
    }
    
    /* Layout with secondary filter sidebar */
    .resources-main-layout {
        display: flex !important;
        flex-direction: row !important;
        gap: 20px;
        align-items: flex-start;
        width: 100%;
        box-sizing: border-box;
    }
    
    .resources-sidebar {
        width: 280px;
        min-width: 280px;
        background: #ffffff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 20px;
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
    }
    
    .resources-content-area {
        flex: 1;
        min-width: 0;
    }
    
    /* Dashboard Hero */
    .dashboard-hero {
        background: #ffffff;
        border-radius: 16px;
        padding: 32px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    .dashboard-hero h1 {
        margin: 0 0 8px 0;
        font-size: 32px;
        font-weight: 700;
        color: #1e293b;
    }
    .dashboard-hero-subtitle {
        margin: 0;
        color: #64748b;
        font-size: 15px;
    }
    
    /* Search */
    .search-bar-container {
        position: relative;
        flex: 1;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        display: flex;
        align-items: center;
        height: 48px;
        overflow: hidden;
    }
    .search-icon {
        position: absolute;
        left: 16px;
        color: #94a3b8;
        font-size: 16px;
    }
    .resource-search-input {
        width: 100%;
        padding: 12px 48px 12px 48px;
        border: none;
        outline: none;
        font-size: 14px;
        color: #1e293b;
        background: transparent;
    }
    .clear-search-btn {
        position: absolute;
        right: 12px;
        background: transparent;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 8px;
        font-size: 14px;
        border-radius: 50%;
    }
    .clear-search-btn:hover {
        color: #ef4444;
        background: #fee2e2;
    }
    
    /* Sidebar Filter Design */
    .resources-sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e2e8f0;
    }
    .resources-sidebar-title {
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .clear-filters-link {
        font-size: 13px;
        color: #3b82f6;
        text-decoration: none;
        font-weight: 500;
    }
    .clear-filters-link:hover {
        text-decoration: underline;
    }
    .filter-section {
        margin-bottom: 24px;
    }
    .filter-section-title {
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin: 0 0 12px 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
    }
    .filter-checkbox-list {
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 250px;
        overflow-y: auto;
    }
    .filter-checkbox-label {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        padding: 6px 4px;
        border-radius: 6px;
        font-size: 14px;
        color: #334155;
    }
    .filter-checkbox-label:hover {
        background: #f8fafc;
    }
    .filter-checkbox {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #3b82f6;
    }
    .filter-checkbox-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    
    /* Collapsible Unit & Lesson Accordion */
    .unit-lesson-accordion {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 8px 0;
    }
    .unit-accordion-item {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s ease;
    }
    .unit-accordion-item.active {
        border-color: #2563eb;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.05);
    }
    .unit-accordion-header {
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        user-select: none;
        background: #f8fafc;
        transition: background 0.2s ease;
    }
    .unit-accordion-header:hover {
        background: #f1f5f9;
    }
    .unit-accordion-item.active .unit-accordion-header {
        background: #f0f7ff;
        border-bottom: 1px solid #cbd5e1;
    }
    .unit-title-text {
        font-weight: 600;
        font-size: 14px;
        color: #1e293b;
    }
    .lesson-count-text {
        font-size: 12px;
        color: #94a3b8;
        margin-left: 8px;
        margin-right: auto;
    }
    .unit-accordion-header i.toggle-arrow {
        font-size: 12px;
        color: #64748b;
        transition: transform 0.2s ease;
    }
    .unit-accordion-item.active .unit-accordion-header i.toggle-arrow {
        transform: rotate(180deg);
        color: #2563eb;
    }
    .unit-accordion-body {
        display: none;
        padding: 16px;
        background: #ffffff;
    }
    .unit-accordion-item.active .unit-accordion-body {
        display: block;
    }
    .lesson-buttons-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .lesson-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #475569;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .lesson-btn:hover {
        border-color: #2563eb;
        color: #2563eb;
        background: #f0f7ff;
    }
    .lesson-btn.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
    }
    
    /* Grade Pills */
    .grade-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    .grade-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 38px;
        padding: 0;
        background: #f1f5f9;
        color: #475569;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    .grade-pill:hover {
        background: #e2e8f0;
    }
    .grade-pill.on {
        background: #3b82f6;
        color: white;
    }
    
    /* Curriculum Checkboxes Container */
    .curriculum-checkboxes-container {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .filter-checkbox-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        font-size: 14px;
        font-weight: 500;
    }
    .filter-checkbox-wrapper.checked {
        border-color: #3b82f6;
        background: #eff6ff;
        color: #3b82f6;
    }
    
    /* Grid and Cards */
    .resources-grid-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e2e8f0;
    }
    .resources-grid-title {
        font-size: 20px;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
    }
    .resources-grid-subtitle {
        font-size: 13px;
        color: #6c757d;
        margin: 0;
    }
    .resources-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
    .resource-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        position: relative;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    .resource-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    
    /* Card Edit Button */
    .card-edit-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid #cbd5e1;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #3b82f6;
        cursor: pointer;
        z-index: 10;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: all 0.2s;
    }
    .card-edit-btn:hover {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
        transform: scale(1.05);
    }
    
    .resource-card-image-container {
        height: 160px;
        width: 100%;
        position: relative;
        background: #f8f9fa;
        overflow: hidden;
    }
    .resource-card-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .resource-card-image-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .resource-card-format-tag {
        position: absolute;
        bottom: 10px;
        left: 10px;
        background: rgba(15, 23, 42, 0.75);
        color: white;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        display: flex;
        align-items: center;
        gap: 4px;
        font-weight: 500;
    }
    .resource-card-divider-line {
        height: 4px;
        width: 100%;
    }
    .resource-card-body {
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex: 1;
    }
    .resource-card-title {
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
        line-height: 1.4;
    }
    .resource-card-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .resource-card-tag {
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
    .resource-card-tag-course {
        background: #fee2e2;
        color: #ef4444;
    }
    .resource-card-tag-section {
        background: #e0f2fe;
        color: #0ea5e9;
    }
    .resource-card-tag-folder {
        background: #f3e8ff;
        color: #a855f7;
    }
    .resource-card-tag-unit {
        background: #dcfce7;
        color: #16a34a;
    }
    
    /* Color types for tags */
    [data-type="videos"] { background-color: #ec4899; }
    [data-type="images"] { background-color: #6f42c1; }
    [data-type="pdf"] { background-color: #dc3545; }
    [data-type="pptx"] { background-color: #fd7e14; }
    [data-type="xlsx"] { background-color: #28a745; }
    [data-type="csv"] { background-color: #28a745; }
    [data-type="docx"] { background-color: #007bff; }
    [data-type="url"] { background-color: #0ea5e9; }
    
    /* Tabs Control */
    .category-toggle-control {
        display: flex;
        background: #e2e8f0;
        padding: 4px;
        border-radius: 12px;
        gap: 4px;
        margin-bottom: 24px;
    }
    .category-toggle-item {
        flex: 1;
        padding: 10px;
        text-align: center;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s;
    }
    .category-toggle-item.active {
        background: white;
        color: #1e293b;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .category-toggle-count {
        background: #f1f5f9;
        color: #475569;
        padding: 1px 6px;
        border-radius: 10px;
        font-size: 11px;
    }
    .category-toggle-item.active .category-toggle-count {
        background: #3b82f6;
        color: white;
    }
    
    /* Modal styles */
    .edit-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    .edit-modal-content {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 500px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        overflow: hidden;
        animation: modalFadeIn 0.3s ease;
    }
    .edit-modal-header {
        background: #f8fafc;
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .edit-modal-title {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }
    .edit-modal-close {
        background: none;
        border: none;
        font-size: 20px;
        color: #64748b;
        cursor: pointer;
    }
    .edit-modal-body {
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .form-label {
        font-size: 13px;
        font-weight: 600;
        color: #475569;
    }
    .form-control {
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
    }
    .form-control:focus {
        border-color: #3b82f6;
    }
    .file-input-wrapper {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        position: relative;
        background: #f8fafc;
        transition: border-color 0.2s;
    }
    .file-input-wrapper:hover {
        border-color: #3b82f6;
    }
    .file-input-wrapper input[type="file"] {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    .image-preview-container {
        max-height: 150px;
        width: 100%;
        border-radius: 8px;
        overflow: hidden;
        margin-top: 10px;
        display: none;
        border: 1px solid #cbd5e1;
    }
    .image-preview-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .edit-modal-footer {
        padding: 16px 24px;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }
    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        border: none;
    }
    .btn-secondary {
        background: #e2e8f0;
        color: #475569;
    }
    .btn-secondary:hover {
        background: #cbd5e1;
    }
    .btn-primary {
        background: #3b82f6;
        color: white;
    }
    .btn-primary:hover {
        background: #2563eb;
    }
    
    @keyframes modalFadeIn {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    /* PPT Player Modal */
    .ppt-player-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        z-index: 9999;
        padding: 20px;
    }

    .ppt-player-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .ppt-player-content {
        background: white;
        border-radius: 12px;
        width: 100%;
        max-width: 1200px;
        height: 80vh;
        display: flex;
        flex-direction: column;
    }

    .ppt-player-header {
        padding: 20px 30px;
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .ppt-player-title {
        font-size: 20px;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
        flex: 1;
    }

    .ppt-player-header-controls {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .ppt-player-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ppt-player-icon-btn {
        background: none;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #475569;
        font-size: 18px;
        cursor: pointer;
        transition: background 0.2s ease, color 0.2s ease;
    }

    .ppt-player-icon-btn:hover {
        background: #e2e8f0;
        color: #1d4ed8;
    }

    .ppt-player-icon-btn.fullscreen-active {
        background: #1d4ed8;
        color: #fff;
    }

    .ppt-player-close {
        background: none;
        border: none;
        font-size: 28px;
        color: #6c757d;
        cursor: pointer;
        padding: 0;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .ppt-player-close:hover {
        background: #e9ecef;
        color: #212529;
    }

    .ppt-player-body {
        flex: 1;
        padding: 20px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .ppt-player-iframe {
        width: 100%;
        height: 100%;
        border: none;
        border-radius: 8px;
    }

    .ppt-spreadsheet-container {
        flex: 1;
        width: 100%;
        overflow: auto;
        background: #ffffff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 16px;
    }

    .ppt-spreadsheet-container table {
        width: 100%;
        border-collapse: collapse;
    }

    .ppt-spreadsheet-container th,
    .ppt-spreadsheet-container td {
        border: 1px solid #e2e8f0;
        padding: 6px 8px;
        font-size: 13px;
    }

    .ppt-spreadsheet-container h3 {
        margin: 0 0 12px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }

    .ppt-spreadsheet-container .sheet-block {
        margin-bottom: 24px;
    }

    .spreadsheet-error {
        color: #b91c1c;
    }

    .spreadsheet-loading {
        color: #4b5563;
        font-style: italic;
    }

    .ppt-download-btn {
        background: none;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #475569;
        font-size: 18px;
        cursor: pointer;
        transition: background 0.2s ease, color 0.2s ease;
    }

    .ppt-download-btn:hover {
        background: #e2e8f0;
        color: #1d4ed8;
    }

    .ppt-download-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        box-shadow: none;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Include admin sidebar -->
            <?php include(__DIR__ . '/includes/admin_sidebar.php'); ?>

            <div class="main-content">
                <!-- Page Header -->
                <div class="dashboard-hero">
                    <h1>Resource Management</h1>
                    <p class="dashboard-hero-subtitle">Edit metadata, Unit & Lesson prefixes, and upload preview images for teaching materials.</p>
                </div>

                <!-- Navigation Tabs -->
                <div class="category-toggle-control" id="resourceTabToggle">
                    <div class="category-toggle-item active" data-tab="all" onclick="filterByResourceType('all')">
                        <i class="fa fa-th"></i>
                        <span class="category-toggle-label">All Resources</span>
                        <span class="category-toggle-count" id="tierCardCountAll"><?php echo count($all_resources); ?></span>
                    </div>
                    <div class="category-toggle-item" data-tab="plan" onclick="filterByResourceType('plan')">
                        <i class="fa fa-lightbulb"></i>
                        <span class="category-toggle-label">Plan</span>
                        <span class="category-toggle-count" id="tierCardCountPlan"><?php echo $resource_counts['plan']; ?></span>
                    </div>
                    <div class="category-toggle-item" data-tab="teach" onclick="filterByResourceType('teach')">
                        <i class="fa fa-chalkboard-user"></i>
                        <span class="category-toggle-label">Teach</span>
                        <span class="category-toggle-count" id="tierCardCountTeach"><?php echo $resource_counts['teach']; ?></span>
                    </div>
                    <div class="category-toggle-item" data-tab="assess" onclick="filterByResourceType('assess')">
                        <i class="fa fa-edit"></i>
                        <span class="category-toggle-label">Assess</span>
                        <span class="category-toggle-count" id="tierCardCountAssess"><?php echo $resource_counts['assess']; ?></span>
                    </div>
                </div>

                <!-- Main Layout -->
                <div class="resources-main-layout">
                    <!-- Left Sidebar Filters -->
                    <div class="resources-sidebar">
                        <div class="resources-sidebar-header">
                            <h3 class="resources-sidebar-title">
                                <i class="fa fa-filter"></i> Filters
                            </h3>
                            <a href="#" class="clear-filters-link" onclick="resetAllFilters(); return false;">Clear all</a>
                        </div>

                        <!-- Curriculum Filter (conditional) -->
                        <?php if ($has_ksa_resources && $has_gcc_resources): ?>
                            <div class="filter-section curriculum-filter-section">
                                <h4 class="filter-section-title">
                                    <span>Curriculum</span>
                                </h4>
                                <ul class="filter-checkbox-list" id="curriculumFilters">
                                    <li class="filter-checkbox-item">
                                        <label class="filter-checkbox-label" id="label_curr_ksa">
                                            <input type="checkbox" id="sidebar_curr_ksa" class="filter-checkbox sidebar-curr-checkbox" value="ksa" <?php echo ($default_curriculum === 'ksa') ? 'checked' : ''; ?> onchange="handleCurriculumChange('ksa')">
                                            KSA
                                        </label>
                                    </li>
                                    <li class="filter-checkbox-item">
                                        <label class="filter-checkbox-label" id="label_curr_gcc">
                                            <input type="checkbox" id="sidebar_curr_gcc" class="filter-checkbox sidebar-curr-checkbox" value="gcc" <?php echo ($default_curriculum === 'gcc') ? 'checked' : ''; ?> onchange="handleCurriculumChange('gcc')">
                                            GCC
                                        </label>
                                    </li>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Grade Filter -->
                        <?php if (!empty($teacher_grade_numbers)): ?>
                            <div class="filter-section grade-filter-section">
                                <h4 class="filter-section-title">
                                    <span>Grade</span>
                                </h4>
                                <div class="grade-pills">
                                    <?php foreach ($teacher_grade_numbers as $grade): ?>
                                        <span class="grade-pill" data-grade="<?php echo $grade; ?>" onclick="toggleGrade(<?php echo $grade; ?>)">G<?php echo $grade; ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Sections Filter -->
                        <div class="filter-section" id="sectionsFilterCheckboxSection">
                            <h4 class="filter-section-title">
                                <span>Sections</span>
                            </h4>
                            <ul class="filter-checkbox-list" id="sectionsFilters">
                                <!-- Populated dynamically -->
                            </ul>
                        </div>

                        <!-- Units Filter -->
                        <div class="filter-section" id="sidebarUnitFilterSection">
                            <h4 class="filter-section-title">
                                <span>Unit & Lesson</span>
                            </h4>
                            <div class="unit-lesson-accordion" id="sidebarUnitFilters">
                                <!-- Populated dynamically -->
                            </div>
                        </div>

                        <!-- File Type Filter -->
                        <div class="filter-section" id="sidebarResourceTypeFilterSection">
                            <h4 class="filter-section-title">
                                <span>File Type</span>
                            </h4>
                            <ul class="filter-checkbox-list" id="sidebarResourceTypeFilters">
                                <!-- Populated dynamically -->
                            </ul>
                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="resources-content-area">
                        <!-- Search Bar -->
                        <div class="dashboard-hero-search-filters" style="margin-bottom: 24px;">
                            <div class="search-bar-container">
                                <i class="fa fa-search search-icon"></i>
                                <input type="text" id="resourceSearch" class="resource-search-input" placeholder="Search resources by name, keyword, or category..." onkeyup="filterResources()">
                                <button class="clear-search-btn" onclick="clearSearch()" style="display: none;">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="resources-grid-container">
                            <div class="resources-grid-header">
                                <div>
                                    <h3 class="resources-grid-title" id="gridTitle">All Resources</h3>
                                </div>
                                <div class="resources-count" id="resourcesCount"><?php echo count($all_resources); ?> resources</div>
                            </div>

                            <div class="resources-grid" id="resourcesGrid">
                                <?php foreach ($all_resources as $resource_item): 
                                    $meta_key = '';
                                    $res_id = 0;
                                    $res_type = '';
                                    
                                    if ($resource_item['type'] === 'file') {
                                        $file = $resource_item['file'];
                                        $res_id = $file->get_id();
                                        $res_type = 'file';
                                        $filename = $file->get_filename();
                                        $file_extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION) ?: 'FILE');
                                        $fileurl = theme_remui_kids_teacher_generate_file_url($file, $USER->id)->out(false);
                                        $preview_image_url = theme_remui_kids_teacher_generate_preview_url($file, $USER->id, $file_extension);
                                        $display_name = theme_remui_kids_teacher_strip_extension($filename);
                                    } else {
                                        $cm = $resource_item['cm'];
                                        $res_id = $cm->id;
                                        $res_type = 'cm';
                                        $file_extension = strtoupper($cm->modname);
                                        $display_name = format_string($cm->name);
                                        $fileurl = '';
                                        $preview_image_url = null;
                                        
                                        if ($cm->modname === 'resource') {
                                            $resourcecontext = context_module::instance($cm->id);
                                            $resourcefiles = $filestorage->get_area_files($resourcecontext->id, 'mod_resource', 'content', 0, 'sortorder, id', false);
                                            if (!empty($resourcefiles)) {
                                                $resourcefile = reset($resourcefiles);
                                                $fileurl = theme_remui_kids_teacher_generate_file_url($resourcefile, $USER->id)->out(false);
                                                $resourcefileext = strtolower(pathinfo($resourcefile->get_filename(), PATHINFO_EXTENSION));
                                                $file_extension = strtoupper($resourcefileext);
                                                $preview_image_url = theme_remui_kids_teacher_generate_preview_url($resourcefile, $USER->id, $file_extension);
                                            }
                                        } else if ($cm->modname === 'url') {
                                            $urlrecord = $DB->get_record('url', ['id' => $cm->instance], '*', IGNORE_MISSING);
                                            if ($urlrecord) {
                                                $fileurl = $urlrecord->externalurl;
                                            }
                                        }
                                    }
                                    
                                    $meta_key = $res_type . '_' . $res_id;
                                    $meta = isset($res_meta_map[$meta_key]) ? $res_meta_map[$meta_key] : null;
                                    
                                    $unit_val = $meta ? $meta->unit : '';
                                    $lesson_val = $meta ? $meta->lesson : '';
                                    $custom_preview = $meta ? $meta->preview_image : '';
                                    
                                    // Determine icon & bg color
                                    $icon_class = 'fa-file';
                                    $icon_color = '#6c757d';
                                    $bg_color = '#f8f9fa';
                                    
                                    if ($file_extension === 'PDF') {
                                        $icon_class = 'fa-file-pdf'; $icon_color = '#dc3545'; $bg_color = '#f8d7da';
                                    } else if ($file_extension === 'PPTX' || $file_extension === 'PPT') {
                                        $icon_class = 'fa-file-powerpoint'; $icon_color = '#fd7e14'; $bg_color = '#ffe5d0';
                                    } else if ($file_extension === 'XLSX' || $file_extension === 'XLS' || $file_extension === 'CSV') {
                                        $icon_class = 'fa-file-excel'; $icon_color = '#28a745'; $bg_color = '#d4edda';
                                    } else if ($file_extension === 'DOCX' || $file_extension === 'DOC') {
                                        $icon_class = 'fa-file-word'; $icon_color = '#007bff'; $bg_color = '#cfe2ff';
                                    } else if (in_array($file_extension, ['PNG', 'JPG', 'JPEG', 'GIF', 'SVG', 'BMP', 'WEBP'])) {
                                        $icon_class = 'fa-file-image'; $icon_color = '#6f42c1'; $bg_color = '#e2d9f3';
                                    } else if (in_array($file_extension, ['MP4', 'AVI', 'MOV', 'WMV', 'MKV', 'WEBM', 'HTML', 'HTM'])) {
                                        $icon_class = 'fa-file-video'; $icon_color = '#e83e8c'; $bg_color = '#f7d6e6';
                                    }
                                    
                                    $format_type = theme_remui_kids_teacher_resource_filter_type($file_extension);
                                    
                                    $course_id = $resource_item['course']->id;
                                    $course_name = $resource_item['course']->fullname;
                                    $section_name = $resource_item['section'];
                                    $folder_name = $resource_item['folder_name'];
                                    $folder_tag = $resource_item['folder_tag'];
                                    $grade_number = 0;
                                    if (preg_match('/\bGrade\s*(\d{1,2})\b/i', $course_name, $m)) {
                                        $grade_number = (int)$m[1];
                                    }
                                    
                                    // Title with prefix
                                    $prefix = '';
                                    if ($unit_val !== '' || $lesson_val !== '') {
                                        $has_unit = ($unit_val === '' || stripos($display_name, trim($unit_val)) !== false);
                                        $has_lesson = ($lesson_val === '' || stripos($display_name, trim($lesson_val)) !== false);
                                        if (!($has_unit && $has_lesson)) {
                                            $prefix = trim($unit_val . ' ' . $lesson_val) . ' ';
                                        }
                                    }
                                    $title_display = $prefix . $display_name;

                                    // Construct office viewer URL
                                    $office_embed_url = '';
                                    if (in_array(strtolower($file_extension), ['ppt', 'pptx', 'xls', 'xlsx', 'csv', 'doc', 'docx', 'odt', 'ods'])) {
                                        $office_src = $fileurl;
                                        if (strpos($office_src, 'http') !== 0) {
                                            $office_src = $CFG->wwwroot . $office_src;
                                        }
                                        $office_embed_url = 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($office_src);
                                    }
                                ?>
                                <div class="resource-card" 
                                     data-res-type="<?php echo $res_type; ?>"
                                     data-res-id="<?php echo $res_id; ?>"
                                     data-unit="<?php echo htmlspecialchars($unit_val); ?>"
                                     data-lesson="<?php echo htmlspecialchars($lesson_val); ?>"
                                     data-grade="<?php echo $grade_number; ?>"
                                     data-resource-type="<?php echo htmlspecialchars($format_type); ?>"
                                     data-category="<?php echo htmlspecialchars($resource_item['category']); ?>"
                                     data-category-id="<?php echo $resource_item['category_id']; ?>"
                                     data-course-id="<?php echo $course_id; ?>"
                                     data-course-name="<?php echo htmlspecialchars($course_name); ?>"
                                     data-section="<?php echo htmlspecialchars($section_name); ?>"
                                     data-folder-name="<?php echo htmlspecialchars($folder_name); ?>"
                                     data-folder-tag="<?php echo htmlspecialchars(strtolower($folder_tag ?: '')); ?>"
                                     data-title-raw="<?php echo htmlspecialchars($display_name); ?>"
                                     data-file-url="<?php echo htmlspecialchars($fileurl); ?>"
                                     data-file-ext="<?php echo htmlspecialchars(strtolower($file_extension)); ?>"
                                     data-preview-url="<?php echo htmlspecialchars($preview_image_url ?? ''); ?>"
                                     data-office-viewer-url="<?php echo htmlspecialchars($office_embed_url); ?>"
                                     data-file-name="<?php echo htmlspecialchars($display_name); ?>"
                                     data-mod-name="<?php echo $res_type === 'cm' ? htmlspecialchars($cm->modname) : ''; ?>"
                                     data-cm-id="<?php echo $res_type === 'cm' ? $cm->id : ''; ?>"
                                     onclick="openAdminResource(this, event)">
                                     
                                     <!-- Edit Button -->
                                     <button class="card-edit-btn" onclick="openEditModal(this.closest('.resource-card'))">
                                         <i class="fa fa-pencil-alt"></i>
                                     </button>

                                     <!-- Image Preview -->
                                     <div class="resource-card-image-container">
                                         <?php if ($custom_preview): ?>
                                             <img class="resource-card-image" src="<?php echo htmlspecialchars($custom_preview); ?>" alt="" loading="lazy">
                                         <?php elseif (in_array(strtolower($file_extension), ['png', 'jpg', 'jpeg', 'gif', 'svg', 'bmp', 'webp']) && $preview_image_url): ?>
                                             <img class="resource-card-image" src="<?php echo htmlspecialchars($preview_image_url); ?>" alt="" loading="lazy">
                                         <?php else: ?>
                                             <div class="resource-card-image-placeholder" style="background: <?php echo $bg_color; ?>;">
                                                 <i class="fa <?php echo $icon_class; ?>" style="font-size: 56px; color: <?php echo $icon_color; ?>;"></i>
                                             </div>
                                         <?php endif; ?>
                                         <div class="resource-card-format-tag" data-type="<?php echo htmlspecialchars($format_type); ?>">
                                             <i class="fa <?php echo $icon_class; ?>"></i>
                                             <span><?php echo htmlspecialchars(strtoupper($format_type)); ?></span>
                                         </div>
                                     </div>

                                     <div class="resource-card-divider-line" data-type="<?php echo htmlspecialchars($format_type); ?>"></div>
                                     
                                     <div class="resource-card-body">
                                         <h4 class="resource-card-title"><?php echo htmlspecialchars($title_display); ?></h4>
                                         <div class="resource-card-tags">
                                             <?php if ($grade_number > 0): ?>
                                                 <span class="resource-card-tag resource-card-tag-course">Grade <?php echo $grade_number; ?></span>
                                             <?php endif; ?>
                                             <?php if ($section_name): ?>
                                                 <span class="resource-card-tag resource-card-tag-section"><?php echo htmlspecialchars(explode(' > ', $section_name)[count(explode(' > ', $section_name)) - 1]); ?></span>
                                             <?php endif; ?>
                                             <?php if ($folder_name): ?>
                                                 <span class="resource-card-tag resource-card-tag-folder"><?php echo htmlspecialchars($folder_name); ?></span>
                                             <?php endif; ?>
                                             <?php if ($unit_val !== '' || $lesson_val !== ''): ?>
                                                 <span class="resource-card-tag resource-card-tag-unit"><?php echo htmlspecialchars(trim($unit_val . ' ' . $lesson_val)); ?></span>
                                             <?php endif; ?>
                                         </div>
                                     </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- No resources -->
                            <div id="noResourcesFound" style="display: none; padding: 40px; text-align: center; color: #64748b;">
                                <i class="fa fa-search fa-3x" style="margin-bottom: 10px;"></i>
                                <p style="font-size: 15px; font-weight: 500;">No resources match the selected filters.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Resource Modal -->
<div class="edit-modal" id="editResourceModal">
    <div class="edit-modal-content">
        <div class="edit-modal-header">
            <h3 class="edit-modal-title">Edit Resource Metadata</h3>
            <button class="edit-modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editResourceForm" onsubmit="saveResourceMetadata(event)">
            <input type="hidden" id="edit_res_type" name="res_type">
            <input type="hidden" id="edit_res_id" name="res_id">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            
            <div class="edit-modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_title_raw">Resource Title</label>
                    <input type="text" id="edit_title_raw" class="form-control" readonly style="background: #f1f5f9; color: #64748b;">
                </div>
                
                <div style="display: grid; grid-cols: 1fr 1fr; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_unit">Unit</label>
                        <input type="text" id="edit_unit" name="unit" class="form-control" placeholder="e.g. U1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_lesson">Lesson</label>
                        <input type="text" id="edit_lesson" name="lesson" class="form-control" placeholder="e.g. L1">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Preview Image</label>
                    <div class="file-input-wrapper">
                        <i class="fa fa-cloud-upload-alt fa-2x" style="color: #94a3b8; margin-bottom: 6px; display: block;"></i>
                        <span style="font-size: 13px; color: #64748b; font-weight: 500;">Click to upload or drag & drop</span>
                        <input type="file" name="preview_image" accept="image/*" onchange="previewSelectedImage(this)">
                    </div>
                    <div class="image-preview-container" id="modalPreviewContainer">
                        <!-- Preview image renders here -->
                    </div>
                </div>
            </div>
            
            <div class="edit-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- PPT Player Modal -->
<div id="pptPlayerModal" class="ppt-player-modal">
    <div class="ppt-player-content" id="pptPlayerContent">
        <div class="ppt-player-header">
            <h3 class="ppt-player-title" id="pptPlayerTitle">Resource Viewer</h3>
            <div class="ppt-player-header-controls">
                <div class="ppt-player-actions">
                    <button id="pptDownloadButton" class="ppt-download-btn" type="button"
                        onclick="downloadCurrentResource()" title="Download File" disabled>
                        <i class="fa fa-download"></i>
                    </button>
                    <button id="pptFullscreenButton" class="ppt-player-icon-btn" type="button"
                        onclick="togglePPTFullscreen()" title="Toggle fullscreen" aria-pressed="false"
                        style="display:none;">
                        <i class="fa fa-expand"></i>
                    </button>
                </div>
                <button class="ppt-player-close" onclick="closePPTPlayer()">×</button>
            </div>
        </div>
        <div class="ppt-player-body">
            <iframe id="pptPlayerIframe" class="ppt-player-iframe" src="" allowfullscreen></iframe>
            <div id="pptSpreadsheetContainer" class="ppt-spreadsheet-container" hidden></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
</script>

<script>
    // Client-side filtering logic
    let currentTab = 'all';
    let currentCurriculum = 'all';
    <?php if ($has_ksa_resources && $has_gcc_resources): ?>
    currentCurriculum = '<?php echo $default_curriculum; ?>';
    <?php endif; ?>
    let selectedGrades = [];
    let selectedUnitFilter = null;
    let selectedLessonFilter = null;

    // Initialize Page
    document.addEventListener('DOMContentLoaded', () => {
        populateSidebarFilters();
        filterResources();
    });

    function filterByResourceType(tab) {
        currentTab = tab;
        document.querySelectorAll('.category-toggle-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`.category-toggle-item[data-tab="${tab}"]`).classList.add('active');
        filterResources();
    }

    function handleCurriculumChange(curr) {
        const ksa = document.getElementById('sidebar_curr_ksa');
        const gcc = document.getElementById('sidebar_curr_gcc');
        
        if (curr === 'ksa' && ksa.checked) {
            if (gcc) gcc.checked = false;
            currentCurriculum = 'ksa';
        } else if (curr === 'gcc' && gcc.checked) {
            if (ksa) ksa.checked = false;
            currentCurriculum = 'gcc';
        } else {
            // Keep at least one selected
            if (curr === 'ksa') ksa.checked = true;
            else if (gcc) gcc.checked = true;
        }
        filterResources();
    }

    function toggleGrade(grade) {
        const pill = document.querySelector(`.grade-pill[data-grade="${grade}"]`);
        if (!pill) return;
        
        pill.classList.toggle('on');
        const idx = selectedGrades.indexOf(grade);
        if (pill.classList.contains('on')) {
            if (idx === -1) selectedGrades.push(grade);
        } else {
            if (idx !== -1) selectedGrades.splice(idx, 1);
        }
        filterResources();
    }

    function resetAllFilters() {
        document.querySelectorAll('.filter-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('.grade-pill.on').forEach(p => p.classList.remove('on'));
        
        selectedUnitFilter = null;
        selectedLessonFilter = null;
        document.querySelectorAll('#sidebarUnitFilters .unit-accordion-item').forEach(item => {
            item.classList.remove('active');
            const body = item.querySelector('.unit-accordion-body');
            if (body) body.style.display = 'none';
        });
        document.querySelectorAll('#sidebarUnitFilters .lesson-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        selectedGrades = [];
        currentTab = 'all';
        <?php if ($has_ksa_resources && $has_gcc_resources): ?>
        currentCurriculum = '<?php echo $default_curriculum; ?>';
        document.getElementById('sidebar_curr_<?php echo $default_curriculum; ?>').checked = true;
        <?php else: ?>
        currentCurriculum = 'all';
        <?php endif; ?>
        
        document.querySelectorAll('.category-toggle-item').forEach(item => item.classList.remove('active'));
        document.querySelector('.category-toggle-item[data-tab="all"]').classList.add('active');
        
        document.getElementById('resourceSearch').value = '';
        
        filterResources();
    }

    function clearSearch() {
        document.getElementById('resourceSearch').value = '';
        filterResources();
    }

    function formatUnitName(unitVal) {
        if (!unitVal) return '';
        const num = unitVal.replace(/^\D+/g, '');
        return 'Unit ' + (num || unitVal);
    }

    function formatLessonName(lessonVal) {
        if (!lessonVal) return '';
        if (lessonVal.toUpperCase().startsWith('L')) {
            return lessonVal.toUpperCase();
        }
        return 'L' + lessonVal;
    }

    function populateSidebarFilters() {
        const allCards = document.querySelectorAll('.resource-card');
        const sectionsSet = new Set();
        const unitsMap = {}; // unit -> Set of lessons
        const typesSet = new Set();
        
        allCards.forEach(card => {
            const section = card.getAttribute('data-section');
            if (section) {
                const parts = section.split(' > ');
                sectionsSet.add(parts[parts.length - 1]);
            }
            const unit = (card.getAttribute('data-unit') || '').trim();
            const lesson = (card.getAttribute('data-lesson') || '').trim();
            if (unit !== '') {
                if (!unitsMap[unit]) {
                    unitsMap[unit] = new Set();
                }
                if (lesson !== '') {
                    unitsMap[unit].add(lesson);
                }
            }
            const type = card.getAttribute('data-resource-type');
            if (type) {
                typesSet.add(type);
            }
        });
        
        // Sections Filters
        const sectionsFilters = document.getElementById('sectionsFilters');
        sectionsFilters.innerHTML = '';
        Array.from(sectionsSet).sort().forEach(sec => {
            const li = document.createElement('li');
            li.innerHTML = `<label class="filter-checkbox-label"><input type="checkbox" class="filter-checkbox sec-checkbox" data-filter-value="${sec}" onchange="filterResources()"> ${sec}</label>`;
            sectionsFilters.appendChild(li);
        });
        
        // Units & Lessons accordion
        const sidebarUnitFilters = document.getElementById('sidebarUnitFilters');
        sidebarUnitFilters.innerHTML = '';
        
        const units = Object.keys(unitsMap).sort((a, b) => {
            const aNum = parseInt(a.replace(/^\D+/g, '')) || 0;
            const bNum = parseInt(b.replace(/^\D+/g, '')) || 0;
            if (aNum !== bNum) return aNum - bNum;
            return a.localeCompare(b);
        });
        
        // Check if previously selected unit is still valid
        if (selectedUnitFilter && !units.includes(selectedUnitFilter)) {
            selectedUnitFilter = null;
            selectedLessonFilter = null;
        }

        units.forEach(unit => {
            const lessons = Array.from(unitsMap[unit]).sort((a, b) => {
                const aNum = parseInt(a.replace(/^\D+/g, '')) || 0;
                const bNum = parseInt(b.replace(/^\D+/g, '')) || 0;
                if (aNum !== bNum) return aNum - bNum;
                return a.localeCompare(b);
            });
            const lessonCount = lessons.length;
            const isUnitActive = (selectedUnitFilter === unit);

            // Create accordion item
            const itemDiv = document.createElement('div');
            itemDiv.className = 'unit-accordion-item' + (isUnitActive ? ' active' : '');
            itemDiv.setAttribute('data-unit-value', unit);

            // Accordion header
            const headerDiv = document.createElement('div');
            headerDiv.className = 'unit-accordion-header';

            const titleSpan = document.createElement('span');
            titleSpan.className = 'unit-title-text';
            titleSpan.textContent = formatUnitName(unit);

            const countSpan = document.createElement('span');
            countSpan.className = 'lesson-count-text';
            countSpan.textContent = lessonCount + ' lesson' + (lessonCount !== 1 ? 's' : '');

            const arrowIcon = document.createElement('i');
            arrowIcon.className = 'fa fa-chevron-down toggle-arrow';

            headerDiv.appendChild(titleSpan);
            headerDiv.appendChild(countSpan);
            headerDiv.appendChild(arrowIcon);

            // Accordion body
            const bodyDiv = document.createElement('div');
            bodyDiv.className = 'unit-accordion-body';
            if (isUnitActive) {
                bodyDiv.style.display = 'block';
            }

            const gridDiv = document.createElement('div');
            gridDiv.className = 'lesson-buttons-grid';

            lessons.forEach(lesson => {
                const lessonBtn = document.createElement('button');
                lessonBtn.type = 'button';
                lessonBtn.className = 'lesson-btn' + (isUnitActive && selectedLessonFilter === lesson ? ' active' : '');
                lessonBtn.textContent = formatLessonName(lesson);
                lessonBtn.onclick = function (e) {
                    e.stopPropagation();
                    if (selectedLessonFilter === lesson) {
                        selectedLessonFilter = null;
                        lessonBtn.classList.remove('active');
                    } else {
                        selectedLessonFilter = lesson;
                        gridDiv.querySelectorAll('.lesson-btn').forEach(btn => btn.classList.remove('active'));
                        lessonBtn.classList.add('active');
                    }
                    filterResources();
                };
                gridDiv.appendChild(lessonBtn);
            });

            bodyDiv.appendChild(gridDiv);

            // Click handler for expanding/collapsing unit
            headerDiv.onclick = function () {
                const isCurrentlyActive = itemDiv.classList.contains('active');
                
                // Deactivate all items first
                sidebarUnitFilters.querySelectorAll('.unit-accordion-item').forEach(item => {
                    item.classList.remove('active');
                    const body = item.querySelector('.unit-accordion-body');
                    if (body) body.style.display = 'none';
                });

                if (!isCurrentlyActive) {
                    itemDiv.classList.add('active');
                    bodyDiv.style.display = 'block';
                    selectedUnitFilter = unit;
                    selectedLessonFilter = null; // reset lesson filter on expanding a new unit
                } else {
                    selectedUnitFilter = null;
                    selectedLessonFilter = null;
                }
                filterResources();
            };

            itemDiv.appendChild(headerDiv);
            itemDiv.appendChild(bodyDiv);
            sidebarUnitFilters.appendChild(itemDiv);
        });
        
        // Types Filters
        const sidebarResourceTypeFilters = document.getElementById('sidebarResourceTypeFilters');
        sidebarResourceTypeFilters.innerHTML = '';
        Array.from(typesSet).sort().forEach(type => {
            const li = document.createElement('li');
            li.innerHTML = `<label class="filter-checkbox-label"><input type="checkbox" class="filter-checkbox type-checkbox" data-filter-value="${type}" onchange="filterResources()"> ${type.toUpperCase()}</label>`;
            sidebarResourceTypeFilters.appendChild(li);
        });
    }

    function filterResources() {
        const searchVal = document.getElementById('resourceSearch').value.toLowerCase();
        const clearBtn = document.querySelector('.clear-search-btn');
        if (clearBtn) clearBtn.style.display = searchVal ? 'block' : 'none';

        // Get selected checklist filters
        const activeSections = Array.from(document.querySelectorAll('.sec-checkbox:checked')).map(cb => cb.getAttribute('data-filter-value'));
        const activeTypes = Array.from(document.querySelectorAll('.type-checkbox:checked')).map(cb => cb.getAttribute('data-filter-value'));
        
        const allCards = document.querySelectorAll('.resource-card');
        let visibleCount = 0;
        
        allCards.forEach(card => {
            const cardName = card.querySelector('.resource-card-title').textContent.toLowerCase();
            const cardType = card.getAttribute('data-resource-type') || '';
            const cardCategory = (card.getAttribute('data-category') || '').toLowerCase();
            const cardSection = card.getAttribute('data-section') || '';
            const cardSectionParts = cardSection.split(' > ');
            const cardSectionLast = cardSectionParts[cardSectionParts.length - 1];
            const cardUnit = card.getAttribute('data-unit') || '';
            const cardGrade = parseInt(card.getAttribute('data-grade') || '0');
            const cardFolderTag = card.getAttribute('data-folder-tag') || '';
            
            // Tab Filter
            let matchesTab = true;
            if (currentTab !== 'all') {
                matchesTab = cardFolderTag === currentTab;
            }
            
            // Curriculum Filter
            let matchesCurriculum = true;
            if (currentCurriculum !== 'all') {
                matchesCurriculum = (currentCurriculum === 'ksa' && cardCategory.includes('ksa')) ||
                                    (currentCurriculum === 'gcc' && cardCategory.includes('gcc'));
            }
            
            // Grade Filter
            let matchesGrade = true;
            if (selectedGrades.length > 0) {
                matchesGrade = selectedGrades.includes(cardGrade);
            }
            
            // Search Filter
            let matchesSearch = true;
            if (searchVal) {
                matchesSearch = cardName.includes(searchVal) || cardCategory.includes(searchVal);
            }
            
            // Section checkboxes
            let matchesSection = true;
            if (activeSections.length > 0) {
                matchesSection = activeSections.includes(cardSectionLast);
            }
            
            // Unit & Lesson filter
            let matchesUnit = true;
            if (selectedUnitFilter) {
                if (cardUnit !== selectedUnitFilter) {
                    matchesUnit = false;
                } else if (selectedLessonFilter) {
                    const cardLesson = card.getAttribute('data-lesson') || '';
                    if (cardLesson !== selectedLessonFilter) {
                        matchesUnit = false;
                    }
                }
            }
            
            // File type checkboxes
            let matchesType = true;
            if (activeTypes.length > 0) {
                matchesType = activeTypes.includes(cardType);
            }
            
            if (matchesTab && matchesCurriculum && matchesGrade && matchesSearch && matchesSection && matchesUnit && matchesType) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        document.getElementById('resourcesCount').textContent = `${visibleCount} resource${visibleCount !== 1 ? 's' : ''}`;
        document.getElementById('noResourcesFound').style.display = visibleCount === 0 ? 'block' : 'none';
        
        // Update Title dynamically
        const titleEl = document.getElementById('gridTitle');
        if (currentTab === 'all') titleEl.textContent = 'All Resources';
        else if (currentTab === 'plan') titleEl.textContent = 'Planning Materials';
        else if (currentTab === 'teach') titleEl.textContent = 'Teaching resources';
        else if (currentTab === 'assess') titleEl.textContent = 'Assessments';
    }

    // Modal and Metadata Saving
    function openEditModal(card) {
        document.getElementById('edit_res_type').value = card.getAttribute('data-res-type');
        document.getElementById('edit_res_id').value = card.getAttribute('data-res-id');
        document.getElementById('edit_title_raw').value = card.getAttribute('data-title-raw');
        document.getElementById('edit_unit').value = card.getAttribute('data-unit');
        document.getElementById('edit_lesson').value = card.getAttribute('data-lesson');
        
        const previewImg = card.querySelector('.resource-card-image');
        const previewContainer = document.getElementById('modalPreviewContainer');
        
        if (previewImg) {
            previewContainer.innerHTML = `<img src="${previewImg.src}" alt="Preview Image">`;
            previewContainer.style.display = 'block';
        } else {
            previewContainer.innerHTML = '';
            previewContainer.style.display = 'none';
        }
        
        document.getElementById('editResourceModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editResourceModal').style.display = 'none';
        document.getElementById('editResourceForm').reset();
    }

    function previewSelectedImage(input) {
        const container = document.getElementById('modalPreviewContainer');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                container.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                container.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function saveResourceMetadata(event) {
        event.preventDefault();
        
        const form = document.getElementById('editResourceForm');
        const formData = new FormData(form);
        const submitBtn = form.querySelector('.btn-primary');
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        
        fetch('<?php echo $CFG->wwwroot; ?>/theme/remui_kids/admin/ajax/save_resource_metadata.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Find resource card and update attributes & title
                const resType = formData.get('res_type');
                const resId = formData.get('res_id');
                const card = document.querySelector(`.resource-card[data-res-type="${resType}"][data-res-id="${resId}"]`);
                
                if (card) {
                    card.setAttribute('data-unit', data.unit);
                    card.setAttribute('data-lesson', data.lesson);
                    
                    // Update Title display
                    const rawTitle = card.getAttribute('data-title-raw');
                    let prefix = '';
                    if (data.unit !== '' || data.lesson !== '') {
                        const hasUnit = (data.unit === '' || rawTitle.toLowerCase().includes(data.unit.trim().toLowerCase()));
                        const hasLesson = (data.lesson === '' || rawTitle.toLowerCase().includes(data.lesson.trim().toLowerCase()));
                        if (!(hasUnit && hasLesson)) {
                            prefix = (data.unit + ' ' + data.lesson).trim() + ' ';
                        }
                    }
                    card.querySelector('.resource-card-title').textContent = prefix + rawTitle;
                    
                    // Update preview image
                    if (data.preview_image) {
                        const imageContainer = card.querySelector('.resource-card-image-container');
                        const img = imageContainer.querySelector('.resource-card-image');
                        const placeholder = imageContainer.querySelector('.resource-card-image-placeholder');
                        
                        if (img) {
                            img.src = data.preview_image;
                        } else {
                            if (placeholder) placeholder.style.display = 'none';
                            const newImg = document.createElement('img');
                            newImg.className = 'resource-card-image';
                            newImg.src = data.preview_image;
                            imageContainer.insertBefore(newImg, imageContainer.firstChild);
                        }
                    }
                    
                    // Re-render unit pills / tags
                    const tagsContainer = card.querySelector('.resource-card-tags');
                    const existingUnitTag = tagsContainer.querySelector('.resource-card-tag-unit');
                    if (existingUnitTag) {
                        existingUnitTag.remove();
                    }
                    if (data.unit !== '' || data.lesson !== '') {
                        const tag = document.createElement('span');
                        tag.className = 'resource-card-tag resource-card-tag-unit';
                        tag.textContent = (data.unit + ' ' + data.lesson).trim();
                        tagsContainer.appendChild(tag);
                    }
                }
                
                closeEditModal();
                populateSidebarFilters();
                filterResources();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred while saving.');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Changes';
        });
    }

    function openAdminResource(card, event) {
        if (event.target.closest('.card-edit-btn')) return;
        const fileUrl = card.getAttribute('data-file-url');
        const modName = card.getAttribute('data-mod-name');
        const cmId = card.getAttribute('data-cm-id');
        
        if (fileUrl && fileUrl.trim() !== '') {
            const ext = (card.getAttribute('data-file-ext') || '').toLowerCase();
            const name = card.getAttribute('data-file-name') || 'Resource';
            const previewUrl = card.getAttribute('data-preview-url') || '';
            const officeViewerUrl = card.getAttribute('data-office-viewer-url') || '';
            
            // Open in modal
            openPPTPlayer(fileUrl, name, ext, { allowDownload: true, previewUrl, officeViewerUrl });
        } else if (modName && cmId) {
            const url = '<?php echo $CFG->wwwroot; ?>/mod/' + modName + '/view.php?id=' + cmId;
            window.open(url, '_blank');
        }
    }

    // PPT Player Modal integration
    const OFFICE_VIEWER_ENABLED = true;
    const PPT_FULLSCREEN_ELEMENT_ID = 'pptPlayerContent';
    const spreadsheetExtensions = ['xls', 'xlsx', 'xlsm', 'csv'];
    const officeViewerExtensions = ['doc', 'docx'];
    const pptExtensions = ['ppt', 'pptx', 'pps', 'ppsx'];
    let teacherResourceOriginalUrl = '';

    function openPPTPlayer(url, title, ext, options = {}) {
        const modal = document.getElementById('pptPlayerModal');
        const iframe = document.getElementById('pptPlayerIframe');
        const spreadsheetContainer = document.getElementById('pptSpreadsheetContainer');
        const titleElement = document.getElementById('pptPlayerTitle');
        const downloadButton = document.getElementById('pptDownloadButton');
        const fullscreenButton = document.getElementById('pptFullscreenButton');
        const extension = (ext || '').toLowerCase();
        const isSpreadsheet = spreadsheetExtensions.includes(extension);
        const isPPT = pptExtensions.includes(extension);
        const isDOCX = extension === 'docx';
        const isDOC = extension === 'doc';
        const isHTML = extension === 'html' || extension === 'htm';
        const allowDownload = options.allowDownload !== false && extension !== 'link' && !isHTML;
        const previewUrl = options.previewUrl || '';
        const officeViewerUrl = options.officeViewerUrl || '';
        const useOfficeViewerIframe = !!officeViewerUrl && (isPPT || isSpreadsheet || isDOCX || isDOC);
        let viewerUrl = url;

        teacherResourceOriginalUrl = url;

        if (downloadButton) {
            if (allowDownload && url) {
                downloadButton.style.display = 'inline-flex';
                downloadButton.disabled = false;
            } else {
                downloadButton.style.display = 'none';
                downloadButton.disabled = true;
            }
        }

        if (fullscreenButton) {
            fullscreenButton.style.display = 'flex';
            fullscreenButton.disabled = false;
            fullscreenButton.classList.remove('fullscreen-active');
            fullscreenButton.setAttribute('aria-pressed', 'false');
        }

        if (useOfficeViewerIframe && iframe) {
            if (spreadsheetContainer) {
                spreadsheetContainer.hidden = true;
                spreadsheetContainer.innerHTML = '';
            }
            iframe.style.display = '';
            iframe.src = officeViewerUrl;
        } else if (isPPT) {
            if (iframe) {
                iframe.style.display = 'none';
                iframe.src = 'about:blank';
            }
            if (spreadsheetContainer) {
                spreadsheetContainer.hidden = false;
                if (previewUrl) {
                    spreadsheetContainer.innerHTML =
                        '<div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; min-height: 500px; display: flex; flex-direction: column; align-items: center; justify-content: center;">' +
                        '<img src="' + escapeHtml(previewUrl) + '" alt="' + escapeHtml(title) + ' - First Slide Preview" ' +
                        'style="max-width: 100%; max-height: 84vh; width: auto; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); background: white; padding: 8px; object-fit: contain;" ' +
                        'onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';" />' +
                        '<div style="display: none; color: #666; padding: 40px;"><p style="font-size: 16px; margin-bottom: 12px;">Preview image failed to load</p><p style="font-size: 14px;">Click Download to view the full presentation</p></div>' +
                        '</div>' +
                        '<p style="text-align: center; color: #666; margin-top: 16px; font-size: 14px; padding: 0 20px;">First Slide Preview - Click Download button above to view the full presentation</p>';
                } else {
                    spreadsheetContainer.innerHTML =
                        '<div style="text-align: center; padding: 60px 40px; color: #666; background: #f8f9fa; border-radius: 8px; min-height: 400px; display: flex; flex-direction: column; align-items: center; justify-content: center;">' +
                        '<i class="fa fa-file-powerpoint" style="font-size: 64px; color: #fd7e14; margin-bottom: 20px;"></i>' +
                        '<p style="font-size: 18px; margin-bottom: 12px; font-weight: 600;">Preview not available</p>' +
                        '<p style="font-size: 14px; color: #999;">Click Download button above to view the full presentation</p>' +
                        '</div>';
                }
            }
        } else if (isDOCX) {
            if (iframe) {
                iframe.style.display = 'none';
                iframe.src = 'about:blank';
            }
            if (spreadsheetContainer) {
                spreadsheetContainer.hidden = false;
                if (previewUrl) {
                    spreadsheetContainer.innerHTML =
                        '<div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; min-height: 500px; display: flex; flex-direction: column; align-items: center; justify-content: center;">' +
                        '<img src="' + escapeHtml(previewUrl) + '" alt="' + escapeHtml(title) + ' - First Page Preview" ' +
                        'style="max-width: 100%; max-height: 84vh; width: auto; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); background: white; padding: 8px; object-fit: contain;" ' +
                        'onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';" />' +
                        '<div style="display: none; color: #666; padding: 40px;"><p style="font-size: 16px; margin-bottom: 12px;">Preview image failed to load</p><p style="font-size: 14px;">Click Download to view the full document</p></div>' +
                        '</div>' +
                        '<p style="text-align: center; color: #666; margin-top: 16px; font-size: 14px; padding: 0 20px;">First Page Preview - Click Download button above to view the full document</p>';
                } else {
                    spreadsheetContainer.innerHTML =
                        '<div style="text-align: center; padding: 60px 40px; color: #666; background: #f8f9fa; border-radius: 8px; min-height: 400px; display: flex; flex-direction: column; align-items: center; justify-content: center;">' +
                        '<i class="fa fa-file-word" style="font-size: 64px; color: #007bff; margin-bottom: 20px;"></i>' +
                        '<p style="font-size: 18px; margin-bottom: 12px; font-weight: 600;">Preview not available</p>' +
                        '<p style="font-size: 14px; color: #999;">Click Download button above to view the full document</p>' +
                        '</div>';
                }
            }
        } else if (isSpreadsheet) {
            if (iframe) {
                iframe.style.display = 'none';
                iframe.src = 'about:blank';
            }
            if (spreadsheetContainer) {
                spreadsheetContainer.hidden = false;
                spreadsheetContainer.innerHTML = '<p class="spreadsheet-loading">Loading spreadsheet preview…</p>';
                loadSpreadsheetPreview(url, spreadsheetContainer);
            }
        } else {
            if (iframe) {
                iframe.style.display = '';
                iframe.src = url;
            }
            if (spreadsheetContainer) {
                spreadsheetContainer.hidden = true;
                spreadsheetContainer.innerHTML = '';
            }
        }

        titleElement.textContent = title;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        updatePPTFullscreenButton();
    }

    async function loadSpreadsheetPreview(url, container) {
        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const arrayBuffer = await response.arrayBuffer();
            const workbook = XLSX.read(arrayBuffer, { type: 'array' });

            let html = '';
            workbook.SheetNames.forEach((sheetName, index) => {
                const worksheet = workbook.Sheets[sheetName];
                const sheetHtml = XLSX.utils.sheet_to_html(worksheet, {
                    header: '<h3>' + escapeHtml(sheetName) + '</h3>',
                });
                html += '<div class="sheet-block">' + sheetHtml + '</div>';
            });

            container.innerHTML = html;
        } catch (error) {
            container.innerHTML = '<p class="spreadsheet-error">Unable to preview this spreadsheet. <a href="' +
                teacherResourceOriginalUrl + '" target="_blank" rel="noopener">Open the file in a new tab</a>.</p>';
            console.error('Spreadsheet preview failed:', error);
        }
    }

    function downloadCurrentResource() {
        if (teacherResourceOriginalUrl) {
            downloadResourceFile(teacherResourceOriginalUrl);
        }
    }

    function downloadResourceFile(url) {
        if (!url) return;
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.target = '_blank';
        anchor.rel = 'noopener';
        anchor.download = '';
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
    }

    function closePPTPlayer() {
        const modal = document.getElementById('pptPlayerModal');
        const iframe = document.getElementById('pptPlayerIframe');
        const spreadsheetContainer = document.getElementById('pptSpreadsheetContainer');
        const fullscreenElement = getCurrentFullscreenElement();
        if (fullscreenElement && fullscreenElement.id === PPT_FULLSCREEN_ELEMENT_ID) {
            exitFullscreen();
        }

        modal.classList.remove('active');
        if (iframe) {
            iframe.src = '';
            iframe.style.display = '';
        }
        if (spreadsheetContainer) {
            spreadsheetContainer.hidden = true;
            spreadsheetContainer.innerHTML = '';
        }
        document.body.style.overflow = '';
        updatePPTFullscreenButton();
    }

    function getCurrentFullscreenElement() {
        return document.fullscreenElement ||
            document.webkitFullscreenElement ||
            document.mozFullscreenElement ||
            document.msFullscreenElement ||
            null;
    }

    function requestFullscreen(element) {
        if (!element) return;
        if (element.requestFullscreen) {
            element.requestFullscreen();
        } else if (element.webkitRequestFullscreen) {
            element.webkitRequestFullscreen();
        } else if (element.mozRequestFullScreen) {
            element.mozRequestFullScreen();
        } else if (element.msRequestFullscreen) {
            element.msRequestFullscreen();
        }
    }

    // Exit fullscreen
    function exitFullscreen() {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }

    function togglePPTFullscreen() {
        const modalContent = document.getElementById(PPT_FULLSCREEN_ELEMENT_ID);
        if (!modalContent) return;
        const fullscreenElement = getCurrentFullscreenElement();
        if (!fullscreenElement) {
            requestFullscreen(modalContent);
        } else if (fullscreenElement.id === PPT_FULLSCREEN_ELEMENT_ID) {
            exitFullscreen();
        } else {
            requestFullscreen(modalContent);
        }
    }

    function updatePPTFullscreenButton() {
        const fullscreenButton = document.getElementById('pptFullscreenButton');
        if (!fullscreenButton || fullscreenButton.style.display === 'none') return;
        const fullscreenElement = getCurrentFullscreenElement();
        const isActive = fullscreenElement && fullscreenElement.id === PPT_FULLSCREEN_ELEMENT_ID;
        fullscreenButton.classList.toggle('fullscreen-active', !!isActive);
        fullscreenButton.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        const icon = fullscreenButton.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-expand', !isActive);
            icon.classList.toggle('fa-compress', !!isActive);
        }
    }

    function makeAbsoluteUrl(url) {
        try {
            return new URL(url, window.location.origin).toString();
        } catch (error) {
            return url;
        }
    }

    function escapeHtml(value) {
        return (value || '').replace(/[&<>"']/g, function (match) {
            switch (match) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case '\'': return '&#39;';
                default: return match;
            }
        });
    }

    // Attach Esc key and background click listener
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const fullscreenElement = getCurrentFullscreenElement();
            if (fullscreenElement && fullscreenElement.id === PPT_FULLSCREEN_ELEMENT_ID) {
                exitFullscreen();
                return;
            }
            const modal = document.getElementById('pptPlayerModal');
            if (modal && modal.classList.contains('active')) {
                closePPTPlayer();
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('pptPlayerModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closePPTPlayer();
                }
            });
        }
        
        ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'].forEach(evt => {
            document.addEventListener(evt, updatePPTFullscreenButton);
        });
    });
</script>

<?php
echo $OUTPUT->footer();
?>
