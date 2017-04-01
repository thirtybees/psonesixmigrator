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
 * Class AjaxProcessor
 *
 * @package PsOneSixMigrator
 *
 * @since   1.0.0
 */
class AjaxProcessor
{
    public static $skipAction = [];
    protected static $instance;
    public $stepDone = true;
    public $status = true;
    public $backupName;
    public $backupFilesFilename;
    public $backupIgnoreFiles;
    public $backupDbFilename;
    public $restoreName;
    public $installVersion;
    public $restoreFilesFilename;
    public $restoreDbFilenames;
    public $installedLanguagesIso;
    public $modulesAddons;
    public $warningExists;
    public $error = '0';
    public $next = '';
    public $nextDesc = '';
    public $nextParams = [];
    public $nextQuickInfo = [];
    public $nextErrors = [];
    public $currentParams = [];
    public $latestRootDir;
    /** @var UpgraderTools $tools */
    public $tools;
    /** @var Upgrader $upgrader */
    public $upgrader;
    public $nextResponseType;
    /**
     * @var array theses values will be automatically added in "nextParams"
     * if their properties exists
     */
    public $ajaxParams = [
        'installVersion',
        'backupName',
        'backupFilesFilename',
        'backupDbFilename',
        'restoreName',
        'restoreFilesFilename',
        'restoreDbFilenames',
        'installedLanguagesIso',
        'modulesAddons',
        'warningExists',
    ];
    public $sampleFileList = [];
    protected $restoreIgnoreAbsoluteFiles = [];
    protected $excludeFilesFromUpgrade = [];
    protected $backupIgnoreAbsoluteFiles = [];
    protected $excludeAbsoluteFilesFromUpgrade = [];
    protected $keepImages;
    protected $restoreIgnoreFiles;
    protected $deactivateCustomModule;

    /**
     * AjaxProcessor constructor.
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        // Database instantiation (need to be cached because there will be at least 100k calls in the upgrade process
        if (class_exists('\\Db')) {
            $this->db = \Db::getInstance();
        } else {
            $this->db = Db::getInstance();
        }

        $this->action = empty($_REQUEST['action']) ? null : $_REQUEST['action'];
        $this->currentParams = empty($_REQUEST['params']) ? null : $_REQUEST['params'];

        $this->tools = UpgraderTools::getInstance();
        $this->upgrader = Upgrader::getInstance();
        $this->installVersion = $this->upgrader->version;
        $this->latestRootDir = $this->tools->latestPath.DIRECTORY_SEPARATOR.'prestashop';

        foreach ($this->ajaxParams as $prop) {
            if (property_exists($this, $prop)) {
                $this->{$prop} = isset($this->currentParams[$prop]) ? $this->currentParams[$prop] : '';
            }
        }

        // Initialize files at first step
        if ($this->action === 'upgradeNow') {
            $this->initializeFiles();
        }
    }

    /**
     * @return AjaxProcessor
     *
     * @since 1.0.0
     */
    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function optionDisplayErrors()
    {
        if (UpgraderTools::getConfig(UpgraderTools::DISPLAY_ERRORS)) {
            error_reporting(E_ALL);
            ini_set('display_errors', 'on');
        } else {
            ini_set('display_errors', 'off');
        }
    }

