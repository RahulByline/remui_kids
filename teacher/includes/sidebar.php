<?php
/**
 * Reusable Teacher Sidebar Include
 * Include this file in all teacher pages
 * 
 * Usage: include(__DIR__ . '/includes/sidebar.php');
 * 
 * @package theme_remui_kids
 */

defined('MOODLE_INTERNAL') || die();

global $CFG, $PAGE, $USER, $DB;
require_once($CFG->dirroot . '/theme/remui_kids/lib/emulator_manager.php');
require_once($CFG->dirroot . '/theme/remui_kids/lib/teacher_school_helper.php');

// Check if Role Switch Access is enabled for this teacher's school
$role_switch_enabled = true; // Default enabled
if (function_exists('theme_remui_kids_get_teacher_company_id')) {
    $teacher_company_id = theme_remui_kids_get_teacher_company_id();
    if ($teacher_company_id) {
        $dbman = $DB->get_manager();
        $settings_table_exists = $dbman->table_exists(new xmldb_table('theme_remui_school_settings'));

        if ($settings_table_exists) {
            $school_setting = $DB->get_record('theme_remui_school_settings', ['schoolid' => $teacher_company_id]);
            if ($school_setting && isset($school_setting->role_switch_enabled)) {
                $role_switch_enabled = (bool) $school_setting->role_switch_enabled;
            }
        }
    }
}

// Get current page for highlighting
$current_url = $PAGE->url->out_omit_querystring();
$current_script = basename($_SERVER['SCRIPT_NAME']);

