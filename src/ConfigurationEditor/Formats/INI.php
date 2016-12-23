<?php

namespace IjorTengab\ConfigurationEditor\Formats;

use IjorTengab\ParseINI\ParseINI;
use IjorTengab\ConfigurationEditor\FormatInterface;
use IjorTengab\ConfigurationEditor\RuntimeException;
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
        // Prepare.
        if ($key === []) {
            $key = '[]';
        }
        $array_type = 'associative';
        $key_parent = '';
        $keys_deleted = [];
        $old_key = $key;

        // Perbaiki nilai $key sebelum dimasukkan ke $this->keys,
        // sekaligus menyesuaikan nilai $this->max_value_of_sequence
        // bagi key yang sequence, juga menyesuaikan nilai dari
        // $this->convert_sequence_to_mapping bagi key tertentu.
        $this->modifyKey($key, $array_type, $key_parent);

        do {
            if (array_key_exists($key, $this->keys)) {
                $this->keys[$key]['changed'] = true;
                $this->keys[$key]['value'] = $value;
                break;
            }

            $this->populateKeysDeleted($key, $keys_deleted);
            $this->populateParentKeyThatExists($key, $key_parent, $keys_deleted);
            $this->deleteKeys($keys_deleted);

            $key_above = $this->getKeyThatExactlyAbove($key_parent);
            if (null === $key_above) {
                if (0 !== $this->last_line) {
                    $this->addEolLastLine();
                }
                $next_line = ++$this->last_line;
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
                break;
            }

            $line_above = $this->keys[$key_above]['line'];

            // Contoh pada kasus:
            // ```ini
            // entahlah = oke
            // benarkah[kita][bisa] = ya
            // ```
            // $config->setData('benarkah[kita][semangat]', 'ho oh');
            // maka perlu ditambah eol pada last line.
            if ($this->last_line === $line_above) {
                $this->addEolLastLine();
            }
            $line_new = $line_above + 1;
            $this->modifySegmenPosition($line_new);
            $new_segmen = [ $line_new => [
                'key' => $old_key,
                'eol' => "\n"
            ]];
            ArrayHelper::elementEditor($this->segmen, 'insert', 'after', $line_above, $new_segmen);
            $this->modifyKeysPosition($line_new);
            $new_keys = [ $key => [
                'line' => $line_new,
                'value' => $value,
                'array_type' => $array_type,
                'changed' => true,
            ]];
            ArrayHelper::elementEditor($this->keys, 'insert', 'after', $key_above, $new_keys);
            // Rebuild last line.
            $this->populateLastLine();
        } while (false);

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
        // todo: jika kita delete angka terakhir, maka perhatikan apakah max value of suquence harusnya
        // juga disesuaikan.
        if (array_key_exists($key, $this->keys)) {
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
        else {
            $keys_deleted = [];
            $this->populateKeysDeleted($key, $keys_deleted);
            $this->deleteKeys($keys_deleted);
            // $debugname = 'keys_deleted'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

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
    protected function modifyKey(&$key, &$array_type, &$key_parent)
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
                $key_parent = $m[1];
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
    protected function addEolLastLine()
    {
        $last_line = $this->last_line;
        if (!isset($this->segmen[$last_line]['eol'])) {
            $this->segmen[$last_line]['eol'] = "\n";
        }
    }

    /**
     *

Misalnya:
kita punya ini
country[name][fullname][internationalname][un] = Indonesia
country[name][fullname][internationalname][asean] = Negara Indonesia
lalu saat kita mengeset
$config->setData('country[name][fullname][localname][dahsyat]', 'Dahsyat Indonesia');
maka key_parent akan terpopulate yakni: country[name][fullname]
// tapi jika
kita punya ini
country = Indonesia ;This is inline comment.
dan kita set
$config->setData('country[name][fullname][localname][dahsyat]', 'Dahsyat Indonesia');
justru
$key_parent = '';
dan
$keys_deleted[] = 'country'
karena tidak bisa kalo key parent nya adalah non array.

     */
    protected function populateParentKeyThatExists($key, &$key_parent, &$keys_deleted)
    {
        // $keys = $this->keys;
        // $debugname = 'keys'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

        $parts = preg_split('/\]?\[/', rtrim($key, ']'));
        $parents = [];
        array_pop($parts);
        while ($last = array_pop($parts)) {
            $parent = '';
            empty($parts) or $parent = implode('][', $parts) . '][';
            $parent .= $last;
            // Memperbaiki dari "country][name][fullname][localname"
            // menjadi "country[name][fullname][localname]"
            $parent = preg_replace_callback('/^([^\]\[]+)\]\[(.+)/', function ($matches) {
                return $matches[1] . '[' . $matches[2] . ']';
            }, $parent);
            $parents[] = $parent;
        }

        foreach ($parents as $parent) {
            $value = $this->data($parent);
            if (null === $value) {
                continue;
            }
            elseif (is_array($value)) {
                $key_parent = $parent;
                break;
            }
            else {
                $keys_deleted = array_merge($keys_deleted, (array) $parent);
                break;
            }
        }

    }

    /**
     * Contoh, misalnya:
     *
country[name][fullname][internationalname][un] = Indonesia
country[name][fullname][internationalname][asean] = Negara Indonesia
maka saat akan mengeset
$config->setData('country[name]', 'Indonesia');
maka
country[name][fullname][internationalname][un] = Indonesia
country[name][fullname][internationalname][asean] = Negara Indonesia
akan di delete karena sudah tidak lagi relevan.
     */
    protected function populateKeysDeleted($key, &$keys_deleted)
    {
        $pattern = '/^' . preg_quote($key) . '\[/';
        $inside = ArrayHelper::filterKeyPattern($this->keys, $pattern);
        if (!empty($inside)) {
            $keys_deleted = array_merge($keys_deleted, array_keys($inside));
        }
    }

    /**
     *
     */
    protected function getKeyThatExactlyAbove($key_parent)
    {
        if ($key_parent === '') {
            return;
        }
        $data = $this->data;
        // $debugname = 'data'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

        // $debugname = 'key_parent'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
        // return;
        $children = $this->getData($key_parent);
        // $debugname = 'children'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
        $flat = ArrayHelper::dimensionalSimplify($children);
        $line = 0;
        $found = null;
        foreach ($flat as $key => $value) {
            // Ubah dari "aa" menjadi "[aa]"
            // dan dari "aa[bb]" menjadi "[aa][bb]".
            $key = rtrim($key, ']') . ']';
            $key = preg_replace('/[\[]/', '][', $key, 1);
            $key = '[' . $key;
            // Tambah prefix.
            $key = $key_parent . $key;
            // pada kasus tertentu,
            // $debugname = 'key'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
            if ($info = $this->keys[$key]) {
                if ($line < $info['line']) {
                    $found = $key;
                    $line = $info['line'];
                }
            }

            // $debugname = 'info'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

        }
        // $debugname = 'found'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";


        return $found;
        // $children_key = key($children);
        // $children_value = current($children);
        // $flat = ArrayHelper::dimensionalSimplify([$children_key => $children_value]);


    }

    /**
     *
     */
    protected function modifySegmenPosition($int)
    {
        // Segmen.
        $segmen_keys = array_keys($this->segmen);
        $segmen_values = array_values($this->segmen);
        foreach ($segmen_keys as &$info) {
            if ($info >= $int) {
               $info++;
           }
        }
        $this->segmen = array_combine($segmen_keys, $segmen_values);
    }

    /**
     *
     */
    protected function modifyKeysPosition($int)
    {
        foreach ($this->keys as &$info) {
           if ($info['line'] >= $int) {
               $info['line']++;
           }
        }
    }

    /**
     *
     */
    protected function deleteKeys($keys_deleted)
    {
        foreach ($keys_deleted as $key) {
            $this->delData($key);
        }
    }
}
