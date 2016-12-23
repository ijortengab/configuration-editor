<?php

namespace IjorTengab\ConfigurationEditor\Formats;

use Psr\Log\LoggerInterface;
use IjorTengab\ParseYAML\ParseYAML;
use IjorTengab\ConfigurationEditor\FormatInterface;
use IjorTengab\Tools\Functions\ArrayHelper;

/**
 * Extend dari Class ParseYAML dimana telah implementasi aturan dari
 * FormatInterface.
 * Todo, jika set value atau del value yang ada key numeric, maka
 * berakibat pada perubahan isi file untuk menajaga kompatibilitas.
 */
class YAML extends ParseYAML implements FormatInterface
{
    protected $file;

    protected $last_line = 0;

    protected $log;

    protected $max_value_of_sequence = [];

    /**
     * Daftar
     * Mengubah [], menjadi [0], [1]
     * yang diakibatkan oleh adanya set data yang langsung ke numeric
     * yang dituju, seperti ->setData('blablabla[100]', 'a')
     */
    protected $convert_sequence_to_mapping = [];

    /**
     * Override punya parent yang protected menjadi public agar bisa diakses
     * oleh fungsi ArrayHelper.
     */
    public $data;

    /**
     *
     */
    public function __toString()
    {
        return $this->raw;
    }

    /**
     * {@inheritdoc}
     */
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function readFile()
    {
        if (is_string($this->file) && is_readable($this->file)) {
            $this->raw = file_get_contents($this->file);
            return $this;
        }
        elseif (is_resource($this->file)) {
            $meta = stream_get_meta_data($this->file);
            $stat = fstat($this->file);
            if (is_readable($meta['uri'])) {
                fseek($this->file, 0);
                $this->raw = ($stat['size'] > 0) ? fread($this->file, $stat['size']) : '';
            }
            return $this;
        }
        throw new RuntimeException('File not readable.');
    }

    /**
     * {@inheritdoc}
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * {@inheritdoc}
     */
    public function setData($key, $value)
    {
        if (false === $this->has_parsed) {
            $this->parse();
        }
        $array_type = 'associative';
        $dimension = 0;
        $key_parent = '';
        if ($key === []) {
            $key = '[]';
        }
        $old_key = $key;

        $this->processBeforeSetDataSequence($key, $array_type, $dimension, $key_parent);

        if (array_key_exists($key, $this->keys)) {
            $this->keys[$key]['changed'] = true;
            $this->keys[$key]['value'] = $value;
        }
        else {
            // Set data baru.
            if ($array_type == 'indexed') {
                // Todo, bagaimana jika parent gak ada.
                $parent_line = $this->keys[$key_parent]['line'];
                $children = $this->getData($key_parent);
                end($children);
                $children_key = key($children);
                $children_value = current($children);
                $flat = ArrayHelper::dimensionalSimplify([$children_key => $children_value]);
                $dapet_key_older_sibling = $key_parent . '[' . $children_key . ']';
                $_ = preg_split('/\]?\[/', rtrim($key, ']'));
                $dapet_dimension = count($_);
                $dapet_line_older_sibling = $this->keys[$dapet_key_older_sibling]['line'];
                if ($this->last_line === $dapet_line_older_sibling) {
                    $this->addEolLastLine();
                }
                $dapet_next_line_untuk_kita = $dapet_line_older_sibling + 1;
                // Segmen.
                $segmen_keys = array_keys($this->segmen);
                $segmen_values = array_values($this->segmen);
                foreach ($segmen_keys as &$info) {
                    if ($info >= $dapet_next_line_untuk_kita) {
                       $info++;
                   }
                }
                $this->segmen = array_combine($segmen_keys, $segmen_values);
                $new_info = $this->segmen[$dapet_line_older_sibling];
                $new_info[$dapet_dimension]['segmen']['value'] = $value;
                $new_info = [$dapet_next_line_untuk_kita => $new_info];
                ArrayHelper::elementEditor($this->segmen, 'insert', 'after', $dapet_line_older_sibling, $new_info);
                // Keys.
                foreach ($this->keys as &$info) {
                   if ($info['line'] >= $dapet_next_line_untuk_kita) {
                       $info['line']++;
                   }
                }
                $new_info = [$key => [
                    'line' => $dapet_next_line_untuk_kita,
                    'dimension' => $dapet_dimension,
                    'value' => $value,
                    'array_type' => $array_type,
                ]];
                ArrayHelper::elementEditor($this->keys, 'insert', 'after', $dapet_key_older_sibling, $new_info);
            }
        }
        // Masukkan ke property $data.
        // Jika $value === null, maka kita tidak bisa menggunakan
        // ArrayHelper::propertyEditor, karena mengeset null sama dengan menghapusnya.
        if (null === $value) {
            $data_expand = ArrayHelper::dimensionalExpand([$key => $value]);
            $this->data = array_replace_recursive((array) $this->data, $data_expand);
        }
        else {
            $this->data($key, $value);
        }
        return $this;
    }

