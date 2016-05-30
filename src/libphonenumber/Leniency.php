<?php
/**
 * Created by PhpStorm.
 * User: SaphirAngel
 * Date: 26/05/2016
 * Time: 10:17
 */

namespace libphonenumber;


class Leniency
{
    const VALID = 'valid';
    const POSSIBLE = 'possible';

    private $level;

    public function __construct($level)
    {
        $refl = new \ReflectionClass($this);
        if (!in_array($level, $refl->getConstants())) {
            throw new \Exception('Leniency level not exist');
        }

        $this->level = $level;
    }

    public function verify(PhoneNumber $number, $candidate, PhoneNumberUtil $util) {
        $verifyMethod = $this->level;
        return $this->$verifyMethod($number, $candidate, $util);
    }


    public function valid(PhoneNumber $number, $candidate, PhoneNumberUtil $util) {
        if (!$util->isValidNumber($number) || !PhoneNumberMatcher::containsOnlyValidXChars($number, $candidate, $util)) {
            return false;
        }


        return PhoneNumberMatcher::isNationalPrefixPresentIfRequired($number, $util);
    }

    public function possible(PhoneNumber $number, $candidate, PhoneNumberUtil $util) {
        return $util->isPossibleNumber($number);
    }

    public function level() {
        return $this->level;
    }
}