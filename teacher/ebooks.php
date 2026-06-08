<?php
/**
 * Hierarchical E-Books Page for Teachers
 *
 * @package   theme_remui_kids
 * @copyright 2025 Kodeit
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lang_init.php');
require_once($CFG->dirroot . '/theme/remui_kids/lib/teacher_school_helper.php');

global $CURRENT_LANG, $DB, $USER, $CFG;

if (!isloggedin()) {
    redirect(get_login_url());
}

// Check if user is teacher
$isteacher = false;
$teacherroles = $DB->get_records_select('role', "shortname IN ('editingteacher','teacher','manager')");
$roleids = array_keys($teacherroles);

if (!empty($roleids)) {
    list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'r');
    $params['userid'] = $USER->id;
    $params['ctxlevel'] = CONTEXT_COURSE;

    $teacher_courses = $DB->get_records_sql(
        "SELECT DISTINCT ctx.instanceid as courseid
         FROM {role_assignments} ra
         JOIN {context} ctx ON ra.contextid = ctx.id
         WHERE ra.userid = :userid AND ctx.contextlevel = :ctxlevel AND ra.roleid {$insql}
         LIMIT 1",
        $params
    );

    if (!empty($teacher_courses)) {
        $isteacher = true;
    }
}

if (is_siteadmin()) {
    $isteacher = true;
}

if (!$isteacher) {
    echo "<h1>Access Denied</h1>";
    echo "<p>You must be a teacher to access this page.</p>";
    echo "<p><a href='" . $CFG->wwwroot . "'>Go Back</a></p>";
    exit;
}

// Ensure books table exists
$dbman = $DB->get_manager();
$table = new xmldb_table('theme_remui_kids_books');

if (!$dbman->table_exists($table)) {
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('region', XMLDB_TYPE_CHAR, '20', null, null, null, 'GCC');
    $table->add_field('level', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
    $table->add_field('subject', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
    $table->add_field('book_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
    $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('book_link', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('cover_image', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $dbman->create_table($table);
} else {
    // Upgrade: add region column if missing (existing books default to GCC)
    $field_region = new xmldb_field('region', XMLDB_TYPE_CHAR, '20', null, null, null, 'GCC');
    if (!$dbman->field_exists($table, $field_region)) {
        $dbman->add_field($table, $field_region);
        $DB->execute("UPDATE {theme_remui_kids_books} SET region = 'GCC' WHERE (region IS NULL OR region = '')");
    }
}
$region_column_exists = $dbman->field_exists($table, new xmldb_field('region'));

// Allowed curriculum (region) codes for this teacher's school: only show GCC/KSA if assigned in admin.
$allowed_ebook_regions = ['GCC', 'KSA']; // default: show both if no assignment table or no school
if ($DB->get_manager()->table_exists('theme_remui_kids_school_ebook_categories')) {
    $teacher_company_id = theme_remui_kids_get_teacher_company_id();
    if ($teacher_company_id > 0) {
        $assigned = $DB->get_fieldset_sql(
            "SELECT category FROM {theme_remui_kids_school_ebook_categories} WHERE school_id = :sid",
            ['sid' => $teacher_company_id]
        );
        if (!empty($assigned)) {
            $allowed_ebook_regions = [];
            if (in_array('Social Science GCC', $assigned)) {
                $allowed_ebook_regions[] = 'GCC';
            }
            if (in_array('Social Science KSA', $assigned)) {
                $allowed_ebook_regions[] = 'KSA';
            }
            if (empty($allowed_ebook_regions)) {
                $allowed_ebook_regions = ['GCC', 'KSA'];
            }
        }
    }
}

$PAGE->set_url('/theme/remui_kids/teacher/ebooks.php');
$PAGE->set_title('E-Books');
$PAGE->set_heading('');
$PAGE->set_pagelayout('standard');

// ------------------------------------------------------------------
// Dynamic sidebar filter data: query what actually exists in the DB
// scoped to the teacher's school allowed regions
// ------------------------------------------------------------------

// Build region WHERE clause for scoping
$region_scope_conditions = [];
$region_scope_params = [];
if ($region_column_exists && !empty($allowed_ebook_regions)) {
    $rparts = [];
    foreach ($allowed_ebook_regions as $ridx => $rreg) {
        $rreg = trim($rreg);
        if ($rreg === 'GCC') {
            $rparts[] = "(region = 'GCC' OR region IS NULL OR region = '')";
        } elseif ($rreg === 'KSA') {
            $rparts[] = "region = 'KSA'";
        }
    }
    if (!empty($rparts)) {
        $region_scope_conditions[] = '(' . implode(' OR ', $rparts) . ')';
    }
} elseif (!$region_column_exists) {
    // Legacy: no region column, all books are GCC
    // no condition needed
}

$region_scope_where = !empty($region_scope_conditions)
    ? 'WHERE ' . implode(' AND ', $region_scope_conditions)
    : '';

// Distinct regions that actually have books for this school
$available_regions_raw = $DB->get_fieldset_sql(
    "SELECT DISTINCT COALESCE(NULLIF(TRIM(region),''), 'GCC') as r
     FROM {theme_remui_kids_books}
     $region_scope_where",
    $region_scope_params
);
$available_regions = array_values(array_unique(array_filter($available_regions_raw)));
// Only show multi-curriculum selector if BOTH GCC and KSA exist
$show_curriculum_filter = in_array('GCC', $available_regions) && in_array('KSA', $available_regions);

// Distinct grades that exist for this school - actually stored in the 'subject' column
$available_grades_raw = $DB->get_fieldset_sql(
    "SELECT DISTINCT subject FROM {theme_remui_kids_books} $region_scope_where AND subject LIKE 'Grade%' ORDER BY subject ASC",
    $region_scope_params
);
$available_grades = array_values(array_filter($available_grades_raw));

// Sort grades numerically (Grade 1, Grade 2 ... Grade 12) then any others alphabetically
usort($available_grades, function ($a, $b) {
    $na = (int) preg_replace('/\D/', '', $a);
    $nb = (int) preg_replace('/\D/', '', $b);
    if ($na && $nb)
        return $na - $nb;
    return strcmp($a, $b);
});

// Distinct book types that exist for this school
$available_book_types_raw = $DB->get_fieldset_sql(
    "SELECT DISTINCT book_type FROM {theme_remui_kids_books} $region_scope_where ORDER BY book_type ASC",
    $region_scope_params
);
$available_book_types = array_values(array_filter($available_book_types_raw));

// ------------------------------------------------------------------
// Fetch ALL books for this school (client-side filtering)
// ------------------------------------------------------------------
// Base SQL scoped to allowed regions
$base_where = $region_scope_where ?: '';
$all_school_books = $DB->get_records_sql(
    "SELECT * FROM {theme_remui_kids_books} $base_where ORDER BY timecreated DESC",
    $region_scope_params
);
if (!$all_school_books)
    $all_school_books = [];

// Fix cover image paths
foreach ($all_school_books as $book) {
    if (!empty($book->cover_image)) {
        $cover_image = trim($book->cover_image);
        if (strpos($cover_image, 'dataroot') !== false || strpos($cover_image, 'theme_remui_kids_books') !== false) {
            $filename = basename($cover_image);
            $corrected_path = $CFG->wwwroot . '/theme/remui_kids/pix/ebooks/' . $filename;
            $file_path = __DIR__ . '/../pix/ebooks/' . $filename;
            $book->cover_image = file_exists($file_path) ? $corrected_path : '';
        } elseif (!preg_match('/^https?:\/\//', $cover_image)) {
            if (strpos($cover_image, '/') === false) {
                $file_path = __DIR__ . '/../pix/ebooks/' . $cover_image;
                $book->cover_image = file_exists($file_path)
                    ? $CFG->wwwroot . '/theme/remui_kids/pix/ebooks/' . $cover_image
                    : '';
            } else {
                $book->cover_image = $CFG->wwwroot . $cover_image;
            }
        }
    }
}

echo $OUTPUT->header();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Include teacher sidebar -->
            <?php 
                // Initialize variables needed by sidebar.php for ebooks
                $selected_regions = []; 
                include(__DIR__ . '/includes/sidebar.php'); 
            ?>

            <div class="main-content">
                <!-- Page Header -->
                <div class="ebooks-page-header">
                    <div class="ebooks-header-inner">
                        <div>
                            <h1 class="ebooks-page-title">E-Books</h1>
                            <p class="ebooks-page-subtitle">Browse and filter books available to your school</p>
                        </div>
                        <div class="ebooks-total-count">
                            <span id="ebooksTotalCount"><?php echo count($all_school_books); ?></span>
                            book<?php echo count($all_school_books) != 1 ? 's' : ''; ?>
                        </div>
                    </div>
                </div>

                <!-- Books Grid - always visible, filtered client-side -->
                <div class="books-grid-section" id="ebooksBooksSection">
                    <?php if (empty($all_school_books)): ?>
                        <div class="no-books">
                            <i class="fa fa-book fa-3x"></i>
                            <p>No books available for your school yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="books-grid" id="booksGrid">
                            <?php foreach ($all_school_books as $book): ?>
                                <div class="book-card-new" data-book-id="<?php echo $book->id; ?>"
                                    data-region="<?php echo htmlspecialchars(strtoupper(trim($book->region ?? 'GCC'))); ?>"
                                    data-grade="<?php echo htmlspecialchars($book->subject); ?>"
                                    data-book-type="<?php echo htmlspecialchars($book->book_type); ?>">

                                    <!-- Preview Area -->
                                    <div class="book-preview-area">
                                        <?php if (!empty($book->cover_image) && !empty(trim($book->cover_image))): ?>
                                            <div class="book-preview-cover-container">
                                                <img src="<?php echo htmlspecialchars($book->cover_image); ?>"
                                                    alt="<?php echo htmlspecialchars($book->title); ?>" class="book-preview-image">
                                            </div>
                                            <div class="book-preview-placeholder" style="display: none;">
                                            <?php elseif ($book->book_link && !empty(trim($book->book_link))): ?>
                                                <div class="book-preview-cover-container">
                                                    <iframe
                                                        src="<?php echo htmlspecialchars($book->book_link); ?>#page=1&zoom=page-fit"
                                                        class="book-preview-cover-iframe" frameborder="0" allowfullscreen
                                                        loading="eager"></iframe>
                                                </div>
                                                <div class="book-preview-placeholder" style="display: none;">
                                                <?php else: ?>
                                                    <div class="book-preview-placeholder">
                                                    <?php endif; ?>
                                                    <div class="book-preview-graphics">
                                                        <svg viewBox="0 0 120 100" xmlns="http://www.w3.org/2000/svg">
                                                            <defs>
                                                                <linearGradient id="bg<?php echo $book->id; ?>" x1="0%" y1="0%"
                                                                    x2="100%" y2="0%">
                                                                    <stop offset="0%"
                                                                        style="stop-color:#60d4ff;stop-opacity:1" />
                                                                    <stop offset="100%"
                                                                        style="stop-color:#4361ee;stop-opacity:1" />
                                                                </linearGradient>
                                                            </defs>
                                                            <path
                                                                d="M20 15 L20 75 Q20 85 30 85 L90 85 Q100 85 100 75 L100 15 Q100 5 90 5 L50 5 Q40 5 40 10 L35 10 Q30 10 30 15 L25 15 Q20 15 20 25 Z"
                                                                stroke="url(#bg<?php echo $book->id; ?>)" stroke-width="4"
                                                                fill="white" stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Book info -->
                                            <div class="book-info-section">
                                                <h4 class="book-title-new"><?php echo htmlspecialchars($book->title); ?></h4>
                                                <div class="book-tags">
                                                    <span
                                                        class="book-tag book-tag-purple"><?php echo htmlspecialchars($book->subject); ?></span>
                                                    <span
                                                        class="book-tag book-tag-pink"><?php echo htmlspecialchars($book->book_type); ?></span>
                                                    <?php if ($show_curriculum_filter): ?>
                                                        <span class="book-tag"
                                                            style="background:#e0f2fe;color:#0369a1;"><?php echo htmlspecialchars(strtoupper(trim($book->region ?? 'GCC'))); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Actions -->
                                            <div class="book-actions-section">
                                                <button type="button" class="btn-view-new"
                                                    data-book-url="<?php echo htmlspecialchars($book->book_link); ?>"
                                                    data-book-title="<?php echo htmlspecialchars($book->title); ?>">
                                                    <i class="fa fa-eye"></i> View
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <!-- No results message (hidden by default, shown by JS) -->
                                <div class="no-books" id="ebooksNoResults" style="display:none;">
                                    <i class="fa fa-search fa-3x"></i>
                                    <p>No books match the selected filters. Try adjusting your selection.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Book Viewer Iframe Overlay -->
        <div id="bookViewerOverlay" class="book-viewer-overlay" style="display: none;">
            <div class="book-viewer-container">
                <div class="book-viewer-header">
                    <div class="book-viewer-header-left">
                        <span id="bookViewerTitle" class="book-viewer-filename"></span>
                    </div>
                    <div class="book-viewer-header-right">
                        <button type="button" class="book-viewer-close" id="closeBookViewer">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="book-viewer-content">
                    <!-- Preview Area -->
                    <div id="bookViewerPreview" class="book-viewer-preview-area" style="display: none;">
                        <iframe id="bookViewerIframe" src="" frameborder="0" allowfullscreen></iframe>
                    </div>
                    <!-- No Preview Message -->
                    <div id="bookViewerNoPreview" class="book-viewer-no-preview">
                        <div class="book-viewer-document-icon">
                            <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                                <rect width="36" height="46" x="6" y="1" fill="#2B579A" rx="2" />
                                <path d="M12 14h24M12 22h24M12 30h18M12 38h24" stroke="white" stroke-width="2"
                                    stroke-linecap="round" />
                                <path d="M38 6L42 2L42 6L38 6Z" fill="#185ABD" />
                            </svg>
                        </div>
                        <p class="book-viewer-no-preview-text">Preview not available</p>
                        <p class="book-viewer-no-preview-hint">Preview not available for this document</p>
                    </div>
                </div>
            </div>
        </div>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            /* Hide Moodle page header */
            #page-header,
            #page-header-content,
            .region-main-settings-menu,
            .page-header-headings {
                display: none !important;
            }

            body {
                overflow-x: hidden;
                width: 100% !important;
                max-width: 100% !important;
            }

            /* Full width page container */
            #page-wrapper,
            #page,
            .page,
            .container-fluid,
            .container {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .row {
                margin-left: 0 !important;
                margin-right: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
            }

            .col-12 {
                padding-left: 0 !important;
                padding-right: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            /* Teacher Sidebar - Same styling as sidebar.php */
            .teacher-sidebar {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 260px !important;
                height: 100vh !important;
                background: white !important;
                border-right: 1px solid #e9ecef !important;
                z-index: 1000 !important;
                overflow-y: auto !important;
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05) !important;
                transform: translateX(0) !important;
                transition: transform 0.3s ease !important;
            }

            .teacher-sidebar.sidebar-open {
                transform: translateX(0) !important;
            }

            .teacher-sidebar .sidebar-content {
                padding: 7rem 0 2rem 0 !important;
            }

            .teacher-sidebar .sidebar-section {
                margin-bottom: 1.5rem !important;
            }

            .teacher-sidebar .sidebar-category {
                font-size: 0.7rem !important;
                font-weight: 700 !important;
                color: #6c757d !important;
                text-transform: uppercase !important;
                letter-spacing: 1px !important;
                margin-bottom: 0.8rem !important;
                padding: 0 1.5rem !important;
                margin-top: 0 !important;
            }

            .teacher-sidebar .sidebar-menu {
                list-style: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .teacher-sidebar .sidebar-item {
                margin-bottom: 0.2rem !important;
            }

            .teacher-sidebar .sidebar-link {
                display: flex !important;
                align-items: center !important;
                padding: 0.7rem 1.5rem !important;
                color: #495057 !important;
                text-decoration: none !important;
                transition: all 0.3s ease !important;
                border-left: 3px solid transparent !important;
            }

            .teacher-sidebar .sidebar-link:hover {
                background-color: #f8f9fa !important;
                color: #4361ee !important;
                text-decoration: none !important;
                border-left-color: #4361ee !important;
            }

            .teacher-sidebar .sidebar-item.active .sidebar-link {
                background-color: #eef1ff !important;
                color: #4361ee !important;
                border-left-color: #4361ee !important;
                font-weight: 600 !important;
            }

            .teacher-sidebar .sidebar-icon {
                width: 18px !important;
                height: 18px !important;
                margin-right: 0.7rem !important;
                text-align: center !important;
                color: inherit !important;
            }

            .teacher-sidebar .sidebar-text {
                font-weight: 500 !important;
                font-size: 0.9rem !important;
                color: inherit !important;
            }

            /* Mobile Sidebar Toggle */
            .sidebar-toggle {
                display: none !important;
                position: fixed !important;
                top: 15px !important;
                left: 15px !important;
                z-index: 1001 !important;
                background: #4361ee !important;
                color: white !important;
                border: none !important;
                padding: 10px 15px !important;
                border-radius: 5px !important;
                cursor: pointer !important;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2) !important;
            }

            .sidebar-toggle:hover {
                background: #3248d8 !important;
            }

            /* Main content area - Full Width Display */
            .main-content {
                margin-left: 260px !important;
                margin-top: 0 !important;
                padding: 20px 50px 30px 30px !important;
                min-height: 100vh;
                width: calc(100vw - 260px) !important;
                max-width: none !important;
                box-sizing: border-box !important;
            }

            /* Ensure all content sections use full width */
            .main-content>*,
            .ebooks-page-header,
            .filter-section,
            .add-book-section,
            .books-grid-section,
            .ebooks-content {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            /* Filter boxes container */
            .filter-boxes {
                width: 100% !important;
                max-width: 100% !important;
            }

            /* Books grid container */
            .books-grid {
                width: 100% !important;
                max-width: 100% !important;
            }

            /* Ensure page layout doesn't interfere with sidebar - Full Width */
            #page-wrapper,
            #page,
            .page {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            #region-main,
            #region-main-box,
            #maincontent {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Remove any Bootstrap/Moodle container max-width restrictions */
            .container-fluid>.row,
            .container-fluid>.row>.col-12,
            [class*="container"]>.row,
            [class*="container"]>.row>[class*="col-"] {
                max-width: 100% !important;
                width: 100% !important;
            }

            /* Override Moodle default container styles */
            #region-main,
            .region-main-content,
            #page-content {
                max-width: 100% !important;
                width: 100% !important;
            }

            @media (max-width: 768px) {
                .sidebar-toggle {
                    display: block !important;
                }

                .teacher-sidebar {
                    transform: translateX(-100%) !important;
                }

                .teacher-sidebar.sidebar-open {
                    transform: translateX(0) !important;
                }

                .main-content {
                    margin-left: 0 !important;
                    width: 100% !important;
                    padding: 15px !important;
                }
            }

            @media (min-width: 769px) {
                .teacher-sidebar {
                    transform: translateX(0) !important;
                    display: block !important;
                    visibility: visible !important;
                }

                .sidebar-toggle {
                    display: none !important;
                }
            }

            /* Page Header - Clean Style */
            .ebooks-page-header {
                background: white;
                padding: 15px 0;
                margin: 0 0 30px 0 !important;
                border-bottom: 1px solid #e9ecef;
                width: 100% !important;
                max-width: 100% !important;
            }

            .ebooks-page-header .container {
                max-width: 100%;
                padding: 0 15px;
            }

            .ebooks-page-header .page-title {
                font-size: 1.75rem;
                font-weight: 700;
                color: #1e40af;
                margin: 0;
                line-height: 1.2;
            }

            .ebooks-page-header .page-subtitle {
                font-size: 1.1rem;
                font-weight: 400;
                color: #6b7280;
                margin: 0;
                line-height: 1.6;
            }

            /* Filter Cards Section */
            .ebooks-filter-section {
                background: white;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 25px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }

            .filter-section-header {
                font-size: 16px;
                font-weight: 600;
                color: #1e293b;
                margin-bottom: 15px;
            }

            .filter-section-wrapper {
                margin-bottom: 25px;
                transition: all 0.3s ease;
            }

            .filter-cards-container {
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
                margin-bottom: 25px;
            }

            .filter-card {
                flex: 1;
                min-width: 220px;
                background: white;
                border: 2px solid #e2e8f0;
                border-radius: 10px;
                padding: 15px;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }

            .filter-card:hover {
                border-color: #cbd5e1;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            .filter-card.selected {
                border-width: 2px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }

            /* Region Card (GCC / KSA) - same layout as level cards */
            .filter-card.region-card {
                padding: 16px;
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 0;
                min-height: 150px;
                position: relative;
                border-radius: 12px;
            }

            .filter-card.region-card .region-checkbox {
                position: absolute;
                top: 12px;
                right: 12px;
            }

            .filter-card.region-card .region-checkbox::after {
                content: '';
                position: absolute;
                width: 18px;
                height: 18px;
                border: 2px solid #94a3b8;
                border-radius: 4px;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }

            .filter-card.region-card.selected .region-checkbox::after {
                background: #2563eb;
                border-color: #2563eb;
            }

            /* Level Card Specific Styles */
            .filter-card.level-card {
                padding: 16px;
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 0;
                min-height: 150px;
                position: relative;
                border-radius: 12px;
            }

            .level-card-icon-wrapper {
                display: flex;
                align-items: flex-start;
                justify-content: flex-start;
                margin-bottom: 12px;
                width: 100%;
            }

            .filter-card-icon {
                width: 40px;
                height: 40px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                flex-shrink: 0;
                color: #64748b;
            }

            .filter-card.selected .filter-card-icon {
                color: white;
            }

            .filter-card.level-card .level-icon {
                width: 50px;
                height: 50px;
                border-radius: 8px;
                font-size: 22px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .filter-card-content {
                flex: 1;
                min-width: 0;
            }

            .filter-card.level-card .level-card-content {
                margin-top: 0;
                width: 100%;
                text-align: left;
            }

            .filter-card-title {
                font-size: 14px;
                font-weight: 600;
                color: #1e293b;
                margin: 0 0 4px 0;
            }

            .filter-card.level-card .filter-card-title {
                font-size: 16px;
                font-weight: 700;
                color: #000000;
                margin: 0 0 8px 0;
                text-align: left;
                line-height: 1.3;
            }

            .filter-card.selected .filter-card-title {
                color: #1e293b;
            }

            .filter-card-description {
                font-size: 12px;
                color: #64748b;
                line-height: 1.4;
                margin: 0 0 6px 0;
            }

            .filter-card.level-card .filter-card-description {
                font-size: 13px;
                color: #6b7280;
                margin: 0;
                text-align: left;
                line-height: 1.5;
            }

            .filter-card-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
                margin-top: 8px;
            }

            .filter-card-checkbox {
                position: absolute;
                top: 12px;
                right: 12px;
                width: 20px;
                height: 20px;
                border: 2px solid #cbd5e1;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
                flex-shrink: 0;
            }

            .filter-card.level-card .level-checkbox {
                top: 16px;
                right: 16px;
                width: 22px;
                height: 22px;
                border: 2px solid #d1d5db;
                border-radius: 6px;
                background: transparent;
            }

            .filter-card.level-card.level-1.selected .level-checkbox,
            .filter-card.level-card.foundation-card.selected .level-checkbox {
                background: #0d9488;
                border-color: #0d9488;
            }

            .filter-card.level-card.level-2.selected .level-checkbox,
            .filter-card.level-card.intermediate-card.selected .level-checkbox {
                background: #a855f7;
                border-color: #a855f7;
            }

            .filter-card.level-card.level-3.selected .level-checkbox,
            .filter-card.level-card.advanced-card.selected .level-checkbox {
                background: #ec4899;
                border-color: #ec4899;
            }

            .filter-card input[type="checkbox"],
            .filter-card input[type="radio"] {
                display: none !important;
                visibility: hidden !important;
                position: absolute !important;
                opacity: 0 !important;
            }

            .filter-card.selected .filter-card-checkbox {
                background: currentColor;
                border-color: currentColor;
            }

            .filter-card.selected .filter-card-checkbox::after {
                content: '\2713';
                color: white;
                font-size: 14px;
                font-weight: bold;
            }

            .filter-card.level-card .level-checkbox::after {
                content: '';
            }

            .filter-card.level-card.selected .level-checkbox::after {
                content: '\2713';
                color: white;
                font-size: 16px;
                font-weight: bold;
            }

            /* Category card colors */
            .filter-card.category-plan.selected {
                border-color: #a855f7;
                color: #a855f7;
            }

            .filter-card.category-plan.selected .filter-card-icon {
                background: #a855f7;
            }

            .filter-card.category-plan.selected .filter-card-badge {
                background: #f3e8ff;
                color: #a855f7;
            }

            .filter-card.category-teach.selected {
                border-color: #10b981;
                color: #10b981;
            }

            .filter-card.category-teach.selected .filter-card-icon {
                background: #10b981;
            }

            .filter-card.category-teach.selected .filter-card-badge {
                background: #d1fae5;
                color: #10b981;
            }

            .filter-card.category-assess.selected {
                border-color: #f59e0b;
                color: #f59e0b;
            }

            .filter-card.category-assess.selected .filter-card-icon {
                background: #f59e0b;
            }

            .filter-card.category-assess.selected .filter-card-badge {
                background: #fef3c7;
                color: #f59e0b;
            }

            /* Level card colors - Always show colored borders */
            .filter-card.level-card.level-1,
            .filter-card.level-card.foundation-card {
                border-color: #0d9488;
                border-width: 2px;
            }

            .filter-card.level-card.level-1.selected,
            .filter-card.level-card.foundation-card.selected {
                border-color: #0d9488;
                color: #0d9488;
                box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2);
            }

            .filter-card.level-card.level-1 .filter-card-icon.level-icon,
            .filter-card.level-card.foundation-card .filter-card-icon.level-icon {
                background: #ccfbf1;
                color: #0d9488;
            }

            .filter-card.level-card.level-1.selected .filter-card-icon.level-icon,
            .filter-card.level-card.foundation-card.selected .filter-card-icon.level-icon {
                background: #0d9488;
                color: white;
            }

            .filter-card.level-card.level-1.selected .level-checkbox,
            .filter-card.level-card.foundation-card.selected .level-checkbox {
                background: #0d9488;
                border-color: #0d9488;
            }

            .filter-card.level-card.level-2,
            .filter-card.level-card.intermediate-card {
                border-color: #a855f7;
                border-width: 2px;
            }

            .filter-card.level-card.level-2.selected,
            .filter-card.level-card.intermediate-card.selected {
                border-color: #a855f7;
                color: #a855f7;
                box-shadow: 0 4px 12px rgba(168, 85, 247, 0.2);
            }

            .filter-card.level-card.level-2 .filter-card-icon.level-icon,
            .filter-card.level-card.intermediate-card .filter-card-icon.level-icon {
                background: #f3e8ff;
                color: #a855f7;
            }

            .filter-card.level-card.level-2.selected .filter-card-icon.level-icon,
            .filter-card.level-card.intermediate-card.selected .filter-card-icon.level-icon {
                background: #a855f7;
                color: white;
            }

            .filter-card.level-card.level-2.selected .level-checkbox,
            .filter-card.level-card.intermediate-card.selected .level-checkbox {
                background: #a855f7;
                border-color: #a855f7;
            }

            .filter-card.level-card.level-3,
            .filter-card.level-card.advanced-card {
                border-color: #ec4899;
                border-width: 2px;
            }

            .filter-card.level-card.level-3.selected,
            .filter-card.level-card.advanced-card.selected {
                border-color: #ec4899;
                color: #ec4899;
                box-shadow: 0 4px 12px rgba(236, 72, 153, 0.2);
            }

            .filter-card.level-card.level-3 .filter-card-icon.level-icon,
            .filter-card.level-card.advanced-card .filter-card-icon.level-icon {
                background: #fce7f3;
                color: #ec4899;
            }

            .filter-card.level-card.level-3.selected .filter-card-icon.level-icon,
            .filter-card.level-card.advanced-card.selected .filter-card-icon.level-icon {
                background: #ec4899;
                color: white;
            }

            .filter-card.level-card.level-3.selected .level-checkbox,
            .filter-card.level-card.advanced-card.selected .level-checkbox {
                background: #ec4899;
                border-color: #ec4899;
            }

            /* Subject card colors */
            .filter-card.subject-english.selected {
                border-color: #ec4899;
                color: #ec4899;
            }

            .filter-card.subject-english.selected .filter-card-icon {
                background: #ec4899;
            }

            .filter-card.subject-maths.selected {
                border-color: #3b82f6;
                color: #3b82f6;
            }

            .filter-card.subject-maths.selected .filter-card-icon {
                background: #3b82f6;
            }

            .filter-card.subject-science.selected {
                border-color: #10b981;
                color: #10b981;
            }

            .filter-card.subject-science.selected .filter-card-icon {
                background: #10b981;
            }

            /* Book type card colors */
            .filter-card.book-type-student.selected {
                border-color: #3b82f6;
                color: #3b82f6;
            }

            .filter-card.book-type-student.selected .filter-card-icon {
                background: #3b82f6;
            }

            .filter-card.book-type-student.selected .filter-card-checkbox {
                background: #3b82f6;
                border-color: #3b82f6;
            }

            .filter-card.book-type-teacher.selected {
                border-color: #a855f7;
                color: #a855f7;
            }

            .filter-card.book-type-teacher.selected .filter-card-icon {
                background: #a855f7;
            }

            .filter-card.book-type-teacher.selected .filter-card-checkbox {
                background: #a855f7;
                border-color: #a855f7;
            }

            /* Grade level card colors - Border and checkbox match icon color when selected */
            .filter-card.subject-grade.grade-1.selected {
                border-color: #dc2626;
                color: #dc2626;
            }

            .filter-card.subject-grade.grade-1.selected .filter-card-icon {
                background: #dc2626;
            }

            .filter-card.subject-grade.grade-1.selected .filter-card-checkbox {
                background: #dc2626;
                border-color: #dc2626;
            }

            .filter-card.subject-grade.grade-2.selected {
                border-color: #ea580c;
                color: #ea580c;
            }

            .filter-card.subject-grade.grade-2.selected .filter-card-icon {
                background: #ea580c;
            }

            .filter-card.subject-grade.grade-2.selected .filter-card-checkbox {
                background: #ea580c;
                border-color: #ea580c;
            }

            .filter-card.subject-grade.grade-3.selected {
                border-color: #d97706;
                color: #d97706;
            }

            .filter-card.subject-grade.grade-3.selected .filter-card-icon {
                background: #d97706;
            }

            .filter-card.subject-grade.grade-3.selected .filter-card-checkbox {
                background: #d97706;
                border-color: #d97706;
            }

            .filter-card.subject-grade.grade-4.selected {
                border-color: #ca8a04;
                color: #ca8a04;
            }

            .filter-card.subject-grade.grade-4.selected .filter-card-icon {
                background: #ca8a04;
            }

            .filter-card.subject-grade.grade-4.selected .filter-card-checkbox {
                background: #ca8a04;
                border-color: #ca8a04;
            }

            .filter-card.subject-grade.grade-5.selected {
                border-color: #65a30d;
                color: #65a30d;
            }

            .filter-card.subject-grade.grade-5.selected .filter-card-icon {
                background: #65a30d;
            }

            .filter-card.subject-grade.grade-5.selected .filter-card-checkbox {
                background: #65a30d;
                border-color: #65a30d;
            }

            .filter-card.subject-grade.grade-6.selected {
                border-color: #059669;
                color: #059669;
            }

            .filter-card.subject-grade.grade-6.selected .filter-card-icon {
                background: #059669;
            }

            .filter-card.subject-grade.grade-6.selected .filter-card-checkbox {
                background: #059669;
                border-color: #059669;
            }

            .filter-card.subject-grade.grade-7.selected {
                border-color: #0d9488;
                color: #0d9488;
            }

            .filter-card.subject-grade.grade-7.selected .filter-card-icon {
                background: #0d9488;
            }

            .filter-card.subject-grade.grade-7.selected .filter-card-checkbox {
                background: #0d9488;
                border-color: #0d9488;
            }

            .filter-card.subject-grade.grade-8.selected {
                border-color: #0891b2;
                color: #0891b2;
            }

            .filter-card.subject-grade.grade-8.selected .filter-card-icon {
                background: #0891b2;
            }

            .filter-card.subject-grade.grade-8.selected .filter-card-checkbox {
                background: #0891b2;
                border-color: #0891b2;
            }

            .filter-card.subject-grade.grade-9.selected {
                border-color: #2563eb;
                color: #2563eb;
            }

            .filter-card.subject-grade.grade-9.selected .filter-card-icon {
                background: #2563eb;
            }

            .filter-card.subject-grade.grade-9.selected .filter-card-checkbox {
                background: #2563eb;
                border-color: #2563eb;
            }

            .filter-card.subject-grade.grade-10.selected {
                border-color: #4f46e5;
                color: #4f46e5;
            }

            .filter-card.subject-grade.grade-10.selected .filter-card-icon {
                background: #4f46e5;
            }

            .filter-card.subject-grade.grade-10.selected .filter-card-checkbox {
                background: #4f46e5;
                border-color: #4f46e5;
            }

            .filter-card.subject-grade.grade-11.selected {
                border-color: #9333ea;
                color: #9333ea;
            }

            .filter-card.subject-grade.grade-11.selected .filter-card-icon {
                background: #9333ea;
            }

            .filter-card.subject-grade.grade-11.selected .filter-card-checkbox {
                background: #9333ea;
                border-color: #9333ea;
            }

            .filter-card.subject-grade.grade-12.selected {
                border-color: #ec4899;
                color: #ec4899;
            }

            .filter-card.subject-grade.grade-12.selected .filter-card-icon {
                background: #ec4899;
            }

            .filter-card.subject-grade.grade-12.selected .filter-card-checkbox {
                background: #ec4899;
                border-color: #ec4899;
            }

            .filter-section {
                margin-bottom: 30px;
                padding: 20px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            }

            .filter-title {
                font-size: 1.3rem;
                font-weight: 600;
                color: #333;
                margin-bottom: 20px;
            }

            .add-book-section {
                margin: 30px 0;
                text-align: center;
            }

            .btn-add-book {
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                color: white;
                border: none;
                padding: 15px 30px;
                font-size: 1.1rem;
                font-weight: 600;
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .btn-add-book:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 20px rgba(79, 172, 254, 0.4);
            }

            .books-grid-section {
                margin-top: 30px;
                overflow: visible;
                min-height: 0;
            }

            .books-grid-section .books-grid {
                overflow: visible;
                min-height: 80px;
            }

            /* Pagination Styles */
            .pagination-container {
                margin-top: 40px;
                padding-top: 30px;
                border-top: 1px solid #e5e7eb;
            }

            .pagination {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                margin-bottom: 15px;
            }

            .pagination-btn {
                padding: 10px 18px;
                border: 1px solid #d1d5db;
                background: white;
                color: #374151;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .pagination-btn:hover:not(:disabled) {
                background: #f3f4f6;
                border-color: #9ca3af;
                transform: translateY(-1px);
            }

            .pagination-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .pagination-pages {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .pagination-page {
                min-width: 40px;
                height: 40px;
                padding: 0 12px;
                border: 1px solid #d1d5db;
                background: white;
                color: #374151;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .pagination-page:hover:not(.active) {
                background: #f3f4f6;
                border-color: #9ca3af;
            }

            .pagination-page.active {
                background: #4361ee;
                color: white;
                border-color: #4361ee;
                cursor: default;
            }

            .pagination-ellipsis {
                padding: 0 8px;
                color: #9ca3af;
                font-weight: 500;
            }

            .pagination-info {
                text-align: center;
                color: #6b7280;
                font-size: 14px;
                margin-top: 10px;
            }

            /* Pagination Styles */
            .pagination-container {
                margin-top: 40px;
                padding-top: 30px;
                border-top: 1px solid #e5e7eb;
            }

            .pagination {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                margin-bottom: 15px;
            }

            .pagination-btn {
                padding: 10px 18px;
                border: 1px solid #d1d5db;
                background: white;
                color: #374151;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .pagination-btn:hover:not(:disabled) {
                background: #f3f4f6;
                border-color: #9ca3af;
                transform: translateY(-1px);
            }

            .pagination-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .pagination-pages {
                display: flex;
                align-items: center;
                gap: 4px;
            }


            .pagination-page {
                min-width: 40px;
                height: 40px;
                padding: 0 12px;
                border: 1px solid #d1d5db;
                background: white;
                color: #374151;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .pagination-page:hover:not(.active) {
                background: #f3f4f6;
                border-color: #9ca3af;
            }

            .pagination-page.active {
                background: #4361ee;
                color: white;
                border-color: #4361ee;
                cursor: default;
            }

            .pagination-ellipsis {
                padding: 0 8px;
                color: #9ca3af;
                font-weight: 500;
            }

            .pagination-info {
                text-align: center;
                color: #6b7280;
                font-size: 14px;
                margin-top: 10px;
            }

            .section-title {
                font-size: 1.5rem;
                font-weight: 600;
                color: #333;
                margin-bottom: 20px;
            }

            .books-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
                width: 100%;
                overflow: visible;
            }

            .book-card {
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                transition: all 0.3s ease;
                position: relative;
                display: flex;
                flex-direction: column;
            }

            .book-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            }

            .book-cover {
                position: relative;
                width: 100%;
                height: 240px;
                overflow: hidden;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .book-cover img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .default-cover {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .book-gradient-overlay {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 55%;
                background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 0%, rgba(0, 0, 0, 0.5) 50%, transparent 100%);
                pointer-events: none;
            }

            .book-content-overlay {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 16px 14px;
                z-index: 2;
            }

            .book-title {
                font-size: 1.1rem;
                font-weight: 700;
                color: white;
                margin: 0 0 8px 0;
                line-height: 1.3;
            }

            .book-description {
                font-size: 0.85rem;
                color: rgba(255, 255, 255, 0.95);
                margin: 0 0 12px 0;
                line-height: 1.5;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .book-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .book-pill {
                padding: 4px 10px;
                background: rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 500;
                color: white;
            }

            .btn-view-book-cta {
                width: 100%;
                padding: 10px 16px;
                background: white;
                color: #1e293b;
                text-align: center;
                font-size: 0.9rem;
                font-weight: 600;
                border: none;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }

            .btn-view-book-cta:hover {
                background: #f8f9fa;
                color: #007bff;
                text-decoration: none;
            }

            .btn-view-book-cta i {
                font-size: 0.9rem;
            }

            .no-books {
                grid-column: 1 / -1;
                text-align: center;
                padding: 60px 20px;
                color: #6c757d;
            }

            .no-books i {
                margin-bottom: 20px;
                color: #ccc;
            }

            /* New Book Card Format Styles */
            .book-card-new {
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
                height: 100%;
            }

            .book-card-new:hover {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
                transform: translateY(-2px);
            }

            /* Preview Area - Approximately 1/3 of card height */
            .book-preview-area {
                width: 100%;
                height: 320px;
                min-height: 320px;
                background: #faf8f4;
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }

            .book-preview-image {
                width: auto;
                height: 110%;
                display: flex;
                margin: auto;
                align-items: center;
                object-fit: cover;
            }

            .book-preview-cover-container {
                width: 100%;
                height: 100%;
                position: relative;
                overflow: hidden;
                background: #f8f9fa;
                padding-bottom: 20px;
            }

            .book-preview-cover-iframe {
                width: 250%;
                height: 350%;
                border: none;
                pointer-events: none;
                /* Prevent interaction with iframe in card */
                transform: scale(0.35) translateY(-100px);
                transform-origin: top left;
                position: absolute;
                top: 0;
                left: 0;
                margin-bottom: 20px;
                image-rendering: -webkit-optimize-contrast;
                image-rendering: crisp-edges;
            }

            .book-preview-placeholder {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
            }

            .book-preview-graphics {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .book-preview-graphics svg {
                width: 100%;
                max-width: 120px;
                height: auto;
                max-height: 100px;
            }

            /* File Information Section - Middle section */
            .book-info-section {
                padding: 16px;
                flex: 1;
                background: white;
            }

            .book-title-new {
                font-size: 16px;
                font-weight: 700;
                color: #1f2937;
                margin: 0 0 12px 0;
                line-height: 1.4;
            }

            .book-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }

            .book-tag {
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
                display: inline-block;
            }

            .book-tag-purple {
                background: #f3e8ff;
                color: #9333ea;
            }

            .book-tag-pink {
                background: #fce7f3;
                color: #ec4899;
            }

            /* Actions Section - Bottom section with thin border separator */
            .book-actions-section {
                padding: 12px 16px;
                display: flex;
                align-items: center;
                border-top: 1px solid #e5e7eb;
                background: white;
            }

            .btn-view-new {
                width: 100%;
                padding: 10px 16px;
                background: linear-gradient(to bottom, #fb923c, #f97316);
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .btn-view-new:hover {
                background: linear-gradient(to bottom, #f97316, #ea580c);
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
                text-decoration: none;
                color: white;
            }

            .btn-view-new i {
                font-size: 14px;
            }

            .btn-favorite {
                width: 40px;
                height: 40px;
                padding: 0;
                background: transparent;
                border: none;
                color: #9ca3af;
                font-size: 18px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
                border-radius: 8px;
            }

            .btn-favorite:hover {
                background: #f3f4f6;
                color: #f59e0b;
            }

            .btn-favorite.active {
                color: #f59e0b;
            }

            /* Book Viewer Iframe Styles */
            .book-viewer-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                backdrop-filter: blur(5px);
            }

            .book-viewer-container {
                width: 95%;
                max-width: 1400px;
                height: 95vh;
                max-height: 1000px;
                display: flex;
                flex-direction: column;
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                overflow: hidden;
            }

            .book-viewer-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                background: #f8f9fa;
                border-bottom: 1px solid #e9ecef;
            }

            .book-viewer-header-left {
                flex: 1;
                min-width: 0;
            }

            .book-viewer-filename {
                font-size: 0.95rem;
                font-weight: 500;
                color: #212529;
                display: block;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .book-viewer-header-right {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-left: 16px;
            }


            .book-viewer-close {
                background: white;
                border: 1px solid #dee2e6;
                color: #212529;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                transition: all 0.3s ease;
                padding: 0;
            }

            .book-viewer-close:hover {
                background: #f8f9fa;
                border-color: #adb5bd;
                transform: rotate(90deg);
            }

            .book-viewer-content {
                flex: 1;
                position: relative;
                overflow: hidden;
                background: white;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .book-viewer-preview-area {
                width: 100%;
                height: 100%;
                position: absolute;
                top: 0;
                left: 0;
            }

            .book-viewer-preview-area iframe {
                width: 100%;
                height: 100%;
                border: none;
            }

            .book-viewer-no-preview {
                text-align: center;
                padding: 60px 40px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            .book-viewer-document-icon {
                width: 120px;
                height: 120px;
                margin-bottom: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .book-viewer-document-icon svg {
                width: 100%;
                height: 100%;
                filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
            }

            .book-viewer-no-preview-text {
                font-size: 1.25rem;
                font-weight: 600;
                color: #212529;
                margin: 0 0 8px 0;
            }

            .book-viewer-no-preview-hint {
                font-size: 0.875rem;
                color: #6c757d;
                margin: 0;
                line-height: 1.5;
            }

            /* Modal Styles */
            .modal {
                display: none;
                position: fixed;
                z-index: 2000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
            }

            .modal-content {
                background: white;
                margin: 5% auto;
                padding: 0;
                border-radius: 15px;
                width: 90%;
                max-width: 600px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            }

            .modal-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px 30px;
                border-radius: 15px 15px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .modal-header h2 {
                margin: 0;
            }

            .close {
                color: white;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }

            .close:hover {
                opacity: 0.7;
            }

            #bookForm {
                padding: 30px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #333;
            }

            .form-group input,
            .form-group textarea {
                width: 100%;
                padding: 12px;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                font-size: 14px;
            }

            .form-group input:focus,
            .form-group textarea:focus {
                outline: none;
                border-color: #667eea;
            }

            .form-group small {
                display: block;
                margin-top: 5px;
                color: #6c757d;
                font-size: 0.85rem;
            }

            .cover-preview {
                margin-top: 10px;
            }

            .cover-preview img {
                max-width: 200px;
                max-height: 300px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            .form-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                margin-top: 30px;
            }

            .btn-cancel,
            .btn-save {
                padding: 12px 25px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
            }

            .btn-cancel {
                background: #e9ecef;
                color: #333;
            }

            .btn-save {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            @media (max-width: 768px) {
                .main-content {
                    margin-left: 0 !important;
                    width: 100% !important;
                    padding: 15px !important;
                }

                .ebooks-filter-section {
                    padding: 20px;
                }

                .filter-cards-container {
                    flex-direction: column;
                }

                .filter-card {
                    min-width: 100%;
                }

                .filter-section-header {
                    font-size: 16px;
                }
            }

            /* Hide notification and message icons in topbar for this page only */
            .navbar [data-region="notifications"],
            .navbar .popover-region-notifications,
            .navbar [data-region="notifications-popover"],
            .navbar .nav-item[data-region="notifications"],
            .navbar .notification-area,
            .navbar [data-region="messages"],
            .navbar .popover-region-messages,
            .navbar [data-region="messages-popover"],
            .navbar .nav-item[data-region="messages"],
            .navbar .message-area,
            .navbar .popover-region,
            .navbar #nav-notification-popover-container,
            .navbar #nav-message-popover-container,
            .navbar .popover-region-container[data-region="notifications"],
            .navbar .popover-region-container[data-region="messages"],
            .navbar .nav-link[data-toggle="popover"][data-region="notifications"],
            .navbar .nav-link[data-toggle="popover"][data-region="messages"],
            .navbar a[href*="message"],
            .navbar a[href*="notification"],
            .navbar .icon-bell,
            .navbar .fa-bell,
            .navbar .icon-envelope,
            .navbar .fa-envelope,
            .navbar .edw-icon-Notification,
            .navbar .edw-icon-Message {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                width: 0 !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                pointer-events: none !important;
            }

            /* Hide dark mode / night mode toggle in topbar for this page only */
            .navbar .nav-darkmode,
            .navbar .nav-item.nav-darkmode,
            .navbar [data-dm],
            .navbar .dm-toggle,
            .navbar .darkmodeicon,
            .navbar .lightmodeicon,
            .navbar .edw-icon-Dark-mode,
            .navbar .edw-icon-Light-mode,
            .navbar a.dm-toggle {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                width: 0 !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                pointer-events: none !important;
            }

            /* Hide edit mode toggle in topbar for this page only */
            .navbar .editingmode,
            .navbar .editmode,
            .navbar [data-key="editmode"],
            .navbar .editmode-switch,
            .navbar .editmode-toggle,
            .navbar a[href*="editmode"],
            .navbar a[href*="edit=on"],
            .navbar a[href*="edit=off"],
            .navbar .usermenu .editmode,
            .navbar #usernavigation .editmode,
            .navbar .navbar-nav .editmode {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                width: 0 !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                pointer-events: none !important;
            }

            /* Hide all user menu dropdown items except logout */
            .navbar #user-action-menu .dropdown-item:not([href*="logout"]):not([href*="logout.php"]),
            .navbar .usermenu .dropdown-item:not([href*="logout"]):not([href*="logout.php"]),
            .navbar [data-region="usermenu"] .dropdown-item:not([href*="logout"]):not([href*="logout.php"]),
            .navbar .dropdown-menu#user-action-menu .dropdown-item:not([href*="logout"]):not([href*="logout.php"]),
            .navbar .carousel-item .dropdown-item:not([href*="logout"]):not([href*="logout.php"]),
            .navbar #usermenu-carousel .dropdown-item:not([href*="logout"]):not([href*="logout.php"]),
            .navbar #user-action-menu a.dropdown-item:not([href*="logout"]):not([href*="logout.php"]) {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                pointer-events: none !important;
            }

            /* Show logout button */
            .navbar #user-action-menu .dropdown-item[href*="logout"],
            .navbar #user-action-menu .dropdown-item[href*="logout.php"],
            .navbar .usermenu .dropdown-item[href*="logout"],
            .navbar .usermenu .dropdown-item[href*="logout.php"],
            .navbar [data-region="usermenu"] .dropdown-item[href*="logout"],
            .navbar [data-region="usermenu"] .dropdown-item[href*="logout.php"],
            .navbar .dropdown-menu#user-action-menu .dropdown-item[href*="logout"],
            .navbar .dropdown-menu#user-action-menu .dropdown-item[href*="logout.php"],
            .navbar .carousel-item .dropdown-item[href*="logout"],
            .navbar .carousel-item .dropdown-item[href*="logout.php"],
            .navbar #usermenu-carousel .dropdown-item[href*="logout"],
            .navbar #usermenu-carousel .dropdown-item[href*="logout.php"] {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                height: auto !important;
                margin: 0.25rem 0 !important;
                padding: 0.5rem 1rem !important;
                pointer-events: auto !important;
            }

            /* Hide all dividers in user menu (they're not needed if only logout is visible) */
            .navbar #user-action-menu .dropdown-divider,
            .navbar .usermenu .dropdown-divider,
            .navbar [data-region="usermenu"] .dropdown-divider {
                display: none !important;
            }

            /* Hide submenu navigation links (carousel navigation) */
            .navbar #user-action-menu .carousel-navigation-link,
            .navbar .usermenu .carousel-navigation-link {
                display: none !important;
            }
        </style>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            // Filter Cards Interaction
            document.addEventListener('DOMContentLoaded', function () {
                // Handle region filter cards (GCC / KSA - multiple selection)
                const regionCards = document.querySelectorAll('#regionFilterCards .filter-card');
                regionCards.forEach(card => {
                    const checkbox = card.querySelector('input[type="checkbox"]');
                    if (!checkbox) return;
                    card.addEventListener('click', function (e) {
                        if (e.target.tagName === 'INPUT' && e.target.type === 'checkbox') e.stopPropagation();
                        if (e.target.closest('.filter-card-checkbox')) e.stopPropagation();
                        checkbox.checked = !checkbox.checked;
                        if (checkbox.checked) card.classList.add('selected');
                        else card.classList.remove('selected');
                        const url = new URL(window.location.href);
                        const selectedRegions = [];
                        document.querySelectorAll('#regionFilterCards input[type="checkbox"]:checked').forEach(cb => { selectedRegions.push(cb.value); });
                        url.searchParams.delete('region');
                        const allKeys = Array.from(url.searchParams.keys());
                        allKeys.forEach(key => {
                            if (key === 'regions[]' || key.startsWith('regions[')) url.searchParams.delete(key);
                        });
                        if (selectedRegions.length > 0) {
                            selectedRegions.forEach(reg => url.searchParams.append('regions[]', reg));
                        } else {
                            url.searchParams.delete('level');
                            allKeys.forEach(key => {
                                if (key === 'levels[]' || key.startsWith('levels[')) url.searchParams.delete(key);
                            });
                            url.searchParams.delete('subject');
                            allKeys.forEach(key => {
                                if (key === 'subjects[]' || key.startsWith('subjects[')) url.searchParams.delete(key);
                            });
                            url.searchParams.delete('book_type');
                        }
                        history.pushState({}, '', url.toString());
                        updateFilterVisibility();
                        filterBooks(true);
                    });
                });

                // Handle level filter cards (multiple selection - checkbox)
                const levelCards = document.querySelectorAll('#levelFilterCards .filter-card');
                levelCards.forEach(card => {
                    const checkbox = card.querySelector('input[type="checkbox"]');

                    card.addEventListener('click', function (e) {
                        // Allow clicking on checkbox directly
                        if (e.target.tagName === 'INPUT' && e.target.type === 'checkbox') {
                            e.stopPropagation();
                        }
                        if (e.target.closest('.filter-card-checkbox')) {
                            e.stopPropagation();
                        }

                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            if (checkbox.checked) {
                                card.classList.add('selected');
                            } else {
                                card.classList.remove('selected');
                            }

                            // Update URL with multiple levels
                            const url = new URL(window.location.href);
                            const selectedLevels = [];
                            document.querySelectorAll('#levelFilterCards input[type="checkbox"]:checked').forEach(cb => {
                                selectedLevels.push(cb.value);
                            });

                            // Remove all level parameters
                            url.searchParams.delete('level');
                            const allParams = Array.from(url.searchParams.keys());
                            allParams.forEach(key => {
                                if (key === 'levels[]' || key.startsWith('levels[')) {
                                    url.searchParams.delete(key);
                                }
                            });

                            if (selectedLevels.length > 0) {
                                // Add multiple levels
                                selectedLevels.forEach(level => {
                                    url.searchParams.append('levels[]', level);
                                });
                            } else {
                                // Clear dependent filters when levels are cleared
                                url.searchParams.delete('subject');
                                const allSubjectParams = Array.from(url.searchParams.keys());
                                allSubjectParams.forEach(key => {
                                    if (key === 'subjects[]' || key.startsWith('subjects[')) {
                                        url.searchParams.delete(key);
                                    }
                                });
                                url.searchParams.delete('book_type');
                            }

                            // Update URL without page reload
                            history.pushState({}, '', url.toString());

                            // Update filter visibility and filter books
                            updateFilterVisibility();
                            filterBooks(true);
                        }
                    });
                });

                // Handle subject filter cards (multiple selection - checkbox)
                const subjectCards = document.querySelectorAll('#subjectFilterCards .filter-card');
                subjectCards.forEach(card => {
                    const checkbox = card.querySelector('input[type="checkbox"]');

                    card.addEventListener('click', function (e) {
                        // Allow clicking on checkbox directly
                        if (e.target.tagName === 'INPUT' && e.target.type === 'checkbox') {
                            e.stopPropagation();
                        }
                        if (e.target.closest('.filter-card-checkbox')) {
                            e.stopPropagation();
                        }

                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            if (checkbox.checked) {
                                card.classList.add('selected');
                            } else {
                                card.classList.remove('selected');
                            }

                            // Update URL with multiple subjects
                            const url = new URL(window.location.href);
                            const selectedSubjects = [];
                            document.querySelectorAll('#subjectFilterCards input[type="checkbox"]:checked').forEach(cb => {
                                selectedSubjects.push(cb.value);
                            });

                            // Remove all subject parameters
                            url.searchParams.delete('subject');
                            const allParams = Array.from(url.searchParams.keys());
                            allParams.forEach(key => {
                                if (key === 'subjects[]' || key.startsWith('subjects[')) {
                                    url.searchParams.delete(key);
                                }
                            });

                            if (selectedSubjects.length > 0) {
                                // Add multiple subjects
                                selectedSubjects.forEach(subject => {
                                    url.searchParams.append('subjects[]', subject);
                                });
                            } else {
                                // Clear dependent filter when subjects are cleared
                                url.searchParams.delete('book_type');
                            }

                            // Update URL without page reload
                            history.pushState({}, '', url.toString());

                            // Update filter visibility and filter books
                            updateFilterVisibility();
                            filterBooks(true);
                        }
                    });
                });

                // Handle book type filter cards (single selection - radio with deselection)
                const bookTypeCards = document.querySelectorAll('#bookTypeFilterCards .filter-card');
                bookTypeCards.forEach(card => {
                    const radio = card.querySelector('input[type="radio"]');

                    card.addEventListener('click', function (e) {
                        if (e.target.tagName === 'INPUT') return;

                        const url = new URL(window.location.href);
                        const isCurrentlySelected = card.classList.contains('selected');

                        // If clicking the same card that's already selected, deselect it
                        if (isCurrentlySelected && radio && radio.checked) {
                            card.classList.remove('selected');
                            if (radio) radio.checked = false;
                            url.searchParams.delete('book_type');
                        } else {
                            // Uncheck all book type cards
                            bookTypeCards.forEach(c => {
                                c.classList.remove('selected');
                                const r = c.querySelector('input[type="radio"]');
                                if (r) r.checked = false;
                            });

                            // Check clicked card
                            card.classList.add('selected');
                            if (radio) radio.checked = true;
                            url.searchParams.set('book_type', radio.value);
                        }

                        // Update URL without page reload
                        history.pushState({}, '', url.toString());

                        // Filter books dynamically
                        filterBooks();
                    });
                });

                // Book Viewer Iframe Functionality
                function getFileExtension(url) {
                    const path = url.split('?')[0]; // Remove query string
                    const parts = path.split('.');
                    return parts.length > 1 ? parts[parts.length - 1].toLowerCase() : '';
                }

                function canPreviewFile(url) {
                    const extension = getFileExtension(url);
                    const previewableExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'html', 'htm', 'txt'];
                    return previewableExtensions.includes(extension);
                }

                function openBookViewer(bookUrl, bookTitle) {
                    const overlay = document.getElementById('bookViewerOverlay');
                    const iframe = document.getElementById('bookViewerIframe');
                    const title = document.getElementById('bookViewerTitle');
                    const previewArea = document.getElementById('bookViewerPreview');
                    const noPreviewArea = document.getElementById('bookViewerNoPreview');

                    if (!overlay || !title || !previewArea || !noPreviewArea) {
                        return;
                    }

                    // Set title
                    title.textContent = bookTitle;

                    // Check if file can be previewed
                    const canPreview = canPreviewFile(bookUrl);

                    if (canPreview && iframe) {
                        // Show iframe preview
                        previewArea.style.display = 'block';
                        noPreviewArea.style.display = 'none';
                        iframe.src = bookUrl;
                    } else {
                        // Show no preview message
                        previewArea.style.display = 'none';
                        noPreviewArea.style.display = 'flex';
                        if (iframe) {
                            iframe.src = '';
                        }
                    }

                    overlay.style.display = 'flex';
                    document.body.style.overflow = 'hidden'; // Prevent background scrolling
                }

                function closeBookViewer() {
                    const overlay = document.getElementById('bookViewerOverlay');
                    const iframe = document.getElementById('bookViewerIframe');
                    const previewArea = document.getElementById('bookViewerPreview');
                    const noPreviewArea = document.getElementById('bookViewerNoPreview');

                    if (overlay && iframe) {
                        overlay.style.display = 'none';
                        iframe.src = ''; // Clear iframe source
                        document.body.style.overflow = ''; // Restore scrolling

                        // Reset preview areas
                        if (previewArea) {
                            previewArea.style.display = 'none';
                        }
                        if (noPreviewArea) {
                            noPreviewArea.style.display = 'flex';
                        }
                    }
                }

                // Function to attach book viewer listeners
                function attachBookViewerListeners() {
                    // Handle View button clicks (works for both static and dynamically loaded buttons)
                    document.querySelectorAll('.btn-view-new').forEach(button => {
                        if (button.dataset.bookUrl && !button.hasAttribute('data-viewer-attached')) {
                            button.setAttribute('data-viewer-attached', 'true');
                            button.addEventListener('click', function (e) {
                                e.preventDefault();
                                const bookUrl = this.dataset.bookUrl;
                                const bookTitle = this.dataset.bookTitle || 'Book Viewer';
                                openBookViewer(bookUrl, bookTitle);
                            });
                        }
                    });
                }

                // Initial attachment of listeners
                attachBookViewerListeners();

                // Preload iframes immediately for faster loading
                function preloadBookPreviewIframes() {
                    document.querySelectorAll('.book-preview-cover-iframe').forEach(iframe => {
                        if (iframe.src && !iframe.hasAttribute('data-preloaded')) {
                            iframe.setAttribute('data-preloaded', 'true');
                            // Ensure iframe starts loading immediately
                            iframe.setAttribute('loading', 'eager');
                            // Create prefetch link for faster loading
                            try {
                                const preloadLink = document.createElement('link');
                                preloadLink.rel = 'prefetch';
                                preloadLink.href = iframe.src;
                                preloadLink.as = 'document';
                                if (!document.querySelector(`link[href="${iframe.src}"]`)) {
                                    document.head.appendChild(preloadLink);
                                }
                            } catch (e) {
                                // Ignore errors for prefetch
                            }
                        }
                    });
                }

                // Call preload on initial page load
                preloadBookPreviewIframes();

                // Handle image loading errors gracefully - prevent 404 console errors
                function handleImageErrors() {
                    document.querySelectorAll('.book-preview-image').forEach(img => {
                        if (img.dataset.coverImage && !img.dataset.errorHandled) {
                            img.dataset.errorHandled = 'true';
                            // Handle image load errors
                            img.addEventListener('error', function () {
                                this.style.display = 'none';
                                const placeholder = this.nextElementSibling;
                                if (placeholder && placeholder.classList.contains('book-preview-placeholder')) {
                                    placeholder.style.display = 'flex';
                                }
                                // Clear the src to prevent repeated failed requests
                                this.onerror = null;
                            }, { once: true });

                            // Handle successful image load
                            img.addEventListener('load', function () {
                                this.style.display = 'block';
                                const placeholder = this.nextElementSibling;
                                if (placeholder && placeholder.classList.contains('book-preview-placeholder')) {
                                    placeholder.style.display = 'none';
                                }
                            }, { once: true });
                        }
                    });
                }

                // Call on page load
                handleImageErrors();

                // Handle close button
                const closeButton = document.getElementById('closeBookViewer');
                if (closeButton) {
                    closeButton.addEventListener('click', closeBookViewer);
                }

                // Close on overlay click (outside the iframe container)
                const overlay = document.getElementById('bookViewerOverlay');
                if (overlay) {
                    overlay.addEventListener('click', function (e) {
                        if (e.target === overlay) {
                            closeBookViewer();
                        }
                    });
                }

                // Close on Escape key
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        const overlay = document.getElementById('bookViewerOverlay');
                        if (overlay && overlay.style.display === 'flex') {
                            closeBookViewer();
                        }
                    }
                });

                // Handle cascading filter logic
                function updateFilterVisibility() {
                    const selectedRegions = [];
                    document.querySelectorAll('#regionFilterCards input[type="checkbox"]:checked').forEach(cb => {
                        selectedRegions.push(cb.value);
                    });
                    const selectedLevels = [];
                    document.querySelectorAll('#levelFilterCards input[type="checkbox"]:checked').forEach(cb => {
                        selectedLevels.push(cb.value);
                    });
                    const selectedSubjects = document.querySelectorAll('#subjectFilterCards input[type="checkbox"]:checked');
                    const levelSection = document.getElementById('levelSectionWrapper');
                    const subjectSection = document.getElementById('subjectSectionWrapper');
                    const bookTypeSection = document.getElementById('bookTypeSectionWrapper');

                    // Show/hide level section based on region selection
                    if (levelSection) {
                        levelSection.style.display = selectedRegions.length > 0 ? 'block' : 'none';
                    }

                    // Check if we're using Foundation/Intermediate/Advanced (grades) or KG Levels (subjects)
                    const hasFoundationCategories = document.querySelector('.foundation-card, .intermediate-card, .advanced-card') !== null;

                    // Show/hide subject section based on level selection
                    if (selectedLevels.length > 0) {
                        if (subjectSection) subjectSection.style.display = 'block';

                        // If using Foundation/Intermediate/Advanced, dynamically show/hide grade cards based on selected levels
                        if (hasFoundationCategories) {
                            const gradeCards = document.querySelectorAll('#subjectFilterCards .subject-grade');
                            gradeCards.forEach(gradeCard => {
                                const gradeNum = parseInt(gradeCard.getAttribute('data-grade-num') || gradeCard.textContent.match(/Grade\s+(\d+)/)?.[1] || 0);

                                if (isNaN(gradeNum) || gradeNum < 1 || gradeNum > 12) {
                                    return; // Skip if not a valid grade number
                                }

                                let shouldShow = false;
                                if (selectedLevels.includes('Foundation') && gradeNum >= 1 && gradeNum <= 5) {
                                    shouldShow = true;
                                }
                                if (selectedLevels.includes('Intermediate') && gradeNum >= 6 && gradeNum <= 8) {
                                    shouldShow = true;
                                }
                                if (selectedLevels.includes('Advanced') && gradeNum >= 9 && gradeNum <= 12) {
                                    shouldShow = true;
                                }

                                // Show/hide grade card
                                if (shouldShow) {
                                    gradeCard.style.display = '';
                                    gradeCard.classList.remove('grade-card-hidden');
                                } else {
                                    gradeCard.style.display = 'none';
                                    gradeCard.classList.add('grade-card-hidden');
                                    // Uncheck and deselect hidden grade cards
                                    const checkbox = gradeCard.querySelector('input[type="checkbox"]');
                                    if (checkbox) {
                                        checkbox.checked = false;
                                        gradeCard.classList.remove('selected');
                                    }
                                }
                            });
                        }
                    } else {
                        if (subjectSection) subjectSection.style.display = 'none';
                        // Clear subject selections when levels are cleared
                        document.querySelectorAll('#subjectFilterCards input[type="checkbox"]').forEach(cb => {
                            cb.checked = false;
                        });
                        document.querySelectorAll('#subjectFilterCards .filter-card').forEach(card => {
                            card.classList.remove('selected');
                        });
                    }

                    // Show/hide book type section based on subject selection
                    if (selectedSubjects.length > 0) {
                        if (bookTypeSection) bookTypeSection.style.display = 'block';
                    } else {
                        if (bookTypeSection) bookTypeSection.style.display = 'none';
                        // Clear book type selection when subjects are cleared
                        document.querySelectorAll('#bookTypeFilterCards input[type="radio"]').forEach(radio => {
                            radio.checked = false;
                        });
                        document.querySelectorAll('#bookTypeFilterCards .filter-card').forEach(card => {
                            card.classList.remove('selected');
                        });
                    }
                }

                // Update visibility on level changes
                levelCards.forEach(card => {
                    const checkbox = card.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.addEventListener('change', function () {
                            updateFilterVisibility();
                        });
                    }
                });

                // Update visibility on subject changes
                subjectCards.forEach(card => {
                    const checkbox = card.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.addEventListener('change', function () {
                            updateFilterVisibility();
                        });
                    }
                });

                // Initial visibility update
                updateFilterVisibility();

                // Function to filter books dynamically via AJAX
                function filterBooks(resetPage = false) {
                    const selectedRegions = [];
                    document.querySelectorAll('#regionFilterCards input[type="checkbox"]:checked').forEach(cb => {
                        selectedRegions.push(cb.value);
                    });
                    const selectedLevels = [];
                    document.querySelectorAll('#levelFilterCards input[type="checkbox"]:checked').forEach(cb => {
                        selectedLevels.push(cb.value);
                    });

                    const selectedSubjects = [];
                    document.querySelectorAll('#subjectFilterCards input[type="checkbox"]:checked').forEach(cb => {
                        selectedSubjects.push(cb.value);
                    });

                    const selectedBookType = document.querySelector('#bookTypeFilterCards input[type="radio"]:checked');
                    const bookType = selectedBookType ? selectedBookType.value : '';

                    // Check if any filter is selected (region is top-level; at least region required to show books)
                    const hasFilters = selectedRegions.length > 0 || selectedLevels.length > 0 || selectedSubjects.length > 0 || bookType;

                    if (!hasFilters) {
                        const booksSection = document.querySelector('.books-grid-section');
                        const booksGrid = document.getElementById('booksGrid');
                        const hasExistingCards = booksGrid && booksGrid.querySelectorAll('.book-card-new').length > 0;
                        // Only hide if we have no filters and no existing cards (avoid hiding due to transient checkbox state)
                        if (booksSection && !hasExistingCards) {
                            booksSection.style.display = 'none';
                        }
                        return;
                    }

                    // Show books section immediately
                    // Ensure books section exists, create if it doesn't
                    let booksSection = document.querySelector('.books-grid-section');
                    if (!booksSection) {
                        // Create books section if it doesn't exist
                        booksSection = document.createElement('div');
                        booksSection.className = 'books-grid-section';
                        booksSection.innerHTML = '<h3 class="section-title">Books Available</h3><div class="books-grid" id="booksGrid"></div>';
                        // Insert after the filter sections or before the end of main content
                        const filterBoxes = document.querySelector('.filter-boxes') || document.querySelector('.ebooks-filter-section');
                        if (filterBoxes && filterBoxes.parentNode) {
                            filterBoxes.parentNode.insertBefore(booksSection, filterBoxes.nextSibling);
                        } else {
                            const mainContent = document.querySelector('.main-content') || document.querySelector('.ebooks-content');
                            if (mainContent) {
                                mainContent.appendChild(booksSection);
                            }
                        }
                    }

                    // Show books section immediately
                    booksSection.style.display = 'block';
                    // Scroll to books section smoothly
                    setTimeout(() => {
                        booksSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 100);

                    // Get or create booksGrid element
                    let booksGrid = document.getElementById('booksGrid');
                    if (!booksGrid) {
                        booksGrid = booksSection.querySelector('.books-grid');
                        if (!booksGrid) {
                            booksGrid = document.createElement('div');
                            booksGrid.className = 'books-grid';
                            booksGrid.id = 'booksGrid';
                            booksSection.appendChild(booksGrid);
                        }
                    }

                    // Get current page - reset to 1 when filters change, keep current when paginating
                    const urlParams = new URLSearchParams(window.location.search);
                    let currentPage = resetPage ? 1 : (parseInt(urlParams.get('page')) || 1);

                    // Build URL with filters and pagination
                    const url = new URL(window.location.origin + window.location.pathname);
                    selectedRegions.forEach(region => {
                        url.searchParams.append('regions[]', region);
                    });
                    selectedLevels.forEach(level => {
                        url.searchParams.append('levels[]', level);
                    });
                    selectedSubjects.forEach(subject => {
                        url.searchParams.append('subjects[]', subject);
                    });
                    if (bookType) {
                        url.searchParams.set('book_type', bookType);
                    }
                    url.searchParams.set('page', currentPage);

                    // Show loading state
                    booksGrid.innerHTML = '<div class="no-books"><i class="fa fa-spinner fa-spin fa-3x"></i><p>Loading books...</p></div>';

                    // Fetch books via AJAX
                    fetch(url.toString())
                        .then(response => response.text())
                        .then(html => {
                            // Parse the response and extract books grid
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newBooksGrid = doc.getElementById('booksGrid');

                            if (newBooksGrid && booksGrid) {
                                booksGrid.innerHTML = newBooksGrid.innerHTML;

                                // Update pagination controls
                                const newPagination = doc.getElementById('paginationContainer');
                                const paginationContainer = document.getElementById('paginationContainer');
                                if (newPagination && paginationContainer) {
                                    paginationContainer.innerHTML = newPagination.innerHTML;
                                    attachPaginationListeners();
                                } else if (newPagination) {
                                    const paginationDiv = document.createElement('div');
                                    paginationDiv.id = 'paginationContainer';
                                    paginationDiv.className = 'pagination-container';
                                    paginationDiv.innerHTML = newPagination.innerHTML;
                                    booksSection.appendChild(paginationDiv);
                                    attachPaginationListeners();
                                } else if (paginationContainer) {
                                    paginationContainer.remove();
                                }

                                // Re-attach event listeners for view buttons after AJAX update
                                if (typeof attachBookViewerListeners === 'function') {
                                    attachBookViewerListeners();
                                }

                                // Preload iframes immediately after books are loaded
                                if (typeof preloadBookPreviewIframes === 'function') {
                                    preloadBookPreviewIframes();
                                }

                                // Handle image errors after AJAX update
                                setTimeout(() => {
                                    if (typeof handleImageErrors === 'function') {
                                        handleImageErrors();
                                    }
                                }, 100);
                            } else if (booksGrid) {
                                // If no books found in response, show empty state
                                booksGrid.innerHTML = '<div class="no-books"><i class="fa fa-book-open fa-3x"></i><p>No books found matching the selected filters.</p></div>';
                                const paginationContainer = document.getElementById('paginationContainer');
                                if (paginationContainer) {
                                    paginationContainer.remove();
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching books:', error);
                            if (booksGrid) {
                                booksGrid.innerHTML = '<div class="no-books"><i class="fa fa-exclamation-triangle fa-3x"></i><p>Error loading books. Please try again.</p></div>';
                            }
                        });
                }

                // Handle checkbox clicks directly
                document.querySelectorAll('.filter-card input[type="checkbox"]').forEach(input => {
                    input.addEventListener('change', function (e) {
                        e.stopPropagation();
                        const card = this.closest('.filter-card');
                        if (card) {
                            if (this.checked) {
                                card.classList.add('selected');
                            } else {
                                card.classList.remove('selected');
                            }

                            // Update URL - same logic as card click
                            const url = new URL(window.location.href);
                            const containerId = card.closest('.filter-cards-container').id;

                            if (containerId === 'levelFilterCards') {
                                const selectedLevels = [];
                                document.querySelectorAll('#levelFilterCards input[type="checkbox"]:checked').forEach(cb => {
                                    selectedLevels.push(cb.value);
                                });

                                url.searchParams.delete('level');
                                const allParams = Array.from(url.searchParams.keys());
                                allParams.forEach(key => {
                                    if (key === 'levels[]' || key.startsWith('levels[')) {
                                        url.searchParams.delete(key);
                                    }
                                });

                                if (selectedLevels.length > 0) {
                                    selectedLevels.forEach(level => {
                                        url.searchParams.append('levels[]', level);
                                    });
                                } else {
                                    url.searchParams.delete('subject');
                                    const allSubjectParams = Array.from(url.searchParams.keys());
                                    Array.from(url.searchParams.keys()).forEach(key => {
                                        if (key === 'subjects[]' || key.startsWith('subjects[')) {
                                            url.searchParams.delete(key);
                                        }
                                    });
                                    url.searchParams.delete('book_type');
                                }
                            } else if (containerId === 'subjectFilterCards') {
                                const selectedSubjects = [];
                                document.querySelectorAll('#subjectFilterCards input[type="checkbox"]:checked').forEach(cb => {
                                    selectedSubjects.push(cb.value);
                                });

                                url.searchParams.delete('subject');
                                const allParams = Array.from(url.searchParams.keys());
                                allParams.forEach(key => {
                                    if (key === 'subjects[]' || key.startsWith('subjects[')) {
                                        url.searchParams.delete(key);
                                    }
                                });

                                if (selectedSubjects.length > 0) {
                                    selectedSubjects.forEach(subject => {
                                        url.searchParams.append('subjects[]', subject);
                                    });
                                } else {
                                    url.searchParams.delete('book_type');
                                }
                            }

                            // Update URL without page reload
                            history.pushState({}, '', url.toString());

                            // Update filter visibility and filter books
                            updateFilterVisibility();
                            filterBooks(true);
                        }
                    });
                });

                // Initialize: If filters are present in URL, server has already rendered the books grid - just keep it visible.
                // Do NOT call filterBooks() on load: it would replace server-rendered cards with "Loading..." then fetch,
                // which can make cards disappear if the fetch fails, is slow, or returns different HTML.
                const urlParams = new URLSearchParams(window.location.search);
                const hasFiltersOnLoad = urlParams.has('regions[]') || urlParams.has('region') || urlParams.has('levels[]') || urlParams.has('subjects[]') || urlParams.has('book_type') ||
                    Array.from(urlParams.keys()).some(key => key.startsWith('regions[') || key.startsWith('levels[') || key.startsWith('subjects['));
                if (hasFiltersOnLoad) {
                    const booksSection = document.querySelector('.books-grid-section');
                    if (booksSection) {
                        booksSection.style.display = 'block';
                    }
                }

                // Pagination event handlers
                function attachPaginationListeners() {
                    document.querySelectorAll('.pagination-btn, .pagination-page').forEach(btn => {
                        const newBtn = btn.cloneNode(true);
                        btn.parentNode.replaceChild(newBtn, btn);

                        newBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            if (this.disabled || this.classList.contains('active')) return;

                            const page = parseInt(this.getAttribute('data-page'));
                            if (page && page > 0) {
                                const url = new URL(window.location.href);
                                url.searchParams.set('page', page);
                                history.pushState({}, '', url.toString());
                                filterBooks(false);
                                const booksSection = document.querySelector('.books-grid-section');
                                if (booksSection) {
                                    booksSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                }
                            }
                        });
                    });
                }

                // Attach pagination listeners on page load
                attachPaginationListeners();
            });

            // Cover image preview
            document.getElementById('coverImage')?.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const preview = document.getElementById('coverPreview');
                        preview.innerHTML = '<img src="' + e.target.result + '" alt="Cover Preview">';
                    };
                    reader.readAsDataURL(file);
                }
            });

        </script>

        <?php
        echo $OUTPUT->footer();
        ?>