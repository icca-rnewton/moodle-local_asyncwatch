<?php
/**
 * Custom admin setting for the course-level profile field write allowlist.
 *
 * Moodle's stock admin_setting_configmulticheckbox renders a flat list with
 * no grouping and no per-item disabled state, neither of which this needs:
 *
 * - Fields are grouped under their own profile field CATEGORY heading, so
 *   an admin who organises fields into categories (e.g. a dedicated
 *   "AsyncWatch" category) can see that grouping at a glance, while still
 *   seeing every other category/field too — a rule might legitimately
 *   target a field outside that category.
 * - LOCKED fields are shown too, greyed out with a disabled (unticked)
 *   checkbox — visible so an admin can confirm AsyncWatch found the field
 *   and could write to it once unlocked, but not selectable, since a
 *   locked field is excluded from the actual runtime write path
 *   regardless of what's ticked here (see
 *   helper::get_profile_field_options()).
 *
 * Storage stays a plain comma-separated list of checked shortnames — the
 * same format admin_setting_configmulticheckbox itself uses — so this is a
 * drop-in replacement with no migration needed for whatever was already
 * ticked.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch;

defined('MOODLE_INTERNAL') || die();

class admin_setting_profilefields extends \admin_setting {

    public function __construct(string $name, string $visiblename, string $description) {
        parent::__construct($name, $visiblename, $description, '');
    }

    /**
     * @return array shortname => 1 for every currently-checked field.
     */
    public function get_setting() {
        $raw = $this->config_read($this->name);
        if ($raw === null || $raw === false || $raw === '') {
            return [];
        }
        $checked = [];
        foreach (explode(',', $raw) as $shortname) {
            $shortname = trim($shortname);
            if ($shortname !== '') {
                $checked[$shortname] = 1;
            }
        }
        return $checked;
    }

    /**
     * @param mixed $data Posted checkbox array, e.g. ['shortname1' => '1'].
     *              Only unlocked text/menu fields are ever actually stored
     *              — a locked or non-existent shortname slipping through
     *              (disabled inputs aren't submitted by the browser, but
     *              nothing stops a hand-crafted POST) is silently dropped
     *              rather than stored.
     */
    public function write_setting($data) {
        if (!is_array($data)) {
            $data = [];
        }
        $eligible = $this->get_eligible_shortnames();
        $checked  = array_values(array_intersect(array_keys($data), $eligible));
        $stored   = implode(',', $checked);
        return ($this->config_write($this->name, $stored) ? '' : get_string('errorsetting', 'admin'));
    }

    /**
     * Shortnames of every unlocked text/menu field — the only ones this
     * setting is allowed to store as checked.
     */
    private function get_eligible_shortnames(): array {
        global $DB;
        $rows = $DB->get_records_select(
            'user_info_field',
            "datatype IN ('text', 'menu') AND locked = 0",
            null, '', 'shortname'
        );
        return array_column($rows, 'shortname');
    }

    public function output_html($data, $query = '') {
        global $DB;

        $checked = is_array($data) ? $data : [];

        $categories = $DB->get_records('user_info_category', null, 'sortorder ASC', 'id, name');
        $fields = $DB->get_records_select(
            'user_info_field',
            "datatype IN ('text', 'menu')",
            null, 'categoryid ASC, sortorder ASC',
            'id, shortname, name, locked, categoryid'
        );

        $by_category = [];
        foreach ($fields as $f) {
            $by_category[(int)$f->categoryid][] = $f;
        }

        $render_field = function(\stdClass $f) use ($checked): string {
            $inputid = 'id_s_local_asyncwatch_profilefield_' . $f->shortname;
            $name    = $this->get_full_name() . '[' . $f->shortname . ']';

            $attrs = [
                'type'  => 'checkbox',
                'name'  => $name,
                'value' => '1',
                'id'    => $inputid,
            ];
            if (isset($checked[$f->shortname])) {
                $attrs['checked'] = 'checked';
            }
            $label_extra = '';
            $row_style   = '';
            if ($f->locked) {
                $attrs['disabled'] = 'disabled';
                $row_style   = 'opacity:0.55;';
                $label_extra = ' <em>(' . get_string('profilefield_locked_tag', 'local_asyncwatch') . ')</em>';
            }

            $label = format_string($f->name) . ' (' . s($f->shortname) . ')' . $label_extra;

            return \html_writer::start_tag('div', ['style' => 'margin:0.15em 0 0.15em 1.25em; ' . $row_style])
                . \html_writer::empty_tag('input', $attrs) . ' '
                . \html_writer::tag('label', $label, ['for' => $inputid])
                . \html_writer::end_tag('div');
        };

        $out = '';
        foreach ($categories as $cat) {
            if (empty($by_category[(int)$cat->id])) {
                continue; // Skip categories with no eligible-datatype fields at all.
            }
            $out .= \html_writer::tag(
                'div', format_string($cat->name),
                ['style' => 'font-weight:600; margin-top:0.9em;']
            );
            foreach ($by_category[(int)$cat->id] as $f) {
                $out .= $render_field($f);
            }
            unset($by_category[(int)$cat->id]);
        }
        // Any remaining fields belong to a category id that no longer
        // exists (or 0) — show them under their own heading rather than
        // silently dropping them from view.
        foreach ($by_category as $leftover_fields) {
            $out .= \html_writer::tag(
                'div', get_string('profilefield_uncategorised', 'local_asyncwatch'),
                ['style' => 'font-weight:600; margin-top:0.9em;']
            );
            foreach ($leftover_fields as $f) {
                $out .= $render_field($f);
            }
        }

        if ($out === '') {
            $out = \html_writer::tag('p', get_string('profilefield_none_eligible', 'local_asyncwatch'));
        }

        return format_admin_setting($this, $this->visiblename, $out, $this->description, true, '', null, $query);
    }
}