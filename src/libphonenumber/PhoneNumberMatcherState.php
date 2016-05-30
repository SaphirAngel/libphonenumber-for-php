<?php
/**
 * Created by PhpStorm.
 * User: SaphirAngel
 * Date: 25/05/2016
 * Time: 15:54
 */

namespace libphonenumber;

/** The potential states of a PhoneNumberMatcher. */
class PhoneNumberMatcherState
{
    const NOT_READY = 0;
    const READY = 1;
    const DONE = 2;
}