    /**
     *
     */
    public function setArrayData(Array $array)
    {
        // todo.
    }

    /**
     * {@inheritdoc}
     */
    public function getData($key = null)
    {
        if (false === $this->has_parsed) {
            $this->parse();
        }
        // Hati-hati, karena pada ArrayHelper::propertyEditor
        // mengeset null sama dengan menghapusnya.
        return (null === $key) ? $this->data() : $this->data($key);
    }

    /**
     * {@inheritdoc}
     */
    public function delData($key)
    {
        if (false === $this->has_parsed) {
            $this->parse();
        }

        if (isset($this->keys[$key])) {
            // Khusus delete $key key[subkey][numeric]
            // Maka perlu flag untuk convert_sequence_to_mapping
            if (preg_match('/(.*)\[(\d+)\]$/', $key, $m)) {
                // Jika angka yang didelete  adalah key terakhir, maka
                // kita tidak perlu convert.
                $_key = $m[1] . '[]';
                if ($this->max_value_of_sequence[$_key] != $m[2]) {
                    $this->convert_sequence_to_mapping[$_key] = true;
                }
            }
            // Clear segmen.
            if (isset($this->keys[$key]['line'])) {
                $line =$this->keys[$key]['line'];
                $this->segmen[$line] = [];
            }
            // Clear keys.
            unset($this->keys[$key]);
            // Clear data.
            $this->data($key, null);
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $this->updateKeyInSegmen();
        $this->updateValueInSegmen();
        $this->rebuildRaw();
        if (is_string($this->file)) {
            if (file_exists($this->file) && !is_writable($this->file)) {
                return false;
            }
            file_put_contents($this->file, $this->raw);
            return true;
        }
        elseif (is_resource($this->file)) {
            $meta = stream_get_meta_data($this->file);
            if (is_writable($meta['uri'])) {
                fseek($this->file, 0);
                fwrite($this->file, $this->raw);
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setLog(LoggerInterface $log)
    {
        $this->log = $log;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Satu method untuk semua kebutuhan CRUD.
     * @see ArrayHelperTrait::_arrayHelper().
     */
    protected function data()
    {
        return ArrayHelper::propertyEditor($this, 'data', func_get_args());
    }

    /**
     *
     */
    protected function afterLooping()
    {
        parent::afterLooping();
        $this->populateMaxValueOfSequence();
        $this->populateLastLine();
    }

    /**
     *
     */
    protected function populateMaxValueOfSequence()
    {
        // Populate pada dimensi pertama.
        $_ = $this->data;
        if (!empty($_)) {
            $_ = ArrayHelper::filterKeyInteger($this->data);
        }
        if (!empty($_)) {
            $_ = max(array_keys($_));
        }
        if (!empty($_)) {
            $this->max_value_of_sequence['[]'] = $_;
        }
        // Populate pada dimensi berikutnya.
        $_ = $this->sequence_of_scalar;
        if (!empty($_)) {
            $_ = array_map(function ($var) {
                return $var - 1;
            }, $_);
            $this->max_value_of_sequence += $_;
        }
    }

    /**
     *
     */
    protected function populateLastLine()
    {
        $this->last_line = max(array_keys($this->segmen));
    }

    /**
     *
     */
    protected function updateKeyInSegmen()
    {
        // Kita perlu melakukan mengubah informasi key yang sebelumnya
        // sequence menjadi mapping pada property $segmen.
        $list = $this->convert_sequence_to_mapping;
        if (!empty($list)) {
            do {
                $pattern = key($list);
                $pattern = substr($pattern, 0, -2);
                $pattern = '/' . preg_quote($pattern) . '\[\d+\]' . '/';
                $sequences = ArrayHelper::filterKeyPattern($this->keys, $pattern);
                foreach ($sequences as $key_mapping => $info) {
                    if (isset($info['line'])) {
                        $line = $info['line'];
                        $this->segmen[$line]['key'] = $key_mapping;
                    }
                }
            }
            while(next($list));
        }
    }

    /**
     *
     */
    protected function updateValueInSegmen()
    {
        $list = ArrayHelper::filterChild($this->keys, ['changed' => true]);
        if (!empty($list)) {
            do {
                $key = key($list);
                $info = $list[$key];
                $line = $info['line'];
                $dimension = $info['dimension'];
                $value = $info['value'];
                $this->segmen[$line][$dimension]['segmen']['value'] = $value;
            }
            while(next($list));
        }
    }

    /**
     *
     */
    protected function rebuildRaw()
    {
        $raw = '';
        foreach ($this->segmen as $line => $_dimension) {
            foreach ($_dimension as $dimension => $_info) {
                $info = $_info['segmen'];
                $key = isset($info['key']) ? $info['key'] : '';
                $value = isset($info['value']) ? $info['value'] : '';
                $key_prepend = isset($info['key_prepend']) ? $info['key_prepend'] : '';
                $key_append = isset($info['key_append']) ? $info['key_append'] : '';
                $value_prepend = isset($info['value_prepend']) ? $info['value_prepend'] : '';
                $value_append = isset($info['value_append']) ? $info['value_append'] : '';
                $quote_value = isset($info['quote_value']) ? $info['quote_value'] : '';
                $separator = isset($info['separator']) ? $info['separator'] : '';
                $comment = isset($info['comment']) ? $info['comment'] : '';
                $eol = isset($info['eol']) ? $info['eol'] : '';
                $raw .= $key_prepend;
                $raw .= $key;
                $raw .= $key_append;
                $raw .= $separator;
                $raw .= $value_prepend;
                $raw .= $quote_value;
                $raw .= $value;
                $raw .= $quote_value;
                $raw .= $value_append;
                $raw .= $comment;
                $raw .= $eol;
            }
        }
        $this->raw = $raw;
    }

    /**
     * Memperbaiki nilai $key yang memiliki suffix "[]", dsb.
     * sebelum dimasukkan kedalam property $keys.
     */
    protected function processBeforeSetDataSequence(&$key, &$array_type, &$dimension, &$key_parent)
    {
        if ($key == '[]') {
            $array_type = 'indexed';
            if (array_key_exists('[]', $this->max_value_of_sequence)) {
                $c = ++$this->max_value_of_sequence[$key];
            }
            else {
                $c = $this->max_value_of_sequence[$key] = 0;
            }
            $key = $c;
        }
        elseif (is_numeric($key)) {
            $array_type = 'indexed';
            if (is_string($key)) {
                // Contoh $key yang string adalah:
                // - var_dump(key): string(1) "8"
                // - var_dump(key): string(3) "8.3"
                // Jika string yg integer, maka perlu ada perlakuan khusus.
                $test_int = (int) $key;
                $test_string = (string) $test_int;
                // todo, apakah perlu if dibawah ini.
                if ($test_string === $key) {
                    if (!array_key_exists('[]', $this->max_value_of_sequence)) {
                        $this->max_value_of_sequence['[]'] = $test_int;
                    }
                    if (array_key_exists('[]', $this->max_value_of_sequence) && $test_int > $this->max_value_of_sequence['[]']) {
                        $this->max_value_of_sequence['[]'] = $test_int;
                    }
                }
            }
            elseif (is_int($key)) {
                if (!array_key_exists('[]', $this->max_value_of_sequence)) {
                    $this->max_value_of_sequence['[]'] = $key;
                }
                if (array_key_exists('[]', $this->max_value_of_sequence) && $key > $this->max_value_of_sequence['[]']) {
                    $this->max_value_of_sequence['[]'] = $key;
                }
            }
            elseif (is_float($key)) {
                // Perlakuan ini disamakan dengan saat mengeset value
                // array dengan key float, yakni jadikan integer dengan
                // pembulatan kebawah.
                $key = (int) round($key, 0, PHP_ROUND_HALF_DOWN);
                if (!array_key_exists('[]', $this->max_value_of_sequence)) {
                    $this->max_value_of_sequence['[]'] = $key;
                }
                if (array_key_exists('[]', $this->max_value_of_sequence) && $key > $this->max_value_of_sequence['[]']) {
                    $this->max_value_of_sequence['[]'] = $key;
                }
            }
        }
        // Untuk kasus
        // $key = 'key[subkey][]';
        // $key = 'key[subkey][84]';
        // $key = 'key[subkey]['cinta']';
        elseif (preg_match('/(.*)\[([^\[\]]*)\]$/', $key, $m)) {
            $key_parent = $m[1];
            if ($m[2] == '') {
                $array_type = 'indexed';
                $_key = $m[0];
                if (array_key_exists($_key, $this->max_value_of_sequence)) {
                    $c = ++$this->max_value_of_sequence[$_key];
                }
                else {
                    $c = $this->max_value_of_sequence[$_key] = 0;
                }
                $key = $m[1] . '[' . $c . ']';
            }
            elseif (is_numeric($m[2])) {
                $array_type = 'indexed';
                $_key = $m[1] . '[]';
                $test_int = (int) $m[2];
                $test_string = (string) $test_int;
                if ($test_string === $m[2]) {
                    // Apakah butuh diconvert ke mapping.
                    do {
                        if (array_key_exists($key, $this->keys)) {
                            break;
                        }
                        if ($test_int === 0) {
                            break;
                        }
                        if (array_key_exists($_key, $this->max_value_of_sequence)) {
                            $current_max_value = $this->max_value_of_sequence[$_key];
                            if ($test_int === ++$current_max_value) {
                                break;
                            }
                        }
                        $this->convert_sequence_to_mapping[$_key] = true;
                    }
                    while (false);
                    // Update max value.
                    if (array_key_exists($_key, $this->max_value_of_sequence)) {
                        $current_max_value = $this->max_value_of_sequence[$_key];
                        if ($test_int > $current_max_value) {
                            $this->max_value_of_sequence[$_key] = $test_int;
                        }
                    }
                    else {
                        $this->max_value_of_sequence[$_key] = $test_int;
                    }
                }
            }
        }
    }

    /**
     *
     */
    protected function convertToString($value)
    {
        if ($value === true) {
            $value = 'true';
        }
        elseif ($value === false) {
            $value = 'false';
        }
        elseif ($value === null) {
            $value = 'null';
        }
        return $value;
    }

    /**
     *
     */
    protected function specialString($value)
    {
        switch ($value) {
            case 'true':
            case 'TRUE':
            case 'false':
            case 'FALSE':
            case 'null':
            case 'NULL':
                return true;
            default:
                return false;
        }
    }

    /**
     *
     */
    public function addEolLastLine()
    {
        $last_line = $this->last_line;
        $line_segmen = $this->segmen[$last_line];
        end($line_segmen);
        $last_dimension = key($line_segmen);
        if (!isset($this->segmen[$last_line][$last_dimension]['segmen']['eol'])) {
            $this->segmen[$last_line][$last_dimension]['segmen']['eol'] = "\n";
        }
    }
}
