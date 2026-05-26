<?php
/**
 * Teacher access gate for theme teacher pages.
 *
 * @package   theme_remui_kids
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Require a logged-in teacher (or site admin).
 *
 * @return void
 */
function theme_remui_kids_require_teacher(): void {
    global $CFG, $DB, $USER;

    if (!isloggedin() || isguestuser()) {
        redirect(get_login_url());
    }

    if (is_siteadmin()) {
        return;
    }

    $teacherroles = $DB->get_records_select('role', "shortname IN ('editingteacher','teacher','manager')");
    $roleids = array_keys($teacherroles);
    if (empty($roleids)) {
        throw new moodle_exception('accessdenied', 'admin');
    }

    list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'r');
    $params['userid'] = $USER->id;
    $params['ctxlevel'] = CONTEXT_COURSE;

    $hasteacherrole = $DB->record_exists_sql(
        "SELECT 1
           FROM {role_assignments} ra
           JOIN {context} ctx ON ra.contextid = ctx.id
          WHERE ra.userid = :userid
            AND ctx.contextlevel = :ctxlevel
            AND ra.roleid {$insql}",
        $params
    );

    if (!$hasteacherrole) {
        throw new moodle_exception('accessdenied', 'admin');
    }
}
