<?php
/**
 * AsyncWatch progress report.
 *
 * Uses Moodle's flexible_table for sortable columns without page reload.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/tablelib.php');

use local_asyncwatch\helper;

// ── Parameters ────────────────────────────────────────────────────────────────
$courseid         = required_param('courseid', PARAM_INT);
$filter_ruleid    = optional_param('ruleid',         0,  PARAM_INT);
$filter_userid    = optional_param('userid',         0,  PARAM_INT);
$filter_status    = optional_param('filterstatus',   '', PARAM_ALPHA);
$filter_rulesetid = optional_param('rulesetid',      0,  PARAM_INT);
$filter_groupid   = optional_param('filtergroupid',  0,  PARAM_INT);
$filter_override  = optional_param('filteroverride', '', PARAM_ALPHA);
$download         = optional_param('download',       '', PARAM_ALPHA);

// ── Auth / context ────────────────────────────────────────────────────────────
$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/asyncwatch:viewreport', $context);

// ── Data ──────────────────────────────────────────────────────────────────────
$parts     = helper::get_parts($courseid);
$now       = time();
$all_rules = helper::get_rules($courseid);
$students  = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email, u.lastaccess');

function aw_row_status(\stdClass $rule, int $done, int $now, int $eff_deadline, int $eff_warn): string {
    if ($done >= $rule->parts_required) return 'completed';
    if ($now >= $eff_deadline) return 'breach';
    if ($eff_warn > 0 && $now >= ($eff_deadline - ($eff_warn * MINSECS))) return 'warning';
    return 'ok';
}

$user_progress = [];
foreach ($students as $user) {
    $user_progress[$user->id] = helper::get_user_progress($courseid, (int)$user->id);
}

// ── Pre-load rule set memberships + group memberships for filtering ────────────
// Mirrors the cron task's logic so the report only shows users against rules
// that actually apply to them (set-assigned rules → group members only;
// global rules → all enrolled users).
$rule_to_rulesetid  = []; // [ruleid => rulesetid|0]
$ruleset_to_groupids = []; // [rulesetid => [groupid, ...]]
foreach ($all_rules as $rule) {
    $srec = $DB->get_record('asyncwatch_ruleset_rules', ['ruleid' => $rule->id], 'rulesetid');
    $rule_to_rulesetid[$rule->id] = $srec ? (int)$srec->rulesetid : 0;
}
foreach (array_unique(array_filter(array_values($rule_to_rulesetid))) as $rsid) {
    $ruleset_to_groupids[$rsid] = helper::get_ruleset_groupids($rsid);
}

// Pre-load all user group memberships in one query.
$all_user_ids = array_keys($students);
$user_groupids = [];
if (!empty($all_user_ids)) {
    list($in_sql, $in_params) = $DB->get_in_or_equal($all_user_ids);
    $in_params[] = $courseid;
    $gm_rows = $DB->get_records_sql(
        "SELECT gm.userid, gm.groupid
           FROM {groups_members} gm
           JOIN {groups} g ON g.id = gm.groupid
          WHERE gm.userid $in_sql AND g.courseid = ?",
        $in_params
    );
    foreach ($gm_rows as $gm) {
        $user_groupids[(int)$gm->userid][] = (int)$gm->groupid;
    }
}

$all_rows = [];
foreach ($all_rules as $rule) {
    $rulesetid     = $rule_to_rulesetid[$rule->id];
    $set_groupids  = $rulesetid ? ($ruleset_to_groupids[$rulesetid] ?? []) : [];

    foreach ($students as $user) {
        $uid        = (int)$user->id;
        $ugroups    = $user_groupids[$uid] ?? [];

        // Apply the same filtering the cron uses:
        // set-assigned rules only apply to users in the set's groups.
        if ($rulesetid && !empty($set_groupids) && !array_intersect($ugroups, $set_groupids)) {
            continue;
        }

        $prog   = $user_progress[$uid];
        $done   = $prog['completed'];
        $eff    = helper::get_effective_deadline($rule, $uid, $courseid);
        $status = aw_row_status($rule, $done, $now, $eff['deadline'], $eff['warn_hours']);

        $all_rows[] = (object)[
            'rule'          => $rule,
            'user'          => $user,
            'done'          => $done,
            'total'         => $prog['total'],
            'status'        => $status,
            'parts'         => $prog['parts'],
            'lastaccess'    => $user->lastaccess,
            'eff_deadline'  => $eff['deadline'],
            'has_override'  => $eff['override'] !== null,
            'override_warn' => $eff['override'] ? (int)$eff['override']->warn_hours : null,
            'groupids'      => $ugroups,
        ];
    }
}

// ── Filter ────────────────────────────────────────────────────────────────────
$rule_to_rulesets = $rule_to_rulesetid; // reuse for filter block below

$rows = array_filter($all_rows, function($r) use (
    $filter_ruleid, $filter_userid, $filter_status,
    $filter_rulesetid, $filter_override, $filter_groupid, $rule_to_rulesets
) {
    if ($filter_ruleid    && (int)$r->rule->id !== $filter_ruleid) return false;
    if ($filter_userid    && (int)$r->user->id !== $filter_userid) return false;
    if ($filter_status    && $r->status        !== $filter_status) return false;
    if ($filter_rulesetid) {
        if (($rule_to_rulesets[$r->rule->id] ?? 0) !== $filter_rulesetid) return false;
    }
    if ($filter_override === 'override' && !$r->has_override) return false;
    if ($filter_override === 'default'  &&  $r->has_override) return false;
    if ($filter_groupid && !in_array((string)$filter_groupid, array_map('strval', $r->groupids ?? []))) return false;
    return true;
});

// ── CSV export ────────────────────────────────────────────────────────────────
if ($download === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="asyncwatch_report_' . $courseid . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');

    $header = [
        get_string('rulename',       'local_asyncwatch'),
        get_string('learner',        'local_asyncwatch'),
        'Email',
        get_string('parts_complete', 'local_asyncwatch'),
        get_string('status',         'local_asyncwatch'),
        get_string('last_activity',  'local_asyncwatch'),
    ];
    foreach ($parts as $part) {
        $header[] = format_string($part->name);
    }
    fputcsv($out, $header);

    foreach ($rows as $row) {
        $line = [
            format_string($row->rule->name),
            fullname($row->user),
            $row->user->email,
            $row->done . ' of ' . $row->total,
            get_string('status_' . $row->status, 'local_asyncwatch'),
            $row->lastaccess ? userdate($row->lastaccess) : '—',
        ];
        foreach ($parts as $part) {
            $line[] = ($row->parts[$part->id] ?? false) ? '1' : '0';
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

// ── Page setup ────────────────────────────────────────────────────────────────
$pageurl = new moodle_url('/local/asyncwatch/report.php', ['courseid' => $courseid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('report', 'local_asyncwatch'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'local_asyncwatch'));

// Tab bar.
$manage_url = new moodle_url('/local/asyncwatch/manage.php', ['courseid' => $courseid]);
$tabs = [
    new tabobject('parts',         new moodle_url($manage_url, ['tab' => 'parts']),  get_string('tab_parts',         'local_asyncwatch')),
    new tabobject('rules',         new moodle_url($manage_url, ['tab' => 'rules']),  get_string('tab_rules',         'local_asyncwatch')),
    new tabobject('rulesets',      new moodle_url('/local/asyncwatch/rulesets.php',      ['courseid' => $courseid]), get_string('tab_rulesets',      'local_asyncwatch')),
    new tabobject('report',        $pageurl,                                                                          get_string('tab_report',        'local_asyncwatch')),
    new tabobject('notifications', new moodle_url('/local/asyncwatch/notifications.php', ['courseid' => $courseid]), get_string('tab_notifications', 'local_asyncwatch')),
];
echo $OUTPUT->tabtree($tabs, 'report');

if (empty($students)) {
    echo $OUTPUT->notification(get_string('noenrolments', 'local_asyncwatch'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if (empty($all_rules)) {
    echo $OUTPUT->notification(get_string('norules', 'local_asyncwatch'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// ── Filter bar ────────────────────────────────────────────────────────────────
$filter_url = new moodle_url($pageurl, ['courseid' => $courseid]);
echo '<form method="get" action="' . $filter_url->out(false) . '" class="mb-4">';
echo '<input type="hidden" name="courseid" value="' . (int)$courseid . '">';
echo '<div class="d-flex flex-wrap align-items-end mb-2">';

// Rule filter.
echo '<div class="mr-2 mb-2"><label class="d-block small font-weight-bold mb-1" for="filter_ruleid">' . get_string('filter_rule', 'local_asyncwatch') . '</label>';
echo '<select name="ruleid" id="filter_ruleid" class="custom-select me-2 mb-2">';
echo '<option value="0">' . get_string('all_rules', 'local_asyncwatch') . '</option>';
foreach ($all_rules as $rule) {
    $sel = ($filter_ruleid === (int)$rule->id) ? ' selected' : '';
    echo '<option value="' . (int)$rule->id . '"' . $sel . '>' . s(format_string($rule->name)) . '</option>';
}
echo '</select></div>';

// User filter.
echo '<div class="mr-2 mb-2"><label class="d-block small font-weight-bold mb-1" for="filter_userid">' . get_string('filter_user', 'local_asyncwatch') . '</label>';
echo '<select name="userid" id="filter_userid" class="custom-select me-2 mb-2">';
echo '<option value="0">' . get_string('all_users', 'local_asyncwatch') . '</option>';
foreach ($students as $user) {
    $sel = ($filter_userid === (int)$user->id) ? ' selected' : '';
    echo '<option value="' . (int)$user->id . '"' . $sel . '>' . s(fullname($user)) . '</option>';
}
echo '</select></div>';

// Override filter.
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

// Group filter.
$all_groups_for_filter = groups_get_all_groups($courseid);
if (!empty($all_groups_for_filter)) {
    echo '<div class="mr-2 mb-2"><label class="d-block small font-weight-bold mb-1" for="filter_groupid">' . get_string('filter_group', 'local_asyncwatch') . '</label>';
    echo '<select name="filtergroupid" id="filter_groupid" class="custom-select me-2 mb-2">';
    echo '<option value="0">' . get_string('filter_group_all', 'local_asyncwatch') . '</option>';
    foreach ($all_groups_for_filter as $g) {
        $sel = ($filter_groupid === (int)$g->id) ? ' selected' : '';
        echo '<option value="' . (int)$g->id . '"' . $sel . '>' . s(format_string($g->name)) . '</option>';
    }
    echo '</select></div>';
}

// Status filter.
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

// Rule Set filter.
$all_rulesets_for_filter = helper::get_rule_sets($courseid);
if (!empty($all_rulesets_for_filter)) {
    echo '<div class="mr-2 mb-2"><label class="d-block small font-weight-bold mb-1" for="filter_rulesetid">' . get_string('filter_ruleset', 'local_asyncwatch') . '</label>';
    echo '<select name="rulesetid" id="filter_rulesetid" class="custom-select me-2 mb-2">';
    echo '<option value="0">' . get_string('filter_ruleset_all', 'local_asyncwatch') . '</option>';
    foreach ($all_rulesets_for_filter as $rs) {
        $sel = ($filter_rulesetid === (int)$rs->id) ? ' selected' : '';
        echo '<option value="' . (int)$rs->id . '"' . $sel . '>' . s(format_string($rs->name)) . '</option>';
    }
    echo '</select></div>';
}

echo '<div class="mb-2">';
echo '<button type="submit" class="btn btn-primary me-2 mb-2">' . get_string('applyfilter', 'local_asyncwatch') . '</button>';
if ($filter_ruleid || $filter_userid || $filter_status || $filter_rulesetid || $filter_override || $filter_groupid) {
    echo ' <a href="' . $pageurl->out(false) . '" class="btn btn-link btn-sm">' . get_string('clearfilter', 'local_asyncwatch') . '</a>';
}
echo '</div>';
echo '</div></form>';

// ── Summary dashboard ────────────────────────────────────────────────────────
// Build per-rule stats from the filtered rows.
if (!empty($rows)) {
    // Aggregate counts per rule.
    $rule_stats = []; // [ruleid => ['rule'=>, 'completed'=>, 'ok'=>, 'warning'=>, 'breach'=>, 'total'=>]]
    foreach ($rows as $row) {
        $rid = (int)$row->rule->id;
        if (!isset($rule_stats[$rid])) {
            $rule_stats[$rid] = [
                'rule'      => $row->rule,
                'completed' => 0,
                'ok'        => 0,
                'warning'   => 0,
                'breach'    => 0,
                'total'     => 0,
                'overrides' => 0,
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
    echo '<div class="d-flex align-items-center justify-content-between mb-2">';
    echo '<span class="font-weight-bold text-muted small" style="text-transform:uppercase;letter-spacing:0.05em;">Rule Summary</span>';
    echo '<button type="button" id="aw-summary-toggle" class="btn btn-sm btn-link p-0" style="font-size:0.85em;">Hide summary</button>';
    echo '</div>';
    echo '<div id="aw-summary-cards">';
    echo '<div class="d-flex flex-wrap" style="gap:1rem;">';

    foreach ($rule_stats as $rid => $st) {
        $rule   = $st['rule'];
        $total  = $st['total'];
        $base_url = new moodle_url($pageurl, ['courseid' => $courseid, 'ruleid' => $rid]);

        echo '<div class="card" style="min-width:220px;flex:1 1 220px;max-width:340px;">';
        echo '<div class="card-header py-2 px-3" style="background:#f8f9fa;">';
        echo '<div class="font-weight-bold" style="font-size:0.9em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' . s(format_string($rule->name)) . '">';
        echo '<a href="' . s($base_url->out(false)) . '" style="color:inherit;text-decoration:none;">' . s(format_string($rule->name)) . '</a>';
        if (!$rule->enabled) {
            echo ' <span class="badge badge-secondary bg-secondary" style="font-size:0.75em;">' . get_string('rule_disabled_badge', 'local_asyncwatch') . '</span>';
        }
        echo '</div>';
        echo '<div class="d-flex align-items-center mt-1" style="gap:0.4rem;">';
        echo '<span class="text-muted" style="font-size:0.78em;">' . $total . ' ' . ($total === 1 ? get_string('learner', 'local_asyncwatch') : get_string('learners', 'local_asyncwatch')) . '</span>';
        if ($st['overrides'] > 0) {
            $ov_link = (new moodle_url($base_url, ['filteroverride' => 'override']))->out(false);
            echo '<a href="' . s($ov_link) . '" style="text-decoration:none;">';
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
            $pct   = round(100 * $count / $total, 2);
            $link  = (new moodle_url($base_url, ['filterstatus' => $skey]))->out(false);
            echo '<a href="' . s($link) . '" title="' . s($status_labels[$skey] . ': ' . $count) . '"'
               . ' style="display:block;width:' . $pct . '%;background:' . $color . ';height:100%;"></a>';
        }
        echo '</div>';

        // Count pills.
        echo '<div class="d-flex flex-wrap" style="gap:0.3rem;">';
        foreach ($status_colors as $skey => $color) {
            $count = $st[$skey];
            $link  = (new moodle_url($base_url, ['filterstatus' => $skey]))->out(false);
            $pct   = $total > 0 ? round(100 * $count / $total) : 0;
            $opacity = $count === 0 ? 'opacity:0.35;' : '';
            echo '<a href="' . s($link) . '" style="text-decoration:none;' . $opacity . '">';
            echo '<span style="display:inline-flex;align-items:center;background:' . $color . ';color:' . ($skey === 'warning' ? '#333' : '#fff') . ';border-radius:3px;padding:1px 6px;font-size:0.75em;font-weight:600;white-space:nowrap;">';
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

// ── Flexible table ────────────────────────────────────────────────────────────
if (empty($rows)) {
    echo $OUTPUT->notification(get_string('noresults', 'local_asyncwatch'), 'info');
} else {
    // Preserve filter params in table sort URLs.
    $filter_params = array_filter([
        'courseid'      => $courseid,
        'ruleid'        => $filter_ruleid,
        'userid'        => $filter_userid,
        'filterstatus'  => $filter_status,
        'rulesetid'     => $filter_rulesetid,
        'filtergroupid' => $filter_groupid,
        'filteroverride'=> $filter_override,
    ]);

    $table = new flexible_table('asyncwatch-report-' . $courseid);
    $table->define_baseurl(new moodle_url($pageurl, $filter_params));

    // Column keys and headers.
    $cols = [
        'rulename',
        'deadline',
        'default_warn',
        'learner',
        'parts_required',
        'part_progress',
        'status',
        'lastaccess',
        'override_deadline',
        'override_warn',
    ];
    $headers = [
        get_string('rulename',         'local_asyncwatch'),
        get_string('default_deadline', 'local_asyncwatch'),
        get_string('default_warn',     'local_asyncwatch'),
        get_string('learner',          'local_asyncwatch'),
        get_string('parts_required',   'local_asyncwatch'),
        get_string('part_progress',    'local_asyncwatch'),  // merged: shows fraction + bar
        get_string('status',           'local_asyncwatch'),
        get_string('last_activity',    'local_asyncwatch'),
        get_string('override_deadline','local_asyncwatch'),
        get_string('override_warn',    'local_asyncwatch'),
    ];

    $table->define_columns($cols);
    $table->define_headers($headers);

    // Sortable columns.
    $table->sortable(true, 'rulename', SORT_ASC);
    $table->no_sorting('default_warn');
    $table->no_sorting('parts_required');
    $table->no_sorting('part_progress');
    $table->no_sorting('override_deadline');
    $table->no_sorting('override_warn');

    $table->set_attribute('class', 'generaltable asyncwatch-report');
    $table->collapsible(false);
    $table->initialbars(true);
    $table->pagesize(25, count($rows));
    $table->setup();

    // Determine sort order from flexible_table's own state.
    $sort_col = $table->get_sort_columns(); // [col => SORT_ASC/SORT_DESC]
    $sort_key = array_key_first($sort_col) ?? 'rulename';
    $sort_asc = ($sort_col[$sort_key] ?? SORT_ASC) === SORT_ASC;

    $sort_map = [
        'rulename'   => fn($r) => strtolower(format_string($r->rule->name)),
        'deadline'   => fn($r) => $r->rule->deadline,
        'learner'    => fn($r) => strtolower(fullname($r->user)),
        'parts_done' => fn($r) => $r->done,
        'status'     => fn($r) => $r->status,
        'lastaccess' => fn($r) => $r->lastaccess,
    ];

    if (isset($sort_map[$sort_key])) {
        $fn = $sort_map[$sort_key];
        usort($rows, function($a, $b) use ($fn, $sort_asc) {
            $va = $fn($a); $vb = $fn($b);
            $cmp = is_string($va) ? strcmp($va, $vb) : ($va <=> $vb);
            return $sort_asc ? $cmp : -$cmp;
        });
    }

    // Helpers.
    $fmt_warn = function(?int $wm): string {
        if ($wm === null || $wm <= 0) return '—';
        if ($wm % (7*24*60) === 0) { $n = $wm/(7*24*60); return $n . ' week(s)'; }
        if ($wm % (24*60)   === 0) { $n = $wm/(24*60);   return $n . ' day(s)'; }
        if ($wm % 60        === 0) { $n = $wm/60;         return $n . ' hour(s)'; }
        return $wm . ' minute(s)';
    };

    $status_cfg = [
        'completed' => ['class' => 'badge badge-success bg-success text-white',  'style' => 'font-size:0.95em;'],
        'ok'        => ['class' => 'badge badge-info bg-info text-white',         'style' => 'font-size:0.95em;'],
        'warning'   => ['class' => 'badge badge-warning bg-warning',              'style' => 'font-size:0.95em;'],
        'breach'    => ['class' => 'badge badge-danger bg-danger text-white',     'style' => 'font-size:0.95em;'],
    ];

    $datefmt = get_string('aw_datetimefmt', 'local_asyncwatch');

    foreach ($rows as $row) {
        $sc    = $status_cfg[$row->status];
        $badge = html_writer::tag('span',
            get_string('status_' . $row->status, 'local_asyncwatch'),
            ['class' => $sc['class'], 'style' => $sc['style']]
        );

        $rule_cell = format_string($row->rule->name);
        if (!$row->rule->enabled) {
            $rule_cell .= ' ' . html_writer::tag('span',
                get_string('rule_disabled_badge', 'local_asyncwatch'),
                ['class' => 'badge badge-secondary bg-secondary text-white ml-1', 'style' => 'font-size:0.95em;']
            );
        }

        // Default warn cell.
        $def_warn_mins = (int)$row->rule->warn_hours;
        if ($def_warn_mins > 0) {
            $def_warn_start    = $row->rule->deadline - ($def_warn_mins * MINSECS);
            $default_warn_cell = $fmt_warn($def_warn_mins) . ' (' . userdate($def_warn_start, $datefmt) . ')';
        } else {
            $default_warn_cell = '—';
        }

        // Override deadline cell.
        $override_cell = $row->has_override
            ? html_writer::tag('span', userdate($row->eff_deadline, $datefmt),
                ['class' => 'badge badge-warning bg-warning', 'style' => 'font-size:0.95em;',
                 'title' => get_string('override_active', 'local_asyncwatch')])
            : html_writer::tag('em', get_string('override_none', 'local_asyncwatch'), ['class' => 'text-muted']);

        // Override warn cell.
        if ($row->has_override && $row->override_warn > 0) {
            $ov_warn_start      = $row->eff_deadline - ($row->override_warn * MINSECS);
            $override_warn_cell = html_writer::tag('span',
                $fmt_warn($row->override_warn) . ' (' . userdate($ov_warn_start, $datefmt) . ')',
                ['class' => 'badge badge-warning bg-warning', 'style' => 'font-size:0.95em;']);
        } else {
            $override_warn_cell = html_writer::tag('em', '—', ['class' => 'text-muted']);
        }

        // Merged progress bar — positional green segments with fraction overlaid.
        $tooltip_lines     = [];
        $total_parts_count = count($parts);
        $done_count        = 0;
        $bar_segments      = '';
        foreach ($parts as $part) {
            $done_part       = $row->parts[$part->id] ?? false;
            $pname           = format_string($part->name);
            $tooltip_lines[] = ($done_part ? '✓' : '✗') . ' ' . $pname;
            if ($done_part) $done_count++;
            $seg_color = $done_part ? '#28a745' : '#dee2e6';
            $seg_w     = round(100 / max(1, $total_parts_count), 4);
            $bar_segments .= '<span style="display:inline-block;width:' . $seg_w . '%;height:100%;background:' . $seg_color . ';border-right:1px solid #fff;"></span>';
        }
        $label   = $done_count . ' / ' . $total_parts_count;
        // Store tooltip data in a data attribute — rendered via JS popover.
        $parts_data = [];
        foreach ($tooltip_lines as $tl) {
            $parts_data[] = htmlspecialchars($tl, ENT_QUOTES);
        }
        $data_parts = implode('|', $parts_data);
        $progress_bar = '<div class="aw-progress-bar"'
            . ' data-parts="' . $data_parts . '"'
            . ' data-done="' . $done_count . '"'
            . ' style="position:relative;width:140px;height:22px;border-radius:4px;overflow:hidden;border:1px solid #ced4da;cursor:pointer;display:flex;">'
            . $bar_segments
            . '<span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:0.78em;font-weight:600;color:#333;line-height:1;text-shadow:0 0 3px #fff,0 0 3px #fff;">'
            . s($label) . '</span>'
            . '</div>';

        $row_data = [
            $rule_cell,
            userdate($row->rule->deadline, $datefmt),
            $default_warn_cell,
            html_writer::link(
                new moodle_url('/user/view.php', ['id' => $row->user->id, 'course' => $courseid]),
                s(fullname($row->user))
            ),
            $row->rule->parts_required . ' / ' . $row->total,
            $progress_bar,
            $badge,
            $row->lastaccess ? userdate($row->lastaccess, $datefmt)
                             : html_writer::tag('em', get_string('never', 'moodle')),
            $override_cell,
            $override_warn_cell,
        ];

        // Apply opacity to disabled rule rows.
        if (!$row->rule->enabled) {
            $table->add_data($row_data, 'dimmed_text');
        } else {
            $table->add_data($row_data);
        }
    }

    $table->finish_output();
}

// CSV export button.
$csv_params = ['courseid' => $courseid, 'download' => 'csv'];
if ($filter_ruleid)    $csv_params['ruleid']        = $filter_ruleid;
if ($filter_userid)    $csv_params['userid']        = $filter_userid;
if ($filter_status)    $csv_params['filterstatus']  = $filter_status;
if ($filter_rulesetid) $csv_params['rulesetid']     = $filter_rulesetid;
if ($filter_groupid)   $csv_params['filtergroupid'] = $filter_groupid;
if ($filter_override)  $csv_params['filteroverride']= $filter_override;
$csv_url = new moodle_url('/local/asyncwatch/report.php', $csv_params);
echo html_writer::div(
    html_writer::link($csv_url, get_string('exportcsv', 'local_asyncwatch'), ['class' => 'btn btn-secondary mt-3']),
    'mt-3'
);

// Custom progress bar hover popup — fully controlled, no Bootstrap tooltip issues.
echo '<style>
#aw-popup {
    position: fixed;
    z-index: 9999;
    background: #333;
    color: #fff;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 0.8em;
    max-width: 1000px;
    pointer-events: none;
    display: none;
    flex-wrap: wrap;
    gap: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
#aw-popup .aw-chip {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    white-space: nowrap;
    font-size: 0.9em;
}
#aw-popup .aw-chip.done   { background: #28a745; }
#aw-popup .aw-chip.notdone { background: #6c757d; }
</style>
<div id="aw-popup"></div>
<script>
(function() {
    var popup = document.getElementById("aw-popup");
    if (!popup) return;

    document.addEventListener("mouseover", function(e) {
        var bar = e.target.closest(".aw-progress-bar");
        if (!bar) return;
        var parts = bar.dataset.parts ? bar.dataset.parts.split("|") : [];
        if (!parts.length) return;

        popup.innerHTML = "";
        parts.forEach(function(p) {
            var done = p.indexOf("\u2713") === 0;
            var chip = document.createElement("span");
            chip.className = "aw-chip " + (done ? "done" : "notdone");
            chip.textContent = p;
            popup.appendChild(chip);
        });
        popup.style.display = "flex";
        positionPopup(e);
    });

    document.addEventListener("mousemove", function(e) {
        if (popup.style.display === "flex") positionPopup(e);
    });

    document.addEventListener("mouseout", function(e) {
        if (!e.target.closest(".aw-progress-bar")) {
            popup.style.display = "none";
        }
    });

    function positionPopup(e) {
        var pw = popup.offsetWidth  || 400;
        var ph = popup.offsetHeight || 40;
        // Centre horizontally on cursor, above it.
        var x = e.clientX - (pw / 2);
        var y = e.clientY - ph - 14;
        // Keep within viewport edges.
        if (x < 10) x = 10;
        if (x + pw > window.innerWidth - 10) x = window.innerWidth - pw - 10;
        // If not enough room above, show below cursor.
        if (y < 10) y = e.clientY + 14;
        popup.style.left = x + "px";
        popup.style.top  = y + "px";
    }
})();
</script>';

echo $OUTPUT->footer();
