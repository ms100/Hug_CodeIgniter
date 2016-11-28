<?php


class My_Loader extends CI_Loader
{
    // --------------------------------------------------------------------

    /**
     * Database Loader
     *
     * @param    mixed $params        Database configuration options
     * @param    bool  $return        Whether to return the database object
     * @param    bool  $query_builder Whether to enable Query Builder
     *                                (overrides the configuration setting)
     *
     * @return    object|bool    Database object if $return is set to TRUE,
     *                    FALSE on failure, CI_Loader instance in any other case
     */
    public function database($params = '', $return = false, $query_builder = null)
    {
        // Grab the super object
        $CI =& get_instance();

        if (is_string($params)) {
            if (empty($params)) {
                if (isset($CI->db)) {
                    if ($return == true) {
                        return $CI->db;
                    } else {
                        return $this;
                    }
                }

                if (!file_exists($file_path = APPPATH . 'config/' . ENVIRONMENT . '/database.php') &&
                    !file_exists($file_path = APPPATH . 'config/database.php')
                ) {
                    show_error('The configuration file database.php does not exist.');
                }
                include($file_path);

                if (!isset($active_group)) {
                    show_error(
                        'You have not specified a database connection group via $active_group in your config/database.php file.'
                    );
                }

                $params = $active_group;
                $mark = true;
            }

            if (isset($CI->dbs[$params])) {
                if (isset($mark)) {
                    $CI->db = $CI->dbs[''] = $CI->dbs[$params];
                }
                if ($return == true) {
                    return $CI->dbs[$params];
                } else {
                    return $this;
                }
            }
        } else {
            $return = true;
        }


        // Do we even need to load the database class?
        /*if($return === false && $query_builder === null && isset($CI->db) && is_object($CI->db) && !empty($CI->db->conn_id)){
            return false;
        }*/

        if (!function_exists('DB')) {
            require(APPPATH . 'database/DB.php');
        }

        if ($return == true) {
            return DB($params, $query_builder);
        }

        // Initialize the db variable. Needed to prevent
        // reference errors with some configurations

        // Load the DB class
        $CI->dbs[$params] = DB($params, $query_builder);
        if (isset($mark)) {
            $CI->db = $CI->dbs[''] = $CI->dbs[$params];
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Cache Loader
     *
     * @param    mixed $params        Database configuration options
     * @param    bool  $return        Whether to return the database object
     *                                (overrides the configuration setting)
     *
     * @return    object|bool    Database object if $return is set to TRUE,
     *                    FALSE on failure, CI_Loader instance in any other case
     */
    public function cache($params = '', $return = false)
    {
        // Grab the super object
        $CI =& get_instance();

        if (empty($params)) {
            if (isset($CI->cache)) {
                if ($return == true) {
                    return $CI->cache;
                } else {
                    return $this;
                }
            }

            if (!file_exists($file_path = APPPATH . 'config/' . ENVIRONMENT . '/cache.php') &&
                !file_exists($file_path = APPPATH . 'config/cache.php')
            ) {
                show_error('The configuration file cache.php does not exist.');
            }
            include($file_path);

            if (!isset($cache_group)) {
                show_error(
                    'You have not specified a cache connection group via $active_group in your config/cache.php file.'
                );
            }

            $params = $cache_group;
            $mark = true;
        }

        if (is_string($params)) {
            if (isset($CI->caches[$params])) {
                if (isset($mark)) {
                    $CI->cache = $CI->caches[''] = $CI->caches[$params];
                }
                if ($return == true) {
                    return $CI->caches[$params];
                } else {
                    return $this;
                }
            }

            if (file_exists($file_path = APPPATH . 'config/cache.php')) {
                include($file_path);
            }
            if (file_exists($file_path = APPPATH . 'config/' . ENVIRONMENT . '/cache.php')) {
                include($file_path);
            }

            $config = isset($config[$params]) ? $config[$params] : [];
        } else {
            $return = true;
            $config = $params;
        }

        if (!class_exists('CI_Cache', false)) {
            require SHAREDPATH . 'libraries/Cache/Cache.php';
        }

        if ($return == true) {
            return new CI_Cache($config);
        }

        $CI->caches[$params] = new CI_Cache($config);
        if (isset($mark)) {
            $CI->cache = $CI->caches[''] = $CI->caches[$params];
        }

        return $this;
    }


    // --------------------------------------------------------------------

    /**
     * Driver Loader
     * Loads a driver library.
     *
     * @param    string|string[] $library     Driver name(s)
     * @param    array           $params      Optional parameters to pass to the driver
     * @param    string          $object_name An optional object name to assign to
     *
     * @return    object|bool    Object or FALSE on failure if $library is a string
     *                and $object_name is set. CI_Loader instance otherwise.
     */
    public function driver($library, $params = null, $object_name = null)
    {
        if (is_array($library)) {
            foreach ($library as $key => $value) {
                if (is_int($key)) {
                    $this->driver($value, $params);
                } else {
                    $this->driver($key, $params, $value);
                }
            }

            return $this;
        } elseif (empty($library)) {
            return false;
        }

        if (strtolower($library) == 'cache') {
            return $this->cache($params);
        }
        if (!class_exists('CI_Driver_Library', false)) {
            // We aren't instantiating an object here, just making the base class available
            require BASEPATH . 'libraries/Driver.php';
        }

        // We can save the loader some time since Drivers will *always* be in a subfolder,
        // and typically identically named to the library
        if (!strpos($library, '/')) {
            $library = ucfirst($library) . '/' . $library;
        }

        return $this->library($library, $params, $object_name);
    }
}
