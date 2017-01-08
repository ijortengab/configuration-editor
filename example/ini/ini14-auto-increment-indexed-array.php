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
