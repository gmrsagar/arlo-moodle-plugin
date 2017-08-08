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
 * Arlo enrolment plugin settings and presets.
 *
 * Things that are accessable:
 *  - $ADMIN = $adminroot;
 *  - $plugininfo = The Arlo enrolment plugin class;
 *  - $enrol = The Arlo enrolment plugin class;
 *
 * @package     enrol_arlo
 * @author      Mathew May
 * @copyright   2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Locked text field, allows unlocking of text to edit
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_configlockedtext extends admin_setting_configtext {
    /**
     * Constructor
     * @param string $name unique ascii name, either 'mysetting' for settings that in config, or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string $defaultsetting default password
     */
    public function __construct($name, $visiblename, $description, $defaultsetting) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_RAW, 30);
    }

    /**
     * Log config changes if necessary.
     * @param string $name
     * @param string $oldvalue
     * @param string $value
     */
    protected function add_to_config_log($name, $oldvalue, $value) {
        // Trigger an event for updating this field.
        $event = \enrol_arlo\event\fqdn_updated::create(array(
            'objectid' => 1,
            'context' => context_system::instance(),
            'other' => array(
                'name' => $name,
                'oldvalue' => $oldvalue,
                'newvalue' => $value
            )
        ));
        $event->trigger();
        if ($value !== '') {
            $value = '********';
        }
        if ($oldvalue !== '' and $oldvalue !== null) {
            $oldvalue = '********';
        }
        parent::add_to_config_log($name, $oldvalue, $value);
    }

    /**
     * Returns XHTML for the field
     * Writes Javascript into the HTML below right before the last div
     *
     * @todo Make javascript available through newer methods if possible
     * @param string $data Value for the field
     * @param string $query Passed as final argument for format_admin_setting
     * @return string XHTML field
     */
    public function output_html($data, $query='') {
        $id = $this->get_id();
        $unmask = get_string('unlock', 'enrol_arlo');
        $unmaskjs = '<script type="text/javascript">
        //<![CDATA[
        var is_ie = (navigator.userAgent.toLowerCase().indexOf("msie") != -1);
        
        document.getElementById("'.$id.'").setAttribute("autocomplete", "off");
        
        var unmaskdiv = document.getElementById("'.$id.'unmaskdiv");
        
        var unmaskchb = document.createElement("input");
        unmaskchb.setAttribute("type", "checkbox");
        unmaskchb.setAttribute("id", "'.$id.'unmask");
        unmaskchb.onchange = function() {
            document.getElementById("id_s_enrol_arlo_platform").readOnly ^= true;
        };
        unmaskdiv.appendChild(unmaskchb);
        
        var unmasklbl = document.createElement("label");
        unmasklbl.innerHTML = "'.addslashes_js($unmask).'";
        if (is_ie) {
          unmasklbl.setAttribute("htmlFor", "'.$id.'unmask");
        } else {
          unmasklbl.setAttribute("for", "'.$id.'unmask");
        }
        unmaskdiv.appendChild(unmasklbl);
        
        if (is_ie) {
          // ugly hack to work around the famous onchange IE bug
          unmaskchb.onclick = function() {this.blur();};
          unmaskdiv.onclick = function() {this.blur();};
        }
        //]]>
        </script>';
        return format_admin_setting($this, $this->visiblename,
            '<div class="form-password"><input readonly="readonly" type="text" size="'.$this->size.'" id="'.$id.'" name="'.$this->get_full_name().'" value="'.s($data).'" /><div class="unmask" id="'.$id.'unmaskdiv"></div>'.$unmaskjs.'</div>',
            $this->description, true, '', NULL, $query);
    }
}

/**
 * Displays current Arlo API status in admin settings page.
 */
class admin_setting_configarlostatus extends admin_setting {
    public function __construct($name, $visiblename) {
        $this->nosave = true;
        parent::__construct($name, $visiblename, '', '');
    }
    /**
     * Always returns true
     * @return bool Always returns true
     */
    public function get_setting() {
        return true;
    }
    /**
     * Always returns true
     * @return bool Always returns true
     */
    public function get_defaultsetting() {
        return true;
    }
    /**
     * Never write settings
     * @return string Always returns an empty string
     */
    public function write_setting($data) {
        // Do not write any setting.
        return '';
    }

    /**
     * Output the current Arlo API status.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;
        $apistatus = get_config('enrol_arlo', 'apistatus');
        $apilastrequested = (int) get_config('enrol_arlo', 'apilastrequested');
        $statusicon = '';
        $reason = '';
        $description = '';
        if (200 == $apistatus) {
            $statusicon = $OUTPUT->pix_icon('t/go', get_string('ok', 'enrol_arlo'));
            $reason = get_string('apistatusok', 'enrol_arlo', userdate($apilastrequested));
            $description = '';
        } else if (0 == $apistatus || ($apistatus >= 400 && $apistatus < 499)) {
            $statusicon = $OUTPUT->pix_icon('t/stop', get_string('notok', 'enrol_arlo'));
            $reason = get_string('apistatusclienterror', 'enrol_arlo');
            $url = new moodle_url('/enrol/arlo/admin/apilog.php');
            $description = get_string('pleasecheckrequestlog', 'enrol_arlo', $url->out());
        } else if ($apistatus >= 500 && $apistatus < 599) {
            $statusicon = $OUTPUT->pix_icon('t/stop', get_string('notok', 'enrol_arlo'));
            $reason = get_string('apistatusservererror', 'enrol_arlo');
            $url = new moodle_url('/enrol/arlo/admin/apilog.php');
            $description = get_string('pleasecheckrequestlog', 'enrol_arlo', $url->out());
        } else {
            return '';
        }
        $return = '<div class="form-item clearfix" id="admin-'.$this->name.'">
                   <div class="form-label"></div>
                   <div class="form-setting">'.$statusicon.' '.$reason.'</div>
                   <div class="form-description">'.$description.'</div>
                   </div>';
        return $return;
    }
}