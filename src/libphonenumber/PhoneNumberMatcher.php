<?php
/**
 * Created by PhpStorm.
 * User: SaphirAngel
 * Date: 25/05/2016
 * Time: 15:09
 */

namespace libphonenumber;

// TODO: update the code for php optimization

class PhoneNumberMatcher implements \Iterator
{

    /**
     * The phone number pattern used by {@link #find}, similar to
     * {@code PhoneNumberUtil.VALID_PHONE_NUMBER}, but with the following differences:
     * <ul>
     *   <li>All captures are limited in order to place an upper bound to the text matched by the
     *       pattern.
     * <ul>
     *   <li>Leading punctuation / plus signs are limited.
     *   <li>Consecutive occurrences of punctuation are limited.
     *   <li>Number of digits is limited.
     * </ul>
     *   <li>No whitespace is allowed at the start or end.
     *   <li>No alpha digits (vanity numbers such as 1-800-SIX-FLAGS) are currently supported.
     * </ul>
     */
    protected static $PATTERN;

    /**
     * Matches strings that look like publication pages. Example:
     * <pre>Computing Complete Answers to Queries in the Presence of Limited Access Patterns.
     * Chen Li. VLDB J. 12(3): 211-227 (2003).</pre>
     *
     * The string "211-227 (2003)" is not a telephone number.
     */
    protected static $PUB_PAGES = '\\d{1,5}-+\\d{1,5}\\s{0,4}\\(\\d{1,4}';
    /**
     * Matches strings that look like dates using "/" as a separator. Examples: 3/10/2011, 31/10/96 or
     * 08/31/95.
     */
    protected static $SLASH_SEPARATED_DATES = '(?:(?:[0-3]?\\d/[01]?\\d)|(?:[01]?\\d/[0-3]?\\d))/(?:[12]\\d)?\\d{2}';
    /**
     * Matches timestamps. Examples: "2012-01-02 08:00". Note that the reg-ex does not include the
     * trailing ":\d\d" -- that is covered by TIME_STAMPS_SUFFIX.
     */
    protected static $TIME_STAMPS = '[12]\\d{3}[-/]?[01]\\d[-/]?[0-3]\\d[ ]+[0-2]\\d$';
    protected static $TIME_STAMPS_SUFFIX = ':[0-5]\\d';

    /**
     * Pattern to check that brackets match. Opening brackets should be closed within a phone number.
     * This also checks that there is something inside the brackets. Having no brackets at all is also
     * fine.
     */
    protected static $MATCHING_BRACKETS;

    protected static $INNER_MATCHES = [
        // Breaks on the slash - e.g. "651-234-2345/332-445-1234"
        '/+(.*)',
        // Note that the bracket here is inside the capturing group, since we consider it part of the
        // phone number. Will match a pattern like "(650) 223 3345 (754) 223 3321".
        '(\\([^(]*)',
        // Breaks on a hyphen - e.g. "12345 - 332-445-1234 is my number."
        // We require a space on either side of the hyphen for it to be considered a separator.
        '(?:\\p{Z}-|-\\p{Z})\\p{Z}*(.+)',
        // Various types of wide hyphens. Note we have decided not to enforce a space here, since it's
        // possible that it's supposed to be used to break two numbers without spaces, and we haven't
        // seen many instances of it used within a number.
        '[\x{2012}-\x{2015}\x{FF0D}]\\p{Z}*(.+)',
        // Breaks on a full stop - e.g. "12345. 332-445-1234 is my number."
        '\\.+\\p{Z}*([^.]+)',
        // Breaks on space - e.g. "3324451234 8002341234"
        '\\p{Z}+(\\P{Z}+)',
    ];

    /**
     * Punctuation that may be at the start of a phone number - brackets and plus signs.
     */
    protected static $LEAD_CLASS;
    /** The phone number utility. */
    private $phoneUtil;
    /** The text searched for phone numbers. */
    private $text;
    /**
     * The region (country) to assume for phone numbers without an international prefix, possibly
     * null.
     */
    private $preferredRegion;
    /** @var Leniency $leniency The degree of validation requested. */
    private $leniency;
    /** The maximum number of retries after matching an invalid number. */
    private $maxTries;
    /** The iteration tristate. */
    private $state = PhoneNumberMatcherState::NOT_READY;
    /** The last successful match, null unless in {@link State#READY}. */
    private $lastMatch = null;
    /** The next index to start searching at. Undefined in {@link State#DONE}. */
    private $searchIndex = 0;

