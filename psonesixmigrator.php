<?php
/**
 * Copyright (C) 2017-2019 thirty bees
 * Copyright (C) 2007-2016 PrestaShop SA
 *
 * thirty bees is an extension to the PrestaShop software by PrestaShop SA.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2019 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark of PrestaShop SA.
 */

/**
 * Don't forget: It should be possible to parse this file on PHP 5.2+, the
 * other files are allowed to be 5.5+.
 */

/**
 * Class PsOneSixMigrator
 *
 * @since 1.0.0
 */
class PsOneSixMigrator extends Module
{
    /**
     * PsOneSixMigrator constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->name = 'psonesixmigrator';
        $this->tab = 'administration';
        $this->author = 'thirty bees';
        $this->version = '2.1.1';
        $this->need_instance = 1;

        $this->bootstrap = true;
        parent::__construct();

        $this->multishop_context = Shop::CONTEXT_ALL;

        $this->displayName = $this->l('PrestaShop to thirty bees Migrator');
        $this->description = $this->l('Provides an automated way to fully migrate your PrestaShop 1.6 store to thirty bees.');

        $this->ps_versions_compliancy = array('min' => '1.6.0.0', 'max' => '1.6.999.999');
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function install()
    {
        require_once __DIR__.'/classes/UpgraderTools.php';

        /* If the "AdminThirtyBeesMigrate" tab does not exist yet, create it */
        if (!$idTab = Tab::getIdFromClassName('AdminThirtyBeesMigrate')) {
            $tab = new Tab();
            $tab->class_name = 'AdminThirtyBeesMigrate';
            $tab->module = $this->name;
            $tab->id_parent = -1;
            foreach (Language::getLanguages(false) as $lang) {
                $tab->name[(int) $lang['id_lang']] = 'thirty bees migration';
            }
            if (!$tab->save()) {
                return $this->abortInstall($this->l('Unable to create the "AdminThirtyBeesMigrate" tab'));
            }
        } else {
            $tab = new Tab((int) $idTab);
        }

        /* Update the "AdminThirtyBeesMigrate" tab id in database or exit */
        if (Validate::isLoadedObject($tab)) {
            Configuration::updateValue('PS_AUTOUPDATE_MODULE_IDTAB', (int) $tab->id);
        } else {
            return $this->abortInstall($this->l('Unable to load the "AdminThirtyBeesMigrate" tab'));
        }

        /* Check that the 1-click upgrade working directory is existing or create it */
        $autoupgradeDir = PsOneSixMigrator\UpgraderTools::getInstance()->autoupgradePath;
        if (!file_exists($autoupgradeDir) && !@mkdir($autoupgradeDir)) {
            return $this->abortInstall(sprintf($this->l('Unable to create the directory "%s"'), $autoupgradeDir));
        }

        /* Make sure that the 1-click upgrade working directory is writeable */
        if (!is_writable($autoupgradeDir)) {
            return $this->abortInstall(sprintf($this->l('Unable to write in the directory "%s"'), $autoupgradeDir));
        }

        /* If a previous version of ajax-upgradetab.php exists, delete it */
        if (file_exists($autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php')) {
            @unlink($autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php');
        }

        /* Then, try to copy the newest version from the module's directory */
        if (!@copy(dirname(__FILE__).DIRECTORY_SEPARATOR.'ajax-upgradetab.php', $autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php')) {
            return $this->abortInstall(sprintf($this->l('Unable to copy ajax-upgradetab.php in %s'), $autoupgradeDir));
        }

        /* Make sure that the XML config directory exists */
        if (!file_exists(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml') &&
            !@mkdir(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml')
        ) {
            return $this->abortInstall(sprintf($this->l('Unable to create the directory "%s"'), _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml'));
        }

        /* Create a dummy index.php file in the XML config directory to avoid directory listing */
        if (!file_exists(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR.'index.php') &&
            (file_exists(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'index.php') &&
                !@copy(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'index.php', _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR.'index.php'))
        ) {
            return $this->abortInstall(sprintf($this->l('Unable to create the directory "%s"'), _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml'));
        }

        return parent::install();
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function uninstall()
    {
        require_once __DIR__.'/classes/UpgraderTools.php';

        /* Delete the 1-click upgrade Back-office tab */
        if ($idTab = Tab::getIdFromClassName('AdminThirtyBeesMigrate')) {
            $tab = new Tab((int) $idTab);
            $tab->delete();
        }

        /* Remove the 1-click upgrade working directory */
        Tools::deleteDirectory(PsOneSixMigrator\UpgraderTools::getInstance()->autoupgradePath, true);

        Configuration::deleteByName('PS_AUTOUPDATE_MODULE_IDTAB');

        return parent::uninstall();
    }

    /**
     * Get content
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function getContent()
    {
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
            $this->context->controller->errors[] = $this->l('In order to migrate to thirty bees you need at least PHP version 5.5');
        }
        if (!extension_loaded('bcmath')) {
            $this->context->controller->errors[] = sprintf($this->l('The `%s` PHP extension needs to be installed and available in order to migrate to thirty bees'), 'bcmath');
        }
        if (!class_exists('ZipArchive')) {
            sprintf($this->l('The `%s` PHP extension needs to be installed and available in order to migrate to thirty bees'), 'zip');
        }
        if (!class_exists('PDO')) {
            $this->context->controller->errors[] = $this->l('The MySQL PDO extension needs to be installed and available in order to migrate to thirty bees');
        }
        if (is_a(Cache::getInstance(), 'CacheApc') || is_a(Cache::getInstance(), 'CacheXcache')) {
            // Disable Cache in these cases
            $settings = file_get_contents(_PS_ROOT_DIR_.'/config/settings.inc.php');
            $settings = preg_replace('/define\(\'_PS_CACHE_ENABLED_\', \'([01]?)\'\);/Ui', 'define(\'_PS_CACHE_ENABLED_\', \'0\');', $settings);
            file_put_contents(_PS_ROOT_DIR_.'/config/settings.inc.php', $settings);
        }

        if (empty($this->context->controller->errors)) {
            header('Location: '.$this->context->link->getAdminLink('AdminThirtyBeesMigrate', true));
            exit;
        }
    }

    /**
     * Set installation errors and return false
     *
     * @param string $error Installation abortion reason
     *
     * @return boolean Always false
     *
     * @since 1.0.0
     */
    protected function abortInstall($error)
    {
        $this->_errors[] = $error;

        return false;
    }
}
