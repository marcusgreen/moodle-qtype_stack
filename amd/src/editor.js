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

/*
 * JavaScript to add popup sql hints in the editor
 *
 * @package qtype_stack
 * @copyright 2021 Marcus Green
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import CodeMirror from 'qtype_stack/codemirror/lib/codemirror';
// import  'qtype_stack/codemirror/addon/hint/show-hint';
// import  'qtype_stack/codemirror/addon/hint/sql-hint';
// import  'qtype_stack/codemirror/mode/sql/sql';

// import  'qtype_stack/codemirror/mode/maxima/maxima';
//import  'qtype_stack/codemirror/mode/d/d';
 import  'qtype_stack/codemirror/mode/javascript/javascript';
import  'qtype_stack/codemirror/mode/coffeescript/coffeescript';
//import  'qtype_stack/codemirror/mode/commonlisp/commonlisp';
//import  'qtype_stack/codemirror/mode/mathematica/mathematica';
//import  'qtype_stack/codemirror/addon/edit/matchbrackets';
import  'qtype_stack/codemirror/addon/edit/matchbrackets';
export const init = (rows) => {
    const editor = CodeMirror.fromTextArea(document.getElementById("id_questionvariables"), {
        mode: "coffeescript",
        smartIndent: true,
        tabSize: 4,
        indentWithTabs: true,
        lineNumbers: true,
        autofocus: true,
    });
     editor.setSize('100%', rows);
};
