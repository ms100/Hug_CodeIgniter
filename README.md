# [Hug CodeIgniter](https://github.com/MS100/Hug_CodeIgniter)
针对CodeIgniter的一些改进，使用与3.0以上版本

> CI3.0文档 http://codeigniter.org.cn/user_guide/

## 改进：
    * 1.Form_validation 表单验证
          新增的验证规则看my_Form_validation.php里的注释
          $_FILES 里的数据在调用 $this->form_validation->run()之后会自动放到 $_POST 里

          * *******
          * 特别注意
          * *******
          CI本身的表单验证是有缺陷的
              例如规则field设为name[],post数据是name=aaa，可以通过验证
              再例如规则field设为name[type],post数据是name[type][]=aaa，也可以通过验证
              而实际上我们在使用中是希望通过设置field字段能控制到post的数据格式的
              如果不限制格式，那么之后的代码可能会出现警告，更严重的是数据库查询会报错


          所以做了修改，表单验证里的字段完全对应post的表单
              例如post字段a是个索引数组，并且需要是必填的正整数，那表单验证规则为
                    array(
                        'field' => 'a[]',//此处的中括号限制a必须为一个数组，之后的rules会循环作用于a中的每一个元素
                        'label' => 'xxx',
                        'rules' => 'required|is_natural_no_zero',
                    ),

              例如post字段a是个关联数组，并且需要a[b]是必填的正整数，那表单验证规则为
                    array(
                        'field' => 'a[b]',//rules只会作用于a[b]
                        'label' => 'xxx',
                        'rules' => 'required|is_natural_no_zero',
                    ),

          上传文件的验证配置：
              $config['cms/article/upload_image'] = array(
                  array(
                      'field' => 'image[name]',
                      'label' => '图片',
                      'rules' => 'required|file_allowed_type[image,word_document]',
                  ),
                  array(
                      'field' => 'image[size]',
                      'label' => '图片',
                      'rules' => 'file_size_max[2MB]',
                  ),
                  array(
                      'field' => 'image[error]',
                      'label' => '图片',
                      'rules' => 'file_upload_error[0]',
                  ),
                  /*array(
                       'field' => 'image[tmp_name]',
                       'label' => '图片',
                       'rules' => 'image_pixel_min[1,1]|image_pixel_max[1000,1000]||valid_image[image[name]]',
                  ),*/针对文件是图片的进一步校验
              );


    * 2.数据库支持主从和读写分离
        特点：
            - SQL执行的时候才选择要链接数据库
            - 配置后会自动根据SQL语句来选择使用主库还是从库
            - 从库连接失败会自动切换到主库
            - 从库失败后在配置的时间里不会去连接从库

        配置举例：
            //主库
            $db['test'] = array(
                'hostname' => '127.0.0.1',
                'port' => 3306,
                'username' => 'test',
                'password' => 'test',
                'database' => 'test',
                'dbdriver' => 'mysqli',
                'pconnect' => false,
                'db_debug' => false,
                'cache_on' => false,
                'char_set' => 'utf8',
                'dbcollat' => 'utf8_general_ci',
                'encrypt' => false,
                'compress' => false,
                'stricton' => true,
                'master_slave' => true,//开启主从
                'auto_switchover' => true,//开启自动切换，开启主从后才有效
                'invalid_key_cache_time' => 60,//连接失败重试间隔秒数
            );


            //从库
            $db['test']['db_slave'][] = array(
                'hostname' => '127.0.0.1',
                'port' => 3307,
                'username' => 'test',
                'password' => 'test',
                'database' => 'test',
                'dbdriver' => 'mysqli',
                'pconnect' => false,
                'db_debug' => false,
                'cache_on' => false,
                'char_set' => 'utf8',
                'dbcollat' => 'utf8_general_ci',
                'encrypt' => false,
                'compress' => false,
                'stricton' => true,
            );
