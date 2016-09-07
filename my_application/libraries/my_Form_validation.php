<?php

class My_Form_validation extends CI_Form_validation{
    
    /**
     * Set Rules 将$_FILES里的内容放到 $_POST中
     *
     * This function takes an array of field names and validation
     * rules as input, any custom error messages, validates the info,
     * and stores it
     *
     * @param    mixed $field
     * @param    string $label
     * @param    mixed $rules
     * @param    array $errors
     * @return    CI_Form_validation
     */
    public function set_rules($field, $label = '', $rules = array(), $errors = array()){
        //-----------------------如果有文件上传，把$_FILES和$_POST合并------------------------
        if(isset($_FILES) && count($_FILES) > 0){
            $_POST = array_merge($_POST, $this->restructure_files($_FILES));
            unset($_FILES);
        }

        // No reason to set rules if we have no POST data
        // or a validation array has not been specified
        if($this->CI->input->method() !== 'post' && empty($this->validation_data)){
            return $this;
        }

        // If an array was passed via the first parameter instead of individual string
        // values we cycle through it and recursively call this function.
        if(is_array($field)){
            foreach($field as $row){
                // Houston, we have a problem...
                if(!isset($row['field'], $row['rules'])){
                    continue;
                }

                // If the field label wasn't passed we use the field name
                $label = isset($row['label']) ? $row['label'] : $row['field'];

                // Add the custom error message array
                $errors = (isset($row['errors']) && is_array($row['errors'])) ? $row['errors'] : array();

                // Here we go!
                $this->set_rules($row['field'], $label, $row['rules'], $errors);
            }

            return $this;
        }

        // No fields or no rules? Nothing to do...
        if(!is_string($field) OR $field === '' OR empty($rules)){
            return $this;
        }elseif(!is_array($rules)){
            // BC: Convert pipe-separated rules string to an array
            if(!is_string($rules)){
                return $this;
            }

            $rules = preg_split('/\|(?![^\[]*\])/', $rules);
        }

        // If the field label wasn't passed we use the field name
        $label = ($label === '') ? $field : $label;

        $indexes = array();
        //----------------增加一个表示是否为索引数组的字段，它的值表示索引数组所在的维度-----------------
        $is_index_array = false;

        // Is the field name an array? If it is an array, we break it apart
        // into its components so that we can fetch the corresponding POST data later
        if(($is_array = (bool)preg_match_all('/\[(.*?)\]/', $field, $matches)) === true){
            sscanf($field, '%[^[][', $indexes[0]);

            for($i = 0, $c = count($matches[0]); $i < $c; $i++){
                if($matches[1][$i] !== ''){
                    $indexes[] = $matches[1][$i];
                }else{
                    $is_index_array = $i + 1;
                }
            }
        }

        // Build our master array
        $this->_field_data[$field] = array(
            'field' => $field,
            'label' => $label,
            'rules' => $rules,
            'errors' => $errors,
            'is_array' => $is_array,
            'keys' => $indexes,
            'is_index_array' => $is_index_array,
            'postdata' => null,
            'error' => ''
        );

        return $this;
    }

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
                foreach ($iterator as $key => $value) {
                    array_splice($stack, $iterator->getDepth() + 1);
                    $pointer = &$stack[count($stack) - 1];
                    $pointer = &$pointer[$key];
                    $stack[] = &$pointer;
                    if (!$iterator->hasChildren()) {
                        $pointer[$field] = $value;
                    }
                }
            }
        }
        return $output;
    }
    
    /**
     * Run the Validator 将$_FILES里的内容放到 $_POST中
     *
     * This function does all the work.
     *
     * @param    string $group
     * @return    bool
     */
    public function run($group = ''){
        //-----------------------如果有文件上传，把$_FILES和$_POST合并------------------------
        if(isset($_FILES) && count($_FILES) > 0){
            $_POST = array_merge($_POST, $this->restructure_files($_FILES));
            unset($_FILES);
        }

        $validation_array = empty($this->validation_data) ? $_POST : $this->validation_data;

        // Does the _field_data array containing the validation rules exist?
        // If not, we look to see if they were assigned via a config file
        if(count($this->_field_data) === 0){
            // No validation rules?  We're done...
            if(count($this->_config_rules) === 0){
                return false;
            }

            if(empty($group)){
                // Is there a validation rule for the particular URI being accessed?
                $group = trim($this->CI->uri->ruri_string(), '/');
                isset($this->_config_rules[$group]) OR $group = $this->CI->router->class . '/' . $this->CI->router->method;
            }

            $this->set_rules(isset($this->_config_rules[$group]) ? $this->_config_rules[$group] : $this->_config_rules);

            // Were we able to set the rules correctly?
            if(count($this->_field_data) === 0){
                log_message('debug', 'Unable to find validation rules');

                return false;
            }
        }

        // Load the language file containing error messages
        $this->CI->lang->load('form_validation');

        // Cycle through the rules for each field and match the corresponding $validation_data item
        foreach($this->_field_data as $field => &$row){
            // Fetch the data from the validation_data array item and cache it in the _field_data array.
            // Depending on whether the field name is an array or a string will determine where we get it from.
            if($row['is_array'] === true){
                //-------------------------此处修改为自定义的获取字段值函数--------------------------
                $this->_field_data[$field]['postdata'] = $this->_my_reduce_array($validation_array, $row['keys'], 0, $row['is_index_array']);
            }elseif(isset($validation_array[$field])){
                $this->_field_data[$field]['postdata'] = $validation_array[$field];
            }
            //var_dump($this->_field_data[$field]['postdata']);exit;
        }
        // Execute validation rules
        // Note: A second foreach (for now) is required in order to avoid false-positives
        //	 for rules like 'matches', which correlate to other validation fields.
        foreach($this->_field_data as $field => &$row){
            // Don't try to validate if we have no rules set
            if(empty($row['rules'])){
                continue;
            }

            $this->_execute($row, $row['rules'], $row['postdata']);
        }

        // Did we end up with any errors?
        $total_errors = count($this->_error_array);
        if($total_errors > 0){
            $this->_safe_form_data = true;
        }

        // Now we need to re-set the POST data with the new, processed data
        empty($this->validation_data) && $this->_reset_post_array();

        return ($total_errors === 0);

    }


    /**
     * Traverse a multidimensional $_POST array index until the data is found
     *
     * @param $array
     * @param $keys
     * @param int $i
     * @param $is_index_array
     * @return array|null
     */
    protected function _my_reduce_array($array, $keys, $i = 0, $is_index_array){
        //var_dump($array, $keys, $is_index_array);
        if(is_array($array)){
            if(isset($keys[$i]) && isset($array[$keys[$i]])){
                return $this->_my_reduce_array($array[$keys[$i]], $keys, ($i + 1), $is_index_array);
            }
            if($is_index_array === $i && $this->is_one_dimensional_array($array)){
                return $array;
            }

        }elseif(!is_array($array)){
            // NULL must be returned for empty fields
            if(!$is_index_array && $array !== '' && !isset($keys[$i])){
                return $array;
            }
        }

        return null;
    }

    /**
     * 判断数组是一维数组，取$_POST的值时候用到
     * @param $array
     * @return bool
     */
    protected function is_one_dimensional_array($array){
        foreach($array as $value){
            if(is_array($value)){
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
    protected function _reset_post_array(){
        foreach($this->_field_data as $field => $row){
            //----------------------此处把获取的值赋值会$_POST,修改为获取的为null也赋值回去------------------------
            //if($row['postdata'] !== null){
            if($row['is_array'] === false){
                isset($_POST[$field]) && $_POST[$field] = $row['postdata'];
            }else{
                // start with a reference
                $post_ref =& $_POST;

                // before we assign values, make a reference to the right POST key
                if(count($row['keys']) === 1){
                    $post_ref =& $post_ref[current($row['keys'])];
                }else{
                    foreach($row['keys'] as $val){
                        //---------------------字段下标不存在时创建下标-------------------------
                        is_array($post_ref) || $post_ref = array();
                        isset($post_ref[$val]) || $post_ref[$val] = null;
                        $post_ref =& $post_ref[$val];
                    }
                }

                $post_ref = $row['postdata'];
            }
            //}
        }
    }

    public function file_upload_error($error, &$param){
        switch($error){
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
     * @param $size
     * @param $max_size
     * @return bool
     */
    public function file_size_max($size, $max_size){
        $max_size_bit = $this->let_to_bit($max_size);

        return $size > $max_size_bit ? false : true;
    }

    /**
     * 文件最小大小
     * @param $size
     * @param $min_size
     * @return bool
     */
    public function file_size_min($size, $min_size){
        $min_size_bit = $this->let_to_bit($min_size);

        return $size < $min_size_bit ? false : true;
    }


    /**
     * 检查文件上传类型
     * @param $name
     * @param $type
     * @return bool
     */
    public function file_allowed_type($name, &$type){
        // get file ext
        $file_ext = strtolower(trim(strrchr($name, '.'), '.'));

        // is type of format a,b,c,d? -> convert to array
        $ext_arr = explode(',', $type);

        $type = '';
        $ext_groups = array(
            'image' => array(
                'jpg',
                'jpeg',
                'gif',
                'png'
            ),
            'application' => array(
                'exe',
                'dll',
                'so',
                'cgi'
            ),
            'php_code' => array(
                'php',
                'php4',
                'php5',
                'inc',
                'phtml'
            ),
            'word_document' => array(
                'rtf',
                'doc',
                'docx'
            ),
            'compressed' => array(
                'zip',
                'gzip',
                'tar',
                'gz'
            ),
            'text' => array(
                'csv',
                'txt'
            ),
        );


        // is $type array? run self recursively
        foreach($ext_arr as $ext){
            // is type a group type? image, application, word_document, code, zip .... -> load proper array
            isset($ext_groups[$ext]) && $ext = $ext_groups[$ext];

            if(is_array($ext)){
                if(in_array($file_ext, $ext)){
                    return true;
                }
                $type .= ',.' . implode(',.', $ext);
            }else{
                if($file_ext == $ext){
                    return true;
                }
                $type .= ',.' . $ext;
            }
        }
        $type = trim($type, ',');

        return false;
    }


    public function valid_image($tmp_name, $name){
        $mime = getimagesize($tmp_name);
        if($mime){
            $ext = image_type_to_extension($mime[2]);
            $this->_field_data[$name]['postdata'] .= $ext;

            return true;
        }

        return false;
    }

    public function file_disallowed_type($file, &$type){
        $rc = $this->file_allowed_type($file, $type);

        return !$rc;
    }


    /**
     * given an string in format of ###AA converts to number of bits it is assignin
     *
     * @param string $sValue
     * @return integer number of bits
     */
    protected function let_to_bit($sValue){
        // Split value from name
        if(!preg_match('/([0-9]+)([ptgmkb]{1,2}|)/ui', $sValue, $aMatches)){ // Invalid input
            return false;
        }

        if(empty ($aMatches [2])){ // No name -> Enter default value
            $aMatches [2] = 'KB';
        }

        if(strlen($aMatches [2]) == 1){ // Shorted name -> full name
            $aMatches [2] .= 'B';
        }

        $iBit = (substr($aMatches [2], -1) == 'B') ? 1024 : 1000;
        // Calculate bits:

        switch(strtoupper(substr($aMatches [2], 0, 1))){
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
     * @return bool
     */
    public function image_pixel_max($file, &$param){
        $limit = explode(',', $param);

        if(count($limit) !== 2){
            return false;
        }
        $param = $limit[0] . '×' . $limit[1];

        $d = @getimagesize($file);
        if(!$d){
            return false;
        }
        if($d[0] > $limit[0] || $d[1] > $limit[1]){
            return false;
        }

        return true;
    }

    /**
     * returns false is the image is smaller than given dimension
     *
     * @param $file
     * @param $param
     * @return bool
     */
    public function image_pixel_min($file, &$param){
        $limit = explode(',', $param);

        if(count($limit) !== 2){
            return false;
        }
        $param = $limit[0] . '×' . $limit[1];

        $d = @getimagesize($file);
        if(!$d){
            return false;
        }

        if($d[0] < $limit[0] || $d[1] < $limit[1]){
            return false;
        }

        return true;
    }


    /**
     * 关联其他字段验证 用法：relate_other_field[关联字段名，关联字段默认值，验证规则，验证规则参数[，验证规则参数[，验证规则参数]]]
     * @param $str
     * @param $param
     * @return bool
     */
    public function relate_other_field($str, $param){
        if(trim($str) == ''){
            return true;
        }
        $arr = explode(',', $param, 4);
        $other_field = $arr[0];
        $default_value = $arr[1];
        $func = $arr[2];
        $rule = isset($arr[3]) ? $arr[3] : null;

        $value = $this->_field_data[$other_field]['postdata'];
        if($value == $default_value){
            return true;
        }

        if(method_exists($this, $func)){
            if(isset($rule)){
                return $this->{$func}($str, $rule);
            }else{
                return $this->{$func}($str);
            }
        }else{
            if(isset($rule)){
                return $func($str, $rule);
            }else{
                return $func($str);
            }
        }

    }

    /**
     * 验证身份证
     * @param $str
     * @return bool
     */
    public function valid_card($str){
        if(!isset ($str) || $str == ''){
            return true;
        }

        if(!preg_match('/^(\d{15})|(\d{18})|(\d{17}(X|x))$/', $str)){
            return false;
        }

        //----------------------对十八位身份证做校验-----------------------
        if(strlen($str) == 18){
            $w = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
            $c = array(1, 0, 'X', 9, 8, 7, 6, 5, 4, 3, 2);

            $sum = 0;
            foreach($w as $key => $value){
                $sum += $value * $str{$key};
            }

            $r = $sum % 11;
            $res = $c[$r];

            if($res != strtoupper($str{17})){
                return false;
            }
        }

        return true;
    }

    /**
     * 验证手机号
     * @param $str
     * @return bool
     */
    public function valid_phone($str){
        if(!isset($str) || $str == ''){
            return true;
        }

        return (intval($str) < 10000000000 or intval($str) > 20000000000) ? false : true;
    }

    /**
     * 验证是MD5加密过的字符串
     *
     * @param $str
     * @param int $length
     * @return bool
     */
    public function valid_md5($str, $length = null){
        if(!isset($str) || $str == ''){
            return true;
        }
        empty($length) && $length = 32;

        //return (strlen($str) != 32) ? false : true;
        return preg_match('/^[a-fA-F0-9]{' . $length . '}$/', $str) ? true : false;
    }

    public function valid_email_can_empty($str){
        if(!isset($str) || $str == ''){
            return true;
        }

        return parent::valid_email($str);
        //return (!preg_match("/^[a-z0-9]([\._\-]?[a-z0-9]+)*@[a-z0-9]([_\-]?[a-z0-9]+)*(\.[a-z0-9]([_\-]?[a-z0-9]+)*)+$/ix", $str)) ? false : true;
    }


    public function regex_match_can_empty($str, $regex){
        if($str == ''){
            return true;
        }

        return parent::regex_match($str, $regex);
    }


    /**
     * 判断字符长度，中文2个字节，英文1个字节
     * @param $str
     * @param $val
     * @return bool
     */
    public function max_length_gbk($str, $val){
        if(preg_match("/[^0-9]/", $val)){
            return false;
        }

        if(function_exists('mb_strlen')){
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
     * @return bool
     */
    public function min_length_gbk($str, $val){
        if(preg_match("/[^0-9]/", $val)){
            return false;
        }

        if(function_exists('mb_strlen')){
            //return (mb_strlen($str, 'gb2312') < $val) ? FALSE : TRUE; //效率太低，下面的方式比它快4到5倍
            return (((strlen($str) + mb_strlen($str, 'UTF-8')) / 2) < $val) ? false : true;
        }

        return (strlen($str) < $val) ? false : true;
    }

    /**
     * 时间大于某个字段
     *
     * @param $end
     * @param $start
     * @return bool
     */
    public function date_greater_than($end, $start){
        $start = $this->_field_data[$start]['postdata'];
        $start = strtr($start, array(
            '.' => '-',
            '年' => '-',
            '月' => '-',
            '日' => ''
        ));

        $start = trim($start, '-');

        $start = strtotime($start);
        if(empty($end)){
            $end = time();
        }else{
            $end = strtr($end, array(
                '.' => '-',
                '年' => '-',
                '月' => '-',
                '日' => ''
            ));
            $end = trim($end, '-');

            $end = strtotime($end);
        }

        if($end <= $start){
            return false;
        }

        return true;
    }

    /**
     * 过滤表情符号
     * @param $str
     * @param null $replace
     * @return mixed|string
     */
    public function filter_emoji($str, $replace = null){
        static $map;
        if(!isset($map)){
            if($this->CI->config->load('emoji', true, true)){
                $map = $this->CI->config->config['emoji'];
            }else{
                $map = array();
            }
        }


        if(!empty($map) && !empty($str)){
            $replace = empty($replace) ? '' : strtr(urlencode($replace), array('%' => '\x'));

            $str = strtr(urlencode($str), array('%' => '\x'));
            $str = str_ireplace($map, $replace, $str);
            $str = strtr($str, array('\x' => '%'));

            $str = urldecode($str);
        }

        return $str;
    }
}
