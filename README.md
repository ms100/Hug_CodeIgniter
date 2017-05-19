[Hug CodeIgniter](https://github.com/MS100/Hug_CodeIgniter)
=============================

**针对CodeIgniter的一些改进，适用于3.0以上版本**

> CI3.0文档 http://codeigniter.org.cn/user_guide/

改进：
--------------------

## 1. Form_validation 表单验证
具体变更查看 **my_Form_validation.php** 里的代码和注释。

### 使用注意
1. **$_FILES** 里的数据在调用 `$this->form_validation->run() 或 set_rules()` 之后会放到 **$_POST** 里；
2. **$_POST** 里的数据格式必须与表单验证里的配置的格式完全对应，否则通不过验证；
3. **注意** CI 自带的规则，字段值为空字符或null时可通过除了 `'required', 'isset', 'matches', 'not_empty_str', 'default_value'` 这几个规则外的所有规则验证。举例：如果设置规则为 is_natural，那么此字段不传或传空字符都可以通过规则，若要必填，还需加上 required；
4. 增加 `not_empty_str` 方法，表示字段可以为 NULL，但不能为空字符。即：前端可以不传此字段，但不能传字段空字符，一般用于一个验证规则有多种提交情况的时候；
5. 在 filter_emoji_helper.php 中增加了 `filter_emoji` 函数，可在表单验证时用来过滤表情符号；另表单验证时普通字符串数据酌情使用 trim 过滤前后空字符。
6. 新增 `set_validation_data(&$data)` 和 `_reset_validation_data()` 方法，可使表单验证的预处理字符方法（例如：`trim` 和 `filter_emoji`）同样作用于自定义数据。
7. 新增 `reset_error` 方法用来重置错误数据，可在批处理相同验证规则的数据时，不需要重复调用 `set_rules` 方法（若使用 `reset_validation` 方法重置验证类，则需要重复调用 `set_rules`）。
8. 验证规则里的 field 字段，**可以** 为以下几种值：
    * name
    * basic[name]
    * work[]，即 [] 只出现一次且在最后；
    * basic[like][]
    * mm[nn][ii][jj]....
    * mm[nn][ii][jj]....[]
9. 验证规则里的 field 字段，**不可以** 为以下几种值：
    * []，即只有 [] ；
    * basic[][like]，即 [] 在中间；
    * basic[like][][], 即有多个 [] ；
10. 验证规则里带有 [] 时，验证规则会作用于里面的每一个元素。

### 特别注意

POST表单所传的字端，若在验证规则里定义的字段格式将会完全限制，但验证规则里没有定义的字段不会被过滤，所以**一定不能**直接将POST数据直接传入数据库，或其他对数据格式有严格要求的方法中。

**CI**自身的表单验证是有缺陷的；
* 例如规则 **field** 设为 **name[]**，**POST** 数据 **name=aaa** 可以通过验证；  
* 例如规则 **field** 设为 **name[type]**，**POST** 数据 **name[type][]=aaa**也可以通过验证；  
* 而实际上我们在使用中是希望通过设置 **field** 字段能控制到 **POST** 数据格式的，如果不限制格式那么之后的代码可能会出现错误或警告，更严重的是数据库报错。

### 使用方式
* 例如 **POST** 字段 **id** 是个索引数组，并且需要是必填的正整数，那表单验证规则为

```php
<?
    $config['foo/test'] = [
        [
            'field' => 'id[]',//此处的中括号限制id必须为一个数组，之后的rules会循环作用于id中的每一个元素
            'label' => 'ID',
            'rules' => 'required|is_natural_no_zero',
        ],
    ];
```

* 例如 **POST** 字段 **info** 是个关联数组，并且需要 **info[id]** 是必填的正整数，那表单验证规则为
* 
```php
<?
    $config['foo/test'] = [
        [
            'field' => 'info[id]',//rules只会作用于info[id]，不会作用于info的其他元素
            'label' => 'ID信息',
            'rules' => 'required|is_natural_no_zero',
        ],
    ];
```

* 上传文件的验证配置：

```php
<?
    $config['cms/article/upload_image'] = [
        [
            'field' => 'image[name]',
            'label' => '图片',
            'rules' => 'required|file_allowed_type[image,word_document]',
        ],
        [
            'field' => 'image[size]',
            'label' => '图片',
            'rules' => 'file_size_max[2MB]',
        ],
        [
            'field' => 'image[error]',
            'label' => '图片',
            'rules' => 'file_upload_error[0]',
        ],
        /*[
            'field' => 'image[tmp_name]',
            'label' => '图片',
            'rules' => 'image_pixel_min[1,1]|image_pixel_max[1000,1000]||valid_image[image[name]]',
        ],*/
    ];
```


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
