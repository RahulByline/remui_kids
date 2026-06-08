<?php
/**
 * Training Library — how-to videos for teachers.
 *
 * @package   theme_remui_kids
 * @copyright 2025 Kodeit
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/includes/teacher_access.php');

use theme_remui_kids\local\support_video_manager;

theme_remui_kids_require_teacher();

global $DB, $CFG, $PAGE, $OUTPUT, $USER;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/theme/remui_kids/teacher/training_library.php'));
$PAGE->set_title('Training Library');
$PAGE->set_heading('');
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('teacher-support-body');

$videosgrouped = [];
$totalvideos = 0;
$dbman = $DB->get_manager();

if ($dbman->table_exists(support_video_manager::TABLE)) {
    // All teacher-visible training videos (any category tagged for teachers / training / general how-to).
    $allteacher = support_video_manager::get_videos(null, 'teacher', true);
    $videos = [];
    foreach ($allteacher as $video) {
        $videos[$video->id] = $video;
    }

    // Also include videos stored under legacy "teachers" category slug.
    foreach (support_video_manager::get_videos('teachers', 'teacher', true) as $video) {
        $videos[$video->id] = $video;
    }
    foreach (support_video_manager::get_videos('training', 'teacher', true) as $video) {
        $videos[$video->id] = $video;
    }

    $categories = support_video_manager::get_categories();
    foreach ($videos as $video) {
        $key = $video->category ?: 'other';
        if (!isset($videosgrouped[$key])) {
            $videosgrouped[$key] = [
                'name' => $categories[$key] ?? ucfirst(str_replace(['_', '-'], ' ', $key)),
                'videos' => [],
            ];
        }
        $videosgrouped[$key]['videos'][] = $video;
    }
    $totalvideos = count($videos);
}

echo $OUTPUT->header();
require_once(__DIR__ . '/includes/teacher_layout_styles.php');
?>

<style>
    .main-content {
        margin-left: 0px !important;
        margin-top: 90px !important;
        width: 100% !important;
    }

    .teacher-page-header {
        padding: 15px 10px;
    }

    .teacher-page-header .page-subtitle {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">

            <div class="main-content">
                <div class="teacher-page-header">
                    <h1 class="page-title">
                        Training Library
                        <?php if ($totalvideos > 0): ?>
                            <span class="training-video-count-badge">(<?php echo (int) $totalvideos; ?> videos)</span>
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">How-to videos and training guides to help you use the Social Science LMS
                        effectively.</p>
                </div>

                <div class="teacher-page-panel training-library-panel">
                    <div class="teacher-page-panel-inner training-library-container">
                        <div class="training-library-search">
                            <div class="help-search-container">
                                <i class="fa fa-search"
                                    style="position:absolute;left:14px;color:#6b7280;z-index:1;"></i>
                                <input type="text" id="trainingSearchInput" class="help-search-input"
                                    placeholder="Search training videos by title, description, or category..." />
                                <button type="button" id="trainingSearchClear"
                                    style="display:none;position:absolute;right:12px;background:transparent;border:none;color:#999;cursor:pointer;z-index:1;">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                            <div id="trainingSearchCount"
                                style="display:none;margin-top:10px;color:#6b7280;font-size:14px;"></div>
                        </div>

                        <?php if (empty($videosgrouped)): ?>
                            <div class="category-section">
                                <div class="no-videos">
                                    <i class="fa fa-video-camera"></i>
                                    <h3>No training videos yet</h3>
                                    <p>Support videos will be visible here once they are added.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($videosgrouped as $categorykey => $data): ?>
                                <div class="category-section" data-category-name="<?php echo s(strtolower($data['name'])); ?>">
                                    <h2 class="category-title">
                                        <i class="fa fa-folder-open" style="color:#0ea5e9;"></i>
                                        <?php echo format_string($data['name']); ?>
                                        <span class="category-count"><?php echo count($data['videos']); ?></span>
                                    </h2>
                                    <div class="videos-grid">
                                        <?php foreach ($data['videos'] as $video): ?>
                                            <?php
                                            $videourl = support_video_manager::url_to_string($video->video_url);
                                            $embedurl = support_video_manager::url_to_string($video->embed_url);
                                            $captionurl = $video->caption_url ?? '';
                                            $searchtext = strtolower($data['name'] . ' ' . $video->title . ' ' . ($video->description ?? ''));
                                            ?>
                                            <div class="video-card" role="button" tabindex="0"
                                                data-video-id="<?php echo (int) $video->id; ?>"
                                                data-video-url="<?php echo s($videourl); ?>"
                                                data-embed-url="<?php echo s($embedurl); ?>"
                                                data-video-type="<?php echo s($video->videotype); ?>"
                                                data-has-captions="<?php echo !empty($video->has_captions) ? 'true' : 'false'; ?>"
                                                data-caption-url="<?php echo s($captionurl); ?>"
                                                data-video-title="<?php echo s($video->title); ?>"
                                                data-video-description="<?php echo s($video->description ?? ''); ?>"
                                                data-search-text="<?php echo s($searchtext); ?>">
                                                <div class="video-card-header">
                                                    <div class="video-icon"><i class="fa fa-play"></i></div>
                                                    <div>
                                                        <h3 class="video-title"><?php echo format_string($video->title); ?></h3>
                                                        <?php if (!empty($video->description)): ?>
                                                            <p class="video-description">
                                                                <?php
                                                                $short = $video->description;
                                                                echo format_string(core_text::substr($short, 0, 140) . (core_text::strlen($short) > 140 ? '...' : ''));
                                                                ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="video-meta">
                                                    <span><i class="fa fa-eye"></i> <?php echo (int) ($video->views ?? 0); ?>
                                                        views</span>
                                                    <?php if (!empty($video->durationformatted)): ?>
                                                        <span><i class="fa fa-clock-o"></i>
                                                            <?php echo s($video->durationformatted); ?></span>
                                                    <?php endif; ?>
                                                    <span><?php echo s($video->videotype); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="trainingVideoModal" class="video-modal" aria-hidden="true">
    <div class="video-modal-content">
        <div class="video-modal-header">
            <h2 class="video-modal-title" id="trainingModalTitle">Training Video</h2>
            <button type="button" class="video-modal-close" id="trainingModalClose" aria-label="Close">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <div style="padding:20px;">
            <div class="video-player-wrapper" id="trainingPlayerWrapper">
                <video id="trainingVideoPlayer" controls style="display:none;width:100%;"></video>
            </div>
            <div id="trainingModalDescription" style="color:#ccc;margin-top:16px;display:none;"></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('trainingVideoModal');
        const player = document.getElementById('trainingVideoPlayer');
        const wrapper = document.getElementById('trainingPlayerWrapper');
        const titleEl = document.getElementById('trainingModalTitle');
        const descEl = document.getElementById('trainingModalDescription');
        const searchInput = document.getElementById('trainingSearchInput');
        const searchClear = document.getElementById('trainingSearchClear');
        const searchCount = document.getElementById('trainingSearchCount');

        function closeModal() {
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (player) {
                player.pause();
                player.removeAttribute('src');
                player.style.display = 'none';
            }
            const iframe = wrapper.querySelector('iframe');
            if (iframe) {
                iframe.remove();
            }
        }

        function openVideo(card) {
            const type = card.getAttribute('data-video-type');
            const videoUrl = card.getAttribute('data-video-url');
            const embedUrl = card.getAttribute('data-embed-url');
            const title = card.getAttribute('data-video-title');
            const description = card.getAttribute('data-video-description');
            const hasCaptions = card.getAttribute('data-has-captions') === 'true';
            const captionUrl = card.getAttribute('data-caption-url');

            titleEl.textContent = title || 'Training Video';
            if (description) {
                descEl.textContent = description;
                descEl.style.display = 'block';
            } else {
                descEl.style.display = 'none';
            }

            const iframe = wrapper.querySelector('iframe');
            if (iframe) {
                iframe.remove();
            }

            if (type === 'youtube' || type === 'vimeo' || type === 'external') {
                const frame = document.createElement('iframe');
                frame.src = embedUrl || videoUrl;
                frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
                frame.allowFullscreen = true;
                wrapper.appendChild(frame);
                player.style.display = 'none';
            } else {
                player.style.display = 'block';
                player.src = videoUrl;
                if (hasCaptions && captionUrl) {
                    let track = player.querySelector('track');
                    if (!track) {
                        track = document.createElement('track');
                        track.kind = 'captions';
                        track.srclang = 'en';
                        track.label = 'English';
                        player.appendChild(track);
                    }
                    track.src = captionUrl;
                }
                player.load();
            }

            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            const videoid = card.getAttribute('data-video-id');
            if (videoid) {
                fetch(M.cfg.wwwroot + '/theme/remui_kids/ajax/support_videos.php?action=record_view&videoid=' + videoid + '&sesskey=' + M.cfg.sesskey).catch(function () { });
            }
        }

        document.querySelectorAll('.training-library-container .video-card').forEach(function (card) {
            card.addEventListener('click', function () { openVideo(card); });
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openVideo(card);
                }
            });
        });

        document.getElementById('trainingModalClose').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        function filterVideos() {
            const q = (searchInput.value || '').trim().toLowerCase();
            let visible = 0;
            let total = 0;

            document.querySelectorAll('.training-library-container .video-card').forEach(function (card) {
                total++;
                const match = !q || (card.getAttribute('data-search-text') || '').indexOf(q) !== -1;
                card.classList.toggle('hidden', !match);
                if (match) {
                    visible++;
                }
            });

            document.querySelectorAll('.training-library-container .category-section').forEach(function (section) {
                const cards = section.querySelectorAll('.video-card:not(.hidden)');
                section.classList.toggle('hidden', cards.length === 0);
            });

            if (q) {
                searchCount.style.display = 'block';
                searchCount.innerHTML = 'Showing <strong>' + visible + '</strong> of ' + total + ' videos';
                searchClear.style.display = 'block';
            } else {
                searchCount.style.display = 'none';
                searchClear.style.display = 'none';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterVideos);
        }
        if (searchClear) {
            searchClear.addEventListener('click', function () {
                searchInput.value = '';
                filterVideos();
            });
        }
    });
</script>

<?php
echo $OUTPUT->footer();
