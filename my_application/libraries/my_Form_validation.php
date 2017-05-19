<?php


class my_Form_validation extends CI_Form_validation
{
    function __construct($rules = [])
    {
        log_message('debug', 'Toc_Form_validation Initialized');
        parent::__construct($rules);
    }

    /**
     * Set Rules 将$_FILES里的内容放到 $_POST中
     * This function takes an array of field names and validations
     * rules as input, any custom error messages, validates the info,
     * and stores it
     *
     * @param    mixed  $field
     * @param    string $label
     * @param    mixed  $rules
     * @param    array  $errors
     *
     * @return    CI_Form_validation
     */
    public function set_rules($field, $label = '', $rules = [], $errors = [])
    {
        //-----------------------如果有文件上传，把$_FILES和$_POST合并------------------------
        if (isset($_FILES) && count($_FILES) > 0) {
            $_POST = array_merge($_POST, $this->restructure_files($_FILES));
            unset($_FILES);
        }

        // No reason to set rules if we have no POST data
        // or a validation array has not been specified
        if ($this->CI->input->method() !== 'post' && empty($this->validation_data)) {
            return $this;
        }

        // If an array was passed via the first parameter instead of individual string
        // values we cycle through it and recursively call this function.
        if (is_array($field)) {
            foreach ($field as $row) {
                // Houston, we have a problem...
                if (!isset($row['field'], $row['rules'])) {
                    continue;
                }

                // If the field label wasn't passed we use the field name
                $label = isset($row['label']) ? $row['label'] : $row['field'];

                // Add the custom error message array
                $errors = (isset($row['errors']) && is_array($row['errors'])) ? $row['errors'] : [];

                // Here we go!
                $this->set_rules($row['field'], $label, $row['rules'], $errors);
            }

            return $this;
        }

        // No fields or no rules? Nothing to do...
        if (!is_string($field) OR $field === '' OR empty($rules)) {
            return $this;
        } elseif (!is_array($rules)) {
            // BC: Convert pipe-separated rules string to an array
            if (!is_string($rules)) {
                return $this;
            }

            $rules = preg_split('/\|(?![^\[]*\])/', $rules);
        }

        // If the field label wasn't passed we use the field name
        $label = ($label === '') ? $field : $label;

        $indexes = [];
        //----------------增加一个表示是否为索引数组的字段，它的值表示索引数组所在的维度-----------------
        $index_array_deep = false;

        // Is the field name an array? If it is an array, we break it apart
        // into its components so that we can fetch the corresponding POST data later
        if (($is_array = (bool)preg_match_all('/\[(.*?)\]/', $field, $matches)) === true) {
            sscanf($field, '%[^[][', $indexes[0]);

            for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
                if ($matches[1][$i] !== '') {
                    $indexes[] = $matches[1][$i];
                } else {
                    $index_array_deep = $i + 1;
                }
            }
        }

        // Build our master array
        $this->_field_data[$field] = [
            'field' => $field,
            'label' => $label,
            'rules' => $rules,
            'errors' => $errors,
            'is_array' => $is_array,
            'keys' => $indexes,
            'index_array_deep' => $index_array_deep,
            'postdata' => null,
            'error' => '',
        ];

