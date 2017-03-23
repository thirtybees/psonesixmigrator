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

use PsOneSixMigrator\AdminSelfTab;
use PsOneSixMigrator\Upgrader;
use PsOneSixMigrator\ConfigurationTest;

require_once __DIR__.'/classes/autoload.php';

// _PS_ADMIN_DIR_ is defined in ajax-upgradetab, but may be not defined in direct call
if (!defined('_PS_ADMIN_DIR_') && defined('PS_ADMIN_DIR')) {
    define('_PS_ADMIN_DIR_', PS_ADMIN_DIR);
}

/**
 * Class AdminThirtyBeesMigrate
 *
 * @since 1.0.0
 */
class AdminThirtyBeesMigrate extends AdminSelfTab
{
    // @codingStandardsIgnoreStart
    /** @var  $lCache */
    public static $lCache;
    /** @var array $classes14 */
    public static $classes14 = [
        'Cache', 'CacheFS', 'CarrierModule', 'Db', 'FrontController', 'Helper', 'ImportModule',
        'MCached', 'Module', 'ModuleGraph', 'ModuleGraphEngine', 'ModuleGrid', 'ModuleGridEngine',
        'MySQL', 'Order', 'OrderDetail', 'OrderDiscount', 'OrderHistory', 'OrderMessage', 'OrderReturn',
        'OrderReturnState', 'OrderSlip', 'OrderState', 'PDF', 'RangePrice', 'RangeWeight', 'StockMvt',
        'StockMvtReason', 'SubDomain', 'Shop', 'Tax', 'TaxRule', 'TaxRulesGroup', 'WebserviceKey', 'WebserviceRequest', '',
    ];
    /** @var int $loopBackupFiles */
    public static $loopBackupFiles = 400;
    //
    /**
     * Used for translations
     *
     * @var int $maxBackupFileSize
     */
    public static $maxBackupFileSize = 15728640;
    // retrocompatibility
    /** @var int $loopBackupDbTime */
    public static $loopBackupDbTime = 6;
    /** @var int $max_written_allowed */
    public static $max_written_allowed = 4194304;
    /** @var int $loopUpgradeFiles */
    public static $loopUpgradeFiles = 600;
    public static $loopRestoreFiles = 400; // json, xml
    public static $loopRestoreQueryTime = 6;
    public static $loopUpgradeModulesTime = 6;
    public static $loopRemoveSamples = 400;
    public static $skipAction = [];
    /**
     * if set to true, will use pclZip library
     * even if ZipArchive is available
     */
    public static $forcePclZip = false;
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
    /**
     * set to false if the current step is a loop
     *
     * @var boolean
     */
    public $stepDone = true;
    public $status = true;
    public $warning_exists = false;
    public $error = '0';
    public $next_desc = '';
    public $nextParams = [];
    public $nextQuickInfo = [];
    public $nextErrors = [];
    public $currentParams = [];
    /**
     * @var array theses values will be automatically added in "nextParams"
     * if their properties exists
     */
    public $ajaxParams = [
        'install_version',
        'backupName',
        'backupFilesFilename',
        'backupDbFilename',
        'restoreName',
        'restoreFilesFilename',
        'restoreDbFilenames',
        'installedLanguagesIso',
        'modules_addons',
        'warning_exists',
    ];
    /**
     * installedLanguagesIso is an array of iso_code of each installed languages
     *
     * @var array
     * @access public
     */
    public $installedLanguagesIso = [];
    /**
     * modules_addons is an array of array(id_addons => name_module).
     *
     * @var array
     * @access public
     */
    public $modules_addons = [];
    public $autoupgradePath = null;
    public $downloadPath = null;
    public $backupPath = null;
    public $latestPath = null;
    public $tmpPath = null;
    /** @var array $templateVars */
    public $templateVars = [];
    /**
     * autoupgradeDir
     *
     * @var string directory relative to admin dir
     */
    public $autoupgradeDir = 'autoupgrade';
    public $latestRootDir = '';
    public $prodRootDir = '';
    public $adminDir = '';
    public $root_writable = null;
    public $module_version = null;
    public $lastAutoupgradeVersion = '';
    public $destDownloadFilename = 'prestashop.zip';
    /**
     * configFilename contains all configuration specific to the autoupgrade module
     *
     * @var string
     * @access public
     */
    public $configFilename = 'config.var';
    /**
     * during upgradeFiles process,
     * this files contains the list of queries left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     *
     * @var string
     */
    public $toUpgradeQueriesList = 'queriesToUpgrade.list';
    /**
     * during upgradeFiles process,
     * this files contains the list of files left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     *
     * @var string
     */
    public $toUpgradeFileList = 'filesToUpgrade.list';
    /**
     * during upgradeModules process,
     * this files contains the list of modules left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     *
     * @var string
     */
    public $toUpgradeModuleList = 'modulesToUpgrade.list';
    /**
     * during upgradeFiles process,
     * this files contains the list of files left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     *
     * @var string
     */
    public $diffFileList = 'filesDiff.list';
    /**
     * during backupFiles process,
     * this files contains the list of files left to save in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     *
     * @var string
     */
    public $toBackupFileList = 'filesToBackup.list';
    /**
     * during backupDb process,
     * this files contains the list of tables left to save in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     *
     * @var string
     */
    public $toBackupDbList = 'tablesToBackup.list';
    /**
     * during restoreDb process,
     * this file contains a serialized array of queries which left to execute for restoring database
     * (this file is deleted in init() method if you reload the page)
     *
     * @var string
     */
    public $toRestoreQueryList = 'queryToRestore.list';
    /**
     * during restoreFiles process,
     * this file contains difference between queryToRestore and queries present in a backupFiles archive
     * (this file is deleted in init() method if you reload the page)
     *
     * @var string
     */
    public $toRemoveFileList = 'filesToRemove.list';
    /**
     * during restoreFiles process,
     * contains list of files present in backupFiles archive
     *
     * @var string
     */
    public $fromArchiveFileList = 'filesFromArchive.list';
    /**
     * mailCustomList contains list of mails files which are customized,
     * relative to original files for the current PrestaShop version
     *
     * @var string
     */
    public $mailCustomList = 'mails-custom.list';
    /**
     * tradCustomList contains list of mails files which are customized,
     * relative to original files for the current PrestaShop version
     *
     * @var string
     */
    public $tradCustomList = 'translations-custom.list';
    /**
     * tmp_files contains an array of filename which will be removed
     * at the beginning of the upgrade process
     *
     * @var array
     */
    public $tmpFiles = [
        'toUpgradeFileList',
        'toUpgradeQueriesList',
        'diffFileList',
        'toBackupFileList',
        'toBackupDbList',
        'toRestoreQueryList',
        'toRemoveFileList',
        'fromArchiveFileList',
        'tradCustomList',
        'mailCustomList',
    ];
    public $install_version;
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
    private $install_autoupgrade_dir; // 15 Mo
    private $restoreIgnoreFiles = [];
    private $restoreIgnoreAbsoluteFiles = []; // 4096 ko
    private $backupIgnoreFiles = [];
    private $backupIgnoreAbsoluteFiles = [];
    private $excludeFilesFromUpgrade = [];
    private $excludeAbsoluteFilesFromUpgrade = [];
    private $restoreName = null;

    /* usage :  key = the step you want to ski
     * value = the next step you want instead
     *	example : public static $skipAction = array();
     *	initial order upgrade:
     *		download, unzip, removeSamples, backupFiles, backupDb, upgradeFiles, upgradeDb, upgradeModules, cleanDatabase, upgradeComplete
     * initial order rollback: rollback, restoreFiles, restoreDb, rollbackComplete
     */
    protected $backupName = null;
    protected $backupFilesFilename = null;
    protected $backupDbFilename = null;
    protected $restoreFilesFilename = null;
    protected $restoreDbFilenames = [];
    // @codingStandardsIgnoreEnd


    public function __construct($autoupgradeDir = false)
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('magic_quotes_runtime', '0');
        @ini_set('magic_quotes_sybase', '0');

        if (empty($autoupgradeDir)) {
            $autoupgradeDir = 'autoupg-inst-'.uniqid();
        }

        $this->install_autoupgrade_dir = $autoupgradeDir;

        global $ajax, $currentIndex;

        if (!empty($ajax)) {
            $this->ajax = true;
        }

        $this->init();
        // retrocompatibility when used in module : Tab can't work,
        // but we saved the tab id in a cookie.
        if (class_exists('Tab', false)) {
            parent::__construct();
        } elseif (isset($_COOKIE['id_tab'])) {
            $this->id = $_COOKIE['id_tab'];
        }

        // Database instanciation (need to be cached because there will be at least 100k calls in the upgrade process
        $this->db = Db::getInstance();

