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

/**
 * This class is currently only here for tests
 *
 * @since 1.0.0
 */
class DbPDO extends Db
{
    /**
     * @param string $server
     * @param string $user
     * @param string $pwd
     * @param string $db
     * @param string $prefix
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public static function hasTableWithSamePrefix($server, $user, $pwd, $db, $prefix)
    {
        try {
            $link = DbPDO::_getPDO($server, $user, $pwd, $db, 5);
        } catch (\PDOException $e) {
            return false;
        }

        $sql = 'SHOW TABLES LIKE \''.$prefix.'%\'';
        $result = $link->query($sql);

        return (bool) $result->fetch();
    }

    public static function checkCreatePrivilege($server, $user, $pwd, $db, $prefix, $engine)
    {
        try {
            $link = DbPDO::_getPDO($server, $user, $pwd, $db, 5);
        } catch (\PDOException $e) {
            return false;
        }

        $sql = '
			CREATE TABLE `'.$prefix.'test` (
			`test` tinyint(1) unsigned NOT NULL
			) ENGINE=MyISAM';
        $result = $link->query($sql);
        if (!$result) {
            $error = $link->errorInfo();

            return $error[2];
        }
        $link->query('DROP TABLE `'.$prefix.'test`');

        return true;
    }

    /**
     * @see Db::checkConnection()
     */
    public static function tryToConnect($server, $user, $pwd, $db, $newDbLink = true, $engine = null, $timeout = 5)
    {
        try {
            $link = DbPDO::_getPDO($server, $user, $pwd, $db, $timeout);
        } catch (\PDOException $e) {
            return ($e->getCode() == 1049) ? 2 : 1;
        }

        if (strtolower($engine) == 'innodb') {
            $sql = 'SHOW VARIABLES WHERE Variable_name = \'have_innodb\'';
            $result = $link->query($sql);
            if (!$result) {
                return 4;
            }
            $row = $result->fetch();
            if (!$row || strtolower($row['Value']) != 'yes') {
                return 4;
            }
        }
        unset($link);

        return 0;
    }

    /**
     * @see Db::checkEncoding()
     */
    public static function tryUTF8($server, $user, $pwd)
    {
        try {
            $link = DbPDO::_getPDO($server, $user, $pwd, false, 5);
        } catch (PDOException $e) {
            return false;
        }
        $result = $link->exec('SET NAMES \'utf8\'');
        unset($link);

        return ($result === false) ? false : true;
    }

    /**
     * @see DbCore::connect()
     */
    public function connect()
    {
        try {
            $this->link = $this->_getPDO($this->server, $this->user, $this->password, $this->database, 5);
        } catch (\PDOException $e) {
            die(sprintf(Tools::displayError('Link to database cannot be established: %s'), $e->getMessage()));
            exit();
        }

        // UTF-8 support
        if (!is_object($this->link) || $this->link->exec('SET NAMES \'utf8\'') === false) {
            Tools::displayError('PrestaShop Fatal error: no utf-8 support. Please check your server configuration.');
            exit();
        }

        return $this->link;
    }

    protected static function _getPDO($host, $user, $password, $dbname, $timeout = 5)
    {
        $dsn = 'mysql:';
        if ($dbname) {
            $dsn .= 'dbname='.$dbname.';';
        }
        if (preg_match('/^(.*):([0-9]+)$/', $host, $matches)) {
            $dsn .= 'host='.$matches[1].';port='.$matches[2];
        } elseif (preg_match('#^.*:(/.*)$#', $host, $matches)) {
            $dsn .= 'unix_socket='.$matches[1];
        } else {
            $dsn .= 'host='.$host;
        }

        return new \PDO($dsn, $user, $password, [\PDO::ATTR_TIMEOUT => $timeout, \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]);
    }

    /**
     * @see DbCore::disconnect()
     */
    public function disconnect()
    {
        unset($this->link);
    }

    /**
     * @see DbCore::nextRow()
     */
    public function nextRow($result = false)
    {
        if (!$result) {
            $result = $this->result;
        }
        if (!is_object($result)) {
            return false;
        }

        return $result->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * @see DbCore::Insert_ID()
     */
    public function Insert_ID()
    {
        return $this->link->lastInsertId();
    }

    /**
     * @see DbCore::Affected_Rows()
     */
    public function Affected_Rows()
    {
        return $this->result->rowCount();
    }

    /**
     * @see DbCore::getMsgError()
     */
    public function getMsgError($query = false)
    {
        $error = $this->link->errorInfo();

        return ($error[0] == '00000') ? '' : $error[2];
    }

    /**
     * @see DbCore::getNumberError()
     */
    public function getNumberError()
    {
        $error = $this->link->errorInfo();

        return isset($error[1]) ? $error[1] : 0;
    }

    /**
     * @see DbCore::getVersion()
     */
    public function getVersion()
    {
        return $this->getValue('SELECT VERSION()');
    }

    /**
     * @see DbCore::_escape()
     */
    public function _escape($str)
    {
        $search = ["\\", "\0", "\n", "\r", "\x1a", "'", '"'];
        $replace = ["\\\\", "\\0", "\\n", "\\r", "\Z", "\'", '\"'];

        return str_replace($search, $replace, $str);
    }

    /**
     * @see DbCore::set_db()
     */
    public function set_db($dbName)
    {
        return $this->link->exec('USE '.pSQL($dbName));
    }

    /**
     * @see DbCore::_query()
     */
    protected function _query($sql)
    {
        return $this->link->query($sql);
    }

    /**
     * @see DbCore::_numRows()
     */
    protected function _numRows($result)
    {
        return $result->rowCount();
    }
}
