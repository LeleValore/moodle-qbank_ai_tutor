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

namespace qbank_genai\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;

/**
 * Class generation_form
 *
 * @package    qbank_genai
 * @copyright  2023 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generation_form extends moodleform {
    /**
     * Form definition: The user can select the resource for which questions shall be generated.
     */
    public function definition() {
        $mform = $this->_form;

        $resources = [];

        foreach ($this->_customdata as $resource) {
            if (!$resource->deletioninprogress) {
                $resources[$resource->id] = $resource->name;
            }
        }

        $mform->addElement('select', 'file', get_string('file', 'core'), $resources);
        $mform->addRule('file', null, 'required', null, 'client');

        $mform->addElement('text', 'numberquestions', get_string('numberquestions', 'qbank_genai'), ['size' => '3']);
        $mform->addRule('numberquestions', null, 'required', null, 'client');
        $mform->setType('numberquestions', PARAM_INT);
        $mform->setDefault('numberquestions', 10);
    }
}
