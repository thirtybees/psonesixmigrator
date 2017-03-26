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


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PsOneSixMigrator\Upgrader;
use PsOneSixMigrator\ConfigurationTest;
use PsOneSixMigrator\UpgraderTools;

require_once __DIR__.'/../../classes/autoload.php';

/**
 * Class AdminThirtyBeesMigrate
 *
 * @since 1.0.0
 */
class AdminThirtyBeesMigrateController extends AdminController
{
    // @codingStandardsIgnoreStart
    /** @var  $lCache */
    public static $lCache;

    public $multishop_context;
    public $multishop_context_group = false;
    public $html = '';
    public $noTabLink = [];
    public $id = -1;
    public $ajax = false;
    public $nextResponseType = 'json';
    public $next = 'N/A';
    /** @var PsOneSixMigrator\Upgrader $upgrader */
    public $upgrader;

    public $standalone = true;

    public $bootstrap = true;


    /** @var array $templateVars */
    public $templateVars = [];

    public $installVersion;
    public $keepImages = null;
    public $updateDefaultTheme = null;
    public $changeToDefaultTheme = null;
    public $keepMails = null;
    public $manualMode = null;
    public $deactivateCustomModule = null;
    public $sampleFileList = [];
    public $_fieldsUpgradeOptions = [];
    public $_fieldsBackupOptions = [];
    protected $_includeContainer = true;
    protected $tools;
    private $install_autoupgrade_dir; // 15 Mo
    private $restoreIgnoreFiles = [];
    private $restoreIgnoreAbsoluteFiles = []; // 4096 ko
    private $backupIgnoreFiles = [];
    private $backupIgnoreAbsoluteFiles = [];
    private $excludeFilesFromUpgrade = [];
    private $excludeAbsoluteFilesFromUpgrade = [];

    protected $lastAutoupgradeVersion;

    /* ## usage
     * key = the step you want to skip
     * value = the next step you want instead
     * example: `public static $skipAction = array();`
     * initial order upgrade:
     *   - download
     *   - unzip
     *   - removeSamples
     *   - backupFiles
     *   - backupDb
     *   - upgradeFiles
     *   - upgradeDb
     *   - upgradeModules
     *   - cleanDatabase
     *   - upgradeComplete
     * initial order rollback:
     *   - rollback
     *   - restoreFiles
     *   - restoreDb
     *   - rollbackComplete
     */
    protected $backupName = null;
    protected $backupFilesFilename = null;
    protected $backupDbFilename = null;
    protected $restoreFilesFilename = null;
    protected $restoreDbFilenames = [];
    // @codingStandardsIgnoreEnd

