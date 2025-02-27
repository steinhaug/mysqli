<?php
namespace Steinhaug\Mysqli\Traits;

trait UtilityTrait
{
    /**
     * Is the value a classical NULL for the SQL
     *
     * @return boolean True or false
     */
    public function considered_null($val)
    {
        if ($val === false) {
            return true;
        }

        if (!strlen($val)) {
            return true;
        }

        if (mb_strtolower($val) === 'null') {
            return true;
        }

        return false;
    }

    /**
    * True False Boolean converter
    *
    * There are several ways to express a true false switch, it could be 0 and 1 just as on and off. Even
    * true and false in itself does not have anything to do with a boolean used in else if statments.
    * Wrap it around your variable and you get what you intended as logic.
    *
    * Example:
    * $test = 'true';
    * if(_bool($test)){ echo 'true'; } else { echo 'false'; }
    *
    * @param {mixed} $var A true false statment not being a boolean
    *
    * @author Kim Steinhaug, <kim@steinhaug.com>
    *
    * @return {boolean} Boolean
    */
    public function _bool($var)
    {
        if (is_bool($var)) {
            return $var;
        } elseif ($var === null || $var === 'NULL' || $var === 'null') {
            return false;
        } elseif (is_string($var)) {
            $var = mb_strtolower(trim($var));
            if ($var=='false') {
                return false;
            } elseif ($var=='true') {
                return true;
            } elseif ($var=='no') {
                return false;
            } elseif ($var=='yes') {
                return true;
            } elseif ($var=='off') {
                return false;
            } elseif ($var=='on') {
                return true;
            } elseif ($var=='') {
                return false;
            } elseif (ctype_digit($var)) {
                if ((int) $var) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }
        } elseif (ctype_digit((string) $var)) {
            if ((int) $var) {
                return true;
            } else {
                return false;
            }
        } elseif (is_array($var)) {
            if (count($var)) {
                return true;
            } else {
                return false;
            }
        } elseif (is_object($var)) {
            return true; // No reason to (bool) an object, we assume OK for crazy logic
        } else {
            return true; // Whatever came though must be something,  OK for crazy logic
        }
    }

    public function generateRandomString($length = 10) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


}
