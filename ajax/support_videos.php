<?php
/**
 * AJAX endpoint for training/support videos (theme table).
 *
 * @package    theme_remui_kids
 * @copyright  2025 Kodeit
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use theme_remui_kids\local\support_video_manager;

require_login();

global $DB;

$action = optional_param('action', '', PARAM_ALPHA);
$videoid = optional_param('videoid', 0, PARAM_INT);
$category = optional_param('category', '', PARAM_TEXT);
$targetrole = optional_param('targetrole', '', PARAM_TEXT);

if ($action === 'record_view' && $videoid > 0) {
    require_sesskey();
    if ($DB->get_manager()->table_exists(support_video_manager::TABLE)) {
        support_video_manager::increment_views($videoid);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

if ($targetrole === '') {
    if (is_siteadmin()) {
        $targetrole = 'admin';
    } else if (has_capability('moodle/course:update', context_system::instance())) {
        $targetrole = 'teacher';
    } else {
        $targetrole = 'student';
    }
}

$response = [
    'success' => false,
    'count' => 0,
    'category' => $category,
    'videos' => [],
];

if (!$DB->get_manager()->table_exists(support_video_manager::TABLE)) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$videos = [];
if ($category !== '') {
    $videos = support_video_manager::get_videos($category, $targetrole, true);
    if (empty($videos)) {
        foreach (support_video_manager::get_videos(null, $targetrole, true) as $video) {
            $videocat = strtolower((string) $video->category);
            $search = strtolower($category);
            if (strpos($videocat, $search) !== false || strpos($search, $videocat) !== false) {
                $videos[$video->id] = $video;
            }
        }
    }
} else {
    $videos = support_video_manager::get_videos(null, $targetrole, true);
}

$response['success'] = true;
$response['count'] = count($videos);

foreach ($videos as $video) {
    $response['videos'][] = [
        'id' => (int) $video->id,
        'title' => $video->title,
        'description' => $video->description ?? '',
        'video_url' => support_video_manager::url_to_string($video->video_url),
        'embed_url' => support_video_manager::url_to_string($video->embed_url),
        'videotype' => $video->videotype,
        'duration' => $video->durationformatted ?? '',
        'views' => (int) ($video->views ?? 0),
        'category' => $video->category,
        'has_captions' => !empty($video->has_captions),
        'caption_url' => $video->caption_url ?? '',
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
