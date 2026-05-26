<?php
/**
 * Inline layout CSS for teacher pages — breaks out of Moodle/RemUI containers (same as ebooks.php).
 *
 * @package   theme_remui_kids
 */

defined('MOODLE_INTERNAL') || die();
?>
<style>
/* Hide duplicate Moodle page header */
#page-header,
#page-header-content,
.region-main-settings-menu,
.page-header-headings,
.page-context-header {
    display: none !important;
}

body.teacher-support-body {
    overflow-x: hidden !important;
    width: 100% !important;
    max-width: 100% !important;
}

/* Full-width Moodle / RemUI shell */
body.teacher-support-body #page-wrapper,
body.teacher-support-body #page,
body.teacher-support-body #page.drawers,
body.teacher-support-body .page,
body.teacher-support-body #page-content,
body.teacher-support-body .main-inner,
body.teacher-support-body #topofscroll,
body.teacher-support-body #region-main,
body.teacher-support-body #region-main-box,
body.teacher-support-body #maincontent,
body.teacher-support-body .region-main-content,
body.teacher-support-body .drawers,
body.teacher-support-body [role="main"] {
    width: 100% !important;
    max-width: none !important;
    padding: 0 !important;
    margin: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    border: 0 !important;
    overflow: visible !important;
}

body.teacher-support-body #page.drawers .main-inner {
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

body.teacher-support-body .container-fluid,
body.teacher-support-body .container-fluid > .row,
body.teacher-support-body .container-fluid > .row > .col-12 {
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* Teacher sidebar — grey links (not theme hyperlink blue) */
.teacher-sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 260px !important;
    height: 100vh !important;
    background: #fff !important;
    border-right: 1px solid #e9ecef !important;
    z-index: 1000 !important;
    overflow-y: auto !important;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05) !important;
}

.teacher-sidebar .sidebar-content {
    padding: 7rem 0 2rem !important;
}

.teacher-sidebar .sidebar-category {
    font-size: 0.7rem !important;
    font-weight: 700 !important;
    color: #6c757d !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    margin-bottom: 0.8rem !important;
    padding: 0 1.5rem !important;
}

.teacher-sidebar .sidebar-menu {
    list-style: none !important;
    padding: 0 !important;
    margin: 0 !important;
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
    color: inherit !important;
}

.teacher-sidebar .sidebar-text {
    font-weight: 500 !important;
    font-size: 0.9rem !important;
    color: inherit !important;
}

.sidebar-toggle {
    display: none !important;
    position: fixed !important;
    top: 15px !important;
    left: 15px !important;
    z-index: 1001 !important;
    background: #4361ee !important;
    color: #fff !important;
    border: none !important;
    padding: 10px 15px !important;
    border-radius: 5px !important;
    cursor: pointer !important;
}

/* Main content — full width beside sidebar (ebooks.php) */
.main-content {
    margin-left: 260px !important;
    margin-top: 0 !important;
    padding: 20px 50px 30px 30px !important;
    min-height: 100vh !important;
    width: calc(100vw - 260px) !important;
    max-width: none !important;
    box-sizing: border-box !important;
    background: #f8f9fa !important;
}

.main-content > *,
.teacher-page-header,
.teacher-page-panel,
.teacher-page-panel-inner,
.training-library-container {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

@media (max-width: 768px) {
    .sidebar-toggle {
        display: block !important;
    }

    .teacher-sidebar {
        transform: translateX(-100%) !important;
        transition: transform 0.3s ease !important;
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
    }
}

/* Page header — E-Books style */
.teacher-page-header {
    background: #fff;
    padding: 15px 0;
    margin: 0 0 30px !important;
    border-bottom: 1px solid #e9ecef;
    width: 100% !important;
}

.teacher-page-header .page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e40af;
    margin: 0 0 6px;
}

.teacher-page-header .page-subtitle {
    font-size: 1rem;
    color: #6b7280;
    margin: 0;
}

.training-video-count-badge {
    font-size: 0.9rem;
    font-weight: 500;
    color: #6b7280;
}

/* Content panel */
.teacher-page-panel {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
    overflow: hidden;
    width: 100% !important;
}

.teacher-page-panel-inner {
    padding: 24px;
}

#helpSupportPage {
    display: flex;
    flex-direction: column;
}

.help-support-page .help-sidebar {
    position: static;
    border-bottom: 2px solid #e9ecef;
    background: #f8f9fa;
}

.help-support-page .help-main-content {
    min-height: 400px;
    padding: 24px;
}

.help-support-page .help-nav-btn {
    color: #6b7280;
}

.help-support-page .help-nav-btn.active {
    color: #4361ee;
    border-bottom-color: #4361ee;
}

.training-library-search {
    margin-bottom: 20px;
}

.training-library-search .help-search-container {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    display: flex;
    align-items: center;
    position: relative;
}

.training-library-search .help-search-input {
    width: 100%;
    padding: 12px 40px;
    border: none;
    background: transparent;
}

.training-library-container .category-section {
    background: #f8fafc;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 16px;
}

.training-library-container .category-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 16px;
}

.training-library-container .category-count {
    background: #4361ee;
    color: #fff;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
}

.training-library-container .videos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.training-library-container .video-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 16px;
    cursor: pointer;
}

.training-library-container .video-card:hover {
    border-color: #4361ee;
    box-shadow: 0 4px 12px rgba(67, 97, 238, 0.12);
}

.training-library-container .video-card.hidden,
.training-library-container .category-section.hidden {
    display: none;
}

.training-library-container .video-card-header {
    display: flex;
    gap: 12px;
}

.training-library-container .video-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: linear-gradient(135deg, #4361ee, #667eea);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.training-library-container .video-title {
    margin: 0 0 6px;
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
}

.training-library-container .video-description {
    margin: 0;
    font-size: 0.875rem;
    color: #6b7280;
}

.training-library-container .video-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 0.8rem;
    color: #9ca3af;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e9ecef;
}

.training-library-container .no-videos {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

.training-library-container .no-videos i {
    font-size: 48px;
    color: #cbd5e1;
    display: block;
    margin-bottom: 12px;
}

#trainingVideoModal.video-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.9);
    z-index: 10050;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

#trainingVideoModal.video-modal.active {
    display: flex;
}

#trainingVideoModal .video-modal-content {
    background: #1a1a1a;
    border-radius: 12px;
    max-width: 1100px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
}

#trainingVideoModal .video-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: #2a2a2a;
    color: #fff;
}

#trainingVideoModal .video-modal-close {
    background: transparent;
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
}

#trainingVideoModal .video-player-wrapper video,
#trainingVideoModal .video-player-wrapper iframe {
    width: 100%;
    min-height: 450px;
    display: block;
    border: 0;
}
</style>
