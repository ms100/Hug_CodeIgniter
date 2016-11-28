<?php
/**
 * CodeIgniter
 * An open source application development framework for PHP
 * This content is released under the MIT License (MIT)
 * Copyright (c) 2014 - 2016, British Columbia Institute of Technology
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package      CodeIgniter
 * @author       EllisLab Dev Team
 * @copyright    Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright    Copyright (c) 2014 - 2016, British Columbia Institute of Technology (http://bcit.ca/)
 * @license      http://opensource.org/licenses/MIT	MIT License
 * @link         https://codeigniter.com
 * @since        Version 2.0.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * CodeIgniter Caching Class
 *
 * @package        CodeIgniter
 * @subpackage     Libraries
 * @category       Core
 * @author         EllisLab Dev Team
 * @link
 */
class CI_Cache
{

    /**
     * Valid cache drivers
     *
     * @var array
     */
    protected $valid_drivers = [
        'apc',
        'dummy',
        'file',
        'memcached',
        'redis',
        'wincache',
    ];

    /**
     * Path of cache files (if file-based cache)
     *
     * @var string
     */
    protected $_cache_path = null;

    /**
     * Reference to the driver
     *
     * @var mixed
     */
    protected $_adapter = null;

    /**
     * Fallback driver
     *
     * @var string
     */
    protected $_backup_driver = 'dummy';

    /**
     * Cache key prefix
     *
     * @var    string
     */
    public $key_prefix = '';

    /**
     * Cache key prefix length
     *
     * @var    integer
     */
    public $key_prefix_len = 0;//新增记录附加key的长度

    /**
     * List of methods in the parent class
     *
     * @var array
     */
    protected $_methods = [];

    /**
     * List of properties in the parent class
     *
     * @var array
     */
    protected $_properties = [];

    /**
     * Array of methods and properties for the parent class(es)
     *
     * @static
     * @var    array
     */
    protected static $_reflections = [];

    /**
     * Constructor
     * Initialize class properties based on the configuration array.
     *
     * @param    array $config = array()
     *
     * @return    void
     */
    public function __construct($config = [])
    {
        //isset($config['adapter']) && $this->_adapter = $config['adapter'];
        isset($config['adapter']) || $config['adapter'] = 'dummy';
        //isset($config['backup']) && $this->_backup_driver = $config['backup'];
        isset($config['key_prefix']) && $this->key_prefix = $config['key_prefix'];
        $this->key_prefix_len = strlen($this->key_prefix);//新增记录附加key的长度

        $this->load_driver($config['adapter'], isset($config['servers']) ? $config['servers'] : []);

        // If the specified adapter isn't available, check the backup.
        if (!$this->is_supported($config['adapter'])) {
            // Backup is supported. Set it to primary.
            log_message(
                'warn',
                'Cache adapter "' .
                $config['adapter'] .
                '" is unavailable. Falling back to "' .
                $this->_backup_driver .
                '" backup adapter.'
            );
            $this->load_driver('dummy');
        }
    }

    // ------------------------------------------------------------------------
    /**
     * 转码key，防止中文key产生错误
     *
     * @param $key
     *
     * @return string
     */
    public function _filter_key($key)
    {
        return base64_encode($this->key_prefix . $key);
    }

    // ------------------------------------------------------------------------
    /**
     * 解码key
     *
     * @param $key
     *
     * @return string
     */
    public function _de_filter_key($key)
    {
        return substr(base64_decode($key), $this->key_prefix_len);
    }

    // ------------------------------------------------------------------------

    /**
     * Get
     * Look for a value in the cache. If it exists, return the data
     * if not, return FALSE
     *
     * @param    string $id
     *
     * @return    mixed    value matching $id or FALSE on failure
     */
    public function get($id)
    {
        return $this->_adapter->get($this->_filter_key($id));
    }

    // ------------------------------------------------------------------------

