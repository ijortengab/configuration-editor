<?php

namespace IjorTengab\ConfigurationEditor\Formats;

use IjorTengab\ConfigurationEditor\FormatInterface;
use IjorTengab\ParseINI;
use IjorTengab\Tools\Traits\PropertyArrayManagerTrait;
use Psr\Log\LoggerInterface;

/**
 * Extend dari Class ParseINI dimana telah implementasi aturan dari
 * FormatInterface.
 */
class INI extends ParseINI implements FormatInterface
{
    /**
     * Trait berisi cara mudah untuk CRUD value dari property bertipe array.
     */
    use PropertyArrayManagerTrait;

    /**
     * Satu method untuk semua kebutuhan CRUD.
     * @see ::propertyArrayManager().
     */
    public function data()
    {
        return $this->propertyArrayManager('data', func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function setFileName($filename)
    {
        $this->filename = $filename;
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
        if (false === $this->has_parsing) {
            $this->parse();
            $this->has_parsing = true;
        }

        $data_map = $this->data_map;


        if (isset($this->data_map[$key])) {
            $line = $this->data_map[$key];
            $this->line_storage[$line]['value'] = $value;
        }
        else {
            // Menambah value baru pada akhir baris.
            $key_unique = $key;
            if (preg_match('/(.*)\[\]$/', $key, $m)) {
                $c = 0;
                $key_unique = $m[1] . '[' . $c . ']';
                if (array_key_exists($key_unique, $this->data_map)) {
                    do {
                        $c++;
                        $key_unique = $m[1] . '[' . $c . ']';
                    } while (array_key_exists($key_unique, $this->data_map));
                }
            }
            // Karena ini ditambah pada baris baru
            // maka pastikan bahwa baris sebelumnya ada EOL.
            end($this->line_storage);
            $line = key($this->line_storage);
            $eol = $this->line_storage[$line]['eol'];
            if (!in_array($eol, ["\r", "\n", "\r\n"])) {
                $this->line_storage[$line]['eol'] = $this->most_eol;
            }
            $this->line_storage[] = array_merge($this->lineStorageDefault(), [
                'key' => $key,
                'key append' => ' ',
                'equals' => '=',
                'value prepend' => ' ',
                'value' => $value,
                'value append' => ' ',
                'eol' => $this->most_eol,
            ]);

            // Cari last record;
            end($this->line_storage);
            $line = key($this->line_storage);

            // Taro di mapping.
            $this->data_map[$key_unique] = $line;
        }

        // Ada kasus ternyata value awal tidak ada quote
        // lalu diedit sehingga value memiliki trailing/leading whitespace
        // untuk seperti ini, maka perlu kita paksa kasih quote.
        if (empty($this->line_storage[$line]['quote'])) {
            $_value = $this->line_storage[$line]['value'];
            $test = trim($_value);
            if ($test != $_value) {
                $this->line_storage[$line]['quote'] = "'";
            }
        }

        $this->data($key, $value);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getData($key = null)
    {
        if (false === $this->has_parsing) {
            $this->parse();
            $this->has_parsing = true;
        }
        return (null === $key) ? $this->data() : $this->data($key);
    }

    /**
     * {@inheritdoc}
     */
    public function delData($key)
    {
        if (false === $this->has_parsing) {
            $this->parse();
            $this->has_parsing = true;
        }
        $data_map = $this->data_map;
        if (isset($this->data_map[$key])) {
            $line = $this->data_map[$key];
            // Hapus dengan kembali ke default.
            $this->line_storage[$line] = $this->lineStorageDefault();
        }
        return $this->data($key, null);
    }

    /**
     * {@inheritdoc}
     */
    public function saveData()
    {
        // $xx = $this->data;
        // $debugname = 'xx'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
        // $data_map = $this->data_map;
        // $debugname = 'data_map'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

        $filename = $this->filename;
        if (null !== $filename) {
            $output = '';
            foreach ($this->line_storage as $info) {
                $output .= $info['key prepend'];
                $output .= $info['key'];
                $output .= $info['key append'];
                $output .= $info['equals'];
                $output .= $info['value prepend'];
                $output .= $info['quote'];
                $output .= $info['value'];
                $output .= $info['quote'];
                $output .= $info['value append'];
                $output .= $info['comment'];
                $output .= $info['eol'];
            }
            file_put_contents($filename, $output);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setLog(LoggerInterface $log)
    {
        return parent::setLog($log);
    }

    /**
     * {@inheritdoc}
     */
    public function getLog()
    {
        return parent::getLog();
    }
}