if (!function_exists('theme_remui_kids_sidebar_match')) {
    function theme_remui_kids_sidebar_match(string $script, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }
            if (strpos($pattern, '.php') !== false) {
                if ($script === $pattern) {
                    return true;
                }
            } else if (strpos($script, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
}

// Active page indicators
$is_dashboard = (strpos($current_url, '/my/') !== false);
$is_courses = theme_remui_kids_sidebar_match($current_script, [
    'teacher_courses.php',
    'course_preview.php'
]);
$is_resources = theme_remui_kids_sidebar_match($current_script, [
    'view_course.php',
    'pdf_viewer.php'
]);
$is_schedule = theme_remui_kids_sidebar_match($current_script, [
    'schedule.php',
    'debug_schedule.php',
    'test_schedule_data.php',
    'create_sample_events.php',
    'fix_schedule.php',
    'check_event_match.php'
]);
$is_lessons = theme_remui_kids_sidebar_match($current_script, [
    'lessonplan.php',
]);
$is_students = theme_remui_kids_sidebar_match($current_script, [
    'students.php',
]);
$is_lessonplan = theme_remui_kids_sidebar_match($current_script, [
    'lessonplan.php',
]);
$is_assignments = theme_remui_kids_sidebar_match($current_script, [
    'assignments.php',
    'create_assignment',
    'edit_assignment',
    'update_assignment',
    'save_manual_grade.php',
    'create_codeeditor',
    'grade_assignment.php',
    'create_codeeditor_page.php',
    'create_assignment_page.php'
]);
$is_quizzes = theme_remui_kids_sidebar_match($current_script, [
    'quizzes.php',
    'create_quiz.php',
    'create_quiz_page.php',
    'quiz_attempts.php',
    'quiz_review.php',
    'quiz_attempts_data.php'
]);
$is_competencies = theme_remui_kids_sidebar_match($current_script, [
    'competencies.php',
    'student_competencies.php',
    'student_competency_evidence.php',
    'competency_details.php',
    'save_competency_rating.php'
]);
$is_switchrole = theme_remui_kids_sidebar_match($current_script, [
    'index.php'
]) && strpos($current_url, '/local/teacherviewstudent/') !== false;
$is_rubrics = theme_remui_kids_sidebar_match($current_script, [
    'rubrics.php',
    'rubric_grading.php',
    'rubric_view.php',
    'grade_student.php',
    'grade_codeeditor_student.php'
]);
$is_gradebook = theme_remui_kids_sidebar_match($current_script, [
    'gradebook.php'
]);
$is_doubts = theme_remui_kids_sidebar_match($current_script, [
    'teacher_doubts.php',
    'questions_unified.php',
    'save_question.php',
    'get_question_bank.php',
    'get_question_categories.php'
]);
$is_emulators = theme_remui_kids_sidebar_match($current_script, [
    'emulators.php'
]);

$is_activity_logs = theme_remui_kids_sidebar_match($current_script, [
    'activity_logs.php'
]);

$is_reports = theme_remui_kids_sidebar_match($current_script, [
    'reports.php',
]);

$is_need_help = theme_remui_kids_sidebar_match($current_script, [
    'need_help.php'
]);

$is_ebook = theme_remui_kids_sidebar_match($current_script, [
    'local/ebook/manage.php',
    'local/ebook/edit.php',
]) || (strpos($current_url, '/local/ebook/') !== false);

$is_certificates = theme_remui_kids_sidebar_match($current_script, [
    'admin_dashboard.php',
    'school_dashboard.php',
    'teacher_dashboard.php',
    'student_dashboard.php',
]) || (strpos($current_url, '/local/certificate_approval/') !== false);

$is_ebooks = theme_remui_kids_sidebar_match($current_script, [
    'ebooks.php',
    'student_book.php',
    'teacher_book.php',
    'practice_book.php'
]) || (strpos($current_url, '/theme/remui_kids/teacher/ebooks.php') !== false)
    || (strpos($current_url, '/theme/remui_kids/teacher/student_book.php') !== false)
    || (strpos($current_url, '/theme/remui_kids/teacher/teacher_book.php') !== false)
    || (strpos($current_url, '/theme/remui_kids/teacher/practice_book.php') !== false);

$is_help_support = theme_remui_kids_sidebar_match($current_script, [
    'help_support.php',
]) || (strpos($current_url, '/theme/remui_kids/teacher/help_support.php') !== false);

$is_training_library = theme_remui_kids_sidebar_match($current_script, [
    'training_library.php',
]) || (strpos($current_url, '/theme/remui_kids/teacher/training_library.php') !== false);

$is_editing_teacher_sidebar = false;
if (isloggedin() && !isguestuser() && !is_siteadmin($USER)) {
    // Check if user has editingteacher archetype role assignment in any visible course
    $sql = "SELECT ra.id
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {course} c ON c.id = ctx.instanceid
            WHERE ra.userid = :userid 
            AND r.archetype = 'editingteacher'
            AND ctx.contextlevel = 50
            AND c.id != 1
            AND c.visible = 1";
    $is_editing_teacher_sidebar = $DB->record_exists_sql($sql, ['userid' => $USER->id]);
}

?>

<!-- Mobile Sidebar Toggle Button -->
<button class="sidebar-toggle" onclick="toggleTeacherSidebar()">
    <i class="fa fa-bars"></i>
</button>

<!-- Teacher Sidebar Navigation -->
<div class="teacher-sidebar <?php echo (($is_editing_teacher_sidebar && $current_script === 'view_course.php') || $is_ebooks) ? 'has-filters' : ''; ?>"
    id="teacherSidebar">
    <div class="sidebar-content">
        <?php if ($is_editing_teacher_sidebar || $is_ebooks): ?>
            <?php if ($current_script === 'view_course.php'): ?>
                <!-- Sidebar Filters Container -->
                <div class="sidebar-filters-container">
                    <div class="resources-sidebar-header">
                        <h3 class="resources-sidebar-title">
                            <i class="fa fa-filter"></i> Filters
                        </h3>
                        <a href="#" class="clear-filters-link" onclick="resetAllFilters(); return false;">Clear all</a>
                    </div>

                    <!-- Curriculum Filter (conditional) -->
                    <?php if (isset($has_ksa_resources) && isset($has_gcc_resources) && $has_ksa_resources && $has_gcc_resources): ?>
                        <div class="filter-section curriculum-filter-section">
                            <h4 class="filter-section-title">
                                <span>Curriculum</span>
                            </h4>
                            <ul class="filter-checkbox-list" id="curriculumFilters">
                                <li class="filter-checkbox-item">
                                    <label class="filter-checkbox-label" id="label_curr_ksa">
                                        <input type="checkbox" id="sidebar_curr_ksa" class="filter-checkbox sidebar-curr-checkbox" value="ksa" <?php echo ($default_curriculum === 'ksa') ? 'checked' : ''; ?>
                                            onchange="handleSidebarCurriculumClick('ksa')">
                                        KSA
                                    </label>
                                </li>
                                <li class="filter-checkbox-item">
                                    <label class="filter-checkbox-label" id="label_curr_gcc">
                                        <input type="checkbox" id="sidebar_curr_gcc" class="filter-checkbox sidebar-curr-checkbox" value="gcc" <?php echo ($default_curriculum === 'gcc') ? 'checked' : ''; ?>
                                            onchange="handleSidebarCurriculumClick('gcc')">
                                        GCC
                                    </label>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Grade Filter -->
                    <?php if (isset($teacher_grade_numbers) && !empty($teacher_grade_numbers)): ?>
                        <div class="filter-section grade-filter-section">
                            <h4 class="filter-section-title">
                                <span>Grade</span>
                            </h4>
                            <div class="grade-pills" id="sidebarGradePills">
                                <?php foreach ($teacher_grade_numbers as $grade): ?>
                                    <span class="grade-pill" data-grade="<?php echo $grade; ?>"
                                        onclick="toggleSidebarGrade(<?php echo $grade; ?>)">G<?php echo $grade; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>


                    <!-- Category Filters (Hidden from frontend, functionality preserved) -->
                    <div class="filter-section" style="display: none;">
                        <h4 class="filter-section-title">
                            <span>Category</span>
                        </h4>
                        <ul class="filter-checkbox-list" id="categoryFilters">
                            <!-- Will be populated by JavaScript -->
                        </ul>
                    </div>

                    <!-- Sections Filter (all unique section names across courses, shown on load) -->
                    <div class="filter-section" id="sectionsFilterCheckboxSection">
                        <h4 class="filter-section-title" onclick="toggleFilterSection(this)">
                            <span>Sections</span>
                            <i class="fa fa-chevron-down"></i>
                        </h4>
                        <ul class="filter-checkbox-list" id="sectionsFilters">
                            <!-- Populated from all courses on page load -->
                        </ul>
                    </div>

                    <!-- Units Filter -->
                    <div class="filter-section" id="sidebarUnitFilterSection" style="display: none;">
                        <h4 class="filter-section-title" onclick="toggleFilterSection(this)">
                            <span>Unit & Lesson</span>
                            <i class="fa fa-chevron-down"></i>
                        </h4>
                        <div class="unit-lesson-accordion" id="sidebarUnitFilters">
                            <!-- Populated from JavaScript -->
                        </div>
                    </div>

                    <!-- Resource Type Filter -->
                    <div class="filter-section" id="sidebarResourceTypeFilterSection">
                        <h4 class="filter-section-title" onclick="toggleFilterSection(this)">
                            <span>File Type</span>
                            <i class="fa fa-chevron-down"></i>
                        </h4>
                        <ul class="filter-checkbox-list" id="sidebarResourceTypeFilters">
                            <!-- Populated from JavaScript -->
                        </ul>
                    </div>
                </div>

                <script>
                    // Mutually exclusive curriculum filter click handlers for sidebar
                    function handleSidebarCurriculumClick(curriculum) {
                        const ksaCheckbox = document.getElementById('sidebar_curr_ksa');
                        const gccCheckbox = document.getElementById('sidebar_curr_gcc');

                        if (curriculum === 'ksa') {
                            if (ksaCheckbox && ksaCheckbox.checked) {
                                if (gccCheckbox) gccCheckbox.checked = false;
                                if (typeof filterByCurriculum === 'function') {
                                    filterByCurriculum('ksa');
                                }
                            } else {
                                if (ksaCheckbox) ksaCheckbox.checked = true;
                            }
                        } else if (curriculum === 'gcc') {
                            if (gccCheckbox && gccCheckbox.checked) {
                                if (ksaCheckbox) ksaCheckbox.checked = false;
                                if (typeof filterByCurriculum === 'function') {
                                    filterByCurriculum('gcc');
                                }
                            } else {
                                if (gccCheckbox) gccCheckbox.checked = true;
                            }
                        }
                    }

                    function toggleSidebarCurriculum(curriculum) {
                        const checkbox = document.getElementById('sidebar_curr_' + curriculum);
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            handleSidebarCurriculumClick(curriculum);
                        }
                    }
                </script>
            <?php elseif ($is_ebooks): ?>
                <!-- Sidebar Filters Container for E-Books -->
                <div class="sidebar-filters-container">
                    <div class="resources-sidebar-header">
                        <h3 class="resources-sidebar-title">
                            <i class="fa fa-filter"></i> Filters
                        </h3>
                        <a href="#" class="clear-filters-link" onclick="resetAllEbookFilters(); return false;">Clear all</a>
                    </div>

                    <!-- Curriculum Filter (conditional) -->
                    <?php if (isset($show_curriculum_filter) && $show_curriculum_filter): ?>
                        <div class="filter-section curriculum-filter-section">
                            <h4 class="filter-section-title">
                                <span>Curriculum</span>
                            </h4>
                            <div class="curriculum-checkboxes-container">
                                <?php foreach ($available_regions as $reg): ?>
                                <label class="filter-checkbox-wrapper <?php echo (in_array($reg, $selected_regions)) ? 'checked' : ''; ?>" id="label_curr_<?php echo strtolower($reg); ?>">
                                    <span class="custom-checkbox">
                                        <i class="fa fa-check"></i>
                                    </span>
                                    <input type="checkbox" class="sidebar-curr-checkbox" value="<?php echo htmlspecialchars($reg); ?>" <?php echo (in_array($reg, $selected_regions)) ? 'checked' : ''; ?>
                                        onchange="handleSidebarEbookFilterChange(this)">
                                    <span class="checkbox-label"><?php echo htmlspecialchars($reg); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Grade Filter (Grid/Pills) -->
                    <?php if (isset($available_grades) && !empty($available_grades)): ?>
                        <div class="filter-section grade-filter-section">
                            <h4 class="filter-section-title">
                                <span>Grade</span>
                            </h4>
                            <div class="grade-pills" id="sidebarEbookGradePills">
                                <?php foreach ($available_grades as $grade): 
                                    // Extract the number to display "G1", "G2" etc.
                                    $grade_num = preg_replace('/\D/', '', $grade);
                                    if (!$grade_num) $grade_num = $grade;
                                ?>
                                    <span class="grade-pill ebook-grade-pill" data-grade="<?php echo htmlspecialchars($grade); ?>"
                                        onclick="toggleSidebarEbookGrade(this)">G<?php echo $grade_num; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Book Type Filter (1 column) -->
                    <?php if (isset($available_book_types) && !empty($available_book_types)): ?>
                        <div class="filter-section book-type-filter-section">
                            <h4 class="filter-section-title">
                                <span>Book Type</span>
                            </h4>
                            <div class="ebooks-booktype-list" style="display: flex; flex-direction: column; gap: 8px;">
                                <?php foreach ($available_book_types as $btype): ?>
                                    <label class="filter-checkbox-wrapper booktype-row-item">
                                        <span class="custom-checkbox">
                                            <i class="fa fa-check"></i>
                                        </span>
                                        <input type="checkbox" class="sidebar-ebook-type-checkbox" value="<?php echo htmlspecialchars($btype); ?>" onchange="handleSidebarEbookFilterChange(this)">
                                        <span class="checkbox-label"><?php echo htmlspecialchars($btype); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <script>
                    function handleSidebarEbookFilterChange(checkbox) {
                        const wrapper = checkbox.closest('.filter-checkbox-wrapper');
                        if (checkbox && wrapper) {
                            if (checkbox.checked) {
                                wrapper.classList.add('checked');
                            } else {
                                wrapper.classList.remove('checked');
                            }
                        }
                        filterEbooks();
                    }

                    function toggleSidebarEbookGrade(pill) {
                        pill.classList.toggle('on');
                        filterEbooks();
                    }

                    function resetAllEbookFilters() {
                        document.querySelectorAll('.sidebar-curr-checkbox, .sidebar-ebook-type-checkbox').forEach(cb => {
                            cb.checked = false;
                        });
                        document.querySelectorAll('.filter-checkbox-wrapper.checked').forEach(w => w.classList.remove('checked'));
                        document.querySelectorAll('.ebook-grade-pill.on').forEach(p => p.classList.remove('on'));
                        filterEbooks();
                    }

                    function filterEbooks() {
                        const selectedRegions = Array.from(document.querySelectorAll('.sidebar-curr-checkbox:checked')).map(cb => cb.value.toUpperCase());
                        
                        // Get selected grades from active pills instead of checkboxes
                        const selectedGrades = Array.from(document.querySelectorAll('.ebook-grade-pill.on')).map(pill => pill.dataset.grade);
                        
                        const selectedTypes = Array.from(document.querySelectorAll('.sidebar-ebook-type-checkbox:checked')).map(cb => cb.value);

                        const bookCards = document.querySelectorAll('.book-card-new');
                        let visibleCount = 0;

                        bookCards.forEach(card => {
                            const cardRegion = card.dataset.region || '';
                            const cardGrade = card.dataset.grade || '';
                            const cardType = card.dataset.bookType || '';

                            let regionMatch = selectedRegions.length === 0 || selectedRegions.includes(cardRegion);
                            let gradeMatch = selectedGrades.length === 0 || selectedGrades.includes(cardGrade);
                            let typeMatch = selectedTypes.length === 0 || selectedTypes.includes(cardType);

                            if (regionMatch && gradeMatch && typeMatch) {
                                card.style.display = 'flex';
                                visibleCount++;
                            } else {
                                card.style.display = 'none';
                            }
                        });

                        const noResults = document.getElementById('ebooksNoResults');
                        if (noResults) {
                            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
                        }
                        
                        const countEl = document.getElementById('ebooksTotalCount');
                        if (countEl) {
                            countEl.textContent = visibleCount;
                        }
                        
                        const booksGrid = document.getElementById('booksGrid');
                        const booksSection = document.getElementById('ebooksBooksSection');
                        if (booksGrid && visibleCount > 0) {
                            booksSection.style.display = 'block';
                            booksGrid.style.display = 'grid';
                        } else if (booksGrid && visibleCount === 0) {
                            booksGrid.style.display = 'none';
                            booksSection.style.display = 'block';
                        }
                    }
                </script>
            <?php endif; ?>
        <?php else: ?>
            <!-- DASHBOARD Section -->
            <div class="sidebar-section">
                <h3 class="sidebar-category">DASHBOARD</h3>
                <ul class="sidebar-menu">
                    <li class="sidebar-item <?php echo $is_resources ? 'active' : ''; ?>">
                        <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/teacher/view_course.php"
                            class="sidebar-link">
                            <i class="fa fa-th-large sidebar-icon"></i>
                            <span class="sidebar-text">Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- E-BOOKS Section -->
            <div class="sidebar-section">
                <h3 class="sidebar-category">E-BOOKS</h3>
                <ul class="sidebar-menu">
                    <li class="sidebar-item <?php echo $is_ebooks ? 'active' : ''; ?>">
                        <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/teacher/ebooks.php" class="sidebar-link">
                            <i class="fa fa-book sidebar-icon"></i>
                            <span class="sidebar-text">E-Books</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- SUPPORT Section -->
            <div class="sidebar-section">
                <h3 class="sidebar-category">SUPPORT</h3>
                <ul class="sidebar-menu">
                    <li class="sidebar-item <?php echo $is_training_library ? 'active' : ''; ?>">
                        <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/teacher/training_library.php"
                            class="sidebar-link">
                            <i class="fa fa-graduation-cap sidebar-icon"></i>
                            <span class="sidebar-text">Training Library</span>
                        </a>
                    </li>
                    <li class="sidebar-item <?php echo $is_help_support ? 'active' : ''; ?>">
                        <a href="<?php echo $CFG->wwwroot; ?>/theme/remui_kids/teacher/help_support.php"
                            class="sidebar-link">
                            <i class="fa fa-life-ring sidebar-icon"></i>
                            <span class="sidebar-text">Help &amp; Support</span>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
    .teacher-quick-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .teacher-quick-action {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 12px;
        text-decoration: none;
        border: none;
        color: #ffffff;
    }

    .teacher-quick-action .action-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 16px;
    }

    .teacher-quick-action .action-copy {
        flex: 1;
    }

    .teacher-quick-action .action-title {
        font-weight: 600;
        font-size: 14px;
        color: #ffffff;
    }

    .teacher-quick-action .action-arrow {
        color: rgba(255, 255, 255, 0.95);
        font-size: 12px;
    }
