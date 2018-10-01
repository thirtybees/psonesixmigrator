<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

namespace PsOneSixMigrator;

abstract class Db
{
    /**
     * Constants used by insert() method
     */
    const INSERT = 1;
    const INSERT_IGNORE = 2;
    const REPLACE = 3;
    /**
     * @var array List of DB instance
     */
    protected static $instance = [];
    /**
     * @var array Object instance for singleton
     */
    protected static $servers = [
        ['server' => _DB_SERVER_, 'user' => _DB_USER_, 'password' => _DB_PASSWD_, 'database' => _DB_NAME_], /* MySQL Master server */
        // Add here your slave(s) server(s)
        // array('server' => '192.168.0.15', 'user' => 'rep', 'password' => '123456', 'database' => 'rep'),
        // array('server' => '192.168.0.3', 'user' => 'myuser', 'password' => 'mypassword', 'database' => 'mydatabase'),
    ];
    /**
     * @var string Server (eg. localhost)
     */
    protected $server;
    /**
     * @var string Database user (eg. root)
     */
    protected $user;
    /**
     * @var string Database password (eg. can be empty !)
     */
    protected $password;
    /**
     * @var string Database name
     */
    protected $database;
    /**
     * @var bool
     */
    protected $isCacheEnabled;
    /**
     * @var mixed Ressource link
     */
    protected $link;
    /**
     * @var mixed SQL cached result
     */
    protected $result;
    /**
     * Store last executed query
     *
     * @var string
     */
    protected $last_query;

    /**
     * Last cached query
     *
     * @var string
     */
    protected $last_cached;

    /**
     * Instantiate database connection
     *
     * @param string $server   Server address
     * @param string $user     User login
     * @param string $password User password
     * @param string $database Database name
     * @param bool   $connect  If false, don't connect in constructor (since 1.5.0)
     */
    public function __construct($server, $user, $password, $database, $connect = true)
    {
        $this->server = $server;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->isCacheEnabled = (defined('_PS_CACHE_ENABLED_')) ? _PS_CACHE_ENABLED_ : false;

        if (!defined('_PS_DEBUG_SQL_')) {
            define('_PS_DEBUG_SQL_', false);
        }

        if (!defined('_PS_MAGIC_QUOTES_GPC_')) {
            define('_PS_MAGIC_QUOTES_GPC_', get_magic_quotes_gpc());
        }

        if ($connect) {
            $this->connect();
        }
    }

    /**
     * Open a connection
     */
    abstract public function connect();

    /**
     * Try a connection to the database
     *
     * @param string $server    Server address
     * @param string $user      Login for database connection
     * @param string $pwd       Password for database connection
     * @param string $db        Database name
     * @param bool   $newDbLink
     * @param bool   $engine
     * @param int    $timeout
     *
     * @return int
     */
    public static function checkConnection($server, $user, $pwd, $db, $newDbLink = true, $engine = null, $timeout = 5)
    {
        return call_user_func_array([Db::getClass(), 'tryToConnect'], [$server, $user, $pwd, $db, $newDbLink, $engine, $timeout]);
    }

    /**
     * Get child layer class
     *
     * @return string
     */
    public static function getClass()
    {
        return 'PsOneSixMigrator\\DbPDO';
    }

    /**
     * Try a connection to te database
     *
     * @param string $server Server address
     * @param string $user   Login for database connection
     * @param string $pwd    Password for database connection
     *
     * @return int
     */
    public static function checkEncoding($server, $user, $pwd)
    {
        return call_user_func_array([Db::getClass(), 'tryUTF8'], [$server, $user, $pwd]);
    }

    /**
     * Try a connection to the database and check if at least one table with same prefix exists
     *
     * @param string $server Server address
     * @param string $user   Login for database connection
     * @param string $pwd    Password for database connection
     * @param string $db     Database name
     * @param string $prefix Tables prefix
     *
     * @return bool
     */
    public static function hasTableWithSamePrefix($server, $user, $pwd, $db, $prefix)
    {
        return call_user_func_array([Db::getClass(), 'hasTableWithSamePrefix'], [$server, $user, $pwd, $db, $prefix]);
    }

    public static function checkCreatePrivilege($server, $user, $pwd, $db, $prefix, $engine)
    {
        return call_user_func_array([Db::getClass(), 'checkCreatePrivilege'], [$server, $user, $pwd, $db, $prefix, $engine]);
    }

    /**
     * @deprecated 1.5.0
     */
    public static function ps($sql, $useCache = 1)
    {
        $ret = Db::s($sql, $useCache);
        p($ret);

        return $ret;
    }

