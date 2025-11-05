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

namespace qbank_genai;

/**
 * Class navigation
 *
 * @package    qbank_genai
 * @copyright  2024 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation extends \core_question\local\bank\navigation_node_base {
    /**
     * Returns the navigation title.
     *
     * @return string
     */
    public function get_navigation_title(): string {
        return get_string('title', 'qbank_genai');
    }

    /**
     * Returns the navigation key.
     *
     * @return string
     */
    public function get_navigation_key(): string {
        return 'generate';
    }

    /**
     * Returns the navigation URL.
     *
     * @return string
     */
    public function get_navigation_url(): \moodle_url {
        global $COURSE;
        return new \moodle_url('/question/bank/genai/index.php', ['courseid' => $COURSE->id]);
    }
}