    /**
     * AdminThirtyBeesMigrateController constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        global $ajax;

        if (!empty($ajax)) {
            $this->ajax = true;
        }

        $this->tools = UpgraderTools::getInstance();

        parent::__construct();

        // Database instantiation (need to be cached because there will be at least 100k calls in the upgrade process
        $this->db = Db::getInstance();
        $this->tools = UpgraderTools::getInstance();

        $fileTab = @filemtime($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php');
        $file = @filemtime(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$this->tools->autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php');

        if ($fileTab < $file) {
            @copy(
                _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$this->autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php',
                $this->tools->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php'
            );
        }

        if (version_compare(_PS_VERSION_, '1.6.1.0', '>=') && !$this->ajax) {
            Context::getContext()->smarty->assign('display_header_javascript', true);
        }
    }

    /**
     * init to build informations we need
     *
     * @return void
     */
    public function init()
    {
        parent::init();

        // test writable recursively
        $upgrader = new Upgrader();
        preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
        if (isset($matches[1])) {
            $upgrader->branch = $matches[1];
        }
        $channel = static::getConfig('channel');
        switch ($channel) {
            case 'archive':
                $this->installVersion = static::getConfig('archive.version_num');
                $this->tools->destDownloadFilename = static::getConfig('archive.filename');
                $upgrader->checkPSVersion(true, ['archive']);
                break;
            case 'directory':
                $this->installVersion = static::getConfig('directory.version_num');
                $upgrader->checkPSVersion(true, ['directory']);
                break;
            default:
                $upgrader->channel = $channel;
                if (static::getConfig('channel') == 'private' && !static::getConfig('private_allow_major')) {
                    $upgrader->checkPSVersion(true, ['private', 'minor']);
                } else {
                    $upgrader->checkPSVersion(true, ['minor']);
                }
                $this->installVersion = $upgrader->versionNum;
        }

        $this->upgrader = $upgrader;
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function initContent()
    {
        parent::initContent();

        $tools = UpgraderTools::getInstance();

        /* PrestaShop demo mode */
        if (defined('_PS_MODE_DEMO_') && _PS_MODE_DEMO_) {
            $html = '<div class="error">'.$this->l('This functionality has been disabled.').'</div>';
            $this->context->smarty->assign('updaterContent', $html);
            $this->context->smarty->assign('content', $html);

            return;
        }

        if (!file_exists($tools->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php')) {
            $html = '<div class="alert alert-danger">'.$this->l('[TECHNICAL ERROR] ajax-upgradetab.php is missing. Please reinstall or reset the module.').'</div>';
            $this->context->smarty->assign('updaterContent', $html);
            $this->context->smarty->assign('content', $html);

            return;
        }

        $html = '<div class="row">';
        $html .= $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/welcome.phtml');

        /* Checks/requirements and "Upgrade PrestaShop now" blocks */
        $html .= $this->displayCurrentConfiguration();
        $html .= $this->displayBlockUpgradeButton();

        $html .= $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/anotherchecklist.phtml');
        $html .= $this->displayRollbackForm();

        $html .= $this->getJsInit();
        $html .= '</div>';

        $this->context->smarty->assign('updaterContent', $html);
//
        $this->context->smarty->assign('content', $html);
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
            // translations may be in "autoupgrade/translations/iso_code.php" or "autoupgrade/iso_code.php",
            // try both locations.
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
        static $config = [];
        if (count($config) == 0) {
            $tools = UpgraderTools::getInstance();
            if (file_exists($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME)) {
                $configContent = Tools::file_get_contents($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME);
                $config = @unserialize(base64_decode($configContent));
            } else {
                $config = [];
            }
        }
        if (empty($key)) {
            return $config;
        } elseif (isset($config[$key])) {
            return trim($config[$key]);
        }

        return false;
    }

    /**
     * create cookies id_employee, id_tab and autoupgrade (token)
     *
     * @return false
     *
     * @since 1.0.0
     */
    public function createCustomToken()
    {
        // ajax-mode for autoupgrade, we can't use the classic authentication
        // so, we'll create a cookie in admin dir, based on cookie key
        $cookie = Context::getContext()->cookie;
        $idEmployee = $cookie->id_employee;
        if ($cookie->id_lang) {
            $isoCode = $_COOKIE['iso_code'] = Language::getIsoById((int) $cookie->id_lang);
        } else {
            $isoCode = 'en';
        }
        $adminDir = trim(str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_), DIRECTORY_SEPARATOR);
        $cookiePath = __PS_BASE_URI__.$adminDir;
        setcookie('id_employee', $idEmployee, 0, $cookiePath);
        setcookie('id_tab', $this->id, 0, $cookiePath);
        setcookie('iso_code', $isoCode, 0, $cookiePath);
        setcookie('autoupgrade', Tools::encrypt($idEmployee), 0, $cookiePath);

        return false;
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function postProcess()
    {
        $this->setFields();

        // set default configuration to default channel & dafault configuration for backup and upgrade
        // (can be modified in expert mode)
        $config = static::getConfig('channel');
        if ($config === false) {
            $config = [];
            $config['channel'] = Upgrader::DEFAULT_CHANNEL;
            $this->writeConfig($config);
            if (class_exists('Configuration', false)) {
                Configuration::updateValue('PS_UPGRADE_CHANNEL', $config['channel']);
            }

            $this->writeConfig(
                [
                    'PS_AUTOUP_PERFORMANCE'          => '1',
                    'PS_AUTOUP_CUSTOM_MOD_DESACT'    => '1',
                    'PS_AUTOUP_UPDATE_DEFAULT_THEME' => '1',
                    'PS_AUTOUP_CHANGE_DEFAULT_THEME' => '0',
                    'PS_AUTOUP_KEEP_MAILS'           => '1',
                    'PS_AUTOUP_BACKUP'               => '1',
                    'PS_AUTOUP_KEEP_IMAGES'          => '0',
                ]
            );
        }

        if (Tools::isSubmit('putUnderMaintenance') && version_compare(_PS_VERSION_, '1.5.0.0', '>=')) {
            foreach (Shop::getCompleteListOfShopsID() as $idShop) {
                Configuration::updateValue('PS_SHOP_ENABLE', 0, false, null, (int) $idShop);
            }
            Configuration::updateGlobalValue('PS_SHOP_ENABLE', 0);
        } elseif (Tools::isSubmit('putUnderMaintenance')) {
            Configuration::updateValue('PS_SHOP_ENABLE', 0);
        }

        if (Tools::isSubmit('customSubmitAutoUpgrade')) {
            $configKeys = array_keys(array_merge($this->_fieldsUpgradeOptions, $this->_fieldsBackupOptions));
            $config = [];
            foreach ($configKeys as $key) {
                if (isset($_POST[$key])) {
                    $config[$key] = $_POST[$key];
                }
            }
            $res = $this->writeConfig($config);
            if ($res) {
                Tools::redirectAdmin($this->currentIndex.'&conf=6&token='.Tools::getValue('token'));
            }
        }

        if (Tools::isSubmit('deletebackup')) {
            $res = false;
            $name = Tools::getValue('name');
            $tools = UpgraderTools::getInstance();
            $filelist = scandir($tools->backupPath);
            foreach ($filelist as $filename) {
                // the following will match file or dir related to the selected backup
                if (!empty($filename) && $filename[0] != '.' && $filename != 'index.php' && $filename != '.htaccess'
                    && preg_match('#^(auto-backupfiles_|)'.preg_quote($name).'(\.zip|)$#', $filename, $matches)
                ) {
                    if (is_file($tools->backupPath.DIRECTORY_SEPARATOR.$filename)) {
                        $res &= unlink($tools->backupPath.DIRECTORY_SEPARATOR.$filename);
                    } elseif (!empty($name) && is_dir($tools->backupPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR)) {
                        $res = Tools::deleteDirectory($tools->backupPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR, true);
                    }
                }
            }
            if ($res) {
                Tools::redirectAdmin(static::$currentIndex.'&conf=1&token='.Tools::getValue('token'));
            } else {
                $this->_errors[] = sprintf($this->l('Error when trying to delete backups %s'), $name);
            }
        }
        parent::postProcess();
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
    public function writeConfig($newConfig)
    {
        $tools = UpgraderTools::getInstance();
        if (!file_exists($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME)) {
            $this->upgrader->channel = $newConfig['channel'];
            $this->upgrader->checkPSVersion();
            $this->installVersion = $this->upgrader->versionNum;

            return $this->resetConfig($newConfig);
        }

        $config = file_get_contents($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME);
        $configUnserialized = @unserialize(base64_decode($config));
        if (!is_array($configUnserialized)) {
            $configUnserialized = @unserialize($config);
        } // retrocompat, before base64_decode implemented
        $config = $configUnserialized;

        foreach ($newConfig as $key => $val) {
            $config[$key] = $val;
        }
        $this->next_desc = $this->l('Configuration successfully updated.').' <strong>'.$this->l('This page will now be reloaded and the module will check if a new version is available.').'</strong>';

        return (bool) file_put_contents($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME, base64_encode(serialize($config)));
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
    public function resetConfig($newConfig)
    {
        $tools = UpgraderTools::getInstance();

        return (bool) file_put_contents($tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::CONFIG_FILENAME, base64_encode(serialize($newConfig)));
    }

    /**
     * @return array
     *
     * @since 1.0.0
     */
    public function getCheckCurrentPsConfig()
    {
        static $allowedArray;

        if (empty($allowedArray)) {
            $allowedArray = [];
            $allowedArray['fopen'] = ConfigurationTest::test_fopen() || ConfigurationTest::test_curl();
            $allowedArray['root_writable'] = $this->getRootWritable();
            $tools = UpgraderTools::getInstance();
            $adminDir = trim(str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_), DIRECTORY_SEPARATOR);
            $allowedArray['admin_au_writable'] = ConfigurationTest::test_dir($adminDir.DIRECTORY_SEPARATOR.$tools->autoupgradeDir, false, $report);
            $allowedArray['shop_deactivated'] = (!Configuration::get('PS_SHOP_ENABLE') || (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['127.0.0.1', 'localhost'])));
            $allowedArray['cache_deactivated'] = !(defined('_PS_CACHE_ENABLED_') && _PS_CACHE_ENABLED_);
            $allowedArray['module_version_ok'] = $this->checkAutoupgradeLastVersion();
        }

        return $allowedArray;
    }

    /**
     * @return bool|null
     *
     * @since 1.0.0
     */
    public function getRootWritable()
    {
        // Root directory permissions cannot be checked recursively anymore, it takes too much time
        $tools = UpgraderTools::getInstance();
        $tools->root_writable = ConfigurationTest::test_dir('/', false, $report);
        $tools->root_writable_report = $report;

        return $tools->root_writable;
    }

    /**
     * @return bool|mixed|string
     *
     * @since 1.0.0
     */
    public function checkAutoupgradeLastVersion()
    {
        if ($this->getModuleVersion()) {
            $this->lastAutoupgradeVersion = version_compare('1.0.0', $this->upgrader->autoupgradeLastVersion, '>=');
        } else {
            $this->lastAutoupgradeVersion = true;
        }

        return $this->lastAutoupgradeVersion;
    }

    /**
     * @return bool|null|string
     *
     * @since 1.0.0
     */
    public function getModuleVersion()
    {
        return false;
    }

    /**
     * @return float|int
     *
     * @since 1.0.0
     */
    public function configOk()
    {
        $allowedArray = $this->getCheckCurrentPsConfig();
        $allowed = array_product($allowedArray);

        return $allowed;
    }

    /**
     * _displayBlockUpgradeButton
     * display the summary current version / target vesrion + "Upgrade Now" button with a "more options" button
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function displayBlockUpgradeButton()
    {
        return $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/blockupgradebutton.phtml');
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function getBlockConfigurationAdvanced()
    {
        return $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/advanced.phtml');
    }

    /**
     * @param string $channel
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function getBlockSelectChannel($channel = 'master')
    {
        $download = $this->tools->downloadPath.DIRECTORY_SEPARATOR;
        $params = [
            'optChannels'     => ['master', 'stable', 'beta'],
            'selectedChannel' => is_string($channel) ? $channel : 'master',
            'download'        => $download,
            'channelDir'      => glob($download.'*.zip'),
            'archiveFilename' => static::getConfig('archive.filename'),
        ];

        return $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/channelselector.phtml', $params);
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function displayDevTools()
    {
        return $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/devtools.phtml');
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function displayBlockActivityLog()
    {
        return $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/activitylog.phtml');
    }

    /**
     * Display a phtml template file
     *
     * @param string $file
     * @param array  $params
     *
     * @return string Content
     *
     * @since 1.0.0
     */
    public function displayAdminTemplate($file, $params = [])
    {
        foreach ($params as $name => $param) {
            $$name = $param;
        }

        ob_start();

        include($file);

        $content = ob_get_contents();
        if (ob_get_level() && ob_get_length() > 0) {
            ob_end_clean();
        }

        return $content;
    }

    public function setMedia()
    {
        parent::setMedia();

        $this->addJS(_PS_MODULE_DIR_.'psonesixmigrator/views/js/upgrader.js');
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
        $str = self::findTranslation('psonesixmigrator', $string, 'AdminThirtyBeesMigrateController');
        $str = $htmlentities ? str_replace('"', '&quot;', htmlentities($str, ENT_QUOTES, 'utf-8')) : $str;
        $str = $addslashes ? addslashes($str) : stripslashes($str);

        return $str;
    }

    /** this returns fieldset containing the configuration points you need to use autoupgrade
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function displayCurrentConfiguration()
    {
        return $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/checklist.phtml');
    }

    /**
     * Generate main form
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function generateMainForm()
    {
        $upgrader = new Upgrader();
        preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
        $upgrader->branch = $matches[1];
        $channel = static::getConfig('channel');
        switch ($channel) {
            case 'archive':
                $upgrader->channel = 'archive';
                $upgrader->versionNum = static::getConfig('archive.version_num');
                break;
            case 'directory':
                $upgrader->channel = 'directory';
                $upgrader->versionNum = static::getConfig('directory.version_num');
                break;
            default:
                $upgrader->channel = $channel;
                if (Tools::getIsset('refreshCurrentVersion')) {
                    // delete the potential xml files we saved in config/xml (from last release and from current)
                    $upgrader->clearXmlMd5File(_PS_VERSION_);
                    $upgrader->clearXmlMd5File($upgrader->versionNum);
                    if (static::getConfig('channel') == 'private' && !static::getConfig('private_allow_major')) {
                        $upgrader->checkPSVersion(true, ['private', 'minor']);
                    } else {
                        $upgrader->checkPSVersion(true, ['minor']);
                    }

                    Tools::redirectAdmin(self::$currentIndex.'&conf=5&token='.Tools::getValue('token'));
                } else {
                    if (static::getConfig('channel') == 'private' && !static::getConfig('private_allow_major')) {
                        $upgrader->checkPSVersion(false, ['private', 'minor']);
                    } else {
                        $upgrader->checkPSVersion(false, ['minor']);
                    }
                }
        }

        $this->upgrader = $upgrader;

        /* Make sure the user has configured the upgrade options, or set default values */
        $configurationKeys = [
            'PS_AUTOUP_UPDATE_DEFAULT_THEME' => 1,
            'PS_AUTOUP_CHANGE_DEFAULT_THEME' => 0,
            'PS_AUTOUP_KEEP_MAILS'           => 1,
            'PS_AUTOUP_CUSTOM_MOD_DESACT'    => 1,
            'PS_AUTOUP_MANUAL_MODE'          => 0,
            'PS_AUTOUP_PERFORMANCE'          => 1,
            'PS_DISPLAY_ERRORS'              => 0,
        ];
        foreach ($configurationKeys as $k => $defaultValue) {
            if (Configuration::get($k) == '') {
                Configuration::updateValue($k, $defaultValue);
            }
        }
    }

    /**
     * @param $name
     * @param $fields
     * @param $tabname
     * @param $size
     * @param $icon
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function displayConfigForm($name, $fields, $tabname, $size, $icon)
    {
        $params = [
            'name'    => $name,
            'fields'  => $fields,
            'tabname' => $tabname,
            'size'    => $size,
            'icon'    => $icon,
        ];

        return $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/displayform.phtml', $params);
    }

    /**
     * Display rollback form
     *
     * @return string
     *
     * @since 1.0.0
     */
    protected function displayRollbackForm()
    {
        return $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/rollbackform.phtml');
    }

    /**
     * @return array
     *
     * @since 1.0.0
     */
    protected function getBackupDbAvailable()
    {
        $tools = UpgraderTools::getInstance();
        $array = [];

        $files = scandir($tools->backupPath);

        foreach ($files as $file) {
            if ($file[0] == 'V' && is_dir($tools->backupPath.DIRECTORY_SEPARATOR.$file)) {
                $array[] = $file;
            }
        }

        return $array;
    }

    /**
     * @return array
     *
     * @since 1.0.0
     */
    protected function getBackupFilesAvailable()
    {
        $tools = UpgraderTools::getInstance();
        $array = [];
        $files = scandir($tools->backupPath);
        foreach ($files as $file) {
            if ($file[0] != '.') {
                if (substr($file, 0, 16) == 'auto-backupfiles') {
                    $array[] = preg_replace('#^auto-backupfiles_(.*-[0-9a-f]{1,8})\..*$#', '$1', $file);
                }
            }
        }

        return $array;
    }


    /**
     * function to set configuration fields display
     *
     * @return void
     */
    private function setFields()
    {
        $this->_fieldsBackupOptions['PS_AUTOUP_BACKUP'] = [
            'title' => $this->l('Back up my files and database'), 'cast' => 'intval', 'validation' => 'isBool', 'defaultValue' => '1',
            'type'  => 'bool', 'desc' => $this->l('Automatically back up your database and files in order to restore your shop if needed. This is experimental: you should still perform your own manual backup for safety.'),
        ];
        $this->_fieldsBackupOptions['PS_AUTOUP_KEEP_IMAGES'] = [
            'title' => $this->l('Back up my images'), 'cast' => 'intval', 'validation' => 'isBool', 'defaultValue' => '1',
            'type'  => 'bool', 'desc' => $this->l('To save time, you can decide not to back your images up. In any case, always make sure you did back them up manually.'),
        ];

        $this->_fieldsUpgradeOptions['PS_AUTOUP_PERFORMANCE'] = [
            'title'   => $this->l('Server performance'), 'cast' => 'intval', 'validation' => 'isInt', 'defaultValue' => '1',
            'type'    => 'select', 'desc' => $this->l('Unless you are using a dedicated server, select "Low".').'<br />'.$this->l('A high value can cause the upgrade to fail if your server is not powerful enough to process the upgrade tasks in a short amount of time.'),
            'choices' => [1 => $this->l('Low (recommended)'), 2 => $this->l('Medium'), 3 => $this->l('High')],
        ];

        $this->_fieldsUpgradeOptions['PS_AUTOUP_CUSTOM_MOD_DESACT'] = [
            'title' => $this->l('Disable non-native modules'), 'cast' => 'intval', 'validation' => 'isBool',
            'type'  => 'bool', 'desc' => $this->l('As non-native modules can experience some compatibility issues, we recommend to disable them by default.').'<br />'.$this->l('Keeping them enabled might prevent you from loading the "Modules" page properly after the migration.'),
        ];

        /* Developers only options */
        if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
            $this->_fieldsUpgradeOptions['PS_AUTOUP_MANUAL_MODE'] = [
                'title' => $this->l('Step by step mode'), 'cast' => 'intval', 'validation' => 'isBool',
                'type'  => 'bool', 'desc' => $this->l('Allows to perform the migration step by step (debug mode).'),
            ];

            $this->_fieldsUpgradeOptions['PS_DISPLAY_ERRORS'] = [
                'title' => $this->l('Display PHP errors'), 'cast' => 'intval', 'validation' => 'isBool', 'defaultValue' => '0',
                'type'  => 'bool', 'desc' => $this->l('This option will keep PHP\'s "display_errors" setting to On (or force it).').'<br />'.$this->l('This is not recommended as the upgrade will immediately fail if a PHP error occurs during an Ajax call.'),
            ];
        } elseif (static::getConfig('PS_DISPLAY_ERRORS')) {
            $this->writeConfig(['PS_DISPLAY_ERRORS' => '0']);
        }
    }

    /**
     * Get js init stuff
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function getJsInit()
    {
        return $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/mainjs.phtml');
    }
}
