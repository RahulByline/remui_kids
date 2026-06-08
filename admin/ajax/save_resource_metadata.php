<?php
/**
 * Save Resource Metadata AJAX Handler - Admin
 *
 * @package   theme_remui_kids
 * @copyright 2026 Kodeit
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_sesskey();

if (!isloggedin()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

global $DB, $USER, $CFG;

// Check if user is admin
if (!is_siteadmin()) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

// Get form data
$res_type = required_param('res_type', PARAM_ALPHA);
$res_id = required_param('res_id', PARAM_INT);
$unit = optional_param('unit', '', PARAM_TEXT);
$lesson = optional_param('lesson', '', PARAM_TEXT);

if ($res_id <= 0 || !in_array($res_type, ['file', 'cm'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Clean and trim inputs
$unit = trim($unit);
$lesson = trim($lesson);

// Handle preview image upload
$preview_image = '';
if (isset($_FILES['preview_image']) && $_FILES['preview_image']['error'] == 0) {
    $file = $_FILES['preview_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
    
    if (in_array($file['type'], $allowed_types)) {
        // Use directory inside moodledata to persist across theme code updates/deploys
        $upload_dir = $CFG->dataroot . '/theme_remui_kids_previews/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_name = 'res_' . $res_type . '_' . $res_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Store path served by secure file fetcher PHP script
            $preview_image = $CFG->wwwroot . '/theme/remui_kids/admin/ajax/get_preview_image.php?file=' . $file_name;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.']);
        exit;
    }
}

// Check database table and create dynamically if missing (fail-safe)
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

$time = time();
$record = $DB->get_record('theme_remui_kids_res_meta', ['res_type' => $res_type, 'res_id' => $res_id]);

if ($record) {
    // Update existing metadata
    $record->unit = $unit;
    $record->lesson = $lesson;
    if ($preview_image) {
        // Delete old preview file if exists
        if (!empty($record->preview_image)) {
            $url_parts = parse_url($record->preview_image);
            $old_filename = '';
            if (isset($url_parts['query'])) {
                parse_str($url_parts['query'], $query_params);
                if (isset($query_params['file'])) {
                    $old_filename = basename($query_params['file']);
                }
            }
            if (!$old_filename) {
                $old_filename = basename($record->preview_image);
            }
            
            if ($old_filename) {
                // Delete from new location inside moodledata
                $old_new_path = $CFG->dataroot . '/theme_remui_kids_previews/' . $old_filename;
                if (file_exists($old_new_path)) {
                    @unlink($old_new_path);
                }
                // Also delete from legacy location inside theme
                $old_legacy_path = __DIR__ . '/../../pix/resources/' . $old_filename;
                if (file_exists($old_legacy_path)) {
                    @unlink($old_legacy_path);
                }
            }
        }
        $record->preview_image = $preview_image;
    }
    $record->timemodified = $time;
    
    if ($DB->update_record('theme_remui_kids_res_meta', $record)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Resource updated successfully',
            'unit' => $unit,
            'lesson' => $lesson,
            'preview_image' => $preview_image ?: $record->preview_image
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update resource metadata']);
    }
} else {
    // Insert new metadata record
    $record = new stdClass();
    $record->res_type = $res_type;
    $record->res_id = $res_id;
    $record->unit = $unit;
    $record->lesson = $lesson;
    $record->preview_image = $preview_image;
    $record->timecreated = $time;
    $record->timemodified = $time;
    
    if ($DB->insert_record('theme_remui_kids_res_meta', $record)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Resource updated successfully',
            'unit' => $unit,
            'lesson' => $lesson,
            'preview_image' => $preview_image
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save resource metadata']);
    }
}
exit;
