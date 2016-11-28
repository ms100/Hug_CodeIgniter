[Hug CodeIgniter](https://github.com/MS100/Hug_CodeIgniter)
=============================

**针对CodeIgniter的一些改进，适用于3.0以上版本**

> CI3.0文档 http://codeigniter.org.cn/user_guide/

改进：
--------------------

## 1. Form_validation 表单验证
* 新增的验证规则看my_Form_validation.php里的注释
* **$_FILES** 里的数据在调用 `$this->form_validation->run() 或 set_rules()` 之后会放到 **$_POST** 里；
* **$_POST** 里的数据格式必须与表单验证里的配置的格式完全对应，否则通不过验证；
* 更改所有CI自带的规则，字端值为空字符或null时可通过验证。举例：如果设置规则为 is_natural，那么此字端不传或传空字符都可以通过规则，若要必填，还需加上 required；
* 增加not_empty_str的规则，表示字端可以为 null，但不能为空字符。即：前端可以不传此字段，但不能传字段=''，一般用于一个验证规则被多处使用的时候；

### 解释：
  CI本身的表单验证是有缺陷的，例如规则field设为name[],post数据是name=aaa，可以通过验证；再例如规则field设为name[type],post数据是name[type][]=aaa，也可以通过验证；而实际上我们在使用中是希望通过设置field字段能控制到post的数据格式的，如果不限制格式那么之后的代码可能会出现警告，更严重的是数据库查询会报错。


### 用法：  
- 例如post字段a是个索引数组，并且需要是必填的正整数，那表单验证规则为

        array(
            'field' => 'a[]',//此处的中括号限制a必须为一个数组，之后的rules会循环作用于a中的每一个元素
            'label' => 'xxx',
            'rules' => 'required|is_natural_no_zero',
        ),

- 例如post字段a是个关联数组，并且需要a[b]是必填的正整数，那表单验证规则为

        array(
            'field' => 'a[b]',//rules只会作用于a[b]
            'label' => 'xxx',
            'rules' => 'required|is_natural_no_zero',
        ),

- 上传文件的验证配置：

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


## 2.数据库支持多库和主从读写分离
* SQL执行的时候才选择要链接数据库
* 配置后会自动根据SQL语句来选择使用主库还是从库
* 从库连接失败会自动切换到主库
* 从库失败后在配置的时间里不会去连接从库

### 用法： 
- 主库

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

- 从库

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

## 3.支持多缓存
* 支持同时存在多套同类型的缓存
* 配置类似于 **database.php**
* 默认读取 `$cache_group`

### 用法：

        $cache_group = 'default';

        $config['default'] = [
            'adapter' => 'memcached',
            'key_prefix' => 'my_',
            'servers' => [
                [
                    'hostname' => '127.0.0.1',
                    'port' => '11211',
                    'weight' => '1',
                ],
            ],
        ];
