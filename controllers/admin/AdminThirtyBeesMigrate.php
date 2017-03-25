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
    /** @var int $loopBackupFiles */
    public static $loopBackupFiles = 400;
    /**
     * Used for translations
     *
     * @var int $maxBackupFileSize
     */
    public static $maxBackupFileSize = 15728640;
    // retrocompatibility
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
    public $bootstrap = true;
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
    private $install_autoupgrade_dir; // 15 Mo
    private $restoreIgnoreFiles = [];
    private $restoreIgnoreAbsoluteFiles = []; // 4096 ko
    private $backupIgnoreFiles = [];
    private $backupIgnoreAbsoluteFiles = [];
    private $excludeFilesFromUpgrade = [];
    private $excludeAbsoluteFilesFromUpgrade = [];
    private $restoreName = null;

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
     * @param bool $autoupgradeDir
     *
     * @since 1.0.0
     */
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

        global $ajax;

        if (!empty($ajax)) {
            $this->ajax = true;
        }

        parent::__construct();

        // Database instanciation (need to be cached because there will be at least 100k calls in the upgrade process
        $this->db = Db::getInstance();

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

        $fileTab = @filemtime($this->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php');
        $file = @filemtime(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$this->autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php');

        if ($fileTab < $file) {
            @copy(
                _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$this->autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php',
                $this->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php'
            );
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
        parent::init();

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
                $this->install_version = $upgrader->versionNum;
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
     * @return void
     *
     * @since 1.0.0
     */
    public function initContent()
    {
        parent::initContent();

        /* PrestaShop demo mode */
        if (defined('_PS_MODE_DEMO_') && _PS_MODE_DEMO_) {
            echo '<div class="error">'.$this->l('This functionality has been disabled.').'</div>';

            return;
        }

        if (!file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php')) {
            echo '<div class="error">'.'<img src="../img/admin/warning.gif" /> '.$this->l('[TECHNICAL ERROR] ajax-upgradetab.php is missing. Please reinstall or reset the module.').'</div>';

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

        $this->context->smarty->assign('content', $html);
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
        $str = self::findTranslation('autoupgrade', $string, 'AdminThirtyBeesMigrateController');
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

            static::$lCache[$cacheKey] = $ret;
        }

        return static::$lCache[$cacheKey];
    }

    /**
     * return the value of $key, configuration saved in $this->configFilename.
     * if $key is empty, will return an array with all configuration;
     *
     * @param string $key
     *
     * @access public
     * @return false|array|string
     *
     * @since 1.0.0
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
     *
     * @return false
     *
     * @since 1.0.0
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

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function cleanTmpFiles()
    {
        foreach ($this->tmpFiles as $tmpFile) {
            if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->$tmpFile)) {
                unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->$tmpFile);
            }
        }
    }

    /**
     * replace tools encrypt
     *
     * @param string $string
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function encrypt($string)
    {
        return md5(_COOKIE_KEY_.$string);
    }

    /**
     * @param bool $disable
     *
     * @return bool
     *
     * @since 1.0.0
     */
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
            foreach (\Shop::getCompleteListOfShopsID() as $idShop) {
                \Configuration::updateValue('PS_SHOP_ENABLE', 0, false, null, (int) $idShop);
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
                        $res = static::deleteDirectory($this->backupPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR);
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
        } elseif ($this->getConfig('PS_DISPLAY_ERRORS')) {
            $this->writeConfig(['PS_DISPLAY_ERRORS' => '0']);
        }
    }

    /**
     * update module configuration (saved in file $this->configFilename) with $new_config
     *
     * @param array $newConfig
     *
     * @return boolean true if success
     *
     * @since 1.0.0
     */
    public function writeConfig($newConfig)
    {
        if (!file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename)) {
            $this->upgrader->channel = $newConfig['channel'];
            $this->upgrader->checkPSVersion();
            $this->installVersion = $this->upgrader->versionNum;

            return $this->resetConfig($newConfig);
        }

        $config = file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename);
        $configUnserialized = @unserialize(base64_decode($config));
        if (!is_array($configUnserialized)) {
            $configUnserialized = @unserialize($config);
        } // retrocompat, before base64_decode implemented
        $config = $configUnserialized;

        foreach ($newConfig as $key => $val) {
            $config[$key] = $val;
        }
        $this->next_desc = $this->l('Configuration successfully updated.').' <strong>'.$this->l('This page will now be reloaded and the module will check if a new version is available.').'</strong>';

        return (bool) file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename, base64_encode(serialize($config)));
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
        return (bool) file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename, base64_encode(serialize($newConfig)));
    }

    /**
     * Delete directory and subdirectories
     *
     * @param string $dirname    Directory name
     * @param bool   $deleteSelf
     *
     * @return bool
     * @since 1.0.0
     */
    public static function deleteDirectory($dirname, $deleteSelf = true)
    {
        return Tools::deleteDirectory($dirname, $deleteSelf);
    }

    /** returns an array containing information related to the channel $channel
     *
     * @param string $channel name of the channel
     *
     * @return array available, version_num, version_name, link, md5, changelog
     *
     * @since 1.0.0
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
            $upgradeInfo['version_num'] = $upgrader->versionNum;
            $upgradeInfo['version_name'] = $upgrader->versionName;
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
                    $upgradeInfo['version_num'] = $upgrader->versionNum;
                    $upgradeInfo['version_name'] = $upgrader->versionName;
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

    /**
     * @param array $upgradeInfo
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function divChannelInfos($upgradeInfo)
    {
        if ($this->getConfig('channel') == 'private') {
            $upgradeInfo['link'] = $this->getConfig('private_release_link');
            $upgradeInfo['md5'] = $this->getConfig('private_release_md5');
        }

        return $this->displayAdminTemplate(__DIR__.'/../../views/templates/admin/channelinfo.phtml', ['upgradeInfo' => $upgradeInfo]);
    }

    /**
     * @desc extract a zip file to the given directory
     * @return bool success
     * we need a copy of it to be able to restore without keeping Tools and Autoload stuff
     *
     * @since 1.0.0
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
        if (!static::$forcePclZip && class_exists('ZipArchive', false)) {
            $this->nextQuickInfo[] = $this->l('Using class ZipArchive...');
            $zip = new \ZipArchive();
            if ($zip->open($fromFile) === true && isset($zip->filename) && $zip->filename) {
                $extractResult = true;
                // We extract file by file, it is very fast
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $extractResult &= $zip->extractTo($toDir, [$zip->getNameIndex($i)]);
                }

                if ($extractResult) {
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

            if (($fileList = $zip->listContent()) == 0) {
                $this->next = 'error';
                $this->nextQuickInfo[] = sprintf($this->l('[ERROR] Error on extracting archive using PclZip: %s.'), $zip->errorInfo(true));

                return false;
            }

            // PCL is very slow, so we need to extract files 500 by 500
            $i = 0;
            $j = 1;
            foreach ($fileList as $file) {
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
                if (($extractResult = $zip->extract(PCLZIP_OPT_BY_INDEX, $index, PCLZIP_OPT_PATH, $toDir, PCLZIP_OPT_REPLACE_NEWER)) == 0) {
                    $this->next = 'error';
                    $this->nextErrors[] = sprintf($this->l('[ERROR] Error on extracting archive using PclZip: %s.'), $zip->errorInfo(true));

                    return false;
                } else {
                    foreach ($extractResult as $extractedFile) {
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

    /**
     * list files to upgrade and return it as array
     *
     * @param string $dir
     *
     * @return number of files found
     *
     * @since 1.0.0
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
     * Check whether a file is in backup or restore skip list
     *
     * @param mixed $file     : current file or directory name eg:'.svn' , 'settings.inc.php'
     * @param mixed $fullpath : current file or directory fullpath eg:'/home/web/www/prestashop/config/settings.inc.php'
     * @param mixed $way      : 'backup' , 'upgrade'
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function _skipFile($file, $fullpath, $way = 'backup')
    {
        $fullpath = str_replace('\\', '/', $fullpath); // wamp compliant
        $rootpath = str_replace('\\', '/', $this->prodRootDir);
        $adminDir = str_replace($this->prodRootDir, '', $this->adminDir);
        switch ($way) {
            case 'backup':
                if (in_array($file, $this->backupIgnoreFiles)) {
                    return true;
                }

                foreach ($this->backupIgnoreAbsoluteFiles as $path) {
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$adminDir, $path);
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
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$adminDir, $path);
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
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$adminDir, $path);
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
     * @return bool
     *
     * @since 1.0.0
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
                    $typeTrad = $this->getTranslationFileType($file);
                    $res = $this->mergeTranslationFile($orig, $dest, $typeTrad);
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
                    static::deleteDirectory($dest, true);
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
     * @return bool
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
     */
    public function getTranslationFileType($file)
    {
        $type = false;
        // line shorter
        $separator = addslashes(DIRECTORY_SEPARATOR);
        $translationDir = $separator.'translations'.$separator;
        if (version_compare(_PS_VERSION_, '1.5.0.5', '<')) {
            $regexModule = '#'.$separator.'modules'.$separator.'.*'.$separator.'('.implode('|', $this->installedLanguagesIso).')\.php#';
        } else {
            $regexModule = '#'.$separator.'modules'.$separator.'.*'.$translationDir.'('.implode('|', $this->installedLanguagesIso).')\.php#';
        }

        if (preg_match($regexModule, $file)) {
            $type = 'module';
        } elseif (preg_match('#'.$translationDir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'admin\.php#', $file)) {
            $type = 'back office';
        } elseif (preg_match('#'.$translationDir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'errors\.php#', $file)) {
            $type = 'error message';
        } elseif (preg_match('#'.$translationDir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'fields\.php#', $file)) {
            $type = 'field';
        } elseif (preg_match('#'.$translationDir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'pdf\.php#', $file)) {
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
     * @return bool
     *
     * @since 1.0.0
     */
    public function mergeTranslationFile($orig, $dest, $type)
    {
        switch ($type) {
            case 'front office':
                $varName = '_LANG';
                break;
            case 'back office':
                $varName = '_LANGADM';
                break;
            case 'error message':
                $varName = '_ERRORS';
                break;
            case 'field':
                $varName = '_FIELDS';
                break;
            case 'module':
                $varName = '_MODULE';
                // if current version is before 1.5.0.5, module has no translations dir
                if (version_compare(_PS_VERSION_, '1.5.0.5', '<') && (version_compare($this->installVersion, '1.5.0.5', '>'))) {
                    $dest = str_replace(DIRECTORY_SEPARATOR.'translations', '', $dest);
                }

                break;
            case 'pdf':
                $varName = '_LANGPDF';
                break;
            case 'mail':
                $varName = '_LANGMAIL';
                break;
            default:
                return false;
        }

        if (!file_exists($orig)) {
            $this->nextQuickInfo[] = sprintf($this->l('[NOTICE] File %s does not exist, merge skipped.'), $orig);

            return true;
        }
        include($orig);
        if (!isset($$varName)) {
            $this->nextQuickInfo[] = sprintf($this->l('[WARNING] %1$s variable missing in file %2$s. Merge skipped.'), $varName, $orig);

            return true;
        }
        $varOrig = $$varName;

        if (!file_exists($dest)) {
            $this->nextQuickInfo[] = sprintf($this->l('[NOTICE] File %s does not exist, merge skipped.'), $dest);

            return false;
        }
        include($dest);
        if (!isset($$varName)) {
            // in that particular case : file exists, but variable missing, we need to delete that file
            // (if not, this invalid file will be copied in /translations during upgradeDb process)
            if ('module' == $type && file_exists($dest)) {
                unlink($dest);
            }
            $this->nextQuickInfo[] = sprintf($this->l('[WARNING] %1$s variable missing in file %2$s. File %2$s deleted and merge skipped.'), $varName, $dest);

            return false;
        }
        $varDest = $$varName;

        $merge = array_merge($varOrig, $varDest);

        if ($fd = fopen($dest, 'w')) {
            fwrite($fd, "<?php\n\nglobal \$".$varName.";\n\$".$varName." = array();\n");
            foreach ($merge as $k => $v) {
                if (get_magic_quotes_gpc()) {
                    $v = stripslashes($v);
                }
                if ('mail' == $type) {
                    fwrite($fd, '$'.$varName.'[\''.$this->db->escape($k).'\'] = \''.$this->db->escape($v).'\';'."\n");
                } else {
                    fwrite($fd, '$'.$varName.'[\''.$this->db->escape($k, true).'\'] = \''.$this->db->escape($v, true).'\';'."\n");
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
     * list modules to upgrade and save them in a serialized array in $this->toUpgradeModuleList
     *
     * @return number of files found
     *
     * @since 1.0.0
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
        foreach ($allModules as $moduleName) {
            if (is_file($dir.DIRECTORY_SEPARATOR.$moduleName)) {
                continue;
            } elseif (is_dir($dir.DIRECTORY_SEPARATOR.$moduleName.DIRECTORY_SEPARATOR)) {
                if (is_array($this->modules_addons)) {
                    $idAddons = array_search($moduleName, $this->modules_addons);
                }
                if (isset($idAddons) && $idAddons) {
                    if ($moduleName != $this->autoupgradeDir) {
                        $list[] = ['id' => $idAddons, 'name' => $moduleName];
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
     * @param mixed $idModule
     * @param mixed $name
     *
     * @access public
     * @return void
     */
    public function upgradeThisModule($idModule, $name)
    {
        $zipFullpath = $this->tmpPath.DIRECTORY_SEPARATOR.$name.'.zip';

        $destExtract = $this->prodRootDir.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR;

        $addonsUrl = 'api.addons.prestashop.com';
        $protocolsList = ['https://' => 443, 'http://' => 80];
        if (!extension_loaded('openssl')) {
            unset($protocolsList['https://']);
        } else {
            unset($protocolsList['http://']);
        }

        $postData = 'version='.$this->installVersion.'&method=module&id_module='.(int) $idModule;

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
            $content = Tools::file_get_contents($protocol.$addonsUrl, false, $context);
            if ($content == false || substr($content, 5) == '<?xml') {
                continue;
            }
            if ($content !== null) {
                if ((bool) file_put_contents($zipFullpath, $content)) {
                    if (filesize($zipFullpath) <= 300) {
                        unlink($zipFullpath);
                    } // unzip in modules/[mod name] old files will be conserved
                    elseif ($this->ZipExtract($zipFullpath, $destExtract)) {
                        $this->nextQuickInfo[] = sprintf($this->l('The files of module %s have been upgraded.'), $name);
                        if (file_exists($zipFullpath)) {
                            unlink($zipFullpath);
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

    /**
     * This function now replaces doUpgrade.php or upgrade.php
     *
     * @return bool
     *
     * @since 1.0.0
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

            $upgradeDirSql = INSTALL_PATH.'/upgrade/sql';
            // if 1.4;
            if (!file_exists($upgradeDirSql)) {
                $upgradeDirSql = INSTALL_PATH.'/sql/upgrade';
            }

            if (!file_exists($upgradeDirSql)) {
                $this->next = 'error';
                $this->next_desc = $this->l('Unable to find upgrade directory in the installation path.');

                return false;
            }

            if ($handle = opendir($upgradeDirSql)) {
                while (false !== ($file = readdir($handle))) {
                    if ($file != '.' and $file != '..') {
                        $upgradeFiles[] = str_replace(".sql", "", $file);
                    }
                }
                closedir($handle);
            }
            if (empty($upgradeFiles)) {
                $this->next = 'error';
                $this->nextQuickInfo[] = sprintf($this->l('Cannot find the SQL upgrade files. Please check that the %s folder is not empty.'), $upgradeDirSql);
                $this->nextErrors[] = sprintf($this->l('Cannot find the SQL upgrade files. Please check that the %s folder is not empty.'), $upgradeDirSql);

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
                if (function_exists('deactivate_custom_modules')) {
                    deactivate_custom_modules();
                }
            }

            if (version_compare(INSTALL_VERSION, '1.5.6.1', '=')) {
                $filename = _PS_INSTALLER_PHP_UPGRADE_DIR_.'migrate_orders.php';
                $content = file_get_contents($filename);
                $strOld[] = '$values_order_detail = array();';
                $strOld[] = '$values_order = array();';
                $strOld[] = '$col_order_detail = array();';
                $content = str_replace($strOld, '', $content);
                file_put_contents($filename, $content);
            }

            foreach ($neededUpgradeFiles as $version) {
                $file = $upgradeDirSql.DIRECTORY_SEPARATOR.$version.'.sql';
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

            foreach ($sqlContentVersion as $upgradeFile => $sqlContent) {
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
                                $funcName = str_replace($pattern[0], '', $php[0]);
                                if (version_compare(INSTALL_VERSION, '1.5.5.0', '=') && $funcName == 'fix_download_product_feature_active') {
                                    continue;
                                }

                                if (!file_exists(_PS_INSTALLER_PHP_UPGRADE_DIR_.strtolower($funcName).'.php')) {
                                    $this->nextQuickInfo[] = '<div class="upgradeDbError">[ERROR] '.$upgradeFile.' PHP - missing file '.$query.'</div>';
                                    $this->nextErrors[] = '[ERROR] '.$upgradeFile.' PHP - missing file '.$query;
                                    $warningExist = true;
                                } else {
                                    require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.strtolower($funcName).'.php');
                                    $phpRes = call_user_func_array($funcName, $parameters);
                                }
                            } /* Or an object method */
                            else {
                                $funcName = [$php[0], str_replace($pattern[0], '', $php[1])];
                                $this->nextQuickInfo[] = '<div class="upgradeDbError">[ERROR] '.$upgradeFile.' PHP - Object Method call is forbidden ( '.$php[0].'::'.str_replace($pattern[0], '', $php[1]).')</div>';
                                $this->nextErrors[] = '[ERROR] '.$upgradeFile.' PHP - Object Method call is forbidden ('.$php[0].'::'.str_replace($pattern[0], '', $php[1]).')';
                                $warningExist = true;
                            }

                            if (isset($phpRes) && (is_array($phpRes) && !empty($phpRes['error'])) || $phpRes === false) {
                                // $this->next = 'error';
                                $this->nextQuickInfo[] = '
								<div class="upgradeDbError">
									[ERROR] PHP '.$upgradeFile.' '.$query."\n".'
									'.(empty($phpRes['error']) ? '' : $phpRes['error']."\n").'
									'.(empty($phpRes['msg']) ? '' : ' - '.$phpRes['msg']."\n").'
								</div>';
                                $this->nextErrors[] = '
								[ERROR] PHP '.$upgradeFile.' '.$query."\n".'
								'.(empty($phpRes['error']) ? '' : $phpRes['error']."\n").'
								'.(empty($phpRes['msg']) ? '' : ' - '.$phpRes['msg']."\n");
                                $warningExist = true;
                            } else {
                                $this->nextQuickInfo[] = '<div class="upgradeDbOk">[OK] PHP '.$upgradeFile.' : '.$query.'</div>';
                            }
                            if (isset($phpRes)) {
                                unset($phpRes);
                            }
                        } else {
                            if (strstr($query, 'CREATE TABLE') !== false) {
                                $pattern = '/CREATE TABLE.*[`]*'._DB_PREFIX_.'([^`]*)[`]*\s\(/';
                                preg_match($pattern, $query, $matches);
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
                                $errorNumber = $this->db->getNumberError();
                                $this->nextQuickInfo[] = '
								<div class="upgradeDbError">
								[WARNING] SQL '.$upgradeFile.'
								'.$errorNumber.' in '.$query.': '.$error.'</div>';

                                $duplicates = ['1050', '1054', '1060', '1061', '1062', '1091'];
                                if (!in_array($errorNumber, $duplicates)) {
                                    $this->nextErrors[] = 'SQL '.$upgradeFile.' '.$errorNumber.' in '.$query.': '.$error;
                                    $warningExist = true;
                                }
                            } else {
                                $this->nextQuickInfo[] = '<div class="upgradeDbOk">[OK] SQL '.$upgradeFile.' '.$query.'</div>';
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

            // At this point, database upgrade is over.
            // Now we need to add all previous missing settings items, and reset cache and compile directories
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
                                static::deleteDirectory($dir.$file.DIRECTORY_SEPARATOR);
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
                    foreach (static::$classes14 as $class) {
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

                if (version_compare($this->installVersion, '1.5.4.0', '>=')) {
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
                            $langPack = Tools::jsonDecode(Tools::file_get_contents('http'.(extension_loaded('openssl') ? 's' : '').'://www.prestashop.com/download/lang_packs/get_language_pack.php?version='.$this->installVersion.'&iso_lang='.$lang['iso_code']));

                            if (!$langPack) {
                                continue;
                            } elseif ($content = Tools::file_get_contents('http'.(extension_loaded('openssl') ? 's' : '').'://translations.prestashop.com/download/lang_packs/gzip/'.$langPack->version.'/'.$lang['iso_code'].'.gzip')) {
                                $file = _PS_TRANSLATIONS_DIR_.$lang['iso_code'].'.gzip';
                                if ((bool) file_put_contents($file, $content)) {
                                    $gz = new Archive_Tar($file, true);
                                    $filesList = $gz->listContent();
                                    if (!$this->keepMails) {
                                        $files_listing = [];
                                        foreach ($filesList as $i => $file) {
                                            if (preg_match('/^mails\/'.$lang['iso_code'].'\/.*/', $file['filename'])) {
                                                unset($filesList[$i]);
                                            }
                                        }
                                        foreach ($filesList as $file) {
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

                if (version_compare($this->installVersion, '1.6.0.0', '>')) {
                    if (version_compare($this->installVersion, '1.6.1.0', '>=')) {
                        require_once(_PS_ROOT_DIR_.'/Core/Foundation/Database/Core_Foundation_Database_EntityInterface.php');
                    }

                    if (file_exists(_PS_ROOT_DIR_.'/classes/Tools.php')) {
                        require_once(_PS_ROOT_DIR_.'/classes/Tools.php');
                    }
                    if (!class_exists('Tools2', false) and class_exists('ToolsCore')) {
                        eval('class Tools2 extends ToolsCore{}');
                    }

                    if (class_exists('Tools2') && method_exists('Tools2', 'generateHtaccess')) {
                        $urlRewrite = (bool) Db::getInstance()->getvalue('SELECT `value` FROM `'._DB_PREFIX_.'configuration` WHERE name=\'PS_REWRITING_SETTINGS\'');

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

                        Tools2::generateHtaccess(null, $urlRewrite);
                    }
                }

                if (version_compare($this->installVersion, '1.6.0.2', '>')) {
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

                if (version_compare($this->installVersion, '1.6.0.0', '>') && class_exists('PrestaShopAutoload') && method_exists('PrestaShopAutoload', 'generateIndex')) {
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
                    static::deleteDirectory(_PS_CACHEFS_DIRECTORY_, false);
                    if (class_exists('CacheFs', false)) {
                        static::createCacheFsDirectories((int) $depth);
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
        $memoryLimit = ini_get('memory_limit');
        if ((substr($memoryLimit, -1) != 'G')
            && ((substr($memoryLimit, -1) == 'M' and substr($memoryLimit, 0, -1) < 128)
                || is_numeric($memoryLimit) and (intval($memoryLimit) < 131072))
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

        define('INSTALL_VERSION', $this->installVersion);
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

        $upgradeDirPhp = 'upgrade/php';
        if (!file_exists(INSTALL_PATH.DIRECTORY_SEPARATOR.$upgradeDirPhp)) {
            $upgradeDirPhp = 'php';
            if (!file_exists(INSTALL_PATH.DIRECTORY_SEPARATOR.$upgradeDirPhp)) {
                $this->next = 'error';
                $this->next_desc = INSTALL_PATH.$this->l(' directory is missing in archive or directory');
                $this->nextQuickInfo[] = INSTALL_PATH.' directory is missing in archive or directory';
                $this->nextErrors[] = INSTALL_PATH.' directory is missing in archive or directory.';

                return false;
            }
        }
        define('_PS_INSTALLER_PHP_UPGRADE_DIR_', INSTALL_PATH.DIRECTORY_SEPARATOR.$upgradeDirPhp.DIRECTORY_SEPARATOR);

        return true;
    }

    /**
     * Implement the upgrade based on upgrade.php from the downloaded archive
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function doUpgrade17()
    {
        $baseUri = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath(_PS_ROOT_DIR_));
        $hostName = $this->getServerFullBaseUrl();

        $url = $hostName.str_replace('\\', '/', $baseUri).'/'.$this->install_autoupgrade_dir.'/upgrade/upgrade.php?autoupgrade=1'.'&deactivateCustomModule=1'.'&updateDefaultTheme=1'.'&keepMails=0'.'&changeToDefaultTheme=1'.'&adminDir='.base64_encode($this->adminDir).'&idEmployee='.(int) $_COOKIE['id_employee'];

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

                if ($this->getConfig('channel') != 'directory' && file_exists($this->latestRootDir) && static::deleteDirectory($this->latestRootDir)) {
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

        static::deleteDirectory(_PS_ROOT_DIR_.'/'.$this->install_autoupgrade_dir);

        return true;
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
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

    /**
     * @return bool
     *
     * @since 1.0.0
     */
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

    /**
     * @param      $levelDepth
     * @param bool $directory
     *
     * @since 1.0.0
     */
    protected function createCacheFsDirectories($levelDepth, $directory = false)
    {
        if (!$directory) {
            if (!defined('_PS_CACHEFS_DIRECTORY_')) {
                define('_PS_CACHEFS_DIRECTORY_', $this->prodRootDir.'/cache/cachefs/');
            }
            $directory = _PS_CACHEFS_DIRECTORY_;
        }
        $chars = '0123456789abcdef';
        for ($i = 0; $i < strlen($chars); $i++) {
            $newDir = $directory.$chars[$i].'/';
            if (mkdir($newDir, 0775) && chmod($newDir, 0775) && $levelDepth - 1 > 0) {
                static::createCacheFsDirectories($levelDepth - 1, $newDir);
            }
        }
    }

    /**
     * @param $zipfile
     *
     * @return array|bool|int
     *
     * @since 1.0.0
     */
    private function listArchivedFiles($zipfile)
    {
        if (file_exists($zipfile)) {
            $res = false;
            if (!static::$forcePclZip && class_exists('ZipArchive', false)) {
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
     *
     * @since 1.0.0
     */
    public function listFilesToRemove()
    {
        $prevVersion = preg_match('#auto-backupfiles_V([0-9.]*)_#', $this->restoreFilesFilename, $matches);
        if ($prevVersion) {
            $prevVersion = $matches[1];
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
            $toRemove = $this->listFilesInDir($this->prodRootDir, 'restore', true);
        }

        $adminDir = str_replace($this->prodRootDir, '', $this->adminDir);
        // if a file in "ToRemove" has been skipped during backup,
        // just keep it
        foreach ($toRemove as $key => $file) {
            $filename = substr($file, strrpos($file, '/') + 1);
            $toRemove[$key] = preg_replace('#^/admin#', $adminDir, $file);
            // this is a really sensitive part, so we add an extra checks: preserve everything that contains "autoupgrade"
            if ($this->_skipFile($filename, $file, 'backup') || strpos($file, $this->autoupgradeDir)) {
                unset($toRemove[$key]);
            }
        }

        return $toRemove;
    }

    /**
     * @param        $dir
     * @param string $way
     * @param bool   $listDirectories
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function listFilesInDir($dir, $way = 'backup', $listDirectories = false)
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
                            $list = array_merge($list, $this->listFilesInDir($fullPath, $way, $listDirectories));
                            if ($listDirectories) {
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

    /**
     * @param       $dir
     * @param array $ignore
     *
     * @return bool
     * 
     * @since 1.0.0
     */
    public function isDirEmpty($dir, $ignore = ['.svn', '.git'])
    {
        $arrayIgnore = array_merge(['.', '..'], $ignore);
        $content = scandir($dir);
        foreach ($content as $filename) {
            if (!in_array($filename, $arrayIgnore)) {
                return false;
            }
        }

        return true;
    }

    /**
     * _listSampleFiles will make a recursive call to scandir() function
     * and list all file which match to the $fileext suffixe (this can be an extension or whole filename)
     *
     * @param string $dir     directory to look in
     * @param string $fileext suffixe filename
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function listSampleFiles($dir, $fileext = '.jpg')
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
                        $res &= $this->listSampleFiles($dir.$file, $fileext);
                    }
                }
            }
        }

        return $res;
    }

    /**
     * @param $removeList
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function removeOneSample($removeList)
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
            $adminDir = trim(str_replace($this->prodRootDir, '', $this->adminDir), DIRECTORY_SEPARATOR);
            $allowedArray['admin_au_writable'] = ConfigurationTest::test_dir($adminDir.DIRECTORY_SEPARATOR.$this->autoupgradeDir, false, $report);
            $allowedArray['shop_deactivated'] = (!\Configuration::get('PS_SHOP_ENABLE') || (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['127.0.0.1', 'localhost'])));
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
        $this->root_writable = ConfigurationTest::test_dir('/', false, $report);
        $this->root_writable_report = $report;

        return $this->root_writable;
    }

    /**
     * @return bool|mixed|string
     *
     * @since 1.0.0
     */
    public function checkAutoupgradeLastVersion()
    {
        if ($this->getModuleVersion()) {
            $this->lastAutoupgradeVersion = version_compare($this->module_version, $this->upgrader->autoupgradeLastVersion, '>=');
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
        $download = $this->downloadPath.DIRECTORY_SEPARATOR;
        $params = [
            'optChannels' => ['master', 'stable', 'beta'],
            'selectedChannel' => is_string($channel) ? $channel : 'master',
            'download' => $download,
            'channelDir' => glob($download.'*.zip'),
            'archiveFilename' => $this->getConfig('archive.filename'),
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
        $channel = $this->getConfig('channel');
        switch ($channel) {
            case 'archive':
                $upgrader->channel = 'archive';
                $upgrader->versionNum = $this->getConfig('archive.version_num');
                break;
            case 'directory':
                $upgrader->channel = 'directory';
                $upgrader->versionNum = $this->getConfig('directory.version_num');
                break;
            default:
                $upgrader->channel = $channel;
                if (Tools::getIsset('refreshCurrentVersion')) {
                    // delete the potential xml files we saved in config/xml (from last release and from current)
                    $upgrader->clearXmlMd5File(_PS_VERSION_);
                    $upgrader->clearXmlMd5File($upgrader->versionNum);
                    if ($this->getConfig('channel') == 'private' && !$this->getConfig('private_allow_major')) {
                        $upgrader->checkPSVersion(true, ['private', 'minor']);
                    } else {
                        $upgrader->checkPSVersion(true, ['minor']);
                    }

                    Tools::redirectAdmin(self::$currentIndex.'&conf=5&token='.Tools::getValue('token'));
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
            if (\Configuration::get($k) == '') {
                \Configuration::updateValue($k, $defaultValue);
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
        $array = [];

        $files = scandir($this->backupPath);

        foreach ($files as $file) {
            if ($file[0] == 'V' && is_dir($this->backupPath.DIRECTORY_SEPARATOR.$file)) {
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

    /**
     * @return string
     *
     * @since 1.0.0
     */
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

        return json_encode($return, JSON_PRETTY_PRINT);
    }
}
