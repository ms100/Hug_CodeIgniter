[Hug CodeIgniter](https://github.com/MS100/Hug_CodeIgniter)
=============================

**针对CodeIgniter的一些改进，适用于3.0以上版本**

> CI3.0文档 http://codeigniter.org.cn/user_guide/

改进：
--------------------

## 1. Form_validation 表单验证
CI本身表单验证功能太弱，还有BUG，所以所以造了个轮子。
具体变更查看 my_Form_validation.php 里的代码和注释。

### 使用注意
1. 如果没有显式的设置调用 `set_validation_data()`，**$_FILES** 里的数据在调用 `run()` 方法之后会放到 **$_POST** 里；
2. 不会过滤没有设置验证规则的字段，所以**一定不能**直接将验证完的数据直接作为参数传给数据库函数或其他对数据格式有严格要求的方法中。
3. 字段值不可以为 **null**，为 **null** 时候**一定**通不过验证。
4. 如果数据为二维及以上数组，请定义好每一维的规则；只有父级存在并通过验证，子级的验证规则才会生效。

    ```php
    <?
        $rules = [
            [
                'field' => 'info[id]',//rules只会作用于info[id]，不会作用于info的其他元素
                'label' => 'ID信息',
                'rules' => 'required|is_natural_no_zero',
            ],
        ];
        
        $_POST = []; //通过，因为 info 字段并没有定义为必填
        $_POST = ['info' => '']; //不能通过验证，会根据 info[id] 推断出 info 为数组
        $_POST = ['info' => []]; //不能通过验证，因为如果存在 info，则必须存在 info[id]
    ```

5. 除了字符串格式，其他格式的字段要用 is_类型（包括 is_array, is_bool, is_string, is_numeric, is_int, is_float）函数修饰，例如某一个字段为数组格式，规则里一般要写上 is_array；如果字段值为字符串 is_string 可以不写，执行时会自动补全。

    ```php
    //例如 **POST** 字段 **id** 是必填且不能是空数组，并且元素值不能是空字符串，那表单验证规则为
    <?
        $rules = [
            [
                'field' => 'id',
                'label' => 'ID',
                'rules' => 'required|is_array',
            ],
            [
                'field' => 'id[]',//此处的中括号表示匹配id下的每一个数组元素，rules会循环作用于每一个元素
                'label' => 'ID',
                'rules' => 'required',//这里如果没写 is_类型 函数，则默认为is_string，这里的 required，只用来限制元素值不能是空字符串
            ],
        ];
        
        $_POST = ['id' => ['a','b','c']]; //通过
        $_POST = ['id' => ['a'=>'a','b'=>'b','c'=>'c']]; //通过
        $_POST = ['id' => [1,2,3]]; //不能通过验证，因为 id 的元素必须是字符串
        $_POST = ['id' => ['','a','b']]; //不能通过验证，因为 id 的元素不能是空字符串
        $_POST = ['id' => []]; //不能通过验证，因为 id 的 required 限制它不能为空数组，将 required 换成 isset 则可以通过验证
        $_POST = []; //不能通过验证，id 字段必填

    ```
    
    ```php
    <?
        //上传图片的验证配置
        $rules = [
            [
                'field' => 'image',
                'label' => '图片',
                'rules' => 'required|is_array',//一定要记得定义父级
            ],
            [
                'field' => 'image[name]',
                'label' => '图片',
                'rules' => 'required|file_allowed_type[image,word_document]',
            ],
            [
                'field' => 'image[size]',
                'label' => '图片',
                'rules' => 'is_int|file_size_max[2MB]',//这里一定要写 is_int
            ],
            [
                'field' => 'image[error]',
                'label' => '图片',
                'rules' => 'is_int|file_upload_error[0]',//这里一定要写 is_int
            ],
            /*[
                'field' => 'image[tmp_name]',
                'label' => '图片',
                'rules' => 'image_pixel_min[1,1]|image_pixel_max[1000,1000]||valid_image[image[name]]',
            ],*/
        ];
    ```


6. **注意** 表单字段不存在时，将只有 required, isset, matches, least_one_required, default_value 这几个规则生效；表单字段值为 空字符串、空数组时，将另有 not_empty_str, not_empty_array 这两个规则生效。

    ```php
    <?
        $rules = [
            [
                'field' => 'name',
                'label' => '名字',
                'rules' => 'min_length[3]',//name 字段不存在或者是可以通过验证的。若要必填，还需加上 required。
            ],
        ];
        
        $_POST = ['name' => '']; //通过
        $_POST = []; //通过;
        $_POST = ['name' => []]; //不能通过验证，默认为 is_string
        $_POST = ['name' => 'ab']; //不能通过验证，长度不足
    ```

7. 所有单个参数且返回值不为 bool 型的函数和方法（例如：`trim`、`array_value`、`filter_emoji`）都会改变字段的值。
8. `reset_error` 方法用来重置错误数据，可在批处理相同验证规则的数据时，不需要重复调用 `set_rules` 方法（若使用 `reset_validation` 方法重置验证类，则需要重复调用 `set_rules`）。
9. 验证规则里的 field 字段，可以无限层级（虽然实际情况很少会用到）：
    * name
    * basic[name]
    * work[]
    * work[][name]
    * basic[like][]
    * mm[nn][][ii]....
    * mm[nn][][ii][][jj]...
    
    ```php
    <?
        $rules = [
            [
                'field' => 'like',
                'label' => '爱好',
                'rules' => 'is_set|is_array',
            ],
            [
                'field' => 'like[]',
                'label' => '爱好信息',
                'rules' => 'required|is_array',//因为 like[][name] 设置了 required，所以这里的 required 实际上没有什么意义，但提示信息会不一样
            ],
            [
                'field' => 'like[][name]',
                'label' => '爱好名称',
                'rules' => 'required',
            ],
            [
                'field' => 'like[][level]',
                'label' => '爱好程度',
                'rules' => 'required',
            ],
            [
                'field' => 'like[][des]',
                'label' => '爱好描述',
                'rules' => 'max_length[30]',
            ],
        ];
        
        $_POST = []; //不能通过，因为 like 字段必须存在
        $_POST = ['like' => []]; //通过
        
        $_POST = [
            'like' => [
                'aaa', //不能通过，元素必须是数组
                ['name' => 'football', 'level' => 'normal'], //通过
                ['name' => 'basketball', 'des' => 'haha'], //不能通过，因为没有level字段
                ['name' => 'sing', 'level' => 'normal', 'des' => 'song'], //通过
            ],
        ];

    ```

10. feild 最后为 [] 的验证规则，会作用于没有被之前的同级规则匹配到的所有元素。所以 feild 最后为 [] 的验证规则一般要写在同级验证规则的最后。

    ```php
    <?
        $rules = [
            [
                'field' => 'name[a]',
                'label' => 'A',
                'rules' => 'required|min_length[3]',
            ],
            [
                'field' => 'name[]',
                'label' => '其他',
                'rules' => 'required|min_length[5]',
            ],
            [
                'field' => 'name[b]',
                'label' => 'B',
                'rules' => 'required|min_length[2]',
            ],
        ];
        
        $_POST = [
            'name' => [
                'a' => 'aaaa', //通过
                'b' => 'bbbb', //此字段会先被 [] 匹配不能通过验证规则
                'c' => 'ccccc', //通过
            ],
        ];
    ```


### 修订和新增规则

|规则名|用法举例|描述|
|:---:|:---:|-------|
|required|-|表示字段必须存在，且不可以为空字符串或空数组|
|isset|-|表示字段必须存在，但可以为空字符串或空数组；isset 组合 上not_empty_str 相当于 required|
|not_empty_str|-|表示字段可以不传，但不能为空字符。即：可以没有此字段，但不能传空字符，一般用于一个验证规则有多种提交情况的时候|
|not_empty_array|-|表示字段可以不传，但不能为空数组。即：前端可以不传此字段，但不能传空数组，一般用于一个验证规则有多种提交情况的时候|
|filter_emoji|-|可在表单验证时用来过滤表情符号；另表单验证时普通字符串数据酌情使用 trim 过滤前后空字符|
|default_value|default[abc]|如果字段不存在或者值为空字符串的时候，给字段设置默认值|
|least_one_required|least_one_required[其他字段名]|两个字端不可同时不传或为空|
|valid_card|-|验证身份证号码，会实现校验逻辑|
|valid_username|-|验证字段值为手机号或邮箱其中一种|
|valid_phone|-|验证手机号|
|valid_tel|-|验证固定电话|
|valid_md5|-|验证md5后的字符串|
|max_length_gbk|max_length_gbk[20]|验证字端长度不超过20，英文为一个字符，中文为两个|
|min_length_gbk|min_length_gbk[30]|验证字端长度不小于30，英文为一个字符，中文为两个|
|date_later_than|date_later_than[2018-02] 或 date_later_than[其他字段名]|验证日期必须晚于设置的日期或字段值|
|date_before_than|date_before_than[2018-02] 或 date_later_than[其他字段名]|验证日期必须早于设置的日期或字段值|
|valid_date|valid_date 或 valid_date[-1] 或 valid_date[1]|验证日期合法性，参数 -1 表示过去的时间，1 表示未来的时间，不传参数表示合法日期即可|
|count_min|count_min[3]|表示数组元素数量不小于3个|
|count_max|count_max[5]|表示数组元素数量不超过5个|
|count_exact|count_exact[4]|表示数组元素数量为4个|


***



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