    /**
     * ends the rollback process
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessRollbackComplete()
    {
        $this->nextDesc = $this->l('Restoration process done. Congratulations! You can now reactivate your shop.');
        $this->next = '';
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
        $str = UpgraderTools::findTranslation('psonesixmigrator', $string, 'AdminThirtyBeesMigrateController');
        $str = $htmlentities ? str_replace('"', '&quot;', htmlentities($str, ENT_QUOTES, 'utf-8')) : $str;
        $str = $addslashes ? addslashes($str) : stripslashes($str);

        return $str;
    }

    /**
     * ends the upgrade process
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessUpgradeComplete()
    {
        if (!$this->warningExists) {
            $this->nextDesc = $this->l('Upgrade process done. Congratulations! You can now reactivate your shop.');
        } else {
            $this->nextDesc = $this->l('Upgrade process done, but some warnings have been found.');
        }
        $this->next = '';

        if (UpgraderTools::getConfig('channel') != 'archive' && file_exists($this->getCoreFilePath()) && unlink($this->getCoreFilePath())) {
            $this->nextQuickInfo[] = sprintf($this->l('%s removed'), $this->getCoreFilePath());
        } elseif (is_file($this->getCoreFilePath())) {
            $this->nextQuickInfo[] = '<strong>'.sprintf($this->l('Please remove %s by FTP'), $this->getCoreFilePath()).'</strong>';
        }

        if (UpgraderTools::getConfig('channel') != 'directory' && file_exists($this->latestRootDir) && Tools::deleteDirectory($this->latestRootDir, true)) {
            $this->nextQuickInfo[] = sprintf($this->l('%s removed'), $this->latestRootDir);
        } elseif (is_dir($this->latestRootDir)) {
            $this->nextQuickInfo[] = '<strong>'.sprintf($this->l('Please remove %s by FTP'), $this->latestRootDir).'</strong>';
        }
    }

    /**
     * getCoreFilePath return the path to the zip file containing thirty bees core.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function getCoreFilePath()
    {
        return $this->tools->downloadPath.DIRECTORY_SEPARATOR.'thirtybees-v'.$this->upgrader->version.'.zip';
    }

    /**
     * getExtraFilePath return the path to the zip file containing thirty bees core.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function getExtraFilePath()
    {
        return $this->tools->downloadPath.DIRECTORY_SEPARATOR.'thirtybees-extra-v'.$this->upgrader->version.'.zip';
    }

    /**
     * update configuration after validating the new values
     *
     * @return void
     *
     * @since 1.0.0
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
            if (!file_exists($this->tools->downloadPath.DIRECTORY_SEPARATOR.$file)) {
                $this->error = 1;
                $this->nextDesc = sprintf($this->l('File %s does not exist. Unable to select that channel.'), $file);

                return;
            }
            if (empty($this->currentParams['archive_num'])) {
                $this->error = 1;
                $this->nextDesc = sprintf($this->l('Version number is missing. Unable to select that channel.'), $file);

                return;
            }
            $config['channel'] = 'archive';
            $config['archive.filename'] = $this->currentParams['archive_prestashop'];
            $config['archive.version_num'] = $this->currentParams['archive_num'];
            // $config['archive_name'] = $this->currentParams['archive_name'];
            $this->nextDesc = $this->l('Upgrade process will use archive.');
        }
        if (isset($this->currentParams['directory_num'])) {
            $config['channel'] = 'directory';
            if (empty($this->currentParams['directory_num']) || strpos($this->currentParams['directory_num'], '.') === false) {
                $this->error = 1;
                $this->nextDesc = sprintf($this->l('Version number is missing. Unable to select that channel.'));

                return;
            }

            $config['directory.version_num'] = $this->currentParams['directory_num'];
        }
        if (isset($this->currentParams['skip_backup'])) {
            $config['skip_backup'] = $this->currentParams['skip_backup'];
        }

        if (!UpgraderTools::writeConfig($config)) {
            $this->error = 1;
            $this->nextDesc = $this->l('Error on saving configuration');
        }
    }

    /**
     * display informations related to the selected channel : link/changelog for remote channel,
     * or configuration values for special channels
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessGetChannelInfo()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';

        $channel = $this->currentParams['channel'];
        $upgrader = Upgrader::getInstance();
        $upgrader->selectedChannel = $channel;
        $upgrader->checkTbVersion(true);

        $this->nextParams['result'] = [
            'version'   => $upgrader->version,
            'channel'   => $upgrader->channel,
            'coreLink'  => $upgrader->coreLink,
            'extraLink' => $upgrader->extraLink,
            'md5Link'   => $upgrader->md5Link,
            'changelog' => $upgrader->changelogLink,
            'available' => (bool) $upgrader->version,
        ];

        UpgraderTools::setConfig('channel', $upgrader->channel);
    }

    /**
     * get the list of all modified and deleted files between current version
     * and target version (according to channel configuration)
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessCompareReleases()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';
        $channel = UpgraderTools::getConfig('channel');
        $this->upgrader = Upgrader::getInstance();
        switch ($channel) {
            case 'archive':
                $version = UpgraderTools::getConfig('archive.version_num');
                break;
            case 'directory':
                $version = UpgraderTools::getConfig('directory.version_num');
                break;
            default:
                preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
                $this->upgrader->branch = $matches[1];
                $this->upgrader->channel = $channel;

                $version = $this->upgrader->version;
        }

        $diffFileList = $this->upgrader->getDiffFilesList(_PS_VERSION_, $version);
        if (!is_array($diffFileList)) {
            $this->nextParams['status'] = 'error';
            $this->nextParams['msg'] = sprintf('Unable to generate diff file list between %1$s and %2$s.', _PS_VERSION_, $version);
        } else {
            file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::FILES_DIFF, base64_encode(serialize($diffFileList)));
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
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessCheckFilesVersion()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';
        $this->upgrader = Upgrader::getInstance();

        $changedFileList = $this->upgrader->getChangedFilesList();
        if (!is_array($changedFileList)
        ) {
            $this->nextParams['status'] = 'error';
            $this->nextParams['msg'] = $this->l('Unable to check files for the installed version of PrestaShop.');
        } else {
            $this->nextParams['status'] = 'ok';
            $testOrigCore = true;

            if (!isset($changedFileList['core'])) {
                $changedFileList['core'] = [];
            }

            if (!isset($changedFileList['translation'])) {
                $changedFileList['translation'] = [];
            }
            file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TRANSLATIONS_CUSTOM_LIST, base64_encode(serialize($changedFileList['translation'])));

            if (!isset($changedFileList['mail'])) {
                $changedFileList['mail'] = [];
            }
            file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::MAIL_CUSTOM_LIST, base64_encode(serialize($changedFileList['mail'])));

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

    /**
     * The very first step of the upgrade process.
     * The only thing done here is the selection of the next step.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessUpgradeNow()
    {
        $this->nextDesc = $this->l('Starting upgrade...');
        $this->next = 'download';
        preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);

        $this->next = 'download';
        $this->nextDesc = $this->l('Shop deactivated. Now downloading... (this can take a while)');

        $this->nextQuickInfo[] = sprintf($this->l('Archives will come from %s and %s'), $this->upgrader->coreLink, $this->upgrader->extraLink);
        $this->nextQuickInfo[] = sprintf($this->l('md5 hashes for core and extra should be resp. %s and %s'), $this->upgrader->md5Core, $this->upgrader->md5Extra);
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessUpgradeFiles()
    {
        // TODO: implement

        return true;
    }

    /**
     * extract chosen version into $this->latestPath directory
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessUnzip()
    {
        $filepath = $this->getCoreFilePath();
        $destExtract = $this->tools->latestPath;

        if (file_exists($destExtract)) {
            Tools::deleteDirectory($destExtract, false);
            $this->nextQuickInfo[] = $this->l('"/latest" directory has been emptied');
        }
        $relativeExtractPath = str_replace(_PS_ROOT_DIR_, '', $destExtract);
        $report = '';
        if (ConfigurationTest::test_dir($relativeExtractPath, false, $report)) {
            if ($this->extractZip($filepath, $destExtract)) {
                // Unsetting to force listing
                unset($this->nextParams['removeList']);
                $this->next = 'removeSamples';
                $this->nextDesc = $this->l('File extraction complete. Removing sample files...');

                return true;
            } else {
                $this->next = 'error';
                $this->nextDesc = sprintf($this->l('Unable to extract %1$s file into %2$s folder...'), $filepath, $destExtract);

                return true;
            }
        } else {
            $this->nextDesc = $this->l('Extraction directory is not writable.');
            $this->nextQuickInfo[] = $this->l('Extraction directory is not writable.');
            $this->nextErrors[] = sprintf($this->l('Extraction directory %s is not writable.'), $destExtract);
            $this->next = 'error';
        }

        return false;
    }

    /**
     * @desc  extract a zip file to the given directory
     * @return bool success
     * we need a copy of it to be able to restore without keeping Tools and Autoload stuff
     *
     * @since 1.0.0
     */
    private function extractZip($fromFile, $toDir)
    {
        if (!is_file($fromFile)) {
            $this->next = 'error';
            $this->nextQuickInfo[] = sprintf($this->l('%s is not a file'), $fromFile);
            $this->nextErrors[] = sprintf($this->l('%s is not a file'), $fromFile);

            return false;
        }

        if (!file_exists($toDir)) {
            if (!mkdir($toDir, 0777, true)) {
                $this->next = 'error';
                $this->nextQuickInfo[] = sprintf($this->l('Unable to create directory %s.'), $toDir);
                $this->nextErrors[] = sprintf($this->l('Unable to create directory %s.'), $toDir);

                return false;
            } else {
                chmod($toDir, 0777);
            }
        }

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

        return false;
    }