</style>

<script>
    // Teacher Sidebar JavaScript
    function toggleTeacherSidebar() {
        const sidebar = document.getElementById('teacherSidebar');
        if (sidebar) {
            sidebar.classList.toggle('sidebar-open');
        }
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function (event) {
        const sidebar = document.getElementById('teacherSidebar');
        const toggleButton = document.querySelector('.sidebar-toggle');

        if (sidebar && toggleButton && window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !toggleButton.contains(event.target)) {
                sidebar.classList.remove('sidebar-open');
            }
        }
    });

    // Handle window resize
    window.addEventListener('resize', function () {
        const sidebar = document.getElementById('teacherSidebar');
        if (sidebar && window.innerWidth > 768) {
            sidebar.classList.remove('sidebar-open');
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const mainContent = document.querySelector('.teacher-main-content');
        if (!mainContent || mainContent.dataset.layout === 'custom') {
            return;
        }

        if (mainContent.querySelector('.teacher-standard-shell')) {
            return;
        }

        const shell = document.createElement('div');
        shell.className = 'teacher-standard-shell';
        if (mainContent.dataset.shell === 'wide') {
            shell.classList.add('wide');
        }

        const children = Array.from(mainContent.childNodes);
        children.forEach(child => {
            if (child.nodeType === Node.ELEMENT_NODE || child.nodeType === Node.TEXT_NODE) {
                shell.appendChild(child);
            }
        });

        mainContent.appendChild(shell);
    });
</script>