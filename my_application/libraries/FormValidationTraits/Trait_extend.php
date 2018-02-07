<?php

Trait Trait_extend
{
    /**
     * 设置默认值
     *
     * @param $str
     * @param $default
     *
     * @return mixed
     */
    public function default_value($str, $default)
    {
        if (!isset($str) || $str === '') {
            $str = $default;
        }
        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * 可以没有此字段，但是不能传空字符串和null
     *
     * @param $str
     *
     * @return bool
     */
    public function not_empty_str($str)
    {
        return is_string($str) && $str !== '';
    }

    // --------------------------------------------------------------------

    /**
     * 可以没有此字段，但是不能传空字符串和null
     *
     * @param $arr
     *
     * @return bool
     */
    public function not_empty_array($arr)
    {
        return is_array($arr) && !empty($arr);
    }

    // --------------------------------------------------------------------

    /**
     * 不能同时为空 用法：least_one_required[field_name]
     *
     * @param string $str
     * @param array  $indexes
     * @param string $field
     *
     * @return bool
     */
    public function least_one_required($str, array $indexes, $field)
    {
        $subject = $this->parse_field_str($field);
        $subject = $this->replace_uncertain_index($subject, $indexes);
        $value = $this->get_validation_data_element($subject);

        return $this->required($str) || $this->required($value);
    }

    /**
     * 验证身份证
     *
     * @param $str
     *
     * @return bool
     */
    public function valid_card($str)
    {
        if (!preg_match('/^\d{15}(?:\d{2}(?:\d|X|x))?$/', $str)) {
            return false;
        }

        //----------------------对十八位身份证做校验-----------------------
        if (strlen($str) == 18) {
            $birth = substr($str, 6, 8);
            if (date('Ymd', strtotime($birth)) != $birth) {
                return false;
            }

            $w = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
            $c = [1, 0, 'X', 9, 8, 7, 6, 5, 4, 3, 2];

            $sum = 0;
            foreach ($w as $key => $value) {
                $sum += $value * $str{$key};
            }

            $r = $sum % 11;
            $res = $c[$r];

            if ($res != strtoupper($str{17})) {
                return false;
            }
        } else {
            $birth = substr($str, 6, 6);
            if (date('ymd', strtotime('19' . $birth)) != $birth) {
                return false;
            }
        }

        return true;
    }

    /**
     * 验证用户帐号类型正确，现在为手机号和邮箱
     *
     * @param $str
     *
     * @return string
     * @throws Exception
     */
    public function valid_username($str)
    {
        return filter_var($str, FILTER_VALIDATE_EMAIL) ? true : $this->valid_phone($str);
    }

    /**
     * 验证手机号
     *
     * @param $str
     *
     * @return bool
     */
    public function valid_phone($str)
    {
        if (strlen($str) != 11) {
            return false;
        }

        return ($str > 10000000000 && $str < 20000000000) ? true : false;
    }

    /**
     * 验证电话号码
     *
     * @param $str
     *
     * @return bool
     */
    public function valid_tel($str)
    {
        return preg_match("/^\d+(?:\-\d+)*$/ix", $str) ? true : false;
    }

    /**
     * 验证是MD5加密过的字符串
     *
     * @param     $str
     * @param int $length
     *
     * @return bool
     */
    public function valid_md5($str, $length = null)
    {
        empty($length) && $length = 32;

        //return (strlen($str) != 32) ? false : true;
        return preg_match('/^[a-fA-F0-9]{' . $length . '}$/', $str) ? true : false;
    }

    /**
     * 判断字符长度，中文2个字节，英文1个字节
     *
     * @param $str
     * @param $val
     *
     * @return bool
     */
    public function max_length_gbk($str, $val)
    {
        if (preg_match("/[^0-9]/", $val)) {
            return false;
        }

        if (function_exists('mb_strlen')) {
            //return (mb_strlen($str, 'gb2312') > $val) ? FALSE : TRUE; //效率太低，下面的方式比它快4到5倍
            return (((strlen($str) + mb_strlen($str, 'UTF-8')) / 2) > $val) ? false : true;
        }

        return (strlen($str) > $val) ? false : true;
    }

    /**
     * 判断字符长度，中文2个字节，英文1个字节
     *
     * @param $str
     * @param $val
     *
     * @return bool
     */
    public function min_length_gbk($str, $val)
    {
        if (preg_match("/[^0-9]/", $val)) {
            return false;
        }

        if (function_exists('mb_strlen')) {
            //return (mb_strlen($str, 'gb2312') < $val) ? FALSE : TRUE; //效率太低，下面的方式比它快4到5倍
            return (((strlen($str) + mb_strlen($str, 'UTF-8')) / 2) < $val) ? false : true;
        }

        return (strlen($str) < $val) ? false : true;
    }

    /**
     * 时间晚于某个字段
     *
     * @param string $end_date
     * @param array  $indexes
     * @param string $start_date_field
     *
     * @return bool
     */
    public function date_later_than($end_date, array $indexes, $start_date_field)
    {
        $start = strtotime($start_date_field);
        if ($start === false) {
            $subject = $this->parse_field_str($start_date_field);
            $subject = $this->replace_uncertain_index($subject, $indexes);
            $start_date = $this->get_validation_data_element($subject);

            $start_date = strtr(
                $start_date,
                [
                    '/' => '-',
                    '.' => '-',
                    '年' => '-',
                    '月' => '-',
                    '日' => '',
                ]
            );

            $start_date = trim($start_date, '-');

            $start = strtotime($start_date);
            if ($start === false) {
                return false;
            }
        }


        $end_date = strtr(
            $end_date,
            [
                '/' => '-',
                '.' => '-',
                '年' => '-',
                '月' => '-',
                '日' => '',
            ]
        );
        $end_date = trim($end_date, '-');

        $end = strtotime($end_date);

        if ($end === false || $end < $start) {
            return false;
        }

        return true;
    }

    /**
     * 时间早某个字段
     *
     * @param string $start_date
     * @param array  $indexes
     * @param string $end_date_field
     *
     * @return bool
     */
    public function date_before_than($start_date, array $indexes, $end_date_field)
    {
        $end = strtotime($end_date_field);
        if ($end === false) {
            $subject = $this->parse_field_str($end_date_field);
            $subject = $this->replace_uncertain_index($subject, $indexes);
            $end_date = $this->get_validation_data_element($subject);

            $end_date = strtr(
                $end_date,
                [
                    '/' => '-',
                    '.' => '-',
                    '年' => '-',
                    '月' => '-',
                    '日' => '',
                ]
            );

            $end_date = trim($end_date, '-');

            $end = strtotime($end_date);
            if ($end === false) {
                return false;
            }
        }


        $start_date = strtr(
            $start_date,
            [
                '/' => '-',
                '.' => '-',
                '年' => '-',
                '月' => '-',
                '日' => '',
            ]
        );
        $start_date = trim($start_date, '-');

        $start = strtotime($start_date);

        if ($start === false || $start > $end) {
            return false;
        }

        return true;
    }

    /**
     * 验证合法的日期
     *
     * @param      $str
     * @param int  $flag
     *
     * @return bool
     */
    public function valid_date($str, $flag = 0)
    {
        $str = strtr(
            $str,
            [
                '/' => '-',
                '.' => '-',
                '年' => '-',
                '月' => '-',
                '日' => '',
            ]
        );

        $str = trim($str, '-');

        $time = strtotime($str);
        if ($time === false) {
            return false;
        }

        if ($flag > 0) {
            return $time >= time();
        } elseif ($flag < 0) {
            return $time <= time();
        } else {
            return true;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Prep URL
     *
     * @param    string
     *
     * @return    string
     */
    public function prep_url_can_no_scheme($str = '')
    {
        if ($str === 'http://' OR $str === '' OR $str === '//') {
            return '';
        }

        if (strpos($str, '//') !== 0 && strpos($str, 'http://') !== 0 && strpos($str, 'https://') !== 0) {
            return '//' . ltrim($str, ':/');
        }

        return $str;
    }



    // --------------------------------------------------------------------

    /**
     * 关联其他字段验证 用法：match_then_rule_other_filed[值, 要验证的字段, 验证规则, 验证规则参数1, 验证规则参数2...]
     *
     * @param string $str
     * @param array  $indexes
     * @param string $param
     *
     * @return bool
     */
    public function match_then_rule_other_filed($str, array $indexes, $param)
    {
        $arr = explode(',', $param, 4);
        if (count($arr) == 3) {
            list($value, $field_name, $rule) = $arr;
            $rule_args = null;
        } elseif (count($arr) == 4) {
            list($value, $field_name, $rule, $rule_args) = $arr;
        } else {
            return false;
        }

        if ($value != $str) {
            return true;
        }

        $subject = $this->parse_field_str($field_name);
        $subject = $this->replace_uncertain_index($subject, $indexes);
        $field_value = $this->get_validation_data_element($subject);

        if (method_exists($this, $rule)) {
            $res = call_user_func([$this, $rule], $field_value, $rule_args);
        } else {
            $res = call_user_func($rule, $field_value, $rule_args);
        }

        if ($res === false) {
            $line = $this->_get_error_message($rule, $field_name);

            // Build the error message
            $message = $this->_build_error_msg($line, $this->_translate_fieldname($this->_field_data[$field_name]['label']), $rule_args);

            if (!isset($this->_error_array[$field_name])) {
                $this->_error_array[$field_name] = $message;
            }
        }
        return true;
    }

}