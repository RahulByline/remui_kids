<?php
/**
 * Help & Support — full page for teachers (sidebar).
 *
 * @package   theme_remui_kids
 * @copyright 2025 Kodeit
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/includes/teacher_access.php');

theme_remui_kids_require_teacher();

$view = optional_param('view', 'new', PARAM_ALPHA);
$ticketid = optional_param('ticket', 0, PARAM_INT);
if ($ticketid > 0) {
    $view = 'detail';
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/theme/remui_kids/teacher/help_support.php', array_filter([
    'view' => $view !== 'new' ? $view : null,
    'ticket' => $ticketid ?: null,
])));
$PAGE->set_title('Help & Support');
$PAGE->set_heading('');
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('teacher-support-body');

$PAGE->requires->css('/theme/remui_kids/style/help_widget.css');
$PAGE->requires->js('/theme/remui_kids/javascript/help_support_page.js', true);

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
                    <h1 class="page-title">Help &amp; Support</h1>
                    <p class="page-subtitle">Create support tickets, track replies, and get help from the platform team.</p>
                </div>

                <div class="teacher-page-panel help-support-page">
                    <div
                        id="helpSupportPage"
                        data-initial-view="<?php echo s($view); ?>"
                        <?php if ($ticketid > 0): ?>data-ticket-id="<?php echo (int) $ticketid; ?>"<?php endif; ?>
                    >
                        <div class="help-sidebar" id="helpPageSidebar">
                            <button type="button" class="help-nav-btn <?php echo $view === 'new' ? 'active' : ''; ?>" data-view="new">
                                <i class="fa fa-plus-circle"></i>
                                <span>New Ticket</span>
                            </button>
                            <button type="button" class="help-nav-btn <?php echo $view === 'list' || $view === 'detail' ? 'active' : ''; ?>" data-view="list">
                                <i class="fa fa-list"></i>
                                <span>My Tickets</span>
                            </button>
                        </div>
                        <div id="helpPageMainContent" class="help-main-content">
                            <?php if ($view === 'new'): ?>
                                <?php require(__DIR__ . '/includes/help_ticket_form.php'); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
