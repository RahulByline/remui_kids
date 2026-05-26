<?php
/**
 * Stream uploaded training/support videos.
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

if (!$video || $video->videotype !== 'uploaded' || empty($video->filename)) {
    throw new moodle_exception('invalidrecord', 'error');
}

$filepath = $CFG->dataroot . '/support_videos/' . $video->filename;
if (!is_readable($filepath)) {
    throw new moodle_exception('filenotfound', 'error');
}

$filesize = filesize($filepath);
$filename = basename($filepath);

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimetype = finfo_file($finfo, $filepath) ?: 'video/mp4';
finfo_close($finfo);

header('Content-Type: ' . $mimetype);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE'])) {
    $range = str_replace('bytes=', '', $_SERVER['HTTP_RANGE']);
    [$start, $end] = array_pad(explode('-', $range), 2, '');
    $start = (int) $start;
    $end = ($end !== '' && is_numeric($end)) ? (int) $end : $filesize - 1;

    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $filesize);
    header('Content-Length: ' . ($end - $start + 1));

    $fp = fopen($filepath, 'rb');
    fseek($fp, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($fp)) {
        $chunk = fread($fp, min(8192, $remaining));
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($fp);
    exit;
}

header('Content-Length: ' . $filesize);
readfile($filepath);
exit;
