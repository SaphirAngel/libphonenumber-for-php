<?php
/**
 * Created by PhpStorm.
 * User: SaphirAngel
 * Date: 26/05/2016
 * Time: 14:09
 */

namespace libphonenumber\Tests\core;


use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberMatch;
use Symfony\Component\Config\Definition\Exception\Exception;

class PhoneNumberMatchTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests the value type semantics. Equality and hash code must be based on the covered range and
     * corresponding phone number. Range and number correctness are tested by
     * {@link PhoneNumberMatcherTest}.
     */
    public function testValueTypeSemantics()
    {
        $number = new PhoneNumber();
        $match1 = new PhoneNumberMatch(10, "1 800 234 45 67", $number);
        $match2 = new PhoneNumberMatch(10, "1 800 234 45 67", $number);

        $this->assertEquals($match1, $match2);
        $this->assertEquals($match1->start(), $match2->start());
        $this->assertEquals($match1->end(), $match2->end());
        $this->assertEquals($match1->number(), $match2->number());
        $this->assertEquals($match1->rawString(), $match2->rawString());
        $this->assertEquals("1 800 234 45 67", $match1->rawString());
    }

    /**
     * Tests the value type semantics for matches with a null number.
     */
    public function testIllegalArguments()
    {
        try {
            new PhoneNumberMatch(-110, "1 800 234 45 67", new PhoneNumber());
            $this->fail();
        } catch (\InvalidArgumentException $e) { /* success */
        }

        try {
           // new PhoneNumberMatch(10, "1 800 234 45 67", null);
           // $this->fail();
        } catch (\Exception $e) { /* success */
        }

        try {
            new PhoneNumberMatch(10, null, new PhoneNumber());
            $this->fail();
        } catch (\Exception $e) { /* success */
        }

        try {
//            new PhoneNumberMatch(10, null, null);
//            $this->fail();
        } catch (\Exception $e) { /* success */
        }
    }
}