        // Performance settings, if your server has a low memory size, lower these values
        $perfArray = [
            'loopBackupFiles'        => [400, 800, 1600],
            'maxBackupFileSize'      => [15728640, 31457280, 62914560],
            'loopBackupDbTime'       => [6, 12, 25],
            'max_written_allowed'    => [4194304, 8388608, 16777216],
            'loopUpgradeFiles'       => [600, 1200, 2400],
            'loopRestoreFiles'       => [400, 800, 1600],
            'loopRestoreQueryTime'   => [6, 12, 25],
            'loopUpgradeModulesTime' => [6, 12, 25],
            'loopRemoveSamples'      => [400, 800, 1600],
        ];
        switch ($this->getConfig('PS_AUTOUP_PERFORMANCE')) {
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
        /* Bug with backwardcompatibility overrinding currentIndex */
        if (version_compare(_PS_VERSION_, '1.5.0.0', '>')) {
            $this->currentIndex = $_SERVER['SCRIPT_NAME'].(($controller = Tools::getValue('controller')) ? '?controller='.$controller : '');
        } else {
            $this->currentIndex = $currentIndex;
        }

        if (defined('_PS_ADMIN_DIR_')) {
            $file_tab = @filemtime($this->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php');
            $file = @filemtime(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$this->autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php');

            if ($file_tab < $file) {
                @copy(
                    _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$this->autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php',
                    $this->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php'
                );
            }
        }

        if (version_compare(_PS_VERSION_, '1.6.1.0', '>=') && !$this->ajax) {
            \Context::getContext()->smarty->assign('display_header_javascript', true);
        }
    }

    /**
     * init to build informations we need
     *
     * @return void
     */
    public function init()
    {
        // For later use, let's set up prodRootDir and adminDir
        // This way it will be easier to upgrade a different path if needed
        $this->prodRootDir = _PS_ROOT_DIR_;
        $this->adminDir = _PS_ADMIN_DIR_;
        if (!defined('__PS_BASE_URI__')) {
            // _PS_DIRECTORY_ replaces __PS_BASE_URI__ in 1.5
            if (defined('_PS_DIRECTORY_')) {
                define('__PS_BASE_URI__', _PS_DIRECTORY_);
            } else {
                define('__PS_BASE_URI__', realpath(dirname($_SERVER['SCRIPT_NAME'])).'/../../');
            }
        }
        // from $_POST or $_GET
        $this->action = empty($_REQUEST['action']) ? null : $_REQUEST['action'];
        $this->currentParams = empty($_REQUEST['params']) ? null : $_REQUEST['params'];
        // test writable recursively
        $this->initPath();
        $upgrader = new Upgrader();
        preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
        if (isset($matches[1])) {
            $upgrader->branch = $matches[1];
        }
        $channel = $this->getConfig('channel');
        switch ($channel) {
            case 'archive':
                $this->install_version = $this->getConfig('archive.version_num');
                $this->destDownloadFilename = $this->getConfig('archive.filename');
                $upgrader->checkPSVersion(true, ['archive']);
                break;
            case 'directory':
                $this->install_version = $this->getConfig('directory.version_num');
                $upgrader->checkPSVersion(true, ['directory']);
                break;
            default:
                $upgrader->channel = $channel;
                if ($this->getConfig('channel') == 'private' && !$this->getConfig('private_allow_major')) {
                    $upgrader->checkPSVersion(true, ['private', 'minor']);
                } else {
                    $upgrader->checkPSVersion(true, ['minor']);
                }
                $this->install_version = $upgrader->version_num;
        }
        if (version_compare($this->install_version, '1.7.1.0', '>=')) {
            $this->latestRootDir = $this->latestPath.DIRECTORY_SEPARATOR;
        } else {
            $this->latestRootDir = $this->latestPath.DIRECTORY_SEPARATOR.'prestashop';
        }
        $this->upgrader = $upgrader;

        // If you have defined this somewhere, you know what you do
        /* load options from configuration if we're not in ajax mode */
        if (!$this->ajax) {
            $this->createCustomToken();

            $postData = 'version='._PS_VERSION_.'&method=listing&action=native&iso_code=all';
            $xmlLocal = $this->prodRootDir.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR.'modules_native_addons.xml';
            $xml = $upgrader->getApiAddons($xmlLocal, $postData, true);

            if (is_object($xml)) {
                foreach ($xml as $mod) {
                    $this->modules_addons[(string) $mod->id] = (string) $mod->name;
                }
            }

            // installedLanguagesIso is used to merge translations files
            $isoIds = \Language::getIsoIds(false);
            foreach ($isoIds as $v) {
                $this->installedLanguagesIso[] = $v['iso_code'];
            }

            $rand = dechex(mt_rand(0, min(0xffffffff, mt_getrandmax())));
            $date = date('Ymd-His');
            $this->backupName = 'V'._PS_VERSION_.'_'.$date.'-'.$rand;
            $this->backupFilesFilename = 'auto-backupfiles_'.$this->backupName.'.zip';
            $this->backupDbFilename = 'auto-backupdb_XXXXXX_'.$this->backupName.'.sql';
            // removing temporary files
            $this->cleanTmpFiles();
        } else {
            foreach ($this->ajaxParams as $prop) {
                if (property_exists($this, $prop)) {
                    $this->{$prop} = isset($this->currentParams[$prop]) ? $this->currentParams[$prop] : '';
                }
            }
        }

        $this->keepImages = $this->getConfig('PS_AUTOUP_KEEP_IMAGES');
        $this->updateDefaultTheme = $this->getConfig('PS_AUTOUP_UPDATE_DEFAULT_THEME');
        $this->changeToDefaultTheme = $this->getConfig('PS_AUTOUP_CHANGE_DEFAULT_THEME');
        $this->keepMails = $this->getConfig('PS_AUTOUP_KEEP_MAILS');
        $this->manualMode = (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) ? (bool) $this->getConfig('PS_AUTOUP_MANUAL_MODE') : false;
        $this->deactivateCustomModule = $this->getConfig('PS_AUTOUP_CUSTOM_MOD_DESACT');

        // during restoration, do not remove :
        $this->restoreIgnoreAbsoluteFiles[] = '/config/settings.inc.php';
        $this->restoreIgnoreAbsoluteFiles[] = '/modules/autoupgrade';
        $this->restoreIgnoreAbsoluteFiles[] = '/admin/autoupgrade';
        $this->restoreIgnoreAbsoluteFiles[] = '.';
        $this->restoreIgnoreAbsoluteFiles[] = '..';

        // during backup, do not save
        $this->backupIgnoreAbsoluteFiles[] = '/tools/smarty_v2/compile';
        $this->backupIgnoreAbsoluteFiles[] = '/tools/smarty_v2/cache';
        $this->backupIgnoreAbsoluteFiles[] = '/tools/smarty/compile';
        $this->backupIgnoreAbsoluteFiles[] = '/tools/smarty/cache';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/smarty/compile';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/smarty/cache';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/tcpdf';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/cachefs';

        // do not care about the two autoupgrade dir we use;
        $this->backupIgnoreAbsoluteFiles[] = '/modules/autoupgrade';
        $this->backupIgnoreAbsoluteFiles[] = '/admin/autoupgrade';

        $this->backupIgnoreFiles[] = '.';
        $this->backupIgnoreFiles[] = '..';
        $this->backupIgnoreFiles[] = '.svn';
        $this->backupIgnoreFiles[] = '.git';
        $this->backupIgnoreFiles[] = $this->autoupgradeDir;

        $this->excludeFilesFromUpgrade[] = '.';
        $this->excludeFilesFromUpgrade[] = '..';
        $this->excludeFilesFromUpgrade[] = '.svn';
        $this->excludeFilesFromUpgrade[] = '.git';
        // do not copy install, neither settings.inc.php in case it would be present
        $this->excludeAbsoluteFilesFromUpgrade[] = '/config/settings.inc.php';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/install';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/'.$this->install_autoupgrade_dir.'/index.php';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/'.$this->install_autoupgrade_dir.'/index_cli.php';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/install-dev';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/config/modules_list.xml';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/config/xml/modules_list.xml';
        // this will exclude autoupgrade dir from admin, and autoupgrade from modules
        $this->excludeFilesFromUpgrade[] = $this->autoupgradeDir;

        if ($this->keepImages === '0') {
            $this->backupIgnoreAbsoluteFiles[] = '/img';
            $this->restoreIgnoreAbsoluteFiles[] = '/img';
        } else {
            $this->backupIgnoreAbsoluteFiles[] = '/img/tmp';
            $this->restoreIgnoreAbsoluteFiles[] = '/img/tmp';
        }

        // If set to false, we need to preserve the default themes
        if (!$this->updateDefaultTheme) {
            if (version_compare(_PS_VERSION_, '1.6.0.0', '>')) {
                $this->excludeAbsoluteFilesFromUpgrade[] = '/themes/default-bootstrap';
            } elseif (version_compare(_PS_VERSION_, '1.5.0.0', '>')) {
                $this->excludeAbsoluteFilesFromUpgrade[] = '/themes/default';
            } else {
                $this->excludeAbsoluteFilesFromUpgrade[] = '/themes/prestashop';
            }
        }
    }

    /**
     * create some required directories if they does not exists
     *
     * Also set nextParams (removeList and filesToUpgrade) if they
     * exists in currentParams
     *
     */
    public function initPath()
    {
        // If not exists in this sessions, "create"
        // session handling : from current to next params
        if (isset($this->currentParams['removeList'])) {
            $this->nextParams['removeList'] = $this->currentParams['removeList'];
        }

        if (isset($this->currentParams['filesToUpgrade'])) {
            $this->nextParams['filesToUpgrade'] = $this->currentParams['filesToUpgrade'];
        }

        if (isset($this->currentParams['modulesToUpgrade'])) {
            $this->nextParams['modulesToUpgrade'] = $this->currentParams['modulesToUpgrade'];
        }

        // set autoupgradePath, to be used in backupFiles and backupDb config values
        $this->autoupgradePath = $this->adminDir.DIRECTORY_SEPARATOR.$this->autoupgradeDir;
        // directory missing
        if (!file_exists($this->autoupgradePath)) {
            if (!mkdir($this->autoupgradePath)) {
                $this->_errors[] = sprintf($this->l('unable to create directory %s'), $this->autoupgradePath);
            }
        }

        if (!is_writable($this->autoupgradePath)) {
            $this->_errors[] = sprintf($this->l('Unable to write in the directory "%s"'), $this->autoupgradePath);
        }

        $this->downloadPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'download';
        if (!file_exists($this->downloadPath)) {
            if (!mkdir($this->downloadPath)) {
                $this->_errors[] = sprintf($this->l('unable to create directory %s'), $this->downloadPath);
            }
        }

        $this->backupPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'backup';
        $tmp = "order deny,allow\ndeny from all";
        if (!file_exists($this->backupPath)) {
            if (!mkdir($this->backupPath)) {
                $this->_errors[] = sprintf($this->l('unable to create directory %s'), $this->backupPath);
            }
        }
        if (!file_exists($this->backupPath.DIRECTORY_SEPARATOR.'index.php')) {
            if (!copy(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'index.php', $this->backupPath.DIRECTORY_SEPARATOR.'index.php')) {
                $this->_errors[] = sprintf($this->l('unable to create file %s'), $this->backupPath.DIRECTORY_SEPARATOR.'index.php');
            }
        }
        if (!file_exists($this->backupPath.DIRECTORY_SEPARATOR.'.htaccess')) {
            if (!file_put_contents($this->backupPath.DIRECTORY_SEPARATOR.'.htaccess', $tmp)) {
                $this->_errors[] = sprintf($this->l('unable to create file %s'), $this->backupPath.DIRECTORY_SEPARATOR.'.htaccess');
            }
        }

        // directory missing
        $this->latestPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'latest';
        if (!file_exists($this->latestPath)) {
            if (!mkdir($this->latestPath)) {
                $this->_errors[] = sprintf($this->l('unable to create directory %s'), $this->latestPath);
            }
        }

        $this->tmpPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'tmp';
        if (!file_exists($this->tmpPath)) {
            if (!mkdir($this->tmpPath)) {
                $this->_errors[] = sprintf($this->l('unable to create directory %s'), $this->tmpPath);
            }
        }
    }

    protected function l($string, $class = 'AdminTab', $addslashes = false, $htmlentities = true)
    {
        // need to be called in order to populate $classInModule
        $str = self::findTranslation('autoupgrade', $string, 'AdminThirtyBeesMigrate');
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
                    _PS_MODULE_DIR_.'autoupgrade'.DIRECTORY_SEPARATOR.'translations'.DIRECTORY_SEPARATOR.$_COOKIE['iso_code'].'.php', // 1.5
                    _PS_MODULE_DIR_.'autoupgrade'.DIRECTORY_SEPARATOR.$_COOKIE['iso_code'].'.php', // 1.4
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

        if (!isset(self::$lCache[$cacheKey])) {
            if (!is_array($_MODULES)) {
                return $string;
            }
            // set array key to lowercase for 1.3 compatibility
            $_MODULES = array_change_key_case($_MODULES);
            if (defined('_THEME_NAME_')) {
                $currentKey = '<{'.strtolower($name).'}'.strtolower(_THEME_NAME_).'>'.strtolower($source).'_'.md5($string);
            } else {
                $currentKey = '<{'.strtolower($name).'}default>'.strtolower($source).'_'.md5($string);
            }
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

            self::$lCache[$cacheKey] = $ret;
        }

        return self::$lCache[$cacheKey];
    }

    /**
     * return the value of $key, configuration saved in $this->configFilename.
     * if $key is empty, will return an array with all configuration;
     *
     * @param string $key
     *
     * @access public
     * @return array or string
     */
    public function getConfig($key = '')
    {
        static $config = [];
        if (count($config) == 0) {
            if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename)) {
                $configContent = Tools::file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename);
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
     */
    public function createCustomToken()
    {
        // ajax-mode for autoupgrade, we can't use the classic authentication
        // so, we'll create a cookie in admin dir, based on cookie key
        global $cookie;
        $idEmployee = $cookie->id_employee;
        if ($cookie->id_lang) {
            $isoCode = $_COOKIE['iso_code'] = \Language::getIsoById((int) $cookie->id_lang);
        } else {
            $isoCode = 'en';
        }
        $adminDir = trim(str_replace($this->prodRootDir, '', $this->adminDir), DIRECTORY_SEPARATOR);
        $cookiePath = __PS_BASE_URI__.$adminDir;
        setcookie('id_employee', $idEmployee, 0, $cookiePath);
        setcookie('id_tab', $this->id, 0, $cookiePath);
        setcookie('iso_code', $isoCode, 0, $cookiePath);
        setcookie('autoupgrade', $this->encrypt($idEmployee), 0, $cookiePath);

        return false;
    }

    public function cleanTmpFiles()
    {
        foreach ($this->tmpFiles as $tmpFile) {
            if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->$tmpFile)) {
                unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->$tmpFile);
            }
        }
    }

    public function checkToken()
    {
        // simple checkToken in ajax-mode, to be free of Cookie class (and no Tools::encrypt() too )
        if ($this->ajax && isset($_COOKIE['id_employee'])) {
            return ($_COOKIE['autoupgrade'] == $this->encrypt($_COOKIE['id_employee']));
        } else {
            return parent::checkToken();
        }
    }

    /**
     * replace tools encrypt
     *
     * @param string $string
     *
     * @return string
     */
    public function encrypt($string)
    {
        return md5(_COOKIE_KEY_.$string);
    }

    public function viewAccess($disable = false)
    {
        if ($this->ajax) {
            return true;
        } else {
            // simple access : we'll allow only 46admin
            global $cookie;
            if ($cookie->profile == 1) {
                return true;
            }
        }

        return false;
    }

    public function postProcess()
    {
        $this->_setFields();

        // set default configuration to default channel & dafault configuration for backup and upgrade
        // (can be modified in expert mode)
        $config = $this->getConfig('channel');
        if ($config === false) {
            $config = [];
            $config['channel'] = Upgrader::DEFAULT_CHANNEL;
            $this->writeConfig($config);
            if (class_exists('Configuration', false)) {
                \Configuration::updateValue('PS_UPGRADE_CHANNEL', $config['channel']);
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
            foreach (\Shop::getCompleteListOfShopsID() as $id_shop) {
                \Configuration::updateValue('PS_SHOP_ENABLE', 0, false, null, (int) $id_shop);
            }
            \Configuration::updateGlobalValue('PS_SHOP_ENABLE', 0);
        } elseif (Tools::isSubmit('putUnderMaintenance')) {
            \Configuration::updateValue('PS_SHOP_ENABLE', 0);
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
            $filelist = scandir($this->backupPath);
            foreach ($filelist as $filename) {
                // the following will match file or dir related to the selected backup
                if (!empty($filename) && $filename[0] != '.' && $filename != 'index.php' && $filename != '.htaccess'
                    && preg_match('#^(auto-backupfiles_|)'.preg_quote($name).'(\.zip|)$#', $filename, $matches)
                ) {
                    if (is_file($this->backupPath.DIRECTORY_SEPARATOR.$filename)) {
                        $res &= unlink($this->backupPath.DIRECTORY_SEPARATOR.$filename);
                    } elseif (!empty($name) && is_dir($this->backupPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR)) {
                        $res = self::deleteDirectory($this->backupPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR);
                    }
                }
            }
            if ($res) {
                Tools::redirectAdmin($this->currentIndex.'&conf=1&token='.Tools::getValue('token'));
            } else {
                $this->_errors[] = sprintf($this->l('Error when trying to delete backups %s'), $name);
            }
        }
        parent::postProcess();
    }

    /**
     * function to set configuration fields display
     *
     * @return void
     */
    private function _setFields()
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
            'type'    => 'select', 'desc' => $this->l('Unless you are using a dedicated server, select "Low".').'<br />'.
                $this->l('A high value can cause the upgrade to fail if your server is not powerful enough to process the upgrade tasks in a short amount of time.'),
            'choices' => [1 => $this->l('Low (recommended)'), 2 => $this->l('Medium'), 3 => $this->l('High')],
        ];

        $this->_fieldsUpgradeOptions['PS_AUTOUP_CUSTOM_MOD_DESACT'] = [
            'title' => $this->l('Disable non-native modules'), 'cast' => 'intval', 'validation' => 'isBool',
            'type'  => 'bool', 'desc' => $this->l('As non-native modules can experience some compatibility issues, we recommend to disable them by default.').'<br />'.
                $this->l('Keeping them enabled might prevent you from loading the "Modules" page properly after the upgrade.'),
        ];

        $this->_fieldsUpgradeOptions['PS_AUTOUP_UPDATE_DEFAULT_THEME'] = [
            'title' => $this->l('Upgrade the default theme'), 'cast' => 'intval', 'validation' => 'isBool', 'defaultValue' => '1',
            'type'  => 'bool', 'desc' => $this->l('If you customized the default PrestaShop theme in its folder (folder name "prestashop" in 1.4, "default" in 1.5, "bootstrap-default" in 1.6), enabling this option will lose your modifications.').'<br />'
                .$this->l('If you are using your own theme, enabling this option will simply update the default theme files, and your own theme will be safe.'),
        ];

        $this->_fieldsUpgradeOptions['PS_AUTOUP_CHANGE_DEFAULT_THEME'] = [
            'title' => $this->l('Switch to the default theme'), 'cast' => 'intval', 'validation' => 'isBool', 'defaultValue' => '0',
            'type'  => 'bool', 'desc' => $this->l('This will change your theme: your shop will then use the default theme of the version of PrestaShop you are upgrading to.'),
        ];

        $this->_fieldsUpgradeOptions['PS_AUTOUP_KEEP_MAILS'] = [
            'title' => $this->l('Upgrade the default e-mails'), 'cast' => 'intval', 'validation' => 'isBool',
            'type'  => 'bool', 'desc' => $this->l('This will upgrade the default PrestaShop e-mails.').'<br />'
                .$this->l('If you customized the default PrestaShop e-mail templates, enabling this option will lose your modifications.'),
        ];

        /* Developers only options */
        if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
            $this->_fieldsUpgradeOptions['PS_AUTOUP_MANUAL_MODE'] = [
                'title' => $this->l('Step by step mode'), 'cast' => 'intval', 'validation' => 'isBool',
                'type'  => 'bool', 'desc' => $this->l('Allows to perform the upgrade step by step (debug mode).'),
            ];

            $this->_fieldsUpgradeOptions['PS_DISPLAY_ERRORS'] = [
                'title' => $this->l('Display PHP errors'), 'cast' => 'intval', 'validation' => 'isBool', 'defaultValue' => '0',
                'type'  => 'bool', 'desc' => $this->l('This option will keep PHP\'s "display_errors" setting to On (or force it).').'<br />'
                    .$this->l('This is not recommended as the upgrade will immediately fail if a PHP error occurs during an Ajax call.'),
            ];
        } elseif ($this->getConfig('PS_DISPLAY_ERRORS')) {
            $this->writeConfig(['PS_DISPLAY_ERRORS' => '0']);
        }
    }

    /**
     * update module configuration (saved in file $this->configFilename) with $new_config
     *
     * @param array $new_config
     *
     * @return boolean true if success
     */
    public function writeConfig($new_config)
    {
        if (!file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename)) {
            $this->upgrader->channel = $new_config['channel'];
            $this->upgrader->checkPSVersion();
            $this->install_version = $this->upgrader->version_num;

            return $this->resetConfig($new_config);
        }

        $config = file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename);
        $configUnserialized = @unserialize(base64_decode($config));
        if (!is_array($configUnserialized)) {
            $configUnserialized = @unserialize($config);
        } // retrocompat, before base64_decode implemented
        $config = $configUnserialized;

        foreach ($new_config as $key => $val) {
            $config[$key] = $val;
        }
        $this->next_desc = $this->l('Configuration successfully updated.').' <strong>'.$this->l('This page will now be reloaded and the module will check if a new version is available.').'</strong>';

        return (bool) file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename, base64_encode(serialize($config)));
    }

    /**
     * reset module configuration with $new_config values (previous config will be totally lost)
     *
     * @param array $new_config
     *
     * @return boolean true if success
     */
    public function resetConfig($new_config)
    {
        return (bool) file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename, base64_encode(serialize($new_config)));
    }

    /**
     * Delete directory and subdirectories
     *
     * @param string $dirname Directory name
     */
    public static function deleteDirectory($dirname, $delete_self = true)
    {
        return Tools::deleteDirectory($dirname, $delete_self);
    }

    /**
     * ends the rollback process
     *
     * @return void
     */
    public function ajaxProcessRollbackComplete()
    {
        $this->next_desc = $this->l('Restoration process done. Congratulations! You can now reactivate your shop.');
        $this->next = '';
    }

    /**
     * ends the upgrade process
     *
     * @return void
     */
    public function ajaxProcessUpgradeComplete()
    {
        if (!$this->warning_exists) {
            $this->next_desc = $this->l('Upgrade process done. Congratulations! You can now reactivate your shop.');
        } else {
            $this->next_desc = $this->l('Upgrade process done, but some warnings have been found.');
        }
        $this->next = '';

        if ($this->getConfig('channel') != 'archive' && file_exists($this->getFilePath()) && unlink($this->getFilePath())) {
            $this->nextQuickInfo[] = sprintf($this->l('%s removed'), $this->getFilePath());
        } elseif (is_file($this->getFilePath())) {
            $this->nextQuickInfo[] = '<strong>'.sprintf($this->l('Please remove %s by FTP'), $this->getFilePath()).'</strong>';
        }

        if ($this->getConfig('channel') != 'directory' && file_exists($this->latestRootDir) && self::deleteDirectory($this->latestRootDir)) {
            $this->nextQuickInfo[] = sprintf($this->l('%s removed'), $this->latestRootDir);
        } elseif (is_dir($this->latestRootDir)) {
            $this->nextQuickInfo[] = '<strong>'.sprintf($this->l('Please remove %s by FTP'), $this->latestRootDir).'</strong>';
        }
    }

    /**
     * getFilePath return the path to the zipfile containing prestashop.
     *
     * @return void
     */
    private function getFilePath()
    {
        return $this->downloadPath.DIRECTORY_SEPARATOR.$this->destDownloadFilename;
    }

    /**
     * update configuration after validating the new values
     *
     * @access public
     */
    public function ajaxProcessUpdateConfig()
    {
        $config = [];
        // nothing next
        $this->next = '';
        // update channel
        if (isset($this->currentParams['channel'])) {
            $config['channel'] = $this->currentParams['channel'];
        }
        if (isset($this->currentParams['private_release_link']) && isset($this->currentParams['private_release_md5'])) {
            $config['channel'] = 'private';
            $config['private_release_link'] = $this->currentParams['private_release_link'];
            $config['private_release_md5'] = $this->currentParams['private_release_md5'];
            $config['private_allow_major'] = $this->currentParams['private_allow_major'];
        }
        // if (!empty($this->currentParams['archive_name']) && !empty($this->currentParams['archive_num']))
        if (!empty($this->currentParams['archive_prestashop'])) {
            $file = $this->currentParams['archive_prestashop'];
            if (!file_exists($this->downloadPath.DIRECTORY_SEPARATOR.$file)) {
                $this->error = 1;
                $this->next_desc = sprintf($this->l('File %s does not exist. Unable to select that channel.'), $file);

                return false;
            }
            if (empty($this->currentParams['archive_num'])) {
                $this->error = 1;
                $this->next_desc = sprintf($this->l('Version number is missing. Unable to select that channel.'), $file);

                return false;
            }
            $config['channel'] = 'archive';
            $config['archive.filename'] = $this->currentParams['archive_prestashop'];
            $config['archive.version_num'] = $this->currentParams['archive_num'];
            // $config['archive_name'] = $this->currentParams['archive_name'];
            $this->next_desc = $this->l('Upgrade process will use archive.');
        }
        if (isset($this->currentParams['directory_num'])) {
            $config['channel'] = 'directory';
            if (empty($this->currentParams['directory_num']) || strpos($this->currentParams['directory_num'], '.') === false) {
                $this->error = 1;
                $this->next_desc = sprintf($this->l('Version number is missing. Unable to select that channel.'));

                return false;
            }

            $config['directory.version_num'] = $this->currentParams['directory_num'];
        }
        if (isset($this->currentParams['skip_backup'])) {
            $config['skip_backup'] = $this->currentParams['skip_backup'];
        }

        if (!$this->writeConfig($config)) {
            $this->error = 1;
            $this->next_desc = $this->l('Error on saving configuration');
        }
    }

    /**
     * display informations related to the selected channel : link/changelog for remote channel,
     * or configuration values for special channels
     *
     * @access public
     */
    public function ajaxProcessGetChannelInfo()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';

        $channel = $this->currentParams['channel'];
        $upgradeInfo = $this->getInfoForChannel($channel);
        $this->nextParams['result']['available'] = $upgradeInfo['available'];

        $this->nextParams['result']['div'] = $this->divChannelInfos($upgradeInfo);
    }

    // Simplification of _displayForm original function

    /** returns an array containing information related to the channel $channel
     *
     * @param string $channel name of the channel
     *
     * @return array available, version_num, version_name, link, md5, changelog
     */
    public function getInfoForChannel($channel)
    {
        $upgradeInfo = [];
        $publicChannel = ['minor', 'major', 'rc', 'beta', 'alpha'];
        $upgrader = new Upgrader();
        preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
        $upgrader->branch = $matches[1];
        $upgrader->channel = $channel;
        if (in_array($channel, $publicChannel)) {
            if ($this->getConfig('channel') == 'private' && !$this->getConfig('private_allow_major')) {
                $upgrader->checkPSVersion(false, ['private', 'minor']);
            } else {
                $upgrader->checkPSVersion(false, ['minor']);
            }

            $upgradeInfo = [];
            $upgradeInfo['branch'] = $upgrader->branch;
            $upgradeInfo['available'] = $upgrader->available;
            $upgradeInfo['version_num'] = $upgrader->version_num;
            $upgradeInfo['version_name'] = $upgrader->version_name;
            $upgradeInfo['link'] = $upgrader->link;
            $upgradeInfo['md5'] = $upgrader->md5;
            $upgradeInfo['changelog'] = $upgrader->changelog;
        } else {
            switch ($channel) {
                case 'private':
                    if (!$this->getConfig('private_allow_major')) {
                        $upgrader->checkPSVersion(false, ['private', 'minor']);
                    } else {
                        $upgrader->checkPSVersion(false, ['minor']);
                    }

                    $upgradeInfo['available'] = $upgrader->available;
                    $upgradeInfo['branch'] = $upgrader->branch;
                    $upgradeInfo['version_num'] = $upgrader->version_num;
                    $upgradeInfo['version_name'] = $upgrader->version_name;
                    $upgradeInfo['link'] = $this->getConfig('private_release_link');
                    $upgradeInfo['md5'] = $this->getConfig('private_release_md5');
                    $upgradeInfo['changelog'] = $upgrader->changelog;
                    break;
                case 'archive':
                    $upgradeInfo['available'] = true;
                    break;
                case 'directory':
                    $upgradeInfo['available'] = true;
                    break;
            }
        }

        return $upgradeInfo;
    }

    public function divChannelInfos($upgradeInfo)
    {
        if ($this->getConfig('channel') == 'private') {
            $upgradeInfo['link'] = $this->getConfig('private_release_link');
            $upgradeInfo['md5'] = $this->getConfig('private_release_md5');
        }

        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/channelinfo.phtml');
    }

    /**
     * get the list of all modified and deleted files between current version
     * and target version (according to channel configuration)
     *
     * @access public
     */
    public function ajaxProcessCompareReleases()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';
        $channel = $this->getConfig('channel');
        $this->upgrader = new Upgrader();
        switch ($channel) {
            case 'archive':
                $version = $this->getConfig('archive.version_num');
                break;
            case 'directory':
                $version = $this->getConfig('directory.version_num');
                break;
            default:
                preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
                $this->upgrader->branch = $matches[1];
                $this->upgrader->channel = $channel;
                if ($this->getConfig('channel') == 'private' && !$this->getConfig('private_allow_major')) {
                    $this->upgrader->checkPSVersion(false, ['private', 'minor']);
                } else {
                    $this->upgrader->checkPSVersion(false, ['minor']);
                }
                $version = $this->upgrader->version_num;
        }

        $diffFileList = $this->upgrader->getDiffFilesList(_PS_VERSION_, $version);
        if (!is_array($diffFileList)) {
            $this->nextParams['status'] = 'error';
            $this->nextParams['msg'] = sprintf('Unable to generate diff file list between %1$s and %2$s.', _PS_VERSION_, $version);
        } else {
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->diffFileList, base64_encode(serialize($diffFileList)));
            if (count($diffFileList) > 0) {
                $this->nextParams['msg'] = sprintf(
                    $this->l('%1$s files will be modified, %2$s files will be deleted (if they are found).'),
                    count($diffFileList['modified']),
                    count($diffFileList['deleted'])
                );
            } else {
                $this->nextParams['msg'] = $this->l('No diff files found.');
            }
            $this->nextParams['result'] = $diffFileList;
        }
    }

    /**
     * list the files modified in the current installation regards to the original version
     *
     * @access public
     */
    public function ajaxProcessCheckFilesVersion()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';
        $this->upgrader = new Upgrader();

        $changedFileList = $this->upgrader->getChangedFilesList();
        if ($this->upgrader->isAuthenticPrestashopVersion() === true
            && !is_array($changedFileList)
        ) {
            $this->nextParams['status'] = 'error';
            $this->nextParams['msg'] = $this->l('Unable to check files for the installed version of PrestaShop.');
            $testOrigCore = false;
        } else {
            if ($this->upgrader->isAuthenticPrestashopVersion() === true) {
                $this->nextParams['status'] = 'ok';
                $testOrigCore = true;
            } else {
                $testOrigCore = false;
                $this->nextParams['status'] = 'warn';
            }

            if (!isset($changedFileList['core'])) {
                $changedFileList['core'] = [];
            }

            if (!isset($changedFileList['translation'])) {
                $changedFileList['translation'] = [];
            }
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->tradCustomList, base64_encode(serialize($changedFileList['translation'])));

            if (!isset($changedFileList['mail'])) {
                $changedFileList['mail'] = [];
            }
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->mailCustomList, base64_encode(serialize($changedFileList['mail'])));

            if ($changedFileList === false) {
                $changedFileList = [];
                $this->nextParams['msg'] = $this->l('Unable to check files');
                $this->nextParams['status'] = 'error';
            } else {
                $this->nextParams['msg'] = ($testOrigCore ? $this->l('Core files are ok') : sprintf($this->l('%1$s file modifications have been detected, including %2$s from core and native modules:'), count(array_merge($changedFileList['core'], $changedFileList['mail'], $changedFileList['translation'])), count($changedFileList['core'])));
            }
            $this->nextParams['result'] = $changedFileList;
        }
    }

    public function ajaxProcessUpgradeNow17()
    {
        return $this->ajaxProcessUpgradeNow();
    }

    /**
     * very first step of the upgrade process. The only thing done is the selection
     * of the next step
     *
     * @access public
     * @return void
     */
    public function ajaxProcessUpgradeNow()
    {
        $this->next_desc = $this->l('Starting upgrade...');
        $channel = $this->getConfig('channel');
        $this->next = 'download';
        if (!is_object($this->upgrader)) {
            $this->upgrader = new Upgrader();
        }
        preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
        $this->upgrader->branch = $matches[1];
        $this->upgrader->channel = $channel;
        if ($this->getConfig('channel') == 'private' && !$this->getConfig('private_allow_major')) {
            $this->upgrader->checkPSVersion(false, ['private', 'minor']);
        } else {
            $this->upgrader->checkPSVersion(false, ['minor']);
        }

        switch ($channel) {
            case 'directory':
                // if channel directory is chosen, we assume it's "ready for use" (samples already removed for example)
                $this->next = 'removeSamples';
                $this->nextQuickInfo[] = $this->l('Skip downloading and unziping steps, upgrade process will now remove sample data.');
                $this->next_desc = $this->l('Shop deactivated. Removing sample files...');
                break;
            case 'archive':
                $this->next = 'unzip';
                $this->nextQuickInfo[] = $this->l('Skip downloading step, upgrade process will now unzip the local archive.');
                $this->next_desc = $this->l('Shop deactivated. Extracting files...');
                break;
            default:
                $this->next = 'download';
                $this->next_desc = $this->l('Shop deactivated. Now downloading... (this can take a while)');
                if ($this->upgrader->channel == 'private') {
                    $this->upgrader->link = $this->getConfig('private_release_link');
                    $this->upgrader->md5 = $this->getConfig('private_release_md5');
                }
                $this->nextQuickInfo[] = sprintf($this->l('Downloaded archive will come from %s'), $this->upgrader->link);
                $this->nextQuickInfo[] = sprintf($this->l('MD5 hash will be checked against %s'), $this->upgrader->md5);
        }
    }

    /**
     * extract chosen version into $this->latestPath directory
     *
     * @return void
     */
    public function ajaxProcessUnzip()
    {
        $filepath = $this->getFilePath();
        $destExtract = $this->latestPath;

        if (file_exists($destExtract)) {
            self::deleteDirectory($destExtract, false);
            $this->nextQuickInfo[] = $this->l('"/latest" directory has been emptied');
        }
        $relativeExtractPath = str_replace(_PS_ROOT_DIR_, '', $destExtract);
        $report = '';
        if (ConfigurationTest::test_dir($relativeExtractPath, false, $report)) {
            if ($this->ZipExtract($filepath, $destExtract)) {
                if (version_compare($this->install_version, '1.7.1.0', '>=')) {
                    // new system release archive
                    $newZip = $destExtract.DIRECTORY_SEPARATOR.'prestashop.zip';
                    if (is_file($newZip)) {
                        @unlink($destExtract.DIRECTORY_SEPARATOR.'/index.php');
                        @unlink($destExtract.DIRECTORY_SEPARATOR.'/Install_PrestaShop.html');
                        if ($this->ZipExtract($newZip, $destExtract)) {
                            // Unsetting to force listing
                            unset($this->nextParams['removeList']);
                            $this->next = 'removeSamples';
                            $this->next_desc = $this->l('File extraction complete. Removing sample files...');
                            @unlink($newZip);

                            return true;
                        } else {
                            $this->next = 'error';
                            $this->next_desc = sprintf($this->l('Unable to extract %1$s file into %2$s folder...'), $filepath, $destExtract);

                            return false;
                        }
                    } else {
                        $this->next = 'error';
                        $this->next_desc = sprintf($this->l('It\'s not a valid upgrade %s archive...'), $this->install_version);

                        return false;
                    }
                } else {
                    // Unsetting to force listing
                    unset($this->nextParams['removeList']);
                    $this->next = 'removeSamples';
                    $this->next_desc = $this->l('File extraction complete. Removing sample files...');

                    return true;
                }
            } else {
                $this->next = 'error';
                $this->next_desc = sprintf($this->l('Unable to extract %1$s file into %2$s folder...'), $filepath, $destExtract);

                return true;
            }
        } else {
            $this->next_desc = $this->l('Extraction directory is not writable.');
            $this->nextQuickInfo[] = $this->l('Extraction directory is not writable.');
            $this->nextErrors[] = sprintf($this->l('Extraction directory %s is not writable.'), $destExtract);
            $this->next = 'error';
        }
    }

    /**
     * @desc extract a zip file to the given directory
     * @return bool success
     * we need a copy of it to be able to restore without keeping Tools and Autoload stuff
     */
    private function ZipExtract($fromFile, $toDir)
    {
        if (!is_file($fromFile)) {
            $this->next = 'error';
            $this->nextQuickInfo[] = sprintf($this->l('%s is not a file'), $fromFile);
            $this->nextErrors[] = sprintf($this->l('%s is not a file'), $fromFile);

            return false;
        }

        if (!file_exists($toDir)) {
            if (!mkdir($toDir)) {
                $this->next = 'error';
                $this->nextQuickInfo[] = sprintf($this->l('Unable to create directory %s.'), $toDir);
                $this->nextErrors[] = sprintf($this->l('Unable to create directory %s.'), $toDir);

                return false;
            } else {
                chmod($toDir, 0775);
            }
        }

        $res = false;
        if (!self::$forcePclZip && class_exists('ZipArchive', false)) {
            $this->nextQuickInfo[] = $this->l('Using class ZipArchive...');
            $zip = new \ZipArchive();
            if ($zip->open($fromFile) === true && isset($zip->filename) && $zip->filename) {
                $extract_result = true;
                $res = true;
                // We extract file by file, it is very fast
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $extract_result &= $zip->extractTo($toDir, [$zip->getNameIndex($i)]);
                }

                if ($extract_result) {
                    $this->nextQuickInfo[] = $this->l('Archive extracted');

                    return true;
                } else {
                    $this->nextQuickInfo[] = sprintf($this->l('zip->extractTo(): unable to use %s as extract destination.'), $toDir);
                    $this->nextErrors[] = sprintf($this->l('zip->extractTo(): unable to use %s as extract destination.'), $toDir);

                    return false;
                }
            } elseif (isset($zip->filename) && $zip->filename) {
                $this->nextQuickInfo[] = sprintf($this->l('Unable to open zipFile %s'), $fromFile);
                $this->nextErrors[] = sprintf($this->l('Unable to open zipFile %s'), $fromFile);

                return false;
            }
        }
        if (!$res) {
            $this->nextQuickInfo[] = $this->l('Using class PclZip...');

            $zip = new \PclZip($fromFile);

            if (($file_list = $zip->listContent()) == 0) {
                $this->next = 'error';
                $this->nextQuickInfo[] = sprintf($this->l('[ERROR] Error on extracting archive using PclZip: %s.'), $zip->errorInfo(true));

                return false;
            }

            // PCL is very slow, so we need to extract files 500 by 500
            $i = 0;
            $j = 1;
            foreach ($file_list as $file) {
                if (!isset($indexes[$i])) {
                    $indexes[$i] = [];
                }
                $indexes[$i][] = $file['index'];
                if ($j++ % 500 == 0) {
                    $i++;
                }
            }

            // replace also modified files
            foreach ($indexes as $index) {
                if (($extract_result = $zip->extract(PCLZIP_OPT_BY_INDEX, $index, PCLZIP_OPT_PATH, $toDir, PCLZIP_OPT_REPLACE_NEWER)) == 0) {
                    $this->next = 'error';
                    $this->nextErrors[] = sprintf($this->l('[ERROR] Error on extracting archive using PclZip: %s.'), $zip->errorInfo(true));

                    return false;
                } else {
                    foreach ($extract_result as $extractedFile) {
                        $file = str_replace($this->prodRootDir, '', $extractedFile['filename']);
                        if ($extractedFile['status'] != 'ok' && $extractedFile['status'] != 'already_a_directory') {
                            $this->nextQuickInfo[] = sprintf($this->l('[ERROR] %s has not been unzipped: '.$extractedFile['status']), $file);
                            $this->nextErrors[] = sprintf($this->l('[ERROR] %s has not been unzipped: '.$extractedFile['status']), $file);
                            $this->next = 'error';
                        } else {
                            $this->nextQuickInfo[] = sprintf('%1$s unzipped into %2$s', $file, str_replace(_PS_ROOT_DIR_, '', $toDir));
                        }
                    }
                    if ($this->next === 'error') {
                        return false;
                    }
                }
            }

            return true;
        }
    }

    public function ajaxProcessUpgradeFiles()
    {
        $this->nextParams = $this->currentParams;

        $admin_dir = str_replace($this->prodRootDir.DIRECTORY_SEPARATOR, '', $this->adminDir);
        if (file_exists($this->latestRootDir.DIRECTORY_SEPARATOR.'admin')) {
            rename($this->latestRootDir.DIRECTORY_SEPARATOR.'admin', $this->latestRootDir.DIRECTORY_SEPARATOR.$admin_dir);
        } elseif (file_exists($this->latestRootDir.DIRECTORY_SEPARATOR.'admin-dev')) {
            rename($this->latestRootDir.DIRECTORY_SEPARATOR.'admin-dev', $this->latestRootDir.DIRECTORY_SEPARATOR.$admin_dir);
        }
        if (file_exists($this->latestRootDir.DIRECTORY_SEPARATOR.'install-dev')) {
            rename($this->latestRootDir.DIRECTORY_SEPARATOR.'install-dev', $this->latestRootDir.DIRECTORY_SEPARATOR.$this->install_autoupgrade_dir);
        }
        if (file_exists($this->latestRootDir.DIRECTORY_SEPARATOR.'install')) {
            rename($this->latestRootDir.DIRECTORY_SEPARATOR.'install', $this->latestRootDir.DIRECTORY_SEPARATOR.$this->install_autoupgrade_dir);
        }

        if (!isset($this->nextParams['filesToUpgrade'])) {
            // list saved in $this->toUpgradeFileList
            // get files differences (previously generated)
            $admin_dir = trim(str_replace($this->prodRootDir, '', $this->adminDir), DIRECTORY_SEPARATOR);
            $filepath_list_diff = $this->autoupgradePath.DIRECTORY_SEPARATOR.$this->diffFileList;
            if (file_exists($filepath_list_diff)) {
                $list_files_diff = unserialize(base64_decode(file_get_contents($filepath_list_diff)));
                // only keep list of files to delete. The modified files will be listed with _listFilesToUpgrade
                $list_files_diff = $list_files_diff['deleted'];
                foreach ($list_files_diff as $k => $path) {
                    if (preg_match("#autoupgrade#", $path)) {
                        unset($list_files_diff[$k]);
                    } else {
                        $list_files_diff[$k] = str_replace('/'.'admin', '/'.$admin_dir, $path);
                    }
                } // do not replace by DIRECTORY_SEPARATOR
            } else {
                $list_files_diff = [];
            }

            if (!($list_files_to_upgrade = $this->_listFilesToUpgrade($this->latestRootDir))) {
                return false;
            }

            // also add files to remove
            $list_files_to_upgrade = array_merge($list_files_diff, $list_files_to_upgrade);
            // save in a serialized array in $this->toUpgradeFileList
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toUpgradeFileList, base64_encode(serialize($list_files_to_upgrade)));
            $this->nextParams['filesToUpgrade'] = $this->toUpgradeFileList;
            $total_files_to_upgrade = count($list_files_to_upgrade);

            if ($total_files_to_upgrade == 0) {
                $this->nextQuickInfo[] = $this->l('[ERROR] Unable to find files to upgrade.');
                $this->nextErrors[] = $this->l('[ERROR] Unable to find files to upgrade.');
                $this->next_desc = $this->l('Unable to list files to upgrade');
                $this->next = 'error';

                return false;
            }
            $this->nextQuickInfo[] = sprintf($this->l('%s files will be upgraded.'), $total_files_to_upgrade);

            $this->next_desc = sprintf($this->l('%s files will be upgraded.'), $total_files_to_upgrade);
            $this->next = 'upgradeFiles';
            $this->stepDone = false;

            return true;
        }

        // later we could choose between _PS_ROOT_DIR_ or _PS_TEST_DIR_
        $this->destUpgradePath = $this->prodRootDir;

        $this->next = 'upgradeFiles';
        $filesToUpgrade = @unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->nextParams['filesToUpgrade'])));
        if (!is_array($filesToUpgrade)) {
            $this->next = 'error';
            $this->next_desc = $this->l('filesToUpgrade is not an array');
            $this->nextQuickInfo[] = $this->l('filesToUpgrade is not an array');
            $this->nextErrors[] = $this->l('filesToUpgrade is not an array');

            return false;
        }

        // @TODO : does not upgrade files in modules, translations if they have not a correct md5 (or crc32, or whatever) from previous version
        for ($i = 0; $i < self::$loopUpgradeFiles; $i++) {
            if (count($filesToUpgrade) <= 0) {
                $this->next = 'upgradeDb';
                if (file_exists(($this->nextParams['filesToUpgrade']))) {
                    unlink($this->nextParams['filesToUpgrade']);
                }
                $this->next_desc = $this->l('All files upgraded. Now upgrading database...');
                $this->nextResponseType = 'json';
                $this->stepDone = true;
                break;
            }

            $file = array_shift($filesToUpgrade);
            if (!$this->upgradeThisFile($file)) {
                // put the file back to the begin of the list
                $totalFiles = array_unshift($filesToUpgrade, $file);
                $this->next = 'error';
                $this->nextQuickInfo[] = sprintf($this->l('Error when trying to upgrade file %s.'), $file);
                $this->nextErrors[] = sprintf($this->l('Error when trying to upgrade file %s.'), $file);
                break;
            }
        }
        file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->nextParams['filesToUpgrade'], base64_encode(serialize($filesToUpgrade)));
        if (count($filesToUpgrade) > 0) {
            if (count($filesToUpgrade)) {
                $this->next_desc = sprintf($this->l('%1$s files left to upgrade.'), count($filesToUpgrade));
                $this->nextQuickInfo[] = sprintf($this->l('%2$s files left to upgrade.'), (isset($file) ? $file : ''), count($filesToUpgrade));
                $this->stepDone = false;
            }
        }

        return true;
    }

    /**
     * list files to upgrade and return it as array
     *
     * @param string $dir
     *
     * @return number of files found
     */
    public function _listFilesToUpgrade($dir)
    {
        static $list = [];
        if (!is_dir($dir)) {
            $this->nextQuickInfo[] = sprintf($this->l('[ERROR] %s does not exist or is not a directory.'), $dir);
            $this->nextErrors[] = sprintf($this->l('[ERROR] %s does not exist or is not a directory.'), $dir);
            $this->next_desc = $this->l('Nothing has been extracted. It seems the unzipping step has been skipped.');
            $this->next = 'error';

            return false;
        }

        $allFiles = scandir($dir);
        foreach ($allFiles as $file) {
            $fullPath = $dir.DIRECTORY_SEPARATOR.$file;

            if (!$this->_skipFile($file, $fullPath, "upgrade")) {
                $list[] = str_replace($this->latestRootDir, '', $fullPath);
                // if is_dir, we will create it :)
                if (is_dir($fullPath)) {
                    if (strpos($dir.DIRECTORY_SEPARATOR.$file, 'install') === false || strpos($dir.DIRECTORY_SEPARATOR.$file, 'modules') !== false) {
                        $this->_listFilesToUpgrade($fullPath);
                    }
                }
            }
        }

        return $list;
    }

    /**
     *    bool _skipFile : check whether a file is in backup or restore skip list
     *
     * @param type $file     : current file or directory name eg:'.svn' , 'settings.inc.php'
     * @param type $fullpath : current file or directory fullpath eg:'/home/web/www/prestashop/config/settings.inc.php'
     * @param type $way      : 'backup' , 'upgrade'
     */
    protected function _skipFile($file, $fullpath, $way = 'backup')
    {
        $fullpath = str_replace('\\', '/', $fullpath); // wamp compliant
        $rootpath = str_replace('\\', '/', $this->prodRootDir);
        $admin_dir = str_replace($this->prodRootDir, '', $this->adminDir);
        switch ($way) {
            case 'backup':
                if (in_array($file, $this->backupIgnoreFiles)) {
                    return true;
                }

                foreach ($this->backupIgnoreAbsoluteFiles as $path) {
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$admin_dir, $path);
                    if ($fullpath == $rootpath.$path) {
                        return true;
                    }
                }
                break;
            // restore or upgrade way : ignore the same files
            // note the restore process use skipFiles only if xml md5 files
            // are unavailable
            case 'restore':
                if (in_array($file, $this->restoreIgnoreFiles)) {
                    return true;
                }

                foreach ($this->restoreIgnoreAbsoluteFiles as $path) {
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$admin_dir, $path);
                    if ($fullpath == $rootpath.$path) {
                        return true;
                    }
                }
                break;
            case 'upgrade':
                // keep mail : will skip only if already exists
                if (!$this->keepMails) /* If set to false, we will not upgrade/replace the "mails" directory */ {
                    if (strpos(str_replace('/', DIRECTORY_SEPARATOR, $fullpath), DIRECTORY_SEPARATOR.'mails'.DIRECTORY_SEPARATOR)) {
                        return true;
                    }
                }
                if (in_array($file, $this->excludeFilesFromUpgrade)) {
                    if ($file[0] != '.') {
                        $this->nextQuickInfo[] = sprintf($this->l('File %s is preserved'), $file);
                    }

                    return true;
                }

                foreach ($this->excludeAbsoluteFilesFromUpgrade as $path) {
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$admin_dir, $path);
                    if (strpos($fullpath, $rootpath.$path) !== false) {
                        $this->nextQuickInfo[] = sprintf($this->l('File %s is preserved'), $fullpath);

                        return true;
                    }
                }

                break;
            // default : if it's not a backup or an upgrade, do not skip the file
            default:
                return false;
        }

        // by default, don't skip
        return false;
    }

    /**
     * upgradeThisFile
     *
     * @param mixed $file
     *
     * @return void
     */
    public function upgradeThisFile($file)
    {

        // note : keepMails is handled in skipFiles
        // translations_custom and mails_custom list are currently not used
        // later, we could handle customization with some kind of diff functions
        // for now, just copy $file in str_replace($this->latestRootDir,_PS_ROOT_DIR_)
        $orig = $this->latestRootDir.$file;
        $dest = $this->destUpgradePath.$file;

        if ($this->_skipFile($file, $dest, 'upgrade')) {
            $this->nextQuickInfo[] = sprintf($this->l('%s ignored'), $file);

            return true;
        } else {
            if (is_dir($orig)) {
                // if $dest is not a directory (that can happen), just remove that file
                if (!is_dir($dest) and file_exists($dest)) {
                    unlink($dest);
                    $this->nextQuickInfo[] = sprintf($this->l('[WARNING] File %1$s has been deleted.'), $file);
                }
                if (!file_exists($dest)) {
                    if (mkdir($dest)) {
                        $this->nextQuickInfo[] = sprintf($this->l('Directory %1$s created.'), $file);

                        return true;
                    } else {
                        $this->next = 'error';
                        $this->nextQuickInfo[] = sprintf($this->l('Error while creating directory %s.'), $dest);
                        $this->nextErrors[] = $this->next_desc = sprintf($this->l('Error while creating directory %s.'), $dest);

                        return false;
                    }
                } else { // directory already exists
                    $this->nextQuickInfo[] = sprintf($this->l('Directory %1$s already exists.'), $file);

                    return true;
                }
            } elseif (is_file($orig)) {
                if ($this->isTranslationFile($file) && file_exists($dest)) {
                    $type_trad = $this->getTranslationFileType($file);
                    $res = $this->mergeTranslationFile($orig, $dest, $type_trad);
                    if ($res) {
                        $this->nextQuickInfo[] = sprintf($this->l('[TRANSLATION] The translation files have been merged into file %1$s.'), $dest);

                        return true;
                    } else {
                        $this->nextQuickInfo[] = sprintf($this->l('[TRANSLATION] The translation files have not been merged into file %1$s. Switch to copy %2$s.'), $dest, $dest);
                        $this->nextErrors[] = sprintf($this->l('[TRANSLATION] The translation files have not been merged into file %1$s. Switch to copy %2$s.'), $dest, $dest);
                    }
                }

                // upgrade exception were above. This part now process all files that have to be upgraded (means to modify or to remove)
                // delete before updating (and this will also remove deprecated files)
                if (copy($orig, $dest)) {
                    $this->nextQuickInfo[] = sprintf($this->l('Copied %1$s.'), $file);

                    return true;
                } else {
                    $this->next = 'error';
                    $this->nextQuickInfo[] = sprintf($this->l('error for copying %1$s'), $file);
                    $this->nextErrors[] = $this->next_desc = sprintf($this->l('Error while copying file %1$s'), $file);

                    return false;
                }
            } elseif (is_file($dest)) {
                if (file_exists($dest)) {
                    unlink($dest);
                }
                $this->nextQuickInfo[] = sprintf('removed file %1$s.', $file);

                return true;
            } elseif (is_dir($dest)) {
                if (strpos($dest, DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR) === false) {
                    self::deleteDirectory($dest, true);
                }
                $this->nextQuickInfo[] = sprintf('removed dir %1$s.', $file);

                return true;
            } else {
                return true;
            }
        }
    }

    /**
     * return true if $file is a translation file
     *
     * @param string $file filepath (from prestashop root)
     *
     * @access public
     * @return boolean
     */
    public function isTranslationFile($file)
    {
        if ($this->getTranslationFileType($file) !== false) {
            return true;
        }

        return false;
    }

    /**
     * getTranslationFileType
     *
     * @param string $file filepath to check
     *
     * @access public
     * @return string type of translation item
     */
    public function getTranslationFileType($file)
    {
        $type = false;
        // line shorter
        $separator = addslashes(DIRECTORY_SEPARATOR);
        $translation_dir = $separator.'translations'.$separator;
        if (version_compare(_PS_VERSION_, '1.5.0.5', '<')) {
            $regex_module = '#'.$separator.'modules'.$separator.'.*'.$separator.'('.implode('|', $this->installedLanguagesIso).')\.php#';
        } else {
            $regex_module = '#'.$separator.'modules'.$separator.'.*'.$translation_dir.'('.implode('|', $this->installedLanguagesIso).')\.php#';
        }

        if (preg_match($regex_module, $file)) {
            $type = 'module';
        } elseif (preg_match('#'.$translation_dir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'admin\.php#', $file)) {
            $type = 'back office';
        } elseif (preg_match('#'.$translation_dir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'errors\.php#', $file)) {
            $type = 'error message';
        } elseif (preg_match('#'.$translation_dir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'fields\.php#', $file)) {
            $type = 'field';
        } elseif (preg_match('#'.$translation_dir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'pdf\.php#', $file)) {
            $type = 'pdf';
        } elseif (preg_match('#'.$separator.'themes'.$separator.'(default|prestashop)'.$separator.'lang'.$separator.'('.implode('|', $this->installedLanguagesIso).')\.php#', $file)) {
            $type = 'front office';
        }

        return $type;
    }

    /**
     * merge the translations of $orig into $dest, according to the $type of translation file
     *
     * @param string $orig file from upgrade package
     * @param string $dest filepath of destination
     * @param string $type type of translation file (module, bo, fo, field, pdf, error)
     *
     * @access public
     * @return boolean
     */
    public function mergeTranslationFile($orig, $dest, $type)
    {
        switch ($type) {
            case 'front office':
                $var_name = '_LANG';
                break;
            case 'back office':
                $var_name = '_LANGADM';
                break;
            case 'error message':
                $var_name = '_ERRORS';
                break;
            case 'field':
                $var_name = '_FIELDS';
                break;
            case 'module':
                $var_name = '_MODULE';
                // if current version is before 1.5.0.5, module has no translations dir
                if (version_compare(_PS_VERSION_, '1.5.0.5', '<') && (version_compare($this->install_version, '1.5.0.5', '>'))) {
                    $dest = str_replace(DIRECTORY_SEPARATOR.'translations', '', $dest);
                }

                break;
            case 'pdf':
                $var_name = '_LANGPDF';
                break;
            case 'mail':
                $var_name = '_LANGMAIL';
                break;
            default:
                return false;
        }

        if (!file_exists($orig)) {
            $this->nextQuickInfo[] = sprintf($this->l('[NOTICE] File %s does not exist, merge skipped.'), $orig);

            return true;
        }
        include($orig);
        if (!isset($$var_name)) {
            $this->nextQuickInfo[] = sprintf($this->l('[WARNING] %1$s variable missing in file %2$s. Merge skipped.'), $var_name, $orig);

            return true;
        }
        $var_orig = $$var_name;

        if (!file_exists($dest)) {
            $this->nextQuickInfo[] = sprintf($this->l('[NOTICE] File %s does not exist, merge skipped.'), $dest);

            return false;
        }
        include($dest);
        if (!isset($$var_name)) {
            // in that particular case : file exists, but variable missing, we need to delete that file
            // (if not, this invalid file will be copied in /translations during upgradeDb process)
            if ('module' == $type && file_exists($dest)) {
                unlink($dest);
            }
            $this->nextQuickInfo[] = sprintf($this->l('[WARNING] %1$s variable missing in file %2$s. File %2$s deleted and merge skipped.'), $var_name, $dest);

            return false;
        }
        $var_dest = $$var_name;

        $merge = array_merge($var_orig, $var_dest);

        if ($fd = fopen($dest, 'w')) {
            fwrite($fd, "<?php\n\nglobal \$".$var_name.";\n\$".$var_name." = array();\n");
            foreach ($merge as $k => $v) {
                if (get_magic_quotes_gpc()) {
                    $v = stripslashes($v);
                }
                if ('mail' == $type) {
                    fwrite($fd, '$'.$var_name.'[\''.$this->db->escape($k).'\'] = \''.$this->db->escape($v).'\';'."\n");
                } else {
                    fwrite($fd, '$'.$var_name.'[\''.$this->db->escape($k, true).'\'] = \''.$this->db->escape($v, true).'\';'."\n");
                }
            }
            fwrite($fd, "\n?>");
            fclose($fd);
        } else {
            return false;
        }

        return true;
    }

    /**
     * upgrade all partners modules according to the installed prestashop version
     *
     * @access public
     * @return void
     */
    public function ajaxProcessUpgradeModules()
    {
        $start_time = time();
        if (!isset($this->nextParams['modulesToUpgrade'])) {
            // list saved in $this->toUpgradeFileList
            $total_modules_to_upgrade = $this->_listModulesToUpgrade();
            if ($total_modules_to_upgrade) {
                $this->nextQuickInfo[] = sprintf($this->l('%s modules will be upgraded.'), $total_modules_to_upgrade);
                $this->next_desc = sprintf($this->l('%s modules will be upgraded.'), $total_modules_to_upgrade);
            }
            $this->stepDone = false;
            $this->next = 'upgradeModules';

            return true;
        }

        $this->next = 'upgradeModules';
        if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->nextParams['modulesToUpgrade'])) {
            $listModules = @unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->nextParams['modulesToUpgrade'])));
        } else {
            $listModules = [];
        }

        if (!is_array($listModules)) {
            $this->next = 'upgradeComplete';
            $this->warning_exists = true;
            $this->next_desc = $this->l('upgradeModule step has not ended correctly.');
            $this->nextQuickInfo[] = $this->l('listModules is not an array. No module has been updated.');
            $this->nextErrors[] = $this->l('listModules is not an array. No module has been updated.');

            return true;
        }

        $time_elapsed = time() - $start_time;
        // module list
        if (count($listModules) > 0) {
            do {
                $module_info = array_shift($listModules);

                $this->upgradeThisModule($module_info['id'], $module_info['name']);
                $time_elapsed = time() - $start_time;
            } while (($time_elapsed < self::$loopUpgradeModulesTime) && count($listModules) > 0);

            $modules_left = count($listModules);
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toUpgradeModuleList, base64_encode(serialize($listModules)));
            unset($listModules);

            $this->next = 'upgradeModules';
            if ($modules_left) {
                $this->next_desc = sprintf($this->l('%s modules left to upgrade.'), $modules_left);
            }
            $this->stepDone = false;
        } else {
            if (version_compare($this->install_version, '1.5.0.0', '>=')) {
                $modules_to_delete['backwardcompatibility'] = 'Backward Compatibility';
                $modules_to_delete['dibs'] = 'Dibs';
                $modules_to_delete['cloudcache'] = 'Cloudcache';
                $modules_to_delete['mobile_theme'] = 'The 1.4 mobile_theme';
                $modules_to_delete['trustedshops'] = 'Trustedshops';
                $modules_to_delete['dejala'] = 'Dejala';
                $modules_to_delete['stripejs'] = 'Stripejs';
                $modules_to_delete['blockvariouslinks'] = 'Block Various Links';

                foreach ($modules_to_delete as $key => $module) {
                    Db::getInstance()->execute(
                        'DELETE ms.*, hm.*
					FROM `'._DB_PREFIX_.'module_shop` ms
					INNER JOIN `'._DB_PREFIX_.'hook_module` hm USING (`id_module`)
					INNER JOIN `'._DB_PREFIX_.'module` m USING (`id_module`)
					WHERE m.`name` LIKE \''.pSQL($key).'\''
                    );
                    Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'module` SET `active` = 0 WHERE `name` LIKE \''.pSQL($key).'\'');

                    $path = $this->prodRootDir.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$key.DIRECTORY_SEPARATOR;
                    if (file_exists($path.$key.'.php')) {
                        if (self::deleteDirectory($path)) {
                            $this->nextQuickInfo[] = sprintf($this->l('%1$s module is not compatible with 1.5.X, it will be removed from your ftp.'), $module);
                        } else {
                            $this->nextErrors[] = sprintf($this->l('%1$s module is not compatible with 1.5.X, please remove it from your ftp.'), $module);
                        }
                    }
                }
            }

            $this->stepDone = true;
            $this->status = 'ok';
            $this->next = 'cleanDatabase';
            $this->next_desc = $this->l('Addons modules files have been upgraded.');
            $this->nextQuickInfo[] = $this->l('Addons modules files have been upgraded.');
            if ($this->manualMode) {
                $this->writeConfig(['PS_AUTOUP_MANUAL_MODE' => '0']);
            }

            return true;
        }

        return true;
    }

    /**
     * list modules to upgrade and save them in a serialized array in $this->toUpgradeModuleList
     *
     * @param string $dir
     *
     * @return number of files found
     */
    public function _listModulesToUpgrade()
    {
        static $list = [];

        $dir = $this->prodRootDir.DIRECTORY_SEPARATOR.'modules';

        if (!is_dir($dir)) {
            $this->nextQuickInfo[] = sprintf($this->l('[ERROR] %s does not exist or is not a directory.'), $dir);
            $this->nextErrors[] = sprintf($this->l('[ERROR] %s does not exist or is not a directory.'), $dir);
            $this->next_desc = $this->l('Nothing has been extracted. It seems the unzip step has been skipped.');
            $this->next = 'error';

            return false;
        }

        $allModules = scandir($dir);
        foreach ($allModules as $module_name) {
            if (is_file($dir.DIRECTORY_SEPARATOR.$module_name)) {
                continue;
            } elseif (is_dir($dir.DIRECTORY_SEPARATOR.$module_name.DIRECTORY_SEPARATOR)) {
                if (is_array($this->modules_addons)) {
                    $id_addons = array_search($module_name, $this->modules_addons);
                }
                if (isset($id_addons) && $id_addons) {
                    if ($module_name != $this->autoupgradeDir) {
                        $list[] = ['id' => $id_addons, 'name' => $module_name];
                    }
                }
            }
        }
        file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toUpgradeModuleList, base64_encode(serialize($list)));
        $this->nextParams['modulesToUpgrade'] = $this->toUpgradeModuleList;

        return count($list);
    }

    /**
     * upgrade module $name (identified by $id_module on addons server)
     *
     * @param mixed $id_module
     * @param mixed $name
     *
     * @access public
     * @return void
     */
    public function upgradeThisModule($id_module, $name)
    {
        $zip_fullpath = $this->tmpPath.DIRECTORY_SEPARATOR.$name.'.zip';

        $dest_extract = $this->prodRootDir.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR;

        $addons_url = 'api.addons.prestashop.com';
        $protocolsList = ['https://' => 443, 'http://' => 80];
        if (!extension_loaded('openssl')) {
            unset($protocolsList['https://']);
        } else {
            unset($protocolsList['http://']);
        }

        $postData = 'version='.$this->install_version.'&method=module&id_module='.(int) $id_module;

        // Make the request
        $opts = [
            'http' => [
                'method'  => 'POST',
                'content' => $postData,
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'timeout' => 10,
            ],
        ];
        $context = stream_context_create($opts);
        foreach ($protocolsList as $protocol => $port) {
            // file_get_contents can return false if https is not supported (or warning)
            $content = Tools::file_get_contents($protocol.$addons_url, false, $context);
            if ($content == false || substr($content, 5) == '<?xml') {
                continue;
            }
            if ($content !== null) {
                if ((bool) file_put_contents($zip_fullpath, $content)) {
                    if (filesize($zip_fullpath) <= 300) {
                        unlink($zip_fullpath);
                    } // unzip in modules/[mod name] old files will be conserved
                    elseif ($this->ZipExtract($zip_fullpath, $dest_extract)) {
                        $this->nextQuickInfo[] = sprintf($this->l('The files of module %s have been upgraded.'), $name);
                        if (file_exists($zip_fullpath)) {
                            unlink($zip_fullpath);
                        }
                    } else {
                        $this->nextQuickInfo[] = sprintf($this->l('[WARNING] Error when trying to upgrade module %s.'), $name);
                        $this->warning_exists = 1;
                    }
                } else {
                    $this->nextQuickInfo[] = sprintf($this->l('[ERROR] Unable to write module %s\'s zip file in temporary directory.'), $name);
                    $this->nextErrors[] = sprintf($this->l('[ERROR] Unable to write module %s\'s zip file in temporary directory.'), $name);
                    $this->warning_exists = 1;
                }
            } else {
                $this->nextQuickInfo[] = sprintf($this->l('[ERROR] No response from Addons server.'));
                $this->nextErrors[] = sprintf($this->l('[ERROR] No response from Addons server.'));
                $this->warning_exists = 1;
            }
        }

        return true;
    }

    public function ajaxProcessUpgradeDb()
    {
        $this->nextParams = $this->currentParams;
        if (!$this->doUpgrade()) {
            $this->next = 'error';
            $this->next_desc = $this->l('Error during database upgrade. You may need to restore your database.');

            return false;
        }

        if (version_compare(INSTALL_VERSION, '1.7.1.0', '<')) {
            $this->next = 'upgradeModules';
            $this->next_desc = $this->l('Database upgraded. Now upgrading your Addons modules...');
        }

        return true;
    }

    /**
     * This function now replaces doUpgrade.php or upgrade.php
     *
     * @return bool
     */
    public function doUpgrade()
    {
        if (!$this->doUpgradeSetupConst()) {
            return false;
        }

        if (version_compare(INSTALL_VERSION, '1.7.1.0', '>=')) {
            $this->doUpgrade17();
        } elseif (version_compare(INSTALL_VERSION, '1.7.0.0', '>=')) {
            $this->next = 'error';
            $this->next_desc = $this->l('You cannot upgrade to this version.');

            return false;
        } else {
            $filePrefix = 'PREFIX_';
            $engineType = 'ENGINE_TYPE';

            $mysqlEngine = (defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'MyISAM');

            //old version detection
            global $oldversion, $logger;
            $oldversion = false;
            if (file_exists(SETTINGS_FILE)) {
                include_once(SETTINGS_FILE);

                // include_once(DEFINES_FILE);
                $oldversion = _PS_VERSION_;
            } else {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->l('The config/settings.inc.php file was not found.');
                $this->nextErrors[] = $this->l('The config/settings.inc.php file was not found.');

                return false;
            }

            if (!defined('__PS_BASE_URI__')) {
                define('__PS_BASE_URI__', realpath(dirname($_SERVER['SCRIPT_NAME'])).'/../../');
            }

            if (!defined('_THEMES_DIR_')) {
                define('_THEMES_DIR_', __PS_BASE_URI__.'themes/');
            }

            $oldversion = _PS_VERSION_;
            $versionCompare = version_compare(INSTALL_VERSION, $oldversion);

            if ($versionCompare == '-1') {
                $this->next = 'error';
                $this->nextQuickInfo[] = sprintf($this->l('Current version: %1$s. Version to install: %2$s.'), $oldversion, INSTALL_VERSION);
                $this->nextErrors[] = sprintf($this->l('Current version: %1$s. Version to install: %2$s'), $oldversion, INSTALL_VERSION);
                $this->nextQuickInfo[] = $this->l('[ERROR] Version to install is too old.');
                $this->nextErrors[] = $this->l('[ERROR] Version to install is too old.');

                return false;
            } elseif ($versionCompare == 0) {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->l(sprintf('You already have the %s version.', INSTALL_VERSION));
                $this->nextErrors[] = $this->l(sprintf('You already have the %s version.', INSTALL_VERSION));

                return false;
            } elseif ($versionCompare === false) {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->l('There is no older version. Did you delete or rename the config/settings.inc.php file?');
                $this->nextErrors[] = $this->l('There is no older version. Did you delete or rename the config/settings.inc.php file?');

                return false;
            }

            //check DB access
            $this->db;
            error_reporting(E_ALL);
            $resultDB = Db::checkConnection(_DB_SERVER_, _DB_USER_, _DB_PASSWD_, _DB_NAME_);
            if ($resultDB !== 0) {
                // $logger->logError('Invalid database configuration.');
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->l('Invalid database configuration');
                $this->nextErrors[] = $this->l('Invalid database configuration');

                return false;
            }

            //custom sql file creation
            $upgradeFiles = [];

            $upgrade_dir_sql = INSTALL_PATH.'/upgrade/sql';
            // if 1.4;
            if (!file_exists($upgrade_dir_sql)) {
                $upgrade_dir_sql = INSTALL_PATH.'/sql/upgrade';
            }

            if (!file_exists($upgrade_dir_sql)) {
                $this->next = 'error';
                $this->next_desc = $this->l('Unable to find upgrade directory in the installation path.');

                return false;
            }

            if ($handle = opendir($upgrade_dir_sql)) {
                while (false !== ($file = readdir($handle))) {
                    if ($file != '.' and $file != '..') {
                        $upgradeFiles[] = str_replace(".sql", "", $file);
                    }
                }
                closedir($handle);
            }
            if (empty($upgradeFiles)) {
                $this->next = 'error';
                $this->nextQuickInfo[] = sprintf($this->l('Cannot find the SQL upgrade files. Please check that the %s folder is not empty.'), $upgrade_dir_sql);
                $this->nextErrors[] = sprintf($this->l('Cannot find the SQL upgrade files. Please check that the %s folder is not empty.'), $upgrade_dir_sql);

                // fail 31
                return false;
            }
            natcasesort($upgradeFiles);
            $neededUpgradeFiles = [];

            $arrayVersion = explode('.', $oldversion);
            $versionNumbers = count($arrayVersion);
            if ($versionNumbers != 4) {
                $arrayVersion = array_pad($arrayVersion, 4, '0');
            }

            $oldversion = implode('.', $arrayVersion);

            foreach ($upgradeFiles as $version) {
                if (version_compare($version, $oldversion) == 1 && version_compare(INSTALL_VERSION, $version) != -1) {
                    $neededUpgradeFiles[] = $version;
                }
            }

            if (strpos(INSTALL_VERSION, '.') === false) {
                $this->nextQuickInfo[] = sprintf($this->l('%s is not a valid version number.'), INSTALL_VERSION);
                $this->nextErrors[] = sprintf($this->l('%s is not a valid version number.'), INSTALL_VERSION);

                return false;
            }

            $sqlContentVersion = [];
            if ($this->deactivateCustomModule) {
                require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'deactivate_custom_modules.php');
                deactivate_custom_modules();
            }

            if (version_compare(INSTALL_VERSION, '1.5.6.1', '=')) {
                $filename = _PS_INSTALLER_PHP_UPGRADE_DIR_.'migrate_orders.php';
                $content = file_get_contents($filename);
                $str_old[] = '$values_order_detail = array();';
                $str_old[] = '$values_order = array();';
                $str_old[] = '$col_order_detail = array();';
                $content = str_replace($str_old, '', $content);
                file_put_contents($filename, $content);
            }

            foreach ($neededUpgradeFiles as $version) {
                $file = $upgrade_dir_sql.DIRECTORY_SEPARATOR.$version.'.sql';
                if (!file_exists($file)) {
                    $this->next = 'error';
                    $this->nextQuickInfo[] = sprintf($this->l('Error while loading SQL upgrade file "%s.sql".'), $version);
                    $this->nextErrors[] = sprintf($this->l('Error while loading SQL upgrade file "%s.sql".'), $version);

                    return false;
                    $logger->logError('Error while loading SQL upgrade file.');
                }
                if (!$sqlContent = file_get_contents($file)."\n") {
                    $this->next = 'error';
                    $this->nextQuickInfo[] = $this->l(sprintf('Error while loading SQL upgrade file %s.', $version));
                    $this->nextErrors[] = $this->l(sprintf('Error while loading sql SQL file %s.', $version));

                    return false;
                    $logger->logError(sprintf('Error while loading sql upgrade file %s.', $version));
                }
                $sqlContent = str_replace([$filePrefix, $engineType], [_DB_PREFIX_, $mysqlEngine], $sqlContent);
                $sqlContent = preg_split("/;\s*[\r\n]+/", $sqlContent);
                $sqlContentVersion[$version] = $sqlContent;
            }

            //sql file execution
            global $requests, $warningExist;
            $requests = '';
            $warningExist = false;

            // Configuration::loadConfiguration();
            $request = '';

            foreach ($sqlContentVersion as $upgrade_file => $sqlContent) {
                foreach ($sqlContent as $query) {
                    $query = trim($query);
                    if (!empty($query)) {
                        /* If php code have to be executed */
                        if (strpos($query, '/* PHP:') !== false) {
                            /* Parsing php code */
                            $pos = strpos($query, '/* PHP:') + strlen('/* PHP:');
                            $phpString = substr($query, $pos, strlen($query) - $pos - strlen(' */;'));
                            $php = explode('::', $phpString);
                            preg_match('/\((.*)\)/', $phpString, $pattern);
                            $paramsString = trim($pattern[0], '()');
                            preg_match_all('/([^,]+),? ?/', $paramsString, $parameters);
                            if (isset($parameters[1])) {
                                $parameters = $parameters[1];
                            } else {
                                $parameters = [];
                            }
                            if (is_array($parameters)) {
                                foreach ($parameters as &$parameter) {
                                    $parameter = str_replace('\'', '', $parameter);
                                }
                            }

                            // reset phpRes to a null value
                            $phpRes = null;
                            /* Call a simple function */
                            if (strpos($phpString, '::') === false) {
                                $func_name = str_replace($pattern[0], '', $php[0]);
                                if (version_compare(INSTALL_VERSION, '1.5.5.0', '=') && $func_name == 'fix_download_product_feature_active') {
                                    continue;
                                }

                                if (!file_exists(_PS_INSTALLER_PHP_UPGRADE_DIR_.strtolower($func_name).'.php')) {
                                    $this->nextQuickInfo[] = '<div class="upgradeDbError">[ERROR] '.$upgrade_file.' PHP - missing file '.$query.'</div>';
                                    $this->nextErrors[] = '[ERROR] '.$upgrade_file.' PHP - missing file '.$query;
                                    $warningExist = true;
                                } else {
                                    require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.strtolower($func_name).'.php');
                                    $phpRes = call_user_func_array($func_name, $parameters);
                                }
                            } /* Or an object method */
                            else {
                                $func_name = [$php[0], str_replace($pattern[0], '', $php[1])];
                                $this->nextQuickInfo[] = '<div class="upgradeDbError">[ERROR] '.$upgrade_file.' PHP - Object Method call is forbidden ( '.$php[0].'::'.str_replace($pattern[0], '', $php[1]).')</div>';
                                $this->nextErrors[] = '[ERROR] '.$upgrade_file.' PHP - Object Method call is forbidden ('.$php[0].'::'.str_replace($pattern[0], '', $php[1]).')';
                                $warningExist = true;
                            }

                            if (isset($phpRes) && (is_array($phpRes) && !empty($phpRes['error'])) || $phpRes === false) {
                                // $this->next = 'error';
                                $this->nextQuickInfo[] = '
								<div class="upgradeDbError">
									[ERROR] PHP '.$upgrade_file.' '.$query."\n".'
									'.(empty($phpRes['error']) ? '' : $phpRes['error']."\n").'
									'.(empty($phpRes['msg']) ? '' : ' - '.$phpRes['msg']."\n").'
								</div>';
                                $this->nextErrors[] = '
								[ERROR] PHP '.$upgrade_file.' '.$query."\n".'
								'.(empty($phpRes['error']) ? '' : $phpRes['error']."\n").'
								'.(empty($phpRes['msg']) ? '' : ' - '.$phpRes['msg']."\n");
                                $warningExist = true;
                            } else {
                                $this->nextQuickInfo[] = '<div class="upgradeDbOk">[OK] PHP '.$upgrade_file.' : '.$query.'</div>';
                            }
                            if (isset($phpRes)) {
                                unset($phpRes);
                            }
                        } else {
                            if (strstr($query, 'CREATE TABLE') !== false) {
                                $pattern = '/CREATE TABLE.*[`]*'._DB_PREFIX_.'([^`]*)[`]*\s\(/';
                                preg_match($pattern, $query, $matches);;
                                if (isset($matches[1]) && $matches[1]) {
                                    $drop = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.$matches[1].'`;';
                                    $result = $this->db->execute($drop, false);
                                    if ($result) {
                                        $this->nextQuickInfo[] = '<div class="upgradeDbOk">'.sprintf($this->l('[DROP] SQL %s table has been dropped.'), '`'._DB_PREFIX_.$matches[1].'`').'</div>';
                                    }
                                }
                            }
                            $result = $this->db->execute($query, false);
                            if (!$result) {
                                $error = $this->db->getMsgError();
                                $error_number = $this->db->getNumberError();
                                $this->nextQuickInfo[] = '
								<div class="upgradeDbError">
								[WARNING] SQL '.$upgrade_file.'
								'.$error_number.' in '.$query.': '.$error.'</div>';

                                $duplicates = ['1050', '1054', '1060', '1061', '1062', '1091'];
                                if (!in_array($error_number, $duplicates)) {
                                    $this->nextErrors[] = 'SQL '.$upgrade_file.' '.$error_number.' in '.$query.': '.$error;
                                    $warningExist = true;
                                }
                            } else {
                                $this->nextQuickInfo[] = '<div class="upgradeDbOk">[OK] SQL '.$upgrade_file.' '.$query.'</div>';
                            }
                        }
                        if (isset($query)) {
                            unset($query);
                        }
                    }
                }
            }
            if ($this->next == 'error') {
                $this->next_desc = $this->l('An error happened during the database upgrade.');

                return false;
            }

            $this->nextQuickInfo[] = $this->l('Database upgrade OK'); // no error !

            # At this point, database upgrade is over.
            # Now we need to add all previous missing settings items, and reset cache and compile directories
            $this->writeNewSettings();

            // Settings updated, compile and cache directories must be emptied
            $arrayToClean[] = $this->prodRootDir.'/tools/smarty/cache/';
            $arrayToClean[] = $this->prodRootDir.'/tools/smarty/compile/';
            $arrayToClean[] = $this->prodRootDir.'/tools/smarty_v2/cache/';
            $arrayToClean[] = $this->prodRootDir.'/tools/smarty_v2/compile/';
            if (version_compare(INSTALL_VERSION, '1.5.0.0', '>')) {
                $arrayToClean[] = $this->prodRootDir.'/cache/smarty/cache/';
                $arrayToClean[] = $this->prodRootDir.'/cache/smarty/compile/';
            }

            foreach ($arrayToClean as $dir) {
                if (!file_exists($dir)) {
                    $this->nextQuickInfo[] = sprintf($this->l('[SKIP] directory "%s" does not exist and cannot be emptied.'), str_replace($this->prodRootDir, '', $dir));
                    continue;
                } else {
                    foreach (scandir($dir) as $file) {
                        if ($file[0] != '.' && $file != 'index.php' && $file != '.htaccess') {
                            if (is_file($dir.$file)) {
                                unlink($dir.$file);
                            } elseif (is_dir($dir.$file.DIRECTORY_SEPARATOR)) {
                                self::deleteDirectory($dir.$file.DIRECTORY_SEPARATOR);
                            }
                            $this->nextQuickInfo[] = sprintf($this->l('[CLEANING CACHE] File %s removed'), $file);
                        }
                    }
                }
            }

            if (version_compare(INSTALL_VERSION, '1.5.0.0', '>')) {
                Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'configuration` SET `name` = \'PS_LEGACY_IMAGES\' WHERE name LIKE \'0\' AND `value` = 1');
                Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'configuration` SET `value` = 0 WHERE `name` LIKE \'PS_LEGACY_IMAGES\'');
                if (Db::getInstance()->getValue('SELECT COUNT(id_product_download) FROM `'._DB_PREFIX_.'product_download` WHERE `active` = 1') > 0) {
                    Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'configuration` SET `value` = 1 WHERE `name` LIKE \'PS_VIRTUAL_PROD_FEATURE_ACTIVE\'');
                }

                if (defined('_THEME_NAME_') && $this->updateDefaultTheme && preg_match('#(default|prestashop|default-boostrap)$#', _THEME_NAME_)) {
                    $separator = addslashes(DIRECTORY_SEPARATOR);
                    $file = _PS_ROOT_DIR_.$separator.'themes'.$separator._THEME_NAME_.$separator.'cache'.$separator;
                    if (file_exists($file)) {
                        foreach (scandir($file) as $cache) {
                            if ($cache[0] != '.' && $cache != 'index.php' && $cache != '.htaccess' && file_exists($file.$cache) && !is_dir($file.$cache)) {
                                if (file_exists($dir.$cache)) {
                                    unlink($file.$cache);
                                }
                            }
                        }
                    }
                }

                if (version_compare(_PS_VERSION_, '1.5.0.0', '<=')) {
                    $dir = _PS_ROOT_DIR_.'/controllers/';
                    if (file_exists($dir)) {
                        foreach (scandir($dir) as $file) {
                            if (!is_dir($file) && $file[0] != '.' && $file != 'index.php' && $file != '.htaccess') {
                                if (file_exists($dir.basename(str_replace('.php', '', $file).'.php'))) {
                                    unlink($dir.basename($file));
                                }
                            }
                        }
                    }

                    $dir = _PS_ROOT_DIR_.'/classes/';
                    foreach (self::$classes14 as $class) {
                        if (file_exists($dir.basename($class).'.php')) {
                            unlink($dir.basename($class).'.php');
                        }
                    }

                    $dir = _PS_ADMIN_DIR_.'/tabs/';
                    if (file_exists($dir)) {
                        foreach (scandir($dir) as $file) {
                            if (!is_dir($file) && $file[0] != '.' && $file != 'index.php' && $file != '.htaccess') {
                                if (file_exists($dir.basename(str_replace('.php', '', $file).'.php'))) {
                                    unlink($dir.basename($file));
                                }
                            }
                        }
                    }
                }

                if (version_compare($this->install_version, '1.5.4.0', '>=')) {
                    // Upgrade languages
                    if (!defined('_PS_TOOL_DIR_')) {
                        define('_PS_TOOL_DIR_', _PS_ROOT_DIR_.'/tools/');
                    }
                    if (!defined('_PS_TRANSLATIONS_DIR_')) {
                        define('_PS_TRANSLATIONS_DIR_', _PS_ROOT_DIR_.'/translations/');
                    }
                    if (!defined('_PS_MODULES_DIR_')) {
                        define('_PS_MODULES_DIR_', _PS_ROOT_DIR_.'/modules/');
                    }
                    if (!defined('_PS_MAILS_DIR_')) {
                        define('_PS_MAILS_DIR_', _PS_ROOT_DIR_.'/mails/');
                    }
                    $langs = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'lang` WHERE `active` = 1');
                    require_once(_PS_TOOL_DIR_.'tar/Archive_Tar.php');
                    if (is_array($langs)) {
                        foreach ($langs as $lang) {
                            $lang_pack = Tools::jsonDecode(Tools::file_get_contents('http'.(extension_loaded('openssl') ? 's' : '').'://www.prestashop.com/download/lang_packs/get_language_pack.php?version='.$this->install_version.'&iso_lang='.$lang['iso_code']));

                            if (!$lang_pack) {
                                continue;
                            } elseif ($content = Tools::file_get_contents('http'.(extension_loaded('openssl') ? 's' : '').'://translations.prestashop.com/download/lang_packs/gzip/'.$lang_pack->version.'/'.$lang['iso_code'].'.gzip')) {
                                $file = _PS_TRANSLATIONS_DIR_.$lang['iso_code'].'.gzip';
                                if ((bool) file_put_contents($file, $content)) {
                                    $gz = new Archive_Tar($file, true);
                                    $files_list = $gz->listContent();
                                    if (!$this->keepMails) {
                                        $files_listing = [];
                                        foreach ($files_list as $i => $file) {
                                            if (preg_match('/^mails\/'.$lang['iso_code'].'\/.*/', $file['filename'])) {
                                                unset($files_list[$i]);
                                            }
                                        }
                                        foreach ($files_list as $file) {
                                            if (isset($file['filename']) && is_string($file['filename'])) {
                                                $files_listing[] = $file['filename'];
                                            }
                                        }
                                        if (is_array($files_listing)) {
                                            $gz->extractList($files_listing, _PS_TRANSLATIONS_DIR_.'../', '');
                                        }
                                    } else {
                                        $gz->extract(_PS_TRANSLATIONS_DIR_.'../', false);
                                    }
                                }
                            }
                        }
                    }
                }

                if (version_compare($this->install_version, '1.6.0.0', '>')) {
                    if (version_compare($this->install_version, '1.6.1.0', '>=')) {
                        require_once(_PS_ROOT_DIR_.'/Core/Foundation/Database/Core_Foundation_Database_EntityInterface.php');
                    }

                    if (file_exists(_PS_ROOT_DIR_.'/classes/Tools.php')) {
                        require_once(_PS_ROOT_DIR_.'/classes/Tools.php');
                    }
                    if (!class_exists('Tools2', false) and class_exists('ToolsCore')) {
                        eval('class Tools2 extends ToolsCore{}');
                    }

                    if (class_exists('Tools2') && method_exists('Tools2', 'generateHtaccess')) {
                        $url_rewrite = (bool) Db::getInstance()->getvalue('SELECT `value` FROM `'._DB_PREFIX_.'configuration` WHERE name=\'PS_REWRITING_SETTINGS\'');

                        if (!defined('_MEDIA_SERVER_1_')) {
                            define('_MEDIA_SERVER_1_', '');
                        }
                        if (!defined('_MEDIA_SERVER_2_')) {
                            define('_MEDIA_SERVER_2_', '');
                        }
                        if (!defined('_MEDIA_SERVER_3_')) {
                            define('_MEDIA_SERVER_3_', '');
                        }
                        if (!defined('_PS_USE_SQL_SLAVE_')) {
                            define('_PS_USE_SQL_SLAVE_', false);
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/ObjectModel.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/ObjectModel.php');
                        }
                        if (!class_exists('ObjectModel', false) and class_exists('ObjectModelCore')) {
                            eval('abstract class ObjectModel extends ObjectModelCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/Configuration.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/Configuration.php');
                        }
                        if (!class_exists('Configuration', false) and class_exists('ConfigurationCore')) {
                            eval('class Configuration extends ConfigurationCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/cache/Cache.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/cache/Cache.php');
                        }
                        if (!class_exists('Cache', false) and class_exists('CacheCore')) {
                            eval('abstract class Cache extends CacheCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/PrestaShopCollection.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/PrestaShopCollection.php');
                        }
                        if (!class_exists('PrestaShopCollection', false) and class_exists('PrestaShopCollectionCore')) {
                            eval('class PrestaShopCollection extends PrestaShopCollectionCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/shop/ShopUrl.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/shop/ShopUrl.php');
                        }
                        if (!class_exists('ShopUrl', false) and class_exists('ShopUrlCore')) {
                            eval('class ShopUrl extends ShopUrlCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/shop/Shop.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/shop/Shop.php');
                        }
                        if (!class_exists('Shop', false) and class_exists('ShopCore')) {
                            eval('class Shop extends ShopCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/Translate.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/Translate.php');
                        }
                        if (!class_exists('Translate', false) and class_exists('TranslateCore')) {
                            eval('class Translate extends TranslateCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/module/Module.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/module/Module.php');
                        }
                        if (!class_exists('Module', false) and class_exists('ModuleCore')) {
                            eval('class Module extends ModuleCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/Validate.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/Validate.php');
                        }
                        if (!class_exists('Validate', false) and class_exists('ValidateCore')) {
                            eval('class Validate extends ValidateCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/Language.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/Language.php');
                        }
                        if (!class_exists('Language', false) and class_exists('LanguageCore')) {
                            eval('class Language extends LanguageCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/Tab.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/Tab.php');
                        }
                        if (!class_exists('Tab', false) and class_exists('TabCore')) {
                            eval('class Tab extends TabCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/Dispatcher.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/Dispatcher.php');
                        }
                        if (!class_exists('Dispatcher', false) and class_exists('DispatcherCore')) {
                            eval('class Dispatcher extends DispatcherCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/Hook.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/Hook.php');
                        }
                        if (!class_exists('Hook', false) and class_exists('HookCore')) {
                            eval('class Hook extends HookCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/Context.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/Context.php');
                        }
                        if (!class_exists('Context', false) and class_exists('ContextCore')) {
                            eval('class Context extends ContextCore{}');
                        }

                        if (file_exists(_PS_ROOT_DIR_.'/classes/Group.php')) {
                            require_once(_PS_ROOT_DIR_.'/classes/Group.php');
                        }
                        if (!class_exists('Group', false) and class_exists('GroupCore')) {
                            eval('class Group extends GroupCore{}');
                        }

                        Tools2::generateHtaccess(null, $url_rewrite);
                    }
                }

                if (version_compare($this->install_version, '1.6.0.2', '>')) {
                    $path = $this->adminDir.DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.'default'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'header.tpl';
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }

            if (file_exists(_PS_ROOT_DIR_.'/cache/class_index.php')) {
                unlink(_PS_ROOT_DIR_.'/cache/class_index.php');
            }

            // Clear XML files
            if (file_exists(_PS_ROOT_DIR_.'/config/xml/blog-fr.xml')) {
                unlink(_PS_ROOT_DIR_.'/config/xml/blog-fr.xml');
            }
            if (file_exists(_PS_ROOT_DIR_.'/config/xml/default_country_modules_list.xml')) {
                unlink(_PS_ROOT_DIR_.'/config/xml/default_country_modules_list.xml');
            }
            if (file_exists(_PS_ROOT_DIR_.'/config/xml/modules_list.xml')) {
                unlink(_PS_ROOT_DIR_.'/config/xml/modules_list.xml');
            }
            if (file_exists(_PS_ROOT_DIR_.'/config/xml/modules_native_addons.xml')) {
                unlink(_PS_ROOT_DIR_.'/config/xml/modules_native_addons.xml');
            }
            if (file_exists(_PS_ROOT_DIR_.'/config/xml/must_have_modules_list.xml')) {
                unlink(_PS_ROOT_DIR_.'/config/xml/must_have_modules_list.xml');
            }
            if (file_exists(_PS_ROOT_DIR_.'/config/xml/tab_modules_list.xml')) {
                unlink(_PS_ROOT_DIR_.'/config/xml/tab_modules_list.xml');
            }
            if (file_exists(_PS_ROOT_DIR_.'/config/xml/trusted_modules_list.xml')) {
                unlink(_PS_ROOT_DIR_.'/config/xml/trusted_modules_list.xml');
            }
            if (file_exists(_PS_ROOT_DIR_.'/config/xml/untrusted_modules_list.xml')) {
                unlink(_PS_ROOT_DIR_.'/config/xml/untrusted_modules_list.xml');
            }

            if ($this->deactivateCustomModule) {
                $exist = Db::getInstance()->getValue('SELECT `id_configuration` FROM `'._DB_PREFIX_.'configuration` WHERE `name` LIKE \'PS_DISABLE_OVERRIDES\'');
                if ($exist) {
                    Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value = 1 WHERE `name` LIKE \'PS_DISABLE_OVERRIDES\'');
                } else {
                    Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'configuration` (name, value, date_add, date_upd) VALUES ("PS_DISABLE_OVERRIDES", 1, NOW(), NOW())');
                }

                if (file_exists(_PS_ROOT_DIR_.'/classes/PrestaShopAutoload.php')) {
                    require_once(_PS_ROOT_DIR_.'/classes/PrestaShopAutoload.php');
                }

                if (version_compare($this->install_version, '1.6.0.0', '>') && class_exists('PrestaShopAutoload') && method_exists('PrestaShopAutoload', 'generateIndex')) {
                    PrestaShopAutoload::getInstance()->_include_override_path = false;
                    PrestaShopAutoload::getInstance()->generateIndex();
                }
            }

            if ($this->changeToDefaultTheme) {
                if (version_compare(INSTALL_VERSION, '1.6.0.0', '>')) {
                    Db::getInstance()->execute(
                        'UPDATE `'._DB_PREFIX_.'shop`
					SET id_theme = (SELECT id_theme FROM `'._DB_PREFIX_.'theme` WHERE name LIKE \'default-bootstrap\')'
                    );
                    Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'theme` WHERE  name LIKE \'default\' OR name LIKE \'prestashop\'');
                } elseif (version_compare(INSTALL_VERSION, '1.5.0.0', '>')) {
                    Db::getInstance()->execute(
                        'UPDATE `'._DB_PREFIX_.'shop`
					SET id_theme = (SELECT id_theme FROM `'._DB_PREFIX_.'theme` WHERE name LIKE \'default\')'
                    );
                    Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'theme` WHERE  name LIKE \'prestashop\'');
                }
            }

            // delete cache filesystem if activated
            if (defined('_PS_CACHE_ENABLED_') && _PS_CACHE_ENABLED_) {
                $depth = (int) $this->db->getValue(
                    'SELECT value
				FROM '._DB_PREFIX_.'configuration
				WHERE name = "PS_CACHEFS_DIRECTORY_DEPTH"'
                );
                if ($depth) {
                    if (!defined('_PS_CACHEFS_DIRECTORY_')) {
                        define('_PS_CACHEFS_DIRECTORY_', $this->prodRootDir.'/cache/cachefs/');
                    }
                    self::deleteDirectory(_PS_CACHEFS_DIRECTORY_, false);
                    if (class_exists('CacheFs', false)) {
                        self::createCacheFsDirectories((int) $depth);
                    }
                }
            }

            $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="0" WHERE name = "PS_HIDE_OPTIMIZATION_TIS"', false);
            $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="1" WHERE name = "PS_NEED_REBUILD_INDEX"', false);
            $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="'.INSTALL_VERSION.'" WHERE name = "PS_VERSION_DB"', false);
        }

        if ($this->next == 'error') {
            return false;
        } elseif (!empty($warningExist) || $this->warning_exists) {
            $this->nextQuickInfo[] = $this->l('Warning detected during upgrade.');
            $this->nextErrors[] = $this->l('Warning detected during upgrade.');
            $this->next_desc = $this->l('Warning detected during upgrade.');
        } else {
            $this->next_desc = $this->l('Database upgrade completed');
        }

        return true;
    }

    /**
     * @return bool
     */
    public function doUpgradeSetupConst()
    {
        // Initialize
        // setting the memory limit to 128M only if current is lower
        $memory_limit = ini_get('memory_limit');
        if ((substr($memory_limit, -1) != 'G')
            && ((substr($memory_limit, -1) == 'M' and substr($memory_limit, 0, -1) < 128)
                || is_numeric($memory_limit) and (intval($memory_limit) < 131072))
        ) {
            @ini_set('memory_limit', '128M');
        }

        /* Redefine REQUEST_URI if empty (on some webservers...) */
        if (!isset($_SERVER['REQUEST_URI']) || empty($_SERVER['REQUEST_URI'])) {
            if (!isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['SCRIPT_FILENAME'])) {
                $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_FILENAME'];
            }
            if (isset($_SERVER['SCRIPT_NAME'])) {
                if (basename($_SERVER['SCRIPT_NAME']) == 'index.php' && empty($_SERVER['QUERY_STRING'])) {
                    $_SERVER['REQUEST_URI'] = dirname($_SERVER['SCRIPT_NAME']).'/';
                } else {
                    $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
                    if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                        $_SERVER['REQUEST_URI'] .= '?'.$_SERVER['QUERY_STRING'];
                    }
                }
            }
        }
        $_SERVER['REQUEST_URI'] = str_replace('//', '/', $_SERVER['REQUEST_URI']);

        define('INSTALL_VERSION', $this->install_version);
        // 1.4
        define('INSTALL_PATH', _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.$this->install_autoupgrade_dir);
        // 1.5 ...
        define('_PS_INSTALL_PATH_', INSTALL_PATH.DIRECTORY_SEPARATOR);
        // 1.6
        if (!defined('_PS_CORE_DIR_')) {
            define('_PS_CORE_DIR_', _PS_ROOT_DIR_);
        }

        define('PS_INSTALLATION_IN_PROGRESS', true);
        define('SETTINGS_FILE', $this->prodRootDir.'/config/settings.inc.php');
        define('DEFINES_FILE', $this->prodRootDir.'/config/defines.inc.php');
        define('INSTALLER__PS_BASE_URI', substr($_SERVER['REQUEST_URI'], 0, -1 * (strlen($_SERVER['REQUEST_URI']) - strrpos($_SERVER['REQUEST_URI'], '/')) - strlen(substr(dirname($_SERVER['REQUEST_URI']), strrpos(dirname($_SERVER['REQUEST_URI']), '/') + 1))));
        //	define('INSTALLER__PS_BASE_URI_ABSOLUTE', 'http://'.ToolsInstall::getHttpHost(false, true).INSTALLER__PS_BASE_URI);

        // XML Header
        // header('Content-Type: text/xml');

        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set('Europe/Paris');
        }

        // if _PS_ROOT_DIR_ is defined, use it instead of "guessing" the module dir.
        if (defined('_PS_ROOT_DIR_') and !defined('_PS_MODULE_DIR_')) {
            define('_PS_MODULE_DIR_', _PS_ROOT_DIR_.'/modules/');
        } elseif (!defined('_PS_MODULE_DIR_')) {
            define('_PS_MODULE_DIR_', INSTALL_PATH.'/../modules/');
        }

        $upgrade_dir_php = 'upgrade/php';
        if (!file_exists(INSTALL_PATH.DIRECTORY_SEPARATOR.$upgrade_dir_php)) {
            $upgrade_dir_php = 'php';
            if (!file_exists(INSTALL_PATH.DIRECTORY_SEPARATOR.$upgrade_dir_php)) {
                $this->next = 'error';
                $this->next_desc = INSTALL_PATH.$this->l(' directory is missing in archive or directory');
                $this->nextQuickInfo[] = INSTALL_PATH.' directory is missing in archive or directory';
                $this->nextErrors[] = INSTALL_PATH.' directory is missing in archive or directory.';

                return false;
            }
        }
        define('_PS_INSTALLER_PHP_UPGRADE_DIR_', INSTALL_PATH.DIRECTORY_SEPARATOR.$upgrade_dir_php.DIRECTORY_SEPARATOR);

        return true;
    }

    /**
     * Implement the upgrade based on upgrade.php from the downloaded archive
     *
     * @return bool
     *
     */
    public function doUpgrade17()
    {
        $base_uri = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath(_PS_ROOT_DIR_));
        $hostName = $this->getServerFullBaseUrl();

        $url = $hostName.str_replace('\\', '/', $base_uri).
            '/'.$this->install_autoupgrade_dir.'/upgrade/upgrade.php?autoupgrade=1'.
            '&deactivateCustomModule=1'.
            '&updateDefaultTheme=1'.
            '&keepMails=0'.
            '&changeToDefaultTheme=1'.
            '&adminDir='.base64_encode($this->adminDir).
            '&idEmployee='.(int) $_COOKIE['id_employee'];

        $json = Tools::file_get_contents($url, false, null, $curl_timeout = 3600);
        $result = json_decode($json, true);

        if ($result) {
            $this->nextQuickInfo = $result['nextQuickInfo'];
            $this->nextErrors = $result['nextErrors'];
            $this->next_desc = $result['nextDesc'];
            $this->warning_exists = $result['warningExists'];
            if (!empty($result['next'])) {
                $this->next = $result['next'];
            } else {
                if ($this->getConfig('channel') != 'archive' && file_exists($this->getFilePath()) && unlink($this->getFilePath())) {
                    $this->nextQuickInfo[] = '<div class="upgradeDbOk">'.sprintf($this->l('%s removed'), $this->getFilePath()).'</div>';
                } elseif (is_file($this->getFilePath())) {
                    $this->nextQuickInfo[] = '<div class="upgradeDbOk"><strong>'.sprintf($this->l('Please remove %s by FTP'), $this->getFilePath()).'</strong></div>';
                }

                if ($this->getConfig('channel') != 'directory' && file_exists($this->latestRootDir) && self::deleteDirectory($this->latestRootDir)) {
                    $this->nextQuickInfo[] = '<div class="upgradeDbOk">'.sprintf($this->l('%s removed'), $this->latestRootDir).'</div>';
                } elseif (is_dir($this->latestRootDir)) {
                    $this->nextQuickInfo[] = '<div class="upgradeDbOk"><strong>'.sprintf($this->l('Please remove %s by FTP'), $this->latestRootDir).'</strong></div>';
                }

                if (!$this->warning_exists) {
                    $this->nextQuickInfo[] = '<br /><div class="upgradeDbOk"><strong>'.$this->l('Upgrade process done. Congratulations! You can now reactivate your shop.').'</strong></div>';
                } else {
                    $this->nextQuickInfo[] = '<br /><div class="upgradeDbOk"><strong>'.$this->l('Upgrade process done, but some warnings have been found.').'</strong></div>';
                }

                $this->nextQuickInfo[] = '<div class="upgradeDbOk">'.$this->l('Don\'t forget to clear your cache (Advanced parameters > Performance > Clear cache)').'</div>';
            }
        }

        self::deleteDirectory(_PS_ROOT_DIR_.'/'.$this->install_autoupgrade_dir);

        return true;
    }

    public function getServerFullBaseUrl()
    {
        $s = $_SERVER;
        $ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on');
        $sp = strtolower($s['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/')).(($ssl) ? 's' : '');
        $port = $s['SERVER_PORT'];
        $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':'.$port;
        $host = isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null;
        $host = isset($host) ? $host : $s['SERVER_NAME'].$port;

        return $protocol.'://'.$host;
    }

    public function writeNewSettings()
    {
        // note : duplicated line
        $mysqlEngine = (defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'MyISAM');

        $oldLevel = error_reporting(E_ALL);
        //refresh conf file
        require_once(_PS_ROOT_DIR_.'/modules/autoupgrade/classes/AddConfToFile.php');
        copy(SETTINGS_FILE, str_replace('.php', '.old.php', SETTINGS_FILE));
        $confFile = new AddConfToFile(SETTINGS_FILE, 'w');
        if ($confFile->error) {
            $this->next = 'error';
            $this->next_desc = $this->l('Error when opening settings.inc.php file in write mode');
            $this->nextQuickInfo[] = $confFile->error;
            $this->nextErrors[] = $this->l('Error when opening settings.inc.php file in write mode').': '.$confFile->error;

            return false;
        }

        $caches = ['CacheMemcache', 'CacheApc', 'CacheFs', 'CacheMemcached', 'CacheXcache'];

        $datas = [
            ['_DB_SERVER_', _DB_SERVER_],
            ['_DB_NAME_', _DB_NAME_],
            ['_DB_USER_', _DB_USER_],
            ['_DB_PASSWD_', _DB_PASSWD_],
            ['_DB_PREFIX_', _DB_PREFIX_],
            ['_MYSQL_ENGINE_', $mysqlEngine],
            ['_PS_CACHING_SYSTEM_', (defined('_PS_CACHING_SYSTEM_') && in_array(_PS_CACHING_SYSTEM_, $caches)) ? _PS_CACHING_SYSTEM_ : 'CacheMemcache'],
            ['_PS_CACHE_ENABLED_', defined('_PS_CACHE_ENABLED_') ? _PS_CACHE_ENABLED_ : '0'],
            ['_COOKIE_KEY_', _COOKIE_KEY_],
            ['_COOKIE_IV_', _COOKIE_IV_],
            ['_PS_CREATION_DATE_', defined("_PS_CREATION_DATE_") ? _PS_CREATION_DATE_ : date('Y-m-d')],
            ['_PS_VERSION_', INSTALL_VERSION],
        ];

        if (version_compare(INSTALL_VERSION, '1.6.0.11', '<')) {
            $datas[] = ['_MEDIA_SERVER_1_', defined('_MEDIA_SERVER_1_') ? _MEDIA_SERVER_1_ : ''];
            $datas[] = ['_MEDIA_SERVER_2_', defined('_MEDIA_SERVER_2_') ? _MEDIA_SERVER_2_ : ''];
            $datas[] = ['_MEDIA_SERVER_3_', defined('_MEDIA_SERVER_3_') ? _MEDIA_SERVER_3_ : ''];
        }
        if (defined('_RIJNDAEL_KEY_')) {
            $datas[] = ['_RIJNDAEL_KEY_', _RIJNDAEL_KEY_];
        }
        if (defined('_RIJNDAEL_IV_')) {
            $datas[] = ['_RIJNDAEL_IV_', _RIJNDAEL_IV_];
        }
        if (!defined('_PS_CACHE_ENABLED_')) {
            define('_PS_CACHE_ENABLED_', '0');
        }
        if (!defined('_MYSQL_ENGINE_')) {
            define('_MYSQL_ENGINE_', 'MyISAM');
        }

        // if install version is before 1.5
        if (version_compare(INSTALL_VERSION, '1.5.0.0', '<=')) {
            $datas[] = ['_DB_TYPE_', _DB_TYPE_];
            $datas[] = ['__PS_BASE_URI__', __PS_BASE_URI__];
            $datas[] = ['_THEME_NAME_', _THEME_NAME_];
        } else {
            $datas[] = ['_PS_DIRECTORY_', __PS_BASE_URI__];
        }
        foreach ($datas as $data) {
            $confFile->writeInFile($data[0], $data[1]);
        }

        if ($confFile->error != false) {
            $this->next = 'error';
            $this->next_desc = $this->l('Error when generating new settings.inc.php file.');
            $this->nextQuickInfo[] = $confFile->error;
            $this->nextErrors[] = $this->l('Error when generating new settings.inc.php file.').' '.$confFile->error;

            return false;
        } else {
            $this->nextQuickInfo[] = $this->l('Settings file updated');
        }
        error_reporting($oldLevel);
    }

    private function createCacheFsDirectories($level_depth, $directory = false)
    {
        if (!$directory) {
            if (!defined('_PS_CACHEFS_DIRECTORY_')) {
                define('_PS_CACHEFS_DIRECTORY_', $this->prodRootDir.'/cache/cachefs/');
            }
            $directory = _PS_CACHEFS_DIRECTORY_;
        }
        $chars = '0123456789abcdef';
        for ($i = 0; $i < strlen($chars); $i++) {
            $new_dir = $directory.$chars[$i].'/';
            if (mkdir($new_dir, 0775) && chmod($new_dir, 0775) && $level_depth - 1 > 0) {
                self::createCacheFsDirectories($level_depth - 1, $new_dir);
            }
        }
    }

    /**
     * Clean the database from unwanted entires
     *
     * @return void
     */
    public function ajaxProcessCleanDatabase()
    {
        global $warningExists;

        /* Clean tabs order */
        foreach ($this->db->ExecuteS('SELECT DISTINCT id_parent FROM '._DB_PREFIX_.'tab') as $parent) {
            $i = 1;
            foreach ($this->db->ExecuteS('SELECT id_tab FROM '._DB_PREFIX_.'tab WHERE id_parent = '.(int) $parent['id_parent'].' ORDER BY IF(class_name IN ("AdminHome", "AdminDashboard"), 1, 2), position ASC') as $child) {
                $this->db->Execute('UPDATE '._DB_PREFIX_.'tab SET position = '.(int) ($i++).' WHERE id_tab = '.(int) $child['id_tab'].' AND id_parent = '.(int) $parent['id_parent']);
            }
        }

        /* Clean configuration integrity */
        $this->db->Execute('DELETE FROM `'._DB_PREFIX_.'configuration_lang` WHERE (`value` IS NULL AND `date_upd` IS NULL) OR `value` LIKE ""', false);

        $this->status = 'ok';
        $this->next = 'upgradeComplete';
        $this->next_desc = $this->l('The database has been cleaned.');
        $this->nextQuickInfo[] = $this->l('The database has been cleaned.');
    }

    public function ajaxProcessRollback()
    {
        // 1st, need to analyse what was wrong.
        $this->nextParams = $this->currentParams;
        $this->restoreFilesFilename = $this->restoreName;
        if (!empty($this->restoreName)) {
            $files = scandir($this->backupPath);
            // find backup filenames, and be sure they exists
            foreach ($files as $file) {
                if (preg_match('#'.preg_quote('auto-backupfiles_'.$this->restoreName).'#', $file)) {
                    $this->restoreFilesFilename = $file;
                    break;
                }
            }
            if (!is_file($this->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename)) {
                $this->next = 'error';
                $this->nextQuickInfo[] = sprintf('[ERROR] file %s is missing : unable to restore files. Operation aborted.', $this->restoreFilesFilename);
                $this->nextErrors[] = $this->next_desc = sprintf($this->l('[ERROR] File %s is missing: unable to restore files. Operation aborted.'), $this->restoreFilesFilename);

                return false;
            }
            $files = scandir($this->backupPath.DIRECTORY_SEPARATOR.$this->restoreName);
            foreach ($files as $file) {
                if (preg_match('#auto-backupdb_[0-9]{6}_'.preg_quote($this->restoreName).'#', $file)) {
                    $this->restoreDbFilenames[] = $file;
                }
            }

            // order files is important !
            if (is_array($this->restoreDbFilenames)) {
                sort($this->restoreDbFilenames);
            }
            if (count($this->restoreDbFilenames) == 0) {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->l('[ERROR] No backup database files found: it would be impossible to restore the database. Operation aborted.');
                $this->nextErrors[] = $this->next_desc = $this->l('[ERROR] No backup database files found: it would be impossible to restore the database. Operation aborted.');

                return false;
            }

            $this->next = 'restoreFiles';
            $this->next_desc = $this->l('Restoring files ...');
            // remove tmp files related to restoreFiles
            if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList)) {
                unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList);
            }
            if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList)) {
                unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList);
            }
        } else {
            $this->next = 'noRollbackFound';
        }
    }

    public function ajaxProcessNoRollbackFound()
    {
        $this->next_desc = $this->l('Nothing to restore');
        $this->next = 'rollbackComplete';
    }

    /**
     * ajaxProcessRestoreFiles restore the previously saved files,
     * and delete files that weren't archived
     *
     * @return boolean true if succeed
     */
    public function ajaxProcessRestoreFiles()
    {
        // loop
        $this->next = 'restoreFiles';
        if (!file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList)
            || !file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList)
        ) {
            // cleanup current PS tree
            $fromArchive = $this->_listArchivedFiles($this->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename);
            foreach ($fromArchive as $k => $v) {
                $fromArchive[$k] = '/'.$v;
            }
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList, base64_encode(serialize($fromArchive)));
            // get list of files to remove
            $toRemove = $this->_listFilesToRemove();
            // let's reverse the array in order to make possible to rmdir
            // remove fullpath. This will be added later in the loop.
            // we do that for avoiding fullpath to be revealed in a text file
            foreach ($toRemove as $k => $v) {
                $toRemove[$k] = str_replace($this->prodRootDir, '', $v);
            }

            $this->nextQuickInfo[] = sprintf($this->l('%s file(s) will be removed before restoring the backup files.'), count($toRemove));
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList, base64_encode(serialize($toRemove)));

            if ($fromArchive === false || $toRemove === false) {
                if (!$fromArchive) {
                    $this->nextQuickInfo[] = sprintf($this->l('[ERROR] Backup file %s does not exist.'), $this->fromArchiveFileList);
                    $this->nextErrors[] = sprintf($this->l('[ERROR] Backup file %s does not exist.'), $this->fromArchiveFileList);
                }
                if (!$toRemove) {
                    $this->nextQuickInfo[] = sprintf($this->l('[ERROR] File "%s" does not exist.'), $this->toRemoveFileList);
                    $this->nextErrors[] = sprintf($this->l('[ERROR] File "%s" does not exist.'), $this->toRemoveFileList);
                }
                $this->next_desc = $this->l('Unable to remove upgraded files.');
                $this->next = 'error';

                return false;
            }
        }

        // first restoreFiles step
        if (!isset($toRemove)) {
            $toRemove = unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList)));
        }

        if (count($toRemove) > 0) {
            for ($i = 0; $i < self::$loopRestoreFiles; $i++) {
                if (count($toRemove) <= 0) {
                    $this->stepDone = true;
                    $this->status = 'ok';
                    $this->next = 'restoreFiles';
                    $this->next_desc = $this->l('Files from upgrade has been removed.');
                    $this->nextQuickInfo[] = $this->l('Files from upgrade has been removed.');
                    file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList, base64_encode(serialize($toRemove)));

                    return true;
                } else {
                    $filename = array_shift($toRemove);
                    $file = rtrim($this->prodRootDir, DIRECTORY_SEPARATOR).$filename;
                    if (file_exists($file)) {
                        if (is_file($file)) {
                            @chmod($file, 0777); // NT ?
                            if (@unlink($file)) {
                                $this->nextQuickInfo[] = sprintf($this->l('%s files removed'), $filename);
                            } else {
                                $this->next = 'error';
                                $this->next_desc = sprintf($this->l('Error when removing %1$s.'), $filename);
                                $this->nextQuickInfo[] = sprintf($this->l('File %s not removed.'), $filename);
                                $this->nextErrors[] = sprintf($this->l('File %s not removed.'), $filename);

                                return false;
                            }
                        } elseif (is_dir($file)) {
                            if ($this->isDirEmpty($file)) {
                                self::deleteDirectory($file, true);
                                $this->nextQuickInfo[] = sprintf($this->l('[NOTICE]  %s deleted.'), $filename);
                            } else {
                                $this->nextQuickInfo[] = sprintf($this->l('[NOTICE] Directory %s skipped (directory not empty).'), $filename);
                            }
                        }
                    } else {
                        $this->nextQuickInfo[] = sprintf($this->l('[NOTICE] %s does not exist'), $filename);
                    }
                }
            }
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList, base64_encode(serialize($toRemove)));
            if (count($toRemove)) {
                $this->next_desc = sprintf($this->l('%s file(s) left to remove.'), count($toRemove));
            }
            $this->next = 'restoreFiles';

            return true;
        }

        // very second restoreFiles step : extract backup
        // if (!isset($fromArchive))
        //	$fromArchive = unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList)));
        $filepath = $this->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename;
        $destExtract = $this->prodRootDir;
        if ($this->ZipExtract($filepath, $destExtract)) {
            $this->next = 'restoreDb';
            $this->next_desc = $this->l('Files restored. Now restoring database...');
            // get new file list
            $this->nextQuickInfo[] = $this->l('Files restored.');
            // once it's restored, do not delete the archive file. This has to be done manually
            // and we do not empty the var, to avoid infinite loop.
            return true;
        } else {
            $this->next = 'error';
            $this->next_desc = sprintf($this->l('Unable to extract file %1$s into directory %2$s .'), $filepath, $destExtract);

            return false;
        }

        return true;
    }

    private function _listArchivedFiles($zipfile)
    {
        if (file_exists($zipfile)) {
            $res = false;
            if (!self::$forcePclZip && class_exists('ZipArchive', false)) {
                $this->nextQuickInfo[] = $this->l('Using class ZipArchive...');
                $files = [];
                $zip = new \ZipArchive();
                $res = $zip->open($zipfile);
                if ($res) {
                    $res = (isset($zip->filename) && $zip->filename) ? true : false;
                }
                if ($zip && $res === true) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $files[] = $zip->getNameIndex($i);
                    }

                    return $files;
                } elseif ($res) {
                    $this->nextQuickInfo[] = $this->l('[ERROR] Unable to list archived files');

                    return false;
                }
            }
            if (!$res) {
                $this->nextQuickInfo[] = $this->l('Using class PclZip...');
                if ($zip = new \PclZip($zipfile)) {
                    return $zip->listContent();
                }
            }
        }

        return false;
    }

    /**
     * this function list all files that will be remove to retrieve the filesystem states before the upgrade
     *
     * @access public
     * @return void
     */
    public function _listFilesToRemove()
    {
        $prev_version = preg_match('#auto-backupfiles_V([0-9.]*)_#', $this->restoreFilesFilename, $matches);
        if ($prev_version) {
            $prev_version = $matches[1];
        }

        if (!$this->upgrader) {
            $this->upgrader = new Upgrader();
        }

        $toRemove = false;
        // note : getDiffFilesList does not include files moved by upgrade scripts,
        // so this method can't be trusted to fully restore directory
        // $toRemove = $this->upgrader->getDiffFilesList(_PS_VERSION_, $prev_version, false);
        // if we can't find the diff file list corresponding to _PS_VERSION_ and prev_version,
        // let's assume to remove every files
        if (!$toRemove) {
            $toRemove = $this->_listFilesInDir($this->prodRootDir, 'restore', true);
        }

        $admin_dir = str_replace($this->prodRootDir, '', $this->adminDir);
        // if a file in "ToRemove" has been skipped during backup,
        // just keep it
        foreach ($toRemove as $key => $file) {
            $filename = substr($file, strrpos($file, '/') + 1);
            $toRemove[$key] = preg_replace('#^/admin#', $admin_dir, $file);
            // this is a really sensitive part, so we add an extra checks: preserve everything that contains "autoupgrade"
            if ($this->_skipFile($filename, $file, 'backup') || strpos($file, $this->autoupgradeDir)) {
                unset($toRemove[$key]);
            }
        }

        return $toRemove;
    }

    public function _listFilesInDir($dir, $way = 'backup', $list_directories = false)
    {
        $list = [];
        $dir = rtrim($dir, '/').DIRECTORY_SEPARATOR;
        $allFiles = false;
        if (is_dir($dir) && is_readable($dir)) {
            $allFiles = scandir($dir);
        }
        if (is_array($allFiles)) {
            foreach ($allFiles as $file) {
                if ($file[0] != '.') {
                    $fullPath = $dir.$file;
                    if (!$this->_skipFile($file, $fullPath, $way)) {
                        if (is_dir($fullPath)) {
                            $list = array_merge($list, $this->_listFilesInDir($fullPath, $way, $list_directories));
                            if ($list_directories) {
                                $list[] = $fullPath;
                            }
                        } else {
                            $list[] = $fullPath;
                        }
                    }
                }
            }
        }

        return $list;
    }

    public function isDirEmpty($dir, $ignore = ['.svn', '.git'])
    {
        $array_ignore = array_merge(['.', '..'], $ignore);
        $content = scandir($dir);
        foreach ($content as $filename) {
            if (!in_array($filename, $array_ignore)) {
                return false;
            }
        }

        return true;
    }

    /**
     * try to restore db backup file
     */
    public function ajaxProcessRestoreDb()
    {
        $skip_ignore_tables = false;
        $ignore_stats_table = [
            _DB_PREFIX_.'connections',
            _DB_PREFIX_.'connections_page',
            _DB_PREFIX_.'connections_source',
            _DB_PREFIX_.'guest',
            _DB_PREFIX_.'statssearch',
        ];
        $this->nextParams['dbStep'] = $this->currentParams['dbStep'];
        $start_time = time();
        $db = $this->db;
        $listQuery = [];
        $errors = [];

        // deal with running backup rest if exist
        if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList)) {
            $listQuery = unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList)));
        }

        // deal with the next files stored in restoreDbFilenames
        if (empty($listQuery) && is_array($this->restoreDbFilenames) && count($this->restoreDbFilenames) > 0) {
            $currentDbFilename = array_shift($this->restoreDbFilenames);
            if (!preg_match('#auto-backupdb_([0-9]{6})_#', $currentDbFilename, $match)) {
                $this->next = 'error';
                $this->error = 1;
                $this->nextQuickInfo[] = $this->next_desc = $this->l(sprintf('%s: File format does not match.', $currentDbFilename));

                return false;
            }
            $this->nextParams['dbStep'] = $match[1];
            $backupdb_path = $this->backupPath.DIRECTORY_SEPARATOR.$this->restoreName;

            $dot_pos = strrpos($currentDbFilename, '.');
            $fileext = substr($currentDbFilename, $dot_pos + 1);
            $requests = [];
            $content = '';

            $this->nextQuickInfo[] = $this->l(sprintf('Opening backup database file %1s in %2s mode', $currentDbFilename, $fileext));

            switch ($fileext) {
                case 'bz':
                case 'bz2':
                    if ($fp = bzopen($backupdb_path.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= bzread($fp, 4096);
                        }
                    } else {
                        die("error when trying to open in bzmode");
                    } // @todo : handle error
                    break;
                case 'gz':
                    if ($fp = gzopen($backupdb_path.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= gzread($fp, 4096);
                        }
                    }
                    gzclose($fp);
                    break;
                default:
                    if ($fp = fopen($backupdb_path.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= fread($fp, 4096);
                        }
                    }
                    fclose($fp);
            }
            $currentDbFilename = '';

            if (empty($content)) {
                $this->nextErrors[] = $this->l('Database backup is empty.');
                $this->nextQuickInfo[] = $this->l('Database backup is empty.');
                $this->next = 'rollback';

                return false;
            }

            // preg_match_all is better than preg_split (what is used in do Upgrade.php)
            // This way we avoid extra blank lines
            // option s (PCRE_DOTALL) added
            $listQuery = preg_split('/;[\n\r]+/Usm', $content);
            unset($content);

            // @TODO : drop all old tables (created in upgrade)
            // This part has to be executed only onces (if dbStep=0)
            if ($this->nextParams['dbStep'] == '1') {
                $all_tables = $this->db->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'%"', true, false);
                $drops = [];
                foreach ($all_tables as $k => $v) {
                    $table = array_shift($v);
                    $drops['drop table '.$k] = 'DROP TABLE IF EXISTS `'.bqSql($table).'`';
                    $drops['drop view '.$k] = 'DROP VIEW IF EXISTS `'.bqSql($table).'`';
                }
                unset($all_tables);
                $listQuery = array_merge($drops, $listQuery);
            }
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList, base64_encode(serialize($listQuery)));
        }
        // @todo : error if listQuery is not an array (that can happen if toRestoreQueryList is empty for example)
        $time_elapsed = time() - $start_time;
        if (is_array($listQuery) && (count($listQuery) > 0)) {
            do {
                if (count($listQuery) == 0) {
                    if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList)) {
                        unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList);
                    }

                    if (count($this->restoreDbFilenames)) {
                        $this->next_desc = sprintf($this->l('Database restoration file %1$s done. %2$s file(s) left...'), $this->nextParams['dbStep'], count($this->restoreDbFilenames));
                    } else {
                        $this->next_desc = sprintf($this->l('Database restoration file %1$s done.'), $this->nextParams['dbStep']);
                    }

                    $this->nextQuickInfo[] = $this->next_desc;
                    $this->stepDone = true;
                    $this->status = 'ok';
                    $this->next = 'restoreDb';
                    if (count($this->restoreDbFilenames) == 0) {
                        $this->next = 'rollbackComplete';
                        $this->nextQuickInfo[] = $this->next_desc = $this->l('Database has been restored.');
                    }

                    return true;
                }
                // filesForBackup already contains all the correct files
                if (count($listQuery) == 0) {
                    continue;
                }

                $query = array_shift($listQuery);
                if (!empty($query)) {
                    if (!$this->db->execute($query, false)) {
                        if (is_array($listQuery)) {
                            $listQuery = array_unshift($listQuery, $query);
                        }
                        $this->nextErrors[] = $this->l('[SQL ERROR] ').$query.' - '.$this->db->getMsgError();
                        $this->nextQuickInfo[] = $this->l('[SQL ERROR] ').$query.' - '.$this->db->getMsgError();
                        $this->next = 'error';
                        $this->error = 1;
                        $this->next_desc = $this->l('Error during database restoration');
                        unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList);

                        return false;
                    }
                }
                // note : theses queries can be too big and can cause issues for display
                // else
                // $this->nextQuickInfo[] = '[OK] '.$query;

                $time_elapsed = time() - $start_time;
            } while ($time_elapsed < self::$loopRestoreQueryTime);

            $queries_left = count($listQuery);

            if ($queries_left > 0) {
                file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList, base64_encode(serialize($listQuery)));
            } elseif (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList)) {
                unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList);
            }

            $this->stepDone = false;
            $this->next = 'restoreDb';
            $this->nextQuickInfo[] = $this->next_desc = sprintf($this->l('%1$s queries left for file %2$s...'), $queries_left, $this->nextParams['dbStep']);
            unset($query);
            unset($listQuery);
        } else {
            $this->stepDone = true;
            $this->status = 'ok';
            $this->next = 'rollbackComplete';
            $this->nextQuickInfo[] = $this->next_desc = $this->l('Database restoration done.');
        }

        return true;
    }

    public function ajaxProcessMergeTranslations()
    {
    }

    public function ajaxProcessBackupDb()
    {
        if (!$this->getConfig('PS_AUTOUP_BACKUP') && version_compare($this->upgrader->version_num, '1.7.0.0', '<')) {
            $this->stepDone = true;
            $this->nextParams['dbStep'] = 0;
            $this->next_desc = sprintf($this->l('Database backup skipped. Now upgrading files...'), $this->backupName);
            $this->next = 'upgradeFiles';

            return true;
        }

        $relative_backup_path = str_replace(_PS_ROOT_DIR_, '', $this->backupPath);
        $report = '';
        if (!ConfigurationTest::test_dir($relative_backup_path, false, $report)) {
            $this->next_desc = $this->l('Backup directory is not writable ');
            $this->nextQuickInfo[] = 'Backup directory is not writable ';
            $this->nextErrors[] = 'Backup directory is not writable "'.$this->backupPath.'"';
            $this->next = 'error';
            $this->error = 1;

            return false;
        }

        $this->stepDone = false;
        $this->next = 'backupDb';
        $this->nextParams = $this->currentParams;
        $start_time = time();

        $psBackupAll = true;
        $psBackupDropTable = true;
        if (!$psBackupAll) {
            $ignore_stats_table = [
                _DB_PREFIX_.'connections',
                _DB_PREFIX_.'connections_page',
                _DB_PREFIX_.'connections_source',
                _DB_PREFIX_.'guest',
                _DB_PREFIX_.'statssearch',
            ];
        } else {
            $ignore_stats_table = [];
        }

        // INIT LOOP
        if (!file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupDbList)) {
            if (!is_dir($this->backupPath.DIRECTORY_SEPARATOR.$this->backupName)) {
                mkdir($this->backupPath.DIRECTORY_SEPARATOR.$this->backupName);
            }
            $this->nextParams['dbStep'] = 0;
            $tablesToBackup = $this->db->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'%"', true, false);
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupDbList, base64_encode(serialize($tablesToBackup)));
        }

        if (!isset($tablesToBackup)) {
            $tablesToBackup = unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupDbList)));
        }
        $found = 0;
        $views = '';

        // MAIN BACKUP LOOP //
        $written = 0;
        do {
            if (!empty($this->nextParams['backup_table'])) {
                // only insert (schema already done)
                $table = $this->nextParams['backup_table'];
                $lines = $this->nextParams['backup_lines'];
            } else {
                if (count($tablesToBackup) == 0) {
                    break;
                }
                $table = current(array_shift($tablesToBackup));
                $this->nextParams['backup_loop_limit'] = 0;
            }

            if ($written == 0 || $written > self::$max_written_allowed) {
                // increment dbStep will increment filename each time here
                $this->nextParams['dbStep']++;
                // new file, new step
                $written = 0;
                if (isset($fp)) {
                    fclose($fp);
                }
                $backupfile = $this->backupPath.DIRECTORY_SEPARATOR.$this->backupName.DIRECTORY_SEPARATOR.$this->backupDbFilename;
                $backupfile = preg_replace("#_XXXXXX_#", '_'.str_pad($this->nextParams['dbStep'], 6, '0', STR_PAD_LEFT).'_', $backupfile);

                // start init file
                // Figure out what compression is available and open the file
                if (file_exists($backupfile)) {
                    $this->next = 'error';
                    $this->error = 1;
                    $this->nextErrors[] = sprintf($this->l('Backup file %s already exists. Operation aborted.'), $backupfile);
                    $this->nextQuickInfo[] = sprintf($this->l('Backup file %s already exists. Operation aborted.'), $backupfile);
                }

                if (function_exists('bzopen')) {
                    $backupfile .= '.bz2';
                    $fp = bzopen($backupfile, 'w');
                } elseif (function_exists('gzopen')) {
                    $backupfile .= '.gz';
                    $fp = gzopen($backupfile, 'w');
                } else {
                    $fp = fopen($backupfile, 'w');
                }

                if ($fp === false) {
                    $this->nextErrors[] = sprintf($this->l('Unable to create backup database file %s.'), addslashes($backupfile));
                    $this->nextQuickInfo[] = sprintf($this->l('Unable to create backup database file %s.'), addslashes($backupfile));
                    $this->next = 'error';
                    $this->error = 1;
                    $this->next_desc = $this->l('Error during database backup.');

                    return false;
                }

                $written += fwrite($fp, '/* Backup '.$this->nextParams['dbStep'].' for '.Tools::getHttpHost(false, false).__PS_BASE_URI__."\n *  at ".date('r')."\n */\n");
                $written += fwrite($fp, "\n".'SET NAMES \'utf8\';'."\n\n");
                // end init file
            }

            // Skip tables which do not start with _DB_PREFIX_
            if (strlen($table) <= strlen(_DB_PREFIX_) || strncmp($table, _DB_PREFIX_, strlen(_DB_PREFIX_)) != 0) {
                continue;
            }

            // start schema : drop & create table only
            if (empty($this->currentParams['backup_table'])) {
                // Export the table schema
                $schema = $this->db->executeS('SHOW CREATE TABLE `'.$table.'`', true, false);

                if (count($schema) != 1 ||
                    !((isset($schema[0]['Table']) && isset($schema[0]['Create Table']))
                        || (isset($schema[0]['View']) && isset($schema[0]['Create View'])))
                ) {
                    fclose($fp);
                    if (file_exists($backupfile)) {
                        unlink($backupfile);
                    }
                    $this->nextErrors[] = sprintf($this->l('An error occurred while backing up. Unable to obtain the schema of %s'), $table);
                    $this->nextQuickInfo[] = sprintf($this->l('An error occurred while backing up. Unable to obtain the schema of %s'), $table);
                    $this->next = 'error';
                    $this->error = 1;
                    $this->next_desc = $this->l('Error during database backup.');

                    return false;
                }

                // case view
                if (isset($schema[0]['View'])) {
                    $views .= '/* Scheme for view'.$schema[0]['View']." */\n";
                    if ($psBackupDropTable) {
                        // If some *upgrade* transform a table in a view, drop both just in case
                        $views .= 'DROP VIEW IF EXISTS `'.$schema[0]['View'].'`;'."\n";
                        $views .= 'DROP TABLE IF EXISTS `'.$schema[0]['View'].'`;'."\n";
                    }
                    $views .= preg_replace('#DEFINER=[^\s]+\s#', 'DEFINER=CURRENT_USER ', $schema[0]['Create View']).";\n\n";
                    $written += fwrite($fp, "\n".$views);
                    $ignore_stats_table[] = $schema[0]['View'];
                } // case table
                elseif (isset($schema[0]['Table'])) {
                    // Case common table
                    $written += fwrite($fp, '/* Scheme for table '.$schema[0]['Table']." */\n");
                    if ($psBackupDropTable && !in_array($schema[0]['Table'], $ignore_stats_table)) {
                        // If some *upgrade* transform a table in a view, drop both just in case
                        $written += fwrite($fp, 'DROP VIEW IF EXISTS `'.$schema[0]['Table'].'`;'."\n");
                        $written += fwrite($fp, 'DROP TABLE IF EXISTS `'.$schema[0]['Table'].'`;'."\n");
                        // CREATE TABLE
                        $written += fwrite($fp, $schema[0]['Create Table'].";\n\n");
                    }
                    // schema created, now we need to create the missing vars
                    $this->nextParams['backup_table'] = $table;
                    $lines = $this->nextParams['backup_lines'] = explode("\n", $schema[0]['Create Table']);
                }
            }
            // end of schema

            // POPULATE TABLE
            if (!in_array($table, $ignore_stats_table)) {
                do {
                    $backup_loop_limit = $this->nextParams['backup_loop_limit'];
                    $data = $this->db->executeS('SELECT * FROM `'.$table.'` LIMIT '.(int) $backup_loop_limit.',200', false, false);
                    $this->nextParams['backup_loop_limit'] += 200;
                    $sizeof = $this->db->numRows();
                    if ($data && ($sizeof > 0)) {
                        // Export the table data
                        $written += fwrite($fp, 'INSERT INTO `'.$table."` VALUES\n");
                        $i = 1;
                        while ($row = $this->db->nextRow($data)) {
                            // this starts a row
                            $s = '(';
                            foreach ($row as $field => $value) {
                                $tmp = "'".$this->db->escape($value, true)."',";
                                if ($tmp != "'',") {
                                    $s .= $tmp;
                                } else {
                                    foreach ($lines as $line) {
                                        if (strpos($line, '`'.$field.'`') !== false) {
                                            if (preg_match('/(.*NOT NULL.*)/Ui', $line)) {
                                                $s .= "'',";
                                            } else {
                                                $s .= 'NULL,';
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                            $s = rtrim($s, ',');

                            if ($i < $sizeof) {
                                $s .= "),\n";
                            } else {
                                $s .= ");\n";
                            }

                            $written += fwrite($fp, $s);
                            ++$i;
                        }
                        $time_elapsed = time() - $start_time;
                    } else {
                        unset($this->nextParams['backup_table']);
                        unset($this->currentParams['backup_table']);
                        break;
                    }
                } while (($time_elapsed < self::$loopBackupDbTime) && ($written < self::$max_written_allowed));
            }
            $found++;
            $time_elapsed = time() - $start_time;
            $this->nextQuickInfo[] = sprintf($this->l('%1$s table has been saved.'), $table);
        } while (($time_elapsed < self::$loopBackupDbTime) && ($written < self::$max_written_allowed));

        // end of loop
        if (isset($fp)) {
            fclose($fp);
            unset($fp);
        }

        file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupDbList, base64_encode(serialize($tablesToBackup)));

        if (count($tablesToBackup) > 0) {
            $this->nextQuickInfo[] = sprintf($this->l('%1$s tables have been saved.'), $found);
            $this->next = 'backupDb';
            $this->stepDone = false;
            if (count($tablesToBackup)) {
                $this->next_desc = sprintf($this->l('Database backup: %s table(s) left...'), count($tablesToBackup));
                $this->nextQuickInfo[] = sprintf($this->l('Database backup: %s table(s) left...'), count($tablesToBackup));
            }

            return true;
        }
        if ($found == 0 && !empty($backupfile)) {
            if (file_exists($backupfile)) {
                unlink($backupfile);
            }
            $this->nextErrors[] = sprintf($this->l('No valid tables were found to back up. Backup of file %s canceled.'), $backupfile);
            $this->nextQuickInfo[] = sprintf($this->l('No valid tables were found to back up. Backup of file %s canceled.'), $backupfile);
            $this->error = 1;
            $this->next_desc = sprintf($this->l('Error during database backup for file %s.'), $backupfile);

            return false;
        } else {
            unset($this->nextParams['backup_loop_limit']);
            unset($this->nextParams['backup_lines']);
            unset($this->nextParams['backup_table']);
            if ($found) {
                $this->nextQuickInfo[] = sprintf($this->l('%1$s tables have been saved.'), $found);
            }
            $this->stepDone = true;
            // reset dbStep at the end of this step
            $this->nextParams['dbStep'] = 0;

            $this->next_desc = sprintf($this->l('Database backup done in filename %s. Now upgrading files...'), $this->backupName);
            $this->next = 'upgradeFiles';

            return true;
        }
    }

    public function ajaxProcessBackupFiles()
    {
        if (!$this->getConfig('PS_AUTOUP_BACKUP') && version_compare($this->upgrader->version_num, '1.7.0.0', '<')) {
            $this->stepDone = true;
            $this->next = 'backupDb';
            $this->next_desc = 'File backup skipped.';

            return true;
        }

        $this->nextParams = $this->currentParams;
        $this->stepDone = false;
        if (empty($this->backupFilesFilename)) {
            $this->next = 'error';
            $this->error = 1;
            $this->next_desc = $this->l('Error during backupFiles');
            $this->nextErrors[] = $this->l('[ERROR] backupFiles filename has not been set');
            $this->nextQuickInfo[] = $this->l('[ERROR] backupFiles filename has not been set');

            return false;
        }

        if (empty($this->nextParams['filesForBackup'])) {
            // @todo : only add files and dir listed in "originalPrestashopVersion" list
            $filesToBackup = $this->_listFilesInDir($this->prodRootDir, 'backup', false);
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupFileList, base64_encode(serialize($filesToBackup)));
            if (count($this->toBackupFileList)) {
                $this->nextQuickInfo[] = sprintf($this->l('%s Files to backup.'), count($this->toBackupFileList));
            }
            $this->nextParams['filesForBackup'] = $this->toBackupFileList;

            // delete old backup, create new
            if (!empty($this->backupFilesFilename) && file_exists($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename)) {
                unlink($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
            }

            $this->nextQuickInfo[] = sprintf($this->l('backup files initialized in %s'), $this->backupFilesFilename);
        }
        $filesToBackup = unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupFileList)));

        $this->next = 'backupFiles';
        if (count($this->toBackupFileList)) {
            $this->next_desc = sprintf($this->l('Backup files in progress. %d files left'), count($filesToBackup));
        }
        if (is_array($filesToBackup)) {
            $res = false;
            if (!self::$forcePclZip && class_exists('ZipArchive', false)) {
                $this->nextQuickInfo[] = $this->l('Using class ZipArchive...');
                $zipArchive = true;
                $zip = new ZipArchive();
                $res = $zip->open($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename, ZIPARCHIVE::CREATE);
                if ($res) {
                    $res = (isset($zip->filename) && $zip->filename) ? true : false;
                }
            }

            if (!$res) {
                $zipArchive = false;
                $this->nextQuickInfo[] = $this->l('Using class PclZip...');
                // pclzip can be already loaded (server configuration)
                $zip = new \PclZip($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
                $res = true;
            }

            if ($zip && $res) {
                $this->next = 'backupFiles';
                $this->stepDone = false;
                $files_to_add = [];
                $close_flag = true;
                for ($i = 0; $i < self::$loopBackupFiles; $i++) {
                    if (count($filesToBackup) <= 0) {
                        $this->stepDone = true;
                        $this->status = 'ok';
                        $this->next = 'backupDb';
                        $this->next_desc = $this->l('All files saved. Now backing up database');
                        $this->nextQuickInfo[] = $this->l('All files have been added to archive.', 'AdminThirtyBeesMigrate', true);
                        break;
                    }
                    // filesForBackup already contains all the correct files
                    $file = array_shift($filesToBackup);

                    $archiveFilename = ltrim(str_replace($this->prodRootDir, '', $file), DIRECTORY_SEPARATOR);
                    $size = filesize($file);
                    if ($size < self::$maxBackupFileSize) {
                        if (isset($zipArchive) && $zipArchive) {
                            $added_to_zip = $zip->addFile($file, $archiveFilename);
                            if ($added_to_zip) {
                                if ($filesToBackup) {
                                    $this->nextQuickInfo[] = sprintf($this->l('%1$s added to archive. %2$s files left.', 'AdminThirtyBeesMigrate', true), $archiveFilename, count($filesToBackup));
                                }
                            } else {
                                // if an error occur, it's more safe to delete the corrupted backup
                                $zip->close();
                                if (file_exists($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename)) {
                                    unlink($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
                                }
                                $this->next = 'error';
                                $this->error = 1;
                                $this->next_desc = sprintf($this->l('Error when trying to add %1$s to archive %2$s.', 'AdminThirtyBeesMigrate', true), $file, $archiveFilename);
                                $close_flag = false;
                                break;
                            }
                        } else {
                            $files_to_add[] = $file;
                            if (count($filesToBackup)) {
                                $this->nextQuickInfo[] = sprintf($this->l('File %1$s (size: %3$s) added to archive. %2$s files left.', 'AdminThirtyBeesMigrate', true), $archiveFilename, count($filesToBackup), $size);
                            } else {
                                $this->nextQuickInfo[] = sprintf($this->l('File %1$s (size: %2$s) added to archive.', 'AdminThirtyBeesMigrate', true), $archiveFilename, $size);
                            }
                        }
                    } else {
                        $this->nextQuickInfo[] = sprintf($this->l('File %1$s (size: %2$s) has been skipped during backup.', 'AdminThirtyBeesMigrate', true), $archiveFilename, $size);
                        $this->nextErrors[] = sprintf($this->l('File %1$s (size: %2$s) has been skipped during backup.', 'AdminThirtyBeesMigrate', true), $archiveFilename, $size);
                    }
                }

                if ($zipArchive && $close_flag && is_object($zip)) {
                    $zip->close();
                } elseif (!$zipArchive) {
                    $added_to_zip = $zip->add($files_to_add, PCLZIP_OPT_REMOVE_PATH, $this->prodRootDir);
                    if ($added_to_zip) {
                        $zip->privCloseFd();
                    }
                    if (!$added_to_zip) {
                        if (file_exists($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename)) {
                            unlink($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
                        }
                        $this->nextQuickInfo[] = sprintf($this->l('[ERROR] Error on backup using PclZip: %s.'), $zip->errorInfo(true));
                        $this->nextErrors[] = sprintf($this->l('[ERROR] Error on backup using PclZip: %s.'), $zip->errorInfo(true));
                        $this->next = 'error';
                    }
                }

                file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupFileList, base64_encode(serialize($filesToBackup)));

                return true;
            } else {
                $this->next = 'error';
                $this->next_desc = $this->l('unable to open archive');

                return false;
            }
        } else {
            $this->stepDone = true;
            $this->next = 'backupDb';
            $this->next_desc = $this->l('All files saved. Now backing up database.');

            return true;
        }
        // 4) save for display.
    }

    /**
     * Remove all sample files.
     *
     * @return boolean true if succeed
     */
    public function ajaxProcessRemoveSamples()
    {
        $this->stepDone = false;
        // remove all sample pics in img subdir
        if (!isset($this->currentParams['removeList'])) {
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/c', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/cms', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/l', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/m', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/os', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/p', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/s', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/scenes', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/st', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/su', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img', '404.gif');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img', 'favicon.ico');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img', 'logo.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img', 'logo_stores.gif');
            $this->_listSampleFiles($this->latestPath.'/prestashop/modules/editorial', 'homepage_logo.jpg');
            // remove all override present in the archive
            $this->_listSampleFiles($this->latestPath.'/prestashop/override', '.php');

            if (count($this->sampleFileList) > 0) {
                $this->nextQuickInfo[] = sprintf($this->l('Starting to remove %1$s sample files'), count($this->sampleFileList));
            }
            $this->nextParams['removeList'] = $this->sampleFileList;
        }

        $resRemove = true;
        for ($i = 0; $i < self::$loopRemoveSamples; $i++) {
            if (count($this->nextParams['removeList']) <= 0) {
                $this->stepDone = true;
                if ($this->getConfig('skip_backup')) {
                    $this->next = 'upgradeFiles';
                    $this->next_desc = $this->l('All sample files removed. Backup process skipped. Now upgrading files.');
                } else {
                    $this->next = 'backupFiles';
                    $this->next_desc = $this->l('All sample files removed. Now backing up files.');
                }

                // break the loop, all sample already removed
                return true;
            }
            $resRemove &= $this->_removeOneSample($this->nextParams['removeList']);
            if (!$resRemove) {
                break;
            }
        }

        return $resRemove;
    }

    /**
     * _listSampleFiles will make a recursive call to scandir() function
     * and list all file which match to the $fileext suffixe (this can be an extension or whole filename)
     *
     * @param string $dir     directory to look in
     * @param string $fileext suffixe filename
     *
     * @return void
     */
    private function _listSampleFiles($dir, $fileext = '.jpg')
    {
        $res = false;
        $dir = rtrim($dir, '/').DIRECTORY_SEPARATOR;
        $toDel = false;
        if (is_dir($dir) && is_readable($dir)) {
            $toDel = scandir($dir);
        }
        // copied (and kind of) adapted from AdminImages.php
        if (is_array($toDel)) {
            foreach ($toDel as $file) {
                if ($file[0] != '.') {
                    if (preg_match('#'.preg_quote($fileext, '#').'$#i', $file)) {
                        $this->sampleFileList[] = $dir.$file;
                    } elseif (is_dir($dir.$file)) {
                        $res &= $this->_listSampleFiles($dir.$file, $fileext);
                    }
                }
            }
        }

        return $res;
    }

    private function _removeOneSample($removeList)
    {
        if (is_array($removeList) and count($removeList) > 0) {
            if (file_exists($removeList[0]) and unlink($removeList[0])) {
                $item = str_replace($this->prodRootDir, '', array_shift($removeList));
                $this->next = 'removeSamples';
                $this->nextParams['removeList'] = $removeList;
                if (count($removeList) > 0) {
                    $this->nextQuickInfo[] = sprintf($this->l('%1$s items removed. %2$s items left.'), $item, count($removeList));
                }
            } else {
                $this->next = 'error';
                $this->nextParams['removeList'] = $removeList;
                $this->nextQuickInfo[] = sprintf($this->l('Error while removing item %1$s, %2$s items left.'), $removeList[0], count($removeList));
                $this->nextErrors[] = sprintf($this->l('Error while removing item %1$s, %2$s items left.'), $removeList[0], count($removeList));

                return false;
            }
        }

        return true;
    }

    /**
     * download PrestaShop archive according to the chosen channel
     *
     * @access public
     */
    public function ajaxProcessDownload()
    {
        if (ConfigurationTest::test_fopen() || ConfigurationTest::test_curl()) {
            if (!is_object($this->upgrader)) {
                $this->upgrader = new Upgrader();
            }
            // regex optimization
            preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
            $this->upgrader->channel = $this->getConfig('channel');
            $this->upgrader->branch = $matches[1];
            if ($this->getConfig('channel') == 'private' && !$this->getConfig('private_allow_major')) {
                $this->upgrader->checkPSVersion(false, ['private', 'minor']);
            } else {
                $this->upgrader->checkPSVersion(false, ['minor']);
            }

            if ($this->upgrader->channel == 'private') {
                $this->upgrader->link = $this->getConfig('private_release_link');
                $this->upgrader->md5 = $this->getConfig('private_release_md5');
            }
            $this->nextQuickInfo[] = sprintf($this->l('downloading from %s'), $this->upgrader->link);
            $this->nextQuickInfo[] = sprintf($this->l('file will be saved in %s'), $this->getFilePath());
            if (file_exists($this->downloadPath)) {
                self::deleteDirectory($this->downloadPath, false);
                $this->nextQuickInfo[] = $this->l('download directory has been emptied');
            }
            $report = '';
            $relative_download_path = str_replace(_PS_ROOT_DIR_, '', $this->downloadPath);
            if (ConfigurationTest::test_dir($relative_download_path, false, $report)) {
                $res = $this->upgrader->downloadLast($this->downloadPath, $this->destDownloadFilename);
                if ($res) {
                    $md5file = md5_file(realpath($this->downloadPath).DIRECTORY_SEPARATOR.$this->destDownloadFilename);
                    if ($md5file == $this->upgrader->md5) {
                        $this->nextQuickInfo[] = $this->l('Download complete.');
                        $this->next = 'unzip';
                        $this->next_desc = $this->l('Download complete. Now extracting...');
                    } else {
                        $this->nextQuickInfo[] = sprintf($this->l('Download complete but MD5 sum does not match (%s).'), $md5file);
                        $this->nextErrors[] = sprintf($this->l('Download complete but MD5 sum does not match (%s).'), $md5file);
                        $this->next = 'error';
                        $this->next_desc = $this->l('Download complete but MD5 sum does not match (%s). Operation aborted.');
                    }
                } else {
                    if ($this->upgrader->channel == 'private') {
                        $this->next_desc = $this->l('Error during download. The private key may be incorrect.');
                        $this->nextQuickInfo[] = $this->l('Error during download. The private key may be incorrect.');
                        $this->nextErrors[] = $this->l('Error during download. The private key may be incorrect.');
                    } else {
                        $this->next_desc = $this->l('Error during download');
                        $this->nextQuickInfo[] = $this->l('Error during download');
                        $this->nextErrors[] = $this->l('Error during download');
                    }
                    $this->next = 'error';
                }
            } else {
                $this->next_desc = $this->l('Download directory is not writable.');
                $this->nextQuickInfo[] = $this->l('Download directory is not writable.');
                $this->nextErrors[] = sprintf($this->l('Download directory %s is not writable.'), $this->downloadPath);
                $this->next = 'error';
            }
        } else {
            $this->nextQuickInfo[] = $this->l('You need allow_url_fopen or cURL enabled for automatic download to work.');
            $this->nextErrors[] = $this->l('You need allow_url_fopen or cURL enabled for automatic download to work.');
            $this->next = 'error';
            $this->next_desc = sprintf($this->l('You need allow_url_fopen or cURL enabled for automatic download to work. You can also manually upload it in filepath %s.'), $this->getFilePath());
        }
    }

    public function ajaxPreProcess()
    {
        /* PrestaShop demo mode */
        if (defined('_PS_MODE_DEMO_') && _PS_MODE_DEMO_) {
            return;
        }

        /* PrestaShop demo mode*/
        if (!empty($_POST['responseType']) && $_POST['responseType'] == 'json') {
            header('Content-Type: application/json');
        }

        if (!empty($_POST['action'])) {
            $action = $_POST['action'];
            if (isset(self::$skipAction[$action])) {
                $this->next = self::$skipAction[$action];
                $this->nextQuickInfo[] = $this->next_desc = sprintf($this->l('action %s skipped'), $action);
                unset($_POST['action']);
            } elseif (!method_exists(get_class($this), 'ajaxProcess'.$action)) {
                $this->next_desc = sprintf($this->l('action "%1$s" not found'), $action);
                $this->next = 'error';
                $this->error = '1';
            }
        }

        if (!method_exists('Tools', 'apacheModExists') || Tools::apacheModExists('evasive')) {
            sleep(1);
        }
    }

    public function displayAjax()
    {
        echo $this->buildAjaxResult();
    }

    public function buildAjaxResult()
    {
        $return = [];

        $return['error'] = $this->error;
        $return['stepDone'] = $this->stepDone;
        $return['next'] = $this->next;
        $return['status'] = $this->next == 'error' ? 'error' : 'ok';
        $return['next_desc'] = $this->next_desc;

        $this->nextParams['config'] = $this->getConfig();

        foreach ($this->ajaxParams as $v) {
            if (property_exists($this, $v)) {
                $this->nextParams[$v] = $this->$v;
            } else {
                $this->nextQuickInfo[] = sprintf($this->l('[WARNING] Property %s is missing'), $v);
            }
        }

        $return['nextParams'] = $this->nextParams;
        if (!isset($return['nextParams']['dbStep'])) {
            $return['nextParams']['dbStep'] = 0;
        }

        $return['nextParams']['typeResult'] = $this->nextResponseType;

        $return['nextQuickInfo'] = $this->nextQuickInfo;
        $return['nextErrors'] = $this->nextErrors;

        return Tools::jsonEncode($return);
    }

    public function display()
    {
        // in order to not use Tools class
        $upgrader = new Upgrader();
        preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
        $upgrader->branch = $matches[1];
        $channel = $this->getConfig('channel');
        switch ($channel) {
            case 'archive':
                $upgrader->channel = 'archive';
                $upgrader->version_num = $this->getConfig('archive.version_num');
                break;
            case 'directory':
                $upgrader->channel = 'directory';
                $upgrader->version_num = $this->getConfig('directory.version_num');
                break;
            default:
                $upgrader->channel = $channel;
                if (isset($_GET['refreshCurrentVersion'])) {
                    // delete the potential xml files we saved in config/xml (from last release and from current)
                    $upgrader->clearXmlMd5File(_PS_VERSION_);
                    $upgrader->clearXmlMd5File($upgrader->version_num);
                    if ($this->getConfig('channel') == 'private' && !$this->getConfig('private_allow_major')) {
                        $upgrader->checkPSVersion(true, ['private', 'minor']);
                    } else {
                        $upgrader->checkPSVersion(true, ['minor']);
                    }

                    Tools::redirectAdmin($this->currentIndex.'&conf=5&token='.Tools::getValue('token'));
                } else {
                    if ($this->getConfig('channel') == 'private' && !$this->getConfig('private_allow_major')) {
                        $upgrader->checkPSVersion(false, ['private', 'minor']);
                    } else {
                        $upgrader->checkPSVersion(false, ['minor']);
                    }
                }
        }

        $this->upgrader = $upgrader;

        /* Make sure the user has configured the upgrade options, or set default values */
        $configuration_keys = [
            'PS_AUTOUP_UPDATE_DEFAULT_THEME' => 1, 'PS_AUTOUP_CHANGE_DEFAULT_THEME' => 0, 'PS_AUTOUP_KEEP_MAILS' => 1, 'PS_AUTOUP_CUSTOM_MOD_DESACT' => 1,
            'PS_AUTOUP_MANUAL_MODE'          => 0, 'PS_AUTOUP_PERFORMANCE' => 1, 'PS_DISPLAY_ERRORS' => 0,
        ];
        foreach ($configuration_keys as $k => $default_value) {
            if (\Configuration::get($k) == '') {
                \Configuration::updateValue($k, $default_value);
            }
        }


        $this->html .= '<script type="text/javascript">var jQueryVersionPS = parseInt($().jquery.replace(/\./g, ""));</script>
		<script type="text/javascript" src="'.__PS_BASE_URI__.'modules/autoupgrade/js/jquery-3.2.0.min.js"></script>';

        /* PrestaShop demo mode */
        if (defined('_PS_MODE_DEMO_') && _PS_MODE_DEMO_) {
            echo '<div class="error">'.$this->l('This functionality has been disabled.').'</div>';

            return;
        }

        if (!file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php')) {
            echo '<div class="error">'.'<img src="../img/admin/warning.gif" /> '.$this->l('[TECHNICAL ERROR] ajax-upgradetab.php is missing. Please reinstall or reset the module.').'</div>';

            return false;
        }
        /* PrestaShop demo mode*/
        $this->html .= '<link type="text/css" rel="stylesheet" href="'.__PS_BASE_URI__.'modules/autoupgrade/css/styles.css" />';

        $this->html .= '
		<h1>'.$this->l('1-click Upgrade').'</h1>
		<fieldset id="informationBlock" class="information" style="float: left; width: 30%;">
			<legend>'.$this->l('Welcome!').'</legend>
			<p>
				'.$this->l('With the PrestaShop 1-Click Upgrade module, upgrading your store to the latest version available has never been easier!').'<br /><br />
				<span style="color:#CC0000;font-weight:bold">'.$this->l('Please always perform a full manual backup of your files and database before starting any upgrade.').'</span><br />
				'.$this->l('Double-check the integrity of your backup and that you can easily manually roll-back if necessary.').'<br />
				'.$this->l('If you do not know how to proceed, ask your hosting provider.').'
			</p>
		</fieldset>';

        /* Checks/requirements and "Upgrade PrestaShop now" blocks */
        $this->html .= $this->_displayCurrentConfiguration();
        $this->html .= '<div class="clear"></div>';
        $this->_displayBlockUpgradeButton();

        $this->html .= $this->displayAdminTemplate(__DIR__.'/views/templates/admin/checklist.phtml');

        $this->html .= '<script type="text/javascript" src="'.__PS_BASE_URI__.'modules/autoupgrade/js/jquery.xml2json.js"></script>';
        $this->html .= $this->_getJsInit();

        echo $this->html;
    }

    /** this returns fieldset containing the configuration points you need to use autoupgrade
     *
     * @return string
     */
    protected function _displayCurrentConfiguration()
    {
        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/currentconfiguration.phtml');
    }

    public function getCheckCurrentPsConfig()
    {
        static $allowed_array;

        if (empty($allowed_array)) {
            $allowed_array = [];
            $allowed_array['fopen'] = ConfigurationTest::test_fopen() || ConfigurationTest::test_curl();
            $allowed_array['root_writable'] = $this->getRootWritable();
            $admin_dir = trim(str_replace($this->prodRootDir, '', $this->adminDir), DIRECTORY_SEPARATOR);
            $allowed_array['admin_au_writable'] = ConfigurationTest::test_dir($admin_dir.DIRECTORY_SEPARATOR.$this->autoupgradeDir, false, $report);
            $allowed_array['shop_deactivated'] = (!\Configuration::get('PS_SHOP_ENABLE') || (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['127.0.0.1', 'localhost'])));
            $allowed_array['cache_deactivated'] = !(defined('_PS_CACHE_ENABLED_') && _PS_CACHE_ENABLED_);
            $allowed_array['module_version_ok'] = $this->checkAutoupgradeLastVersion();
            if (version_compare(_PS_VERSION_, '1.5.0.0', '<')) {
                $allowed_array['test_mobile'] = ConfigurationTest::test_mobile();
            }
        }

        return $allowed_array;
    }

    public function getRootWritable()
    {
        // Root directory permissions cannot be checked recursively anymore, it takes too much time
        $this->root_writable = ConfigurationTest::test_dir('/', false, $report);
        $this->root_writable_report = $report;

        return $this->root_writable;
    }

    public function checkAutoupgradeLastVersion()
    {
        if ($this->getModuleVersion()) {
            $this->lastAutoupgradeVersion = version_compare($this->module_version, $this->upgrader->autoupgrade_last_version, '>=');
        } else {
            $this->lastAutoupgradeVersion = true;
        }

        return $this->lastAutoupgradeVersion;
    }

    public function getModuleVersion()
    {
        if (is_null($this->module_version)) {
            if (file_exists(_PS_ROOT_DIR_.'/modules/autoupgrade/config.xml')
                && $xml_module_version = simplexml_load_file(_PS_ROOT_DIR_.'/modules/autoupgrade/config.xml')
            ) {
                $this->module_version = (string) $xml_module_version->version;
            } else {
                $this->module_version = false;
            }
        }

        return $this->module_version;
    }

    public function configOk()
    {
        $allowed_array = $this->getCheckCurrentPsConfig();
        $allowed = array_product($allowed_array);

        return $allowed;
    }

    /**
     * _displayBlockUpgradeButton
     * display the summary current version / target vesrion + "Upgrade Now" button with a "more options" button
     *
     * @access private
     * @return void
     */
    private function _displayBlockUpgradeButton()
    {
        $this->html .= $this->displayAdminTemplate(__DIR__.'/views/templates/admin/blockupgradebutton.phtml');
    }

    public function getBlockConfigurationAdvanced()
    {
        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/advanced.phtml');
    }

    public function getBlockSelectChannel($channel = 'stable')
    {
        $this->templateVars['optChannels'] = [
            'stable',
            'beta',
        ];
        $download = $this->downloadPath.DIRECTORY_SEPARATOR;
        $this->templateVars['download'] = $download;
        $this->templateVars['channelDir'] = glob($download.'*.zip');
        $this->templateVars['archiveFilename'] = $this->getConfig('archive.filename');
        $this->templateVars['selectedChannel'] = is_string($channel) ? $channel : 'stable';

        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/channelselector.phtml');
    }

    public function displayDevTools()
    {
        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/devtools.phtml');
    }

    private function _upgradingTo17Step1()
    {
        $checks = new CheckTests17();
        $checkRequiredTests = $checks->checkRequiredTests();

        $this->html .= '
        <fieldset id="hideStep17-1">
            <legend>'.sprintf($this->l('Upgrading to PrestaShop %s'), $this->upgrader->version_num).'</legend>';

        $this->html .= '<p>'.sprintf($this->l('Migrating from PrestaShop %s to the PrestaShop %s version will have a HUGE impact on your store.'), _PS_VERSION_, $this->upgrader->version_num).'</p>';

        $this->html .= '<p><b>'.sprintf($this->l('You will not be able to use your current theme, modules and advanced stock data as soon as your store is upgraded to %s'), $this->upgrader->version_num).'</b></p>';

        $this->html .= '<p>'.$this->l('Please also make sure that your server installation meets the minimum requirements:').'</p>';

        $this->html .= '<ul style="list-style-type: none;">';

        if (!empty($checkRequiredTests)) {
            $pic_ok = '<img src="../img/admin/enabled.gif" alt="ok"/>';
            $pic_nok = '<img src="../img/admin/disabled.gif" alt="nok"/>';

            $translationsTests = [
                'upload'                    => sprintf($this->l('%s folder exists'), '/upload/'),
                'img_dir'                   => sprintf($this->l('%s folder exists'), '/img/'),
                'module_dir'                => sprintf($this->l('%s folder exists'), '/modules/'),
                'theme_dir'                 => sprintf($this->l('%s folder exists'), '/themes/'),
                'translations_dir'          => sprintf($this->l('%s folder exists'), '/translations/'),
                'customizable_products_dir' => sprintf($this->l('%s folder exists'), '/upload/'),
                'virtual_products_dir'      => sprintf($this->l('%s folder exists'), '/download/'),
                'config_dir'                => sprintf($this->l('%s folder exists'), '/config/'),
                'mails_dir'                 => sprintf($this->l('%s folder exists'), '/mails/'),

                'system' => $this->l('Required system functions are enabled (fopen, file_exists, chmod)'),

                'phpversion'         => sprintf($this->l('%s function is enabled'), 'version_compare'),
                'apache_mod_rewrite' => sprintf($this->l('%s function is enabled'), 'apache_mod_rewrite'),

                'curl'      => sprintf($this->l('%s extension is enabled'), 'cURL'),
                'gd'        => sprintf($this->l('%s extension is enabled'), 'gd'),
                'json'      => sprintf($this->l('%s extension is enabled'), 'json'),
                'pdo_mysql' => sprintf($this->l('%s extension is enabled'), 'PDO MySQL'),
                'openssl'   => sprintf($this->l('%s extension is enabled'), 'open SSL'),
                'simplexml' => sprintf($this->l('%s extension is enabled'), 'simpleXML'),
                'zip'       => sprintf($this->l('%s extension is enabled'), 'zip'),
                'intl'      => sprintf($this->l('%s extension is enabled'), 'intl'),
                'fileinfo'  => sprintf($this->l('%s extension is enabled'), 'fileinfo'),
            ];

            foreach ($checkRequiredTests['checks'] as $key => $test) {
                $this->html .= '<li>'.($test === 'ok' ? $pic_ok : $pic_nok).' '.(isset($translationsTests[$key]) ? $translationsTests[$key] : $key).'</li>';
            }

            if (empty($checkRequiredTests['success'])) {
                $this->html .= '<p style="text-align:center;font-weight: bold; font-size: 1.2em;">'.$this->l('Your installation lacks some of the minimum requirements. Please check and try again.').'</p>';
            }
        }

        $this->html .= '</ul>
        </fieldset>';
    }

    private function _upgradingTo17Step2()
    {
        $this->html .= $this->displayAdminTemplate(__DIR__.'/views/templates/admin/upgradingto17step2.phtml');
    }

    private function _displayComparisonBlock()
    {
        $this->html .= $this->displayAdminTemplate(__DIR__.'/views/templates/admin/comparisonblock.phtml');
    }

    private function _displayBlockActivityLog()
    {
        $this->html .= $this->displayAdminTemplate(__DIR__.'/views/templates/admin/activitylog.phtml');
    }

    protected function _displayForm($name, $fields, $tabname, $size, $icon)
    {
        $confValues = $this->getConfig();
        $required = false;

        $this->html .= '
			<fieldset id="'.$name.'Block"><legend><img src="../img/admin/'.strval($icon).'.gif" />'.$tabname.'</legend>';
        foreach ($fields as $key => $field) {
            if (isset($field['required']) && $field['required']) {
                $required = true;
            }

            if ((isset($field['disabled']) && $field['disabled']) || version_compare($this->upgrader->version_num, '1.7.1.0', '>=')) {
                $disabled = true;
            } else {
                $disabled = false;
            }

            if (isset($confValues17[$key])) {
                $val = $confValues17[$key];
            } else {
                if (isset($confValues[$key])) {
                    $val = $confValues[$key];
                } else {
                    $val = isset($field['defaultValue']) ? $field['defaultValue'] : false;
                }
            }

            if (!in_array($field['type'], ['image', 'radio', 'container', 'container_end']) || isset($field['show'])) {
                $this->html .= '<div style="clear: both; padding-top:15px;">'.($field['title'] ? '<label >'.$field['title'].'</label>' : '').'<div class="margin-form" style="padding-top:5px;">';
            }

            /* Display the appropriate input type for each field */
            switch ($field['type']) {
                case 'disabled':
                    $this->html .= $field['disabled'];
                    break;

                case 'bool':
                    $this->html .= '<label class="t" for="'.$key.'_on">
						<img src="../img/admin/enabled.gif" alt="'.$this->l('Yes').'" title="'.$this->l('Yes').'" /></label>
					<input type="radio" '.($disabled ? 'disabled="disabled"' : '').' name="'.$key.'" id="'.$key.'_on" value="1"'.($val ? ' checked="checked"' : '').(isset($field['js']['on']) ? $field['js']['on'] : '').' />
					<label class="t" for="'.$key.'_on"> '.$this->l('Yes').'</label>
					<label class="t" for="'.$key.'_off"><img src="../img/admin/disabled.gif" alt="'.$this->l('No').'" title="'.$this->l('No').'" style="margin-left: 10px;" /></label>
					<input type="radio" '.($disabled ? 'disabled="disabled"' : '').' name="'.$key.'" id="'.$key.'_off" value="0" '.(!$val ? 'checked="checked"' : '').(isset($field['js']['off']) ? $field['js']['off'] : '').'/>
					<label class="t" for="'.$key.'_off"> '.$this->l('No').'</label>';
                    break;

                case 'radio':
                    foreach ($field['choices'] as $cValue => $cKey) {
                        $this->html .= '<input '.($disabled ? 'disabled="disabled"' : '').' type="radio" name="'.$key.'" id="'.$key.$cValue.'_on" value="'.(int) ($cValue).'"'.(($cValue == $val) ? ' checked="checked"' : '').(isset($field['js'][$cValue]) ? ' '.$field['js'][$cValue] : '').' /><label class="t" for="'.$key.$cValue.'_on"> '.$cKey.'</label><br />';
                    }
                    $this->html .= '<br />';
                    break;

                case 'select':
                    $this->html .= '<select name='.$key.'>';
                    foreach ($field['choices'] as $cValue => $cKey) {
                        $this->html .= '<option value="'.(int) $cValue.'"'.(($cValue == $val) ? ' selected="selected"' : '').'>'.$cKey.'</option>';
                    }
                    $this->html .= '</select>';
                    break;

                case 'textarea':
                    $this->html .= '<textarea '.($disabled ? 'disabled="disabled"' : '').' name='.$key.' cols="'.$field['cols'].'" rows="'.$field['rows'].'">'.htmlentities($val, ENT_COMPAT, 'UTF-8').'</textarea>';
                    break;

                case 'container':
                    $this->html .= '<div id="'.$key.'">';
                    break;

                case 'container_end':
                    $this->html .= (isset($field['content']) === true ? $field['content'] : '').'</div>';
                    break;

                case 'text':
                default:
                    $this->html .= '<input '.($disabled ? 'disabled="disabled"' : '').' type="'.$field['type'].'"'.(isset($field['id']) === true ? ' id="'.$field['id'].'"' : '').' size="'.(isset($field['size']) ? (int) ($field['size']) : 5).'" name="'.$key.'" value="'.($field['type'] == 'password' ? '' : htmlentities($val, ENT_COMPAT, 'UTF-8')).'" />'.(isset($field['next']) ? '&nbsp;'.strval($field['next']) : '');
            }
            $this->html .= ((isset($field['required']) && $field['required'] && !in_array($field['type'], ['image', 'radio'])) ? ' <sup>*</sup>' : '');
            $this->html .= (isset($field['desc']) ? '<p style="clear:both">'.((isset($field['thumb']) && $field['thumb'] && $field['thumb']['pos'] == 'after') ? '<img src="'.$field['thumb']['file'].'" alt="'.$field['title'].'" title="'.$field['title'].'" style="float:left;" />' : '').$field['desc'].'</p>' : '');
            if (!in_array($field['type'], ['image', 'radio', 'container', 'container_end']) || isset($field['show'])) {
                $this->html .= '</div></div>';
            }
        }

        $this->html .= '	<div align="center" style="margin-top: 20px;">
					<input type="submit" value="'.$this->l('   Save   ', 'AdminPreferences').'" name="customSubmitAutoUpgrade" class="button" />
				</div>
				'.($required ? '<div class="small"><sup>*</sup> '.$this->l('Required field', 'AdminPreferences').'</div>' : '').'
			</fieldset>
			<br/>';
    }

    private function getConfigFor17()
    {
        return [
            'PS_AUTOUP_CUSTOM_MOD_DESACT'    => 1,
            'PS_AUTOUP_UPDATE_DEFAULT_THEME' => 1,
            'PS_AUTOUP_CHANGE_DEFAULT_THEME' => 1,
            'PS_AUTOUP_KEEP_MAILS'           => 1,
            'PS_AUTOUP_BACKUP'               => 1,
            'PS_AUTOUP_KEEP_IMAGES'          => 1,
        ];
    }

    protected function _displayRollbackForm()
    {
        $this->html .= $this->displayAdminTemplate(__DIR__.'/views/templates/admin/rollbackform.phtml');
    }

    protected function getBackupDbAvailable()
    {
        $array = [];

        $files = scandir($this->backupPath);

        foreach ($files as $file) {
            if ($file[0] == 'V' && is_dir($this->backupPath.DIRECTORY_SEPARATOR.$file)) {
                $array[] = $file;
            }
        }

        return $array;
    }

    protected function getBackupFilesAvailable()
    {
        $array = [];
        $files = scandir($this->backupPath);
        foreach ($files as $file) {
            if ($file[0] != '.') {
                if (substr($file, 0, 16) == 'auto-backupfiles') {
                    $array[] = preg_replace('#^auto-backupfiles_(.*-[0-9a-f]{1,8})\..*$#', '$1', $file);
                }
            }
        }

        return $array;
    }

    private function _buttonUpgradingTo17Step2()
    {
        $this->html .= '<p class="clear" style="text-align: center;"><a href="" id="showStep17-2" class="button-autoupgrade17">'.sprintf($this->l('Upgrade to PrestaShop %s'), $this->upgrader->version_num).'</a></p>';
    }

    private function _getJsInit()
    {
        return $this->displayAdminTemplate(__DIR__.'/views/templates/admin/mainjs.phtml');
    }

    public function optionDisplayErrors()
    {
        if ($this->getConfig('PS_DISPLAY_ERRORS')) {
            error_reporting(E_ALL);
            ini_set('display_errors', 'on');
        } else {
            ini_set('display_errors', 'off');
        }
    }

    /**
     * Display a phtml template file
     *
     * @param string $file
     *
     * @return string Content
     *
     * @since 1.0.0
     */
    public function displayAdminTemplate($file)
    {
        ob_start();

        include($file);

        $content = ob_get_contents();
        if (ob_get_level() && ob_get_length() > 0) {
            ob_end_clean();
        }
        return $content;
    }
}
