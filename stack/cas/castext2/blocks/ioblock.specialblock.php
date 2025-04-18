<?php
// This file is part of Stateful
//
// Stateful is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stateful is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stateful.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Add description here!
 * @package    qtype_stack
 * @copyright  2024 University of Edinburgh.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../block.interface.php');

// phpcs:ignore moodle.Commenting.MissingDocblock.Class
class stack_cas_castext2_special_ioblock extends stack_cas_castext2_block {
    // phpcs:ignore moodle.Commenting.VariableComment.Missing
    public $channel;
    // phpcs:ignore moodle.Commenting.VariableComment.Missing
    public $variable;

    // phpcs:ignore moodle.Commenting.MissingDocblock.Function
    public function __construct($params, $children=[], $mathmode=false, $channel='', $variable='') {
        parent::__construct($params, $children, $mathmode);
        $this->channel = $channel;
        $this->variable = $variable;
    }

    // phpcs:ignore moodle.Commenting.MissingDocblock.Function
    public function compile($format, $options): ?MP_Node {
        // If used before input2 we do not need to maintain the parsed structure.
        // If we do not need the structure we can cut down on processign and compile
        // directly to a string.
        if (isset($options['io-blocks-as-raw']) && $options['io-blocks-as-raw'] === 'pre-input2') {
            return new MP_String('[[' . $this->channel . ':' . $this->variable . ']]');
        }
        return new MP_List([new MP_String('ioblock'), new MP_String($this->channel), new MP_String($this->variable)]);
    }

    // phpcs:ignore moodle.Commenting.MissingDocblock.Function
    public function is_flat(): bool {
        return false;
    }

    // phpcs:ignore moodle.Commenting.MissingDocblock.Function
    public function validate_extract_attributes(): array {
        return [];
    }

    // Might seem odd to postprocess this but this is a hook that others connect to.
    // phpcs:ignore moodle.Commenting.MissingDocblock.Function
    public function postprocess(array $params, castext2_processor $processor,
        castext2_placeholder_holder $holder): string {
        return '[[' . $params[1] . ':' . $params[2] . ']]';
    }

}
