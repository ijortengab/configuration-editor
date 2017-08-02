<?php

namespace IjorTengab\ConfigurationEditor\Traits;

use IjorTengab\Tools\Functions\ArrayHelper;

trait IniYamlFormatTrait
{
    use CommonTrait;
    
    /**
     * todo, create doc here.
     */
    protected function processSequence(&$old_key, &$key, &$array_type, &$key_parent)
    {
        if ($key === '[]') {
            $key_type = 'empty';
            $array_type = 'indexed';
            // $int = ...; // Continue.
            $key_square = '[]';
        }
        elseif (is_numeric($key)) {
            $key_type = 'numeric';
            // $array_type = '...'; // Continue.
            $int = $key;
            $key_square = '[]';
        }
        elseif (preg_match('/(.*)\[([^\[\]]*)\]$/', $key, $m)) {
            $key_parent = $m[1];
            if ($m[2] == '') {
                $key_type = 'empty';
                $array_type = 'indexed';
                // $int = ...; // Continue.
                $key_square = $key_parent . '[]';
            }
            elseif (is_numeric($m[2])) {
                $key_type = 'numeric';
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

        // Populate $int.
        switch ($key_type) {
            case 'empty':
                // Ubah $key.
                $int = 0;
                if (array_key_exists($key_square, $this->max_value_of_sequence)) {
                    $int = $this->max_value_of_sequence[$key_square] + 1;
                }
                $key = $int;
                empty($key_parent) or $key = $key_parent . '[' . $int . ']';
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
            // Modifikaksi argument $oldkey serta set convert ke mapping
            // jika diperlukan.
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
            do {
                if (array_key_exists($key, $this->keys)) {
                    break;
                }
                if (array_key_exists($key_square, $this->max_value_of_sequence)) {
                    $array = ($key_parent === '') ? (array) $this->data : $this->data($key_parent);
                    $current_max_value = ArrayHelper::getHighestIndexedKey($array);
                    if ($int === ++$current_max_value) {
                        $old_key = $key_square;
                        break;
                    }
                }
                else {
                    if ($int === 0) {
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
                if ($int > $current_max_value) {
                    $this->max_value_of_sequence[$key_square] = $int;
                }
            }
            else {
                $this->max_value_of_sequence[$key_square] = $int;
            }
        }
    }
    
    /**
     * todo, create doc here.
     */
    protected function getKeyChildrenYounger($key_parent)
    {
        $found = null;
        if ($key_parent === '') {
            // Ini berarti self::setData('[]', '...');
            if (array_key_exists('[]', $this->max_value_of_sequence)) {
                // Default value dari ParseINI::$data adalah null, gunakan magic.
                $array = ArrayHelper::filterKeyInteger((array) $this->data);
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
        elseif ($children = $this->getData($key_parent)) {
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

}
