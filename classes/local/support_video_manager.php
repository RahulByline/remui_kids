<?php
/**
 * Support Video Manager Class
 *
 * Handles all operations related to support videos including upload, storage, and retrieval.
 *
 * @package    theme_remui_kids
 * @copyright  2025 Kodeit
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_remui_kids\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Support Video Manager class
 */
class support_video_manager {

    /** @var string Database table name */
    const TABLE = 'theme_remui_kids_support_videos';

    /**
     * Get all videos or filter by category/role
     *
     * @param string|null $category Filter by category
     * @param string|null $targetrole Filter by target role
     * @param bool $visible_only Only return visible videos
     * @return array Array of video records
     */
    public static function get_videos($category = null, $targetrole = null, $visible_only = true) {
        global $DB, $CFG;

        $conditions = [];
        $params = [];

        if ($visible_only) {
            $conditions[] = 'visible = :visible';
            $params['visible'] = 1;
        }

        if ($category !== null) {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }

        if ($targetrole !== null) {
            $conditions[] = "(targetrole = :targetrole OR targetrole = 'all')";
            $params['targetrole'] = $targetrole;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT * FROM {" . self::TABLE . "} {$where} ORDER BY sortorder ASC, timecreated DESC";

        $videos = $DB->get_records_sql($sql, $params);

        foreach ($videos as $video) {
            self::enrich_video($video);
        }

        return $videos;
    }

    /**
     * Get videos grouped by category for display pages.
     *
     * @param string|null $targetrole Filter by target role
     * @param bool $visibleonly Only visible videos
     * @param string|null $category Optional category filter
     * @return array
     */
    public static function get_videos_by_category($targetrole = null, $visibleonly = true, $category = null) {
        $videos = self::get_videos($category, $targetrole, $visibleonly);
        $categories = self::get_categories();

        $grouped = [];
        foreach ($videos as $video) {
            $key = $video->category ?: 'other';
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $categories[$key] ?? ucfirst(str_replace('_', ' ', $key)),
                    'videos' => [],
                ];
            }
            $grouped[$key]['videos'][] = $video;
        }

