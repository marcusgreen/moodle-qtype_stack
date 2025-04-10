<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

namespace qtype_stack;

use qtype_stack_testcase;
use stack_ast_container;
use stack_cas_security;
use stack_numbers_test_data;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for various AST container features.
 *
 * @package    qtype_stack
 * @copyright  2019 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

require_once(__DIR__ . '/fixtures/numbersfixtures.class.php');
require_once(__DIR__ . '/../stack/cas/ast.container.class.php');
require_once(__DIR__ . '/../stack/cas/cassession2.class.php');
require_once(__DIR__ . '/fixtures/test_base.php');

/**
 * Add description here.
 * @group qtype_stack
 * @covers \stack_ast_container
 */
final class ast_container_test extends qtype_stack_testcase {

    public function test_types(): void {

        $matrix = stack_ast_container::make_from_teacher_source('foo:matrix([1,2],[3,4])', 'type test', new stack_cas_security());
        $this->assertTrue($matrix->is_matrix());
        $this->assertFalse($matrix->is_int());
        $this->assertFalse($matrix->is_float());

        $string = stack_ast_container::make_from_teacher_source('foo:"matrix([1,2],[3,4])"', 'type test', new stack_cas_security());
        $this->assertTrue($string->is_string());
        $this->assertFalse($string->is_matrix());
        $this->assertFalse($string->is_int());
        $this->assertFalse($string->is_float());
        $this->assertEquals(-1, $string->is_list());

        $float = stack_ast_container::make_from_teacher_source('0.23e2', 'type test', new stack_cas_security());
        $this->assertFalse($float->is_string());
        $this->assertFalse($float->is_matrix());
        $this->assertFalse($float->is_int());
        $this->assertTrue($float->is_float());
        $this->assertEquals(-1, $float->is_list());

        $int = stack_ast_container::make_from_teacher_source('234545323423446526524562', 'type test', new stack_cas_security());
        $this->assertFalse($int->is_string());
        $this->assertFalse($int->is_matrix());
        $this->assertTrue($int->is_int());
        $this->assertFalse($int->is_float());
        $this->assertEquals(-1, $int->is_list());

        $int = stack_ast_container::make_from_teacher_source('x:-234545323423446526524562', 'type test', new stack_cas_security());
        $this->assertFalse($int->is_string());
        $this->assertFalse($int->is_matrix());
        $this->assertTrue($int->is_int());
        $this->assertFalse($int->is_float());
        $this->assertEquals(-1, $int->is_list());

        $list = stack_ast_container::make_from_teacher_source('x:[1,2,3]', 'type test', new stack_cas_security());
        $this->assertFalse($list->is_string());
        $this->assertFalse($list->is_matrix());
        $this->assertFalse($list->is_int());
        $this->assertFalse($list->is_float());
        $this->assertEquals(3, $list->is_list());
    }

    public function test_list_accessor(): void {

        $list = stack_ast_container::make_from_teacher_source('x:[1,2*x,3-4]', 'list access test', new stack_cas_security());
        $this->assertEquals(3, $list->is_list());

        $this->assertEquals('1', $list->get_list_element(0)->toString());
        $this->assertEquals('2*x', $list->get_list_element(1)->toString());
        $this->assertEquals('3-4', $list->get_list_element(2)->toString());
    }

    // phpcs:ignore moodle.Commenting.MissingDocblock.MissingTestcaseMethodDescription
    public function get_valid($s, $st, $te) {

        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertEquals($st, $at1->get_valid());

        $at2 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertEquals($te, $at2->get_valid());
    }

    public function test_get_valid(): void {

        $cases = [
            ['', false, true],
            ['1', true, true],
            ['a b', true, false],
            ['%pi', true, true], // Only %pi %e, %i, %gamma, %phi.
            ['1+%e', true, true],
            ['e^%i*%pi', true, true],
            ['%gamma', true, true],
            ['%phi', true, true],
            // Use of % sign is a change here in STACK 4.3.
            ['%o1', true, true],
            // Literal unicode character (pi) instead of name.
            [json_decode('"\u03C0"'), true, true],
            [json_decode('"\u2205"'), true, true],
            // Non-matching brackets.
            ['(x+1', false, false],
            ['(y^2+1))', false, false],
            ['[sin(x)+1)', false, false],
            ['([y^2+1)]', false, false],
            // Function which does not appears on the teacher's list.
            ['setelmx(2,1,1,C)', false, true],
            ['2*reallytotalnonsensefunction(x)', false, true],
            ['system("rm *")', false, false], // This should never occur.
            ['"system(rm *)"', true, true], // There is nothing wrong with this.
            ['$', false, false],
            ['@', false, false],
            // Inequalities.
            ['x>=1', true, true],
            ['x=>1', false, false],
            // Unencapsulated commas. Also evaluation flags.
            ['a,b', false, true],
        ];

        foreach ($cases as $case) {
            $this->get_valid($case[0], $case[1], $case[2]);
        }
    }

