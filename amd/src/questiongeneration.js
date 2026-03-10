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
 * AMD module for the question generation feature.
 *
 * @module     qbank_genai/questiongeneration
 * @copyright  2026 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Selectors from 'qbank_genai/selectors';

class QuestionGeneration {
    constructor(contextID, courseID) {
        this.contextID = contextID;
        this.courseID = courseID;
        this.registerEventListeners();
        this.hideMessage();
    }

    registerEventListeners() {
        const generatorButton = document.querySelector(Selectors.ELEMENTS.QUESTIONGENERATIONBUTTON);
        if (generatorButton) {
            generatorButton.addEventListener('click', async() => {

                this.hideMessage();

                const fileID = parseInt(document.querySelector(Selectors.ELEMENTS.QUESTIONGENERATIONFILESELECT).value);
                const numberQuestions = parseInt(document.querySelector(Selectors.ELEMENTS.QUESTIONGENERATIONNUMBERINPUT).value);

                if (isNaN(numberQuestions) || numberQuestions <= 0) {
                    this.showMessage("Enter a positive number of questions!", true);
                    return;
                }

                generatorButton.setAttribute('disabled', 'disabled');
                const oldText = generatorButton.innerHTML;
                generatorButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

                const request = {
                    methodname: 'qbank_genai_generate_questions',
                    args: {
                        contextID: this.contextID,
                        courseID: this.courseID,
                        fileID: fileID,
                        numberQuestions: numberQuestions,
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
                    generatorButton.removeAttribute('disabled');
                    generatorButton.innerHTML = oldText;
                }
            });
        }
    }

    showMessage(message, error) {
        const resultField = document.querySelector(Selectors.ELEMENTS.QUESTIONGENERATIONRESULT);
        if (resultField) {
            resultField.innerHTML = message;
            resultField.style.display = 'block';
            resultField.classList.add(error ? 'alert-danger' : 'alert-info');
        }
    }

    hideMessage() {
        const resultField = document.querySelector(Selectors.ELEMENTS.QUESTIONGENERATIONRESULT);
        if (resultField) {
            resultField.innerHTML = '';
            resultField.style.display = 'none';
            resultField.classList.remove('alert-info', 'alert-danger');
        }
    }
}

export const init = (contextID, courseID) => {
    new QuestionGeneration(contextID, courseID);
};