        return $grouped;
    }

    /**
     * Add playback URLs and display metadata to a video record.
     *
     * @param \stdClass $video
     * @return void
     */
    public static function enrich_video(\stdClass $video): void {
        $video->video_url = self::get_video_url($video);
        $video->embed_url = self::get_embed_url($video);
        $video->has_captions = !empty($video->captionfile);
        $caption = self::get_caption_url($video);
        $video->caption_url = $caption instanceof \moodle_url ? $caption->out(false) : (string) $caption;
        $video->durationformatted = self::format_duration($video->duration ?? null);
    }

    /**
     * Get a single video by ID
     *
     * @param int $videoid Video ID
     * @return object|false Video record or false if not found
     */
    public static function get_video($videoid) {
        global $DB;

        $video = $DB->get_record(self::TABLE, ['id' => $videoid]);
        if ($video) {
            self::enrich_video($video);
        }

        return $video;
    }

    /**
     * Upload a video file
     *
     * @param array $file $_FILES array entry
     * @param string $title Video title
     * @param string $description Video description
     * @param string $category Category
     * @param string $subcategory Subcategory
     * @param string $targetrole Target role
     * @param int $userid User ID uploading the video
     * @param array|null $captionfile Optional caption file
     * @return int|false Video ID or false on failure
     */
    public static function upload_video($file, $title, $description, $category, $subcategory, $targetrole, $userid, $captionfile = null) {
        global $DB, $CFG;

        // Validate file
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \Exception('Invalid file upload');
        }

        // Create upload directory if it doesn't exist
        $upload_dir = $CFG->dataroot . '/support_videos';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('video_') . '.' . $extension;
        $filepath = $upload_dir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \Exception('Failed to move uploaded file');
        }

        // Handle caption file if provided
        $captionfile_path = null;
        if ($captionfile && isset($captionfile['tmp_name']) && is_uploaded_file($captionfile['tmp_name'])) {
            $caption_ext = pathinfo($captionfile['name'], PATHINFO_EXTENSION);
            $captionfile_path = uniqid('caption_') . '.' . $caption_ext;
            $caption_fullpath = $upload_dir . '/' . $captionfile_path;
            
            if (!move_uploaded_file($captionfile['tmp_name'], $caption_fullpath)) {
                $captionfile_path = null;
            }
        }

        // Insert into database
        $record = new \stdClass();
        $record->title = $title;
        $record->description = $description;
        $record->category = $category;
        $record->subcategory = $subcategory;
        $record->targetrole = $targetrole;
        $record->videotype = 'uploaded';
        $record->filename = $filename;
        $record->filepath = '/support_videos/' . $filename;
        $record->captionfile = $captionfile_path;
        $record->filesize = filesize($filepath);
        $record->duration = null;
        $record->thumbnail = null;
        $record->sortorder = 0;
        $record->uploadedby = $userid;
        $record->timecreated = time();
        $record->timemodified = time();
        $record->visible = 1;
        $record->views = 0;

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Add an external video (YouTube, Vimeo, etc.)
     *
     * @param string $title Video title
     * @param string $description Video description
     * @param string $videourl Video URL
     * @param string $videotype Video type (youtube, vimeo, external)
     * @param string $category Category
     * @param string $subcategory Subcategory
     * @param string $targetrole Target role
     * @param int $userid User ID adding the video
     * @return int|false Video ID or false on failure
     */
    public static function add_external_video($title, $description, $videourl, $videotype, $category, $subcategory, $targetrole, $userid) {
        global $DB;

        // Validate video type
        if (!in_array($videotype, ['youtube', 'vimeo', 'external'])) {
            throw new \Exception('Invalid video type');
        }

        // Insert into database
        $record = new \stdClass();
        $record->title = $title;
        $record->description = $description;
        $record->category = $category;
        $record->subcategory = $subcategory;
        $record->targetrole = $targetrole;
        $record->videotype = $videotype;
        $record->videourl = $videourl;
        $record->uploadedby = $userid;
        $record->timecreated = time();
        $record->timemodified = time();
        $record->visible = 1;
        $record->views = 0;

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete a video
     *
     * @param int $videoid Video ID
     * @return bool True on success
     */
    public static function delete_video($videoid) {
        global $DB, $CFG;

        $video = $DB->get_record(self::TABLE, ['id' => $videoid]);
        if (!$video) {
            return false;
        }

        // Delete physical file if it's an uploaded video
        if ($video->videotype === 'uploaded' && !empty($video->filename)) {
            $filepath = $CFG->dataroot . '/support_videos/' . $video->filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // Delete caption file if exists
            if (!empty($video->captionfile)) {
                $caption_filepath = $CFG->dataroot . '/support_videos/' . $video->captionfile;
                if (file_exists($caption_filepath)) {
                    unlink($caption_filepath);
                }
            }
        }

        return $DB->delete_records(self::TABLE, ['id' => $videoid]);
    }

    /**
     * Update video visibility
     *
     * @param int $videoid Video ID
     * @param int $visible Visibility (0 or 1)
     * @return bool True on success
     */
    public static function update_visibility($videoid, $visible) {
        global $DB;

        $record = new \stdClass();
        $record->id = $videoid;
        $record->visible = $visible;
        $record->timemodified = time();

        return $DB->update_record(self::TABLE, $record);
    }

    /**
     * Increment video views
     *
     * @param int $videoid Video ID
     * @return bool True on success
     */
    public static function increment_views($videoid) {
        global $DB;

        $video = $DB->get_record(self::TABLE, ['id' => $videoid]);
        if (!$video) {
            return false;
        }

        $record = new \stdClass();
        $record->id = $videoid;
        $record->views = $video->views + 1;

        return $DB->update_record(self::TABLE, $record);
    }

    /**
     * Get video URL
     *
     * @param object $video Video record
     * @return string Video URL
     */
    public static function get_video_url($video) {
        if ($video->videotype === 'uploaded') {
            return new \moodle_url('/theme/remui_kids/support/stream.php', ['id' => $video->id]);
        }

        return $video->videourl ?? '';
    }

    /**
     * Get embed URL for external videos.
     *
     * @param object $video
     * @return string
     */
    public static function get_embed_url($video) {
        if ($video->videotype === 'youtube' && !empty($video->videourl)) {
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video->videourl, $matches)) {
                return 'https://www.youtube.com/embed/' . $matches[1];
            }
        } else if ($video->videotype === 'vimeo' && !empty($video->videourl)) {
            if (preg_match('/vimeo\.com\/(\d+)/', $video->videourl, $matches)) {
                return 'https://player.vimeo.com/video/' . $matches[1];
            }
        } else if ($video->videotype === 'uploaded') {
            $url = self::get_video_url($video);
            return $url instanceof \moodle_url ? $url->out(false) : (string) $url;
        }

        return $video->videourl ?? '';
    }

    /**
     * Format duration in seconds for display.
     *
     * @param int|null $seconds
     * @return string
     */
    public static function format_duration($seconds) {
        if ($seconds === null || $seconds <= 0) {
            return '';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Get all available categories
     *
     * @return array Category key => name pairs
     */
    public static function get_categories() {
        return [
            'teachers' => 'Teacher Training',
            'training' => 'Training Library',
            'courses' => 'Courses Management',
            'students' => 'Students Management',
            'gradebook' => 'Gradebook & Grading',
            'attendance' => 'Attendance Tracking',
            'quizzes' => 'Quizzes & Assessments',
            'assignments' => 'Assignments',
            'communication' => 'Communication',
            'reports' => 'Reports & Analytics',
            'settings' => 'System Settings',
            'profile' => 'Profile Management',
            'navigation' => 'Navigation & Interface',
            'support' => 'Support & Help',
            'other' => 'Other'
        ];
    }

    /**
     * Aggregate video statistics for admin dashboards.
     *
     * @return \stdClass
     */
    public static function get_statistics() {
        global $DB;

        $stats = new \stdClass();
        $stats->total_videos = $DB->count_records(self::TABLE, ['visible' => 1]);
        $stats->total_views = (int) $DB->get_field_sql(
            'SELECT COALESCE(SUM(views), 0) FROM {' . self::TABLE . '} WHERE visible = 1'
        );
        $stats->unique_viewers = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT userid) FROM {theme_remui_kids_video_views}'
        );
        $stats->total_uploads = $DB->count_records(self::TABLE, ['videotype' => 'uploaded', 'visible' => 1]);
        $stats->total_external = (int) $DB->count_records_select(
            self::TABLE,
            "videotype IN ('youtube', 'vimeo', 'external') AND visible = 1"
        );

        return $stats;
    }

    /**
     * Get all target roles
     *
     * @return array Role key => name pairs
     */
    public static function get_target_roles() {
        return [
            'admin' => 'Administrators',
            'teacher' => 'Teachers',
            'student' => 'Students',
            'all' => 'All Users'
        ];
    }

    /**
     * Get caption file URL
     *
     * @param object $video Video record
     * @return string|null Caption file URL or null if not available
     */
    public static function get_caption_url($video) {
        global $CFG;

        if ($video->videotype === 'uploaded' && !empty($video->captionfile)) {
            return new \moodle_url('/theme/remui_kids/support/caption.php', ['id' => $video->id]);
        }

        return '';
    }

    /**
     * Search videos by keyword
     *
     * @param string $keyword Search keyword
     * @param string|null $targetrole Filter by role
     * @return array Array of matching videos
     */
    public static function search_videos($keyword, $targetrole = null) {
        global $DB;

        $sql = "SELECT * FROM {" . self::TABLE . "} 
                WHERE visible = 1 
                AND (title LIKE :keyword1 OR description LIKE :keyword2 OR category LIKE :keyword3)";
        
        $params = [
            'keyword1' => '%' . $keyword . '%',
            'keyword2' => '%' . $keyword . '%',
            'keyword3' => '%' . $keyword . '%'
        ];

        if ($targetrole !== null) {
            $sql .= " AND (targetrole = :targetrole OR targetrole = 'all')";
            $params['targetrole'] = $targetrole;
        }

        $sql .= " ORDER BY timecreated DESC";

        $videos = $DB->get_records_sql($sql, $params);

        foreach ($videos as $video) {
            self::enrich_video($video);
        }

        return $videos;
    }

    /**
     * Resolve URL helper for templates (string or moodle_url).
     *
     * @param mixed $url
     * @return string
     */
    public static function url_to_string($url): string {
        if ($url instanceof \moodle_url) {
            return $url->out(false);
        }
        return (string) $url;
    }
}