    public function test_get_valid_inequalities(): void {

        $cases = [
            ['x>1 and x<4', true, true],
            ['not (x>1)', true, true],
            ['x<=2 or not (x>1)', true, true],
            ['x<1 or (x>1 and t<sin(x))', true, true],
            ['[x<1, x>3]', true, true],
            ['pg:if x<x0 then f0 else if x<x1 then 1000 else f1', true, true],
                // Change for issue #324 now stops checking chained inequalities for teachers.
                // And change again, chained inequalities are now again checked always we just have less false positives.
            ['1<x<7', false, false],
            ['1<a<=x^2', false, false],
            ['{1<x<y, c>0}', false, false],
        ];

        foreach ($cases as $case) {
            $this->get_valid($case[0], $case[1], $case[2]);
        }
    }

    public function test_validation_alias(): void {

        $casstring = stack_ast_container::make_from_student_source(json_decode('"\u03C0"').'*r^2', '', new stack_cas_security());
        $casstring->get_valid();
        $this->assertEquals($casstring->get_evaluationform(), '%pi*r^2');
        $this->assertEquals('', $casstring->get_errors());
        $this->assertEquals('', $casstring->get_answernote());
    }

    public function test_validation_unicode(): void {

        // Note the error with the * in this expression.
        $casstring = stack_ast_container::make_from_student_source(json_decode('"\u212F"').'*^x', '', new stack_cas_security());
        $casstring->get_valid();
        $this->assertEquals('Expected "#pm#", "%not ", "\'", "\'\'", "(", "+", "+-", "-", "? ", "?", "?? ", "[", "do", ' .
            '"for", "from", "if", "in", "next", "not ", "not", "nounnot ", "nounnot", "step", "thru", "unless", ' .
            '"while", "{", "|", boolean, float, identifier, integer, string or whitespace but "^" found.',
            $casstring->get_errors());
        $this->assertEquals('ParseError', $casstring->get_answernote());
    }

    public function test_validation_error(): void {

        // Consider A union B.
        $casstring = stack_ast_container::make_from_student_source('A ' . json_decode('"\u222A"') . ' B', '',
                new stack_cas_security());
        $casstring->get_valid();
        $this->assertEquals(stack_string('stackCas_forbiddenChar', ['char' => json_decode('"\u222A"')]),
                $casstring->get_errors());
        $this->assertEquals('forbiddenChar', $casstring->get_answernote());
    }

    public function test_spurious_operators(): void {

        $casstring = stack_ast_container::make_from_student_source('2/*x', '', new stack_cas_security());
        $casstring->get_valid();
        $this->assertEquals('Unknown operator: <span class="stacksyntaxexample">/*</span>.',
                $casstring->get_errors());
        $this->assertEquals('spuriousop', $casstring->get_answernote());
    }

    public function test_spurious_operators_2(): void {

        $casstring = stack_ast_container::make_from_student_source('x==2*x', '', new stack_cas_security());
        $casstring->get_valid();
        $this->assertEquals('Unknown operator: <span class="stacksyntaxexample">==</span>.',
                $casstring->get_errors());
        $this->assertEquals('spuriousop', $casstring->get_answernote());
    }

    public function test_global_forbidden_words(): void {

        $s = 'system("rm *")';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">system</span>.',
                $at1->get_errors());
        $this->assertEquals('forbiddenFunction', $at1->get_answernote());

