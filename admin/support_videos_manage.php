<?php
/**
 * Add support videos (upload or external URL) — super admin.
 *
 * @package   theme_remui_kids
 */

require_once(__DIR__ . '/../../../config.php');

use theme_remui_kids\local\support_video_manager;

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/theme/remui_kids/admin/support_videos_manage.php'));
$PAGE->set_title('Add Support Video');
$PAGE->set_heading('Add Support Video');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);
$listurl = new moodle_url('/theme/remui_kids/admin/support_videos.php');

if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'upload':
            if (!empty($_FILES['videofile']) && $_FILES['videofile']['error'] === UPLOAD_ERR_OK) {
                $title = required_param('title', PARAM_TEXT);
                $description = optional_param('description', '', PARAM_TEXT);
                $category = required_param('category', PARAM_TEXT);
                $subcategory = optional_param('subcategory', '', PARAM_TEXT);
                $targetrole = required_param('targetrole', PARAM_TEXT);
                $captionfile = (!empty($_FILES['captionfile']) && $_FILES['captionfile']['error'] === UPLOAD_ERR_OK)
                    ? $_FILES['captionfile']
                    : null;
                try {
                    support_video_manager::upload_video(
                        $_FILES['videofile'],
                        $title,
                        $description,
                        $category,
                        $subcategory,
                        $targetrole,
                        $USER->id,
                        $captionfile
                    );
                    redirect($listurl, 'Video uploaded successfully.', null, \core\output\notification::NOTIFY_SUCCESS);
                } catch (Exception $e) {
                    redirect($PAGE->url, 'Upload failed: ' . $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
                }
            }
            redirect($PAGE->url, 'Please select a video file to upload.', null, \core\output\notification::NOTIFY_ERROR);

        case 'addexternal':
            $title = required_param('title', PARAM_TEXT);
            $description = optional_param('description', '', PARAM_TEXT);
            $videourl = required_param('videourl', PARAM_URL);
            $videotype = required_param('videotype', PARAM_TEXT);
            $category = required_param('category', PARAM_TEXT);
            $subcategory = optional_param('subcategory', '', PARAM_TEXT);
            $targetrole = required_param('targetrole', PARAM_TEXT);
            try {
                support_video_manager::add_external_video(
                    $title,
                    $description,
                    $videourl,
                    $videotype,
                    $category,
                    $subcategory,
                    $targetrole,
                    $USER->id
                );
                redirect($listurl, 'Video added successfully.', null, \core\output\notification::NOTIFY_SUCCESS);
            } catch (Exception $e) {
                redirect($PAGE->url, 'Could not add video: ' . $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
            }
    }
}

$categories = support_video_manager::get_categories();
$roles = support_video_manager::get_target_roles();

echo $OUTPUT->header();
require_once(__DIR__ . '/includes/admin_sidebar.php');
?>

<style>
.admin-main-content { padding: 30px; min-height: calc(100vh - 80px); background: #f5f7fa; }
.manage-panel { max-width: 900px; margin: 0 auto; }
.manage-card { background: #fff; border-radius: 12px; padding: 28px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.manage-card h2 { margin: 0 0 8px; font-size: 22px; color: #333; }
.manage-card p { color: #666; margin: 0 0 20px; }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; }
.form-group input[type="text"],
.form-group input[type="url"],
.form-group input[type="file"],
.form-group textarea,
.form-group select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; }
.btn-submit { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; }
.back-link { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; color: #667eea; text-decoration: none; }
</style>

<div class="admin-main-content">
<div class="manage-panel">
    <a href="<?php echo $listurl->out(false); ?>" class="back-link"><i class="fa fa-arrow-left"></i> Back to Support Videos</a>

    <div class="manage-card">
        <h2><i class="fa fa-upload"></i> Upload video file</h2>
        <p>MP4 or other video files are stored on the server and shown in the Training Library for teachers.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category" required>
                    <option value="">-- Select category --</option>
                    <?php foreach ($categories as $key => $name): ?>
                        <option value="<?php echo s($key); ?>"><?php echo format_string($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Subcategory</label>
                <input type="text" name="subcategory">
            </div>
            <div class="form-group">
                <label>Target audience *</label>
                <select name="targetrole" required>
                    <?php foreach ($roles as $key => $name): ?>
                        <option value="<?php echo s($key); ?>"<?php echo $key === 'teacher' ? ' selected' : ''; ?>>
                            <?php echo format_string($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Video file *</label>
                <input type="file" name="videofile" accept="video/*" required>
            </div>
            <div class="form-group">
                <label>Caption file (optional, .vtt / .srt)</label>
                <input type="file" name="captionfile" accept=".vtt,.srt,.sub">
            </div>
            <button type="submit" class="btn-submit"><i class="fa fa-upload"></i> Upload video</button>
        </form>
    </div>

    <div class="manage-card">
        <h2><i class="fa fa-youtube-play"></i> Add YouTube / Vimeo / external link</h2>
        <p>Paste a URL instead of uploading a file.</p>
        <form method="post">
            <input type="hidden" name="action" value="addexternal">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Platform *</label>
                <select name="videotype" required>
                    <option value="youtube">YouTube</option>
                    <option value="vimeo">Vimeo</option>
                    <option value="external">Other URL</option>
                </select>
            </div>
            <div class="form-group">
                <label>Video URL *</label>
                <input type="url" name="videourl" required placeholder="https://">
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category" required>
                    <option value="">-- Select category --</option>
                    <?php foreach ($categories as $key => $name): ?>
                        <option value="<?php echo s($key); ?>"><?php echo format_string($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Subcategory</label>
                <input type="text" name="subcategory">
            </div>
            <div class="form-group">
                <label>Target audience *</label>
                <select name="targetrole" required>
                    <?php foreach ($roles as $key => $name): ?>
                        <option value="<?php echo s($key); ?>"<?php echo $key === 'teacher' ? ' selected' : ''; ?>>
                            <?php echo format_string($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-submit"><i class="fa fa-plus"></i> Add video</button>
        </form>
    </div>
</div>
</div>

<?php
echo $OUTPUT->footer();
