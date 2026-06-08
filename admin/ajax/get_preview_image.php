<?php
/**
 * Serve preview image from moodledata securely
 *
 * @package   theme_remui_kids
 * @copyright 2026 Kodeit
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

// Simple validation that the user is logged in
if (!isloggedin()) {
    send_file_not_found();
}

$file = optional_param('file', '', PARAM_FILE);
if (empty($file)) {
    send_file_not_found();
}

// Ensure the file is inside the preview directory
$filename = basename($file);
$filepath = $CFG->dataroot . '/theme_remui_kids_previews/' . $filename;

if (!file_exists($filepath)) {
    send_file_not_found();
}

// Get the mime type of the file
$mime = mime_content_type($filepath);
if (!$mime) {
    $mime = 'image/png'; // Default fallback
}

// Send the headers and output the file
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: public, max-age=86400'); // Cache for 1 day
readfile($filepath);
exit;