    /**
     * Creates a new instance. See the factory methods in {@link PhoneNumberUtil} on how to obtain a
     * new instance.
     *
     * @param PhoneNumberUtil $util the phone number util to use
     * @param string $text the character sequence that we will search, null for no text
     * @param string $country the country to assume for phone numbers not written in international format
     *                  (with a leading plus, or with the international dialing prefix of the
     *                  specified region). May be null or "ZZ" if only numbers with a
     *                  leading plus should be considered.
     * @param Leniency $leniency the leniency to use when evaluating candidate phone numbers
     * @param int $maxTries the maximum number of invalid numbers to try before giving up on the text.
     *                  This is to cover degenerate cases where the text has a lot of false positives
     *                  in it. Must be {@code >= 0}.
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function __construct(PhoneNumberUtil $util, $text, $country, Leniency $leniency = null, $maxTries = 0)
    {
        self::initMatchingBracketsAndPattern();

        if (($util == null) || ($leniency == null)) {
            throw new \Exception('Null pointer exception');
        }
        if ($maxTries < 0) {
            throw new \InvalidArgumentException();
        }
        $this->phoneUtil = $util;
        $this->text = ($text != null) ? $text : "";
        $this->preferredRegion = $country;
        $this->leniency = $leniency;
        $this->maxTries = $maxTries;
    }

    /**
     *
     */
    protected static function initMatchingBracketsAndPattern()
    {
        /* Builds the MATCHING_BRACKETS and PATTERN regular expressions. The building blocks below exist
        * to make the pattern more easily understood. */

        $openingParens = '(\\[\x{FF08}\x{FF3B}';
        $closingParens = ')\\]\x{FF09}\x{FF3D}';
        $nonParens = '[^'.$openingParens.$closingParens.']';

        /* Limit on the number of pairs of brackets in a phone number. */
        $bracketPairLimit = self::limit(0, 3);
        /*
         * An opening bracket at the beginning may not be closed, but subsequent ones should be.  It's
         * also possible that the leading bracket was dropped, so we shouldn't be surprised if we see a
         * closing bracket first. We limit the sets of brackets in a phone number to four.
         */
        self::$MATCHING_BRACKETS = '(?:['.$openingParens.'])?'.'(?:'.$nonParens.'+'.'['.$closingParens.'])?'.
            $nonParens.'+'.
            '(?:['.$openingParens.']'.$nonParens.'+['.$closingParens.'])'.$bracketPairLimit.
            $nonParens.'*';

        /* Limit on the number of leading (plus) characters. */
        $leadLimit = self::limit(0, 2);
        /* Limit on the number of consecutive punctuation characters. */
        $punctuationLimit = self::limit(0, 4);
        /* The maximum number of digits allowed in a digit-separated block. As we allow all digits in a
         * single block, set high enough to accommodate the entire national number and the international
         * country code. */
        $digitBlockLimit = PhoneNumberUtil::MAX_LENGTH_FOR_NSN + PhoneNumberUtil::MAX_LENGTH_COUNTRY_CODE;
        /* Limit on the number of blocks separated by punctuation. Uses digitBlockLimit since some
         * formats use spaces to separate each digit. */
        $blockLimit = self::limit(0, $digitBlockLimit);

        /* A punctuation sequence allowing white space. */
        $punctuation = '['.PhoneNumberUtil::VALID_PUNCTUATION.']'.$punctuationLimit;
        /* A digits block without punctuation. */
        $digitSequence = '\\p{Nd}'.self::limit(1, $digitBlockLimit);

        $leadClassChars = $openingParens.PhoneNumberUtil::PLUS_CHARS;
        $leadClass = '['.$leadClassChars.']';
        self::$LEAD_CLASS = $leadClass;

        self::$PATTERN = '(?:'.$leadClass.$punctuation.')'.$leadLimit.
            $digitSequence.'(?:'.$punctuation.$digitSequence.')'.$blockLimit.
            '(?:'.preg_replace('/#/', '\#', PhoneNumberUtil::$EXTN_PATTERNS_FOR_MATCHING).')?';
    }

    /**
     * Returns a regular expression quantifier with an upper and lower limit.
     * @param int $lower
     * @param int $upper
     * @return string
     * @throws \InvalidArgumentException
     */
    private static function limit($lower, $upper)
    {
        if (($lower < 0) || ($upper <= 0) || ($upper < $lower)) {
            throw new \InvalidArgumentException();
        }

        return '{'.$lower.','.$upper.'}';
    }

