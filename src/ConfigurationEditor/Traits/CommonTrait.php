<?php
    
    
namespace IjorTengab\ConfigurationEditor\Traits;    
    
trait CommonTrait
{
    
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

