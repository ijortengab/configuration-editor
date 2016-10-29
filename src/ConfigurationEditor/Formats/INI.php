<?php

namespace IjorTengab\ConfigurationEditor\Formats;

use IjorTengab\ParseINI\ParseINI;
use IjorTengab\ConfigurationEditor\FormatInterface;
use IjorTengab\Tools\Functions\ArrayHelper;
use Psr\Log\LoggerInterface;

/**
 * Extend dari Class ParseINI dimana telah implementasi aturan dari
 * FormatInterface.
 * Todo, jika set value atau del value yang ada key numeric, maka
 * berakibat pada perubahan isi file untuk menajaga kompatibilitas.
 */
class INI extends ParseINI implements FormatInterface
{
    protected $filename;

    protected $last_line;

    protected $log;

    protected $max_value_of_sequence = [];

    protected $convert_sequence_to_mapping = [];

    /**
     * Override punya parent yang protected menjadi public agar bisa diakses
     * oleh fungsi ArrayHelper.
     */
    public $data;

    /**
     * {@inheritdoc}
     */
    public function setFileName($filename)
    {
        if (is_readable($filename)) {
            $this->filename = $filename;
            $this->raw = file_get_contents($filename);
            return $this;
        }
        throw new InvalidArgumentException('File not readable.');
    }

    /**
     * {@inheritdoc}
     */
    public function getFileName()
    {
        return $this->filename;
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
        if ($key === []) {
            $key = '[]';
        }
        $old_key = $key;

        // Perbaiki nilai $key sebelum dimasukkan ke $this->keys,
        // sekaligus menyesuaikan nilai $this->max_value_of_sequence
        // bagi key yang sequence, juga menyesuaikan nilai dari
        // $this->convert_sequence_to_mapping bagi key tertentu.
        $this->processBeforeSetData($key, $array_type);

        // Setelah $key diubah, cek apakah telah exists di property $keys.
        // Jika tidak ada, maka kita perlu menambah juga di property $segmen.
        if (array_key_exists($key, $this->keys)) {
            $this->keys[$key]['changed'] = true;
            $this->keys[$key]['value'] = $value;
        }
        else {
            $last_line = $this->last_line;
            // Last line harus ada eol, jika tidak ada, maka paksa tambah.
            if (!isset($this->segmen[$last_line]['eol'])) {
                $this->segmen[$last_line]['eol'] = "\n";
            }
            $next_line = ++$this->last_line;
            // Gunakan $old_key pada segmen, jadi meski
            // key telah berubah menjadi aa[0], maka pada segmen
            // kita tetap menggunakan aa[].
            $this->segmen[$next_line] = [
                'key' => $old_key,
                'eol' => "\n"
            ];
            $this->keys[$key] = [
                'line' => $next_line,
                'value' => $value,
                'array_type' => $array_type,
                'changed' => true,
            ];
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
    public function saveData()
    {
        $this->updateKeyInSegmen();
        $this->updateValueInSegmen();
        $this->rebuildRaw();
        // Kita tidak perlu mengecek lagi tentang is_writable, karena hal ini
        // sudah dicek di ConfigurationEditor::autoSave.
        file_put_contents($this->filename, $this->raw);
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
                $value = $info['value'];
                $this->segmen[$line]['value'] = $value;
            }
            while(next($list));
        }
    }

    /**
     *
     */
    protected function rebuildRaw()
    {
        $this->raw = '';
        foreach ($this->segmen as $info) {
            if (empty($info)) {
                // Deleted key.
                continue;
            }
            if (isset($info['key'])) {
                $key = $info['key'];
                $key_prepend = isset($info['key_prepend']) ? $info['key_prepend'] : '';
                $key_append = isset($info['key_append']) ? $info['key_append'] : '';
                $value_prepend = isset($info['value_prepend']) ? $info['value_prepend'] : '';
                $value_append = isset($info['value_append']) ? $info['value_append'] : '';
                $quote_value = isset($info['quote_value']) ? $info['quote_value'] : '';
                if (!array_key_exists('separator', $info)) {
                    // Berarti ini record baru.
                    $key_append = ' ';
                    $value_prepend = ' ';
                }
                $separator = '=';
                if (array_key_exists('value', $info)) {
                    // Gunakan array_key_exists, karena NULL
                    // pada key value juga merupakan sebuah nilai.
                    $value = $info['value'];
                }
                else {
                    // Berarti key harus tetap exists.
                    list($value, $separator, $key_append, $quote_value, $value_prepend, $value_append) = ['', '', '', '', '', ''];
                }
                if (is_string($value)) {
                    if ($this->specialString($value)) {
                        $quote_value = "'";
                    }
                }
                else {
                    // Convert value to string.
                    $value = $this->convertToString($value);
                    $quote_value = '';
                }
                $this->raw .= $key_prepend;
                $this->raw .= $key;
                $this->raw .= $key_append;
                $this->raw .= $separator;
                $this->raw .= $value_prepend;
                $this->raw .= $quote_value;
                $this->raw .= $value;
                $this->raw .= $quote_value;
                $this->raw .= $value_append;
            }
            $comment = isset($info['comment']) ? $info['comment'] : '';
            $eol = isset($info['eol']) ? $info['eol'] : '';
            $this->raw .= $comment;
            $this->raw .= $eol;
        }
    }

    /**
     *
     */
    protected function processBeforeSetData(&$key, &$array_type)
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
}
