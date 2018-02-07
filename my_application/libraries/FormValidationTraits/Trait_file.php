<?php

Trait Trait_file
{
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
    public function file_allowed_ext($name, $type)
    {
        $file_ext = pathinfo($name, PATHINFO_EXTENSION);
        $file_ext = strtolower($file_ext);

        $allow_ext_arr = explode(',', $type);

        foreach ($allow_ext_arr as $ext) {
            if ($file_ext == $ext) {
                return true;
            }
        }

        return false;
    }

    public function file_allowed_mime($tmp_name, $type)
    {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }

        $mime = @finfo_file($finfo, $tmp_name);
        if ($mime === false) {
            return false;
        }

        @finfo_close($finfo);

        $mimes_mapping =& get_mimes();

        $allow_ext_arr = explode(',', $type);

        foreach ($allow_ext_arr as $ext) {
            if (isset($mimes_mapping[$ext])) {
                if (is_array($mimes_mapping[$ext]) && in_array($mime, $mimes_mapping[$ext])) {
                    return true;
                } elseif (is_string($mimes_mapping[$ext]) && $mime == $mimes_mapping[$ext]) {
                    return true;
                }
            }
        }

        return false;
    }

    public function fix_image_ext($name, array $indexes, $tmp_name_field)
    {
        $subject = $this->parse_field_str($tmp_name_field);
        $subject = $this->replace_uncertain_index($subject, $indexes);
        $tmp_name = $this->get_validation_data_element($subject);

        if (isset($tmp_name)) {
            $mime = @getimagesize($tmp_name);
            if ($mime) {
                $fix_ext = image_type_to_extension($mime[2]);
                $fix_ext = strtolower(trim($fix_ext, '.'));
                if ($fix_ext === 'jpeg') {
                    $fix_ext = 'jpg';
                }

                $fileinfo = pathinfo($name);
                $ext = strtolower($fileinfo['extension']);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }

                if ($ext !== $fix_ext) {
                    return trim($fileinfo['filename'], '.') . '.' . $fix_ext;
                }

                return true;
            }
        }

        return false;
    }

    public function file_disallowed_ext($name, $type)
    {
        return !$this->file_allowed_ext($name, $type);
    }

    public function file_disallowed_mime($tmp_name, $type)
    {
        return !$this->file_allowed_mime($tmp_name, $type);
    }

    /**
     * 转化字节
     *
     * @param string $value
     *
     * @return integer number of bits
     */
    protected function let_to_bit($value)
    {
        if (!preg_match('/^(?<num>\d+)(?<unit>p|t|g|m|k)?b?$/i', $value, $match)) { // Invalid input
            return false;
        }

        $map = [
            'P' => 50,
            'T' => 40,
            'G' => 30,
            'M' => 20,
            'K' => 10,
        ];

        return intval($match['num']) << $map[strtoupper($match['unit'])];
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
}