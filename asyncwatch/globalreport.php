<?php
/**
 * AsyncWatch Cross-course Rules progress report.
 *
 * Parallel to report.php, but spans multiple courses per rule rather than
 * being scoped to one course — lives outside any course context, same as
 * globalrules.php.
 *
 * URL: /local/asyncwatch/globalreport.php[?ruleid=X][&userid=X][&filterstatus=X]
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

use local_asyncwatch\helper;

admin_externalpage_setup('local_asyncwatch_globalreport');

// ── Parameters ────────────────────────────────────────────────────────────────
$filter_ruleid   = optional_param('ruleid',         0,  PARAM_INT);
$filter_userid   = optional_param('userid',         0,  PARAM_INT);
$filter_status   = optional_param('filterstatus',   '', PARAM_ALPHA);
$filter_override = optional_param('filteroverride', '', PARAM_ALPHA);
$download        = optional_param('download',       '', PARAM_ALPHA);

$now       = time();
$all_rules = helper::get_global_rules();

// ── Build rows across every rule (bulk-loaded per rule, same pattern as
//    check_progress.php uses for per-course rules) ─────────────────────────
$all_rows        = [];
$all_users_index = []; // [userid => user] — for the "filter by learner" dropdown

foreach ($all_rules as $rule) {
    $ruleid    = (int)$rule->id;
    $courseids = helper::get_global_rule_courseids($ruleid);
    if (empty($courseids)) continue;

    $users = helper::get_global_rule_users($ruleid);
    if (empty($users)) continue;

    $coursenames = [];
    foreach ($courseids as $cid) {
        $c = get_course($cid);
        $coursenames[] = format_string($c->fullname);
    }

    $userids  = array_keys($users);
    $progress = helper::bulk_get_global_rule_progress($ruleid, $userids);

    // Bulk-load cohort overrides + membership for this rule (avoids one
    // query per user).
    $overrides = helper::get_global_rule_overrides($ruleid);
    $overrides_by_cohort = [];
    foreach ($overrides as $ov) {
        $overrides_by_cohort[(int)$ov->cohortid] = $ov;
    }
    $user_cohortids = []; // [userid => [cohortid, ...]]
    if (!empty($overrides_by_cohort)) {
        list($in_sql1, $params1) = $DB->get_in_or_equal(array_keys($overrides_by_cohort));
        list($in_sql2, $params2) = $DB->get_in_or_equal($userids);
        $rows = $DB->get_records_sql(
            "SELECT id, userid, cohortid FROM {cohort_members} WHERE cohortid $in_sql1 AND userid $in_sql2",
            array_merge($params1, $params2)
        );
        foreach ($rows as $r) {
            $user_cohortids[(int)$r->userid][] = (int)$r->cohortid;
        }
    }

    foreach ($users as $uid => $user) {
        $all_users_index[$uid] = $user;

        $prog  = $progress[$uid] ?? ['completed' => 0, 'total' => 0];
        $done  = $prog['completed'];
        $total = $prog['total'];

        // Effective deadline: best (latest) cohort override this user is in.
        $best = null;
        foreach ($user_cohortids[$uid] ?? [] as $cid) {
            if (!isset($overrides_by_cohort[$cid])) continue;
            $ov = $overrides_by_cohort[$cid];
            if ($best === null || (int)$ov->deadline > (int)$best->deadline) {
                $best = $ov;
            }
        }
        $eff_deadline = $best ? (int)$best->deadline   : (int)$rule->deadline;
        $eff_warn     = $best ? (int)$best->warn_hours : (int)$rule->warn_hours;

        $status = helper::status_for_progress($rule, $done, $now, $eff_deadline, $eff_warn);

        $all_rows[] = (object)[
            'rule'         => $rule,
            'user'         => $user,
            'done'         => $done,
            'total'        => $total,
            'status'       => $status,
            'lastaccess'   => $user->lastaccess,
            'eff_deadline' => $eff_deadline,
            'has_override' => $best !== null,
            'coursenames'  => $coursenames,
        ];
    }
}

// ── Filter ────────────────────────────────────────────────────────────────────
$rows = array_filter($all_rows, function($r) use ($filter_ruleid, $filter_userid, $filter_status, $filter_override) {
    if ($filter_ruleid && (int)$r->rule->id !== $filter_ruleid) return false;
    if ($filter_userid && (int)$r->user->id !== $filter_userid) return false;
    if ($filter_status && $r->status !== $filter_status) return false;
    if ($filter_override === 'override' && !$r->has_override) return false;
    if ($filter_override === 'default'  &&  $r->has_override) return false;
    return true;
});

// ── CSV export ────────────────────────────────────────────────────────────────
if ($download === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="asyncwatch_globalreport_' . date('Ymd') . '.csv"');
    echo helper::global_rows_to_csv($rows);
    exit;
}

// ── Page setup ────────────────────────────────────────────────────────────────
$pageurl = new moodle_url('/local/asyncwatch/globalreport.php');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('globalreport', 'local_asyncwatch'));

$tabs = [
    new tabobject('globalrules', new moodle_url('/local/asyncwatch/globalrules.php'), get_string('globalrules', 'local_asyncwatch')),
    new tabobject('globalreport', new moodle_url('/local/asyncwatch/globalreport.php'), get_string('globalreport', 'local_asyncwatch')),
];
echo $OUTPUT->tabtree($tabs, 'globalreport');

if (empty($all_rules)) {
    echo $OUTPUT->notification(get_string('noglobalrules', 'local_asyncwatch'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// ── Filter form ───────────────────────────────────────────────────────────────
echo '<form method="get" action="' . $pageurl->out(false) . '" class="mb-3">';
echo '<div class="d-flex flex-wrap align-items-end" style="gap:0.75rem;">';

echo '<div class="mr-2 mb-2"><label class="d-block small font-weight-bold mb-1" for="filter_ruleid">' . get_string('filter_rule', 'local_asyncwatch') . '</label>';
echo '<select name="ruleid" id="filter_ruleid" class="custom-select me-2 mb-2">';
echo '<option value="0">' . get_string('all_rules', 'local_asyncwatch') . '</option>';
foreach ($all_rules as $rule) {
    $sel = ($filter_ruleid === (int)$rule->id) ? ' selected' : '';
    echo '<option value="' . (int)$rule->id . '"' . $sel . '>' . s(format_string($rule->name)) . '</option>';
}
echo '</select></div>';

echo '<div class="mr-2 mb-2"><label class="d-block small font-weight-bold mb-1" for="filter_userid">' . get_string('filter_user', 'local_asyncwatch') . '</label>';
echo '<select name="userid" id="filter_userid" class="custom-select me-2 mb-2">';
echo '<option value="0">' . get_string('all_users', 'local_asyncwatch') . '</option>';
foreach ($all_users_index as $user) {
    $sel = ($filter_userid === (int)$user->id) ? ' selected' : '';
    echo '<option value="' . (int)$user->id . '"' . $sel . '>' . s(fullname($user)) . '</option>';
}
echo '</select></div>';

echo '<div class="mr-2 mb-2"><label class="d-block small font-weight-bold mb-1" for="filter_status">' . get_string('filter_status', 'local_asyncwatch') . '</label>';
echo '<select name="filterstatus" id="filter_status" class="custom-select me-2 mb-2">';
foreach ([
    ''          => get_string('filter_status_all',       'local_asyncwatch'),
    'completed' => get_string('filter_status_completed', 'local_asyncwatch'),
    'ok'        => get_string('filter_status_ok',        'local_asyncwatch'),
    'warning'   => get_string('filter_status_warning',   'local_asyncwatch'),
    'breach'    => get_string('filter_status_breach',    'local_asyncwatch'),
] as $val => $label) {
    $sel = ($filter_status === $val) ? ' selected' : '';
    echo '<option value="' . s($val) . '"' . $sel . '>' . s($label) . '</option>';
}
echo '</select></div>';

echo '<div class="mr-2 mb-2"><label class="d-block small font-weight-bold mb-1" for="filter_override">' . get_string('filter_override', 'local_asyncwatch') . '</label>';
echo '<select name="filteroverride" id="filter_override" class="custom-select me-2 mb-2">';
foreach ([
    ''         => get_string('filter_override_all',     'local_asyncwatch'),
    'override' => get_string('filter_override_active',  'local_asyncwatch'),
    'default'  => get_string('filter_override_default', 'local_asyncwatch'),
] as $val => $label) {
    $sel = ($filter_override === $val) ? ' selected' : '';
    echo '<option value="' . s($val) . '"' . $sel . '>' . s($label) . '</option>';
}
echo '</select></div>';

$csv_params = ['download' => 'csv'];
if ($filter_ruleid)   $csv_params['ruleid']         = $filter_ruleid;
if ($filter_userid)   $csv_params['userid']         = $filter_userid;
if ($filter_status)   $csv_params['filterstatus']   = $filter_status;
if ($filter_override) $csv_params['filteroverride'] = $filter_override;
$csv_url = new moodle_url($pageurl, $csv_params);

echo '<div class="mb-2">';
echo '<button type="submit" class="btn btn-primary me-2 mb-2">' . get_string('applyfilter', 'local_asyncwatch') . '</button>';
echo html_writer::link($csv_url, get_string('exportcsv', 'local_asyncwatch'), ['class' => 'btn btn-secondary me-2 mb-2']);
if ($filter_ruleid || $filter_userid || $filter_status || $filter_override) {
    echo ' <a href="' . $pageurl->out(false) . '" class="btn btn-link btn-sm">' . get_string('clearfilter', 'local_asyncwatch') . '</a>';
}
echo '</div>';

echo '</div>';
echo '</form>';

// ── Summary dashboard ────────────────────────────────────────────────────────
// Card counts respect every active filter EXCEPT status (and override) —
// otherwise clicking a status pill would zero out the others. Same fix as
// the per-course report.
$rows_for_summary = array_filter($all_rows, function($r) use ($filter_ruleid, $filter_userid) {
    if ($filter_ruleid && (int)$r->rule->id !== $filter_ruleid) return false;
    if ($filter_userid && (int)$r->user->id !== $filter_userid) return false;
    return true;
});

if (!empty($rows_for_summary)) {
    $rule_stats = []; // [ruleid => ['rule'=>,'coursenames'=>,'completed'=>,'ok'=>,'warning'=>,'breach'=>,'total'=>,'overrides'=>]]
    foreach ($rows_for_summary as $row) {
        $rid = (int)$row->rule->id;
        if (!isset($rule_stats[$rid])) {
            $rule_stats[$rid] = [
                'rule'        => $row->rule,
                'coursenames' => $row->coursenames,
                'completed'   => 0, 'ok' => 0, 'warning' => 0, 'breach' => 0,
                'total'       => 0, 'overrides' => 0,
            ];
        }
        $rule_stats[$rid][$row->status]++;
        $rule_stats[$rid]['total']++;
        if ($row->has_override) $rule_stats[$rid]['overrides']++;
    }

    $status_colors = [
        'completed' => '#28a745',
        'ok'        => '#17a2b8',
        'warning'   => '#ffc107',
        'breach'    => '#dc3545',
    ];
    $status_labels = [
        'completed' => get_string('status_completed', 'local_asyncwatch'),
        'ok'        => get_string('status_ok',        'local_asyncwatch'),
        'warning'   => get_string('status_warning',   'local_asyncwatch'),
        'breach'    => get_string('status_breach',    'local_asyncwatch'),
    ];

    echo '<div class="aw-summary-dashboard mb-4">';
    echo '<style>
.aw-rule-card { transition: box-shadow 0.15s ease, border-color 0.15s ease; cursor: pointer; }
.aw-rule-card:hover { border-color: #0D3C6F; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.aw-pill-badge { transition: filter 0.15s ease; }
.aw-pill-link:hover .aw-pill-badge { filter: brightness(0.85); }
</style>';
    echo '<div class="d-flex align-items-center justify-content-between mb-2">';
    echo '<span class="font-weight-bold text-muted small" style="text-transform:uppercase;letter-spacing:0.05em;">Rule Summary</span>';
    echo '<button type="button" id="aw-summary-toggle" class="btn btn-sm btn-link p-0" style="font-size:0.85em;">Hide summary</button>';
    echo '</div>';
    echo '<div id="aw-summary-cards">';
    echo '<div class="d-flex flex-wrap" style="gap:1rem;">';

    foreach ($rule_stats as $rid => $st) {
        $rule  = $st['rule'];
        $total = $st['total'];
        $base_url = new moodle_url($pageurl, ['ruleid' => $rid]);

        // Toggle: if already scoped to just this rule (no other filters),
        // clicking again clears back to "all rules" instead of a no-op.
        $already_just_this_rule = ($filter_ruleid === $rid)
            && $filter_status === '' && $filter_userid === 0 && $filter_override === '';
        $card_target = $already_just_this_rule ? new moodle_url($pageurl) : $base_url;
        $card_label  = $already_just_this_rule
            ? get_string('card_show_all_rules', 'local_asyncwatch')
            : format_string($rule->name);

        echo '<div class="card aw-rule-card" style="min-width:220px;flex:1 1 220px;max-width:340px;position:relative;">';
        echo '<a href="' . s($card_target->out(false)) . '" aria-label="' . s($card_label) . '"'
           . ' title="' . s($card_label) . '"'
           . ' style="position:absolute;top:0;left:0;right:0;bottom:0;z-index:1;"></a>';
        echo '<div class="card-header py-2 px-3" style="background:#f8f9fa;">';
        echo '<div class="font-weight-bold" style="font-size:0.9em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' . s(format_string($rule->name)) . '">';
        echo s(format_string($rule->name));
        if (!$rule->enabled) {
            echo ' <span class="badge badge-secondary bg-secondary" style="font-size:0.75em;">' . get_string('rule_disabled_badge', 'local_asyncwatch') . '</span>';
        }
        echo '</div>';
        echo '<div class="small text-muted mt-1" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' . s(implode(', ', $st['coursenames'])) . '">'
           . s(implode(', ', $st['coursenames'])) . '</div>';
        echo '<div class="d-flex align-items-center mt-1" style="gap:0.4rem;">';
        echo '<span class="text-muted" style="font-size:0.78em;">' . $total . ' ' . ($total === 1 ? get_string('learner', 'local_asyncwatch') : get_string('learners', 'local_asyncwatch')) . '</span>';
        if ($st['overrides'] > 0) {
            $ov_link = (new moodle_url($base_url, ['filteroverride' => 'override']))->out(false);
            echo '<a href="' . s($ov_link) . '" style="text-decoration:none;position:relative;z-index:2;">';
            echo '<span class="badge badge-warning bg-warning" style="font-size:0.72em;font-weight:600;">' . $st['overrides'] . ' override' . ($st['overrides'] === 1 ? '' : 's') . ' active</span>';
            echo '</a>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="card-body p-2">';

        // Stacked progress bar.
        echo '<div style="display:flex;height:10px;border-radius:4px;overflow:hidden;margin-bottom:0.5rem;">';
        foreach ($status_colors as $skey => $color) {
            $count = $st[$skey];
            if ($count === 0 || $total === 0) continue;
            $pct  = round(100 * $count / $total, 2);
            $link = (new moodle_url($base_url, ['filterstatus' => $skey]))->out(false);
            echo '<a href="' . s($link) . '" title="' . s($status_labels[$skey] . ': ' . $count) . '"'
               . ' style="display:block;width:' . $pct . '%;background:' . $color . ';height:100%;position:relative;z-index:2;"></a>';
        }
        echo '</div>';

        // Count pills.
        echo '<div class="d-flex flex-wrap" style="gap:0.3rem;">';
        foreach ($status_colors as $skey => $color) {
            $count = $st[$skey];
            $link  = (new moodle_url($base_url, ['filterstatus' => $skey]))->out(false);
            $opacity = ($filter_status !== '' && $filter_status !== $skey) ? 'opacity:0.35;' : '';
            echo '<a href="' . s($link) . '" class="aw-pill-link" style="text-decoration:none;position:relative;z-index:2;' . $opacity . '">';
            echo '<span class="aw-pill-badge" style="display:inline-flex;align-items:center;background:' . $color . ';color:' . ($skey === 'warning' ? '#333' : '#fff') . ';border-radius:3px;padding:1px 6px;font-size:0.75em;font-weight:600;white-space:nowrap;">';
            echo s($count) . ' ' . s($status_labels[$skey]);
            echo '</span></a>';
        }
        echo '</div>';

        echo '</div></div>'; // card-body / card
    }

    echo '</div></div></div>'; // d-flex / aw-summary-cards / aw-summary-dashboard
    echo '<script>
(function() {
    var btn   = document.getElementById("aw-summary-toggle");
    var cards = document.getElementById("aw-summary-cards");
    if (!btn || !cards) return;
    btn.addEventListener("click", function() {
        if (cards.style.display === "none") {
            cards.style.display = "";
            btn.textContent = "Hide summary";
        } else {
            cards.style.display = "none";
            btn.textContent = "Show summary";
        }
    });
})();
</script>';
}

// ── Table ─────────────────────────────────────────────────────────────────────
if (empty($rows)) {
    echo $OUTPUT->notification(get_string('noresults', 'local_asyncwatch'), 'info');
} else {
    $table = new flexible_table('asyncwatch-globalreport');
    $table->define_columns(['rule', 'learner', 'courses', 'progress', 'status', 'lastaccess']);
    $table->define_headers([
        get_string('rulename',       'local_asyncwatch'),
        get_string('learner',        'local_asyncwatch'),
        get_string('globalrule_col_courses', 'local_asyncwatch'),
        get_string('parts_complete', 'local_asyncwatch'),
        get_string('status',         'local_asyncwatch'),
        get_string('last_activity',  'local_asyncwatch'),
    ]);
    $table->define_baseurl($pageurl->out(false));
    $table->sortable(false);
    $table->set_attribute('class', 'generaltable');
    $table->setup();

    $status_cfg = [
        'completed' => ['class' => 'badge badge-success bg-success text-white', 'style' => ''],
        'ok'        => ['class' => 'badge badge-info bg-info text-white',       'style' => ''],
        'warning'   => ['class' => 'badge badge-warning bg-warning',            'style' => 'color:#333;'],
        'breach'    => ['class' => 'badge badge-danger bg-danger text-white',   'style' => ''],
    ];
    $datefmt = get_string('aw_datetimefmt', 'local_asyncwatch');

    foreach ($rows as $row) {
        $sc    = $status_cfg[$row->status];
        $badge = html_writer::tag('span', get_string('status_' . $row->status, 'local_asyncwatch'),
            ['class' => $sc['class'], 'style' => $sc['style']]);

        $rule_cell = format_string($row->rule->name);
        if (!$row->rule->enabled) {
            $rule_cell .= ' ' . html_writer::tag('span', get_string('rule_disabled_badge', 'local_asyncwatch'),
                ['class' => 'badge badge-secondary bg-secondary text-white ml-1', 'style' => 'font-size:0.95em;']);
        }
        if ($row->has_override) {
            $rule_cell .= ' ' . html_writer::tag('span', userdate($row->eff_deadline, $datefmt),
                ['class' => 'badge badge-warning bg-warning ml-1', 'style' => 'font-size:0.85em;color:#333;',
                 'title' => get_string('override_active_cohort', 'local_asyncwatch')]);
        }

        $table->add_data([
            $rule_cell,
            html_writer::link(new moodle_url('/user/profile.php', ['id' => $row->user->id]), s(fullname($row->user))),
            implode(', ', $row->coursenames),
            $row->done . ' / ' . $row->total,
            $badge,
            $row->lastaccess ? userdate($row->lastaccess, $datefmt) : html_writer::tag('em', get_string('never', 'moodle')),
        ]);
    }

    $table->finish_output();
}

echo $OUTPUT->footer();