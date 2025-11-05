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

use core_question\local\bank\navigation_node_base;
use core_question\local\bank\view;

/**
 * Plugin entrypoint.
 *
 * @package    qbank_genai
 * @copyright  2024 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_feature extends \core_question\local\bank\plugin_features_base {
    /**
     * This method will return the object for the navigation node.
     *
     * @return navigation_node_base
     */
    public function get_navigation_node(): ?navigation_node_base {
        return new navigation();
    }

    /**
     * Returns the question actions for the question bank.
     *
     * @param view $qbank The question bank view.
     * @return array An array of question actions.
     */
    public function get_question_actions($qbank): array {
        return [new autotag_action($qbank)];
    }

    /**
     * Returns the bulk actions for the question bank.
     *
     * @param view|null $qbank The question bank view.
     * @return array An array of bulk actions.
     */
    public function get_bulk_actions(?view $qbank = null): array {
        return [new bulk_autotag_action($qbank)];
    }
}
