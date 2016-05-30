<?php
/**
 * Created by PhpStorm.
 * User: SaphirAngel
 * Date: 26/05/2016
 * Time: 14:32
 */

namespace libphonenumber\Tests\core;


use libphonenumber\CountryCodeSource;
use libphonenumber\CountryCodeToRegionCodeMapForTesting;
use libphonenumber\Leniency;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberMatch;
use libphonenumber\PhoneNumberMatcher;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\RegionCode;
use Symfony\Component\Config\Definition\Exception\Exception;

class PhoneNumberMatcherTest extends \PHPUnit_Framework_TestCase
{
    const TEST_META_DATA_FILE_PREFIX = "../../../Tests/libphonenumber/Tests/core/data/PhoneNumberMetadataForTesting";

    /**
     * @var PhoneNumberUtil
     */
    protected $phoneUtil;

    /** @var NumberTest[] */
    private $IMPOSSIBLE_CASES;
    private $POSSIBLE_ONLY_CASES;
    private $VALID_CASES;
    private $STRICT_GROUPING_CASES;
    private $EXACT_GROUPING_CASES;

    public function setUp()
    {
        PhoneNumberUtil::resetInstance();
        $this->phoneUtil = PhoneNumberUtil::getInstance(
            self::TEST_META_DATA_FILE_PREFIX,
            CountryCodeToRegionCodeMapForTesting::$countryCodeToRegionCodeMapForTesting
        );

        /* IMPOSSIBLE_CASES */
        $this->IMPOSSIBLE_CASES[] = new NumberTest("12345", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("23456789", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("234567890112", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("650+253+1234", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("3/10/1984", RegionCode::CA);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("03/27/2011", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("31/8/2011", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("1/12/2011", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("10/12/82", RegionCode::DE);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("650x2531234", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("2012-01-02 08:00", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("2012/01/02 08:00", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("20120102 08:00", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("2014-04-12 04:04 PM", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("2014-04-12 &nbsp;04:04 PM", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("2014-04-12 &nbsp;04:04 PM", RegionCode::US);
        $this->IMPOSSIBLE_CASES[] = new NumberTest("2014-04-12  04:04 PM", RegionCode::US);

        /* POSSIBLE ONLY CASES */
        // US numbers cannot start with 7 in the test metadata to be valid.
        $this->POSSIBLE_ONLY_CASES[] = new NumberTest("7121115678", RegionCode::US);
        // 'X' should not be found in numbers at leniencies stricter than POSSIBLE, unless it represents
        // a carrier code or extension.
        $this->POSSIBLE_ONLY_CASES[] = new NumberTest("1650 x 253 - 1234", RegionCode::US);
        $this->POSSIBLE_ONLY_CASES[] = new NumberTest("650 x 253 - 1234", RegionCode::US);
        $this->POSSIBLE_ONLY_CASES[] = new NumberTest("6502531x234", RegionCode::US);
        //$this->POSSIBLE_ONLY_CASES[] = new NumberTest("(20) 3346 1234", RegionCode::GB); // Non-optional NP omitted

        /* VALID VASES */
        $this->VALID_CASES[] = new NumberTest("65 02 53 00 00", RegionCode::US);
        $this->VALID_CASES[] = new NumberTest("6502 538365", RegionCode::US);
        $this->VALID_CASES[] = new NumberTest("650//253-1234",
            RegionCode::US); // 2 slashes are illegal at higher levels
        $this->VALID_CASES[] = new NumberTest("650/253/1234", RegionCode::US);
        $this->VALID_CASES[] = new NumberTest("9002309. 158", RegionCode::US);
        $this->VALID_CASES[] = new NumberTest("12 7/8 - 14 12/34 - 5", RegionCode::US);
        $this->VALID_CASES[] = new NumberTest("12.1 - 23.71 - 23.45", RegionCode::US);
        $this->VALID_CASES[] = new NumberTest("800 234 1 111x1111", RegionCode::US);
        $this->VALID_CASES[] = new NumberTest("1979-2011 100", RegionCode::US);
        $this->VALID_CASES[] = new NumberTest("+494949-4-94", RegionCode::DE); // National number in wrong format
        $this->VALID_CASES[] = new NumberTest("\u{FF14}\u{FF11}\u{FF15}\u{FF16}\u{FF16}\u{FF16}\u{FF16}-\u{FF17}\u{FF17}\u{FF17}",
            RegionCode::US);
        $this->VALID_CASES[] = new NumberTest("2012-0102 08", RegionCode::US); // Very strange formatting.
        $this->VALID_CASES[] = new NumberTest("2012-01-02 08", RegionCode::US);
        // Breakdown assistance number with unexpected formatting.
        $this->VALID_CASES[] = new NumberTest("1800-1-0-10 22", RegionCode::AU);
        $this->VALID_CASES[] = new NumberTest("030-3-2 23 12 34", RegionCode::DE);
        $this->VALID_CASES[] = new NumberTest("03 0 -3 2 23 12 34", RegionCode::DE);
        $this->VALID_CASES[] = new NumberTest("(0)3 0 -3 2 23 12 34", RegionCode::DE);
        $this->VALID_CASES[] = new NumberTest("0 3 0 -3 2 23 12 34", RegionCode::DE);

        /* STRICT_GROUPING_CASES */
        $this->STRICT_GROUPING_CASES[] = new NumberTest("(415) 6667777", RegionCode::US);
        $this->STRICT_GROUPING_CASES[] = new NumberTest("415-6667777", RegionCode::US);
        // Should be found by strict grouping but not exact grouping, as the last two groups are
        // formatted together as a block.
        $this->STRICT_GROUPING_CASES[] = new NumberTest("0800-2491234", RegionCode::DE);
        // Doesn't match any formatting in the test file, but almost matches an alternate format (the
        // last two groups have been squashed together here).
        $this->STRICT_GROUPING_CASES[] = new NumberTest("0900-1 123123", RegionCode::DE);
        $this->STRICT_GROUPING_CASES[] = new NumberTest("(0)900-1 123123", RegionCode::DE);
        $this->STRICT_GROUPING_CASES[] = new NumberTest("0 900-1 123123", RegionCode::DE);
        // NDC also found as part of the country calling code; this shouldn't ruin the grouping
        // expectations.
        $this->STRICT_GROUPING_CASES[] = new NumberTest("+33 3 34 2312", RegionCode::FR);

        /* EXACT_GROUPING_CASES */
        $this->EXACT_GROUPING_CASES[] = new NumberTest("\u{FF14}\u{FF11}\u{FF15}\u{FF16}\u{FF16}\u{FF16}\u{FF17}\u{FF17}\u{FF17}\u{FF17}",
            RegionCode::US);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("\u{FF14}\u{FF11}\u{FF15}-\u{FF16}\u{FF16}\u{FF16}-\u{FF17}\u{FF17}\u{FF17}\u{FF17}",
            RegionCode::US);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("4156667777", RegionCode::US);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("4156667777 x 123", RegionCode::US);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("415-666-7777", RegionCode::US);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("415/666-7777", RegionCode::US);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("415-666-7777 ext. 503", RegionCode::US);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("1 415 666 7777 x 123", RegionCode::US);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("+1 415-666-7777", RegionCode::US);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("+494949 49", RegionCode::DE);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("+49-49-34", RegionCode::DE);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("+49-4931-49", RegionCode::DE);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("04931-49", RegionCode::DE); // With National Prefix
        $this->EXACT_GROUPING_CASES[] = new NumberTest("+49-494949", RegionCode::DE); // One group with country code
        $this->EXACT_GROUPING_CASES[] = new NumberTest("+49-494949 ext. 49", RegionCode::DE);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("+49494949 ext. 49", RegionCode::DE);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("0494949", RegionCode::DE);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("0494949 ext. 49", RegionCode::DE);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("01 (33) 3461 2234", RegionCode::MX); // Optional NP present
        $this->EXACT_GROUPING_CASES[] = new NumberTest("(33) 3461 2234", RegionCode::MX); // Optional NP omitted
        $this->EXACT_GROUPING_CASES[] = new NumberTest("1800-10-10 22", RegionCode::AU); // Breakdown assistance number.
        // Doesn't match any formatting in the test file, but matches an alternate format exactly.
        $this->EXACT_GROUPING_CASES[] = new NumberTest("0900-1 123 123", RegionCode::DE);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("(0)900-1 123 123", RegionCode::DE);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("0 900-1 123 123", RegionCode::DE);
        $this->EXACT_GROUPING_CASES[] = new NumberTest("+33 3 34 23 12", RegionCode::FR);
    }

    public function testContainsMoreThanOneSlashInNationalNumber()
    {
        // A date should return true.
        $number = new PhoneNumber();
        $number->setCountryCode(1);
        $number->setCountryCodeSource(CountryCodeSource::FROM_DEFAULT_COUNTRY);
        $candidate = "1/05/2013";
        $this->assertTrue(PhoneNumberMatcher::containsMoreThanOneSlashInNationalNumber($number, $candidate));

        // Here, the country code source thinks it started with a country calling code, but this is not
        // the same as the part before the slash, so it's still true.
        $number = new PhoneNumber();
        $number->setCountryCode(274);
        $number->setCountryCodeSource(CountryCodeSource::FROM_NUMBER_WITHOUT_PLUS_SIGN);
        $candidate = "27/4/2013";
        $this->assertTrue(PhoneNumberMatcher::containsMoreThanOneSlashInNationalNumber($number, $candidate));

        // Now it should be false, because the first slash is after the country calling code.
        $number = new PhoneNumber();
        $number->setCountryCode(49);
        $number->setCountryCodeSource(CountryCodeSource::FROM_NUMBER_WITH_PLUS_SIGN);
        $candidate = "49/69/2013";
        $this->assertFalse(PhoneNumberMatcher::containsMoreThanOneSlashInNationalNumber($number, $candidate));

        $number = new PhoneNumber();
        $number->setCountryCode(49);
        $number->setCountryCodeSource(CountryCodeSource::FROM_NUMBER_WITHOUT_PLUS_SIGN);
        $candidate = "+49/69/2013";
        $this->assertFalse(PhoneNumberMatcher::containsMoreThanOneSlashInNationalNumber($number, $candidate));

        $candidate = "+ 49/69/2013";
        $this->assertFalse(PhoneNumberMatcher::containsMoreThanOneSlashInNationalNumber($number, $candidate));

        $candidate = "+49/69/20/13";
        $this->assertTrue(PhoneNumberMatcher::containsMoreThanOneSlashInNationalNumber($number, $candidate));

        // Here, the first group is not assumed to be the country calling code, even though it is the
        // same as it, so this should return true.
        $number = new PhoneNumber();
        $number->setCountryCode(49);
        $number->setCountryCodeSource(CountryCodeSource::FROM_DEFAULT_COUNTRY);
        $candidate = "49/69/2013";
        $this->assertTrue(PhoneNumberMatcher::containsMoreThanOneSlashInNationalNumber($number, $candidate));
    }

    /** See {@link PhoneNumberUtilTest#testParseNationalNumber()}. */
    public function testFindNationalNumber()
    {
        // same cases as in testParseNationalNumber
        $this->doTestFindInContext("033316005", RegionCode::NZ);
        // ("33316005", RegionCode.NZ) is omitted since the national prefix is obligatory for these
        // types of numbers in New Zealand.
        // National prefix attached and some formatting present.
        $this->doTestFindInContext("03-331 6005", RegionCode::NZ);
        $this->doTestFindInContext("03 331 6005", RegionCode::NZ);
        // Testing international prefixes.
        // Should strip country code.
        $this->doTestFindInContext("0064 3 331 6005", RegionCode::NZ);
        // Try again, but this time we have an international number with Region Code US. It should
        // recognize the country code and parse accordingly.
        $this->doTestFindInContext("01164 3 331 6005", RegionCode::US);
        $this->doTestFindInContext("+64 3 331 6005", RegionCode::US);

        $this->doTestFindInContext("64(0)64123456", RegionCode::NZ);
        // Check that using a "/" is fine in a phone number.
        // Note that real Polish numbers do *not* start with a 0.
        $this->doTestFindInContext("0123/456789", RegionCode::PL);

        $this->doTestFindInContext("123-456-7890", RegionCode::US);
    }

    /**
     * Tests numbers found by {@link PhoneNumberUtil#findNumbers(CharSequence, String)} in various
     * textual contexts.
     *
     * @param number the number to test and the corresponding region code to use
     */
    private function doTestFindInContext($number, $defaultCountry)
    {
        $this->findPossibleInContext($number, $defaultCountry);


        $parsed = $this->phoneUtil->parse($number, $defaultCountry);
        if ($this->phoneUtil->isValidNumber($parsed)) {
            $this->findValidInContext($number, $defaultCountry);
        }
    }


    /**
     * Tests valid numbers in contexts that should pass for {@link Leniency#POSSIBLE}.
     */
    private function findPossibleInContext($number, $defaultCountry)
    {
        $contextPairs = [];
        $contextPairs[] = new NumberContext("", "");  // no context
        $contextPairs[] = new NumberContext("   ", "\t");  // whitespace only
        $contextPairs[] = new NumberContext("Hello ", "");  // no context at end
        $contextPairs[] = new NumberContext("", " to call me!");  // no context at start
        $contextPairs[] = new NumberContext("Hi there, call ", " to reach me!");  // no context at start
        $contextPairs[] = new NumberContext("Hi there, call ", ", or don't");  // with commas
        // Three examples without whitespace around the number.
        $contextPairs[] = new NumberContext("Hi call", "");
        $contextPairs[] = new NumberContext("", "forme");
        $contextPairs[] = new NumberContext("Hi call", "forme");
        // With other small numbers.
        $contextPairs[] = new NumberContext("It's cheap! Call ", " before 6:30");
        // With a second number later.
        $contextPairs[] = new NumberContext("Call ", " or +1800-123-4567!");
        $contextPairs[] = new NumberContext("Call me on June 2 at", "");  // with a Month-Day date
        // With publication pages.
        $contextPairs[] = new NumberContext(
            "As quoted by Alfonso 12-15 (2009), you may call me at ", "");
        $contextPairs[] = new NumberContext(
            "As quoted by Alfonso 12-15 et al. (2009), you may call me at ", "");
        // With dates, written in the American style.
        $contextPairs[] = new NumberContext(
            "As I said on 03/10/2011, you may call me at ", "");
        // With trailing numbers after a comma. The 45 should not be considered an extension.
        $contextPairs[] = new NumberContext("", ", 45 days a year");
        // With a postfix stripped off as it looks like the start of another number.
        $contextPairs[] = new NumberContext("Call ", "/x12 more");

        $this->doTestInContext($number, $defaultCountry, $contextPairs, new Leniency(Leniency::POSSIBLE));
    }

    private function doTestInContext($number, $defaultCountry, array $contextPairs, Leniency $leniency)
    {
        foreach ($contextPairs as $context) {
            /** @var NumberContext $context */
            $prefix = $context->leadingText;
            $text = $prefix.$number.$context->trailingText;

            $start = mb_strlen($prefix, 'UTF-8');
            $end = $start + mb_strlen($number, 'UTF-8');
            /** @var PhoneNumberMatcher $iterator */

            $iterator = $this->phoneUtil->findNumbers($text, $defaultCountry, $leniency, 100000);

            /** @var PhoneNumberMatch $match */
            $match = $iterator->hasNext() ? $iterator->next() : null;
            $this->assertNotNull($match, "Did not find a number in '".$text."'; expected '".$number."'");

            $extracted = mb_substr($text, $match->start(), $match->end() - $match->start(), 'UTF-8');

            $this->assertTrue($start == $match->start() && $end == $match->end(),
                "Unexpected phone region in '".$text."'; extracted '".$extracted."'");
            $this->assertTrue($number == $extracted);
            $this->assertTrue($match->rawString() == $extracted);
        }
    }

    /**
     * Tests valid numbers in contexts that fail for {@link Leniency#POSSIBLE} but are valid for
     * {@link Leniency#VALID}.
     */
    private function findValidInContext($number, $defaultCountry)
    {
        $contextPairs = [];
        // With other small numbers.
        $contextPairs[] = new NumberContext("It's only 9.99! Call ", " to buy");
        // With a number Day.Month.Year date.
        $contextPairs[] = new NumberContext("Call me on 21.6.1984 at ", "");
        // With a number Month/Day date.
        $contextPairs[] = new NumberContext("Call me on 06/21 at ", "");
        // With a number Day.Month date.
        $contextPairs[] = new NumberContext("Call me on 21.6. at ", "");
        // With a number Month/Day/Year date.
        $contextPairs[] = new NumberContext("Call me on 06/21/84 at ", "");

        $this->doTestInContext($number, $defaultCountry, $contextPairs, new Leniency(Leniency::VALID));
    }

    /** See {@link PhoneNumberUtilTest#testParseWithInternationalPrefixes()}. */
    public function testFindWithInternationalPrefixes()
    {
        $this->doTestFindInContext("+1 (650) 333-6000", RegionCode::NZ);
        $this->doTestFindInContext("1-650-333-6000", RegionCode::US);
        // Calling the US number from Singapore by using different service providers
        // 1st test: calling using SingTel IDD service (IDD is 001)
        $this->doTestFindInContext("0011-650-333-6000", RegionCode::SG);
        // 2nd test: calling using StarHub IDD service (IDD is 008)
        $this->doTestFindInContext("0081-650-333-6000", RegionCode::SG);
        // 3rd test: calling using SingTel V019 service (IDD is 019)
        $this->doTestFindInContext("0191-650-333-6000", RegionCode::SG);
        // Calling the US number from Poland
        $this->doTestFindInContext("0~01-650-333-6000", RegionCode::PL);
        // Using "++" at the start.
        $this->doTestFindInContext('++1 (650) 333-6000', RegionCode::PL);
        // Using a full-width plus sign.

        $this->doTestFindInContext("\u{FF0B} (650) 333-6000", RegionCode::SG);
        // The whole number, including punctuation, is here represented in full-width form.
        $this->doTestFindInContext("\u{FF0B}\u{FF11}\u{3000}\u{FF08}\u{FF16}\u{FF15}\u{FF10}\u{FF09}".
            "\u{3000}\u{FF13}\u{FF13}\u{FF13}\u{FF0D}\u{FF16}\u{FF10}\u{FF10}\u{FF10}",
            RegionCode::SG);
    }

    /** See {@link PhoneNumberUtilTest#testParseWithLeadingZero()}. */
    public function testFindWithLeadingZero()
    {
        $this->doTestFindInContext("+39 02-36618 300", RegionCode::NZ);
        $this->doTestFindInContext("02-36618 300", RegionCode::IT);
        $this->doTestFindInContext("312 345 678", RegionCode::IT);
    }

    /** See {@link PhoneNumberUtilTest#testParseNationalNumberArgentina()}. */
    public function testFindNationalNumberArgentina()
    {
        // Test parsing mobile numbers of Argentina.
        $this->doTestFindInContext("+54 9 343 555 1212", RegionCode::AR);
        $this->doTestFindInContext("0343 15 555 1212", RegionCode::AR);

        $this->doTestFindInContext("+54 9 3715 65 4320", RegionCode::AR);
        $this->doTestFindInContext("03715 15 65 4320", RegionCode::AR);

        // Test parsing fixed-line numbers of Argentina.
        $this->doTestFindInContext("+54 11 3797 0000", RegionCode::AR);
        $this->doTestFindInContext("011 3797 0000", RegionCode::AR);

        $this->doTestFindInContext("+54 3715 65 4321", RegionCode::AR);
        $this->doTestFindInContext("03715 65 4321", RegionCode::AR);

        $this->doTestFindInContext("+54 23 1234 0000", RegionCode::AR);
        $this->doTestFindInContext("023 1234 0000", RegionCode::AR);
    }

    /** See {@link PhoneNumberUtilTest#testParseWithXInNumber()}. */
    public function testFindWithXInNumber()
    {
        $this->doTestFindInContext("(0xx) 123456789", RegionCode::AR);
        // A case where x denotes both carrier codes and extension symbol.
        $this->doTestFindInContext("(0xx) 123456789 x 1234", RegionCode::AR);

        // This test is intentionally constructed such that the number of digit after xx is larger than
        // 7, so that the number won't be mistakenly treated as an extension, as we allow extensions up
        // to 7 digits. This assumption is okay for now as all the countries where a carrier selection
        // code is written in the form of xx have a national significant number of length larger than 7.
        $this->doTestFindInContext("011xx5481429712", RegionCode::US);
    }


    /** See {@link PhoneNumberUtilTest#testParseNumbersMexico()}. */
    public function testFindNumbersMexico()
    {
        // Test parsing fixed-line numbers of Mexico.
        $this->doTestFindInContext("+52 (449)978-0001", RegionCode::MX);
        $this->doTestFindInContext("01 (449)978-0001", RegionCode::MX);
        $this->doTestFindInContext("(449)978-0001", RegionCode::MX);

        // Test parsing mobile numbers of Mexico.
        $this->doTestFindInContext("+52 1 33 1234-5678", RegionCode::MX);
        $this->doTestFindInContext("044 (33) 1234-5678", RegionCode::MX);
        $this->doTestFindInContext("045 33 1234-5678", RegionCode::MX);
    }


    /** See {@link PhoneNumberUtilTest#testParseNumbersWithPlusWithNoRegion()}. */
    public function testFindNumbersWithPlusWithNoRegion()
    {
        // RegionCode.ZZ is allowed only if the number starts with a '+' - then the country code can be
        // calculated.
        $this->doTestFindInContext("+64 3 331 6005", RegionCode::ZZ);
        // Null is also allowed for the region code in these cases.
        $this->doTestFindInContext("+64 3 331 6005", null);
    }

    /** See {@link PhoneNumberUtilTest#testParseExtensions()}. */
    public function testFindExtensions()
    {
        $this->doTestFindInContext("03 331 6005 ext 3456", RegionCode::NZ);
        $this->doTestFindInContext("03-3316005x3456", RegionCode::NZ);
        $this->doTestFindInContext("03-3316005 int.3456", RegionCode::NZ);
        $this->doTestFindInContext("03 3316005 #3456", RegionCode::NZ);
        $this->doTestFindInContext("0~0 1800 7493 524", RegionCode::PL);
        $this->doTestFindInContext("(1800) 7493.524", RegionCode::US);
        // Check that the last instance of an extension token is matched.
        $this->doTestFindInContext("0~0 1800 7493 524 ~1234", RegionCode::PL);
        // Verifying bug-fix where the last digit of a number was previously omitted if it was a 0 when
        // extracting the extension. Also verifying a few different cases of extensions.
        $this->doTestFindInContext("+44 2034567890x456", RegionCode::NZ);
        $this->doTestFindInContext("+44 2034567890x456", RegionCode::GB);

        $this->doTestFindInContext("+44 2034567890 x456", RegionCode::GB);
        $this->doTestFindInContext("+44 2034567890 X456", RegionCode::GB);
        $this->doTestFindInContext("+44 2034567890 X 456", RegionCode::GB);
        $this->doTestFindInContext("+44 2034567890 X  456", RegionCode::GB);
        $this->doTestFindInContext("+44 2034567890  X 456", RegionCode::GB);

        $this->doTestFindInContext("(800) 901-3355 x 7246433", RegionCode::US);
        $this->doTestFindInContext("(800) 901-3355 , ext 7246433", RegionCode::US);
        $this->doTestFindInContext("(800) 901-3355 ,extension 7246433", RegionCode::US);
        // The next test differs from PhoneNumberUtil -> when matching we don't consider a lone comma to
        // indicate an extension, although we accept it when parsing.
        $this->doTestFindInContext("(800) 901-3355 ,x 7246433", RegionCode::US);
        $this->doTestFindInContext("(800) 901-3355 ext: 7246433", RegionCode::US);
    }

    public function testFindInterspersedWithSpace()
    {
        $this->doTestFindInContext("0 3   3 3 1   6 0 0 5", RegionCode::NZ);
    }

    /**
     * Test matching behavior when starting in the middle of a phone number.
     */
    public function testIntermediateParsePositions()
    {
        $text = "Call 033316005  or 032316005!";
        //             |    |    |    |    |    |
        //             0    5   10   15   20   25

        // Iterate over all possible indices.
        for ($i = 0; $i <= 5; $i++) {
            $this->assertEqualRange($text, $i, 5, 14);
        }
// 7 and 8 digits in a row are still parsed as number.
        $this->assertEqualRange($text, 6, 6, 14);
        $this->assertEqualRange($text, 7, 7, 14);
// Anything smaller is skipped to the second instance.
        for ($i = 8; $i <= 19; $i++) {
            $this->assertEqualRange($text, $i, 19, 28);
        }
    }

    /**
     * Asserts that another number can be found in {@code text} starting at {@code index}, and that
     * its corresponding range is {@code [start, end)}.
     */
    private function assertEqualRange($text, $index, $start, $end)
    {
        $sub = substr($text, $index, strlen($text) - $index);

        /** @var PhoneNumberMatcher $iterator */
        $matches = $this->phoneUtil->findNumbers($sub, RegionCode::NZ, new Leniency(Leniency::POSSIBLE), 100000);
        $this->assertTrue($matches->hasNext());
        /** @var PhoneNumberMatch $match */
        $match = $matches->next();
        $this->assertEquals($start - $index, $match->start());
        $this->assertEquals($end - $index, $match->end());
        $this->assertEquals(substr($sub, $match->start(), $match->end() - $match->start()), $match->rawString());
    }

    public function testFourMatchesInARow()
    {

        $number1 = "415-666-7777";
        $number2 = "800-443-1223";
        $number3 = "212-443-1223";
        $number4 = "650-443-1223";
        $text = $number1." - ".$number2." - ".$number3." - ".$number4;

        /** @var PhoneNumberMatcher $iterator */
        $iterator = $this->phoneUtil->findNumbers($text, RegionCode::US);
        $match = $iterator->hasNext() ? $iterator->next() : null;
        $this->assertMatchProperties($match, $text, $number1, RegionCode::US);

        $match = $iterator->hasNext() ? $iterator->next() : null;
        $this->assertMatchProperties($match, $text, $number2, RegionCode::US);

        $match = $iterator->hasNext() ? $iterator->next() : null;
        $this->assertMatchProperties($match, $text, $number3, RegionCode::US);

        $match = $iterator->hasNext() ? $iterator->next() : null;
        $this->assertMatchProperties($match, $text, $number4, RegionCode::US);
    }

    /**
     * Asserts that the expected match is non-null, and that the raw string and expected
     * proto buffer are set appropriately.
     */
    private function assertMatchProperties($match, $text, $number, $region)
    {
        $expectedResult = $this->phoneUtil->parse($number, $region);
        $this->assertNotNull($match, "Did not find a number in '".$text."'; expected ".$number);

        $this->assertEquals($expectedResult, $match->number());
        $this->assertEquals($number, $match->rawString());
    }

    public function testMatchesFoundWithMultipleSpaces()
    {
        $number1 = "(415) 666-7777";
        $number2 = "(800) 443-1223";
        $text = $number1." ".$number2;

        $iterator = $this->phoneUtil->findNumbers($text, RegionCode::US);
        $match = $iterator->hasNext() ? $iterator->next() : null;
        $this->assertMatchProperties($match, $text, $number1, RegionCode::US);

        $match = $iterator->hasNext() ? $iterator->next() : null;
        $this->assertMatchProperties($match, $text, $number2, RegionCode::US);
    }

    public function testMatchWithSurroundingZipcodes()
    {
        $number = "415-666-7777";
        $zipPreceding = "My address is CA 34215 - ".$number." is my number.";

        $iterator =
            $this->phoneUtil->findNumbers($zipPreceding, RegionCode::US);
        $match = $iterator->hasNext() ? $iterator->next() : null;
        $this->assertMatchProperties($match, $zipPreceding, $number, RegionCode::US);

        // Now repeat, but this time the phone number has spaces in it. It should still be found.
        $number = "(415) 666 7777";

        $zipFollowing = "My number is ".$number.". 34215 is my zip-code.";
        $iterator = $this->phoneUtil->findNumbers($zipFollowing, RegionCode::US);
        $matchWithSpaces = $iterator->hasNext() ? $iterator->next() : null;
        $this->assertMatchProperties($matchWithSpaces, $zipFollowing, $number, RegionCode::US);
    }

    public function testIsLatinLetter()
    {
        $this->assertTrue(PhoneNumberMatcher::isLatinLetter('c'));
        $this->assertTrue(PhoneNumberMatcher::isLatinLetter('C'));
        $this->assertTrue(PhoneNumberMatcher::isLatinLetter("\u{00C9}"));
        $this->assertTrue(PhoneNumberMatcher::isLatinLetter("\u{0301}"));  // Combining acute accent
        // Punctuation, digits and white-space are not considered "latin letters".
        $this->assertFalse(PhoneNumberMatcher::isLatinLetter(':'));
        $this->assertFalse(PhoneNumberMatcher::isLatinLetter('5'));
        $this->assertFalse(PhoneNumberMatcher::isLatinLetter('-'));
        $this->assertFalse(PhoneNumberMatcher::isLatinLetter('.'));
        $this->assertFalse(PhoneNumberMatcher::isLatinLetter(' '));
        $this->assertFalse(PhoneNumberMatcher::isLatinLetter("\u{6211}"));  // Chinese character
        $this->assertFalse(PhoneNumberMatcher::isLatinLetter("\u{306E}"));  // Hiragana letter no
    }

    public function testMatchesWithSurroundingLatinChars()
    {
        $possibleOnlyContexts = [];
        $possibleOnlyContexts[] = new NumberContext("abc", "def");
        $possibleOnlyContexts[] = new NumberContext("abc", "");
        $possibleOnlyContexts[] = new NumberContext("", "def");
        // Latin capital letter e with an acute accent.
        $possibleOnlyContexts[] = new NumberContext("\u{00C9}", "");
        // e with an acute accent decomposed (with combining mark).
        $possibleOnlyContexts[] = new NumberContext("e\u{0301}", "");

        // Numbers should not be considered valid, if they are surrounded by Latin characters, but
        // should be considered possible.
        $this->findMatchesInContexts($possibleOnlyContexts, false, true);
    }

    /**
     * Helper method which tests the contexts provided and ensures that:
     * -- if isValid is true, they all find a test number inserted in the middle when leniency of
     *  matching is set to VALID; else no test number should be extracted at that leniency level
     * -- if isPossible is true, they all find a test number inserted in the middle when leniency of
     *  matching is set to POSSIBLE; else no test number should be extracted at that leniency level
     */
    private function findMatchesInContexts(
        array $contexts,
        $isValid,
        $isPossible,
        $region = RegionCode::US,
        $number = "415-666-7777"
    ) {
        if ($isValid) {
            $this->doTestInContext($number, $region, $contexts, new Leniency(Leniency::VALID));
        } else {
            foreach ($contexts as $context) {
                $text = $context->leadingText.$number.$context->trailingText;
                $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers($text, $region)),
                    "Should not have found a number in ".$text);
            }
        }
        if ($isPossible) {
            $this->doTestInContext($number, $region, $contexts, new Leniency(Leniency::POSSIBLE));
        } else {
            foreach ($contexts as $context) {
                $text = $context->leadingText.$number.$context->trailingText;
                $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers($text, $region,
                    new Leniency(Leniency::POSSIBLE))), "Should not have found a number in ".$text);
            }
        }
    }

    private function hasNoMatches(PhoneNumberMatcher $iterable)
    {
        return !$iterable->hasNext();
    }

    public function testMoneyNotSeenAsPhoneNumber()
    {
        $possibleOnlyContexts = [];
        $possibleOnlyContexts[] = new NumberContext("$", "");
        $possibleOnlyContexts[] = new NumberContext("", "$");
        $possibleOnlyContexts[] = new NumberContext("\u{00A3}", "");  // Pound sign
        $possibleOnlyContexts[] = new NumberContext("\u{00A5}", "");  // Yen sign
        $this->findMatchesInContexts($possibleOnlyContexts, false, true);
    }


    public function testPercentageNotSeenAsPhoneNumber()
    {
        $possibleOnlyContexts = [];
        $possibleOnlyContexts[] = new NumberContext("", "%");
        // Numbers followed by % should be dropped.
        $this->findMatchesInContexts($possibleOnlyContexts, false, true);
    }


    public function testPhoneNumberWithLeadingOrTrailingMoneyMatches()
    {
        // Because of the space after the 20 (or before the 100) these dollar amounts should not stop
        // the actual number from being found.
        $contexts = [];
        $contexts[] = new NumberContext("$20 ", "");
        $contexts[] = new NumberContext("", " 100$");
        $this->findMatchesInContexts($contexts, true, true);
    }


    public function testMatchesWithSurroundingLatinCharsAndLeadingPunctuation()
    {
        // Contexts with trailing characters. Leading characters are okay here since the numbers we will
        // insert start with punctuation, but trailing characters are still not allowed.
        $possibleOnlyContexts = [];
        $possibleOnlyContexts[] = new NumberContext("abc", "def");
        $possibleOnlyContexts[] = new NumberContext("", "def");
        $possibleOnlyContexts[] = new NumberContext("", "\u{00C9}");
        // Numbers should not be considered valid, if they have trailing Latin characters, but should be
        // considered possible.
        $numberWithPlus = "+14156667777";
        $numberWithBrackets = "(415)6667777";
        $this->findMatchesInContexts($possibleOnlyContexts, false, true, RegionCode::US, $numberWithPlus);
        $this->findMatchesInContexts($possibleOnlyContexts, false, true, RegionCode::US, $numberWithBrackets);

        $validContexts = [];
        $validContexts[] = new NumberContext("abc", "");
        $validContexts[] = new NumberContext("\u{00C9}", "");
        $validContexts[] = new NumberContext("\u{00C9}", ".");  // Trailing punctuation.
        $validContexts[] = new NumberContext("\u{00C9}", " def");  // Trailing white-space.

        // Numbers should be considered valid, since they start with punctuation.
        $this->findMatchesInContexts($validContexts, true, true, RegionCode::US, $numberWithPlus);
        $this->findMatchesInContexts($validContexts, true, true, RegionCode::US, $numberWithBrackets);
    }


    public function testMatchesWithSurroundingChineseChars()
    {
        $validContexts = [];
        $validContexts[] = new NumberContext("\u{6211}\u{7684}\u{7535}\u{8BDD}\u{53F7}\u{7801}\u{662F}", "");
        $validContexts[] = new NumberContext("", "\u{662F}\u{6211}\u{7684}\u{7535}\u{8BDD}\u{53F7}\u{7801}");
        $validContexts[] = new NumberContext("\u{8BF7}\u{62E8}\u{6253}", "\u{6211}\u{5728}\u{660E}\u{5929}");

        // Numbers should be considered valid, since they are surrounded by Chinese.
        $this->findMatchesInContexts($validContexts, true, true);
    }


    public function testMatchesWithSurroundingPunctuation()
    {
        $validContexts = [];
        $validContexts[] = new NumberContext("My number-", "");  // At end of text.
        $validContexts[] = new NumberContext("", ".Nice day.");  // At start of text.
        $validContexts[] = new NumberContext("Tel:", ".");  // Punctuation surrounds number.
        $validContexts[] = new NumberContext("Tel: ", " on Saturdays.");  // White-space is also fine.

        // Numbers should be considered valid, since they are surrounded by punctuation.
        $this->findMatchesInContexts($validContexts, true, true);
    }


    public function testMatchesMultiplePhoneNumbersSeparatedByPhoneNumberPunctuation()
    {
        $text = "Call 650-253-4561 -- 455-234-3451";
        $region = RegionCode::US;

        $number1 = new PhoneNumber();
        $number1->setCountryCode($this->phoneUtil->getCountryCodeForRegion($region));
        $number1->setNationalNumber(6502534561);
        $match1 = new PhoneNumberMatch(5, "650-253-4561", $number1);

        $number2 = new PhoneNumber();
        $number2->setCountryCode($this->phoneUtil->getCountryCodeForRegion($region));
        $number2->setNationalNumber(4552343451);
        $match2 = new PhoneNumberMatch(21, "455-234-3451", $number2);

        $matches = $this->phoneUtil->findNumbers($text, $region);
        $this->assertEquals($match1, $matches->next());
        $this->assertEquals($match2, $matches->next());
    }

    public function testDoesNotMatchMultiplePhoneNumbersSeparatedWithNoWhiteSpace()
    {
        // No white-space found between numbers - neither is found.
        $text = "Call 650-253-4561--455-234-3451";
        $region = RegionCode::US;

        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers($text, $region)));
    }

    public function testMatchesWithPossibleLeniency()
    {
        $testCases = array_merge(
            $this->STRICT_GROUPING_CASES,
            $this->EXACT_GROUPING_CASES,
            $this->VALID_CASES,
            $this->POSSIBLE_ONLY_CASES
        );
        $this->doTestNumberMatchesForLeniency($testCases, new Leniency(Leniency::POSSIBLE));
    }

    private function doTestNumberMatchesForLeniency($testCases, Leniency $leniency)
    {
        $noMatchFoundCount = 0;
        $wrongMatchFoundCount = 0;
        foreach ($testCases as $test) {
            $iterator = $this->findNumbersForLeniency($test->rawString, $test->region, $leniency);
            /** @var PhoneNumberMatch $match */
            $match = $iterator->hasNext() ? $iterator->next() : null;
            if ($match == null) {
                $noMatchFoundCount++;
                error_log("No match found in ".$test." for leniency: ".$leniency->level());
            } else {
                if (!$test->rawString == $match->rawString()) {
                    $wrongMatchFoundCount++;
                    error_log("Found wrong match in test ".$test.". Found ".$match->rawString());
                }
            }
        }
        $this->assertEquals(0, $noMatchFoundCount);
        $this->assertEquals(0, $wrongMatchFoundCount);
    }

    /**
     * @param $text
     * @param $defaultCountry
     * @param Leniency $leniency
     * @return PhoneNumberMatcher
     */
    private function findNumbersForLeniency($text, $defaultCountry, Leniency $leniency)
    {
        return $this->phoneUtil->findNumbers($text, $defaultCountry, $leniency);
    }

    public function testNonMatchesWithPossibleLeniency()
    {
        $testCases = $this->IMPOSSIBLE_CASES;
        $this->doTestNumberNonMatchesForLeniency($testCases, new Leniency(Leniency::POSSIBLE));
    }

    private function doTestNumberNonMatchesForLeniency($testCases, Leniency $leniency)
    {
        $matchFoundCount = 0;
        foreach ($testCases as $test) {
            $iterator = $this->findNumbersForLeniency($test->rawString, $test->region, $leniency);
            $match = $iterator->hasNext() ? $iterator->next() : null;

            if ($match != null) {
                $matchFoundCount++;
                echo "Match found in ".$test." for leniency: ".$leniency->level();
            }
        }
        $this->assertEquals(0, $matchFoundCount);
    }

    public function testMatchesWithValidLeniency()
    {
        $testCases = array_merge($this->STRICT_GROUPING_CASES, $this->EXACT_GROUPING_CASES, $this->VALID_CASES);
        $this->doTestNumberMatchesForLeniency($testCases, new Leniency(Leniency::VALID));
    }

    /*
        public function testMatchesWithStrictGroupingLeniency() {
            $testCases = array_merge($this->STRICT_GROUPING_CASES, $this->EXACT_GROUPING_CASES);
            $this->doTestNumberMatchesForLeniency($testCases, Leniency.STRICT_GROUPING);
        }

        public function testNonMatchesWithStrictGroupLeniency() {
            $testCases = [];
            testCases.addAll(Arrays.asList(IMPOSSIBLE_CASES));
            testCases.addAll(Arrays.asList(POSSIBLE_ONLY_CASES));
            testCases.addAll(Arrays.asList(VALID_CASES));
            $this->doTestNumberNonMatchesForLeniency(testCases, Leniency.STRICT_GROUPING);
        }

        public function testMatchesWithExactGroupingLeniency() {
            $testCases = [];
            testCases.addAll(Arrays.asList(EXACT_GROUPING_CASES));
            doTestNumberMatchesForLeniency(testCases, Leniency.EXACT_GROUPING);
        }

        public function testNonMatchesExactGroupLeniency() {
            $testCases = [];
            testCases.addAll(Arrays.asList(IMPOSSIBLE_CASES));
            testCases.addAll(Arrays.asList(POSSIBLE_ONLY_CASES));
            testCases.addAll(Arrays.asList(VALID_CASES));
            testCases.addAll(Arrays.asList(STRICT_GROUPING_CASES));
            $this->doTestNumberNonMatchesForLeniency(testCases, Leniency.EXACT_GROUPING);
        }
    */

        public function testNonMatchesWithValidLeniency()
    {
        $testCases = array_merge($this->IMPOSSIBLE_CASES, $this->POSSIBLE_ONLY_CASES);
        $this->doTestNumberNonMatchesForLeniency($testCases, new Leniency(Leniency::VALID));
    }

    public function testNonMatchingBracketsAreInvalid()
    {
        // The digits up to the ", " form a valid US number, but it shouldn't be matched as one since
        // there was a non-matching bracket present.
        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers("80.585 [79.964, 81.191]",
            RegionCode::US)));

        // The trailing "]" is thrown away before parsing, so the resultant number, while a valid US
        // number, does not have matching brackets.
        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers("80.585 [79.964]", RegionCode::US)));

        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers("80.585 ((79.964)", RegionCode::US)));

        // This case has too many sets of brackets to be valid.
        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers("(80).(585) (79).(9)64", RegionCode::US)));
    }


    public function testNoMatchIfRegionIsNull()
    {
        // Fail on non-international prefix if region code is null.
        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers("Random text body - number is 0331 6005, see you there",
            null)));
    }


    public function testNoMatchInEmptyString()
    {
        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers("", RegionCode::US)));
        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers("  ", RegionCode::US)));
    }


    public function testNoMatchIfNoNumber()
    {
        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers("Random text body - number is foobar, see you there",
            RegionCode::US)));
    }

    public function testSequences()
    {
        // Test multiple occurrences.
        $text = "Call 033316005  or 032316005!";
        $region = RegionCode::NZ;

        $number1 = new PhoneNumber();
        $number1->setCountryCode($this->phoneUtil->getCountryCodeForRegion($region));
        $number1->setNationalNumber(33316005);
        $match1 = new PhoneNumberMatch(5, "033316005", $number1);

        $number2 = new PhoneNumber();
        $number2->setCountryCode($this->phoneUtil->getCountryCodeForRegion($region));
        $number2->setNationalNumber(32316005);
        $match2 = new PhoneNumberMatch(19, "032316005", $number2);

        $matches = $this->phoneUtil->findNumbers($text, $region, new Leniency(Leniency::POSSIBLE));

        $this->assertEquals($match1, $matches->next());
        $this->assertEquals($match2, $matches->next());
    }

    public function testNullInput()
    {
        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers(null, RegionCode::US)));
        $this->assertTrue($this->hasNoMatches($this->phoneUtil->findNumbers(null, null)));
    }


    public function testMaxMatches()
    {
        // Set up text with 100 valid phone numbers.
        $numbers = '';
        for ($i = 0; $i < 100; $i++) {
            $numbers .= "My info: 415-666-7777,";
        }

// Matches all 100. Max only applies to failed cases.
        $expected = [];
        $number = $this->phoneUtil->parse("+14156667777", null);
        for ($i = 0; $i < 100; $i++) {
            $expected[] = $number;
        }

        $iterable = $this->phoneUtil->findNumbers($numbers, RegionCode::US, new Leniency(Leniency::VALID), 10);
        $actual = [];
        foreach ($iterable as $match) {
            $actual[] = $match->number();
        }

        $this->assertEquals($expected, $actual);
    }

}

class NumberContext
{
    public $leadingText;
    public $trailingText;

    public function __construct($leadingText, $trailingText)
    {
        $this->leadingText = $leadingText;
        $this->trailingText = $trailingText;
    }
}


/**
 * Small class that holds the number we want to test and the region for which it should be valid.
 */
class NumberTest
{
    public $rawString;
    public $region;

    public function __construct($rawString, $regionCode)
    {
        $this->rawString = $rawString;
        $this->region = $regionCode;
    }

    public function __toString()
    {
        return $this->rawString." (".$this->region.")";
    }
}