<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 * @author CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace OpenPsa\Ranger;

use IntlDateFormatter;
use Datetime;
use RuntimeException;

class Ranger
{
    const ERA = 0;
    const YEAR = 1;
    const QUARTER = 2;
    const MONTH = 3;
    const WEEK = 4;
    const DAY = 5;
    const AM = 6;
    const HOUR = 7;
    const MINUTE = 8;
    const SECOND = 9;
    const TIMEZONE = -1;

    private $pattern_characters = array
    (
        'G' => self::ERA,
        'y' => self::YEAR,
        'Y' => self::YEAR,
        'u' => self::YEAR,
        'U' => self::YEAR,
        'r' => self::YEAR,
        'Q' => self::QUARTER,
        'q' => self::QUARTER,
        'M' => self::MONTH,
        'L' => self::MONTH,
        'w' => self::WEEK,
        'W' => self::WEEK,
        'd' => self::DAY,
        'D' => self::DAY,
        'F' => self::DAY,
        'g' => self::DAY,
        'E' => self::DAY,
        'e' => self::DAY,
        'c' => self::DAY,
        'a' => self::AM,
        'h' => self::HOUR,
        'H' => self::HOUR,
        'k' => self::HOUR,
        'K' => self::HOUR,
        'm' => self::MINUTE,
        's' => self::SECOND,
        'S' => self::SECOND,
        'A' => self::SECOND,
        'z' => self::TIMEZONE,
        'Z' => self::TIMEZONE,
        'O' => self::TIMEZONE,
        'v' => self::TIMEZONE,
        'V' => self::TIMEZONE,
        'X' => self::TIMEZONE,
        'x' => self::TIMEZONE
    );

    /**
     * @var string
     */
    private $escape_character = "'";

    /**
     * @var IntlDateFormatter
     */
    private $intl;

    /**
     * @var array
     */
    private $pattern_mask;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var int
     */
    private $date_format = IntlDateFormatter::MEDIUM;

    /**
     * @var int
     */
    private $time_format = IntlDateFormatter::NONE;

    /**
     *
     * @param string $locale
     */
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @param int $format
     * @return self
     */
    public function setDateFormat($format)
    {
        if ($format !== $this->date_format)
        {
            $this->date_format = $format;
            $this->intl = null;
            $this->pattern_mask = array();
        }
        return $this;
    }

    /**
     * @param int $format
     * @return self
     */
    public function setTimeFormat($format)
    {
        if ($format !== $this->time_format)
        {
            $this->time_format = $format;
            $this->intl = null;
            $this->pattern_mask = array();
        }
        return $this;
    }

    /**
     *
     * @param mixed $start
     * @param mixed $end
     * @return string
     */
    public function format($start, $end)
    {
        $start = new Datetime($start);
        $end = new Datetime($end);

        $best_match = $this->find_best_match($start, $end);

        $start_tokens = $this->tokenize($start);
        $end_tokens = $this->tokenize($end);

        $left = '';
        foreach ($this->pattern_mask as $i => $part)
        {
            if ($part['delimiter'])
            {
                $left .= $part['content'];
            }
            else
            {
                if ($part['content'] > $best_match)
                {
                    break;
                }
                $left .= $start_tokens[$i]['content'];
            }
        }

        $right = '';
        for ($j = count($this->pattern_mask) - 1; $j + 1 > $i; $j--)
        {
            $part = $end_tokens[$j];

            if ($part['type'] == 'delimiter')
            {
                $right = $part['content'] . $right;
            }
            else
            {
                if ($part['type'] > $best_match)
                {
                    break;
                }
                $right = $part['content'] . $right;
            }
        }

        $left_middle = '';
        $right_middle = '';
        for ($k = $i; $k <= $j; $k++)
        {
            $left_middle .= $start_tokens[$k]['content'];
            $right_middle .= $end_tokens[$k]['content'];
        }

        return $left . $left_middle . ' - ' . $right_middle . $right;
    }

