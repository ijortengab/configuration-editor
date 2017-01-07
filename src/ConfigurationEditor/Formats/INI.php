<?php

namespace IjorTengab\ConfigurationEditor\Formats;

use IjorTengab\ParseINI\ParseINI;
use IjorTengab\ConfigurationEditor\FormatInterface;
use IjorTengab\ConfigurationEditor\RuntimeException;
use IjorTengab\Tools\Functions\ArrayHelper;
use Psr\Log\LoggerInterface;

/**
 * Extend dari Class ParseINI dimana telah implementasi aturan dari
 * FormatInterface dan terdapat fitur untuk unparse (encode kembali)
 * ke format INI.
 */
class INI extends ParseINI implements FormatInterface
{
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
     * $string = <<<'INI'
     * aa[] = '00'
     * aa[] = '11'
     * bb[] = '00'
     * bb[] = '11'
     * cc[] = '00'
     * INI;
     * $config = new ConfigurationEditor(new INI($string));
     * $config->setData('aa[]', '22');
     * $config->save();
     * $string = (string) $config;
     * ```
     * Pada code diatas, maka nilai dari $string akan menjadi
     * ```
     * $string = <<<'INI'
     * aa[] = '00'
     * aa[] = '11'
     * aa[] = '22'
     * bb[] = '00'
     * bb[] = '11'
     * cc[] = '00'
     * INI;
     * ```
     * Pada contoh diatas, maka nilai dari property ini
     * setelah dilakukan new instance adalah:
     * ```
     * $this->max_value_of_sequence = [
     *     'aa[]' => 1,
     *     'bb[]' => 2,
     *     'cc[]' => 0,
     * ];
     * ```
     * dan setelah dijalankan method ::setData, nilai dari property ini
     * adalah
     * ```
     * $this->max_value_of_sequence = [
     *     'aa[]' => 2,
     *     'bb[]' => 2,
     *     'cc[]' => 0,
     * ];
     * ```
     */
    protected $max_value_of_sequence = [];

    /**
     * Property untuk menyimpan array. Informasi yang disimpan
     * adalah daftar key yang awalnya merupakan sequence of scalar
     * (indexed array) yang akan diubah menjadi mapping of scalar
     * associative array. Hal ini disebabkan oleh penghilangan
     * element array sehingga urutannya menjadi tidak lagi urut dari
     * 0 ke angka terakhir. Bisa juga disebabkan oleh penambahan
     * index yang melompat dari urutan seharusnya.
     *
     * Contoh:
     * ```
     * $string = <<<'INI'
     * aa[] = '00'
     * aa[] = '11'
     * aa[] = '22'
     * bb[] = '00'
     * bb[] = '11'
     * cc[] = '00'
     * cc[] = '11'
     * INI;
     * $config = new ConfigurationEditor(new INI($string));
     * $config->delData('aa[1]');
     * $config->setData('bb[9]', '99');
     * $config->save();
     * $string = (string) $config;
     * ```
     * Pada code diatas, maka nilai dari $string akan menjadi
     * ```
     * $string = <<<'INI'
     * aa[0] = '00'
     * aa[2] = '22'
     * bb[0] = '00'
     * bb[1] = '11'
     * bb[9] = '99'
     * cc[] = '00'
     * cc[] = '11'
     * INI;
     * ```
     * Pada contoh diatas, maka nilai dari property ini adalah:
     * ```
     * $convert_sequence_to_mapping = [
     *     'aa[]' => true,
     *     'bb[]' => true,
     * ];
     * ```
     */
    protected $convert_sequence_to_mapping = [];

    /**
     * Implements of FormatInterface::__toString().
     *
     * Magic method - jika object ini di-convert ke string maka
     * itu sama dengan me-return property $raw.
     *
     * Segala perubahan (self::setData atau self::delData)
     * tidak otomatis mengubah property $raw. Harap menjalankan
     * terlebih dahulu method self::save() agar property $raw
     * diperbaharui sesuai perubahan terbaru.
     */
    public function __toString()
    {
        return $this->raw;
    }

    /**
     * Implements of FormatInterface::setFile().
     *
     * {@inheritdoc}
     */
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * Implements of FormatInterface::readFile().
     *
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
     * Implements of FormatInterface::getFile().
     *
     * {@inheritdoc}
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Implements of FormatInterface::setData().
     *
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
        $old_key = $key;
        $this->processSequence($old_key, $key, $array_type, $key_parent);

        // Modifikasi property $segmen dan $keys.
        do {
            // $key jika sudah ada di property $keys, maka kita tinggal mengubah
            // value pada informasi di property $keys.
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
                break;
            }
            // Jika belum ada informasi $key pada property $keys, maka kita
            // perlu membuat segmen baru.
            $this->populateKeysDeleted($key, $keys_deleted);
            $this->populateParentKeyThatExists($key, $key_parent, $keys_deleted);
            $this->deleteKeys($keys_deleted);

            // Agar rapih, maka kita tempatkan dalam satu urutan dengan
            // sibling.
            $key_above = $this->getKeyChildrenYounger($key_parent);
            if (null === $key_above) {
                // Taruh dipaling bawah.
                if (0 !== $this->last_line) {
                    $this->addEolLastLine();
                }
                $next_line = ++$this->last_line;
                $this->segmen[$next_line]['segmen'] = [
                    'key' => $old_key,
                    'separator' => '=',
                    'key_append' => ' ',
                    'value_prepend' => ' ',
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
            // Sisipkan tepat dibawah sibling.
            $line_above = $this->keys[$key_above]['line'];

            // Contoh pada kasus:
            // ```ini
            // aa = AA
            // bb[bbb][bbbb] = BBBB
            // ```
            // $config->setData('bb[bbb][cccc]', 'CCCC');
            // maka perlu ditambah eol pada last line.
            if ($this->last_line === $line_above) {
                $this->addEolLastLine();
            }
            $line_new = $line_above + 1;
            $this->modifySegmenPosition($line_new);
            $new_segmen = [ $line_new => [ 'segmen' => [
                'key' => $old_key,
                'separator' => '=',
                'key_append' => ' ',
                'value_prepend' => ' ',
                'eol' => "\n"
            ]]];
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
    }

    /**
     * Implements of FormatInterface::setArrayData().
     *
     * {@inheritdoc}
     */
    public function setArrayData(Array $array)
    {
        $array = ArrayHelper::dimensionalSimplify($array);
        foreach ($array as $key => $value) {
            $this->setData($key, $value);
        }
    }

    /**
     * Implements of FormatInterface::getData().
     *
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
     * Implements of FormatInterface::delData().
     *
     * {@inheritdoc}
     */
    public function delData($key)
    {
        if (false === $this->has_parsed) {
            $this->parse();
        }
        $keys_deleted = [];
        $this->populateKeysDeleted($key, $keys_deleted);
        $this->deleteKeys($keys_deleted);
        return $this->_delData($key);
    }

    /**
     * Implements of FormatInterface::clear().
     *
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->has_parsed = true;
        $this->raw = '';
        $this->sequence_of_scalar = [];
        $this->max_value_of_sequence = [];
        $this->convert_sequence_to_mapping = [];
        $this->last_line = 0;
        $this->segmen = [];
        $this->keys = [];
        $this->data = [];
    }

    /**
     * Implements of FormatInterface::save().
     *
     * {@inheritdoc}
     */
    public function save()
    {
        $this->modifySegmenUpdateKey();
        $this->modifySegmenUpdateValue();
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
     * Implements of FormatInterface::setLog().
     *
     * {@inheritdoc}
     */
    public function setLog(LoggerInterface $log)
    {
        $this->log = $log;
        return $this;
    }

    /**
     * Implements of FormatInterface::getLog().
     *
     * {@inheritdoc}
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Menghapus data $key dimana $key merupakan children dengan dimensi
     * terdalam.
     */
    protected function _delData($key)
    {
        if (array_key_exists($key, $this->keys)) {
            // Khusus delete key numeric yang bukan merupakan
            // nilai key tertinggi, maka kita perlu convert
            // dari sequence of scalar (indexed array) menjadi
            // mapping of scalar (associative array).
            if (
                preg_match('/(.*)\[(\d+)\]$/', $key, $m) &&
                array_key_exists($m[1] . '[]', $this->sequence_of_scalar)
            ) {
                $_key = $m[1] . '[]';
                $current_max_value = ArrayHelper::getHighestIndexedKey($this->data($m[1]));
                if ($current_max_value != $m[2]) {
                    $this->convert_sequence_to_mapping[$_key] = true;
                }
            }
            // Clear segmen.
            $line = $this->keys[$key]['line'];
            unset($this->segmen[$line]);
            // Clear keys.
            unset($this->keys[$key]);
            // Clear data.
            $this->data($key, null);
        }
        return $this;
    }

    /**
     * Satu method untuk semua kebutuhan CRUD.
     * @see ArrayHelper::propertyEditor().
     */
    protected function data()
    {
        return ArrayHelper::propertyEditor($this, 'data', func_get_args());
    }

    /**
     * Override parent::afterLooping().
     */
    protected function afterLooping()
    {
        parent::afterLooping();
        $this->populateMaxValueOfSequence();
        $this->populateLastLine();
        $this->populateOriginalValueInKeys();
    }

    /**
     * Mengisi property $max_value_of_sequence berdasarkan
     * informasi pada property $sequence_of_scalar.
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
        foreach ($this->sequence_of_scalar as $key_square => $value) {
            $key = substr($key_square, 0, -2);
            $current_max_value = ArrayHelper::getHighestIndexedKey($this->data($key));
            $this->max_value_of_sequence[$key_square] = $current_max_value;
        }
    }

    /**
     * Mengisi property $last_line dengan melihat informasi
     * pada property $segmen.
     */
    protected function populateLastLine()
    {
        $this->last_line = max(array_keys($this->segmen));
    }

    /**
     * Menambah informasi original value pada property $keys
     * yang dilakukan saat new instance sebelum dilakukan edit data.
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
     * Mengubah informasi pada property $segmen.
     *
     * Mengupdate informasi 'key' yang disebabkan harus diconvert
     * dari awalnya sequence menjadi mapping demi konsistensi value.
     */
    protected function modifySegmenUpdateKey()
    {
        // Misalnya kita punya data.
        // ```ini
        // xyz[] = 'aa';
        // xyz[] = 'bb';
        // xyz[] = 'cc';
        // ```
        // Jika dilakukan hal ini
        // $config->delData('xyz[0]');
        // $config->setData('xyz[0]', 'aa');
        // Maka tetap akan dilakukan convert to sequence menjadi
        // ```ini
        // xyz[1] = 'bb';
        // xyz[2] = 'cc';
        // xyz[0] = 'aa';
        // ```
        // Hal ini sesuai dengan behaviour dari set array[] di PHP.
        //
        // Untuk kasus yang lain:
        // ```ini
        // xyz[] = 'aa';
        // xyz[] = 'bb';
        // xyz[] = 'cc';
        // ```
        // $config->delData('xyz[1]');
        // $config->delData('xyz[2]');
        // $config->setData('xyz[1]', '...');
        // $config->setData('xyz[2]', '...');
        // akan terjadi $this->convert_sequence_to_mapping['xyz[]'] = true;
        // karena penghapusan del data 'xyz[1]' yang merupakan
        // key yang berada di tengah-tengah urutan data.
        // Tapi karena hasil keseluruhannya sama-sama urut:
        // ```ini
        // xyz[0] = 'aa';
        // xyz[1] = '...';
        // xyz[2] = '...';
        // ```
        // maka tidak perlu dilakukan convert to sequence.
        // Oleh karena itu perlu kita lakukan verifkasi.
        $list = $this->convert_sequence_to_mapping;
        if (!empty($list)) {
            do {
                $key_square = key($list);
                $key = substr($key_square, 0, -2);
                // Skip jika urut sesuai penjelasan diatas.
                if (ArrayHelper::isIndexedKeySorted($this->data($key))) {
                    continue;
                }
                if ($key === '') {
                    $pattern = '/^\d+$/';
                }
                else {
                    $pattern = '/' . preg_quote($key) . '\[\d+\]' . '/';
                }
                $sequences = ArrayHelper::filterKeyPattern($this->keys, $pattern);
                foreach ($sequences as $key_mapping => $info) {
                    if (isset($info['line'])) {
                        $line = $info['line'];
                        $this->segmen[$line]['segmen']['key'] = $key_mapping;
                    }
                }
            }
            while(next($list));
            // Clear.
            $this->convert_sequence_to_mapping = [];
        }
    }

    /**
     * Mengubah informasi pada property $segmen.
     *
     * Mengupdate informasi 'value' hasil perubahan oleh method self::setData().
     */
    protected function modifySegmenUpdateValue()
    {
        $list = ArrayHelper::filterChild($this->keys, ['changed' => true]);
        if (!empty($list)) {
            do {
                $key = key($list);
                $info = $list[$key];
                $line = $info['line'];
                $value = $info['value'];
                $this->segmen[$line]['segmen']['value'] = $value;
            }
            while(next($list));
        }
    }

    /**
     * Membangun kembali value dari property $raw berdasarkna informasi
     * pada property $segmen.
     */
    protected function rebuildRaw()
    {
        $this->raw = '';
        foreach ($this->segmen as $info) {
            $segmen = array_merge([
                'key_prepend' => '',
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
                elseif (is_numeric($segmen['value']) && $segmen['quote_value'] == '') {
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
            $position = explode(' ', 'key_prepend key key_append separator value_prepend quote_value value quote_value comment eol');
            array_walk($position, function ($val) use ($segmen, &$_raw) {
                $_raw .= $segmen[$val];
            });
            $this->raw .= $_raw;
        }
    }

    /**
     * Memodifikasi argument yang masuk ke method agar sesuai kebutuhan saat
     * set data berupa sequence (indexed array).
     *
     * Memodifikasi argument dari parameter $key diperlukan karena nantinya akan
     * dimasukkan kedalam property $keys sehingga perlu dijadikan unique.
     * Sementara argument dari parameter lainnya dimodifikasi karena akan
     * dijadikan referensi.
     *
     * Mengedit property $max_value_of_sequence, menyesuaikan dengan set data
     * sequence terbaru.
     *
     * Mengisi property $convert_sequence_to_mapping jika ditemukan pengisian
     * data yang menyebabkan sequence menjadi tidak urut.
     *
     * Preprocess ini dijalankan oleh method self::setData().
     */
    protected function processSequence(&$old_key, &$key, &$array_type, &$key_parent)
    {
        if ($key === '[]') {
            $sequence_type = 'empty';
            $array_type = 'indexed';
            // $int = ...; // Continue.
            $key_square = '[]';
        }
        elseif (is_numeric($key)) {
            $sequence_type = 'numeric';
            // $array_type = '...'; // Continue.
            $int = $key;
            $key_square = '[]';
        }
        elseif (preg_match('/(.*)\[([^\[\]]*)\]$/', $key, $m)) {
            $key_parent = $m[1];
            if ($m[2] == '') {
                $sequence_type = 'empty';
                $array_type = 'indexed';
                // $int = ...; // Continue.
                $key_square = $key_parent . '[]';
            }
            elseif (is_numeric($m[2])) {
                $sequence_type = 'numeric';
                // $array_type = '...'; // Continue.
                $int = $m[2];
                $key_square = $key_parent . '[]';
            }
            else {
                return;
            }
        }
        else {
            return;
        }
        switch ($sequence_type) {
            case 'empty':
                // Ubah $key.
                if (array_key_exists($key_square, $this->max_value_of_sequence)) {
                    $c = ++$this->max_value_of_sequence[$key_square];
                }
                else {
                    $c = $this->max_value_of_sequence[$key_square] = 0;
                }
                // Populate $int.
                $key = $int = $c;
                empty($key_parent) or $key = $key_parent . '[' . $c . ']';
                break;

            case 'numeric':
                // Perhatikan pada kasus
                // ```
                // $config->setData(3, '...');
                // $config->setData(3.8, '...');
                // $config->setData('3', '...');
                // $config->setData('3.8', '...');
                // ```
                // Pada set data = float, maka akan diubah menjadi floor integer
                // sesuai dengan kaidah PHP.
                // Pada set data float tapi string, maka key tersebut disamakan
                // dengan associative array. Contoh:
                // ```
                // self::setData('56.7', '...');
                // ```
                if ($this->isNumericInteger($int)) {
                    $array_type = 'indexed';
                    $int = (int) $int;
                }
                elseif (is_float($int)) {
                    $array_type = 'indexed';
                    $int = (int) floor($int);
                }
                break;
        }
        if ($array_type == 'indexed') {
            do {
                if (array_key_exists($key, $this->keys)) {
                    break;
                }
                if ($int === 0) {
                    break;
                }
                if (array_key_exists($key_square, $this->max_value_of_sequence)) {
                    // Ubah value dari argument $oldkey dengan
                    // penjelasab sbb.
                    //
                    // Pada kasus.
                    // ```
                    // aa[] = 00
                    // aa[] = 11
                    // aa[] = 22
                    // ```
                    // $config->delData('aa[2]');
                    // $config->setData('aa[2]', '...');
                    // mengakibatkan menjadi
                    // ```
                    // aa[] = 11
                    // aa[] = 22
                    // aa[2] = ...
                    // ```
                    // Oleh karena itu, jika key yang diset adalah
                    // key terakhir, maka ubah oldkey yang akan ditempatkan
                    // di segman.
                    // sehingga segmen yang terbentuk nantinya seperti ini.
                    // ```
                    // aa[] = 11
                    // aa[] = 22
                    // aa[] = ...
                    $current_max_value = ArrayHelper::getHighestIndexedKey($this->data($key_parent));
                    if ($int === ++$current_max_value) {
                        $old_key = $key_square;
                        break;
                    }
                }
                $this->convert_sequence_to_mapping[$key_square] = true;
            }
            while (false);
            // Update max value.
            if (array_key_exists($key_square, $this->max_value_of_sequence)) {
                $current_max_value = $this->max_value_of_sequence[$key_square];
                if ($key > $current_max_value) {
                    $this->max_value_of_sequence[$key_square] = $key;
                }
            }
            else {
                $this->max_value_of_sequence[$key_square] = $key;
            }
        }
    }

    /**
     * Mengubah value non string menjadi string.
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
     * String yang secara syntax adalah tipe non string.
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
     * Menambah end of line pada baris terakhir.
     */
    protected function addEolLastLine()
    {
        $last_line = $this->last_line;
        if (!isset($this->segmen[$last_line]['segmen']['eol'])) {
            $this->segmen[$last_line]['segmen']['eol'] = "\n";
        }
    }

    /**
     * Method untuk mengisi argument dari parameter $keys_deleted.
     * Cara kerja method ini misalnya sebagai berikut:
     * ```
     * $string = <<<'INI'
     * country[name][fullname][internationalname][default] = Indonesia
     * country[name][fullname][internationalname][complete] = Negara Indonesia
     * INI;
     * $config = new ConfigurationEditor(new INI($string));
     * ```
     * Saat dilakukan set data seperti dibawah ini:
     * ```
     * $config->setData('country[name]', 'Indonesia');
     * ```
     * Maka key dibawah ini:
     * ```
     * country[name][fullname][internationalname][default] = Indonesia
     * country[name][fullname][internationalname][complete] = Negara Indonesia
     * ```
     * harus di-delete karena sudah tidak lagi relevan.
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
     * Method untuk memperbarui argument dari parameter $key_parent
     * yang mana key parent tersebut exists pada property $keys.
     *
     * Sekaligus juga mengisi argument dari parameter $keys_deleted jika
     * ditemukan key-key yang harus didelete.
     *
     * Contoh pertama:
     * ```
     * $string = <<<'INI'
     * country[name][fullname][internationalname][default] = Indonesia
     * country[name][fullname][internationalname][complete] = Negara Indonesia
     * INI;
     * $config = new ConfigurationEditor(new INI($string));
     * ```
     * Saat dilakukan set data seperti dibawah ini:
     * ```
     * $config->setData('country[name][fullname][localname][other]', 'Nusantara');
     * ```
     * akan didapat argument baru dari parameter sebagai berikut:
     * ```
     * $key_parent = 'country[name][fullname]';
     * ```
     * $key_deleted tidak ada perubahan.
     *
     * Contoh kedua:
     * ```
     * $string = <<<'INI'
     * country = 'Indonesia'
     * INI;
     * $config = new ConfigurationEditor(new INI($string));
     * ```
     * Saat dilakukan set data seperti dibawah ini:
     * ```
     * $config->setData('country[name][fullname][localname][other]', 'Nusantara');
     * ```
     * akan didapat argument baru dari parameter sebagai berikut:
     * ```
     * $key_parent = '';
     * $key_deleted[] = 'country';
     * ```
     * Pada contoh diatas $key_deleted bertambah element array-nya yakni
     * key 'country'. Key country yang berisi value string sudah tidak lagi
     * relevant dengan set data terbaru dimana key country adalah bagian dari
     * array multidimensi.
     */
    protected function populateParentKeyThatExists($key, &$key_parent, &$keys_deleted)
    {
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
     * Mendapatkan key children yang diset paling bawah dari key parent.
     * Key ini digunakan saat set data dimana key ini akan tepat berada diatas
     * dari key baru hasil set new data.
     * Contoh:
     * ```
     * $string = <<<'INI'
     * aa[] = '00'
     * aa[] = '11'
     * bb[] = '00'
     * bb[] = '11'
     * cc[] = '00'
     * INI;
     * $config = new ConfigurationEditor(new INI($string));
     * $config->setData('aa[]', '22');
     * $config->save();
     * $string = (string) $config;
     * ```
     * Pada contoh diatas, maka nilai hasil return dari method ini
     * adalah:
     * ```
     * return 'aa[1]';
     * ```
     * Pada code diatas, maka nilai dari $string akan menjadi:
     * ```
     * $string = <<<'INI'
     * aa[] = '00'
     * aa[] = '11'
     * aa[] = '22'
     * bb[] = '00'
     * bb[] = '11'
     * cc[] = '00'
     * INI;
     * ```
     */
    protected function getKeyChildrenYounger($key_parent)
    {
        $found = null;
        if ($key_parent === '') {
            // Ini berarti self::setData('[]', '...');
            if (array_key_exists('[]', $this->max_value_of_sequence)) {
                $array = ArrayHelper::filterKeyInteger($this->data);
                $line = null;
                foreach ($array as $key => $value) {
                    if ($info = $this->keys[$key]) {
                        if (null === $line) {
                            $line = $info['line'];
                            $found = $key;
                        }
                        if ($line < $info['line']) {
                            $found = $key;
                            $line = $info['line'];
                        }
                    }
                }
            }
        }
        else {
            $children = $this->getData($key_parent);
            $array = ArrayHelper::dimensionalSimplify($children);
            $line = null;
            foreach ($array as $key => $value) {
                // Ubah $key dari
                // - "aa" menjadi "[aa]"
                // - "aa[bb]" menjadi "[aa][bb]",
                // - "aa[bb][cc]" menjadi "[aa][bb][cc]".
                $key = rtrim($key, ']') . ']';
                $key = preg_replace('/[\[]/', '][', $key, 1);
                $key = '[' . $key;
                // Tambah prefix.
                $key = $key_parent . $key;
                if ($info = $this->keys[$key]) {
                    if (null === $line) {
                        $line = $info['line'];
                        $found = $key;
                    }
                    if ($line < $info['line']) {
                        $found = $key;
                        $line = $info['line'];
                    }
                }
            }
        }
        return $found;
    }

    /**
     * Segmen-segmen yang berada pada baris $int dan dibawahnya maka
     * akan increment satu digit. Pada text editor, ini setara dengan
     * menekan tombol enter untuk mendapat blank space pada baris $int.
     */
    protected function modifySegmenPosition($int)
    {
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
     * Informasi line pada property $keys yang berada pada baris $int
     * dan dibawahnya maka akan increment satu digit.
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
     * Menghapus key-key pada property $keys yang direferensikan oleh
     * parameter $keys_deleted.
     * Penyebab key-key ini dihapus karena sudah tidak relevan untuk tetap
     * exists yang disebabkan oleh set data atau del data.
     * Penjelasan dengan contoh dapat dilihat pada self::populateKeysDeleted(),
     * dan self::populateParentKeyThatExists().
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
     * Mengecek kembali apakah hasil dari is_numeric merupakan integer
     * atau string yang integer.
     */
    protected function isNumericInteger($mixed)
    {
        $is_int = false;
        if (is_int($mixed)) {
            $is_int = true;
        }
        elseif (is_string($mixed)) {
            $test_int = (int) $mixed;
            $test_string = (string) $test_int;
            if ($test_string === $mixed) {
                $is_int = true;
            }
        }
        return $is_int;
    }
}
