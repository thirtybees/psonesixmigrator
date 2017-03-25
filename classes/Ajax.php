<?php

namespace PsOneSixMigrator;

class Ajax
{
    /**
     * @return void
     *
     * @since 1.0.0
     */
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
     * @return bool
     *
     * @since 1.0.0
     */
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
     * ends the rollback process
     *
     * @return void
     *
     * @since 1.0.0
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
     *
     * @since 1.0.0
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

        if ($this->getConfig('channel') != 'directory' && file_exists($this->latestRootDir) && static::deleteDirectory($this->latestRootDir)) {
            $this->nextQuickInfo[] = sprintf($this->l('%s removed'), $this->latestRootDir);
        } elseif (is_dir($this->latestRootDir)) {
            $this->nextQuickInfo[] = '<strong>'.sprintf($this->l('Please remove %s by FTP'), $this->latestRootDir).'</strong>';
        }
    }

    /**
     * getFilePath return the path to the zipfile containing prestashop.
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function getFilePath()
    {
        return $this->downloadPath.DIRECTORY_SEPARATOR.$this->destDownloadFilename;
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
            if (!file_exists($this->downloadPath.DIRECTORY_SEPARATOR.$file)) {
                $this->error = 1;
                $this->next_desc = sprintf($this->l('File %s does not exist. Unable to select that channel.'), $file);

                return;
            }
            if (empty($this->currentParams['archive_num'])) {
                $this->error = 1;
                $this->next_desc = sprintf($this->l('Version number is missing. Unable to select that channel.'), $file);

                return;
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

                return;
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
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessGetChannelInfo()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';

        $channel = $this->currentParams['channel'];
        $upgradeInfo = $this->getInfoForChannel($channel);
        $this->nextParams['result']['available'] = isset($upgradeInfo['available']) ? $upgradeInfo['available'] : false;

        $this->nextParams['result']['div'] = $this->divChannelInfos($upgradeInfo);
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
                $version = $this->upgrader->versionNum;
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
     * @return void
     *
     * @since 1.0.0
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

    /**
     * very first step of the upgrade process. The only thing done is the selection
     * of the next step
     *
     * @return void
     *
     * @since 1.0.0
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
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessUpgradeFiles()
    {
        $this->nextParams = $this->currentParams;

        $adminDir = str_replace($this->prodRootDir.DIRECTORY_SEPARATOR, '', $this->adminDir);
        if (file_exists($this->latestRootDir.DIRECTORY_SEPARATOR.'admin')) {
            rename($this->latestRootDir.DIRECTORY_SEPARATOR.'admin', $this->latestRootDir.DIRECTORY_SEPARATOR.$adminDir);
        } elseif (file_exists($this->latestRootDir.DIRECTORY_SEPARATOR.'admin-dev')) {
            rename($this->latestRootDir.DIRECTORY_SEPARATOR.'admin-dev', $this->latestRootDir.DIRECTORY_SEPARATOR.$adminDir);
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
            $adminDir = trim(str_replace($this->prodRootDir, '', $this->adminDir), DIRECTORY_SEPARATOR);
            $filepathListDiff = $this->autoupgradePath.DIRECTORY_SEPARATOR.$this->diffFileList;
            if (file_exists($filepathListDiff)) {
                $listFilesDiff = unserialize(base64_decode(file_get_contents($filepathListDiff)));
                // only keep list of files to delete. The modified files will be listed with _listFilesToUpgrade
                $listFilesDiff = $listFilesDiff['deleted'];
                foreach ($listFilesDiff as $k => $path) {
                    if (preg_match("#autoupgrade#", $path)) {
                        unset($listFilesDiff[$k]);
                    } else {
                        $listFilesDiff[$k] = str_replace('/'.'admin', '/'.$adminDir, $path);
                    }
                } // do not replace by DIRECTORY_SEPARATOR
            } else {
                $listFilesDiff = [];
            }

            if (!($listFilesToUpgrade = $this->_listFilesToUpgrade($this->latestRootDir))) {
                return false;
            }

            // also add files to remove
            $listFilesToUpgrade = array_merge($listFilesDiff, $listFilesToUpgrade);
            // save in a serialized array in $this->toUpgradeFileList
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toUpgradeFileList, base64_encode(serialize($listFilesToUpgrade)));
            $this->nextParams['filesToUpgrade'] = $this->toUpgradeFileList;
            $totalFilesToUpgrade = count($listFilesToUpgrade);

            if ($totalFilesToUpgrade == 0) {
                $this->nextQuickInfo[] = $this->l('[ERROR] Unable to find files to upgrade.');
                $this->nextErrors[] = $this->l('[ERROR] Unable to find files to upgrade.');
                $this->next_desc = $this->l('Unable to list files to upgrade');
                $this->next = 'error';

                return false;
            }
            $this->nextQuickInfo[] = sprintf($this->l('%s files will be upgraded.'), $totalFilesToUpgrade);

            $this->next_desc = sprintf($this->l('%s files will be upgraded.'), $totalFilesToUpgrade);
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
        for ($i = 0; $i < static::$loopUpgradeFiles; $i++) {
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
     * extract chosen version into $this->latestPath directory
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessUnzip()
    {
        $filepath = $this->getFilePath();
        $destExtract = $this->latestPath;

        if (file_exists($destExtract)) {
            static::deleteDirectory($destExtract, false);
            $this->nextQuickInfo[] = $this->l('"/latest" directory has been emptied');
        }
        $relativeExtractPath = str_replace(_PS_ROOT_DIR_, '', $destExtract);
        $report = '';
        if (ConfigurationTest::test_dir($relativeExtractPath, false, $report)) {
            if ($this->ZipExtract($filepath, $destExtract)) {
                if (version_compare($this->installVersion, '1.7.1.0', '>=')) {
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
                        $this->next_desc = sprintf($this->l('It\'s not a valid upgrade %s archive...'), $this->installVersion);

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
     * upgrade all partners modules according to the installed prestashop version
     *
     * @access public
     * @return void
     *
     * @since 1.0.0
     */
    public function ajaxProcessUpgradeModules()
    {
        $startTime = time();
        if (!isset($this->nextParams['modulesToUpgrade'])) {
            // list saved in $this->toUpgradeFileList
            $totalModulesToUpgrade = $this->_listModulesToUpgrade();
            if ($totalModulesToUpgrade) {
                $this->nextQuickInfo[] = sprintf($this->l('%s modules will be upgraded.'), $totalModulesToUpgrade);
                $this->next_desc = sprintf($this->l('%s modules will be upgraded.'), $totalModulesToUpgrade);
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

        // module list
        if (count($listModules) > 0) {
            do {
                $moduleInfo = array_shift($listModules);

                $this->upgradeThisModule($moduleInfo['id'], $moduleInfo['name']);
                $timeElapsed = time() - $startTime;
            } while (($timeElapsed < static::$loopUpgradeModulesTime) && count($listModules) > 0);

            $modulesLeft = count($listModules);
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toUpgradeModuleList, base64_encode(serialize($listModules)));
            unset($listModules);

            $this->next = 'upgradeModules';
            if ($modulesLeft) {
                $this->next_desc = sprintf($this->l('%s modules left to upgrade.'), $modulesLeft);
            }
            $this->stepDone = false;
        } else {
            if (version_compare($this->installVersion, '1.5.0.0', '>=')) {
                $modulesToDelete['backwardcompatibility'] = 'Backward Compatibility';
                $modulesToDelete['dibs'] = 'Dibs';
                $modulesToDelete['cloudcache'] = 'Cloudcache';
                $modulesToDelete['mobile_theme'] = 'The 1.4 mobile_theme';
                $modulesToDelete['trustedshops'] = 'Trustedshops';
                $modulesToDelete['dejala'] = 'Dejala';
                $modulesToDelete['stripejs'] = 'Stripejs';
                $modulesToDelete['blockvariouslinks'] = 'Block Various Links';

                foreach ($modulesToDelete as $key => $module) {
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
                        if (static::deleteDirectory($path)) {
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
     * @return bool
     *
     * @since 1.0.0
     */
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
     * Clean the database from unwanted entires
     *
     * @return void
     *
     * @since 1.0.0
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

    /**
     * @return void
     *
     * @since 1.0.0
     */
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
     *
     * @since 1.0.0
     */
    public function ajaxProcessRestoreFiles()
    {
        // loop
        $this->next = 'restoreFiles';
        if (!file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList)
            || !file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList)
        ) {
            // cleanup current PS tree
            $fromArchive = $this->listArchivedFiles($this->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename);
            foreach ($fromArchive as $k => $v) {
                $fromArchive[$k] = '/'.$v;
            }
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList, base64_encode(serialize($fromArchive)));
            // get list of files to remove
            $toRemove = $this->listFilesToRemove();
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
            for ($i = 0; $i < static::$loopRestoreFiles; $i++) {
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
                                static::deleteDirectory($file, true);
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

    /**
     * try to restore db backup file
     *
     * @return bool
     *
     * @since 1.0.0
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
        $startTime = time();
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
            $backupdbPath = $this->backupPath.DIRECTORY_SEPARATOR.$this->restoreName;

            $dotPos = strrpos($currentDbFilename, '.');
            $fileext = substr($currentDbFilename, $dotPos + 1);
            $requests = [];
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
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList, base64_encode(serialize($listQuery)));
        }
        // @todo : error if listQuery is not an array (that can happen if toRestoreQueryList is empty for example)
        $timeElapsed = time() - $startTime;
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
                            array_unshift($listQuery, $query);
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

                $timeElapsed = time() - $startTime;
            } while ($timeElapsed < static::$loopRestoreQueryTime);

            $queriesLeft = count($listQuery);

            if ($queriesLeft > 0) {
                file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList, base64_encode(serialize($listQuery)));
            } elseif (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList)) {
                unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList);
            }

            $this->stepDone = false;
            $this->next = 'restoreDb';
            $this->nextQuickInfo[] = $this->next_desc = sprintf($this->l('%1$s queries left for file %2$s...'), $queriesLeft, $this->nextParams['dbStep']);
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
        if (!$this->getConfig('PS_AUTOUP_BACKUP') && version_compare($this->upgrader->versionNum, '1.7.0.0', '<')) {
            $this->stepDone = true;
            $this->nextParams['dbStep'] = 0;
            $this->next_desc = sprintf($this->l('Database backup skipped. Now upgrading files...'), $this->backupName);
            $this->next = 'upgradeFiles';

            return true;
        }

        $relativeBackupPath = str_replace(_PS_ROOT_DIR_, '', $this->backupPath);
        $report = '';
        if (!ConfigurationTest::test_dir($relativeBackupPath, false, $report)) {
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

            if ($written == 0 || $written > static::$maxWrittenAllowed) {
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
            if (!in_array($table, $ignoreStatsTable)) {
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
                        $timeElapsed = time() - $startTime;
                    } else {
                        unset($this->nextParams['backup_table']);
                        unset($this->currentParams['backup_table']);
                        break;
                    }
                } while (($timeElapsed < static::$loopBackupDbTime) && ($written < static::$maxWrittenAllowed));
            }
            $found++;
            $timeElapsed = time() - $startTime;
            $this->nextQuickInfo[] = sprintf($this->l('%1$s table has been saved.'), $table);
        } while (($timeElapsed < static::$loopBackupDbTime) && ($written < static::$maxWrittenAllowed));

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

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessBackupFiles()
    {
        if (!$this->getConfig('PS_AUTOUP_BACKUP') && version_compare($this->upgrader->versionNum, '1.7.0.0', '<')) {
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
            $filesToBackup = $this->listFilesInDir($this->prodRootDir, 'backup', false);
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
            if (!static::$forcePclZip && class_exists('ZipArchive', false)) {
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
                $filesToAdd = [];
                $closeFlag = true;
                for ($i = 0; $i < static::$loopBackupFiles; $i++) {
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
                    if ($size < static::$maxBackupFileSize) {
                        if (isset($zipArchive) && $zipArchive) {
                            $addedToZip = $zip->addFile($file, $archiveFilename);
                            if ($addedToZip) {
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
                                $closeFlag = false;
                                break;
                            }
                        } else {
                            $filesToAdd[] = $file;
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

                if ($zipArchive && $closeFlag && is_object($zip)) {
                    $zip->close();
                } elseif (!$zipArchive) {
                    $addedToZip = $zip->add($filesToAdd, PCLZIP_OPT_REMOVE_PATH, $this->prodRootDir);
                    if ($addedToZip) {
                        $zip->privCloseFd();
                    }
                    if (!$addedToZip) {
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
     * @return bool
     *
     * @since 1.0.0
     */
    public function ajaxProcessRemoveSamples()
    {
        $this->stepDone = false;
        // remove all sample pics in img subdir
        if (!isset($this->currentParams['removeList'])) {
            $this->listSampleFiles($this->latestPath.'/prestashop/img/c', '.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img/cms', '.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img/l', '.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img/m', '.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img/os', '.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img/p', '.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img/s', '.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img/scenes', '.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img/st', '.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img/su', '.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img', '404.gif');
            $this->listSampleFiles($this->latestPath.'/prestashop/img', 'favicon.ico');
            $this->listSampleFiles($this->latestPath.'/prestashop/img', 'logo.jpg');
            $this->listSampleFiles($this->latestPath.'/prestashop/img', 'logo_stores.gif');
            $this->listSampleFiles($this->latestPath.'/prestashop/modules/editorial', 'homepage_logo.jpg');
            // remove all override present in the archive
            $this->listSampleFiles($this->latestPath.'/prestashop/override', '.php');

            if (count($this->sampleFileList) > 0) {
                $this->nextQuickInfo[] = sprintf($this->l('Starting to remove %1$s sample files'), count($this->sampleFileList));
            }
            $this->nextParams['removeList'] = $this->sampleFileList;
        }

        $resRemove = true;
        for ($i = 0; $i < static::$loopRemoveSamples; $i++) {
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
            $resRemove &= $this->removeOneSample($this->nextParams['removeList']);
            if (!$resRemove) {
                break;
            }
        }

        return $resRemove;
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
                static::deleteDirectory($this->downloadPath, false);
                $this->nextQuickInfo[] = $this->l('download directory has been emptied');
            }
            $report = '';
            $relativeDownloadPath = str_replace(_PS_ROOT_DIR_, '', $this->downloadPath);
            if (ConfigurationTest::test_dir($relativeDownloadPath, false, $report)) {
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

    /**
     * @return void
     *
     * @since 1.0.0
     */
    public function displayAjax()
    {
        echo $this->buildAjaxResult();
    }
}
