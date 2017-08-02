<?php

namespace IjorTengab\ConfigurationEditor\Formats;

use Psr\Log\LoggerInterface;
use IjorTengab\ParseYAML\ParseYAML;
use IjorTengab\ConfigurationEditor\FormatInterface;
use IjorTengab\ConfigurationEditor\Traits\IniYamlFormatTrait;
use IjorTengab\Tools\Functions\ArrayHelper;


/**
 * Extend dari Class ParseYAML dimana telah implementasi aturan dari
 * FormatInterface.
 * Todo, jika set value atau del value yang ada key numeric, maka
 * berakibat pada perubahan isi file untuk menajaga kompatibilitas.

 todo, bagaimana jika ada yg iseng-iseng ngubah sequence
 jadi mapping dan kemudian ubah lagi jadi sequence,
 untuk perkara seperti ini kita perlu memperhatikan
 informasi pada property max_value_of sequence

 todo perlu dibuat example pada kasus

// $config->delData('cinta[0]');
// $config->setData('cinta[0]', 'aa');
sehingga tidak jadi convert to mapping
 */
class YAML extends ParseYAML implements FormatInterface
{
    /**
     * IniYamlFormatTrait contains method:
     * - protected function processSequence(&$old_key, &$key, &$array_type, &$key_parent)
     * - protected function isNumericInteger($mixed)
     */
    use IniYamlFormatTrait;

    /**
     * Override dari property parent::$data. Mengubah visibility dari protected
     * menjadi public agar bisa diakses oleh fungsi ArrayHelper.
     */
    public $data;

    /**
     * Property ini menyimpan informasi file. Bisa bertipe
     * string yang merupakan informasi path. Bisa juga bertipe
     * resource (stream).
     */
    protected $file;

    /**
     * Property yang berisi baris terakhir dari format INI.
     * Nantinya digunakan untuk informasi pada property $segmen
     * jika dilakukan penambahan baris baru.
     */
    protected $last_line = 0;

    /**
     * Instance dari object yang implements Psr/Log/LoggerInterface.
     */
    protected $log;

    /**
     * Property untuk menyimpan array. Informasi yang disimpan
     * adalah nilai key tertinggi dari tiap-tiap sequence of scalar
     * sebagai referensi saat akan setData dengan format sequence.
     *
     * Contoh:
     * ```
     * $string = <<<'YAML'
     * aa:
     *   - '00'
     *   - '11'
     * bb:
     *   - '00'
     *   - '11'
     * cc:
     *   - '00'
     * YAML;
     * $config = new ConfigurationEditor(new INI($string));
     * $config->setData('aa[]', '22');
     * $config->save();
     * $string = (string) $config;
     * ```
     * Pada code diatas, maka nilai dari $string akan menjadi
     * ```
     * $string = <<<'YAML'

     * YAML;
     * ```
     * Pada contoh diatas, maka nilai dari property ini
     * sebelum dijalankan method self::setData() adalah:
     * ```
     * $this->max_value_of_sequence = [
     *     'aa[]' => 1,
     *     'bb[]' => 1,
     *     'cc[]' => 0,
     * ];
     * ```
     * dan setelah dijalankan method self::setData(),
     * nilai dari property ini
     * ```
     * $this->max_value_of_sequence = [
     *     'aa[]' => 2,
     *     'bb[]' => 1,
     *     'cc[]' => 0,
     * ];
     * ```
     */
    protected $max_value_of_sequence = [];