    /**
     * Get Multi
     *
     * @param    array $ids Cache IDs
     *
     * @return    mixed    Data on success, FALSE on failure
     */
    public function getMulti($ids)
    {
        if (method_exists($this->_adapter, __FUNCTION__)) {
            foreach ($ids as $k => $id) {
                $ids[$k] = $this->_filter_key($id);
            }

            $data = $this->_adapter->getMulti($ids);

            $return = [];
            foreach ($data as $key => $value) {
                $return[$this->_de_filter_key($key)] = $value;
            }

            return $return;
        } else {
            $return = [];
            foreach ($ids as $id) {
                if (($data = $this->get($id)) !== false) {
                    $return[$id] = $data;
                }
            }

            return $return;
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Add
     *
     * @param    string $id   Cache ID
     * @param    mixed  $data Data being cached
     * @param    int    $ttl  Time to live
     *
     * @return    bool    TRUE on success, FALSE on failure
     */
    public function add($id, $data = 1, $ttl = 10)
    {
        if (method_exists($this->_adapter, __FUNCTION__)) {
            return $this->_adapter->add($this->_filter_key($id), $data, $ttl);
        } else {
            if ($this->get($id) === false) {
                return $this->save($id, $data, $ttl);
            } else {
                return false;
            }
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Set
     *
     * @param    string $id   Cache ID
     * @param    mixed  $data Data being cached
     * @param    int    $ttl  Time to live
     *
     * @return    bool    TRUE on success, FALSE on failure
     */
    public function set($id, $data, $ttl = 604800)
    {
        return $this->save($id, $data, $ttl);
    }

    // ------------------------------------------------------------------------

    /**
     * Cache Save
     *
     * @param    string $id   Cache ID
     * @param    mixed  $data Data to store
     * @param    int    $ttl  Cache TTL (in seconds)
     * @param    bool   $raw  Whether to store the raw value
     *
     * @return    bool    TRUE on success, FALSE on failure
     */
    public function save($id, $data, $ttl = 604800, $raw = false)
    {
        return $this->_adapter->save($this->_filter_key($id), $data, $ttl, $raw);
    }

    // ------------------------------------------------------------------------

    /**
     * Replace
     *
     * @param    string $id   Cache ID
     * @param    mixed  $data Data being cached
     * @param    int    $ttl  Time to live
     *
     * @return    bool    TRUE on success, FALSE on failure
     */
    public function replace($id, $data, $ttl = 604800)
    {
        if (method_exists($this->_adapter, __FUNCTION__)) {
            return $this->_adapter->replace($this->_filter_key($id), $data, $ttl);
        } else {
            $this->delete($id);

            return $this->save($id, $data, $ttl);
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Set Multi
     *
     * @param    mixed $data Data being cached
     * @param    int   $ttl  Time to live
     *
     * @return    bool    TRUE on success, FALSE on failure
     */
    public function setMulti($data, $ttl = 604800)
    {
        if (method_exists($this->_adapter, __FUNCTION__)) {
            $new_data = [];
            foreach ($data as $id => $value) {
                $new_data[$this->_filter_key($id)] = $value;
            }

            return $this->_adapter->setMulti($new_data, $ttl);
        } else {
            foreach ($data as $id => $value) {
                if (!$this->save($id, $value, $ttl)) {
                    return false;
                }
            }

            return true;
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Delete from Cache
     *
     * @param    string $id Cache ID
     *
     * @return    bool    TRUE on success, FALSE on failure
     */
    public function delete($id)
    {
        return $this->_adapter->delete($this->_filter_key($id));
    }

    // ------------------------------------------------------------------------

    /**
     * Delete Multi
     *
     * @param    array $ids Cache IDs
     *
     * @return    bool    TRUE on success, FALSE on failure
     */
    public function deleteMulti($ids)
    {
        if (method_exists($this->_adapter, __FUNCTION__)) {
            foreach ($ids as $k => $id) {
                $ids[$k] = $this->_filter_key($id);
            }

            return $this->_adapter->deleteMulti($ids);
        } else {
            foreach ($ids as $id) {
                $this->delete($id);
            }

            return true;
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Increment a raw value
     *
     * @param    string $id     Cache ID
     * @param    int    $offset Step/value to add
     *
     * @return    mixed    New value on success or FALSE on failure
     */
    public function increment($id, $offset = 1)
    {
        return $this->_adapter->increment($this->_filter_key($id), $offset);
    }

    // ------------------------------------------------------------------------

    /**
     * Decrement a raw value
     *
     * @param    string $id     Cache ID
     * @param    int    $offset Step/value to reduce by
     *
     * @return    mixed    New value on success or FALSE on failure
     */
    public function decrement($id, $offset = 1)
    {
        return $this->_adapter->decrement($this->_filter_key($id), $offset);
    }

    // ------------------------------------------------------------------------

    /**
     * Clean the cache
     *
     * @return    bool    TRUE on success, FALSE on failure
     */
    public function clean()
    {
        return $this->_adapter->clean();
    }

    // ------------------------------------------------------------------------

    /**
     * Cache Info
     *
     * @param    string $type = 'user'    user/filehits
     *
     * @return    mixed    array containing cache info on success OR FALSE on failure
     */
    public function cache_info($type = 'user')
    {
        return $this->_adapter->cache_info($type);
    }

    // ------------------------------------------------------------------------

    /**
     * Get Cache Metadata
     *
     * @param    string $id key to get cache metadata on
     *
     * @return    mixed    cache item metadata
     */
    public function get_metadata($id)
    {
        return $this->_adapter->get_metadata($this->_filter_key($id));
    }

    // ------------------------------------------------------------------------

    /**
     * Is the requested driver supported in this environment?
     *
     * @param    string $driver The driver to test
     *
     * @return    array
     */
    public function is_supported($driver)
    {
        static $support;

        if (!isset($support, $support[$driver])) {
            $support[$driver] = $this->_adapter->is_supported();
        }

        return $support[$driver];
    }

    /**
     * Load driver
     * Separate load_driver call to support explicit driver load by library or user
     *
     * @param    string $child Driver name (w/o parent prefix)
     * @param    array  $config
     *
     * @return    object    Child class
     */
    private function load_driver($child, array $config = [])
    {
        // The child will be prefixed with the parent lib
        $child_name = 'Cache_' . $child;

        // See if requested child is a valid driver
        if (!in_array($child, $this->valid_drivers)) {
            // The requested driver isn't valid!
            $msg = 'Invalid driver requested: ' . $child_name;
            log_message('error', $msg);
            show_error($msg);
        }

        // Use standard class name
        $class_name = 'CI_' . $child_name;
        if (!class_exists($class_name, false)) {
            $file = SHAREDPATH . 'libraries/Cache/drivers/' . $child_name . '.php';
            if (file_exists($file)) {
                // Include source
                include_once($file);
            }
        }

        // Did we finally find the class?
        if (!class_exists($class_name, false)) {
            if (class_exists($child_name, false)) {
                $class_name = $child_name;
            } else {
                $msg = 'Unable to load the requested driver: ' . $class_name;
                log_message('error', $msg);
                show_error($msg);
            }
        }

        // Instantiate, decorate and add child
        $obj = new $class_name($config);
        $this->decorate($class_name);
        $this->_adapter = $obj;
        return $this->_adapter;
    }

    /**
     * Decorate
     * Decorates the child with the parent driver lib's methods and properties
     *
     * @param    string $class_name
     *
     * @return    void
     */
    public function decorate($class_name)
    {
        if (!isset(self::$_reflections[$class_name])) {
            $r = new ReflectionClass($class_name);

            foreach ($r->getMethods() as $method) {
                if ($method->isPublic()) {
                    $this->_methods[] = $method->getName();
                }
            }

            foreach ($r->getProperties() as $prop) {
                if ($prop->isPublic()) {
                    $this->_properties[] = $prop->getName();
                }
            }

            self::$_reflections[$class_name] = [$this->_methods, $this->_properties];
        } else {
            list($this->_methods, $this->_properties) = self::$_reflections[$class_name];
        }
    }

    // --------------------------------------------------------------------

    /**
     * __call magic method
     * Handles access to the parent driver library's methods
     *
     * @param    string
     * @param    array
     *
     * @return    mixed
     */
    public function __call($method, $args = [])
    {
        if (in_array($method, $this->_methods)) {
            return call_user_func_array([$this->_adapter, $method], $args);
        }

        throw new BadMethodCallException('No such method: ' . $method . '()');
    }
}
