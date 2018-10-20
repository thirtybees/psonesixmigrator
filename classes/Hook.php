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
 * Class Hook
 *
 * @since 1.0.0
 */
class Hook extends ObjectModel
{
    // @codingStandardsIgnoreStart
    /**
     * @var array List of executed hooks on this page
     */
    public static $executed_hooks = [];
    public static $native_module;
    /**
     * @deprecated 1.0.0
     */
    protected static $_hook_modules_cache = null;
    /**
     * @deprecated 1.0.0
     */
    protected static $_hook_modules_cache_exec = null;
    /**
     * @var string Hook name identifier
     */
    public $name;
    /**
     * @var string Hook title (displayed in BO)
     */
    public $title;
    /**
     * @var string Hook description
     */
    public $description;
    /**
     * @var bool
     */
    public $position = false;
    /**
     * @var bool Is this hook usable with live edit ?
     */
    public $live_edit = false;
    // @codingStandardsIgnoreEnd

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'hook',
        'primary' => 'id_hook',
        'fields'  => [
            'name'        => ['type' => self::TYPE_STRING, 'validate' => 'isHookName', 'required' => true, 'size' => 64],
            'title'       => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName'],
            'description' => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'],
            'position'    => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'live_edit'   => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
        ],
    ];

    /**
     * Return Hooks List
     *
     * @param bool $position
     *
     * @return array Hooks List
     *
     * @throws \Exception
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getHooks($position = false)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
			SELECT * FROM `'._DB_PREFIX_.'hook` h
			'.($position ? 'WHERE h.`position` = 1' : '').'
			ORDER BY `name`'
        );
    }

    /**
     * Return hook ID from name
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     * @throws \Exception
     */
    public static function getNameById($hookId)
    {
        $cacheId = 'hook_namebyid_'.$hookId;
        if (!Cache::isStored($cacheId)) {
            $result = Db::getInstance()->getValue(
                '
							SELECT `name`
							FROM `'._DB_PREFIX_.'hook`
							WHERE `id_hook` = '.(int) $hookId
            );
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Return hook live edit bool from ID
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     * @throws \Exception
     */
    public static function getLiveEditById($hookId)
    {
        $cacheId = 'hook_live_editbyid_'.$hookId;
        if (!Cache::isStored($cacheId)) {
            $result = Db::getInstance()->getValue(
                '
							SELECT `live_edit`
							FROM `'._DB_PREFIX_.'hook`
							WHERE `id_hook` = '.(int) $hookId
            );
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Return Hooks List
     *
     * @since   1.5.0
     *
     * @param int $idHook
     * @param int $idModule
     *
     * @return array Modules List
     *
     * @throws \Exception
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getModulesFromHook($idHook, $idModule = null)
    {
        $hmList = Hook::getHookModuleList();
        $moduleList = (isset($hmList[$idHook])) ? $hmList[$idHook] : [];

        if ($idModule) {
            return (isset($moduleList[$idModule])) ? [$moduleList[$idModule]] : [];
        }

        return $moduleList;
    }

    /**
     * Get list of all registered hooks with modules
     *
     * @return array
     *
     * @throws \Exception
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getHookModuleList()
    {
        $cacheId = 'hook_module_list';
        if (!Cache::isStored($cacheId)) {
            $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                SELECT h.id_hook, h.name AS h_name, h.title, h.description, h.position, h.live_edit, hm.position AS hm_position, m.id_module, m.name, m.active
                FROM `'._DB_PREFIX_.'hook_module` hm
                STRAIGHT_JOIN `'._DB_PREFIX_.'hook` h ON (h.id_hook = hm.id_hook AND hm.id_shop = '.(int) Context::getContext()->shop->id.')
                STRAIGHT_JOIN `'._DB_PREFIX_.'module` AS m ON (m.id_module = hm.id_module)
                ORDER BY hm.position'
            );
            $list = [];
            foreach ($results as $result) {
                if (!isset($list[$result['id_hook']])) {
                    $list[$result['id_hook']] = [];
                }

                $list[$result['id_hook']][$result['id_module']] = [
                    'id_hook'     => $result['id_hook'],
                    'title'       => $result['title'],
                    'description' => $result['description'],
                    'hm.position' => $result['position'],
                    'live_edit'   => $result['live_edit'],
                    'm.position'  => $result['hm_position'],
                    'id_module'   => $result['id_module'],
                    'name'        => $result['name'],
                    'active'      => $result['active'],
                ];
            }
            Cache::store($cacheId, $list);

            // @todo remove this in 1.6, we keep it in 1.5 for retrocompatibility
            Hook::$_hook_modules_cache = $list;

            return $list;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Execute modules for specified hook
     *
     * @param string $hookName        Hook Name
     * @param array  $hookArgs        Parameters for the functions
     * @param int    $idModule        Execute hook for this module only
     * @param bool   $arrayReturn     If specified, module output will be set by name in an array
     * @param bool   $checkExceptions Check permission exceptions
     * @param bool   $usePush         Force change to be refreshed on Dashboard widgets
     * @param int    $idShop          If specified, hook will be execute the shop with this ID
     *
     * @throws \Exception
     *
     * @return string|array modules output
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function exec(
        $hookName,
        $hookArgs = [],
        $idModule = null,
        $arrayReturn = false,
        $checkExceptions = true,
        $usePush = false,
        $idShop = null
    ) {
            return;
    }

    /**
     * Get list of modules we can execute per hook
     *
     * @param string $hookName Get list of modules for this hook if given
     *
     * @return array
     *
     * @throws \Exception
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getHookModuleExecList($hookName = null)
    {
        return [];
    }

    /**
     * Return backward compatibility hook name
     *
     *
     * @param string $hookName Hook name
     *
     * @return int Hook ID
     *
     * @throws \Exception
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getRetroHookName($hookName)
    {
        $aliasList = Hook::getHookAliasList();
        if (isset($aliasList[strtolower($hookName)])) {
            return $aliasList[strtolower($hookName)];
        }

        $retroHookName = array_search($hookName, $aliasList);
        if ($retroHookName === false) {
            return '';
        }

        return $retroHookName;
    }

    /**
     * Get list of hook alias
     *
     *
     * @return array
     *
     * @throws \Exception
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getHookAliasList()
    {
        $cacheId = 'hook_alias';
        if (!Cache::isStored($cacheId)) {
            $hookAliasList = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'hook_alias`');
            $hookAlias = [];
            if ($hookAliasList) {
                foreach ($hookAliasList as $ha) {
                    $hookAlias[strtolower($ha['alias'])] = $ha['name'];
                }
            }
            Cache::store($cacheId, $hookAlias);

            return $hookAlias;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Return hook ID from name
     *
     * @param string $hookName Hook name
     *
     * @return int Hook ID
     *
     * @throws \Exception
     * @throws \Exception
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getIdByName($hookName)
    {
        $hookName = strtolower($hookName);
        if (!Validate::isHookName($hookName)) {
            return false;
        }

        $cacheId = 'hook_idsbyname';
        if (!Cache::isStored($cacheId)) {
            // Get all hook ID by name and alias
            $hookIds = [];
            $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
            $result = $db->executeS(
                '
			SELECT `id_hook`, `name`
			FROM `'._DB_PREFIX_.'hook`
			UNION
			SELECT `id_hook`, ha.`alias` AS name
			FROM `'._DB_PREFIX_.'hook_alias` ha
			INNER JOIN `'._DB_PREFIX_.'hook` h ON ha.name = h.name', false
            );
            while ($row = $db->nextRow($result)) {
                $hookIds[strtolower($row['name'])] = $row['id_hook'];
            }
            Cache::store($cacheId, $hookIds);
        } else {
            $hookIds = Cache::retrieve($cacheId);
        }

        return (isset($hookIds[$hookName]) ? $hookIds[$hookName] : false);
    }

    /**
     * @param string $display
     * @param Module $moduleInstance
     * @param int    $idHook
     *
     * @return string
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function wrapLiveEdit($display, $moduleInstance, $idHook)
    {
        return '<script type="text/javascript"> modules_list.push(\''.Tools::safeOutput($moduleInstance->name).'\');</script>
				<div id="hook_'.(int) $idHook.'_module_'.(int) $moduleInstance->id.'_moduleName_'.str_replace('_', '-', Tools::safeOutput($moduleInstance->name)).'"
				class="dndModule" style="border: 1px dotted red;'.(!strlen($display) ? 'height:50px;' : '').'">
					<span style="font-family: Georgia;font-size:13px;font-style:italic;">
						<img style="padding-right:5px;" src="'._MODULE_DIR_.Tools::safeOutput($moduleInstance->name).'/logo.gif">'
            .Tools::safeOutput($moduleInstance->displayName).'<span style="float:right">
				<a href="#" id="'.(int) $idHook.'_'.(int) $moduleInstance->id.'" class="moveModule">
					<img src="'._PS_ADMIN_IMG_.'arrow_out.png"></a>
				<a href="#" id="'.(int) $idHook.'_'.(int) $moduleInstance->id.'" class="unregisterHook">
					<img src="'._PS_ADMIN_IMG_.'delete.gif"></a></span>
				</span>'.$display.'</div>';
    }

    /**
     * @deprecated 1.0.0
     *
     * @param mixed $pdf
     * @param int   $idOrder
     *
     * @return bool|string
     */
    public static function PDFInvoice($pdf, $idOrder)
    {
        Tools::displayAsDeprecated();
        if (!is_object($pdf) || !Validate::isUnsignedId($idOrder)) {
            return false;
        }

        return Hook::exec('PDFInvoice', ['pdf' => $pdf, 'id_order' => $idOrder]);
    }

    /**
     * @deprecated 1.0.0
     *
     * @param string $module
     *
     * @return string
     */
    public static function backBeforePayment($module)
    {
        Tools::displayAsDeprecated();
        if ($module) {
            return Hook::exec('backBeforePayment', ['module' => strval($module)]);
        }
    }

    /**
     * @deprecated 1.0.0
     *
     * @param int     $idCarrier
     * @param Carrier $carrier
     *
     * @return bool|string
     */
    public static function updateCarrier($idCarrier, $carrier)
    {
        Tools::displayAsDeprecated();
        if (!Validate::isUnsignedId($idCarrier) || !is_object($carrier)) {
            return false;
        }

        return Hook::exec('updateCarrier', ['id_carrier' => $idCarrier, 'carrier' => $carrier]);
    }

    /**
     * Preload hook modules cache
     *
     * @deprecated 1.0.0 use Hook::getHookModuleList() instead
     *
     * @return bool preload_needed
     * @throws \Exception
     * @throws \Exception
     */
    public static function preloadHookModulesCache()
    {
        Tools::displayAsDeprecated('Use Hook::getHookModuleList() instead');

        if (!is_null(static::$_hook_modules_cache)) {
            return false;
        }

        static::$_hook_modules_cache = Hook::getHookModuleList();

        return true;
    }

    /**
     * Return hook ID from name
     *
     * @param string $hookName Hook name
     *
     * @return int Hook ID
     *
     * @throws \Exception
     * @throws \Exception
     * @deprecated 1.0.0 use Hook::getIdByName() instead
     */
    public static function get($hookName)
    {
        Tools::displayAsDeprecated('Use Hook::getIdByName() instead');
        if (!Validate::isHookName($hookName)) {
            die(Tools::displayError());
        }

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new DbQuery())
                ->select('`id_hook`, `name`')
                ->from('hook')
                ->where('`name` = \''.pSQL($hookName).'\'')
        );

        return ($result ? $result['id_hook'] : false);
    }

    /**
     * Called when quantity of a product is updated.
     *
     * @deprecated 1.0.0
     *
     * @param Cart     $cart
     * @param Order    $order
     * @param Customer $customer
     * @param Currency $currency
     * @param int      $orderStatus
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function newOrder($cart, $order, $customer, $currency, $orderStatus)
    {
        Tools::displayAsDeprecated();

        return Hook::exec(
            'newOrder', [
                'cart'        => $cart,
                'order'       => $order,
                'customer'    => $customer,
                'currency'    => $currency,
                'orderStatus' => $orderStatus,
            ]
        );
    }

    /**
     * @deprecated 1.0.0
     *
     * @param Product    $product
     * @param Order|null $order
     *
     * @return string
     */
    public static function updateQuantity($product, $order = null)
    {
        Tools::displayAsDeprecated();

        return Hook::exec('updateQuantity', ['product' => $product, 'order' => $order]);
    }

    /**
     * @deprecated 1.0.0
     *
     * @param Product  $product
     * @param Category $category
     *
     * @return string
     */
    public static function productFooter($product, $category)
    {
        Tools::displayAsDeprecated();

        return Hook::exec('productFooter', ['product' => $product, 'category' => $category]);
    }

    /**
     * @deprecated 1.0.0
     *
     * @param Product $product
     *
     * @return string
     */
    public static function productOutOfStock($product)
    {
        Tools::displayAsDeprecated();

        return Hook::exec('productOutOfStock', ['product' => $product]);
    }

    /**
     * @deprecated 1.0.0
     *
     * @param Product $product
     *
     * @return string
     */
    public static function addProduct($product)
    {
        Tools::displayAsDeprecated();

        return Hook::exec('addProduct', ['product' => $product]);
    }

    /**
     * @deprecated 1.0.0
     *
     * @param Product $product
     *
     * @return string
     */
    public static function updateProduct($product)
    {
        Tools::displayAsDeprecated();

        return Hook::exec('updateProduct', ['product' => $product]);
    }

    /**
     * @deprecated 1.0.0
     *
     * @param Product $product
     *
     * @return string
     */
    public static function deleteProduct($product)
    {
        Tools::displayAsDeprecated();

        return Hook::exec('deleteProduct', ['product' => $product]);
    }

    /**
     * @deprecated 1.0.0
     */
    public static function updateProductAttribute($idProductAttribute)
    {
        Tools::displayAsDeprecated();

        return Hook::exec('updateProductAttribute', ['id_product_attribute' => $idProductAttribute]);
    }

    /**
     * @param bool $autoDate
     * @param bool $nullValues
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function add($autoDate = true, $nullValues = false)
    {
        Cache::clean('hook_idsbyname');

        return parent::add($autoDate, $nullValues);
    }
}
