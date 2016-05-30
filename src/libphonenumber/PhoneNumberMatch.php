<?php
/**
 * Created by PhpStorm.
 * User: SaphirAngel
 * Date: 26/05/2016
 * Time: 13:22
 */

namespace libphonenumber;


final class PhoneNumberMatch
{
    /** @var int $start The start index into the text. */
    private $start;
    /** @var string $rawString The raw substring matched. */
    private $rawString;
    /** @var PhoneNumber $number The matched phone number. */
    private $number;

    /**
     * Creates a new match.
     *
     * @param int $start the start index into the target text
     * @param string $rawString the matched substring of the target text
     * @param PhoneNumber $number the matched phone number
     */
    public function __construct($start, $rawString, PhoneNumber $number)
    {
        if ($start < 0) {
            throw new \InvalidArgumentException("Start index must be >= 0.");
        }

        if ($rawString == null || $number == null) {
            throw new \Exception('NullPointerException');
        }
        $this->start = $start;
        $this->rawString = $rawString;
        $this->number = $number;
    }

    /** Returns the phone number matched by the receiver. */
    public function number()
    {
        return $this->number;
    }

    public function __toString()
    {
        return 'PhoneNumberMatch ['.$this->start().','.$this->end().')'.$this->rawString();
    }

    /** Returns the start index of the matched phone number within the searched text. */
    public function start()
    {
        return $this->start;
    }

    /** Returns the exclusive end index of the matched phone number within the searched text. */
    public function end()
    {
        return $this->start + mb_strlen($this->rawString, 'UTF-8');
    }

    /** Returns the raw string matched as a phone number in the searched text. */
    public function rawString()
    {
        return $this->rawString;
    }
}