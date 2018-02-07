<?php

include __DIR__.'/FormValidationTraits/Trait_array.php';
include __DIR__.'/FormValidationTraits/Trait_ci.php';
include __DIR__.'/FormValidationTraits/Trait_extend.php';
include __DIR__.'/FormValidationTraits/Trait_file.php';

class CI_Form_validation
{
    use Trait_array;
    use Trait_ci;
    use Trait_extend;
    use Trait_file;
    /**
     * Reference to the CodeIgniter instance
     *
     * @var object
     */
    protected $CI;

    /**
     * Validation data for the current form submission
     *
     * @var array
     */
    protected $_field_data = [];

    /**
     * Validation rules for the current form
     *
     * @var array
     */
    protected $_config_rules = [];

    /**
     * Array of validation errors
     *
     * @var array
     */
    protected $_error_array = [];

    /**
     * Array of custom error messages
     *
     * @var array
     */
    protected $_error_messages = [];

    /**
     * Start tag for error wrapping
     *
     * @var string
     */
    protected $_error_prefix = '<p>';

    /**
     * End tag for error wrapping
     *
     * @var string
     */
    protected $_error_suffix = '</p>';

    /**
     * Custom data to validate
     *
     * @var array
     */
    protected $validation_data;

    /**
     * Initialize Form_Validation class
     *
     * @param    array $rules
     *
     * @return    void
     */
    public function __construct($rules = [])
    {
        $this->CI =& get_instance();

        // applies delimiters set in config file.
        if (isset($rules['error_prefix'])) {
            $this->_error_prefix = $rules['error_prefix'];
            unset($rules['error_prefix']);
        }
        if (isset($rules['error_suffix'])) {
            $this->_error_suffix = $rules['error_suffix'];
            unset($rules['error_suffix']);
        }

        // Validation rules can be stored in a config file.
        $this->_config_rules = $rules;

        // Automatically load the form helper
        $this->CI->load->helper('form');
        $this->CI->lang->load('form_validation');

        log_message('debug', 'Form Validation Class Initialized');
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
        /*if (!isset($this->validation_data) && isset($_FILES) && count($_FILES) > 0) {
            $_POST = array_merge($_POST, $this->restructure_files($_FILES));
            unset($_FILES);
        }*/


        // No reason to set rules if we have no POST data
        // or a validation array has not been specified
        /*if ($this->CI->input->method() !== 'post' && !isset($this->validation_data)) {
            return $this;
        }*/

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
                $this->set_one_rule($row['field'], $label, $row['rules'], $errors);
            }
        } else {
            $this->set_one_rule($field, $label, $rules, $errors);
        }

        return $this;
    }

    protected function set_one_rule($field, $label = '', $rules = [], $errors = [])
    {
        // No fields or no rules? Nothing to do...
        if (!is_string($field) || $field === '') {
            return;
        } elseif (!is_array($rules)) {
            // BC: Convert pipe-separated rules string to an array
            if (!is_string($rules) || empty($rules)) {
                $rules = [];
            } else {
                $rules = preg_split('/\|(?![^\[]*\])/', $rules);
            }
        }

        // If the field label wasn't passed we use the field name
        $label === '' && $label = $field;

        $field === '[]' && $field = '';

        $indexes = $this->parse_field_str($field);

        // Build our master array
        $this->_field_data[$field] = [
            'field' => $field,
            'label' => $label,
            'rules' => $rules,
            'errors' => $errors,
            'keys' => $indexes,
        ];
    }

    // --------------------------------------------------------------------

    /**
     * 用来变换$_FILES的数组格式
     *
     * @param array $input
     *
     * @return array
     */
    public function restructure_files(array $input)
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
        if (!isset($this->validation_data)) {
            if ($this->CI->input->method() !== 'post') {
                return false;
            }

            if (isset($_FILES) && count($_FILES) > 0) {
                $_POST = array_merge($_POST, $this->restructure_files($_FILES));
                unset($_FILES);
            }
            $this->validation_data = &$_POST;
        }

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
                isset($this->_config_rules[$group]) || $group = $this->CI->router->class . '/' . $this->CI->router->method;
            }

            isset($this->_config_rules[$group]) && $this->set_rules($this->_config_rules[$group]);

            // Were we able to set the rules correctly?
            if (count($this->_field_data) === 0) {
                log_message('debug', 'Unable to find validation rules');

                return false;
            }
        }

        $form = $this->_make_field_data_to_tree();

        $this->execute($form, $this->validation_data);

        unset($this->validation_data);
        $this->validation_data = null;

        return !count($this->_error_array);
    }

    // --------------------------------------------------------------------

    protected function _make_field_data_to_tree()
    {
        foreach ($this->_field_data as $field => $row) {
            while (count($row['keys']) > 1) {
                array_pop($row['keys']);
                $parent_field = vsprintf('%s' . str_repeat('[%s]', count($row['keys']) - 1), $row['keys']);
                if (isset($this->_field_data[$parent_field])) {
                    if (!isset($this->_field_data[$parent_field]['rules']['is_array'])) {
                        $this->_field_data[$parent_field]['rules']['is_array'] = 'is_array';
                    }
                    break;
                }
                $this->set_one_rule($parent_field, $parent_field, ['is_array']);
            }
        }

        $tree = [];
        $field_data = $this->_field_data;

        foreach ($field_data as $field => $row) {
            $field_data[$field]['rules'] = $this->_prepare_rules($row['rules']);
            if (count($row['keys']) > 1) {
                $t = array_pop($row['keys']);
                $parent_field = vsprintf('%s' . str_repeat('[%s]', count($row['keys']) - 1), $row['keys']);

                $field_data[$parent_field]['sub'][$t] = &$field_data[$field];
            } else {
                $tree[$field] = &$field_data[$field];
            }
        }

        return $tree;
    }

    // --------------------------------------------------------------------

    protected function execute(array &$field_data, array &$validation_data, array $indexes = [])
    {
        $data = $validation_data;
        foreach ($field_data as $field => $row) {
            if (empty($row['rules'])) {
                continue;
            }
            if ($field == '') {
                foreach ($data as $key => $value) {
                    $indexes[] = (string)$key;
                    $res = $this->validate($row, $indexes, $data[$key]);

                    if ($res && isset($row['sub']) && is_array($data[$key])) {
                        $this->execute($row['sub'], $data[$key], $indexes);
                    }
                    array_pop($indexes);
                }
            } else {
                $indexes[] = (string)$field;
                if (array_key_exists($field, $data)) {
                    $res = $this->validate($row, $indexes, $data[$field]);

                    if ($res && isset($row['sub']) && is_array($data[$field])) {
                        $this->execute($row['sub'], $data[$field], $indexes);
                    }

                    $store[$field] = $data[$field];
                    unset($data[$field]);
                } elseif ($rules = array_intersect_key($row['rules'], ['required' => '', 'isset' => '', 'matches' => '', 'least_one_required' => ''])) {
                    $temp = null;
                    $row['rules'] = $rules;
                    $this->validate($row, $indexes, $temp);
                } elseif (isset($row['rules']['default_value'])) {
                    $store[$field] = '';
                    $this->validate($row, $indexes, $store[$field]);
                }
                array_pop($indexes);
            }
        }

        if (isset($store)) {
            $validation_data = $store + $data;
        } else {
            $validation_data = $data;
        }
    }

    // --------------------------------------------------------------------


    protected function validate(array $row, array $indexes, &$data)
    {
        $rules = $row['rules'];

        foreach ($rules as $rule) {
            // Is the rule a callback?
            $callable = false;
            $param = null;
            if (is_string($rule)) {
                if (preg_match('/(?<rule>.*?)\[(?<param>.*)\]/', $rule, $match)) {
                    $rule = $match['rule'];
                    $param = $match['param'];
                }
            } elseif (is_callable($rule)) {
                $callable = true;
            } elseif (is_array($rule) && isset($rule[0], $rule[1]) && is_callable($rule[1])) {
                // We have a "named" callable, so save the name
                $callable = $rule[0];
                $rule = $rule[1];
            }


            // Ignore empty, non-required inputs with a few exceptions ...
            if (
                ($data === null || $data === '' || $data === [])
                && $callable === false
                && !in_array($rule, ['required', 'isset', 'not_empty_str', 'not_empty_array', 'matches', 'least_one_required', 'default_value', 'is_array', 'is_bool', 'is_string', 'is_numeric', 'is_int', 'is_float'], true)
            ) {
                continue;
            }

            // Call the function that corresponds to the rule
            if ($callable !== false) {
                $result = is_array($rule)
                    ? $rule[0]->{$rule[1]}($data)
                    : $rule($data);

                // Is $callable set to a rule name?
                if (!is_bool($callable)) {
                    $rule = $callable;
                }

                // Re-assign the result to the master data array
                is_bool($result) || $data = $result;
            } elseif (method_exists($this, $rule)) {
                if (in_array($rule, ['matches', 'differs', 'least_one_required', 'date_later_than', 'date_before_than', 'fix_image_ext', 'match_then_rule_other_filed'])) {
                    $result = $this->$rule($data, $indexes, $param);
                } else {
                    $result = isset($param) ? $this->$rule($data, $param) : $this->$rule($data);
                }

                is_bool($result) || $data = $result;
            } else {
                // If our own wrapper function doesn't exist we see if a native PHP function does.
                // Users can use any native PHP function call that has one param.
                if (function_exists($rule)) {
                    // Native PHP functions issue warnings if you pass them more parameters than they use
                    $result = isset($param) ? $rule($data, $param) : $rule($data);

                    is_bool($result) || $data = $result;
                } elseif ($rule == 'isset') {
                    $result = isset($data);
                } elseif ($rule == 'empty') {
                    $result = empty($data);
                } else {
                    log_message('debug', 'Unable to find validation rule: ' . $rule);
                    $result = false;
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
                if (isset($param) && in_array($rule, ['differs', 'matches', 'least_one_required', 'date_later_than', 'date_before_than']) && isset($this->_field_data[$param], $this->_field_data[$param]['label'])) {
                    $param = $this->_translate_fieldname($this->_field_data[$param]['label']);
                }

                // Build the error message
                $message = $this->_build_error_msg($line, $this->_translate_fieldname($row['label']), isset($param) ? $param : '');

                $full_key = vsprintf('%s' . str_repeat('[%s]', count($indexes) - 1), $indexes);

                if (!isset($this->_error_array[$full_key])) {
                    $this->_error_array[$full_key] = $message;
                }

                return false;
            }
        }

        return true;
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
        $special = [];
        $callbacks = [];

        foreach ($rules as &$rule) {
            // Let 'required' always be the first (non-callback) rule
            if (in_array($rule, ['required', 'isset', 'not_empty_str', 'not_empty_array'])) {
                $special = [$rule => $rule] + $special;
            } elseif (in_array($rule, ['is_string', 'is_array', 'is_bool', 'is_numeric', 'is_int', 'is_float'])) {
                $special[$rule] = $rule;
            } // Proper callables
            elseif (is_array($rule) && is_callable($rule)) {
                $callbacks[] = $rule;
            } // "Named" callables; i.e. array('name' => $callable)
            elseif (is_array($rule) && isset($rule[0], $rule[1]) && is_callable($rule[1])) {
                $callbacks[] = $rule;
            } elseif (preg_match('/(?<rule>default_value|least_one_required)\[.*\]/', $rule, $match)) {
                $special = [$match['rule'] => $rule] + $special;
            } // Everything else goes at the end of the queue
            else {
                $new_rules[] = $rule;
            }
        }
        if (!array_intersect_key(['is_string' => '', 'is_array' => '', 'is_bool' => '', 'is_numeric' => '', 'is_int' => '', 'is_float' => ''], $special)) {
            //$callbacks[] = 'is_string';
            $special['is_string'] = 'is_string';
        }

        return array_merge($callbacks, $special, $new_rules);
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
    public function set_validation_data(&$data)
    {

        if (is_array($data)) {
            $this->validation_data = &$data;
        } else {
            $this->validation_data = [&$data];
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Set Error Message
     * Lets users set their own error messages on the fly. Note:
     * The key name has to match the function name that it corresponds to.
     *
     * @param    array
     * @param    string
     *
     * @return    CI_Form_validation
     */
    public function set_error_message($lang, $val = '')
    {
        if (!is_array($lang)) {
            $lang = [$lang => $val];
        }

        $this->_error_messages = array_merge($this->_error_messages, $lang);
        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Set The Error Delimiter
     * Permits a prefix/suffix to be added to each error message
     *
     * @param    string
     * @param    string
     *
     * @return    CI_Form_validation
     */
    public function set_error_delimiters($prefix = '<p>', $suffix = '</p>')
    {
        $this->_error_prefix = $prefix;
        $this->_error_suffix = $suffix;
        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Get Error Message
     * Gets the error message associated with a particular field
     *
     * @param    string $field  Field name
     * @param    string $prefix HTML start tag
     * @param    string $suffix HTML end tag
     *
     * @return    string
     */
    public function error($field, $prefix = '', $suffix = '')
    {
        if (empty($this->_field_data[$field]['error'])) {
            return '';
        }

        if ($prefix === '') {
            $prefix = $this->_error_prefix;
        }

        if ($suffix === '') {
            $suffix = $this->_error_suffix;
        }

        return $prefix . $this->_field_data[$field]['error'] . $suffix;
    }

    // --------------------------------------------------------------------

    /**
     * Get Array of Error Messages
     * Returns the error messages as an array
     *
     * @return    array
     */
    public function error_array()
    {
        return $this->_error_array;
    }

    // --------------------------------------------------------------------

    /**
     * Error String
     * Returns the error messages as a string, wrapped in the error delimiters
     *
     * @param    string
     * @param    string
     *
     * @return    string
     */
    public function error_string($prefix = '', $suffix = '')
    {
        // No errors, validation passes!
        if (count($this->_error_array) === 0) {
            return '';
        }

        if ($prefix === '') {
            $prefix = $this->_error_prefix;
        }

        if ($suffix === '') {
            $suffix = $this->_error_suffix;
        }

        // Generate the error string
        $str = '';
        foreach ($this->_error_array as $val) {
            if ($val !== '') {
                $str .= $prefix . $val . $suffix . "\n";
            }
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Get the error message for the rule
     *
     * @param    string $rule  The rule name
     * @param    string $field The field name
     *
     * @return    string
     */
    protected function _get_error_message($rule, $field)
    {
        // check if a custom message is defined through validation config row.
        if (isset($this->_field_data[$field]['errors'][$rule])) {
            return $this->_field_data[$field]['errors'][$rule];
        } // check if a custom message has been set using the set_message() function
        elseif (isset($this->_error_messages[$rule])) {
            return $this->_error_messages[$rule];
        } elseif (false !== ($line = $this->CI->lang->line('form_validation_' . $rule))) {
            return $line;
        }

        return $this->CI->lang->line('form_validation_error_message_not_set') . '(' . $rule . ')';
    }

    // --------------------------------------------------------------------

    /**
     * Translate a field name
     *
     * @param    string    the field name
     *
     * @return    string
     */
    protected function _translate_fieldname($fieldname)
    {
        // Do we need to translate the field name? We look for the prefix 'lang:' to determine this
        // If we find one, but there's no translation for the string - just return it
        if (sscanf($fieldname, 'lang:%s', $line) === 1 && false === ($fieldname = $this->CI->lang->line($line, false))) {
            return $line;
        }

        return $fieldname;
    }

    // --------------------------------------------------------------------

    /**
     * Build an error message using the field and param.
     *
     * @param    string    The error message line
     * @param    string    A field's human name
     * @param    mixed    A rule's optional parameter
     *
     * @return    string
     */
    protected function _build_error_msg($line, $field = '', $param = '')
    {
        // Check for %s in the string for legacy support.
        if (strpos($line, '%s') !== false) {
            return sprintf($line, $field, $param);
        }

        return str_replace(['{field}', '{param}'], [$field, $param], $line);
    }

    // --------------------------------------------------------------------

    /**
     * Checks if the rule is present within the validator
     * Permits you to check if a rule is present within the validator
     *
     * @param    string    the field name
     *
     * @return    bool
     */
    public function has_rule($field = null)
    {
        if (is_null($field)) {
            return !empty($this->_field_data);
        }

        return isset($this->_field_data[$field]);
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
        return $this;
    }

    /**
     * Reset validation vars
     * Prevents subsequent validation routines from being affected by the
     * results of any previous validation routine due to the CI singleton.
     *
     * @return    CI_Form_validation
     */
    public function reset_validation()
    {
        $this->_field_data = [];
        $this->_error_array = [];
        $this->_error_messages = [];
        return $this;
    }

    protected function get_validation_data_element(array $indexes)
    {
        $data = $this->validation_data;

        foreach ($indexes as $i) {
            if (isset($data[$i])) {
                $data = $data[$i];
            } else {
                return null;
            }
        }

        return $data;
    }

    protected function replace_uncertain_index(array $subject, array $replace)
    {
        if (count($replace) > 1 && in_array('', $subject)) {
            $count = min($replace, $subject);

            for ($i = 0; $i < $count; $i++) {
                if ($replace[$i] === $subject[$i]) {
                    continue;
                } elseif ($subject[$i] === '') {
                    $subject[$i] = $replace[$i];
                } else {
                    break;
                }
            }
        }

        return $subject;
    }

    protected function parse_field_str($field)
    {
        $indexes = [];
        if ((bool)preg_match_all('/\[(?<sub>.*?)\]/', $field, $matches)) {
            sscanf($field, '%[^[][', $indexes[0]);

            $indexes = array_merge($indexes, $matches['sub']);
        } else {
            $indexes[] = $field;
        }

        return $indexes;
    }
}
