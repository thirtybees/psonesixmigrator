<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2018 thirty bees
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
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2018 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

namespace PsOneSixMigrator;

/**
 * Class Language
 *
 * @since 1.0.0
 */
class Language extends ObjectModel
{
    // @codingStandardsIgnoreStart
    /** @var array Languages cache */
    protected static $_checkedLangs;
    protected static $_LANGUAGES;
    protected static $countActiveLanguages = [];
    protected static $_cache_language_installation = null;
    /** @var string Name */
    public $name;
    /** @var string 2-letter iso code */
    public $iso_code;
    /** @var string 5-letter iso code */
    public $language_code;
    /** @var string date format http://http://php.net/manual/en/function.date.php with the date only */
    public $date_format_lite = 'Y-m-d';
    /** @var string date format http://http://php.net/manual/en/function.date.php with hours and minutes */
    public $date_format_full = 'Y-m-d H:i:s';
    /** @var bool true if this language is right to left language */
    public $is_rtl = false;
    /** @var bool Status */
    public $active = true;
    // @codingStandardsIgnoreEnd

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'lang',
        'primary' => 'id_lang',
        'fields'  => [
            'name'             => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 32],
            'iso_code'         => ['type' => self::TYPE_STRING, 'validate' => 'isLanguageIsoCode', 'required' => true, 'size' => 2],
            'language_code'    => ['type' => self::TYPE_STRING, 'validate' => 'isLanguageCode', 'size' => 5],
            'active'           => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'is_rtl'           => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'date_format_lite' => ['type' => self::TYPE_STRING, 'validate' => 'isPhpDateFormat', 'required' => true, 'size' => 32],
            'date_format_full' => ['type' => self::TYPE_STRING, 'validate' => 'isPhpDateFormat', 'required' => true, 'size' => 32],
        ],
    ];
    protected $webserviceParameters = [
        'objectNodeName'  => 'language',
        'objectsNodeName' => 'languages',
    ];
    protected $translationsFilesAndVars = [
        'fields' => '_FIELDS',
        'errors' => '_ERRORS',
        'admin'  => '_LANGADM',
        'pdf'    => '_LANGPDF',
        'tabs'   => 'tabs',
    ];

    /**
     * LanguageCore constructor.
     *
     * @param int|null $id
     * @param int|null $idLang
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function __construct($id = null, $idLang = null)
    {
        parent::__construct($id);
    }

    /**
     * Returns an array of language IDs
     *
     * @param bool     $active Select only active languages
     * @param int|bool $idShop Shop ID
     *
     * @return array
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     * @throws \Exception
     */
    public static function getIDs($active = true, $idShop = false)
    {
        return static::getLanguages($active, $idShop, true);
    }

    /**
     * Returns available languages
     *
     * @param bool     $active  Select only active languages
     * @param int|bool $idShop  Shop ID
     * @param bool     $idsOnly If true, returns an array of language IDs
     *
     * @return array Languages
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     * @throws \Exception
     */
    public static function getLanguages($active = true, $idShop = false, $idsOnly = false)
    {
        if (!static::$_LANGUAGES) {
            Language::loadLanguages();
        }

        $languages = [];
        foreach (static::$_LANGUAGES as $language) {
            if ($active && !$language['active'] || ($idShop && !isset($language['shops'][(int) $idShop]))) {
                continue;
            }

            $languages[] = $idsOnly ? $language['id_lang'] : $language;
        }

        return $languages;
    }

    /**
     * Load all languages in memory for caching
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     * @throws \Exception
     */
    public static function loadLanguages()
    {
        static::$_LANGUAGES = [];

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new DbQuery())
                ->select('l.*, ls.`id_shop`')
                ->from('lang', 'l')
                ->leftJoin('lang_shop', 'ls', 'l.`id_lang` = ls.`id_lang`')
        );
        foreach ($result as $row) {
            if (!isset(static::$_LANGUAGES[(int) $row['id_lang']])) {
                static::$_LANGUAGES[(int) $row['id_lang']] = $row;
            }
            static::$_LANGUAGES[(int) $row['id_lang']]['shops'][(int) $row['id_shop']] = true;
        }
    }

    /**
     * @param $idLang
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getLanguage($idLang)
    {
        if (!isset(static::$_LANGUAGES[$idLang])) {
            return false;
        }

        return static::$_LANGUAGES[(int) ($idLang)];
    }

    /**
     * @param string $isoCode
     *
     * @return false|null|string
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     * @throws \Exception
     */
    public static function getLanguageCodeByIso($isoCode)
    {
        if (!Validate::isLanguageIsoCode($isoCode)) {
            die(Tools::displayError('Fatal error: ISO code is not correct').' '.Tools::safeOutput($isoCode));
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            (new DbQuery())
                ->select('`language_code`')
                ->from('lang')
                ->where('`iso_code` = \''.pSQL(strtolower($isoCode)).'\'')
        );
    }

    /**
     * @param string $code
     *
     * @return bool|Language
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     * @throws \Exception
     */
    public static function getLanguageByIETFCode($code)
    {
        if (!Validate::isLanguageCode($code)) {
            die(sprintf(Tools::displayError('Fatal error: IETF code %s is not correct'), Tools::safeOutput($code)));
        }

        // $code is in the form of 'xx-YY' where xx is the language code
        // and 'YY' a country code identifying a variant of the language.
        $langCountry = explode('-', $code);
        // Get the language component of the code
        $lang = $langCountry[0];

        // Find the id_lang of the language.
        // We look for anything with the correct language code
        // and sort on equality with the exact IETF code wanted.
        // That way using only one query we get either the exact wanted language
        // or a close match.
        $idLang = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            (new DbQuery())
                ->select('`id_lang`, IF(language_code = \''.pSQL($code).'\', 0, LENGTH(language_code)) as found')
                ->from('lang')
                ->where('LEFT(`language_code`, 2) = \''.pSQL($lang).'\'')
                ->orderBy('`found` ASC')
        );

        // Instantiate the Language object if we found it.
        if ($idLang) {
            return new Language($idLang);
        } else {
            return false;
        }
    }

    /**
     * Return array (id_lang, iso_code)
     *
     * @param bool $active
     *
     * @return array Language (id_lang, iso_code)
     * @throws \Exception
     * @since    1.0.0
     * @version  1.0.0 Initial version
     */
    public static function getIsoIds($active = true)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new DbQuery())
                ->select('`id_lang`, `iso_code`')
                ->from('lang')
                ->where($active ? '`active` = 1' : '')
        );
    }

    /**
     * @param string $from
     * @param string $to
     *
     * @return bool
     *
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function copyLanguageData($from, $to)
    {
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SHOW TABLES FROM `'._DB_NAME_.'`');
        foreach ($result as $row) {
            if (preg_match('/_lang/', $row['Tables_in_'._DB_NAME_]) && $row['Tables_in_'._DB_NAME_] != _DB_PREFIX_.'lang') {
                $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                    (new DbQuery())
                        ->select('*')
                        ->from(bqSQL($row['Tables_in_'._DB_NAME_]))
                        ->where('`id_lang` = '.(int) $from)
                );
                if (!count($result2)) {
                    continue;
                }
                Db::getInstance()->delete(bQSQL($row['Tables_in_'._DB_NAME_]), '`id_lang` = '.(int) $to);
                $query = 'INSERT INTO `'.$row['Tables_in_'._DB_NAME_].'` VALUES ';
                foreach ($result2 as $row2) {
                    $query .= '(';
                    $row2['id_lang'] = $to;
                    foreach ($row2 as $field) {
                        $query .= (!is_string($field) && $field == null) ? 'NULL,' : '\''.pSQL($field, true).'\',';
                    }
                    $query = rtrim($query, ',').'),';
                }
                $query = rtrim($query, ',');
                Db::getInstance()->execute($query);
            }
        }

        return true;
    }

    /**
     * @param $iso_code
     *
     * @return bool|mixed
     *
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function isInstalled($iso_code)
    {
        if (static::$_cache_language_installation === null) {
            static::$_cache_language_installation = [];
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('`id_lang`, `iso_code`')
                    ->from('lang')
            );
            foreach ($result as $row) {
                static::$_cache_language_installation[$row['iso_code']] = $row['id_lang'];
            }
        }

        return (isset(static::$_cache_language_installation[$iso_code]) ? static::$_cache_language_installation[$iso_code] : false);
    }

    /**
     * Check if more on than one language is activated
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     * @throws \Exception
     */
    public static function isMultiLanguageActivated($idShop = null)
    {
        return (Language::countActiveLanguages($idShop) > 1);
    }

    /**
     * @param null $idShop
     *
     * @return mixed
     *
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function countActiveLanguages($idShop = null)
    {
        if (isset(Context::getContext()->shop) && is_object(Context::getContext()->shop) && $idShop === null) {
            $idShop = (int) Context::getContext()->shop->id;
        }

        if (!isset(static::$countActiveLanguages[$idShop])) {
            static::$countActiveLanguages[$idShop] = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('COUNT(DISTINCT l.`id_lang`)')
                    ->from('lang', 'l')
                    ->innerJoin('lang_shop', 'ls', 'ls.`id_lang` = l.`id_lang`')
                    ->where('ls.`id_shop` = '.(int) $idShop)
                    ->where('l.`active` = 1')
            );
        }

        return static::$countActiveLanguages[$idShop];
    }

    /**
     * @param string      $iso
     * @param Archive_Tar $tar
     *
     * @return array|bool|int|null
     */
    public static function getLanguagePackListContent($iso, $tar)
    {
        $key = 'Language::getLanguagePackListContent_'.$iso;
        if (!Cache::isStored($key)) {
            if (!$tar instanceof Archive_Tar) {
                return false;
            }
            $result = $tar->listContent();
            Cache::store($key, $result);

            return $result;
        }

        return Cache::retrieve($key);
    }

    /**
     * Return id from iso code
     *
     * @param string $isoCode Iso code
     * @param bool   $noCache
     *
     * @return false|null|string
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     * @throws \Exception
     */
    public static function getIdByIso($isoCode, $noCache = false)
    {
        if (!Validate::isLanguageIsoCode($isoCode)) {
            die(Tools::displayError('Fatal error: ISO code is not correct').' '.Tools::safeOutput($isoCode));
        }

        $key = 'Language::getIdByIso_'.$isoCode;
        if ($noCache || !Cache::isStored($key)) {
            $idLang = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('`id_lang`')
                    ->from('lang')
                    ->where('`iso_code` = \''.pSQL($isoCode).'\'')
            );

            Cache::store($key, $idLang);

            return $idLang;
        }

        return Cache::retrieve($key);
    }

    /**
     * @param bool $autoDate
     * @param bool $nullValues
     * @param bool $onlyAdd
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     * @throws \Exception
     */
    public function add($autoDate = true, $nullValues = false, $onlyAdd = false)
    {
        if (!parent::add($autoDate, $nullValues)) {
            return false;
        }

        if ($onlyAdd) {
            return true;
        }

        // create empty files if they not exists
        $this->_generateFiles();

        // Set default language routes
        Configuration::updateValue('PS_ROUTE_product_rule', [$this->id => '{categories:/}{rewrite}']);
        Configuration::updateValue('PS_ROUTE_category_rule', [$this->id => '{rewrite}']);
        Configuration::updateValue('PS_ROUTE_layered_rule', [$this->id => '{categories:/}{rewrite}{/:selected_filters}']);
        Configuration::updateValue('PS_ROUTE_supplier_rule', [$this->id => '{rewrite}']);
        Configuration::updateValue('PS_ROUTE_manufacturer_rule', [$this->id => '{rewrite}']);
        Configuration::updateValue('PS_ROUTE_cms_rule', [$this->id => 'info/{categories:/}{rewrite}']);
        Configuration::updateValue('PS_ROUTE_cms_category_rule', [$this->id => 'info/{categories:/}{rewrite}']);

        $this->loadUpdateSQL();

        return true;
    }

    /**
     * Generate translations files
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     *
     */
    protected function _generateFiles($newIso = null)
    {
        $isoCode = $newIso ? $newIso : $this->iso_code;

        if (!file_exists(_PS_TRANSLATIONS_DIR_.$isoCode)) {
            if (@mkdir(_PS_TRANSLATIONS_DIR_.$isoCode)) {
                @chmod(_PS_TRANSLATIONS_DIR_.$isoCode, 0777);
            }
        }

        foreach ($this->translationsFilesAndVars as $file => $var) {
            $pathFile = _PS_TRANSLATIONS_DIR_.$isoCode.'/'.$file.'.php';
            if (!file_exists($pathFile)) {
                if ($file != 'tabs') {
                    @file_put_contents(
                        $pathFile, '<?php
	global $'.$var.';
	$'.$var.' = array();
?>'
                    );
                } else {
                    @file_put_contents(
                        $pathFile, '<?php
	$'.$var.' = array();
	return $'.$var.';
?>'
                    );
                }
            }

            @chmod($pathFile, 0777);
        }
    }

    /**
     * loadUpdateSQL will create default lang values when you create a new lang, based on default id lang
     *
     * @return bool true if succeed
     *
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function loadUpdateSQL()
    {
        $tables = Db::getInstance()->executeS('SHOW TABLES LIKE \''.str_replace('_', '\\_', _DB_PREFIX_).'%\_lang\' ');
        $langTables = [];

        foreach ($tables as $table) {
            foreach ($table as $t) {
                if ($t != _DB_PREFIX_.'configuration_lang') {
                    $langTables[] = $t;
                }
            }
        }

        $return = true;

        $shops = Shop::getShopsCollection(false);
        foreach ($shops as $shop) {
            /** @var Shop $shop */
            $idLangDefault = Configuration::get('PS_LANG_DEFAULT', null, $shop->id_shop_group, $shop->id);

            foreach ($langTables as $name) {
                preg_match('#^'.preg_quote(_DB_PREFIX_).'(.+)_lang$#i', $name, $m);
                $identifier = 'id_'.$m[1];

                $fields = '';
                // We will check if the table contains a column "id_shop"
                // If yes, we will add "id_shop" as a WHERE condition in queries copying data from default language
                $shopFieldExists = $primaryKeyExists = false;
                $columns = Db::getInstance()->executeS('SHOW COLUMNS FROM `'.$name.'`');
                foreach ($columns as $column) {
                    $fields .= '`'.$column['Field'].'`, ';
                    if ($column['Field'] == 'id_shop') {
                        $shopFieldExists = true;
                    }
                    if ($column['Field'] == $identifier) {
                        $primaryKeyExists = true;
                    }
                }
                $fields = rtrim($fields, ', ');

                if (!$primaryKeyExists) {
                    continue;
                }

                $sql = 'INSERT IGNORE INTO `'.$name.'` ('.$fields.') (SELECT ';

                // For each column, copy data from default language
                reset($columns);
                foreach ($columns as $column) {
                    if ($identifier != $column['Field'] && $column['Field'] != 'id_lang') {
                        $sql .= '(
							SELECT `'.bqSQL($column['Field']).'`
							FROM `'.bqSQL($name).'` tl
							WHERE tl.`id_lang` = '.(int) $idLangDefault.'
							'.($shopFieldExists ? ' AND tl.`id_shop` = '.(int) $shop->id : '').'
							AND tl.`'.bqSQL($identifier).'` = `'.bqSQL(str_replace('_lang', '', $name)).'`.`'.bqSQL($identifier).'`
						),';
                    } else {
                        $sql .= '`'.bqSQL($column['Field']).'`,';
                    }
                }
                $sql = rtrim($sql, ', ');
                $sql .= ' FROM `'._DB_PREFIX_.'lang` CROSS JOIN `'.bqSQL(str_replace('_lang', '', $name)).'`)';
                $return &= Db::getInstance()->execute($sql);
            }
        }

        return $return;
    }

    /**
     * @see     ObjectModel::getFields()
     * @return array
     *
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function getFields()
    {
        $this->iso_code = strtolower($this->iso_code);
        if (empty($this->language_code)) {
            $this->language_code = $this->iso_code;
        }

        return parent::getFields();
    }

    /**
     * Return iso code from id
     *
     * @param int $idLang Language ID
     *
     * @return string Iso code
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getIsoById($idLang)
    {
        if (isset(static::$_LANGUAGES[(int) $idLang]['iso_code'])) {
            return static::$_LANGUAGES[(int) $idLang]['iso_code'];
        }

        return false;
    }

    /**
     * @param $dir
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function recurseDeleteDir($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }
        if ($handle = @opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != '.' && $file != '..') {
                    if (is_dir($dir.'/'.$file)) {
                        Language::recurseDeleteDir($dir.'/'.$file);
                    } elseif (file_exists($dir.'/'.$file)) {
                        @unlink($dir.'/'.$file);
                    }
                }
            }
            closedir($handle);
        }
        if (is_writable($dir)) {
            rmdir($dir);
        }
    }
}
