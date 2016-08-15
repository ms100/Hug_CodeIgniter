<?php

class My_Loader extends CI_Loader{
    // --------------------------------------------------------------------

    /**
     * Database Loader
     *
     * @param    mixed $params Database configuration options
     * @param    bool $return Whether to return the database object
     * @param    bool $query_builder Whether to enable Query Builder
     *                    (overrides the configuration setting)
     *
     * @return    object|bool    Database object if $return is set to TRUE,
     *                    FALSE on failure, CI_Loader instance in any other case
     */
    public function database($params = '', $return = false, $query_builder = null){
        // Grab the super object
        $CI =& get_instance();

        if(is_string($params)){
            if(empty($params)){
                if(isset($CI->db)){
                    if($return == true){
                        return $CI->db;
                    }else{
                        return $this;
                    }
                }

                if(!file_exists($file_path = APPPATH . 'config/' . ENVIRONMENT . '/database.php') && !file_exists($file_path = APPPATH . 'config/database.php')){
                    show_error('The configuration file database.php does not exist.');
                }
                include($file_path);

                if(!isset($active_group)){
                    show_error('You have not specified a database connection group via $active_group in your config/database.php file.');
                }

                $params = $active_group;
                $mark = true;
            }

            if(isset($CI->dbs[$params])){
                if(isset($mark)){
                    $CI->db = $CI->dbs[''] = $CI->dbs[$params];
                }
                if($return == true){
                    return $CI->dbs[$params];
                }else{
                    return $this;
                }
            }
        }else{
            $return = true;
        }


        // Do we even need to load the database class?
        /*if($return === false && $query_builder === null && isset($CI->db) && is_object($CI->db) && !empty($CI->db->conn_id)){
            return false;
        }*/

        if(!function_exists('DB')){
            require(APPPATH . 'database/DB.php');
        }

        if($return == true){
            return DB($params, $query_builder);
        }

        // Initialize the db variable. Needed to prevent
        // reference errors with some configurations

        // Load the DB class
        $CI->dbs[$params] = DB($params, $query_builder);
        if(isset($mark)){
            $CI->db = $CI->dbs[''] = $CI->dbs[$params];
        }

        return $this;
    }

}
