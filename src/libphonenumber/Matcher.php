<?php

namespace libphonenumber;

/**
 * Matcher for various regex matching
 *
 * Note that this is NOT the same as google's java PhoneNumberMatcher class.
 * This class is a minimal port of java's built-in matcher class, whereas PhoneNumberMatcher
 * is designed to recognize phone numbers embedded in any text.
 */
class Matcher
{
    /**
     * @var string
     */
    protected $pattern;

    /**
     * @var string
     */
    protected $subject;

    /**
     * @var array
     */
    protected $groups = array();

    /**
     * @var int
     */
    protected $offset = 0;

    /**
     * @param string $pattern
     * @param string $subject
     */
    public function __construct($pattern, $subject)
    {
        $this->pattern = str_replace('/', '\/', $pattern);
        $this->subject = $subject;
    }

    protected function doMatch($type = 'find')
    {
        $final_pattern = '(?:' . $this->pattern . ')';
        switch ($type) {
            case 'matches':
                $final_pattern = '^' . $final_pattern . '$';
                break;
            case 'lookingAt':
                $final_pattern = '^' . $final_pattern;
                break;
            case 'find':
            default:
                // no changes	    
                break;
        }

        $final_pattern = '/' . $final_pattern . '/uix';

        $offset = strlen(mb_substr($this->subject, 0, $this->offset, 'UTF-8'));
        $found = (preg_match($final_pattern, $this->subject, $this->groups, PREG_OFFSET_CAPTURE, $offset) == 1) ? true : false;
        if ($found) {
            $this->offset = $this->start(0) + mb_strlen($this->groups[0][0], 'UTF-8'); //$this->groups[0][1] + strlen($this->groups[0][0]);
        }
        return $found;
    }

    /**
     * @return bool
     */
    public function matches()
    {
        return $this->doMatch('matches');
    }

    /**
     * @return bool
     */
    public function lookingAt()
    {
        return $this->doMatch('lookingAt');
    }

    /**
     * @param int $index
     * @return bool
     */
    public function find($index = null)
    {
        if ($index !== null) {
            $this->offset = $index;
        }

        return $this->doMatch('find');
    }

    /**
     * @return int
     */
    public function groupCount()
    {
        if (empty($this->groups)) {
            return null;
        } else {
            return count($this->groups) - 1;
        }
    }

    /**
     * @param int $group
     * @return string
     */
    public function group($group = null)
    {
        if (!isset($group)) {
            $group = 0;
        }
        return (isset($this->groups[$group][0])) ? $this->groups[$group][0] : null;
    }

    /**
     * @param int|null $group
     * @return int
     */
    public function end($group = null)
    {
        if (!isset($group) || $group === null) {
            $group = 0;
        }
        if (!isset($this->groups[$group])) {
            return null;
        }
        //return $this->groups[$group][1] + strlen($this->groups[$group][0]);
        return $this->start($group) + mb_strlen($this->groups[$group][0], 'UTF-8');
    }

    public function start($group = null)
    {
        if ($group === null) {
            $group = 0;
        }

        if (!isset($this->groups[$group])) {
            return null;
        }

        return mb_strlen(substr($this->subject, 0, $this->groups[$group][1]), 'UTF-8');//$this->groups[$group][1];
    }

    /**
     * @param string $replacement
     * @return string
     */
    public function replaceFirst($replacement)
    {
        return preg_replace('/' . $this->pattern . '/x', $replacement, $this->subject, 1);
    }

    /**
     * @param string $replacement
     * @return string
     */
    public function replaceAll($replacement)
    {
        return preg_replace('/' . $this->pattern . '/x', $replacement, $this->subject);
    }

    /**
     * @param string $input
     * @return Matcher
     */
    public function reset($input = "")
    {
        $this->subject = $input;

        return $this;
    }
}
