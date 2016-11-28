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
 * @since        Version 2.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * CodeIgniter Memcached Caching Class
 *
 * @package        CodeIgniter
 * @subpackage     Libraries
 * @category       Core
 * @author         EllisLab Dev Team
 * @link
 */
class CI_Cache_memcached
{

    /**
     * Holds the memcached object
     *
     * @var object
     */
    protected $_memcached;

    /**
     * Memcached configuration
     *
     * @var array
     */
    protected $_config = [
        'default' => [
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 1,
        ],
    ];

    // ------------------------------------------------------------------------

    /**
     * Class constructor
     * Setup Memcache(d)
     *
     * @param array $config
     *
     * @return    void
     */
    public function __construct(array $config)
    {
        $defaults = $this->_config['default'];
        $this->_config = $config;

        if (class_exists('Memcached', false)) {
            $this->_memcached = new Memcached();
        } elseif (class_exists('Memcache', false)) {
            $this->_memcached = new Memcache();
        } else {
            log_message('error', 'Cache: Failed to create Memcache(d) object; extension not loaded?');

            return;
        }

        foreach ($this->_config as $cache_server) {
            isset($cache_server['hostname']) OR $cache_server['hostname'] = $defaults['host'];
            isset($cache_server['port']) OR $cache_server['port'] = $defaults['port'];
            isset($cache_server['weight']) OR $cache_server['weight'] = $defaults['weight'];

            if ($this->_memcached instanceof Memcache) {
                // Third parameter is persistance and defaults to TRUE.
                $this->_memcached->addServer(
                    $cache_server['hostname'],
                    $cache_server['port'],
                    true,
                    $cache_server['weight']
                );
            } elseif ($this->_memcached instanceof Memcached) {
                $this->_memcached->addServer(
                    $cache_server['hostname'],
                    $cache_server['port'],
                    $cache_server['weight']
                );
            }
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Fetch from cache
     *
     * @param    string $id Cache ID
     *
     * @return    mixed    Data on success, FALSE on failure
     */
    public function get($id)
    {
        return $this->_memcached->get($id);
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
        if ($this->_memcached instanceof Memcached) {
            return $this->_memcached->getMulti($ids);
        } elseif ($this->_memcached instanceof Memcache) {
            return $this->_memcached->get($ids);
        }

        return false;
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
        if ($this->_memcached instanceof Memcached) {
            return $this->_memcached->add($id, $data, $ttl);
        } elseif ($this->_memcached instanceof Memcache) {
            return $this->_memcached->add($id, $data, 0, $ttl);
        }

        return false;
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
     * Save
     *
     * @param    string $id   Cache ID
     * @param    mixed  $data Data being cached
     * @param    int    $ttl  Time to live
     *
     * @return    bool    TRUE on success, FALSE on failure
     */
    public function save($id, $data, $ttl = 604800, $raw = false)
    {
        if ($this->_memcached instanceof Memcached) {
            return $this->_memcached->set($id, $data, $ttl);
        } elseif ($this->_memcached instanceof Memcache) {
            return $this->_memcached->set($id, $data, 0, $ttl);
        }

        return false;
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
        if ($this->_memcached instanceof Memcached) {
            return $this->_memcached->replace($id, $data, $ttl);
        } elseif ($this->_memcached instanceof Memcache) {
            return $this->_memcached->replace($id, $data, 0, $ttl);
        }

        return false;
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
        if ($this->_memcached instanceof Memcached) {
            return $this->_memcached->setMulti($data, $ttl);
        } elseif ($this->_memcached instanceof Memcache) {
            foreach ($data as $id => $value) {
                if (!$this->_memcached->set($id, $value, 0, $ttl)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * Delete from Cache
     *
     * @param    mixed $id key to be deleted.
     *
     * @return    bool    true on success, false on failure
     */
    public function delete($id)
    {
        return $this->_memcached->delete($id);
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
        if ($this->_memcached instanceof Memcached) {
            return $this->_memcached->deleteMulti($ids);
        } elseif ($this->_memcached instanceof Memcache) {
            foreach ($ids as $id) {
                $this->_memcached->delete($id);
            }

            return true;
        }

        return false;
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
        return $this->_memcached->increment($id, $offset);
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
        return $this->_memcached->decrement($id, $offset);
    }

    // ------------------------------------------------------------------------

    /**
     * Clean the Cache
     *
     * @return    bool    false on failure/true on success
     */
    public function clean()
    {
        return $this->_memcached->flush();
    }

    // ------------------------------------------------------------------------

    /**
     * Cache Info
     *
     * @return    mixed    array on success, false on failure
     */
    public function cache_info()
    {
        return $this->_memcached->getStats();
    }

    // ------------------------------------------------------------------------

    /**
     * Get Cache Metadata
     *
     * @param    mixed $id key to get cache metadata on
     *
     * @return    mixed    FALSE on failure, array on success.
     */
    public function get_metadata($id)
    {
        //方法保留只是为了兼容
        $data = $this->_memcached->get($id);

        return [
            'expire' => 0,
            'mtime' => 0,
            'data' => $data,
        ];
    }

    // ------------------------------------------------------------------------

    /**
     * Is supported
     * Returns FALSE if memcached is not supported on the system.
     * If it is, we setup the memcached object & return TRUE
     *
     * @return    bool
     */
    public function is_supported()
    {
        return (extension_loaded('memcached') OR extension_loaded('memcache'));
    }

    // ------------------------------------------------------------------------

    /**
     * Class destructor
     * Closes the connection to Memcache(d) if present.
     *
     * @return    void
     */
    public function __destruct()
    {
        if ($this->_memcached instanceof Memcache) {
            $this->_memcached->close();
        } elseif ($this->_memcached instanceof Memcached && method_exists($this->_memcached, 'quit')) {
            $this->_memcached->quit();
        }
    }
}
