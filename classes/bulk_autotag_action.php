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

use core_question\local\bank\view;

/**
 * Class bulk_autotag_action
 *
 * @package    qbank_genai
 * @copyright  2025 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_autotag_action extends \core_question\local\bank\bulk_action_base {
    /** @var view|null Question bank view. */
    protected $qbank;

    /**
     * Constructor.
     *
     * @param view|null $qbank The question bank view.
     */
    public function __construct(?view $qbank) {
        $this->qbank = $qbank;
    }

    /**
     * Returns the title for the bulk action.
     *
     * @return string The title.
     */
    public function get_bulk_action_title(): string {
        return get_string('autotag', 'qbank_genai');
    }

    /**
     * Returns the key for the bulk action.
     *
     * @return string The key.
     */
    public function get_key(): string {
        return 'autotag';
    }

    /**
     * Returns the URL for the bulk action.
     *
     * @return \moodle_url The URL.
     */
    public function get_bulk_action_url(): \moodle_url {
        global $COURSE;
        return new \moodle_url('/question/bank/genai/autotag.php', ['courseid' => $COURSE->id]);
    }

    /**
     * Returns the capabilities required for the bulk action.
     *
     * @return array The capabilities required.
     */
    public function get_bulk_action_capabilities(): ?array {
        return ['moodle/question:tagall'];
    }
}
