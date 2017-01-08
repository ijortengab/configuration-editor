<?php

/**
 * Example: Auto increment indexed array.
 *
 * Attention: Run ```composer install``` first before execute this file.
 * @see: example/README.md
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/ijortengab/tools/functions/var_dump.php';
require __DIR__ . '/../../vendor/ijortengab/tools/functions/tmpfile.php';
use IjorTengab\ConfigurationEditor\ConfigurationEditor;
use function IjorTengab\Override\PHP\VarDump\var_dump;
use const IjorTengab\Override\PHP\VarDump\SUBJECT;
use const IjorTengab\Override\PHP\VarDump\COMMENT;
use const IjorTengab\Override\PHP\VarDump\LINE;
use function IjorTengab\Override\PHP\TmpFile\tmpfile;

// PHP Array.
$array = ['0', '1', '2'];

// Current value.
var_dump($array, SUBJECT | COMMENT | LINE);

// Delete.
unset($array[1]);
unset($array[2]);

// Current value.
var_dump($array, SUBJECT | COMMENT | LINE);

// Insert sequence with key auto increment.
$array[] = 'Whats Key Number? The Answer is 3';

// Current value.
var_dump($array, SUBJECT | COMMENT | LINE);

// Let's do the same thing.

// Init.
$config = ConfigurationEditor::load(['file' => tmpfile(), 'format' => 'ini']);

$config->setArrayData(['0', '1', '2'])->save();

// Lets see current value and content.
var_dump($config->getData(), SUBJECT | COMMENT | LINE);
var_dump((string) $config, SUBJECT | COMMENT | LINE);

// Delete.
$config->delData('1')->delData('2')->save();

// Lets see current value and content.
var_dump($config->getData(), SUBJECT | COMMENT | LINE);
var_dump((string) $config, SUBJECT | COMMENT | LINE);

// Insert sequence with key auto increment.
$config->setData('[]', 'Whats Key Number? The Answer is 3')->save();

// Lets see current value and content.
var_dump($config->getData(), SUBJECT | COMMENT | LINE);
var_dump((string) $config, SUBJECT | COMMENT | LINE);

/**
 * Line: 24
 * var_dump($array):
 * array(3) {
 *     [0]=>
 *     string(1) "0"
 *     [1]=>
 *     string(1) "1"
 *     [2]=>
 *     string(1) "2"
 * }
 */
/**
 * Line: 31
 * var_dump($array):
 * array(1) {
 *     [0]=>
 *     string(1) "0"
 * }
 */
/**
 * Line: 37
 * var_dump($array):
 * array(2) {
 *     [0]=>
 *     string(1) "0"
 *     [3]=>
 *     string(33) "Whats Key Number? The Answer is 3"
 * }
 */
/**
 * Line: 47
 * var_dump($config->getData()):
 * array(3) {
 *     [0]=>
 *     string(1) "0"
 *     [1]=>
 *     string(1) "1"
 *     [2]=>
 *     string(1) "2"
 * }
 */
/**
 * Line: 48
 * var_dump((string) $config):
 * string(27) "[] = '0'
 * [] = '1'
 * [] = '2'
 * "
 */
/**
 * Line: 54
 * var_dump($config->getData()):
 * array(1) {
 *     [0]=>
 *     string(1) "0"
 * }
 */
/**
 * Line: 55
 * var_dump((string) $config):
 * string(9) "[] = '0'
 * "
 */
/**
 * Line: 61
 * var_dump($config->getData()):
 * array(2) {
 *     [0]=>
 *     string(1) "0"
 *     [3]=>
 *     string(33) "Whats Key Number? The Answer is 3"
 * }
 */
/**
 * Line: 62
 * var_dump((string) $config):
 * string(46) "0 = '0'
 * 3 = Whats Key Number? The Answer is 3
 * "
 */