        $at2 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at2->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">system</span>.',
                $at2->get_errors());
        $this->assertEquals('forbiddenFunction', $at1->get_answernote());
    }

    public function test_global_forbidden_words_case(): void {

        // This is a change of behaviour in Dec 2018.
        $s = 'System("rm *")';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Input is case sensitive: <span class="stacksyntaxexample">System</span> is an unknown function. ' .
                'Did you mean <span class="stacksyntaxexample">system</span>?', $at1->get_errors());
        $this->assertEquals('unknownFunctionCase', $at1->get_answernote());

        // This is a change of behaviour in July 2019.
        $at2 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertTrue($at2->get_valid());
        $this->assertEquals('', $at2->get_answernote());
    }

    public function test_teacher_only_words(): void {

        $s = 'setelmx(2,1,1,C)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">setelmx</span>.',
                $at1->get_errors());

        $at2 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertTrue($at2->get_valid());
        $this->assertEquals('', $at2->get_errors());
    }

    public function test_allow_words(): void {

        $s = '2*dumvariable+3';
        $secrules = new stack_cas_security();
        $secrules->set_allowedwords('dumvariable');
        $at1 = stack_ast_container::make_from_student_source($s, '', $secrules);
        $this->assertTrue($at1->get_valid());
    }

    public function test_allow_words_fail(): void {

        $s = 'sin(2*dumvariable+3)';
        $secrules = new stack_cas_security();
        $secrules->set_allowedwords('dvariable');
        $at1 = stack_ast_container::make_from_student_source($s, '', $secrules);
        $this->assertFalse($at1->get_valid());
    }

    public function test_allow_words_teacher(): void {

        $s = 'sin(2*dumvariable+3)';
        $secrules = new stack_cas_security();
        $secrules->set_allowedwords('dvariable');
        $at1 = stack_ast_container::make_from_teacher_source($s, '', $secrules);
        $this->assertTrue($at1->get_valid());
    }

    public function test_check_external_forbidden_words(): void {

        $cases = [
            ['sin(ta)', 'ta', false, 'forbiddenVariable'],
            ['sin(ta)', 'ta,a,b', false, 'forbiddenVariable'],
            ['1+sin(ta)', 'a,b', true, ''],
            ['sin(ta)', 'sa', true, ''],
            ['sin(ta)', 'sin', false, 'forbiddenFunction'],
            ['sin(a)', 'a', false, 'forbiddenVariable'],
            ['diff(x^2,x)', '[[basic-calculus]]', false, 'forbiddenFunction'],
            ['diff(x^2,x)', '[[BASIC-CALCULUS]]', false, 'forbiddenFunction'],
        ];

        foreach ($cases as $case) {
            $secrules = new stack_cas_security();
            $secrules->set_forbiddenwords($case[1]);
            $cs = stack_ast_container::make_from_student_source($case[0], '', $secrules);
            $this->assertEquals($case[2], $cs->get_valid());
            $this->assertEquals($case[3], $cs->get_answernote());
        }
    }

    public function test_check_external_forbidden_words_literal(): void {

        $cases = [
            ['3+5', '+', false],
            ['sin(a)', 'a', false], // It includes single letters.
            // Changed in 4.3.
            ['sin(a)', 'i', true], // Since it is a string match, this can be inside a name.
            ['sin(a)', 'b', true],
            // The below test of escaped commas is why we ignore MP_Checking_Geoups in 998_security.filter.php.
            ['sin(a)', 'b,\,,c', true], // Test escaped commas.
            ['[x,y,z]', 'b,\,,c', false],
            ['diff(x^2,x)', '[[BASIC-CALCULUS]]', false], // From lists.
            ['solve((x-6)^4,x)', '[[BASIC-ALGEBRA]]', false], // From lists.
            ['sin(A-B)', '[[BASIC-TRIG]]', false], // From lists.
        ];

        foreach ($cases as $case) {
            $secrules = new stack_cas_security();
            $secrules->set_forbiddenwords($case[1]);
            $cs = stack_ast_container::make_from_student_source($case[0], '', $secrules);
            $this->assertEquals($case[2], $cs->get_valid());
        }
    }

    public function test_strings_1(): void {

        $s = 'a:"hello"';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
    }

    public function test_strings_2(): void {

        $s = 'a:["2x)",3*x]';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
    }

    public function test_strings_mismatched_string_delimiters(): void {

        $s = 'a:""hello""';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());

        $s = 'a:"hello"   "hello"';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());

        $s = 'a:"hello"5';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());

        $s = 'a:"hello"*5';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        // Not a valid mathematical operation, but a valid parse tree.
        $this->assertTrue($at1->get_valid());

        $s = 'a:"hello"  +  "hello"';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        // Not a valid mathematical operation, but a valid parse tree.
        $this->assertTrue($at1->get_valid());

        $s = 'a:(5)*"hello"';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        // Not a valid mathematical operation, but a valid parse tree.
        $this->assertTrue($at1->get_valid());

        $s = 'a:(5)/"hello"';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        // Not a valid mathematical operation, but a valid parse tree.
        $this->assertTrue($at1->get_valid());

        $s = 'a:5-"hello"';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        // Not a valid mathematical operation, but a valid parse tree.
        $this->assertTrue($at1->get_valid());

        $s = 'a:[{"hello"},"hello",["hello"]]';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());

        $s = 'a:cos(pi)"hello"';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());

        $s = 'a:[{"hello"}"hello"["hello"]]';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());

        $s = 'a:"hello';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertEquals('You are missing a quotation sign <code>"</code>. ', $at1->get_errors());
    }

    public function test_system_execution(): void {

        // First the obvious one, just eval that string.
        $s = 'a:eval_string("system(\\"rm /tmp/test\\")")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">eval_string</span>.',
                $at1->get_errors());

        $s = 'a:eval_string("system(rm /tmp/test)")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">eval_string</span>.',
                $at1->get_errors());

        // The second requires a bit more, parse but do the eval later.
        $s = 'a:ev(parse_string("system(\\"rm /tmp/test\\")"),eval)';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">parse_string</span>.',
                $at1->get_errors());

        // Then things get tricky, one needs to write the thing out and eval when reading in.
        // Luckilly, appendfile, save, writefile, and stringout commands would require manual eval.
        // But lets test them anyway.
        $s = 'a:appendfile("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">appendfile</span>.',
                $at1->get_errors());

        $s = 'a:writefile("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">writefile</span>.',
                $at1->get_errors());

        $s = 'a:save("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">save</span>.',
                $at1->get_errors());

        $s = 'a:stringout("/tmp/test", "system(\\"rm /tmp/test\\");")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">stringout</span>.',
                $at1->get_errors());

        // The corresponding read commands load, loadfile, batch, and batchload are all bad.
        $s = 'a:load("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">load</span>.',
                $at1->get_errors());

        $s = 'a:loadfile("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">loadfile</span>.',
                $at1->get_errors());

        $s = 'a:batch("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">batch</span>.',
                $at1->get_errors());

        $s = 'a:batchload("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">batchload</span>.',
                $at1->get_errors());

        // Naturally, lower level functions can allow you to actually edit or generate files to execute.
        // The opena, openw, and openr and their binary versions could do even more harm.
        // Even scarier is naturally, the possibility to edit files...
        $s = 'a:opena("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">opena</span>.',
                $at1->get_errors());

        $s = 'a:openw("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">openw</span>.',
                $at1->get_errors());

        $s = 'a:openr("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">openr</span>.',
                $at1->get_errors());

        $s = 'a:opena_binary("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">opena_binary</span>.',
                $at1->get_errors());

        $s = 'a:openw_binary("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">openw_binary</span>.',
                $at1->get_errors());

        $s = 'a:openr_binary("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">openr_binary</span>.',
                $at1->get_errors());

        // And lets not forget being able to output file contents.
        $s = 'a:printfile("/tmp/test")';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('Forbidden function: <span class="stacksyntaxexample">printfile</span>.',
                $at1->get_errors());

        // And then there is the possibility of using lisp level functions.
        $s = ':lisp (with-open-file (stream "/tmp/test" :direction :output) (format stream "system(\\"rm /tmp/test\\")"))';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('The expression <span class="stacksyntaxexample">lisp</span> is forbidden.',
                $at1->get_errors());

        // That last goes wrong due to "strings" not being usable in the lisp way.
        // Assuming those are in variables we can try this.
        $s = ':lisp (with-open-file (stream a :direction :output) (format stream b))';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('The expression <span class="stacksyntaxexample">lisp</span> is forbidden.',
                $at1->get_errors());
    }

    public function test_scientific_1(): void {

        $s = 'a:3e2';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('', $at1->get_answernote());
    }

    public function test_trig_1(): void {

        $s = 'a:sin[2*x]';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('trigparens', $at1->get_answernote());
    }

    public function test_trig_2(): void {

        $s = 'a:cot*2*x';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('forbiddenVariable', $at1->get_answernote());
    }

    public function test_trig_3(): void {

        $s = 'a:tan^-1(x)-1';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('missing_stars | trigexp', $at1->get_answernote());
    }

    public function test_trig_4(): void {

        $s = 'a:sin^2(x)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('missing_stars | trigexp', $at1->get_answernote());
    }

    public function test_trig_5(): void {

        $s = 'a:Sim(x)-1';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('forbiddenFunction', $at1->get_answernote());
    }

    public function test_trig_6(): void {

        $s = 'a:Sin(x)-1';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('unknownFunctionCase', $at1->get_answernote());
    }

    public function test_in_1(): void {

        $s = 'a:1+In(x)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('stackCas_badLogIn', $at1->get_answernote());
    }

    public function test_in_2(): void {

        $s = 'a:1+In(x)';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
    }

    public function test_greek_1(): void {

        $s = 'a:Delta-1';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
    }

    public function test_greek_2(): void {

        $s = 'a:DELTA-1';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('unknownVariableCase', $at1->get_answernote());
        // Note the capital D in the feedback here.  We suggest a capital Delta.
        $this->assertEquals('Input is case sensitive: <span class="stacksyntaxexample">DELTA</span> '.
                'is an unknown variable. Did you mean <span class="stacksyntaxexample">Delta, delta</span>?',
                $at1->get_errors());
    }

    public function test_unencapsulated_commas_1(): void {

        $s = 'a,b';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('unencapsulated_comma', $at1->get_answernote());
    }

    public function test_forbid_function_single_letter(): void {

        $s = 'a:x^2+a+f(x)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());

        $secrules = new stack_cas_security();
        $secrules->set_forbiddenkeys(['f']);
        // This next test returns true, because the check only looks for keys longer than one character.
        // The caching of get_valid is something that might need some work.
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
    }

    public function test_implied_complex_mult1(): void {

        $s = '-(1/512)+i(sqrt(3)/512)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('-(1/512)+i*((%_C(sqrt),sqrt(3))/512)', $at1->get_evaluationform());
        $this->assertEquals('-(1/512)+i*(sqrt(3)/512)', $at1->get_inputform());
    }

    public function test_implied_complex_mult2(): void {

        $s = '-(1/512)+i(sqrt(3)/512)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
    }

    public function test_semicolon(): void {

        $s = 'a:3;b:4';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        // This is a change in STACK 4.3.
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('', $at1->get_answernote());
    }

    public function test_log_sugar_1(): void {

        $s = 'log(x)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        // Note that get_evaluationform is the string actually sent to Maxima, so this test case should have _C.
        $this->assertEquals('(%_C(log),log(x))', $at1->get_evaluationform());
        $this->assertEquals('log(x)', $at1->get_inputform());
    }

    public function test_log_sugar_2(): void {

        $s = 'log_10(a+x^2)+log_a(b)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('(%_C(lg),lg(a+x^2,10))+(%_C(lg),lg(b,a))', $at1->get_evaluationform());
        $this->assertEquals('lg(a+x^2,10)+lg(b,a)', $at1->get_inputform());
        $this->assertEquals('logsubs', $at1->get_answernote());
    }

    public function test_log_sugar_3(): void {

        // Note that STACK spots there is a missing * here.
        // Note that in the new 4.3 world we need to define a filter to note
        // the star we do not want to be inserted.
        $s = 'log_5x(3)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security(), ['991_no_fixing_stars']);
        $this->assertFalse($at1->get_valid());
        // There would be a star there but as it is now an invalid thing you cannot see it.
        // It is not lg(3,5*x), as it would have been in the past.
        $this->assertEquals('missing_stars | logsubs', $at1->get_answernote());
    }

    public function test_log_sugar_4(): void {

        // The missing * in this expression is correctly inserted.
        $s = 'log_5x(3)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('(%_C(lg),lg(3,5*x))', $at1->get_evaluationform());
        $this->assertEquals('lg(3,5*x)', $at1->get_inputform());
        $this->assertEquals('missing_stars | logsubs', $at1->get_answernote());
    }

    public function test_log_sugar_5(): void {

        $s = 'log_x^2(3)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('(%_C(lg),lg(3,x^2))', $at1->get_evaluationform());
        $this->assertEquals('lg(3,x^2)', $at1->get_inputform());
        $this->assertEquals('missing_stars | logsubs', $at1->get_answernote());
    }

    public function test_log_sugar_6(): void {

        $s = 'log_%e(%e)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('(%_C(lg),lg(%e,%e))', $at1->get_evaluationform());
        $this->assertEquals('lg(%e,%e)', $at1->get_inputform());
        $this->assertEquals('logsubs', $at1->get_answernote());
    }

    public function test_unary_plus(): void {

        // This is an interesting parser edge case.
        $s = 'p:+a^b*c';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('p:+a^b*c', $at1->get_evaluationform());
        $this->assertEquals('', $at1->get_answernote());
    }

    public function test_chained_inequalities_s(): void {

        $s = 'sa:3<x<5';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertFalse($at1->get_valid());
        $this->assertEquals('chained_inequalities', $at1->get_answernote());
    }

    public function test_chained_inequalities_t(): void {

        $s = 'f(x) := if x < 0 then (if x < 1 then 1 else 2) else 3';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('f(x):=if x < 0 then (if x < 1 then 1 else 2) else 3', $at1->get_evaluationform());
        $this->assertEquals('', $at1->get_answernote());
    }

    public function test_decimal_digits(): void {

        $tests = stack_numbers_test_data::get_raw_test_data();

        foreach ($tests as $t) {
            $ast = stack_ast_container::make_from_student_source($t[0], '', new stack_cas_security());
            $r = $ast->get_decimal_digits();
            $this->assertEquals($r['lowerbound'], $t[1]);
            $this->assertEquals($r['upperbound'], $t[2]);
            $this->assertEquals($r['decimalplaces'], $t[3]);
            $this->assertEquals($r['fltfmt'], $t[4]);
        }

    }

    public function test_decimal_digits_utils(): void {

        $tests = stack_numbers_test_data::get_raw_test_data_utils();

        foreach ($tests as $t) {
            $ast = stack_ast_container::make_from_student_source($t[0], '', new stack_cas_security());
            $r = $ast->get_decimal_digits();
            $this->assertEquals($r['lowerbound'], $t[1]);
            $this->assertEquals($r['upperbound'], $t[2]);
            $this->assertEquals($r['decimalplaces'], $t[3]);
            $this->assertEquals($r['fltfmt'], $t[4]);
        }
    }

    public function test_spaces_1_brackets(): void {

        $s = 'a (b c)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('a*(b*c)', $at1->get_inputform());
        $err = '';
        $this->assertEquals($err, $at1->get_errors());
        $this->assertEquals('spaces', $at1->get_answernote());
    }

    public function test_spaces_1_bracket_brackets(): void {

        $s = '(1+c) (x+1)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('(1+c)*(x+1)', $at1->get_inputform());
        $err = '';
        $this->assertEquals($err, $at1->get_errors());
        $this->assertEquals('spaces', $at1->get_answernote());
    }

    public function test_spaces_1_logic(): void {

        $s = 'a b and c';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('a*b and c', $at1->get_inputform());
        $err = '';
        $this->assertEquals($err, $at1->get_errors());
        $this->assertEquals('spaces', $at1->get_answernote());
    }

    public function test_remove_add_nouns(): void {

        $s = "['sum(k^2,k,1,n),'product(k^2,k,1,n),a nounand b, noundiff(y,x)+y=0, nounnot false, nounnot(false)]";
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $err = '';
        $this->assertEquals($err, $at1->get_errors());

        // Noun operators protected by ' are skipped in the 996 filter..
        $s = "['sum(k^2,k,1,n),'product(k^2,k,1,n),a nounand b," .
            "(%_C(noundiff),noundiff(y,x))+y = 0,nounnot false,nounnot(false)]";
        $this->assertEquals($s, $at1->get_evaluationform());
        // The subtle change of spaces after commas and equals signs shows the parser is re-displaying the expression.
        $this->assertEquals("['sum(k^2,k,1,n),'product(k^2,k,1,n),a nounand b,noundiff(y,x)+y = 0," .
                "nounnot false,nounnot(false)]",
                $at1->get_inputform());

        $at1->set_nounify(0);
        // Remove all nouns when evaluating.
        // Since 'sum was not protected by 996, it is not protected now.
        $s = "[sum(k^2,k,1,n),product(k^2,k,1,n),a and b,(%_C(noundiff)," .
                "diff(y,x))+y = 0,not false,not(false)]";
        $this->assertEquals($s, $at1->get_evaluationform());
        // Get input form also removes noun forms.
        $this->assertEquals("[sum(k^2,k,1,n),product(k^2,k,1,n),a and b,diff(y,x)+y = 0,not false,not(false)]",
            $at1->get_inputform(true, 0));

        // This example has only one noun on the product.
        // Sum will get protected by %_C in the evaluation form, but product will not.
        $s = "[sum(k^2,k,1,n),'product(k^2,k,1,n),a and b, diff(y,x)+y=0, not false, not(false)]";
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());
        $err = '';
        $this->assertEquals($err, $at1->get_errors());

        $s = "[(%_C(sum),sum(k^2,k,1,n)),'product(k^2,k,1,n),a and b,(%_C(diff),diff(y,x))+y = 0," .
                "not false,not(false)]";
        $this->assertEquals($s, $at1->get_evaluationform());
        $this->assertEquals("[sum(k^2,k,1,n),'product(k^2,k,1,n),a and b,diff(y,x)+y = 0,not false,not(false)]",
                $at1->get_inputform());

        $at1->set_nounify(0);
        $s = "[(%_C(sum),sum(k^2,k,1,n)),product(k^2,k,1,n),a and b,(%_C(diff),diff(y,x))+y = 0," .
                "not false,not(false)]";
        $this->assertEquals($s, $at1->get_evaluationform());
        $this->assertEquals("[sum(k^2,k,1,n),product(k^2,k,1,n),a and b,diff(y,x)+y = 0,not false,not(false)]",
                $at1->get_inputform(true, 0));

        $at1->set_nounify(1);
        // We don't add apostophies where they don't exist.
        $s = "[(%_C(sum),sum(k^2,k,1,n)),'product(k^2,k,1,n),a nounand b,(%_C(diff)," .
                "noundiff(y,x))+y = 0,nounnot false,nounnot(false)]";
        $this->assertEquals($s, $at1->get_evaluationform());
        $this->assertEquals("[sum(k^2,k,1,n),'product(k^2,k,1,n),a nounand b,noundiff(y,x)+y = 0," .
            "nounnot false,nounnot(false)]",
            $at1->get_inputform(true, 1));

        $at1->set_nounify(2);
        // We only add apostophies to logic nouns.
        $s = "[(%_C(sum),sum(k^2,k,1,n)),'product(k^2,k,1,n),a nounand b,(%_C(diff)," .
                "diff(y,x))+y = 0,nounnot false,nounnot(false)]";
        $this->assertEquals($s, $at1->get_evaluationform());
        $this->assertEquals("[sum(k^2,k,1,n),'product(k^2,k,1,n),a nounand b,diff(y,x)+y = 0," .
            "nounnot false,nounnot(false)]",
            $at1->get_inputform(true, 2));
    }

    public function test_stacklet(): void {

        $s = 'stacklet(a,x*%i+y)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());

        $this->assertTrue($at1->get_valid());

        // Note that stacklet() is held as a function, and not parsed into MP_Let.
        $expected = '([FunctionCall: ([Id] stacklet)] ([Id] a),([Op: +] ([Op: *] ([Id] x), ([Id] %i)), ([Id] y)))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));

        $this->assertEquals('(%_C(stacklet),stacklet(a,x*%i+y))', $at1->get_evaluationform());
        $this->assertEquals('stacklet(a,x*%i+y)', $at1->get_inputform());
        // Must have nounify=0 here to force into "let ...." style.
        $this->assertEquals('let a=x*%i+y', $at1->get_inputform(true, 0));

        $err = '';
        $this->assertEquals($err, $at1->get_errors());
        $this->assertEquals('', $at1->get_answernote());
    }

    public function test_pm(): void {

        $s = 'a+-b';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());

        $expected = '([Op: +-] ([Id] a), ([Id] b))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));

        $this->assertEquals('a#pm#b', $at1->get_evaluationform());
        $this->assertEquals('a+-b', $at1->get_inputform(true, 0));

        $err = '';
        $this->assertEquals($err, $at1->get_errors());
        $this->assertEquals('', $at1->get_answernote());

        $s = 'a#pm#b';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());

        $expected = '([Op: #pm#] ([Id] a), ([Id] b))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));

        $this->assertEquals('a#pm#b', $at1->get_evaluationform());
        $this->assertEquals('a+-b', $at1->get_inputform(true, 0));

        $err = '';
        $this->assertEquals($err, $at1->get_errors());
        $this->assertEquals('', $at1->get_answernote());

        $s = '+-a';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());

        $expected = '([PrefixOp: +-] ([Id] a))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));

        $this->assertEquals('#pm#a', $at1->get_evaluationform());
        $this->assertEquals('+-a', $at1->get_inputform(true, 0));

        $err = '';
        $this->assertEquals($err, $at1->get_errors());
        $this->assertEquals('', $at1->get_answernote());

        $s = '#pm#a';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());

        $expected = '([PrefixOp: #pm#] ([Id] a))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));

        $this->assertEquals('#pm#a', $at1->get_evaluationform());
        $this->assertEquals('+-a', $at1->get_inputform(true, 0));

        $err = '';
        $this->assertEquals($err, $at1->get_errors());
        $this->assertEquals('', $at1->get_answernote());
    }

    public function test_input_varmatix(): void {

        $s = 'matrix([a,b],[c,d])';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $expected = '([FunctionCall: ([Id] matrix)] ([List] ([Id] a), ([Id] b)),([List] ([Id] c), ([Id] d)))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));
        $this->assertTrue($at1->get_valid());
        $this->assertEquals("a b\nc d", $at1->ast_to_string(null, ['inputform' => true, 'varmatrix' => true]));

        $s = 'matrix([{1,2},[a,b]])';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $expected = '([FunctionCall: ([Id] matrix)] ([List] ([Set] ([Int] 1), ([Int] 2)), ([List] ([Id] a), ([Id] b))))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));
        $this->assertTrue($at1->get_valid());
        $this->assertEquals("{1,2} [a,b]", $at1->ast_to_string(null,
            ['inputform' => true, 'varmatrix' => true]));

        // This is a crazy example because the rows are different lengths.  So what?
        $s = 'matrix([matrix([a,b])],[a,b])';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $expected = '([FunctionCall: ([Id] matrix)] ([List] ([FunctionCall: ([Id] matrix)] ' .
                '([List] ([Id] a), ([Id] b)))),([List] ([Id] a), ([Id] b)))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));
        $this->assertTrue($at1->get_valid());
        // This is to record the behaviour only.  It isn't a sensible example.
        $this->assertEquals("matrix([a,b])\na b", $at1->ast_to_string(null,
            ['inputform' => true, 'varmatrix' => true]));
    }

    public function test_ntuple(): void {

        $s = '(x,y)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security());
        $this->assertTrue($at1->get_valid());

        $expected = '([Group] ([Id] x),([Id] y))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));

        $this->assertEquals('(x,y)', $at1->get_evaluationform());
        $this->assertEquals('(x,y)', $at1->get_inputform(true, 0, true));

        $filterstoapply = ['504_insert_tuples_for_groups'];
        $s = '(x,y)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security(), $filterstoapply);
        $this->assertTrue($at1->get_valid());

        $expected = '([FunctionCall: ([Id] ntuple)] ([Id] x),([Id] y))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));

        $this->assertEquals('(%_C(ntuple),ntuple(x,y))', $at1->get_evaluationform());
        $this->assertEquals('ntuple(x,y)', $at1->get_inputform());
        $this->assertEquals('(x,y)', $at1->get_inputform(true, 0, true));

        // Nested tuples are fine (if a bit odd....).
        $filterstoapply = ['504_insert_tuples_for_groups'];
        $s = '(a,(x,y))';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security(), $filterstoapply);
        $this->assertTrue($at1->get_valid());

        $expected = '([FunctionCall: ([Id] ntuple)] ([Id] a),([FunctionCall: ([Id] ntuple)] ([Id] x),([Id] y)))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));

        $this->assertEquals('(%_C(ntuple),ntuple(a,(%_C(ntuple),ntuple(x,y))))', $at1->get_evaluationform());
        $this->assertEquals('ntuple(a,ntuple(x,y))', $at1->get_inputform());
        $this->assertEquals('(a,(x,y))', $at1->get_inputform(true, 0, true));

        $filterstoapply = ['504_insert_tuples_for_groups'];
        $s = '((x,y),a)';
        $at1 = stack_ast_container::make_from_student_source($s, '', new stack_cas_security(), $filterstoapply);
        $this->assertTrue($at1->get_valid());

        $expected = '([FunctionCall: ([Id] ntuple)] ([FunctionCall: ([Id] ntuple)] ([Id] x),([Id] y)),([Id] a))';
        $this->assertEquals($expected, $at1->ast_to_string(null, ['flattree' => true]));

        $this->assertEquals('(%_C(ntuple),ntuple((%_C(ntuple),ntuple(x,y)),a))', $at1->get_evaluationform());
        $this->assertEquals('ntuple(ntuple(x,y),a)', $at1->get_inputform());
        $this->assertEquals('((x,y),a)', $at1->get_inputform(true, 0, true));

    }

    public function test_identify_simplification_modifications(): void {

        $t1 = 'foo+bar';
        $t1 = stack_ast_container::make_from_teacher_source($t1, '', new stack_cas_security());
        $t1 = $t1->identify_simplification_modifications();
        $this->assertFalse($t1['simp-accessed']);
        $this->assertFalse($t1['simp-modified']);
        $this->assertFalse($t1['out-of-ev-write']);
        $this->assertEquals($t1['last-seen'], null);

        $t2 = '3/9,simp=false';
        $t2 = stack_ast_container::make_from_teacher_source($t2, '', new stack_cas_security());
        $t2 = $t2->identify_simplification_modifications();
        $this->assertTrue($t2['simp-accessed']);
        $this->assertTrue($t2['simp-modified']);
        $this->assertFalse($t2['out-of-ev-write']);
        $this->assertEquals($t2['last-seen'], false);

        // Issue #849.
        $t3 = '(simp:false,3/9)';
        $t3 = stack_ast_container::make_from_teacher_source($t3, '', new stack_cas_security());
        $t3 = $t3->identify_simplification_modifications();
        $this->assertTrue($t3['simp-accessed'], "a");
        $this->assertTrue($t3['simp-modified'], "b");
        $this->assertTrue($t3['out-of-ev-write'], "c");
        $this->assertEquals($t3['last-seen'], false);

    }

    public function test_teacher_answer_decimals(): void {

        // This tests the functions which generate "The teacher's answer is".
        $s = '{4.4,4}';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $at1->set_nounify(0);
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('', $at1->get_errors());
        $this->assertEquals($at1->get_inputform(true, 0, true, ','), '{4,4;4}');

        $s = '[4.4,4]';
        $at1 = stack_ast_container::make_from_teacher_source($s, '', new stack_cas_security());
        $at1->set_nounify(0);
        $this->assertTrue($at1->get_valid());
        $this->assertEquals('', $at1->get_errors());
        $this->assertEquals($at1->get_inputform(true, 0, true, ','), '[4,4;4]');
    }
}