    /**
     * upgrade all partners modules according to the installed prestashop version
     *
     * @access public
     * @return bool
     *
     * @since  1.0.0
     */
    public function ajaxProcessUpgradeModules()
    {
        $startTime = time();
        if (!isset($this->nextParams['modulesToUpgrade'])) {
            // list saved in $this->toUpgradeFileList
            $totalModulesToUpgrade = $this->listModulesToUpgrade();
            if ($totalModulesToUpgrade) {
                $this->nextQuickInfo[] = sprintf($this->l('%s modules will be upgraded.'), $totalModulesToUpgrade);
                $this->nextDesc = sprintf($this->l('%s modules will be upgraded.'), $totalModulesToUpgrade);
            }
            $this->stepDone = false;
            $this->next = 'upgradeModules';

            return true;
        }

        $this->next = 'upgradeModules';
        if (file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.$this->nextParams['modulesToUpgrade'])) {
            $listModules = @unserialize(base64_decode(file_get_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.$this->nextParams['modulesToUpgrade'])));
        } else {
            $listModules = [];
        }

        if (!is_array($listModules)) {
            $this->next = 'upgradeComplete';
            $this->warning_exists = true;
            $this->nextDesc = $this->l('upgradeModule step has not ended correctly.');
            $this->nextQuickInfo[] = $this->l('listModules is not an array. No module has been updated.');
            $this->nextErrors[] = $this->l('listModules is not an array. No module has been updated.');

            return true;
        }

        // module list
        if (count($listModules) > 0) {
            do {
                $moduleInfo = array_shift($listModules);

                $this->upgradeThisModule($moduleInfo['id'], $moduleInfo['name']);
                $timeElapsed = time() - $startTime;
            } while (($timeElapsed < UpgraderTools::$loopUpgradeModulesTime) && count($listModules) > 0);

            $modulesLeft = count($listModules);
            file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_UPGRADE_MODULE_LIST, base64_encode(serialize($listModules)));
            unset($listModules);

            $this->next = 'upgradeModules';
            if ($modulesLeft) {
                $this->nextDesc = sprintf($this->l('%s modules left to upgrade.'), $modulesLeft);
            }
            $this->stepDone = false;
        }

        return true;
    }

    /**
     * list modules to upgrade and save them in a serialized array in $this->toUpgradeModuleList
     *
     * @return false|int Number of files found
     *
     * @since 1.0.0
     */
    public function listModulesToUpgrade()
    {
        static $list = [];

        $dir = _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules';

        if (!is_dir($dir)) {
            $this->nextQuickInfo[] = sprintf($this->l('[ERROR] %s does not exist or is not a directory.'), $dir);
            $this->nextErrors[] = sprintf($this->l('[ERROR] %s does not exist or is not a directory.'), $dir);
            $this->nextDesc = $this->l('Nothing has been extracted. It seems the unzip step has been skipped.');
            $this->next = 'error';

            return false;
        }

        $allModules = scandir($dir);
        foreach ($allModules as $moduleName) {
            if (is_file($dir.DIRECTORY_SEPARATOR.$moduleName)) {
                continue;
            } elseif (is_dir($dir.DIRECTORY_SEPARATOR.$moduleName.DIRECTORY_SEPARATOR)) {
                if (is_array($this->modulesAddons)) {
                    $idAddons = array_search($moduleName, $this->modulesAddons);
                }
                if (isset($idAddons) && $idAddons) {
                    if ($moduleName != $this->tools->autoupgradeDir) {
                        $list[] = ['id' => $idAddons, 'name' => $moduleName];
                    }
                }
            }
        }
        file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_UPGRADE_MODULE_LIST, base64_encode(serialize($list)));
        $this->nextParams['modulesToUpgrade'] = UpgraderTools::TO_UPGRADE_MODULE_LIST;

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
        $zipFullpath = $this->tools->tmpPath.DIRECTORY_SEPARATOR.$name.'.zip';

        $destExtract = _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR;

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
                    elseif ($this->extractZip($zipFullpath, $destExtract)) {
                        $this->nextQuickInfo[] = sprintf($this->l('The files of module %s have been upgraded.'), $name);
                        if (file_exists($zipFullpath)) {
                            unlink($zipFullpath);
                        }
                    } else {
                        $this->nextQuickInfo[] = sprintf($this->l('[WARNING] Error when trying to upgrade module %s.'), $name);
                        $this->warningExists = true;
                    }
                } else {
                    $this->nextQuickInfo[] = sprintf($this->l('[ERROR] Unable to write module %s\'s zip file in temporary directory.'), $name);
                    $this->nextErrors[] = sprintf($this->l('[ERROR] Unable to write module %s\'s zip file in temporary directory.'), $name);
                    $this->warningExists = true;
                }
            } else {
                $this->nextQuickInfo[] = sprintf($this->l('[ERROR] No response from Addons server.'));
                $this->nextErrors[] = sprintf($this->l('[ERROR] No response from Addons server.'));
                $this->warningExists = true;
            }
        }
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessUpgradeDb()
    {
        $this->nextParams = $this->currentParams;
        if (!$this->doUpgrade()) {
            $this->next = 'error';
            $this->nextDesc = $this->l('Error during database upgrade. You may need to restore your database.');

            return false;
        }

        if (version_compare(INSTALL_VERSION, '1.7.1.0', '<')) {
            $this->next = 'upgradeModules';
            $this->nextDesc = $this->l('Database upgraded. Now upgrading your Addons modules...');
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

        $filePrefix = 'PREFIX_';
        $engineType = 'ENGINE_TYPE';

        $mysqlEngine = (defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'MyISAM');

        //old version detection
        global $oldversion;
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
            $this->nextDesc = $this->l('Unable to find upgrade directory in the installation path.');

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
//                    $logger->logError('Error while loading SQL upgrade file.');
            }
            if (!$sqlContent = file_get_contents($file)."\n") {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->l(sprintf('Error while loading SQL upgrade file %s.', $version));
                $this->nextErrors[] = $this->l(sprintf('Error while loading sql SQL file %s.', $version));

                return false;
//                    $logger->logError(sprintf('Error while loading sql upgrade file %s.', $version));
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
            $this->nextDesc = $this->l('An error happened during the database upgrade.');

            return false;
        }

        $this->nextQuickInfo[] = $this->l('Database upgrade OK'); // no error !

        // At this point, database upgrade is over.
        // Now we need to add all previous missing settings items, and reset cache and compile directories
        $this->writeNewSettings();

        // Settings updated, compile and cache directories must be emptied
        $arrayToClean[] = _PS_ROOT_DIR_.'/tools/smarty/cache/';
        $arrayToClean[] = _PS_ROOT_DIR_.'/tools/smarty/compile/';
        $arrayToClean[] = _PS_ROOT_DIR_.'/tools/smarty_v2/cache/';
        $arrayToClean[] = _PS_ROOT_DIR_.'/tools/smarty_v2/compile/';
        if (version_compare(INSTALL_VERSION, '1.5.0.0', '>')) {
            $arrayToClean[] = _PS_ROOT_DIR_.'/cache/smarty/cache/';
            $arrayToClean[] = _PS_ROOT_DIR_.'/cache/smarty/compile/';
        }

        foreach ($arrayToClean as $dir) {
            if (!file_exists($dir)) {
                $this->nextQuickInfo[] = sprintf($this->l('[SKIP] directory "%s" does not exist and cannot be emptied.'), str_replace(_PS_ROOT_DIR_, '', $dir));
                continue;
            } else {
                foreach (scandir($dir) as $file) {
                    if ($file[0] != '.' && $file != 'index.php' && $file != '.htaccess') {
                        if (is_file($dir.$file)) {
                            unlink($dir.$file);
                        } elseif (is_dir($dir.$file.DIRECTORY_SEPARATOR)) {
                            Tools::deleteDirectory($dir.$file.DIRECTORY_SEPARATOR, true);
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
                                    $filesListing = [];
                                    foreach ($filesList as $i => $file) {
                                        if (preg_match('/^mails\/'.$lang['iso_code'].'\/.*/', $file['filename'])) {
                                            unset($filesList[$i]);
                                        }
                                    }
                                    foreach ($filesList as $file) {
                                        if (isset($file['filename']) && is_string($file['filename'])) {
                                            $filesListing[] = $file['filename'];
                                        }
                                    }
                                    if (is_array($filesListing)) {
                                        $gz->extractList($filesListing, _PS_TRANSLATIONS_DIR_.'../', '');
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
                $path = _PS_ADMIN_DIR_.DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.'default'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'header.tpl';
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

        // delete cache filesystem if activated
        if (defined('_PS_CACHE_ENABLED_') && _PS_CACHE_ENABLED_) {
            $depth = (int) $this->db->getValue(
                'SELECT value
				FROM '._DB_PREFIX_.'configuration
				WHERE name = "PS_CACHEFS_DIRECTORY_DEPTH"'
            );
            if ($depth) {
                if (!defined('_PS_CACHEFS_DIRECTORY_')) {
                    define('_PS_CACHEFS_DIRECTORY_', _PS_ROOT_DIR_.'/cache/cachefs/');
                }
                Tools::deleteDirectory(_PS_CACHEFS_DIRECTORY_, false);
                if (class_exists('CacheFs', false)) {
                    static::createCacheFsDirectories((int) $depth);
                }
            }
        }

        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="0" WHERE name = "PS_HIDE_OPTIMIZATION_TIS"', false);
        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="1" WHERE name = "PS_NEED_REBUILD_INDEX"', false);
        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="'.INSTALL_VERSION.'" WHERE name = "PS_VERSION_DB"', false);

        if ($this->next == 'error') {
            return false;
        } elseif (!empty($warningExist) || $this->warningExists) {
            $this->nextQuickInfo[] = $this->l('Warning detected during upgrade.');
            $this->nextErrors[] = $this->l('Warning detected during upgrade.');
            $this->nextDesc = $this->l('Warning detected during upgrade.');
        } else {
            $this->nextDesc = $this->l('Database upgrade completed');
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
        define('INSTALL_PATH', _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.$this->tools->autoupgradeDir);
        // 1.5 ...
        define('_PS_INSTALL_PATH_', INSTALL_PATH.DIRECTORY_SEPARATOR);
        // 1.6
        if (!defined('_PS_CORE_DIR_')) {
            define('_PS_CORE_DIR_', _PS_ROOT_DIR_);
        }

        define('PS_INSTALLATION_IN_PROGRESS', true);
        define('SETTINGS_FILE', _PS_ROOT_DIR_.'/config/settings.inc.php');
        define('DEFINES_FILE', _PS_ROOT_DIR_.'/config/defines.inc.php');
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
                $this->nextDesc = INSTALL_PATH.$this->l(' directory is missing in archive or directory');
                $this->nextQuickInfo[] = INSTALL_PATH.' directory is missing in archive or directory';
                $this->nextErrors[] = INSTALL_PATH.' directory is missing in archive or directory.';

                return false;
            }
        }
        define('_PS_INSTALLER_PHP_UPGRADE_DIR_', INSTALL_PATH.DIRECTORY_SEPARATOR.$upgradeDirPhp.DIRECTORY_SEPARATOR);

        return true;
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
        copy(SETTINGS_FILE, str_replace('.php', '.old.php', SETTINGS_FILE));
        $confFile = new AddConfToFile(SETTINGS_FILE, 'w');
        if ($confFile->error) {
            $this->next = 'error';
            $this->nextDesc = $this->l('Error when opening settings.inc.php file in write mode');
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

        $datas[] = ['_PS_DIRECTORY_', __PS_BASE_URI__];

        foreach ($datas as $data) {
            $confFile->writeInFile($data[0], $data[1]);
        }

        if ($confFile->error != false) {
            $this->next = 'error';
            $this->nextDesc = $this->l('Error when generating new settings.inc.php file.');
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
                define('_PS_CACHEFS_DIRECTORY_', _PS_ROOT_DIR_.'/cache/cachefs/');
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
     * Clean the database from unwanted entires
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessCleanDatabase()
    {
        /* Clean tabs order */
        foreach ($this->db->executeS('SELECT DISTINCT id_parent FROM '._DB_PREFIX_.'tab') as $parent) {
            $i = 1;
            foreach ($this->db->executeS('SELECT id_tab FROM '._DB_PREFIX_.'tab WHERE id_parent = '.(int) $parent['id_parent'].' ORDER BY IF(class_name IN ("AdminHome", "AdminDashboard"), 1, 2), position ASC') as $child) {
                $this->db->execute('UPDATE '._DB_PREFIX_.'tab SET position = '.(int) ($i++).' WHERE id_tab = '.(int) $child['id_tab'].' AND id_parent = '.(int) $parent['id_parent']);
            }
        }

        /* Clean configuration integrity */
        $this->db->execute('DELETE FROM `'._DB_PREFIX_.'configuration_lang` WHERE (`value` IS NULL AND `date_upd` IS NULL) OR `value` LIKE ""', false);

        $this->status = 'ok';
        $this->next = 'upgradeComplete';
        $this->nextDesc = $this->l('The database has been cleaned.');
        $this->nextQuickInfo[] = $this->l('The database has been cleaned.');
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessRollback()
    {
        // 1st, need to analyse what was wrong.
        $this->nextParams = $this->currentParams;
        $this->restoreFilesFilename = $this->restoreName;
        if (!empty($this->restoreName)) {
            $files = scandir($this->tools->backupPath);
            // find backup filenames, and be sure they exists
            foreach ($files as $file) {
                if (preg_match('#'.preg_quote('auto-backupfiles_'.$this->restoreName).'#', $file)) {
                    $this->restoreFilesFilename = $file;
                    break;
                }
            }
            if (!is_file($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename)) {
                $this->next = 'error';
                $this->nextQuickInfo[] = sprintf('[ERROR] file %s is missing : unable to restore files. Operation aborted.', $this->restoreFilesFilename);
                $this->nextErrors[] = $this->nextDesc = sprintf($this->l('[ERROR] File %s is missing: unable to restore files. Operation aborted.'), $this->restoreFilesFilename);

                return false;
            }
            $files = scandir($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->restoreName);
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
                $this->nextErrors[] = $this->nextDesc = $this->l('[ERROR] No backup database files found: it would be impossible to restore the database. Operation aborted.');

                return false;
            }

            $this->next = 'restoreFiles';
            $this->nextDesc = $this->l('Restoring files ...');
            // remove tmp files related to restoreFiles
            if (file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::FROM_ARCHIVE_FILE_LIST)) {
                unlink($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::FROM_ARCHIVE_FILE_LIST);
            }
            if (file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_REMOVE_FILE_LIST)) {
                unlink($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_REMOVE_FILE_LIST);
            }
        } else {
            $this->next = 'noRollbackFound';
        }

        return false;
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessNoRollbackFound()
    {
        $this->nextDesc = $this->l('Nothing to restore');
        $this->next = 'rollbackComplete';
    }

    /**
     * ajaxProcessRestoreFiles restore the previously saved files,
     * and delete files that weren't archived
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessRestoreFiles()
    {
        // loop
        $this->next = 'restoreFiles';
        if (!file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::FROM_ARCHIVE_FILE_LIST)
            || !file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_REMOVE_FILE_LIST)
        ) {
            // cleanup current PS tree
            $fromArchive = $this->listArchivedFiles($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename);
            foreach ($fromArchive as $k => $v) {
                $fromArchive[$k] = '/'.$v;
            }
            file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::FROM_ARCHIVE_FILE_LIST, base64_encode(serialize($fromArchive)));
            // get list of files to remove
            $toRemove = $this->listFilesToRemove();
            // let's reverse the array in order to make possible to rmdir
            // remove fullpath. This will be added later in the loop.
            // we do that for avoiding fullpath to be revealed in a text file
            foreach ($toRemove as $k => $v) {
                $toRemove[$k] = str_replace(_PS_ROOT_DIR_, '', $v);
            }

            $this->nextQuickInfo[] = sprintf($this->l('%s file(s) will be removed before restoring the backup files.'), count($toRemove));
            file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_REMOVE_FILE_LIST, base64_encode(serialize($toRemove)));

            if ($fromArchive === false || $toRemove === false) {
                if (!$fromArchive) {
                    $this->nextQuickInfo[] = sprintf($this->l('[ERROR] Backup file %s does not exist.'), UpgraderTools::FROM_ARCHIVE_FILE_LIST);
                    $this->nextErrors[] = sprintf($this->l('[ERROR] Backup file %s does not exist.'), UpgraderTools::FROM_ARCHIVE_FILE_LIST);
                }
                if (!$toRemove) {
                    $this->nextQuickInfo[] = sprintf($this->l('[ERROR] File "%s" does not exist.'), UpgraderTools::TO_REMOVE_FILE_LIST);
                    $this->nextErrors[] = sprintf($this->l('[ERROR] File "%s" does not exist.'), UpgraderTools::TO_REMOVE_FILE_LIST);
                }
                $this->nextDesc = $this->l('Unable to remove upgraded files.');
                $this->next = 'error';

                return false;
            }
        }

        // first restoreFiles step
        if (!isset($toRemove)) {
            $toRemove = unserialize(base64_decode(file_get_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_REMOVE_FILE_LIST)));
        }

        if (count($toRemove) > 0) {
            for ($i = 0; $i < UpgraderTools::$loopRestoreFiles; $i++) {
                if (count($toRemove) <= 0) {
                    $this->stepDone = true;
                    $this->status = 'ok';
                    $this->next = 'restoreFiles';
                    $this->nextDesc = $this->l('Files from upgrade has been removed.');
                    $this->nextQuickInfo[] = $this->l('Files from upgrade has been removed.');
                    file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_REMOVE_FILE_LIST, base64_encode(serialize($toRemove)));

                    return true;
                } else {
                    $filename = array_shift($toRemove);
                    $file = rtrim(_PS_ROOT_DIR_, DIRECTORY_SEPARATOR).$filename;
                    if (file_exists($file)) {
                        if (is_file($file)) {
                            @chmod($file, 0777); // NT ?
                            if (@unlink($file)) {
                                $this->nextQuickInfo[] = sprintf($this->l('%s files removed'), $filename);
                            } else {
                                $this->next = 'error';
                                $this->nextDesc = sprintf($this->l('Error when removing %1$s.'), $filename);
                                $this->nextQuickInfo[] = sprintf($this->l('File %s not removed.'), $filename);
                                $this->nextErrors[] = sprintf($this->l('File %s not removed.'), $filename);

                                return false;
                            }
                        } elseif (is_dir($file)) {
                            if ($this->isDirEmpty($file)) {
                                Tools::deleteDirectory($file, true);
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
            file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_REMOVE_FILE_LIST, base64_encode(serialize($toRemove)));
            if (count($toRemove)) {
                $this->nextDesc = sprintf($this->l('%s file(s) left to remove.'), count($toRemove));
            }
            $this->next = 'restoreFiles';

            return true;
        }

        // very second restoreFiles step : extract backup
        // if (!isset($fromArchive))
        //	$fromArchive = unserialize(base64_decode(file_get_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList)));
        $filepath = $this->tools->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename;
        $destExtract = _PS_ROOT_DIR_;
        if ($this->extractZip($filepath, $destExtract)) {
            $this->next = 'restoreDb';
            $this->nextDesc = $this->l('Files restored. Now restoring database...');
            // get new file list
            $this->nextQuickInfo[] = $this->l('Files restored.');
            // once it's restored, do not delete the archive file. This has to be done manually
            // and we do not empty the var, to avoid infinite loop.
            return true;
        } else {
            $this->next = 'error';
            $this->nextDesc = sprintf($this->l('Unable to extract file %1$s into directory %2$s .'), $filepath, $destExtract);

            return false;
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

        return false;
    }

    /**
     * this function list all files that will be remove to retrieve the filesystem states before the upgrade
     *
     * @access public
     * @return array|false
     *
     * @since  1.0.0
     */
    public function listFilesToRemove()
    {
        $prevVersion = preg_match('#auto-backupfiles_V([0-9.]*)_#', $this->restoreFilesFilename, $matches);
        if ($prevVersion) {
            $prevVersion = $matches[1];
        }

        if (!$this->upgrader) {
            $this->upgrader = Upgrader::getInstance();
        }

        $toRemove = false;
        // note : getDiffFilesList does not include files moved by upgrade scripts,
        // so this method can't be trusted to fully restore directory
        // $toRemove = $this->upgrader->getDiffFilesList(_PS_VERSION_, $prev_version, false);
        // if we can't find the diff file list corresponding to _PS_VERSION_ and prev_version,
        // let's assume to remove every files
        if (!$toRemove) {
            $toRemove = $this->listFilesInDir(_PS_ROOT_DIR_, 'restore', true);
        }

        $adminDir = str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_);
        // if a file in "ToRemove" has been skipped during backup,
        // just keep it
        foreach ($toRemove as $key => $file) {
            $filename = substr($file, strrpos($file, '/') + 1);
            $toRemove[$key] = preg_replace('#^/admin#', $adminDir, $file);
            // this is a really sensitive part, so we add an extra checks: preserve everything that contains "autoupgrade"
            if ($this->skipFile($filename, $file, 'backup') || strpos($file, $this->tools->autoupgradeDir)) {
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
                    if (!$this->skipFile($file, $fullPath, $way)) {
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
    protected function skipFile($file, $fullpath, $way = 'backup')
    {
        $fullpath = str_replace('\\', '/', $fullpath); // wamp compliant
        $rootpath = str_replace('\\', '/', _PS_ROOT_DIR_);
        $adminDir = str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_);
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
                /* If set to false, we will not upgrade/replace the "mails" directory */
                if (!UpgraderTools::getConfig('PS_AUTOUP_KEEP_MAILS')) {
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
     * try to restore db backup file
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessRestoreDb()
    {
        $this->nextParams['dbStep'] = $this->currentParams['dbStep'];
        $startTime = time();
        $listQuery = [];

        // deal with running backup rest if exist
        if (file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST)) {
            $listQuery = unserialize(base64_decode(file_get_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST)));
        }

        // deal with the next files stored in restoreDbFilenames
        if (empty($listQuery) && is_array($this->restoreDbFilenames) && count($this->restoreDbFilenames) > 0) {
            $currentDbFilename = array_shift($this->restoreDbFilenames);
            if (!preg_match('#auto-backupdb_([0-9]{6})_#', $currentDbFilename, $match)) {
                $this->next = 'error';
                $this->error = 1;
                $this->nextQuickInfo[] = $this->nextDesc = $this->l(sprintf('%s: File format does not match.', $currentDbFilename));

                return false;
            }
            $this->nextParams['dbStep'] = $match[1];
            $backupdbPath = $this->tools->backupPath.DIRECTORY_SEPARATOR.$this->restoreName;

            $dotPos = strrpos($currentDbFilename, '.');
            $fileext = substr($currentDbFilename, $dotPos + 1);
            $content = '';

            $this->nextQuickInfo[] = $this->l(sprintf('Opening backup database file %1s in %2s mode', $currentDbFilename, $fileext));

            switch ($fileext) {
                case 'bz':
                case 'bz2':
                    if ($fp = bzopen($backupdbPath.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= bzread($fp, 4096);
                        }
                    } else {
                        die("error when trying to open in bzmode");
                    } // @todo : handle error
                    break;
                case 'gz':
                    if ($fp = gzopen($backupdbPath.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= gzread($fp, 4096);
                        }
                    }
                    gzclose($fp);
                    break;
                default:
                    if ($fp = fopen($backupdbPath.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= fread($fp, 4096);
                        }
                    }
                    fclose($fp);
            }

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
                $allTables = $this->db->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'%"', true, false);
                $drops = [];
                foreach ($allTables as $k => $v) {
                    $table = array_shift($v);
                    $drops['drop table '.$k] = 'DROP TABLE IF EXISTS `'.bqSql($table).'`';
                    $drops['drop view '.$k] = 'DROP VIEW IF EXISTS `'.bqSql($table).'`';
                }
                unset($allTables);
                $listQuery = array_merge($drops, $listQuery);
            }
            file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST, base64_encode(serialize($listQuery)));
        }
        if (is_array($listQuery) && (count($listQuery) > 0)) {
            do {
                if (count($listQuery) == 0) {
                    if (file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST)) {
                        unlink($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST);
                    }

                    if (count($this->restoreDbFilenames)) {
                        $this->nextDesc = sprintf($this->l('Database restoration file %1$s done. %2$s file(s) left...'), $this->nextParams['dbStep'], count($this->restoreDbFilenames));
                    } else {
                        $this->nextDesc = sprintf($this->l('Database restoration file %1$s done.'), $this->nextParams['dbStep']);
                    }

                    $this->nextQuickInfo[] = $this->nextDesc;
                    $this->stepDone = true;
                    $this->status = 'ok';
                    $this->next = 'restoreDb';
                    if (count($this->restoreDbFilenames) == 0) {
                        $this->next = 'rollbackComplete';
                        $this->nextQuickInfo[] = $this->nextDesc = $this->l('Database has been restored.');
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
                            array_unshift($listQuery, $query);
                        }
                        $this->nextErrors[] = $this->l('[SQL ERROR] ').$query.' - '.$this->db->getMsgError();
                        $this->nextQuickInfo[] = $this->l('[SQL ERROR] ').$query.' - '.$this->db->getMsgError();
                        $this->next = 'error';
                        $this->error = 1;
                        $this->nextDesc = $this->l('Error during database restoration');
                        unlink($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST);

                        return false;
                    }
                }

                $timeElapsed = time() - $startTime;
            } while ($timeElapsed < UpgraderTools::$loopRestoreQueryTime);

            $queriesLeft = count($listQuery);

            if ($queriesLeft > 0) {
                file_put_contents($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST, base64_encode(serialize($listQuery)));
            } elseif (file_exists($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST)) {
                unlink($this->tools->autoupgradePath.DIRECTORY_SEPARATOR.UpgraderTools::TO_RESTORE_QUERY_LIST);
            }

            $this->stepDone = false;
            $this->next = 'restoreDb';
            $this->nextQuickInfo[] = $this->nextDesc = sprintf($this->l('%1$s queries left for file %2$s...'), $queriesLeft, $this->nextParams['dbStep']);
            unset($query);
            unset($listQuery);
        } else {
            $this->stepDone = true;
            $this->status = 'ok';
            $this->next = 'rollbackComplete';
            $this->nextQuickInfo[] = $this->nextDesc = $this->l('Database restoration done.');
        }

        return true;
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessMergeTranslations()
    {
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessBackupDb()
    {
        if (!UpgraderTools::getConfig(UpgraderTools::BACKUP)) {
            $this->stepDone = true;
            $this->nextParams['dbStep'] = 0;
            $this->nextDesc = sprintf($this->l('Database backup skipped. Now upgrading files...'), $this->backupName);
            $this->next = 'upgradeFiles';

            return true;
        }

        $relativeBackupPath = str_replace(_PS_ROOT_DIR_, '', $this->tools->backupPath);
        $report = '';
        if (!ConfigurationTest::test_dir($relativeBackupPath, false, $report)) {
            $this->nextDesc = $this->l('Backup directory is not writable ');
            $this->nextQuickInfo[] = 'Backup directory is not writable ';
            $this->nextErrors[] = 'Backup directory is not writable "'.$this->tools->backupPath.'"';
            $this->next = 'error';
            $this->error = 1;

            return false;
        }

        $this->stepDone = false;
        $this->next = 'backupDb';
        $this->nextParams = $this->currentParams;
        $startTime = time();

        $psBackupAll = true;
        $psBackupDropTable = true;
        if (!$psBackupAll) {
            $ignoreStatsTable = [
                _DB_PREFIX_.'connections',
                _DB_PREFIX_.'connections_page',
                _DB_PREFIX_.'connections_source',
                _DB_PREFIX_.'guest',
                _DB_PREFIX_.'statssearch',
            ];
        } else {
            $ignoreStatsTable = [];
        }

        // INIT LOOP
        if (!isset($this->nextParams['tablesToBackup']) || empty($this->nextParams['tablesToBackup'])) {
            $this->nextParams['dbStep'] = 0;
            $tablesToBackup = $this->db->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'%"', true, false);
            $this->nextParams['tablesToBackup'] = $tablesToBackup;
        }

        if (!isset($tablesToBackup)) {
            $tablesToBackup = $this->nextParams['tablesToBackup'];
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

            if ($written == 0 || $written > UpgraderTools::$maxWrittenAllowed) {
                // increment dbStep will increment filename each time here
                $this->nextParams['dbStep']++;
                // new file, new step
                $written = 0;
                if (isset($fp)) {
                    fclose($fp);
                }
                $backupfile = $this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupDbFilename;
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
                    $this->nextDesc = $this->l('Error during database backup.');

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
            if (empty($this->currentParams['backup_table']) && isset($fp)) {
                // Export the table schema
                $schema = $this->db->executeS('SHOW CREATE TABLE `'.$table.'`', true, false);

                if (count($schema) != 1 ||
                    !((isset($schema[0]['Table']) && isset($schema[0]['Create Table']))
                        || (isset($schema[0]['View']) && isset($schema[0]['Create View'])))
                ) {
                    fclose($fp);
                    if (isset($backupfile) && file_exists($backupfile)) {
                        unlink($backupfile);
                    }
                    $this->nextErrors[] = sprintf($this->l('An error occurred while backing up. Unable to obtain the schema of %s'), $table);
                    $this->nextQuickInfo[] = sprintf($this->l('An error occurred while backing up. Unable to obtain the schema of %s'), $table);
                    $this->next = 'error';
                    $this->error = 1;
                    $this->nextDesc = $this->l('Error during database backup.');

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
                    $ignoreStatsTable[] = $schema[0]['View'];
                } // case table
                elseif (isset($schema[0]['Table'])) {
                    // Case common table
                    $written += fwrite($fp, '/* Scheme for table '.$schema[0]['Table']." */\n");
                    if ($psBackupDropTable && !in_array($schema[0]['Table'], $ignoreStatsTable)) {
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
            if (!in_array($table, $ignoreStatsTable) && isset($fp)) {
                do {
                    $backupLoopLimit = $this->nextParams['backup_loop_limit'];
                    $data = $this->db->executeS('SELECT * FROM `'.$table.'` LIMIT '.(int) $backupLoopLimit.',200', false, false);
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
                                } elseif (isset($lines)) {
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
                        $timeElapsed = time() - $startTime;
                    } else {
                        unset($this->nextParams['backup_table']);
                        unset($this->currentParams['backup_table']);
                        break;
                    }
                } while (($timeElapsed < UpgraderTools::$loopBackupDbTime) && ($written < UpgraderTools::$maxWrittenAllowed));
            }
            $found++;
            $timeElapsed = time() - $startTime;
            $this->nextQuickInfo[] = sprintf($this->l('%1$s table has been saved.'), $table);
        } while (($timeElapsed < UpgraderTools::$loopBackupDbTime) && ($written < UpgraderTools::$maxWrittenAllowed));

        // end of loop
        if (isset($fp)) {
            fclose($fp);
            unset($fp);
        }

        $this->nextParams['tablesToBackup'] = $tablesToBackup;

        if (count($tablesToBackup) > 0) {
            $this->nextQuickInfo[] = sprintf($this->l('%1$s tables have been saved.'), $found);
            $this->next = 'backupDb';
            $this->stepDone = false;
            if (count($tablesToBackup)) {
                $this->nextDesc = sprintf($this->l('Database backup: %s table(s) left...'), count($tablesToBackup));
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
            $this->nextDesc = sprintf($this->l('Error during database backup for file %s.'), $backupfile);

            return false;
        } else {
            unset($this->nextParams['backup_loop_limit']);
            unset($this->nextParams['backup_lines']);
            unset($this->nextParams['backup_table']);
            unset($this->nextParams['tablesToBackup']);
            if ($found) {
                $this->nextQuickInfo[] = sprintf($this->l('%1$s tables have been saved.'), $found);
            }
            $this->stepDone = true;
            // reset dbStep at the end of this step
            $this->nextParams['dbStep'] = 0;

            $this->nextDesc = sprintf($this->l('Database backup done in filename %s. Now upgrading files...'), $this->backupName);
            $this->next = 'upgradeFiles';

            return true;
        }
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessBackupFiles()
    {
        if (!UpgraderTools::getConfig(UpgraderTools::BACKUP)) {
            $this->stepDone = true;
            $this->next = 'backupDb';
            $this->nextDesc = 'File backup skipped.';

            return true;
        }

        $this->nextParams = $this->currentParams;
        $this->stepDone = false;
        if (empty($this->backupFilesFilename)) {
            $this->next = 'error';
            $this->error = 1;
            $this->nextDesc = $this->l('Error during backupFiles');
            $this->nextErrors[] = $this->l('[ERROR] backupFiles filename has not been set');
            $this->nextQuickInfo[] = $this->l('[ERROR] backupFiles filename has not been set');

            return false;
        }

        if (empty($this->nextParams['filesForBackup'])) {
            // @todo : only add files and dir listed in "originalPrestashopVersion" list
            $filesForBackup = $this->listFilesInDir(_PS_ROOT_DIR_, 'backup', false);
            if (count($filesForBackup)) {
                $this->nextQuickInfo[] = sprintf($this->l('%s Files to backup.'), count($filesForBackup));
            }
            $this->nextParams['filesForBackup'] = $filesForBackup;

            // delete old backup, create new
            if (!empty($this->backupFilesFilename) && file_exists($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename)) {
                unlink($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
            }

            $this->nextQuickInfo[] = sprintf($this->l('backup files initialized in %s'), $this->backupFilesFilename);
        }
        $filesForBackup = $this->nextParams['filesForBackup'];

        $this->next = 'backupFiles';
        if (count($filesForBackup)) {
            $this->nextDesc = sprintf($this->l('Backup files in progress. %d files left'), count($filesForBackup));
        }
        if (is_array($filesForBackup)) {
            $this->nextQuickInfo[] = $this->l('Using class ZipArchive...');
            $zipArchive = true;
            $zip = new \ZipArchive();
            $res = $zip->open($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename, \ZipArchive::CREATE);
            if ($res) {
                $res = (isset($zip->filename) && $zip->filename) ? true : false;
            }

            if ($zip && $res) {
                $this->next = 'backupFiles';
                $this->stepDone = false;
                $filesToAdd = [];
                $closeFlag = true;
                for ($i = 0; $i < UpgraderTools::$loopBackupFiles; $i++) {
                    if (count($filesForBackup) <= 0) {
                        $this->stepDone = true;
                        $this->status = 'ok';
                        $this->next = 'backupDb';
                        $this->nextDesc = $this->l('All files saved. Now backing up database');
                        $this->nextQuickInfo[] = $this->l('All files have been added to archive.', 'AdminThirtyBeesMigrate', true);
                        break;
                    }
                    // filesForBackup already contains all the correct files
                    $file = array_shift($filesForBackup);

                    $archiveFilename = ltrim(str_replace(_PS_ROOT_DIR_, '', $file), DIRECTORY_SEPARATOR);
                    $size = filesize($file);
                    if ($size < UpgraderTools::$maxBackupFileSize) {
                        if (isset($zipArchive) && $zipArchive) {
                            $addedToZip = $zip->addFile($file, $archiveFilename);
                            if ($addedToZip) {
                                if ($filesForBackup) {
                                    $this->nextQuickInfo[] = sprintf($this->l('%1$s added to archive. %2$s files left.', 'AdminThirtyBeesMigrate', true), $archiveFilename, count($filesForBackup));
                                }
                            } else {
                                // if an error occur, it's more safe to delete the corrupted backup
                                $zip->close();
                                if (file_exists($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename)) {
                                    unlink($this->tools->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
                                }
                                $this->next = 'error';
                                $this->error = 1;
                                $this->nextDesc = sprintf($this->l('Error when trying to add %1$s to archive %2$s.', 'AdminThirtyBeesMigrate', true), $file, $archiveFilename);
                                $closeFlag = false;
                                break;
                            }
                        } else {
                            $filesToAdd[] = $file;
                            if (count($filesForBackup)) {
                                $this->nextQuickInfo[] = sprintf($this->l('File %1$s (size: %3$s) added to archive. %2$s files left.', 'AdminThirtyBeesMigrate', true), $archiveFilename, count($filesForBackup), $size);
                            } else {
                                $this->nextQuickInfo[] = sprintf($this->l('File %1$s (size: %2$s) added to archive.', 'AdminThirtyBeesMigrate', true), $archiveFilename, $size);
                            }
                        }
                    } else {
                        $this->nextQuickInfo[] = sprintf($this->l('File %1$s (size: %2$s) has been skipped during backup.', 'AdminThirtyBeesMigrate', true), $archiveFilename, $size);
                        $this->nextErrors[] = sprintf($this->l('File %1$s (size: %2$s) has been skipped during backup.', 'AdminThirtyBeesMigrate', true), $archiveFilename, $size);
                    }
                }

                if ($zipArchive && $closeFlag && is_object($zip)) {
                    $zip->close();
                }

                $this->nextParams['filesForBackup'] = $filesForBackup;

                return true;
            } else {
                $this->next = 'error';
                $this->nextDesc = $this->l('unable to open archive');

                return false;
            }
        } else {
            $this->stepDone = true;
            $this->next = 'backupDb';
            $this->nextDesc = $this->l('All files saved. Now backing up database.');

            return true;
        }
    }

    /**
     * Remove all sample files.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessRemoveSamples()
    {
        $this->stepDone = false;
        // remove all sample pics in img subdir
        if (!isset($this->currentParams['removeList'])) {
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img/c', '.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img/cms', '.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img/l', '.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img/m', '.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img/os', '.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img/p', '.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img/s', '.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img/scenes', '.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img/st', '.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img/su', '.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img', '404.gif');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img', 'favicon.ico');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img', 'logo.jpg');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/img', 'logo_stores.gif');
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/modules/editorial', 'homepage_logo.jpg');
            // remove all override present in the archive
            $this->listSampleFiles($this->tools->latestPath.'/prestashop/override', '.php');

            if (count($this->sampleFileList) > 0) {
                $this->nextQuickInfo[] = sprintf($this->l('Starting to remove %1$s sample files'), count($this->sampleFileList));
            }
            $this->nextParams['removeList'] = $this->sampleFileList;
        }

        $resRemove = true;
        for ($i = 0; $i < UpgraderTools::$loopRemoveSamples; $i++) {
            if (count($this->nextParams['removeList']) <= 0) {
                $this->stepDone = true;
                if (UpgraderTools::getConfig('skip_backup')) {
                    $this->next = 'upgradeFiles';
                    $this->nextDesc = $this->l('All sample files removed. Backup process skipped. Now upgrading files.');
                } else {
                    $this->next = 'backupFiles';
                    $this->nextDesc = $this->l('All sample files removed. Now backing up files.');
                }

                // break the loop, all sample already removed
                return true;
            }
            $resRemove &= $this->removeOneSample($this->nextParams['removeList']);
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
                $item = str_replace(_PS_ROOT_DIR_, '', array_shift($removeList));
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
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessDownload()
    {
        if (ConfigurationTest::test_fopen() || ConfigurationTest::test_curl()) {
            if (!is_object($this->upgrader)) {
                $this->upgrader = Upgrader::getInstance();
            }
            // regex optimization
            preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);

            $this->nextQuickInfo[] = sprintf($this->l('Downloading from %s and %s'), $this->upgrader->coreLink, $this->upgrader->extraLink);
            $this->nextQuickInfo[] = sprintf($this->l('Files will be saved to %s and %s'), $this->getCoreFilePath());
            if (file_exists($this->tools->downloadPath)) {
                Tools::deleteDirectory($this->tools->downloadPath, false);
                $this->nextQuickInfo[] = $this->l('Download directory has been cleared');
            }
            $report = '';
            $relativeDownloadPath = str_replace(_PS_ROOT_DIR_, '', $this->tools->downloadPath);
            if (ConfigurationTest::test_dir($relativeDownloadPath, false, $report)) {
                $res = $this->upgrader->downloadLast($this->tools->downloadPath);
                if ($res) {
                    // FIXME: also check extra package
                    $md5CoreFile = md5_file(realpath($this->tools->downloadPath).DIRECTORY_SEPARATOR."thirtybees-v{$this->upgrader->version}.zip");
                    $md5ExtraFile = md5_file(realpath($this->tools->downloadPath).DIRECTORY_SEPARATOR."thirtybees-extra-v{$this->upgrader->version}.zip");
                    if ($md5CoreFile === $this->upgrader->md5Core && $md5ExtraFile === $this->upgrader->md5Extra) {
                        $this->nextQuickInfo[] = $this->l('Download complete.');
                        $this->next = 'unzip';
                        $this->nextDesc = $this->l('Download complete. Now extracting...');
                    } else {
                        if ($md5CoreFile !== $this->upgrader->md5Core) {
                            $this->nextQuickInfo[] = sprintf($this->l('Download complete but the md5 sum of the core package does not match (%s).'), $md5CoreFile);
                            $this->nextErrors[] = sprintf($this->l('Download complete but md5 the sum of the core package does not match (%s).'), $md5CoreFile);
                        }
                        if ($md5ExtraFile !== $this->upgrader->md5Extra) {
                            $this->nextQuickInfo[] = sprintf($this->l('Download complete but md5 sum of the library package does not match (%s).'), $md5ExtraFile);
                            $this->nextErrors[] = sprintf($this->l('Download complete but md5 sum the library package does not match (%s).'), $md5ExtraFile);
                        }

                        $this->next = 'error';
                        $this->nextDesc = $this->l('Download complete but the md5 sums do not match. Operation aborted.');
                    }
                } else {
                    $this->nextDesc = $this->l('Error during download');
                    $this->nextQuickInfo[] = $this->l('Error during download');
                    $this->nextErrors[] = $this->l('Error during download');

                    $this->next = 'error';
                }
            } else {
                $this->nextDesc = $this->l('Download directory is not writable.');
                $this->nextQuickInfo[] = $this->l('Download directory is not writable.');
                $this->nextErrors[] = sprintf($this->l('Download directory %s is not writable.'), $this->tools->downloadPath);
                $this->next = 'error';
            }
        } else {
            // FIXME: make sure the user downloads all files
            $this->nextQuickInfo[] = $this->l('You need allow_url_fopen or cURL enabled for automatic download to work.');
            $this->nextErrors[] = $this->l('You need allow_url_fopen or cURL enabled for automatic download to work.');
            $this->next = 'error';
            $this->nextDesc = sprintf($this->l('You need allow_url_fopen or cURL enabled for automatic download to work. You can also manually upload it in filepath %s.'), $this->getCoreFilePath());
        }
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
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
            if (isset(static::$skipAction[$action])) {
                $this->next = static::$skipAction[$action];
                $this->nextQuickInfo[] = $this->nextDesc = sprintf($this->l('action %s skipped'), $action);
                unset($_POST['action']);
            } elseif (!method_exists(get_class($this), 'ajaxProcess'.$action)) {
                $this->nextDesc = sprintf($this->l('action "%1$s" not found'), $action);
                $this->next = 'error';
                $this->error = '1';
            }
        }

        if (!method_exists('Tools', 'apacheModExists') || Tools::apacheModExists('evasive')) {
            sleep(1);
        }
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function displayAjax()
    {
        die($this->buildAjaxResult());
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function buildAjaxResult()
    {
        $return = [];

        $return['error'] = (bool) $this->error;
        $return['stepDone'] = $this->stepDone;
        $return['next'] = $this->next;
        $return['status'] = $this->next == 'error' ? 'error' : 'ok';
        $return['nextDesc'] = $this->nextDesc;

        $this->nextParams['config'] = UpgraderTools::getConfig();

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

    /**
     * list files to upgrade and return it as array
     *
     * @param string $dir
     *
     * @return false|array number of files found
     *
     * @since 1.0.0
     */
    public function listFilesToUpgrade($dir)
    {
        static $list = [];
        if (!is_dir($dir)) {
            $this->nextQuickInfo[] = sprintf($this->l('[ERROR] %s does not exist or is not a directory.'), $dir);
            $this->nextErrors[] = sprintf($this->l('[ERROR] %s does not exist or is not a directory.'), $dir);
            $this->nextDesc = $this->l('Nothing has been extracted. It seems the unzipping step has been skipped.');
            $this->next = 'error';

            return false;
        }

        $allFiles = scandir($dir);
        foreach ($allFiles as $file) {
            $fullPath = $dir.DIRECTORY_SEPARATOR.$file;

            if (!$this->skipFile($file, $fullPath, "upgrade")) {
                $list[] = str_replace($this->latestRootDir, '', $fullPath);
                // if is_dir, we will create it :)
                if (is_dir($fullPath)) {
                    if (strpos($dir.DIRECTORY_SEPARATOR.$file, 'install') === false || strpos($dir.DIRECTORY_SEPARATOR.$file, 'modules') !== false) {
                        $this->listFilesToUpgrade($fullPath);
                    }
                }
            }
        }

        return $list;
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

        if ($this->skipFile($file, $dest, 'upgrade')) {
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
                        $this->nextErrors[] = $this->nextDesc = sprintf($this->l('Error while creating directory %s.'), $dest);

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
                    $this->nextErrors[] = $this->nextDesc = sprintf($this->l('Error while copying file %1$s'), $file);

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
                    Tools::deleteDirectory($dest, true);
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
     * @since  1.0.0
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
     * @since  1.0.0
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
     * @since  1.0.0
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

    protected function initializeFiles()
    {
        // installedLanguagesIso is used to merge translations files
        $isoIds = \Language::getIsoIds(false);
        foreach ($isoIds as $v) {
            $this->installedLanguagesIso[] = $v['iso_code'];
        }
        $this->installedLanguagesIso = array_unique($this->installedLanguagesIso);

        $rand = dechex(mt_rand(0, min(0xffffffff, mt_getrandmax())));
        $date = date('Ymd-His');
        $version = _PS_VERSION_;
        $this->backupName = "v{$version}_{$date}-{$rand}";
        $this->backupFilesFilename = 'auto-backupfiles_'.$this->backupName.'.zip';
        $this->backupDbFilename = 'auto-backupdb_XXXXXX_'.$this->backupName.'.sql';

        $this->keepImages = UpgraderTools::getConfig(UpgraderTools::BACKUP_IMAGES);
        $this->keepMails = UpgraderTools::getConfig(UpgraderTools::KEEP_MAILS);
        $this->manualMode = (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) ? (bool) UpgraderTools::getConfig(UpgraderTools::MANUAL_MODE) : false;
        $this->deactivateCustomModule = UpgraderTools::getConfig(UpgraderTools::DISABLE_CUSTOM_MODULES);

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
        $this->backupIgnoreFiles[] = $this->tools->autoupgradeDir;

        $this->excludeFilesFromUpgrade[] = '.';
        $this->excludeFilesFromUpgrade[] = '..';
        $this->excludeFilesFromUpgrade[] = '.svn';
        $this->excludeFilesFromUpgrade[] = '.git';
        // do not copy install, neither settings.inc.php in case it would be present
        $this->excludeAbsoluteFilesFromUpgrade[] = '/config/settings.inc.php';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/install';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/'.$this->tools->autoupgradeDir.'/index_cli.php';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/install-dev';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/config/modules_list.xml';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/config/xml/modules_list.xml';
        // this will exclude autoupgrade dir from admin, and autoupgrade from modules
        $this->excludeFilesFromUpgrade[] = $this->tools->autoupgradeDir;

        if ($this->keepImages === '0') {
            $this->backupIgnoreAbsoluteFiles[] = '/img';
            $this->restoreIgnoreAbsoluteFiles[] = '/img';
        } else {
            $this->backupIgnoreAbsoluteFiles[] = '/img/tmp';
            $this->restoreIgnoreAbsoluteFiles[] = '/img/tmp';
        }

        $this->excludeAbsoluteFilesFromUpgrade[] = '/themes/default-bootstrap';
    }

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function cleanTmpFiles()
    {
        $tools = UpgraderTools::getInstance();
        $tmpFiles = [
            UpgraderTools::TO_UPGRADE_QUERIES_LIST,
            UpgraderTools::TO_UPGRADE_FILE_LIST,
            UpgraderTools::TO_UPGRADE_MODULE_LIST,
            UpgraderTools::FILES_DIFF,
            UpgraderTools::TO_BACKUP_FILE_LIST,
            UpgraderTools::TO_BACKUP_DB_LIST,
            UpgraderTools::TO_RESTORE_QUERY_LIST,
            UpgraderTools::TO_REMOVE_FILE_LIST,
            UpgraderTools::FROM_ARCHIVE_FILE_LIST,
            UpgraderTools::MAIL_CUSTOM_LIST,
            UpgraderTools::TRANSLATIONS_CUSTOM_LIST,
        ];
        foreach ($tmpFiles as $tmpFile) {
            if (file_exists($tools->autoupgradePath.DIRECTORY_SEPARATOR.$tmpFile)) {
                unlink($tools->autoupgradePath.DIRECTORY_SEPARATOR.$tmpFile);
            }
        }
    }

    /**
     * Verify thirty bees token
     *
     * @return bool
     */
    public function verifyToken()
    {
        if (isset($_SERVER['HTTP_X_THIRTYBEES_AUTH'])) {
            $ajaxToken = $_SERVER['HTTP_X_THIRTYBEES_AUTH'];
        } elseif (isset($_POST['ajaxToken'])) {
            $ajaxToken = $_POST['ajaxToken'];
        } else {
            return false;
        }

        $blowfish = new Blowfish(_COOKIE_KEY_, _COOKIE_IV_);

        $tokenShouldBe = $blowfish->encrypt('thirtybees1337H4ck0rzz');

        return $tokenShouldBe && $ajaxToken === $tokenShouldBe;
    }
}