    /**
     * Daftar
     * Mengubah [], menjadi [0], [1]
     * yang diakibatkan oleh adanya set data yang langsung ke numeric
     * yang dituju, seperti ->setData('blablabla[100]', 'a')
     */
    protected $convert_sequence_to_mapping = [];



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
                return $this;
            }
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

        // Alternative syntax untuk mempermudah user, dimana
        // self::setData([], '...') === self::setData('[]', '...').
        if ($key === []) {
            $key = '[]';
        }

        // Process terhadap $key yang terindikasi sequence.
        $array_type = 'associative';
        $key_parent = '';
        $keys_deleted = [];
        $keys_created = [];
        $old_key = $key;




        // Preprocess khusus untuk set data berupa sequence (suffix []
        // atau [\d+]).

        $this->processSequence($old_key, $key, $array_type, $key_parent);


        // Modifikasi property $segmen dan $keys.
        do {
            if (array_key_exists($key, $this->keys)) {
                if (
                    array_key_exists('original_value', $this->keys[$key]) &&
                    $this->keys[$key]['original_value'] === $value
                ) {
                    $this->keys[$key]['changed'] = false;
                    break;
                }
                $this->keys[$key]['changed'] = true;
                $this->keys[$key]['value'] = $value;
                // todo, beri contoh code disini
                // karena ini ada perbedaan dengan INI.
                $this->populateKeysDeleted($key, $keys_deleted);
                $this->deleteKeys($keys_deleted);
                break;
            }

            // Misalnya, kita punya informasi
            // country[name][fullname] = Indonesia
            // country[name][oldname] = Nusantara
            // , maka pada property $keys.
            // terdapat informasi.
            // country = ...
            // country[name] = ...
            // country[name][fullname] = Indonesia.
            // Jika kita akan mengeset
            // country[name] = New Value, maka
            // otomatis key
            // country[name][fullname] dan country[name][oldname]
            // harus dihapus.
            // untuk konsistensi data.

            $this->populateKeysDeleted($key, $keys_deleted);
            $this->populateParentKeyThatExists($key, $key_parent, $keys_deleted);
            $this->deleteKeys($keys_deleted);


            // // $debugname = 'key_parent'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . // $debugname . '): '; var_dump($// $debugname); echo "</pre>\r\n";
            // // $debugname = 'keys_deleted'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . // $debugname . '): '; var_dump($// $debugname); echo "</pre>\r\n";
            $key_above = $this->getKeyChildrenYounger($key_parent);



            $this->populateKeysCreated($keys_created, $key);
            $debugname = 'keys_created'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
            $this->createKeys($keys_created, $key, $value);




        } while (false);

        // $debugname = 'keys'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
        // $debugname = 'segmen'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }



        // Masukkan value ke property $data.
        // Jika $value === null, maka kita tidak bisa menggunakan
        // ArrayHelper::propertyEditor, karena mengeset null sama dengan
        // menghapusnya.
        if (null === $value) {
            $data_expand = ArrayHelper::dimensionalExpand([$key => $value]);
            // Default value dari ParseINI::$data adalah null, gunakan magic.
            $this->data = array_replace_recursive((array) $this->data, $data_expand);
        }
        else {
            $this->data($key, $value);
        }
        return $this;


        return;

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
        $array = ArrayHelper::dimensionalSimplify($array);
        foreach ($array as $key => $value) {
            $this->setData($key, $value);
        }
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
        // Berbeda dengan INI, pada YAML seluruh key pada array multidimensi
        // berada pada property $keys.
        // Contoh:
        // Jika di INI hanya ada country[name][fullname] = Indonesia, maka
        // pada YAML, keseluruhannya ada, yakni
        // country = ...
        // country[name] = ...
        // country[name][fullname] = Indonesia
        // hal ini mengingat informasi pada property $segmen yang perlu
        // ditampung.
        // Oleh karena itu kita lakukan penghapusan pada array2 multidimensi
        // yang didalamnya terlebih dahulu.
        $keys_deleted = [];
        $this->populateKeysDeleted($key, $keys_deleted);

        // die('stop');

        $this->deleteKeys($keys_deleted);
        return $this->_delData($key);
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        // Todo, sementara.
        $this->modifySegmenUpdateKey();
        $this->modifySegmenUpdateValue();
        $this->rebuildRaw();
        return;
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
     * Del data pada key yang sudah pasti merupakan
     * dimensi terdalam.
     */
    protected function _delData($key)
    {
        if (array_key_exists($key, $this->keys)) {
            // Khusus delete key numeric yang bukan merupakan
            // nilai key tertinggi, maka kita perlu convert.
            if (
                preg_match('/(.*)\[(\d+)\]$/', $key, $m) &&
                array_key_exists($m[1] . '[]', $this->sequence_of_scalar)
            ) {
                $_key = $m[1] . '[]';
                if ($this->max_value_of_sequence[$_key] != $m[2]) {
                    $this->convert_sequence_to_mapping[$_key] = true;
                }
            }
            // Todo. cari tahu kalo delete
            // segmen yang satu line ada dua dimension.

            // Clear Segmen.
            $line =$this->keys[$key]['line'];
            $dimension =$this->keys[$key]['dimension'];
            unset($this->segmen[$line][$dimension]);

            // Perbaiki segmen, jika pada kasus seperti ini.
            // pada kasus
            // ```
            // a:
            //   - aa: AA
            //     bb: BB
            // ```
            // maka saat kita menghapus a[aa], maka
            // segmen bertipe indexed harus kita pindahkan ke
            // line dibawahnya.
            $dimension_above = $dimension - 1;
            $line_below = $line + 1;
            if (
                isset($this->segmen[$line][$dimension_above]) &&
                $this->segmen[$line][$dimension_above]['array_type'] == 'indexed' &&
                isset($this->segmen[$line_below][$dimension])
            ) {
                $_segmen = $this->segmen[$line][$dimension_above];
                $_keys = $this->segmen[$line][$dimension_above]['keys'];
                $this->keys[$_keys]['line'] = $line_below;
                $new_segmen = [$dimension_above => $_segmen];
                unset($this->segmen[$line][$dimension_above]);
                ArrayHelper::elementEditor($this->segmen[$line_below], 'insert', 'before', $dimension, $new_segmen);
                $this->segmen[$line_below][$dimension]['segmen']['key_prepend'] = '';
            }

            // Clear keys.
            unset($this->keys[$key]);
            // Clear data.
            $this->data($key, null);
        }
        // $keys = $this->keys;
        // $segmen = $this->segmen;


        return;
        // die('ss');


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
        $this->populateOriginalValueInKeys();
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
    protected function populateOriginalValueInKeys()
    {
        foreach ($this->keys as &$info) {
            if (array_key_exists('value', $info)) {
                $info['original_value'] = $info['value'];
            }
        }
    }

    /**
     *
     */
    protected function modifySegmenUpdateKey()
    {



        // Kita perlu melakukan mengubah informasi key yang sebelumnya
        // sequence menjadi mapping pada property $segmen.



        // verifikasi
        // misalnya kita punya data
/* cinta:
  - aa
  - bb
  - cc
  - dd
  - ee
 */

 // maka saat, kita
 //
// $config->delData('cinta[0]');
// $config->setData('cinta[0]', 'aa');
// key cinta[], akan dijadikan covert to sequence.
// secara faktual, hal ini tidak diperlukan, oleh karena itu
// kita lakukan verifikasi.
        // $max_value_of_sequence = $this->max_value_of_sequence;

        // // $debugname = 'max_value_of_sequence'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . // $debugname . '): '; var_dump($// $debugname); echo "</pre>\r\n";
        $list = $this->convert_sequence_to_mapping;


        if (!empty($list)) {
            do {
                // Verifikasi.
                $_key = key($list);
                $count_expected = $this->max_value_of_sequence[$_key] + 1;
                $key = substr($_key, 0, -2);
                $count_real = count($this->data($key));
                // todo, lihat pada ini tentang ArrayHelper::isIndexedKeySorted($array)
                // apakah diperlukan pada yaml?

                if ($count_expected == $count_real) {
                    //
                    // todo
                    // perhatikan pada kasus ini
                    //* aa[ttt][9] = BBBB
 // * bb[1] = '11'
 // * bb[2] = '22'
 // * bb[0] = lancar jaya
 // perlu dipikirkan juga caranya.
                    continue;
                }
                $pattern = '/' . preg_quote($key) . '\[\d+\]' . '/';


                // $pattern = key($list);
                // $pattern = substr($pattern, 0, -2);

                $sequences = ArrayHelper::filterKeyPattern($this->keys, $pattern);

                foreach ($sequences as $info) {
                    $line = $info['line'];
                    $dimension = $info['dimension'];
                    $this->segmen[$line][$dimension]['array_type'] = 'associative';
                    $n = $this->getKeyDeepest($this->segmen[$line][$dimension]['keys']);
                    $this->segmen[$line][$dimension]['segmen']['key'] = $n;
                    $this->segmen[$line][$dimension]['segmen']['separator'] = ':';
                    // Jika $value_prepend = '   - ', maka
                    // perlu kita jadikan = '   - ';
                    if (
                        isset($this->segmen[$line][$dimension]['segmen']['value_prepend']) &&
                        preg_match('/^(.+)-\s$/', $this->segmen[$line][$dimension]['segmen']['value_prepend'], $m)
                    ) {

                        $this->segmen[$line][$dimension]['segmen']['key_prepend'] = $m[1];
                        $this->segmen[$line][$dimension]['segmen']['value_prepend'] = ' ';
                    }
                }


            }
            while(next($list));
        }


 return;
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
    protected function modifySegmenUpdateValue()
    {
        $list = ArrayHelper::filterChild($this->keys, ['changed' => true]);

        // return;
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
        $this->raw = '';
        foreach ($this->segmen as $line => $_dimension) {
            foreach ($_dimension as $dimension => $info) {
                $segmen = array_merge([
                    'key_prepend' => '',
                    'quote_key' => '',
                    'key' => '',
                    'key_append' => '',
                    'separator' => '',
                    'value_prepend' => '',
                    'quote_value' => '',
                    'value' => '',
                    'value_append' => '',
                    'comment' => '',
                    'eol' => '',
                ], $info['segmen']);
                // Modify.
                if (is_string($segmen['value'])) {
                    if ($this->specialString($segmen['value'])) {
                        $segmen['quote_value'] = "'";
                    }
                }
                else {
                    // Convert value to string.
                    $segmen['value'] = $this->convertToString($segmen['value']);
                    $segmen['quote_value'] = '';
                }
                // Set in raw.
                $_raw = '';
                $position = explode(' ', 'key_prepend quote_key key quote_key key_append separator value_prepend quote_value value quote_value value_append comment eol');
                array_walk($position, function ($val) use ($segmen, &$_raw) {
                    $_raw .= $segmen[$val];
                });
                $this->raw .= $_raw;
            }
        }
    }





    /**
     * Method ini sebagai pembantu saat menjalankan method self::setData().
     * Melakukan proses untuk memodifikasi variable karena set data yang
     * diminta user berupa sequence. Sequence disini yakni bersuffix '[]'
     * atau bersuffix digit.
     * Contoh:
     * $this->setData('a[]', '...');
     * $this->setData('b[]', '...');
     * $this->setData('c[1]', '...');
     * $this->setData('d[99]', '...');
     *
     * Variable yang dimodifikasi adalah $old_key, yakni akan

     todo, bekerja pada old_key, lihat pada ini.
     todo, lihat pada ini tentang perubahan float dan floor.
     */
    protected function offffffffprocessSequence2(&$old_key, &$key, &$array_type, &$key_parent)
    {
        if ($key === '[]') {
            $array_type = 'indexed';
            if (array_key_exists('[]', $this->max_value_of_sequence)) {
                $c = ++$this->max_value_of_sequence[$key];
            }
            else {
                $c = $this->max_value_of_sequence[$key] = 0;
            }
            $key = $c;
            return;
        }
        if (is_numeric($key) && is_string($key)) {
            $array_type = 'indexed';
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
            return;
        }
        if (is_int($key)) {
            $array_type = 'indexed';
            if (!array_key_exists('[]', $this->max_value_of_sequence)) {
                $this->max_value_of_sequence['[]'] = $key;
            }
            if (array_key_exists('[]', $this->max_value_of_sequence) && $key > $this->max_value_of_sequence['[]']) {
                $this->max_value_of_sequence['[]'] = $key;
            }
            return;
        }
        if (is_float($key)) {
            $array_type = 'indexed';
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
            return;
        }
        if (preg_match('/(.*)\[([^\[\]]*)\]$/', $key, $m)) {
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
                return;
            }
            if (is_numeric($m[2])) {
                $array_type = 'indexed';
                $key_parent = $m[1];
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
                            // todo, cek di ini pada kondisi disini
                            // apakah di yaml juga berlaku hal serupa.
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
                return;
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

Misalnya:
kita punya ini
country[name][fullname][internationalname][un] = Indonesia
country[name][fullname][internationalname][asean] = Negara Indonesia
lalu saat kita mengeset
$config->setData('
country[name][fullname][localname][dahsyat]
', 'Dahsyat Indonesia');
maka key_parent akan terpopulate yakni:
country[name][fullname]
// tapi jika
kita punya ini
country = Indonesia ;This is inline comment.
dan kita set
$config->setData('
country[name][fullname][localname][dahsyat]
', 'Dahsyat Indonesia');
justru
$key_parent = '';
dan
$keys_deleted[] = 'country'
karena tidak bisa kalo key parent nya adalah non array.

     */
    protected function populateParentKeyThatExists($key, &$key_parent, &$keys_deleted)
    {
        // $keys = $this->keys;

        // die('stop');

        $parts = preg_split('/\]?\[/', rtrim($key, ']'));


        $parents = [];
        array_pop($parts);

        if (!empty($parts)) {
            do {
                $last = array_pop($parts);
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
            while (!empty($parts));
        }

        /* while ($last = array_pop($parts)) {
            $parent = '';
            empty($parts) or $parent = implode('][', $parts) . '][';


            $parent .= $last;


            // Memperbaiki dari "country][name][fullname][localname"
            // menjadi "country[name][fullname][localname]"
            $parent = preg_replace_callback('/^([^\]\[]+)\]\[(.+)/', function ($matches) {
                return $matches[1] . '[' . $matches[2] . ']';
            }, $parent);
            $parents[] = $parent;
        } */

        // die('stop2');

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
     * todo, gunakan trait.
     */
    protected function xxxxxxxxxxxxxxxxxxxgetKeyChildrenYounger($key_parent)
    {
        if ($key_parent === '') {
            return;
        }
        $data = $this->data;

        // return;
        $children = $this->getData($key_parent);
        $debugname = 'children'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }

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

            if ($info = $this->keys[$key]) {
                if ($line < $info['line']) {
                    $found = $key;
                    $line = $info['line'];
                }
            }


        }



        return $found;
        // $children_key = key($children);
        // $children_value = current($children);
        // $flat = ArrayHelper::dimensionalSimplify([$children_key => $children_value]);


    }


    /**
     * todo, gunakan trait.
     */
    protected function getKeySiblingOldest($key_parent)
    {
        if ($key_parent === '') {
            // todo kalo return null bagaimana.
            return;
        }
        $data = $this->data;

        // return;
        $children = $this->getData($key_parent);

        $flat = ArrayHelper::dimensionalSimplify($children);


        $line = null;
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

            if ($info = $this->keys[$key]) {
                if (null === $line) {
                    $found = $key;
                    $line = $info['line'];
                }

                if ($line > $info['line']) {
                    $found = $key;
                    $line = $info['line'];
                }
            }


        }
        // todo, kalo found tetap null bagaimana?



        return $found;
        // $children_key = key($children);
        // $children_value = current($children);
        // $flat = ArrayHelper::dimensionalSimplify([$children_key => $children_value]);


    }

    /**
     *
     */
    protected function modifySegmenPosition($line, $int = 1)
    {
        // Segmen.
        $segmen_keys = array_keys($this->segmen);
        $segmen_values = array_values($this->segmen);
        foreach ($segmen_keys as &$info) {
            if ($info >= $line) {
               $info += $int;
           }
        }
        $this->segmen = array_combine($segmen_keys, $segmen_values);
    }


    /**
     *
     */
    protected function modifyKeysPosition($line, $int = 1)
    {
        foreach ($this->keys as &$info) {
           if ($info['line'] >= $line) {
               $info['line'] += $int;
           }
        }
    }

    /**
     *
     */
    protected function deleteKeys($keys_deleted)
    {
        if (!empty($keys_deleted)) {
            // Hapus mulai dari yang paling bawah.
            while ($key_deleted = array_pop($keys_deleted)) {
                $this->_delData($key_deleted);
            }
        }
    }

    /**
     *
     */
    protected function getKeyDeepest($key)
    {


        // if (preg_match('/\[([^\]\[])\]$/', $key, $matches)) {
        if (preg_match('/\[([^\[\]]+)\]$/', $key, $matches)) {
            return $matches[1];
        }
        return $key;
    }


    /**
     *
     */
    public function clear()
    {

        // return $this;
    }


    /**
     *
     */
    protected function populateKeysCreated(&$keys_created, $key)
    {
        $parts = preg_split('/\]?\[/', rtrim($key, ']'));
        $parent = '';
        $_keys_created = [];
        do {
            $part = array_shift($parts);
            $part = empty($parent) ? $part : '[' . $part .']';
            $parent .= $part;
            $_keys_created[] = $parent;
        }
        while ($parts);
        // Hapus yang terakhir.
        // array_pop($_keys_created);
        // $_keys_created = array_filter($_keys_created, function ($val) {
            // return !array_key_exists($val, $this->keys);
        // });
        $keys_created = array_merge($keys_created, $_keys_created);

        // die('op');

    }

    /**
     *  Dimana $keys created. adalah
     */
    protected function createKeys($keys_created, $key, $value)
    {

        $line = $this->last_line + 1;
        $dimension = 1;
        // todo, indent gunakan dari rata-rata yang ada.
        // atau dari last.


        $_segmen = [];
        $_keys = [];
        $parent_exists = null;
        $parent_exists_line = null;
        $key_above = null;
        $key_above_line = null;
        // $debugname = 'keys'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
        // $debugname = 'segmen'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }

        foreach ($keys_created as $key_created) {
            $debugname = 'key_created'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }


            if (array_key_exists($key_created, $this->keys)) {
                // Pada kasus.
                // ```yml
                // a:
                // ```
                // setData('a[b][c]', true)

                // $parent_exists = $key_above = $key_created;
                // $parent_exists_line = $key_above_line = $this->keys[$key_created]['line'];
                $parent_exists = $key_created;
                // $parent_exists_line = $this->keys[$key_created]['line'];
                // $line = $parent_exists_line + 1;
                // $line = $parent_exists_line + 1;
                $dimension++;
                continue;
            }
            $key_above = $this->getKeyChildrenYounger($parent_exists);
            $debugname = 'key_above'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
            // todo, if (null === $key_above)
            $line_above = $this->keys[$key_above]['line'];
            // Todo,
            // jika kasus kayak gini:
            // 1  | aa:
            // 2  |   entah:
            // 3  |     - kita
            // 4  |     - bisa
            // 5  | bb:
            // 6  | cc:
            // Maka $line_above bisa pas diatasnya, tetapi jika kasus kayak gini
            // 1  | aa:
            // 2  |   entah:
            // 3  |     - kita
            // 4  |     - "bisa
            // 5  |        bisa"
            // 6  | bb:
            // 7  | cc:
            // Hal seperti ini nanti perlu diusut
            $debugname = 'line_above'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
            $line = $this->keys[$key_above]['line'] + 1;

            // $debugname = 'segmen'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }

            if (null === $key_above) {

            }
            else {
                $line_above = $this->keys[$key_above]['line'];
                // $debugname = 'line_above'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
                $line = $this->keys[$key_above]['line'] + 1;
            }

            $indent = str_repeat(' ', ($dimension - 1) * 2 );
            $_segmen[$line][$dimension] = [
                'array_type' => 'associative',
                'segmen' => [
                    'key_prepend' => $indent,
                    'key' => $this->getKeyDeepest($key_created),
                    'separator' => ':',
                    'eol' => "\n",
                ],
                'keys' => $key_created,
            ];
            $_keys[$key_created] = [
                'line' => $line,
                'dimension' => $dimension,
                'array_type' => 'associative',
            ];
            if ($key_created === $key) {
                $_segmen[$line][$dimension]['segmen']['value_prepend'] = ' ';
                $_keys[$key_created]['value'] = $value;
                $_keys[$key_created]['changed'] = true;
            }
            $line++;
            $dimension++;




/*
 */
            // if (null === $key_above) {

            // }
            // else {
                // $line_above = $this->keys[$key_above]['line'];

                // $line = $this->keys[$key_above]['line'] + 1;
            // }
        }
        if (null === $parent_exists) {
            if (0 !== $this->last_line) {
                $this->addEolLastLine();
            }
            $this->segmen += $_segmen;
            $this->keys += $_keys;
            // $this->keys = array_merge($this->keys, $_keys);
        }
        else {
            $this->modifySegmenPosition($parent_exists_line + 1, count($_segmen));
            ArrayHelper::elementEditor($this->segmen, 'insert', 'after', $parent_exists_line, $_segmen);
            // $debugname = 'segmen'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
            $this->modifyKeysPosition($parent_exists_line + 1, count($_keys));
            ArrayHelper::elementEditor($this->keys, 'insert', 'after', $parent_exists, $_keys);
            // $debugname = 'keys'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
        }
        // $debugname = 'segmen'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
        // $debugname = '_segmen'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
        // $debugname = 'keys'; $debugvariable = '|||wakwaw|||'; if (array_key_exists($debugname, get_defined_vars())) { $debugvariable = $$debugname; } elseif (isset($this) && property_exists($this, $debugname)){ $debugvariable = $this->{$debugname}; $debugname = '$this->' . $debugname; } if ($debugvariable !== '|||wakwaw|||') {        echo "\r\n<pre>" . basename(__FILE__ ). ":" . __LINE__ . " (Time: " . date('c') . ", Direktori: " . dirname(__FILE__) . ")\r\n". 'var_dump(' . $debugname . '): '; var_dump($debugvariable); echo "</pre>\r\n"; }
        // Rebuild last line.
        $this->populateLastLine();

    }


}
