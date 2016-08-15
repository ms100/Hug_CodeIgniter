<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2016, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the 'Software'), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package    CodeIgniter
 * @author    EllisLab Dev Team
 * @copyright    Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright    Copyright (c) 2014 - 2016, British Columbia Institute of Technology (http://bcit.ca/)
 * @license    http://opensource.org/licenses/MIT	MIT License
 * @link    https://codeigniter.com
 * @since    Version 1.0.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

$lang['form_validation_required'] = '{field}必填';
$lang['form_validation_isset'] = '{field}必填';
$lang['form_validation_valid_email'] = '{field}必须为有效的邮箱地址';
$lang['form_validation_valid_emails'] = '{field}必须为有效的邮箱地址';
$lang['form_validation_valid_url'] = '{field}必须为有效的超链接';
$lang['form_validation_valid_ip'] = '{field}必须为有效的IP地址';
$lang['form_validation_min_length'] = '{field}长度不少于{param}个字';
$lang['form_validation_max_length'] = '{field}长度不超过{param}个字';
$lang['form_validation_exact_length'] = '{field}长度必须是{param}个字';
$lang['form_validation_alpha'] = '{field}必须是英文字母';
$lang['form_validation_alpha_numeric'] = '{field}必须是字母或数字';
$lang['form_validation_alpha_numeric_spaces'] = '{field}必须是字母/数字/空格';
$lang['form_validation_alpha_dash'] = '{field}必须是字母/数字/下划线/破折号';
$lang['form_validation_numeric'] = '{field}必须是数字';
$lang['form_validation_is_numeric'] = '{field}必须是数字';
$lang['form_validation_integer'] = '{field}必须是整数';
$lang['form_validation_regex_match'] = '{field}必须为正确的格式';
$lang['form_validation_matches'] = '{field}与{param}必须相同';
$lang['form_validation_differs'] = '{field}与{param}不能相同';
$lang['form_validation_is_unique'] = '{field}必须是唯一值';
$lang['form_validation_is_natural'] = '{field}必须是自然数';
$lang['form_validation_is_natural_no_zero'] = '{field}必须是正整数';
$lang['form_validation_decimal'] = '{field}必须是十进制数';
$lang['form_validation_less_than'] = '{field}必须小于{param}';
$lang['form_validation_less_than_equal_to'] = '{field}不能大于{param}';
$lang['form_validation_greater_than'] = '{field}必须大于{param}';
$lang['form_validation_greater_than_equal_to'] = '{field}不能小于{param}';
$lang['form_validation_error_message_not_set'] = '{field}输入格式错误';//'Unable to access an error message corresponding to your field name {field}'
$lang['form_validation_in_list'] = '{field}必须是{param}中的一个';

$lang['form_validation_min_length_gbk'] = '{field}长度不少于{param}个字符';
$lang['form_validation_max_length_gbk'] = '{field}长度不超过{param}个字符';
$lang['form_validation_valid_phone'] = '{field}必须是合法的手机号码';
$lang['form_validation_valid_md5'] = '{field}必须是MD5加密字符串';
$lang['form_validation_valid_card'] = '{field}必须是有效的身份证号';
$lang['form_validation_date_greater_than'] = '{field}必须晚于{param}';
$lang['form_validation_file_allowed_type'] = '{field}格式必须是{param}';
$lang['form_validation_file_upload_error'] = '{field}上传失败，{param}';
$lang['form_validation_file_size_max'] = '{field}大小不能超过{param}';
$lang['form_validation_file_size_min'] = '{field}大小不能小于{param}';
$lang['form_validation_image_pixel_max'] = '{field}不能超过{param}像素';
$lang['form_validation_image_pixel_min'] = '{field}不能小于{param}像素';
$lang['form_validation_valid_email_can_empty'] = '{field}不是有效的邮箱地址';
$lang['form_validation_regex_match_can_empty'] = '{field}格式不正确';
$lang['form_validation_relate_other_field'] = '{field}格式不正确';
$lang['form_validation_valid_image'] = '{field}必须是图片';