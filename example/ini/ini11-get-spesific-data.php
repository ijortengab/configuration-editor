<?php

/**
 * Example: Create Simple Data.
 *
 * Attention: Run ```composer install``` first before execute this file.
 * @see: example/README.md
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/ijortengab/tools/functions/var_dump.php';
use IjorTengab\ConfigurationEditor\ConfigurationEditor;
use function IjorTengab\Override\PHP\VarDump\var_dump;
use const IjorTengab\Override\PHP\VarDump\SUBJECT;
use const IjorTengab\Override\PHP\VarDump\COMMENT;
use const IjorTengab\Override\PHP\VarDump\LINE;

// Init.
$from_path = __DIR__ . '/example05.ini';
$config = ConfigurationEditor::load($from_path);

// Lets see current content.
var_dump((string) $config, SUBJECT | COMMENT | LINE);

// Lets see current data.
var_dump($config->getData(), SUBJECT | COMMENT | LINE);

// Lets see spesific data.
var_dump($config->getData('island[sumatera][about]'), SUBJECT | COMMENT | LINE);
var_dump($config->getData('island[sumatera][province]'), SUBJECT | COMMENT | LINE);
var_dump($config->getData('island[sumatera][province][2]'), SUBJECT | COMMENT | LINE);

/**
 * Line: 23
 * var_dump((string) $config):
 * string(428) "; This is comment.
 * country = Indonesia ;This is inline comment.
 * islands[] = Sumatera
 * islands[] = Jawa
 * islands[] = Borneo
 * islands[] = Sulawesi ;This is inline comment.
 * islands[] = Papua
 * island[sumatera][about] = province
 * island[sumatera][province][] = Aceh
 * island[sumatera][province][] = Sumatera Utara
 * island[sumatera][province][] = Sumatera Barat
 * island[sumatera][province][] = Sumatera Selatan
 * country[name] = Indonesia
 * "
 */
/**
 * Line: 26
 * var_dump($config->getData()):
 * array(3) {
 *     ["country"]=>
 *     array(1) {
 *         ["name"]=>
 *         string(9) "Indonesia"
 *     }
 *     ["islands"]=>
 *     array(5) {
 *         [0]=>
 *         string(8) "Sumatera"
 *         [1]=>
 *         string(4) "Jawa"
 *         [2]=>
 *         string(6) "Borneo"
 *         [3]=>
 *         string(8) "Sulawesi"
 *         [4]=>
 *         string(5) "Papua"
 *     }
 *     ["island"]=>
 *     array(1) {
 *         ["sumatera"]=>
 *         array(2) {
 *             ["about"]=>
 *             string(8) "province"
 *             ["province"]=>
 *             array(4) {
 *                 [0]=>
 *                 string(4) "Aceh"
 *                 [1]=>
 *                 string(14) "Sumatera Utara"
 *                 [2]=>
 *                 string(14) "Sumatera Barat"
 *                 [3]=>
 *                 string(16) "Sumatera Selatan"
 *             }
 *         }
 *     }
 * }
 */
/**
 * Line: 29
 * var_dump($config->getData('island[sumatera][about]')):
 * string(8) "province"
 */
/**
 * Line: 30
 * var_dump($config->getData('island[sumatera][province]')):
 * array(4) {
 *     [0]=>
 *     string(4) "Aceh"
 *     [1]=>
 *     string(14) "Sumatera Utara"
 *     [2]=>
 *     string(14) "Sumatera Barat"
 *     [3]=>
 *     string(16) "Sumatera Selatan"
 * }
 */
/**
 * Line: 31
 * var_dump($config->getData('island[sumatera][province][2]')):
 * string(14) "Sumatera Barat"
 */
