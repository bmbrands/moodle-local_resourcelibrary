<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Simple value select filter.
 * A variant of the user_filter_simpleselect
 *
 * @package   local_resourcelibrary
 * @copyright  2020 CALL Learning 2020 - Laurent David laurent@call-learning.fr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_resourcelibrary\filters;

defined('MOODLE_INTERNAL') || die;

/**
 * Generic filter based on a simple checkbox
 *
 * @copyright  2020 CALL Learning 2020 - Laurent David laurent@call-learning.fr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkbox_filter extends base_filter {
    /**
     * options for the list values
     *
     * @var array
     */
    protected $_options;

    /**
     * Constructor
     *
     * @param \core_customfield\field_controller $field user table filed name
     * @throws \moodle_exception
     */
    public function __construct(\core_customfield\field_controller $field) {
        parent::__construct($field);
        if (!$field instanceof \customfield_checkbox\field_controller) {
            throw new \moodle_exception('wronghandlerforfilter', 'local_resourcelibrary');
        }
    }



    /**
     * Adds controls specific to this filter in the form.
     * @param \MoodleQuickForm form
     *
     */
    public function add_to_form(\MoodleQuickForm &$mform) {
        $elementname = $this->get_form_value_item_name();
        $mform->addElement( 'checkbox', $elementname, $this->_label);
        $mform->setDefault($elementname, $this->_field->get_configdata_property('checkbydefault'));
        $mform->setType($elementname, PARAM_BOOL);
        parent::add_to_form($mform);
    }

    /**
     * Retrieves data from the form data
     *
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    public function check_data($formdata) {
        $field = $this->_name;

        if (array_key_exists($field,  (array) $formdata) and $formdata->$field !== '') {
            return array('value' => (string) $formdata->$field);
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array $data filter settings
     * @return array sql string and $params (or array (null, null) if no filter)
     */
    public function get_sql_filter($data) {
        static $counter = 0;
        $name = 'ex_checkbox' . $counter++;

        $field = $this->get_sql_field_name();
        return empty($data) ? array(null, null) : array("$field=:$name", array($name => $data));
    }
}
