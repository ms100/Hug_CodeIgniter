<?php

Trait Trait_ci
{
    // --------------------------------------------------------------------

    /**
     * Required
     *
     * @param    string
     *
     * @return    bool
     */
    public function required($str)
    {
        return is_array($str) ? !empty($str) : (trim($str) !== '');
    }

    // --------------------------------------------------------------------

    /**
     * Performs a Regular Expression match test.
     *
     * @param    string
     * @param    string    regex
     *
     * @return    bool
     */
    public function regex_match($str, $regex)
    {
        return (bool)preg_match($regex, $str);
    }

    // --------------------------------------------------------------------

    /**
     * Match one field to another
     *
     * @param    string $str string to compare against
     * @param    array  $indexes
     * @param    string $field
     *
     * @return    bool
     */
    public function matches($str, array $indexes, $field)
    {
        $subject = $this->parse_field_str($field);
        $subject = $this->replace_uncertain_index($subject, $indexes);
        $data = $this->get_validation_data_element($subject);

        return isset($data) ? ($str === $data) : false;
    }

    // --------------------------------------------------------------------

    /**
     * Differs from another field
     *
     * @param   string $str
     * @param   array  $indexes
     * @param   string field
     *
     * @return    bool
     */
    public function differs($str, array $indexes, $field)
    {
        $subject = $this->parse_field_str($field);
        $subject = $this->replace_uncertain_index($subject, $indexes);
        $data = $this->get_validation_data_element($subject);

        return !(isset($data) && $data === $str);
    }

    // --------------------------------------------------------------------

    /**
     * Minimum Length
     *
     * @param    string
     * @param    string
     *
     * @return    bool
     */
    public function min_length($str, $val)
    {
        if (!is_numeric($val)) {
            return false;
        }

        return ($val <= mb_strlen($str));
    }

    // --------------------------------------------------------------------

    /**
     * Max Length
     *
     * @param    string
     * @param    string
     *
     * @return    bool
     */
    public function max_length($str, $val)
    {
        if (!is_numeric($val)) {
            return false;
        }

        return ($val >= mb_strlen($str));
    }

    // --------------------------------------------------------------------

    /**
     * Exact Length
     *
     * @param    string
     * @param    string
     *
     * @return    bool
     */
    public function exact_length($str, $val)
    {
        if (!is_numeric($val)) {
            return false;
        }

        return (mb_strlen($str) === (int)$val);
    }

    // --------------------------------------------------------------------

    /**
     * Valid URL
     *
     * @param    string $str
     *
     * @return    bool
     */
    public function valid_url($str)
    {
        if (empty($str)) {
            return false;
        } elseif (preg_match('/^(?:([^:]*)\:)?\/\/(.+)$/', $str, $matches)) {
            if (empty($matches[2])) {
                return false;
            } elseif (!in_array(strtolower($matches[1]), ['http', 'https'], true)) {
                return false;
            }

            $str = $matches[2];
        }

        // PHP 7 accepts IPv6 addresses within square brackets as hostnames,
        // but it appears that the PR that came in with https://bugs.php.net/bug.php?id=68039
        // was never merged into a PHP 5 branch ... https://3v4l.org/8PsSN
        if (preg_match('/^\[([^\]]+)\]/', $str, $matches) && !is_php('7') && filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $str = 'ipv6.host' . substr($str, strlen($matches[1]) + 2);
        }

        return (filter_var('http://' . $str, FILTER_VALIDATE_URL) !== false);
    }

    // --------------------------------------------------------------------

    /**
     * Valid Email
     *
     * @param    string
     *
     * @return    bool
     */
    public function valid_email($str)
    {
        if (function_exists('idn_to_ascii') && preg_match('#\A([^@]+)@(.+)\z#', $str, $matches)) {
            $str = $matches[1] . '@' . idn_to_ascii($matches[2]);
        }

        return (bool)filter_var($str, FILTER_VALIDATE_EMAIL);
    }

    // --------------------------------------------------------------------

    /**
     * Valid Emails
     *
     * @param    string
     *
     * @return    bool
     */
    public function valid_emails($str)
    {
        if (strpos($str, ',') === false) {
            return $this->valid_email(trim($str));
        }

        foreach (explode(',', $str) as $email) {
            if (trim($email) !== '' && $this->valid_email(trim($email)) === false) {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Validate IP Address
     *
     * @param    string
     * @param    string    'ipv4' or 'ipv6' to validate a specific IP format
     *
     * @return    bool
     */
    public function valid_ip($ip, $which = '')
    {
        return $this->CI->input->valid_ip($ip, $which);
    }

    // --------------------------------------------------------------------

    /**
     * Alpha
     *
     * @param    string
     *
     * @return    bool
     */
    public function alpha($str)
    {
        return ctype_alpha($str);
    }

    // --------------------------------------------------------------------

    /**
     * Alpha-numeric
     * @param    string
     *
     * @return    bool
     */
    public function alpha_numeric($str)
    {
        return ctype_alnum((string)$str);
    }

    // --------------------------------------------------------------------

    /**
     * Alpha-numeric w/ spaces
     *
     * @param    string
     *
     * @return    bool
     */
    public function alpha_numeric_spaces($str)
    {
        return (bool)preg_match('/^[A-Z0-9 ]+$/i', $str);
    }

    // --------------------------------------------------------------------

    /**
     * Alpha-numeric with underscores and dashes
     *
     * @param    string
     *
     * @return    bool
     */
    public function alpha_dash($str)
    {
        return (bool)preg_match('/^[a-z0-9_-]+$/i', $str);
    }

    // --------------------------------------------------------------------

    /**
     * Numeric
     *
     * @param    string
     *
     * @return    bool
     */
    public function numeric($str)
    {
        return (bool)preg_match('/^[\-+]?[0-9]*\.?[0-9]+$/', $str);
    }

    // --------------------------------------------------------------------

    /**
     * Integer
     *
     * @param    string
     *
     * @return    bool
     */
    public function integer($str)
    {
        return (bool)preg_match('/^[\-+]?[0-9]+$/', $str);
    }

    // --------------------------------------------------------------------

    /**
     * Decimal number
     *
     * @param    string
     *
     * @return    bool
     */
    public function decimal($str)
    {
        return (bool)preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $str);
    }

    // --------------------------------------------------------------------

    /**
     * Greater than
     *
     * @param    string
     * @param    int
     *
     * @return    bool
     */
    public function greater_than($str, $min)
    {
        return is_numeric($str) ? ($str > $min) : false;
    }

    // --------------------------------------------------------------------

    /**
     * Equal to or Greater than
     *
     * @param    string
     * @param    int
     *
     * @return    bool
     */
    public function greater_than_equal_to($str, $min)
    {
        return is_numeric($str) ? ($str >= $min) : false;
    }

    // --------------------------------------------------------------------

    /**
     * Less than
     *
     * @param    string
     * @param    int
     *
     * @return    bool
     */
    public function less_than($str, $max)
    {
        return is_numeric($str) ? ($str < $max) : false;
    }

    // --------------------------------------------------------------------

    /**
     * Equal to or Less than
     *
     * @param    string
     * @param    int
     *
     * @return    bool
     */
    public function less_than_equal_to($str, $max)
    {
        return is_numeric($str) ? ($str <= $max) : false;
    }

    // --------------------------------------------------------------------

    /**
     * Value should be within an array of values
     *
     * @param    string
     * @param    string
     *
     * @return    bool
     */
    public function in_list($value, $list)
    {
        return in_array($value, explode(',', $list), true);
    }

    // --------------------------------------------------------------------

    /**
     * Is a Natural number  (0,1,2,3, etc.)
     *
     * @param    string
     *
     * @return    bool
     */
    public function is_natural($str)
    {
        return ctype_digit((string)$str);
    }

    // --------------------------------------------------------------------

    /**
     * Is a Natural number, but not a zero  (1,2,3, etc.)
     *
     * @param    string
     *
     * @return    bool
     */
    public function is_natural_no_zero($str)
    {
        return ($str != 0 && ctype_digit((string)$str));
    }

    // --------------------------------------------------------------------

    /**
     * Valid Base64
     * Tests a string for characters outside of the Base64 alphabet
     * as defined by RFC 2045 http://www.faqs.org/rfcs/rfc2045
     *
     * @param    string
     *
     * @return    bool
     */
    public function valid_base64($str)
    {
        return (base64_encode(base64_decode($str)) === $str);
    }

    // --------------------------------------------------------------------

    /**
     * Prep URL
     *
     * @param    string
     *
     * @return    string
     */
    public function prep_url($str = '')
    {
        if ($str === 'http://' OR $str === '' OR $str === '//') {
            return '';
        }

        $url = parse_url($str);

        if (!$url || !isset($url['scheme'])) {
            return 'http://' . ltrim($str, ':/');
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Strip Image Tags
     *
     * @param    string
     *
     * @return    string
     */
    public function strip_image_tags($str)
    {
        return $this->CI->security->strip_image_tags($str);
    }

    // --------------------------------------------------------------------

    /**
     * Convert PHP tags to entities
     *
     * @param    string
     *
     * @return    string
     */
    public function encode_php_tags($str)
    {
        return str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $str);
    }
}