<?php

namespace PsOneSixMigrator;

class UpgraderTools
{
    /**
     * configFilename contains all configuration specific to the psonesixmigrator module
     *
     * @var string
     * @access public
     */
    const CONFIG_FILENAME = 'config.json';
    /**
     * during upgradeFiles process,
     * this files contains the list of queries left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     */
    const TO_UPGRADE_QUERIES_LIST = 'queriesToUpgrade.json';
    /**
     * during upgradeFiles process,
     * this files contains the list of files left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     */
    const TO_UPGRADE_FILE_LIST = 'filesToUpgrade.json';
    /**
     * during upgradeModules process,
     * this files contains the list of modules left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     */
    const TO_UPGRADE_MODULE_LIST = 'modulesToUpgrade.json';
    /**
     * during upgradeFiles process,
     * this files contains the list of files left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     */
    const FILES_DIFF = 'filesDiff.json';
    /**
     * during backupFiles process,
     * this files contains the list of files left to save in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     */
    const TO_BACKUP_FILE_LIST = 'filesToBackup.json';
    /**
     * during backupDb process,
     * this files contains the list of tables left to save in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     */
    const TO_BACKUP_DB_LIST = 'tablesToBackup.json';
    /**
     * during restoreDb process,
     * this file contains a serialized array of queries which left to execute for restoring database
     * (this file is deleted in init() method if you reload the page)
     */
    const TO_RESTORE_QUERY_LIST = 'queryToRestore.json';
    /**
     * during restoreFiles process,
     * this file contains difference between queryToRestore and queries present in a backupFiles archive
     * (this file is deleted in init() method if you reload the page)
     */
    const TO_REMOVE_FILE_LIST = 'filesToRemove.json';
    /**
     * during restoreFiles process,
     * contains list of files present in backupFiles archive
     */
    const FROM_ARCHIVE_FILE_LIST = 'filesFromArchive.json';
    /**
     * `MAIL_CUSTOM_LIST` contains list of mails files which are customized,
     * relative to original files for the current PrestaShop version
     */
    const MAIL_CUSTOM_LIST = 'mails-custom.list';
    /**
     * `TRANSLATIONS_CUSTOM_LIST` contains list of mails files which are customized,
     * relative to original files for the current PrestaShop version
     */
    const TRANSLATIONS_CUSTOM_LIST = 'translations-custom.list';

    const KEEP_MAILS = 'keepMails';
    const DISABLE_CUSTOM_MODULES = 'disableCustomModules';
    const DISABLE_OVERRIDES = 'disableOverrides';
    const SWITCH_TO_DEFAULT_THEME = 'switchToDefaultTheme';
    const MANUAL_MODE = 'manualMode';
    const DISPLAY_ERRORS = 'displayErrors';
    const BACKUP = 'backup';
    const BACKUP_IMAGES = 'backupImages';
    const PERFORMANCE = 'performance';

    public $autoupgradePath;
    public $autoupgradeDir = 'psonesixmigrator';
    /**
     * modules_addons is an array of array(id_addons => name_module).
     *
     * @var array
     * @access public
     */
    /** @var  $lCache */
    public static $lCache;
    public $modules_addons = [];
    public $downloadPath;
    public $backupPath;
    public $latestPath;
    public $tmpPath;

    public $root_writable;
    public $root_writable_report;
    public $module_version;
    public $lastAutoupgradeVersion = '';

    /** @var array $error */
    public $errors = [];

    // Performance variables
    /** @var int $loopBackupFiles */
    public static $loopBackupFiles = 400;
    /**
     * Used for translations
     *
     * @var int $maxBackupFileSize
     */
    public static $maxBackupFileSize = 15728640;
    /** @var int $loopBackupDbTime */
    public static $loopBackupDbTime = 6;
    /** @var int $maxWrittenAllowed */
    public static $maxWrittenAllowed = 4194304;
    /** @var int $loopUpgradeFiles */
    public static $loopUpgradeFiles = 600;
    public static $loopRestoreFiles = 400; // json, xml
    public static $loopRestoreQueryTime = 6;
    public static $loopUpgradeModulesTime = 6;
    public static $loopRemoveSamples = 400;

    /** @var UpgraderTools $instance */
    protected static $instance;

