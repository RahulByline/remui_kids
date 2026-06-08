<?php
/**
 * Toggle Bookmark AJAX Handler
 */

require_once(__DIR__ . '/../../../../config.php');

header('Content-Type: application/json');

try {
    require_sesskey();
    require_login();

    global $DB, $USER;

    $res_type = required_param('res_type', PARAM_ALPHA);
    $res_id = required_param('res_id', PARAM_INT);

    $conditions = [
        'userid' => $USER->id,
        'res_type' => $res_type,
        'res_id' => $res_id,
    ];

    $existing = $DB->get_record('theme_remui_kids_bookmarks', $conditions);

    if ($existing) {
        $DB->delete_records('theme_remui_kids_bookmarks', ['id' => $existing->id]);
        echo json_encode(['success' => true, 'bookmarked' => false]);
    } else {
        $record = new stdClass();
        $record->userid = $USER->id;
        $record->res_type = $res_type;
        $record->res_id = $res_id;
        $record->timecreated = time();
        $DB->insert_record('theme_remui_kids_bookmarks', $record);
        echo json_encode(['success' => true, 'bookmarked' => true]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
