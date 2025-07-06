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
 * AMD module for the AutoTag feature.
 *
 * @module     qbank_genai/autotag
 * @copyright  2025 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Selectors from 'qbank_genai/selectors';

class AutoTag {
    constructor(questionlist) {
        this.questionlist = questionlist;
        this.registerEventListeners();
        this.hideMessage();
    }

    registerEventListeners() {
        const tagger = document.querySelector(Selectors.ELEMENTS.AUTOTAGBUTTON);
        if (tagger) {
            tagger.addEventListener('click', async() => {

                this.hideMessage();

                tagger.setAttribute('disabled', 'disabled');
                const oldText = tagger.innerHTML;
                tagger.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

                const request = {
                    methodname: 'qbank_genai_autotag_questions',
                    args: {
                        questionlist: this.questionlist,
                    }
                };

                try {
                    const responseObj = await Ajax.call([request])[0];
                    if (responseObj.error) {
                        this.showMessage(responseObj.error.exception.message, true);
                    } else {
                        this.showMessage(responseObj, false);
                    }
                } catch (error) {
                    this.showMessage(error.message, true);
                } finally {
                    tagger.removeAttribute('disabled');
                    tagger.innerHTML = oldText;
                }
            });
        }
    }

    showMessage(message, error) {
        const errorField = document.querySelector(Selectors.ELEMENTS.RESULTFIELD);
        if (errorField) {
            errorField.innerHTML = message;
            errorField.style.display = 'block';
            errorField.classList.add(error ? 'alert-danger' : 'alert-info');
        }
    }

    hideMessage() {
        const errorField = document.querySelector(Selectors.ELEMENTS.RESULTFIELD);
        if (errorField) {
            errorField.innerHTML = '';
            errorField.style.display = 'none';
            errorField.classList.remove('alert-info', 'alert-danger');
        }
    }
}

export const init = (questionlist) => {
    new AutoTag(questionlist);
};