    /**
     * @param DateTime $date
     * @return array
     */
    private function tokenize(DateTime $date)
    {
        $tokens = array();
        $formatted = $this->get_intl()->format((int) $date->format('U'));

        $type = null;
        foreach ($this->pattern_mask as $i => $part)
        {
            if ($part['delimiter'])
            {
                $parts = explode($part['content'], $formatted, 2);

                if (count($parts) == 2)
                {
                    $tokens[] = array('type' => $type, 'content' => $parts[0]);
                    $formatted = $parts[1];
                }
                $tokens[] = array('type' => 'delimiter', 'content' => $part['content']);
            }
            else
            {
                $type = $part['content'];
            }
        }
        if (!$part['delimiter'])
        {
            $tokens[] =  array('type' => $type, 'content' => $formatted);
        }
        return $tokens;
    }

    /**
     * @return IntlDateFormatter
     */
    private function get_intl()
    {
        if ($this->intl === null)
        {
            $this->intl = new IntlDateFormatter($this->locale, $this->date_format, $this->time_format);
            $this->parse_pattern($this->intl->getPattern());
        }
        return $this->intl;
    }

    /**
     *
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    private function find_best_match(DateTime $start, DateTime $end)
    {
        $best_match = -2;
        if ($start->format('Y') !== $end->format('Y'))
        {
            $best_match = self::TIMEZONE;
        }
        else if ($start->format('m') !== $end->format('m'))
        {
            $best_match = self::YEAR;
        }
        else if ($start->format('d') !== $end->format('d'))
        {
            $best_match = self::MONTH;
        }
        else if ($start->format('a') !== $end->format('a'))
        {
            $best_match = self::DAY;
        }
        else if ($start->format('H') !== $end->format('H'))
        {
            $best_match = self::AM;
        }
        else if ($start->format('i') !== $end->format('i'))
        {
            //it makes no sense to display something like 10:00:00 - 30:00...
            $best_match = self::AM;
        }
        else if ($start->format('s') !== $end->format('s'))
        {
            //it makes no sense to display something like 10:00:00 - 30:00...
            $best_match = self::AM;
        }
        else
        {
            $best_match = self::SECOND;
        }

        //set to same time to avoid DST problems
        $tz_end = clone $end;
        $tz_end->setTimestamp((int) $start->format('U'));
        if (   $start->format('T') !== $tz_end->format('T')
            || (   $this->time_format !== IntlDateFormatter::NONE
                && $best_match < self::DAY))
        {
            $best_match = -2;
        }
        return $best_match;
    }

    /**
     * @param string $pattern
     * @return array
     */
    private function parse_pattern($pattern)
    {
        $this->pattern_mask = array();
        $esc_active = false;
        $part = array('content' => '', 'delimiter' => false);
        foreach (str_split($pattern) as $char)
        {
            //@todo the esc char handling is untested
            if ($char == $this->escape_character)
            {
                if ($esc_active)
                {
                    $escape_active = false;
                    if ($part['content'] === '')
                    {
                        //Literal '
                        $part['content'] = $char;
                    }
                    $this->push_to_mask($part);
                    $part = array('content' => '', 'delimiter' => false);
                }
                else
                {
                    $escape_active = true;
                    $this->push_to_mask($part);
                    $part = array('content' => '', 'delimiter' => true);
                }
            }
            else if ($esc_active)
            {
                $part['content'] .= $char;
            }
            else if (!array_key_exists($char, $this->pattern_characters))
            {
                if ($part['delimiter'] === false)
                {
                    $this->push_to_mask($part);
                    $part = array('content' => $char, 'delimiter' => true);
                }
                else
                {
                    $part['content'] .= $char;
                }
            }
            else
            {
                if ($part['delimiter'] === true)
                {
                    $this->push_to_mask($part);
                    $part = array('content' => $this->pattern_characters[$char], 'delimiter' => false);
                }
                else
                {
                    if (   $part['content'] !== ''
                        && $part['content'] !== $this->pattern_characters[$char])
                    {
                        throw new RuntimeException('missing separator between date parts');
                    }
                    $part['content'] = $this->pattern_characters[$char];
                }
            }
        }
        $this->push_to_mask($part);
    }

    /**
     * @param array $part
     */
    private function push_to_mask(array $part)
    {
        if ($part['content'] !== '')
        {
            $this->pattern_mask[] = $part;
        }
    }
}