    /**
     * Los UpgraderToolos Singletonos
     *
     * @return static
     *
     * @since 1.0.0
     */
    public static function getInstance()
    {
        if (!isset(static::$instance) && !is_object(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    protected function __construct()
    {
        $this->initPath();
    }

    /**
     * create some required directories if they does not exists
     */
    public function initPath()
    {
        // If not exists in this sessions, "create"
        // session handling : from current to next params

        // set autoupgradePath, to be used in backupFiles and backupDb config values
        $this->autoupgradePath = _PS_ADMIN_DIR_.DIRECTORY_SEPARATOR.$this->autoupgradeDir;
        // directory missing
        if (!file_exists($this->autoupgradePath)) {
            if (!mkdir($this->autoupgradePath)) {
                $this->errors[] = sprintf($this->l('unable to create directory %s'), $this->autoupgradePath);
            }
        }

        if (!is_writable($this->autoupgradePath)) {
            $this->errors[] = sprintf($this->l('Unable to write in the directory "%s"'), $this->autoupgradePath);
        }

        $this->downloadPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'download';
        if (!file_exists($this->downloadPath)) {
            if (!mkdir($this->downloadPath)) {
                $this->errors[] = sprintf($this->l('unable to create directory %s'), $this->downloadPath);
            }
        }

        $this->backupPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'backup';
        $tmp = "order deny,allow\ndeny from all";
        if (!file_exists($this->backupPath)) {
            if (!mkdir($this->backupPath)) {
                $this->errors[] = sprintf($this->l('unable to create directory %s'), $this->backupPath);
            }
        }
        if (!file_exists($this->backupPath.DIRECTORY_SEPARATOR.'index.php')) {
            if (!copy(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'index.php', $this->backupPath.DIRECTORY_SEPARATOR.'index.php')) {
                $this->errors[] = sprintf($this->l('unable to create file %s'), $this->backupPath.DIRECTORY_SEPARATOR.'index.php');
            }
        }
        if (!file_exists($this->backupPath.DIRECTORY_SEPARATOR.'.htaccess')) {
            if (!file_put_contents($this->backupPath.DIRECTORY_SEPARATOR.'.htaccess', $tmp)) {
                $this->errors[] = sprintf($this->l('unable to create file %s'), $this->backupPath.DIRECTORY_SEPARATOR.'.htaccess');
            }
        }

        // directory missing
        $this->latestPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'latest';
        if (!file_exists($this->latestPath)) {
            if (!mkdir($this->latestPath)) {
                $this->errors[] = sprintf($this->l('unable to create directory %s'), $this->latestPath);
            }
        }

        $this->tmpPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'tmp';
        if (!file_exists($this->tmpPath)) {
            if (!mkdir($this->tmpPath)) {
                $this->errors[] = sprintf($this->l('unable to create directory %s'), $this->tmpPath);
            }
        }
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    protected function initializePerformance()
    {
        // Performance settings, if your server has a low memory size, lower these values
        $perfArray = [
            'loopBackupFiles'        => [     400,      800,     1600],
            'maxBackupFileSize'      => [15728640, 31457280, 62914560],
            'loopBackupDbTime'       => [       6,       12,       25],
            'maxWrittenAllowed'      => [ 4194304,  8388608, 16777216],
            'loopUpgradeFiles'       => [     600,     1200,     2400],
            'loopRestoreFiles'       => [     400,      800,     1600],
            'loopRestoreQueryTime'   => [       6,       12,       25],
            'loopUpgradeModulesTime' => [       6,       12,       25],
            'loopRemoveSamples'      => [     400,      800,     1600],
        ];
        switch (static::getConfig('PS_AUTOUP_PERFORMANCE')) {
            case 3:
                foreach ($perfArray as $property => $values) {
                    self::$$property = $values[2];
                }
                break;
            case 2:
                foreach ($perfArray as $property => $values) {
                    self::$$property = $values[1];
                }
                break;
            case 1:
            default:
                foreach ($perfArray as $property => $values) {
                    self::$$property = $values[0];
                }
        }
    }

    /**
     * return the value of $key, configuration saved in `CONFIG_FILENAME`.
     * if $key is empty, will return an array with all configuration;
     *
     * @param string $key
     *
     * @access public
     * @return false|array|string
     *
     * @since 1.0.0
     *
     * @todo: move to `UpgraderTools`
     */
    public static function getConfig($key = '')
    {
        $tools = UpgraderTools::getInstance();
        if (file_exists($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME)) {
            $config = json_decode(file_get_contents($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME), true);
        } else {
            $config = [];
        }

        if (empty($key)) {
            return $config;
        } elseif (isset($config[$key])) {
            return trim($config[$key]);
        }

        return false;
    }

    /**
     * Set config value
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public static function setConfig($key, $value)
    {
        $tools = UpgraderTools::getInstance();
        if (!file_exists($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME)) {
            $config = static::initConfig();
        } else {
            $config = json_decode(file_get_contents($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME), true);
        }

        if (!is_array($config)) {
            $config = [];
        }

        $config[$key] = $value;

        return (bool) file_put_contents($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME, json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * update module configuration (saved in file `UpgraderTools::CONFIG_FILENAME`) with `$new_config`
     *
     * @param array $newConfig
     *
     * @return boolean true if success
     *
     * @since 1.0.0
     */
    public static function writeConfig($newConfig)
    {
        $tools = UpgraderTools::getInstance();
        if (!file_exists($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME)) {
            return (bool) static::resetConfig($newConfig);
        }

        $config = json_decode(file_get_contents($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME), true);

        foreach ($newConfig as $key => $val) {
            $config[$key] = $val;
        }

        if (!is_array($config)) {
            $config = [];
        }

        return (bool) file_put_contents($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME, json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * Initialize configuration
     *
     * @return false|array
     *
     * @since 1.0.0
     */
    protected function initConfig()
    {
        $newConfig = [];
        $upgrader = Upgrader::getInstance();
        $upgrader->channel = $newConfig['channel'];
        $upgrader->checkTbVersion();
        $newConfig['channel'] = $upgrader->channel;

        if (static::resetConfig($newConfig)) {
            return $newConfig;
        }

        return false;
    }

    /**
     * reset module configuration with $new_config values (previous config will be totally lost)
     *
     * @param array $newConfig
     *
     * @return boolean true if success
     *
     * @since 1.0.0
     */
    public static function resetConfig($newConfig)
    {
        $tools = UpgraderTools::getInstance();

        if (!is_array($newConfig)) {
            $newConfig = [];
        }

        return (bool) file_put_contents($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME, json_encode($newConfig, JSON_PRETTY_PRINT));
    }

    /**
     * @param mixed  $string
     * @param string $class
     * @param bool   $addslashes
     * @param bool   $htmlentities
     *
     * @return mixed|string
     *
     * @since 1.0.0
     */
    protected function l($string, $class = 'AdminThirtyBeesMigrateController', $addslashes = false, $htmlentities = true)
    {
        // need to be called in order to populate $classInModule
        $str = static::findTranslation('psonesixmigrator', $string, 'AdminThirtyBeesMigrateController');
        $str = $htmlentities ? str_replace('"', '&quot;', htmlentities($str, ENT_QUOTES, 'utf-8')) : $str;
        $str = $addslashes ? addslashes($str) : stripslashes($str);

        return $str;
    }

    /**
     * findTranslation (initially in Module class), to make translations works
     *
     * @param string $name   module name
     * @param string $string string to translate
     * @param string $source current class
     *
     * @return string translated string
     *
     * @since 1.0.0
     */
    public static function findTranslation($name, $string, $source)
    {
        static $_MODULES;

        $string = str_replace('\'', '\\\'', $string);

        if (!is_array($_MODULES)) {
            // note: $_COOKIE[iso_code] is set in createCustomToken();
            $filesToTry = [];
            if (isset($_COOKIE['iso_code']) && $_COOKIE['iso_code']) {
                $filesToTry = [
                    _PS_MODULE_DIR_.'psonesixmigrator'.DIRECTORY_SEPARATOR.'translations'.DIRECTORY_SEPARATOR.$_COOKIE['iso_code'].'.php', // 1.5
                    _PS_MODULE_DIR_.'psonesixmigrator'.DIRECTORY_SEPARATOR.$_COOKIE['iso_code'].'.php', // 1.4
                ];
            }
            // Translations may be in either '<autoupgradeDir>/translations/
            // iso_code.php' or '<autoupgradeDir>/iso_code.php', try both.
            foreach ($filesToTry as $file) {
                if (file_exists($file) && include($file) && isset($_MODULE)) {
                    $_MODULES = !empty($_MODULES) ? array_merge($_MODULES, $_MODULE) : $_MODULE;
                    break;
                }
            }
        }
        $cacheKey = $name.'|'.$string.'|'.$source;

        if (!isset(static::$lCache[$cacheKey])) {
            if (!is_array($_MODULES)) {
                return $string;
            }
            // set array key to lowercase for 1.3 compatibility
            $_MODULES = array_change_key_case($_MODULES);
            // note : we should use a variable to define the default theme (instead of "prestashop")
            $defaultKey = '<{'.strtolower($name).'}prestashop>'.strtolower($source).'_'.md5($string);
            $currentKey = $defaultKey;

            if (isset($_MODULES[$currentKey])) {
                $ret = stripslashes($_MODULES[$currentKey]);
            } elseif (isset($_MODULES[strtolower($currentKey)])) {
                $ret = stripslashes($_MODULES[strtolower($currentKey)]);
            } elseif (isset($_MODULES[$defaultKey])) {
                $ret = stripslashes($_MODULES[$defaultKey]);
            } elseif (isset($_MODULES[strtolower($defaultKey)])) {
                $ret = stripslashes($_MODULES[strtolower($defaultKey)]);
            } else {
                $ret = stripslashes($string);
            }

            static::$lCache[$cacheKey] = $ret;
        }

        return static::$lCache[$cacheKey];
    }
}