    /**
     * @param PhoneNumber $number
     * @param $candidate
     * @param PhoneNumberUtil $util
     * @return bool
     */
    public static function containsOnlyValidXChars(PhoneNumber $number, $candidate, PhoneNumberUtil $util)
    {
        for ($index = 0; $index < mb_strlen($candidate, 'UTF-8') - 1; $index++) {
            $charAtIndex = $candidate{$index};
            if ($charAtIndex != 'x' && $charAtIndex != 'X') {
                continue;
            }

            $charAtNextIndex = $candidate{$index + 1};
            if ($charAtNextIndex == 'x' || $charAtNextIndex == 'X') {
                $index++;
                if ($util->isNumberMatch($number, mb_substr($candidate, $index, null, 'UTF-8')) == MatchType::NSN_MATCH) {
                    return false;
                }
                continue;
            }

            if (PhoneNumberUtil::normalizeDigitsOnly(mb_substr($candidate, $index, null, 'UTF-8')) != $number->getExtension()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param PhoneNumber $number
     * @param PhoneNumberUtil $util
     * @return bool
     */
    public static function isNationalPrefixPresentIfRequired(PhoneNumber $number, PhoneNumberUtil $util)
    {
        // First, check how we deduced the country code. If it was written in international format, then
        // the national prefix is not required.
        if ($number->getCountryCodeSource() != CountryCodeSource::FROM_DEFAULT_COUNTRY) {
            return true;
        }

        $phoneNumberRegion = $util->getRegionCodeForCountryCode($number->getCountryCode());
        $metadata = $util->getMetadataForRegion($phoneNumberRegion);
        if ($metadata == null) {
            return true;
        }

        // Check if a national prefix should be present when formatting this number.
        $nationalNumber = $util->getNationalSignificantNumber($number);
        $formatRule = $util->chooseFormattingPatternForNumber($metadata->numberFormats(), $nationalNumber);
        // To do this, we check that a national prefix formatting rule was present and that it wasn't
        // just the first-group symbol ($1) with punctuation.
        if ($formatRule != null && strlen($formatRule->getNationalPrefixFormattingRule()) > 0) {
            if ($formatRule->getNationalPrefixOptionalWhenFormatting()) {
                // The national-prefix is optional in these cases, so we don't need to check if it was
                // present.
                return true;
            }
            if (PhoneNumberUtil::formattingRuleHasFirstGroupOnly($formatRule->getNationalPrefixFormattingRule())) {
                // National Prefix not needed for this number.
                return true;
            }

            // Normalize the remainder.
            $rawInputCopy = PhoneNumberUtil::normalizeDigitsOnly($number->getRawInput());
            $rawInput = $rawInputCopy;
            // Check if we found a national prefix and/or carrier code at the start of the raw input, and
            // return the result.
            return $util->maybeStripNationalPrefixAndCarrierCode($rawInput, $metadata, $carrierCode);
        }

        return true;
    }

    public static function containsMoreThanOneSlashInNationalNumber(PhoneNumber $number, $candidate)
    {
        $firstSlashInBodyIndex = strpos($candidate, '/');
        if ($firstSlashInBodyIndex === false) {
            // No slashes, this is okay.
            return false;
        }

        // Now look for a second one.
        $secondSlashInBodyIndex = strpos($candidate, '/', $firstSlashInBodyIndex + 1);
        if ($secondSlashInBodyIndex === false) {
            // Only one slash, this is okay.
            return false;
        }

        // If the first slash is after the country calling code, this is permitted.
        $candidateHasCountryCode = ($number->getCountryCodeSource() == CountryCodeSource::FROM_NUMBER_WITH_PLUS_SIGN ||
            $number->getCountryCodeSource() == CountryCodeSource::FROM_NUMBER_WITHOUT_PLUS_SIGN);
        if ($candidateHasCountryCode &&
            PhoneNumberUtil::normalizeDigitsOnly(substr($candidate, 0,
                $firstSlashInBodyIndex)) == $number->getCountryCode()
        ) {
            // Any more slashes and this is illegal.
            return strpos(substr($candidate, $secondSlashInBodyIndex + 1), '/') !== false;
        }

        return true;
    }

    public function current()
    {
        // Start or rewind
        if ($this->lastMatch == null && $this->state == PhoneNumberMatcherState::NOT_READY) {
            return $this->next();
        }

        return $this->lastMatch;
    }

    public function next()
    {
        // Check the state and find the next match as a side-effect if necessary.
        if (!$this->hasNext()) {
            return false;
        }

        // Don't retain that memory any longer than necessary.
        $result = $this->lastMatch;
        $this->state = PhoneNumberMatcherState::NOT_READY;

        return $result;
    }

    public function hasNext()
    {
        if ($this->state == PhoneNumberMatcherState::NOT_READY) {
            $this->lastMatch = $this->find($this->searchIndex);
            if ($this->lastMatch == null) {
                $this->state = PhoneNumberMatcherState::DONE;
            } else {
                $this->searchIndex = $this->lastMatch->end();
                $this->state = PhoneNumberMatcherState::READY;
            }
        }

        return ($this->state == PhoneNumberMatcherState::READY);
    }

    /**
     * Attempts to find the next subsequence in the searched sequence on or after {@code searchIndex}
     * that represents a phone number. Returns the next match, null if none was found.
     *
     * @param int $index the search index to start searching at
     * @return Matcher|null the phone number match found, null if none can be found
     */
    private function find($index)
    {
        $matcher = new Matcher(self::$PATTERN, $this->text);
        while (($this->maxTries > 0) && $matcher->find($index)) {
            $start = $matcher->start();
            $candidate = mb_substr($this->text, $start, $matcher->end() - $start, 'UTF-8');

            // Check for extra numbers at the end.
            // TODO: This is the place to start when trying to support extraction of multiple phone number
            // from split notations (+41 79 123 45 67 / 68).
            $candidate = $this->trimAfterFirstMatch(PhoneNumberUtil::$SECOND_NUMBER_START_PATTERN, $candidate);

            $match = $this->extractMatch($candidate, $start);

            if ($match != null) {
                return $match;
            }

            $index = $start + mb_strlen($candidate, 'UTF-8');
            $this->maxTries--;
        }

        return null;
    }

    /**
     * Trims away any characters after the first match of {@code pattern} in {@code candidate},
     * returning the trimmed version.
     * @param string $pattern
     * @param string $candidate
     * @return string
     */
    private static function trimAfterFirstMatch($pattern, $candidate)
    {
        $trailingCharsMatcher = new Matcher($pattern, $candidate);
        if ($trailingCharsMatcher->find()) {
            $candidate = mb_substr($candidate, 0, $trailingCharsMatcher->start(), 'UTF-8');
        }

        return $candidate;
    }

    /**
     * Attempts to extract a match from a {@code candidate} character sequence.
     *
     * @param string $candidate the candidate text that might contain a phone number
     * @param int $offset the offset of {@code candidate} within {@link #text}
     * @return Matcher|null the match found, null if none can be found
     */
    private function extractMatch($candidate, $offset)
    {
        if ((new Matcher(self::$SLASH_SEPARATED_DATES, $candidate))->find()) {
            return null;
        }

        // Skip potential time-stamps.
        if ((new Matcher(self::$TIME_STAMPS, $candidate))->find()) {
            $followingText = mb_substr($this->text, $offset + mb_strlen($candidate, 'UTF-8'), null, 'UTF-8');
            if ((new Matcher(self::$TIME_STAMPS_SUFFIX, $followingText))->lookingAt()) {
                return null;
            }
        }

        // Try to come up with a valid match given the entire candidate.
        $rawString = $candidate;
        $match = $this->parseAndVerify($rawString, $offset);

        if ($match != null) {
            return $match;
        }

        $innerMatch = $this->extractInnerMatch($rawString, $offset);

        return $innerMatch;
    }

    /**
     * Parses a phone number from the {@code candidate} using {@link PhoneNumberUtil#parse} and
     * verifies it matches the requested {@link #leniency}. If parsing and verification succeed, a
     * corresponding {@link PhoneNumberMatch} is returned, otherwise this method returns null.
     *
     * @param string $candidate the candidate match
     * @param int $offset the offset of {@code candidate} within {@link #text}
     * @return PhoneNumberMatch|null the parsed and validated phone number match, or null
     */
    private function parseAndVerify($candidate, $offset)
    {
        try {
            if (!(new Matcher(self::$MATCHING_BRACKETS, $candidate))->matches() || (new Matcher(self::$PUB_PAGES,
                    $candidate))->find()
            ) {
                return null;
            }


            // If leniency is set to VALID or stricter, we also want to skip numbers that are surrounded
            // by Latin alphabetic characters, to skip cases like abc8005001234 or 8005001234def.
            if ($this->leniency->level() == Leniency::VALID) {
                // If the candidate is not at the start of the text, and does not start with phone-number
                // punctuation, check the previous character.
                if ($offset > 0 && !((new Matcher(self::$LEAD_CLASS, $candidate))->lookingAt())) {
                    $previousChar = mb_substr($this->text, $offset - 1 , 1, 'UTF-8'); //substr($this->text, $offset - 1, 1);
                    // We return null if it is a latin letter or an invalid punctuation symbol.
                    if ($this->isInvalidPunctuationSymbol($previousChar) || $this->isLatinLetter($previousChar)) {
                        return null;
                    }
                }
                $lastCharIndex = $offset + mb_strlen($candidate, 'UTF-8');
                if ($lastCharIndex < mb_strlen($this->text, 'UTF-8')) {
                    $nextChar = mb_substr($this->text, $lastCharIndex, 1, 'UTF-8');
                    if ($this->isInvalidPunctuationSymbol($nextChar) || $this->isLatinLetter($nextChar)) {
                        return null;
                    }
                }
            }

            $number = $this->phoneUtil->parseAndKeepRawInput($candidate, $this->preferredRegion);

            // Check Israel * numbers: these are a special case in that they are four-digit numbers that
            // our library supports, but they can only be dialled with a leading *. Since we don't
            // actually store or detect the * in our phone number library, this means in practice we
            // detect most four digit numbers as being valid for Israel. We are considering moving these
            // numbers to ShortNumberInfo instead, in which case this problem would go away, but in the
            // meantime we want to restrict the false matches so we only allow these numbers if they are
            // preceded by a star. We enforce this for all leniency levels even though these numbers are
            // technically accepted by isPossibleNumber and isValidNumber since we consider it to be a
            // deficiency in those methods that they accept these numbers without the *.
            // TODO: Remove this or make it significantly less hacky once we've decided how to
            // handle these short codes going forward in ShortNumberInfo. We could use the formatting
            // rules for instance, but that would be slower.
            if ($this->phoneUtil->getRegionCodeForCountryCode($number->getCountryCode()) == 'IL' &&
                mb_strlen($this->phoneUtil->getNationalSignificantNumber($number), 'UTF-8') == 4 &&
                ($offset == 0 || ($offset > 0 && $this->text{$offset - 1} != '*'))
            ) {
                // No match
                return null;
            }

            if ($this->leniency->verify($number, $candidate, $this->phoneUtil)) {
                $number->clearCountryCodeSource();
                $number->clearRawInput();
                $number->clearPreferredDomesticCarrierCode();

                return new PhoneNumberMatch($offset, $candidate, $number);
            }
        } catch (NumberParseException $e) {

        }

        return null;
    }

    private static function isInvalidPunctuationSymbol($character)
    {
        return $character == '%' || preg_match('%\p{Sc}%u', $character);;
    }

    /**
     * Helper method to determine if a character is a Latin-script letter or not. For our purposes,
     * combining marks should also return true since we assume they have been added to a preceding
     * Latin character.
     * [\u0000-\u007F] = Basic Latin;
     * [\u0080-\u00FF] = Latin-1 Supplement;
     * [\u0100-\u017F] = Latin Extended-A;
     * [\u0180-\u024F] = Latin Extended-B;
     * [\u1E00-\u1EFF] = Latin Extended Additional
     * [\u0300-\u036F] = Combining Diacritical marks
     *
     *
     * [\u0000-\u024F] together
     */
    public static function isLatinLetter($letter)
    {
        // Combining marks are a subset of non-spacing-mark.
        if (preg_match('%\P{L}%u', $letter) && preg_match('%\P{Mn}%u', $letter)) {
            return false;
        }

        return preg_match('%[\x{0000}-\x{024F}]|[\x{1E00}-\x{1EFF}]|[\x{0300}-\x{036F}]%ui', $letter) > 0;
    }

    private function extractInnerMatch($candidate, $offset)
    {
        foreach (self::$INNER_MATCHES as $possibleInnerMatch) {
            $groupMatcher = new Matcher($possibleInnerMatch, $candidate);
            $isFirstMatch = true;
            while ($groupMatcher->find() && $this->maxTries > 0) {
                if ($isFirstMatch) {
                    $group = self::trimAfterFirstMatch(PhoneNumberUtil::$UNWANTED_END_CHAR_PATTERN,
                        mb_substr($candidate, 0, $groupMatcher->start(), 'UTF-8'));
                    $match = $this->parseAndVerify($group, $offset);
                    if ($match != null) {
                        return $match;
                    }

                    $this->maxTries--;
                    $isFirstMatch = false;
                }
                $group = self::trimAfterFirstMatch(PhoneNumberUtil::$UNWANTED_END_CHAR_PATTERN,
                    $groupMatcher->group(1));

                $match = $this->parseAndVerify($group, $offset + $groupMatcher->start(1));

                if ($match != null) {
                    return $match;
                }

                $this->maxTries--;
            }
        }

        return null;
    }


    public function key()
    {
        throw new \Exception('Not supported.');
    }

    public function valid()
    {
        return ($this->state !== PhoneNumberMatcherState::DONE);
    }

    public function rewind()
    {
        $this->searchIndex = 0;
        $this->lastMatch = null;
    }
}