    /**
     * @deprecated 1.5.0
     */
    public static function s($sql, $useCache = true)
    {
        return Db::getInstance()->executeS($sql, true, $useCache);
    }

    /**
     * ExecuteS return the result of $sql as array
     *
     * @param string  $sql      query to execute
     * @param boolean $array    return an array instead of a mysql_result object (deprecated since 1.5.0, use query method instead)
     * @param bool    $useCache if query has been already executed, use its result
     *
     * @return array or result object
     */
    public function executeS($sql, $array = true, $useCache = false)
    {
        if ($sql instanceof DbQuery) {
            $sql = $sql->build();
        }

        // This method must be used only with queries which display results
        if (!preg_match('#^\s*\(?\s*(select|show|explain|describe|desc)\s#i', $sql)) {
            if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                Tools::displayError('Db->executeS() must be used only with select, show, explain or describe queries');
                exit();
            }

            return $this->execute($sql, $useCache);
        }

        $this->result = false;
        $this->last_query = $sql;

        $this->result = $this->query($sql);
        if (!$this->result) {
            return false;
        }

        $this->last_cached = false;
        if (!$array) {
            return $this->result;
        }

        $resultArray = [];
        while ($row = $this->nextRow($this->result)) {
            $resultArray[] = $row;
        }

        return $resultArray;
    }

    /**
     * Execute a query
     *
     * @param string $sql
     * @param bool   $useCache
     *
     * @return bool
     */
    public function execute($sql, $useCache = false)
    {
        if ($sql instanceof DbQuery) {
            $sql = $sql->build();
        }

        if (trim($sql) == false) {
            return ($this->result = true);
        }

        $this->result = $this->query($sql);

        return (bool) $this->result;
    }

    /* do not remove, useful for some modules */

    /**
     * Execute a query and get result ressource
     *
     * @param string $sql
     *
     * @return mixed
     */
    public function query($sql)
    {
        if ($sql instanceof DbQuery) {
            $sql = $sql->build();
        }

        $this->result = $this->_query($sql);
        if (_PS_DEBUG_SQL_) {
            $this->displayError($sql);
        }

        return $this->result;
    }

    /**
     * Execute a query and get result resource
     *
     * @param string $sql
     *
     * @return mixed
     */
    abstract protected function _query($sql);

    /**
     * Display last SQL error
     *
     * @param bool $sql
     */
    public function displayError($sql = false)
    {
        // @codingStandardsIgnoreStart
        global $webservice_call;

        $errno = $this->getNumberError();
        if ($webservice_call && $errno) {
            // @codingStandardsIgnoreEnd
            $dbg = debug_backtrace();
        } else {
            if (_PS_DEBUG_SQL_ && $errno && !defined('PS_INSTALLATION_IN_PROGRESS')) {
                if ($sql) {
                    Tools::displayError($this->getMsgError().'<br /><br /><pre>'.$sql.'</pre>');
                }
                Tools::displayError($this->getMsgError());
                exit();
            }
        }
    }

    /**
     * Returns the number of the error from previous database operation
     */
    abstract public function getNumberError();

    /**
     * Returns the text of the error message from previous database operation
     */
    abstract public function getMsgError();

    /**
     * Get next row for a query which doesn't return an array
     *
     * @param mixed $result
     */
    abstract public function nextRow($result = false);

    /**
     * Get Db object instance
     *
     * @param bool $master Decides whether the connection to be returned by the master server or the slave server
     *
     * @return Db instance
     */
    public static function getInstance($master = true)
    {
        static $id = 0;

        $totalServers = count(static::$servers);
        if ($master || $totalServers == 1) {
            $idServer = 0;
        } else {
            $id++;
            $idServer = ($totalServers > 2 && ($id % $totalServers) != 0) ? $id : 1;
        }

        if (!isset(static::$instance[$idServer])) {
            $class = Db::getClass();
            static::$instance[$idServer] = new $class(
                static::$servers[$idServer]['server'],
                static::$servers[$idServer]['user'],
                static::$servers[$idServer]['password'],
                static::$servers[$idServer]['database']
            );
        }

        return static::$instance[$idServer];
    }

    /**
     * @deprecated 1.5.0
     */
    public static function ds($sql, $useCache = 1)
    {
        Db::s($sql, $useCache);
        die();
    }

    /**
     * Get the ID generated from the previous INSERT operation
     */
    abstract public function Insert_ID();

    /**
     * Get number of affected rows in previous database operation
     */
    abstract public function Affected_Rows();

    /**
     * Get database version
     *
     * @return string
     */
    abstract public function getVersion();

    abstract public function set_db($dbName);

    /**
     * Close connection to database
     */
    public function __destruct()
    {
        if ($this->link) {
            $this->disconnect();
        }
    }

    /**
     * Close a connection
     */
    abstract public function disconnect();

    /**
     * Filter SQL query within a blacklist
     *
     * @param string $table  Table where insert/update data
     * @param string $values Data to insert/update
     * @param string $type   INSERT or UPDATE
     * @param string $where  WHERE clause, only for UPDATE (optional)
     * @param int    $limit  LIMIT clause (optional)
     *
     * @return mixed|boolean SQL query result
     */
    public function autoExecuteWithNullValues($table, $values, $type, $where = '', $limit = 0)
    {
        return $this->autoExecute($table, $values, $type, $where, $limit, 0, true);
    }

    /**
     * @deprecated 1.5.0 use insert() or update() method instead
     */
    public function autoExecute($table, $data, $type, $where = '', $limit = 0, $useCache = true, $useNull = false)
    {
        $type = strtoupper($type);
        switch ($type) {
            case 'INSERT':
                return $this->insert($table, $data, $useNull, $useCache, Db::INSERT, false);

            case 'INSERT IGNORE':
                return $this->insert($table, $data, $useNull, $useCache, Db::INSERT_IGNORE, false);

            case 'REPLACE':
                return $this->insert($table, $data, $useNull, $useCache, Db::REPLACE, false);

            case 'UPDATE':
                return $this->update($table, $data, $where, $limit, $useNull, $useCache, false);

            default:
                Tools::displayError('Wrong argument (miss type) in Db::autoExecute()');
                exit();
                break;
        }
    }

    /**
     * Execute an INSERT query
     *
     * @param string $table      Table name without prefix
     * @param array  $data       Data to insert as associative array. If $data is a list of arrays, multiple insert will be done
     * @param bool   $nullValues If we want to use NULL values instead of empty quotes
     * @param bool   $useCache
     * @param int    $type       Must be Db::INSERT or Db::INSERT_IGNORE or Db::REPLACE
     * @param bool   $addPrefix  Add or not _DB_PREFIX_ before table name
     *
     * @return bool
     */
    public function insert($table, $data, $nullValues = false, $useCache = true, $type = Db::INSERT, $addPrefix = true)
    {
        if (!$data && !$nullValues) {
            return true;
        }

        if ($addPrefix) {
            $table = _DB_PREFIX_.$table;
        }

        if ($type == Db::INSERT) {
            $insertKeyword = 'INSERT';
        } else {
            if ($type == Db::INSERT_IGNORE) {
                $insertKeyword = 'INSERT IGNORE';
            } else {
                if ($type == Db::REPLACE) {
                    $insertKeyword = 'REPLACE';
                } else {
                    Tools::displayError('Bad keyword, must be Db::INSERT or Db::INSERT_IGNORE or Db::REPLACE');
                    exit();
                }
            }
        }

        // Check if $data is a list of row
        $current = current($data);
        if (!is_array($current) || isset($current['type'])) {
            $data = [$data];
        }

        $keys = [];
        $valuesStringified = [];
        foreach ($data as $rowData) {
            $values = [];
            foreach ($rowData as $key => $value) {
                if (isset($keysStringified)) {
                    // Check if row array mapping are the same
                    if (!in_array("`$key`", $keys)) {
                        Tools::displayError('Keys form $data subarray don\'t match');
                        exit();
                    }
                } else {
                    $keys[] = "`$key`";
                }

                if (!is_array($value)) {
                    $value = ['type' => 'text', 'value' => $value];
                }
                if ($value['type'] == 'sql') {
                    $values[] = $value['value'];
                } else {
                    $values[] = $nullValues && ($value['value'] === '' || is_null($value['value'])) ? 'NULL' : "'{$value['value']}'";
                }
            }
            $keysStringified = implode(', ', $keys);
            $valuesStringified[] = '('.implode(', ', $values).')';
        }

        $sql = $insertKeyword.' INTO `'.$table.'` ('.$keysStringified.') VALUES '.implode(', ', $valuesStringified);

        return (bool) $this->q($sql, $useCache);
    }

    /**
     *
     * Execute a query
     *
     * @param string $sql
     * @param bool   $useCache
     *
     * @return mixed $result
     */
    protected function q($sql, $useCache = true)
    {
        if ($sql instanceof DbQuery) {
            $sql = $sql->build();
        }

        $this->result = false;
        $result = $this->query($sql);

        return $result;
    }

    /**
     * @param string $table      Table name without prefix
     * @param array  $data       Data to insert as associative array. If $data is a list of arrays, multiple insert will be done
     * @param string $where      WHERE condition
     * @param int    $limit
     * @param bool   $nullValues If we want to use NULL values instead of empty quotes
     * @param bool   $useCache
     * @param bool   $addPrefix  Add or not _DB_PREFIX_ before table name
     *
     * @return bool
     */
    public function update($table, $data, $where = '', $limit = 0, $nullValues = false, $useCache = true, $addPrefix = true)
    {
        if (!$data) {
            return true;
        }

        if ($addPrefix) {
            $table = _DB_PREFIX_.$table;
        }

        $sql = 'UPDATE `'.$table.'` SET ';
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                $value = ['type' => 'text', 'value' => $value];
            }
            if ($value['type'] == 'sql') {
                $sql .= "`$key` = {$value['value']},";
            } else {
                $sql .= ($nullValues && ($value['value'] === '' || is_null($value['value']))) ? "`$key` = NULL," : "`$key` = '{$value['value']}',";
            }
        }

        $sql = rtrim($sql, ',');
        if ($where) {
            $sql .= ' WHERE '.$where;
        }
        if ($limit) {
            $sql .= ' LIMIT '.(int) $limit;
        }

        return (bool) $this->q($sql, $useCache);
    }

    /**
     * Execute a DELETE query
     *
     * @param string $table     Name of the table to delete
     * @param string $where     WHERE clause on query
     * @param int    $limit     Number max of rows to delete
     * @param bool   $useCache  Use cache or not
     * @param bool   $addPrefix Add or not _DB_PREFIX_ before table name
     *
     * @return bool
     */
    public function delete($table, $where = '', $limit = 0, $useCache = true, $addPrefix = true)
    {
        if (_DB_PREFIX_ && !preg_match('#^'._DB_PREFIX_.'#i', $table) && $addPrefix) {
            $table = _DB_PREFIX_.$table;
        }

        $this->result = false;
        $sql = 'DELETE FROM `'.bqSQL($table).'`'.($where ? ' WHERE '.$where : '').($limit ? ' LIMIT '.(int) $limit : '');
        $res = $this->query($sql);

        return (bool) $res;
    }

    /**
     * getValue return the first item of a select query.
     *
     * @param mixed $sql
     * @param bool  $useCache
     *
     * @return mixed
     */
    public function getValue($sql, $useCache = true)
    {
        if ($sql instanceof DbQuery) {
            $sql = $sql->build();
        }

        if (!$result = $this->getRow($sql, $useCache)) {
            return false;
        }

        return array_shift($result);
    }

    /**
     * getRow return an associative array containing the first row of the query
     * This function automatically add "limit 1" to the query
     *
     * @param mixed $sql      the select query (without "LIMIT 1")
     * @param bool  $useCache find it in cache first
     *
     * @return array associative array of (field=>value)
     */
    public function getRow($sql, $useCache = false)
    {
        if ($sql instanceof DbQuery) {
            $sql = $sql->build();
        }

        $sql .= ' LIMIT 1';
        $this->result = false;
        $this->last_query = $sql;

        $this->result = $this->query($sql);
        if (!$this->result) {
            return false;
        }

        $this->last_cached = false;
        $result = $this->nextRow($this->result);

        return $result;
    }

    /**
     * Get number of rows for last result
     *
     * @return int
     */
    public function numRows()
    {
        if (!$this->last_cached && $this->result) {
            $nrows = $this->_numRows($this->result);

            return $nrows;
        }
    }

    /**
     * Get number of rows in a result
     *
     * @param mixed $result
     */
    abstract protected function _numRows($result);

    /**
     * Sanitize data which will be injected into SQL query
     *
     * @param string  $string SQL data which will be injected into SQL query
     * @param boolean $htmlOk Does data contain HTML code ? (optional)
     *
     * @return string Sanitized data
     */
    public function escape($string, $htmlOk = false)
    {
        if (_PS_MAGIC_QUOTES_GPC_) {
            $string = stripslashes($string);
        }
        if (!is_numeric($string)) {
            $string = $this->_escape($string);
            if (!$htmlOk) {
                $string = strip_tags(Tools::nl2br($string));
            }
        }

        return $string;
    }

    /**
     * Protect string against SQL injections
     *
     * @param string $str
     *
     * @return string
     */
    abstract public function _escape($str);
}
