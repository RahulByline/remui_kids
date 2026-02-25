<?php
/**
 * AJAX endpoint to save school Social Science curriculum assignments (Social Science KSA / Social Science GCC).
 *
 * @package   theme_remui_kids
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

header('Content-Type: application/json');
ob_start();

require_once(__DIR__ . '/../../../config.php');

global $DB, $USER;

ob_clean();

try {
    require_login();
    require_sesskey();
    if (!is_siteadmin()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication error: ' . $e->getMessage()]);
    exit;
}

$assignments_json = optional_param('assignments', '', PARAM_RAW);
if (empty($assignments_json)) {
    echo json_encode(['success' => false, 'message' => 'No assignments provided']);
    exit;
}

$assignments = json_decode($assignments_json, true);
if (!is_array($assignments)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid assignments data']);
    exit;
}

$valid_categories = ['Social Science KSA', 'Social Science GCC'];

try {
    $dbman = $DB->get_manager();
    $table = new xmldb_table('theme_remui_kids_school_ebook_categories');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('school_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('category', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('school_category', XMLDB_INDEX_UNIQUE, ['school_id', 'category']);
        $dbman->create_table($table);
    } else {
        $field_cat = new xmldb_field('category');
        if (!$dbman->field_exists($table, $field_cat)) {
            $field_cat->set_attributes(XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $dbman->add_field($table, $field_cat);
        }
    }

    $transaction = $DB->start_delegated_transaction();

    $school_ids_to_update = [];
    foreach ($assignments as $assignment) {
        $school_id = (int)$assignment['school_id'];
        if ($school_id > 0 && !in_array($school_id, $school_ids_to_update)) {
            $school_ids_to_update[] = $school_id;
        }
    }

    if (!empty($school_ids_to_update)) {
        list($insql, $inparams) = $DB->get_in_or_equal($school_ids_to_update, SQL_PARAMS_NAMED, 'schoolid');
        $DB->delete_records_select('theme_remui_kids_school_ebook_categories', "school_id {$insql}", $inparams);
    }

    $time = time();
    $records_to_insert = [];
    $errors = [];

    foreach ($assignments as $idx => $assignment) {
        if (!isset($assignment['school_id']) || !isset($assignment['category'])) {
            $errors[] = "Assignment #" . ($idx + 1) . " missing required fields";
            continue;
        }
        $school_id = (int)$assignment['school_id'];
        $category = trim($assignment['category']);
        if (!in_array($category, $valid_categories)) {
            $errors[] = "Invalid category: " . $category;
            continue;
        }
        if ($school_id <= 0) {
            $errors[] = "Invalid school ID: " . $school_id;
            continue;
        }
        $record = new stdClass();
        $record->school_id = $school_id;
        $record->category = $category;
        $record->timecreated = $time;
        $record->timemodified = $time;
        $records_to_insert[] = $record;
    }

    $seen = [];
    $unique = [];
    foreach ($records_to_insert as $r) {
        $key = $r->school_id . '|' . $r->category;
        if (!isset($seen[$key])) {
            $unique[] = $r;
            $seen[$key] = true;
        }
    }
    $records_to_insert = $unique;

    $inserted_count = 0;
    foreach ($records_to_insert as $record) {
        try {
            $DB->insert_record('theme_remui_kids_school_ebook_categories', $record);
            $inserted_count++;
        } catch (dml_exception $e) {
            if (stripos($e->getMessage(), 'Duplicate') !== false || stripos($e->getMessage(), 'unique') !== false) {
                $inserted_count++;
            } else {
                $errors[] = "Insert failed: school_id=" . $record->school_id . ", category=" . $record->category;
            }
        }
    }

    if ($inserted_count !== count($records_to_insert)) {
        try {
            $transaction->rollback(new Exception("Insert count mismatch"));
        } catch (Exception $e) {}
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Only ' . $inserted_count . ' of ' . count($records_to_insert) . ' assignments were saved. Please try again.'
        ]);
        exit;
    }

    $transaction->allow_commit();
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Assignments saved successfully. ' . $inserted_count . ' assignment(s) saved.',
        'saved_count' => $inserted_count,
        'total_count' => count($assignments)
    ]);
    exit;

} catch (Exception $e) {
    if (isset($transaction)) {
        try { $transaction->rollback($e); } catch (Exception $e2) {}
    }
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
