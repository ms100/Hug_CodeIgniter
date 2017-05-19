<?php
/**
 * 过滤表情符号
 *
 * @param      $str
 * @param null $replace
 *
 * @return mixed|string
 */
if (!function_exists('filter_emoji')) {
    function filter_emoji(&$str, $replace = null)
    {
        static $map;
        if (!isset($map)) {
            $CI =& get_instance();
            if ($CI->config->load('emoji', true, true)) {
                $map = $CI->config->config['emoji'];
            } else {
                $map = [];
            }
        }


        if (!empty($map) && !empty($str)) {
            $replace = empty($replace) ? '' : strtr(urlencode($replace), ['%' => '\x']);

            $str = strtr(urlencode($str), ['%' => '\x']);
            $str = str_ireplace($map, $replace, $str);
            $str = strtr($str, ['\x' => '%']);

            $str = urldecode($str);
        }

        return $str;
    }
}