        return $this;
    }

    /**
     * 用来变换$_FILES的数组格式
     *
     * @param array $input
     *
     * @return array
     */
    private function restructure_files(array $input)
    {
        $output = [];
        foreach ($input as $name => $array) {
            foreach ($array as $field => $value) {
                $pointer = &$output[$name];
                if (!is_array($value)) {
                    $pointer[$field] = $value;
                    continue;
                }
                $stack = [&$pointer];
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveArrayIterator($value),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $k => $v) {
                    array_splice($stack, $iterator->getDepth() + 1);
                    $pointer = &$stack[count($stack) - 1];
                    $pointer = &$pointer[$k];
                    $stack[] = &$pointer;
                    if (!$iterator->hasChildren()) {
                        $pointer[$field] = $v;
                    }
                }
            }
        }
        return $output;
    }

    /**
     * Run the Validator 将$_FILES里的内容放到 $_POST中
     * This function does all the work.
     *
     * @param    string $group
     *
     * @return    bool
     */
    public function run($group = '')
    {
        //-----------------------如果有文件上传，把$_FILES和$_POST合并------------------------
        if (isset($_FILES) && count($_FILES) > 0) {
            $_POST = array_merge($_POST, $this->restructure_files($_FILES));
            unset($_FILES);
        }

        $validation_array = empty($this->validation_data) ? $_POST : $this->validation_data;

        // Does the _field_data array containing the validation rules exist?
        // If not, we look to see if they were assigned via a config file
        if (count($this->_field_data) === 0) {
            // No validation rules?  We're done...
            if (count($this->_config_rules) === 0) {
                return false;
            }

            if (empty($group)) {
                // Is there a validation rule for the particular URI being accessed?
                $group = trim($this->CI->uri->ruri_string(), '/');
                isset($this->_config_rules[$group]) OR
                $group = $this->CI->router->class . '/' . $this->CI->router->method;
            }

            $this->set_rules(isset($this->_config_rules[$group]) ? $this->_config_rules[$group] : $this->_config_rules);

            // Were we able to set the rules correctly?
            if (count($this->_field_data) === 0) {
                log_message('debug', 'Unable to find validation rules');

                return false;
            }
        }

        // Load the language file containing error messages
        $this->CI->lang->load('form_validation');

        // Cycle through the rules for each field and match the corresponding $validation_data item
        foreach ($this->_field_data as $field => &$row) {
            // Fetch the data from the validation_data array item and cache it in the _field_data array.
            // Depending on whether the field name is an array or a string will determine where we get it from.
            if ($row['is_array'] === true) {
                //-------------------------此处修改为自定义的获取字段值函数--------------------------
                $this->_field_data[$field]['postdata'] = $this->_my_reduce_array(
                    $validation_array,
                    $row['keys'],
                    0,
                    $row['index_array_deep']
                );
            } elseif (isset($validation_array[$field])) {
                $this->_field_data[$field]['postdata'] = is_array($validation_array[$field]) ? false : $validation_array[$field];
            } else {
                $this->_field_data[$field]['postdata'] = null;
            }
            //var_dump($this->_field_data[$field]['postdata']);exit;
        }
        // Execute validation rules
        // Note: A second foreach (for now) is required in order to avoid false-positives
        //	 for rules like 'matches', which correlate to other validation fields.
        foreach ($this->_field_data as $field => &$row) {
            // Don't try to validate if we have no rules set
            if (empty($row['rules'])) {
                continue;
            }

            $this->_execute($row, $row['rules'], $row['postdata']);
        }

        // Did we end up with any errors?
        $total_errors = count($this->_error_array);
        if ($total_errors > 0) {
            $this->_safe_form_data = true;
        }

        // Now we need to re-set the POST data with the new, processed data
        empty($this->validation_data) ? $this->_reset_post_array() : $this->_reset_validation_data();

        return ($total_errors === 0);
    }

    // --------------------------------------------------------------------

    /**
     * Prepare rules
     * Re-orders the provided rules in order of importance, so that
     * they can easily be executed later without weird checks ...
     * "Callbacks" are given the highest priority (always called),
     * followed by 'required' (called if callbacks didn't fail),
     * and then every next rule depends on the previous one passing.
     *
     * @param    array $rules
     *
     * @return    array
     */
    protected function _prepare_rules($rules)
    {
        $new_rules = [];
        $callbacks = [];

        foreach ($rules as &$rule) {
            // Let 'required' always be the first (non-callback) rule
            if ($rule === 'required') {
                array_unshift($new_rules, 'required');
            } // 'isset' is a kind of a weird alias for 'required' ...
            elseif ($rule === 'isset' && (empty($new_rules) OR $new_rules[0] !== 'required')) {
                array_unshift($new_rules, 'isset');
            } elseif ($rule === 'not_empty_str' && (empty($new_rules) OR $new_rules[0] !== 'required')) {
                array_unshift($new_rules, 'not_empty_str');
            } // The old/classic 'callback_'-prefixed rules
            elseif (is_string($rule) && strncmp('callback_', $rule, 9) === 0) {
                $callbacks[] = $rule;
            } // Proper callables
            elseif (is_callable($rule)) {
                $callbacks[] = $rule;
            } // "Named" callables; i.e. array('name' => $callable)
            elseif (is_array($rule) && isset($rule[0], $rule[1]) && is_callable($rule[1])) {
                $callbacks[] = $rule;
            } // Everything else goes at the end of the queue
            else {
                $new_rules[] = $rule;
            }
        }

        return array_merge($callbacks, $new_rules);
    }

    // --------------------------------------------------------------------

    /**
     * Executes the Validation routines
     *
     * @param    array
     * @param    array
     * @param    mixed
     * @param    int
     *
     * @return    mixed
     */
    protected function _execute($row, $rules, $postdata = null, $cycles = 0)
    {
        // If the $_POST data is an array we will run a recursive call
        //
        // Note: We MUST check if the array is empty or not!
        //       Otherwise empty arrays will always pass validation.
        if (is_array($postdata) && !empty($postdata)) {
            foreach ($postdata as $key => $val) {
                $this->_execute($row, $rules, $val, $key);
            }

            return;
        }

        $rules = $this->_prepare_rules($rules);
        foreach ($rules as $rule) {
            $_in_array = false;

            // We set the $postdata variable with the current data in our master array so that
            // each cycle of the loop is dealing with the processed data from the last cycle
            if ($row['is_array'] === true && is_array($this->_field_data[$row['field']]['postdata'])) {
                // We shouldn't need this safety, but just in case there isn't an array index
                // associated with this cycle we'll bail out
                if (!isset($this->_field_data[$row['field']]['postdata'][$cycles])) {
                    continue;
                }

                $postdata = $this->_field_data[$row['field']]['postdata'][$cycles];
                $_in_array = true;
            } else {
                // If we get an array field, but it's not expected - then it is most likely
                // somebody messing with the form on the client side, so we'll just consider
                // it an empty field
                if (is_array($this->_field_data[$row['field']]['postdata'])) {
                    $postdata = false;
                } else {
                    $postdata = $this->_field_data[$row['field']]['postdata'];
                }
            }

            // Is the rule a callback?
            $callback = $callable = false;
            if (is_string($rule)) {
                if (strpos($rule, 'callback_') === 0) {
                    $rule = substr($rule, 9);
                    $callback = true;
                }
            } elseif (is_callable($rule)) {
                $callable = true;
            } elseif (is_array($rule) && isset($rule[0], $rule[1]) && is_callable($rule[1])) {
                // We have a "named" callable, so save the name
                $callable = $rule[0];
                $rule = $rule[1];
            }

            // Strip the parameter (if exists) from the rule
            // Rules can contain a parameter: max_length[5]
            $param = false;
            if (!$callable && preg_match('/(.*?)\[(.*)\]/', $rule, $match)) {
                $rule = $match[1];
                $param = $match[2];
            }

            // Ignore empty, non-required inputs with a few exceptions ...
            if (
                ($postdata === null OR $postdata === '')
                && $callback === false
                && $callable === false
                && !in_array($rule, ['required', 'isset', 'matches', 'not_empty_str', 'default_value'], true)
            ) {
                continue;
            }

            if ($postdata === false) {
                $rule = 'not_false';
            }

            // Call the function that corresponds to the rule
            if ($callback OR $callable !== false) {
                if ($callback) {
                    if (!method_exists($this->CI, $rule)) {
                        log_message('debug', 'Unable to find callback validation rule: ' . $rule);
                        $result = false;
                    } else {
                        // Run the function and grab the result
                        $result = $this->CI->$rule($postdata, $param);
                    }
                } else {
                    $result = is_array($rule)
                        ? $rule[0]->{$rule[1]}($postdata)
                        : $rule($postdata);

                    // Is $callable set to a rule name?
                    if ($callable !== false) {
                        $rule = $callable;
                    }
                }

                // Re-assign the result to the master data array
                if ($_in_array === true) {
                    $this->_field_data[$row['field']]['postdata'][$cycles] = is_bool($result) ? $postdata : $result;
                } else {
                    $this->_field_data[$row['field']]['postdata'] = is_bool($result) ? $postdata : $result;
                }
            } elseif (!method_exists($this, $rule)) {
                // If our own wrapper function doesn't exist we see if a native PHP function does.
                // Users can use any native PHP function call that has one param.
                if (function_exists($rule)) {
                    // Native PHP functions issue warnings if you pass them more parameters than they use
                    $result = ($param !== false) ? $rule($postdata, $param) : $rule($postdata);

                    if ($_in_array === true) {
                        $this->_field_data[$row['field']]['postdata'][$cycles] = is_bool($result) ? $postdata : $result;
                    } else {
                        $this->_field_data[$row['field']]['postdata'] = is_bool($result) ? $postdata : $result;
                    }
                } else {
                    log_message('debug', 'Unable to find validation rule: ' . $rule);
                    $result = false;
                }
            } else {
                $result = $this->$rule($postdata, $param);

                if ($_in_array === true) {
                    $this->_field_data[$row['field']]['postdata'][$cycles] = is_bool($result) ? $postdata : $result;
                } else {
                    $this->_field_data[$row['field']]['postdata'] = is_bool($result) ? $postdata : $result;
                }
            }

            // Did the rule test negatively? If so, grab the error.
            if ($result === false) {
                // Callable rules might not have named error messages
                if (!is_string($rule)) {
                    $line = $this->CI->lang->line('form_validation_error_message_not_set') . '(Anonymous function)';
                } else {
                    $line = $this->_get_error_message($rule, $row['field']);
                }

                // Is the parameter we are inserting into the error message the name
                // of another field? If so we need to grab its "field label"
                if (isset($this->_field_data[$param], $this->_field_data[$param]['label'])) {
                    $param = $this->_translate_fieldname($this->_field_data[$param]['label']);
                }

                // Build the error message
                $message = $this->_build_error_msg($line, $this->_translate_fieldname($row['label']), $param);

                // Save the error message
                $this->_field_data[$row['field']]['error'] = $message;

                if (!isset($this->_error_array[$row['field']])) {
                    $this->_error_array[$row['field']] = $message;
                }

                return;
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Traverse a multidimensional $_POST array index until the data is found
     *
     * @param     $array
     * @param     $keys
     * @param int $i
     * @param     $index_array_deep
     *
     * @return array|null
     */
    protected function _my_reduce_array($array, $keys, $i = 0, $index_array_deep)
    {
        if (is_array($array)) {
            //var_dump($array, $keys, $i);echo '===';
            if (isset($keys[$i], $array[$keys[$i]])) {
                return $this->_my_reduce_array($array[$keys[$i]], $keys, ($i + 1), $index_array_deep);
            }

            if ($index_array_deep === $i && $this->is_one_dimensional_array($array)) {
                return $array;
            }

            if (isset($keys[$i]) && !isset($array[$keys[$i]])) {
                return null;
            }
        } else {
            // NULL must be returned for empty fields

            if (!$index_array_deep && !isset($keys[$i])) {
                return $array;
            }
        }
        return false;
    }

    /**
     * 判断数组是一维数组，取$_POST的值时候用到
     *
     * @param $array
     *
     * @return bool
     */
    protected function is_one_dimensional_array($array)
    {
        foreach ($array as $value) {
            if (is_array($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Re-populate the _POST array with our finalized and processed data
     *
     * @return    void
     */
    protected function _reset_post_array()
    {
        foreach ($this->_field_data as $field => $row) {
            //----------------------此处把获取的值赋值会$_POST,修改为获取的为null也赋值回去------------------------
            if ($row['postdata'] !== null && $row['postdata'] !== false) {
                if ($row['is_array'] === false) {
                    isset($_POST[$field]) && $_POST[$field] = $row['postdata'];
                } else {
                    // start with a reference
                    //

                    // before we assign values, make a reference to the right POST key
                    if (count($row['keys']) === 1) {
                        $_POST[current($row['keys'])] = $row['postdata'];
                    } else {
                        $post_ref =& $_POST;
                        foreach ($row['keys'] as $val) {
                            //---------------------字段下标不存在时创建下标-------------------------
                            //is_array($post_ref) || $post_ref = [];
                            //isset($post_ref[$val]) || $post_ref[$val] = null;
                            $post_ref =& $post_ref[$val];
                        }
                        $post_ref = $row['postdata'];
                    }
                }
            }
        }
    }


    // --------------------------------------------------------------------

    /**
     * By default, form validation uses the $_POST array to validate
     * If an array is set through this method, then this array will
     * be used instead of the $_POST array
     * Note that if you are validating multiple arrays, then the
     * reset_validation() function should be called after validating
     * each array due to the limitations of CI's singleton
     *
     * @param    array $data
     *
     * @return    CI_Form_validation
     */
    public function set_validation_data(array &$data)
    {
        if (!empty($data)) {
            $this->validation_data = &$data;
        }

        return $this;
    }

    // --------------------------------------------------------------------


    protected function _reset_validation_data()
    {
        foreach ($this->_field_data as $field => $row) {
            //----------------------此处把获取的值赋值会$_POST,修改为获取的为null也赋值回去------------------------
            if ($row['postdata'] !== null && $row['postdata'] !== false) {
                if ($row['is_array'] === false) {
                    isset($this->validation_data[$field]) && $this->validation_data[$field] = $row['postdata'];
                } else {
                    // start with a reference
                    //

                    // before we assign values, make a reference to the right POST key
                    if (count($row['keys']) === 1) {
                        $this->validation_data[current($row['keys'])] = $row['postdata'];
                    } else {
                        $post_ref =& $this->validation_data;
                        foreach ($row['keys'] as $val) {
                            //---------------------字段下标不存在时创建下标-------------------------
                            //is_array($post_ref) || $post_ref = [];
                            //isset($post_ref[$val]) || $post_ref[$val] = null;
                            $post_ref =& $post_ref[$val];
                        }
                        $post_ref = $row['postdata'];
                    }
                }
            }
        }
        unset($this->validation_data);
        $this->validation_data = [];
    }

    protected function not_false($value)
    {
        return $value !== false;
    }

    public function file_upload_error($error, &$param)
    {
        switch ($error) {
            case UPLOAD_ERR_OK:
                return true;
            case UPLOAD_ERR_INI_SIZE :
                $param = '上传文件大小超过php.ini中的限制';//The uploaded file exceeds the upload_max_filesize directive in php.ini
                break;
            case UPLOAD_ERR_FORM_SIZE :
                $param = '上传文件大小超过HTML表单指定MAX_FILE_SIZE的限制';//The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form
                break;
            case UPLOAD_ERR_PARTIAL :
                $param = '上传文件只完成了一部分';//The uploaded file was only partially uploaded
                break;
            case UPLOAD_ERR_NO_FILE :
                $param = '没有文件被上传';
                break;
            case UPLOAD_ERR_NO_TMP_DIR :
                $param = '没有临时文件夹';//Missing a temporary folder
                break;
            case UPLOAD_ERR_CANT_WRITE :
                $param = '文件未写入磁盘';//Failed to write file to disk
                break;
            case UPLOAD_ERR_EXTENSION :
                $param = '文件上传被中止';//File upload stopped by extension
                break;
            default :
                $param = '未知错误';//'Unknown upload error'
        }

        return false;
    }

    /**
     * 文件最大大小
     *
     * @param $size
     * @param $max_size
     *
     * @return bool
     */
    public function file_size_max($size, $max_size)
    {
        $max_size_bit = $this->let_to_bit($max_size);

        return $size > $max_size_bit ? false : true;
    }

    /**
     * 文件最小大小
     *
     * @param $size
     * @param $min_size
     *
     * @return bool
     */
    public function file_size_min($size, $min_size)
    {
        $min_size_bit = $this->let_to_bit($min_size);

        return $size < $min_size_bit ? false : true;
    }

    /**
     * 检查文件上传类型
     *
     * @param $name
     * @param $type
     *
     * @return bool
     */
    public function file_allowed_type($name, &$type)
    {
        // get file ext
        $file_ext = strtolower(trim(strrchr($name, '.'), '.'));

        // is type of format a,b,c,d? -> convert to array
        $ext_arr = explode(',', $type);

        $type = '';
        $ext_groups = [
            'image' => [
                'jpg',
                'jpeg',
                'gif',
                'png',
            ],
            'application' => [
                'exe',
                'dll',
                'so',
                'cgi',
            ],
            'php_code' => [
                'php',
                'php4',
                'php5',
                'inc',
                'phtml',
            ],
            'word_document' => [
                'rtf',
                'doc',
                'docx',
            ],
            'compressed' => [
                'zip',
                'gzip',
                'tar',
                'gz',
            ],
            'text' => [
                'csv',
                'txt',
            ],
        ];


        // is $type array? run self recursively
        foreach ($ext_arr as $ext) {
            // is type a group type? image, application, word_document, code, zip .... -> load proper array
            isset($ext_groups[$ext]) && $ext = $ext_groups[$ext];

            if (is_array($ext)) {
                if (in_array($file_ext, $ext)) {
                    return true;
                }
                $type .= ',.' . implode(',.', $ext);
            } else {
                if ($file_ext == $ext) {
                    return true;
                }
                $type .= ',.' . $ext;
            }
        }
        $type = trim($type, ',');

        return false;
    }

    public function valid_image($tmp_name, $name)
    {
        $mime = getimagesize($tmp_name);
        if ($mime) {
            $ext = image_type_to_extension($mime[2]);
            $this->_field_data[$name]['postdata'] .= $ext;

            return true;
        }

        return false;
    }

    public function file_disallowed_type($file, &$type)
    {
        $rc = $this->file_allowed_type($file, $type);

        return !$rc;
    }

    /**
     * given an string in format of ###AA converts to number of bits it is assignin
     *
     * @param string $sValue
     *
     * @return integer number of bits
     */
    protected function let_to_bit($sValue)
    {
        // Split value from name
        if (!preg_match('/([0-9]+)([ptgmkb]{1,2}|)/ui', $sValue, $aMatches)) { // Invalid input
            return false;
        }

        if (empty ($aMatches [2])) { // No name -> Enter default value
            $aMatches [2] = 'KB';
        }

        if (strlen($aMatches [2]) == 1) { // Shorted name -> full name
            $aMatches [2] .= 'B';
        }

        $iBit = (substr($aMatches [2], -1) == 'B') ? 1024 : 1000;
        // Calculate bits:

        switch (strtoupper(substr($aMatches [2], 0, 1))) {
            case 'P' :
                $aMatches [1] *= $iBit;
            case 'T' :
                $aMatches [1] *= $iBit;
            case 'G' :
                $aMatches [1] *= $iBit;
            case 'M' :
                $aMatches [1] *= $iBit;
            case 'K' :
                $aMatches [1] *= $iBit;
                break;
        }

        // Return the value in bits
        return $aMatches [1];
    }

    /**
     * returns false if image is bigger than the dimensions given
     *
     * @param $file
     * @param $param
     *
     * @return bool
     */
    public function image_pixel_max($file, &$param)
    {
        $limit = explode(',', $param);

        if (count($limit) !== 2) {
            return false;
        }
        $param = $limit[0] . '×' . $limit[1];

        $d = @getimagesize($file);
        if (!$d) {
            return false;
        }
        if ($d[0] > $limit[0] || $d[1] > $limit[1]) {
            return false;
        }

        return true;
    }

    /**
     * returns false is the image is smaller than given dimension
     *
     * @param $file
     * @param $param
     *
     * @return bool
     */
    public function image_pixel_min($file, &$param)
    {
        $limit = explode(',', $param);

        if (count($limit) !== 2) {
            return false;
        }
        $param = $limit[0] . '×' . $limit[1];

        $d = @getimagesize($file);
        if (!$d) {
            return false;
        }

        if ($d[0] < $limit[0] || $d[1] < $limit[1]) {
            return false;
        }

        return true;
    }

    /**
     * 关联其他字段验证 用法：relate_other_field[关联字段名，关联字段默认值，验证规则，验证规则参数[，验证规则参数[，验证规则参数]]]
     *
     * @param $str
     * @param $param
     *
     * @return bool
     */
    public function relate_other_field($str, $param)
    {
        $arr = explode(',', $param, 4);
        $other_field = $arr[0];
        $default_value = $arr[1];
        $func = $arr[2];
        $rule = isset($arr[3]) ? $arr[3] : null;

        $value = $this->_field_data[$other_field]['postdata'];
        if ($value == $default_value) {
            return true;
        }

        if (method_exists($this, $func)) {
            if (isset($rule)) {
                return $this->{$func}($str, $rule);
            } else {
                return $this->{$func}($str);
            }
        } else {
            if (isset($rule)) {
                return $func($str, $rule);
            } else {
                return $func($str);
            }
        }
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
     * 时间大于某个字段
     *
     * @param $end_str
     * @param $start_str
     *
     * @return bool
     */
    public function date_later_than($end_str, $start_str)
    {
        $start = strtotime($start_str);
        if ($start === false) {
            $start_str = $this->_field_data[$start_str]['postdata'];
            $start_str = strtr(
                $start_str,
                [
                    '/' => '-',
                    '.' => '-',
                    '年' => '-',
                    '月' => '-',
                    '日' => '',
                ]
            );

            $start_str = trim($start_str, '-');

            $start = strtotime($start_str);
        }


        $end_str = strtr(
            $end_str,
            [
                '/' => '-',
                '.' => '-',
                '年' => '-',
                '月' => '-',
                '日' => '',
            ]
        );
        $end_str = trim($end_str, '-');

        $end = strtotime($end_str);

        if ($end <= $start) {
            return false;
        }

        return true;
    }

    /**
     * 验证合法的日期
     *
     * @param      $str
     * @param bool $future
     *
     * @return bool
     */
    public function valid_date($str, $future = false)
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

        if ($future) {
            return $time < time() ? false : true;
        } else {
            return $time > time() ? false : true;
        }
    }

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
        if (!isset($str) || $str == '') {
            $str = $default;
        }
        return $str;
    }

    // --------------------------------------------------------------------


    public function not_empty_str($str)
    {
        if (!isset($str) || $str != '') {
            return true;
        }
        return false;
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

        if (strpos($str, 'http://') !== 0 && strpos($str, 'https://') !== 0) {
            return 'http://' . ltrim($str, ':/');
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Reset validation vars
     * Prevents subsequent validation routines from being affected by the
     * results of any previous validation routine due to the CI singleton.
     *
     * @return    CI_Form_validation
     */
    public function reset_error()
    {
        $this->_error_array = [];
        $this->_error_messages = [];
        $this->error_string = '';
        return $this;
    }

    public function reset_validation()
    {
        $this->validation_data = [];
        $this->_field_data = [];
        $this->_error_array = [];
        $this->_error_messages = [];
        $this->error_string = '';
        return $this;
    }
}
