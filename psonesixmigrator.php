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
 * PrestaShop is an internationally registered trademark & property of PrestaShop SA
 *
 *
 * Don't forget: It should be possible to parse this file on PHP 5.2+, the other files are allowed to be 5.5+
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
        $this->version = '1.0.0';
        $this->need_instance = 1;

        $this->bootstrap = true;
        parent::__construct();

        $this->multishop_context = Shop::CONTEXT_ALL;

        $this->displayName = $this->l('1-Click Upgrade');
        $this->description = $this->l('Provides an automated method to upgrade your shop to the latest version of PrestaShop.');

        $this->ps_versions_compliancy = array('min' => '1.6.0.0', 'max' => '1.6.999.999');
    }

    /**
     * @return bool
     *
     * @since 1.0.0
     */
    public function install()
    {
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
        $autoupgradeDir = _PS_ADMIN_DIR_.DIRECTORY_SEPARATOR.'autoupgrade';
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
            !@mkdir(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml', 0775)
        ) {
            return $this->abortInstall(sprintf($this->l('Unable to create the directory "%s"'), _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml'));
        } else {
            @chmod(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml', 0775);
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
        /* Delete the 1-click upgrade Back-office tab */
        if ($idTab = Tab::getIdFromClassName('AdminThirtyBeesMigrate')) {
            $tab = new Tab((int) $idTab);
            $tab->delete();
        }

        /* Remove the 1-click upgrade working directory */
        static::removeDirectory(_PS_ADMIN_DIR_.DIRECTORY_SEPARATOR.'autoupgrade');

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
        header('Location: index.php?tab=AdminThirtyBeesMigrate&token='.md5(pSQL(_COOKIE_KEY_.'AdminThirtyBeesMigrate'.(int) Tab::getIdFromClassName('AdminThirtyBeesMigrate').(int) Context::getContext()->employee->id)));
        exit;
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

    /**
     * @param string $dir
     *
     * @since 1.00
     */
    protected static function removeDirectory($dir)
    {
        if ($handle = @opendir($dir)) {
            while (false !== ($entry = @readdir($handle))) {
                if ($entry != '.' && $entry != '..') {
                    if (is_dir($dir.DIRECTORY_SEPARATOR.$entry) === true) {
                        static::removeDirectory($dir.DIRECTORY_SEPARATOR.$entry);
                    } else {
                        @unlink($dir.DIRECTORY_SEPARATOR.$entry);
                    }
                }
            }

            @closedir($handle);
            @rmdir($dir);
        }
    }
}
