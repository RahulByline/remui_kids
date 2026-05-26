<?php
/**
 * Serve caption files for uploaded videos.
 *
 * @package    theme_remui_kids
 * @copyright  2025 Kodeit
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use theme_remui_kids\local\support_video_manager;

require_login();

$videoid = required_param('id', PARAM_INT);
$video = support_video_manager::get_video($videoid);

if (!$video || empty($video->captionfile)) {
    throw new moodle_exception('filenotfound', 'error');
}

$filepath = $CFG->dataroot . '/support_videos/' . $video->captionfile;
if (!is_readable($filepath)) {
    throw new moodle_exception('filenotfound', 'error');
}

$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
$mimetype = $ext === 'vtt' ? 'text/vtt' : 'application/octet-stream';

header('Content-Type: ' . $mimetype);
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
