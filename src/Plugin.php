<?php

namespace Detain\MyAdminAuthorizenet;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminAuthorizenet
 */
class Plugin
{
	public static $name = 'Authorizenet Plugin';
	public static $description = 'Allows handling of Authorizenet based Payments through their Payment Processor/Payment System.';
	public static $help = '';
	public static $type = 'plugin';

	/**
	 * Plugin constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * @return array
	 */
	public static function getHooks()
	{
		return [
			'system.settings' => [__CLASS__, 'getSettings'],
			//'ui.menu' => [__CLASS__, 'getMenu'],
			'function.requirements' => [__CLASS__, 'getRequirements']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getMenu(GenericEvent $event)
	{
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			function_requirements('has_acl');
			if (has_acl('client_billing')) {
			}
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event)
	{
		/**
		 * @var \MyAdmin\Plugins\Loader $this->loader
		 */
		$loader = $event->getSubject();
		$loader->add_page_requirement('charge_card_invoice', '/../vendor/detain/myadmin-authorizenet-payments/src/charge_card_invoice.php');
		$loader->add_requirement('mask_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('valid_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('get_locked_ccs', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('select_cc_exp', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('can_use_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('format_cc_exp', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('make_cc_decline', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_page_requirement('email_cc_decline', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('parse_ccs', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('get_bad_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('get_cc_bank_number', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('get_cc_last_four', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('charge_card', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('auth_charge_card', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
		$loader->add_requirement('add_cc_new_data', '/../vendor/detain/myadmin-authorizenet-payments/src/add_cc.php');
		$loader->add_requirement('get_cc_cats_and_fields', '/../vendor/detain/myadmin-authorizenet-payments/src/admin/view_cc_transaction.php');
		$loader->add_page_requirement('manage_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/manage_cc.php');
		$loader->add_page_requirement('add_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/add_cc.php');
		$loader->add_requirement('verify_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/verify_cc.php');
		$loader->add_requirement('verify_admin_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/verify_cc.php');
		$loader->add_requirement('verify_cc_charge', '/../vendor/detain/myadmin-authorizenet-payments/src/verify_cc.php');
		$loader->add_page_requirement('view_cc_transaction', '/../vendor/detain/myadmin-authorizenet-payments/src/admin/view_cc_transaction.php');
		$loader->add_page_requirement('disable_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/admin/disable_cc.php');
		$loader->add_page_requirement('disable_cc_whitelist', '/../vendor/detain/myadmin-authorizenet-payments/src/admin/disable_cc_whitelist.php');
		$loader->add_requirement('get_authorizenet_fields', '/../vendor/detain/myadmin-authorizenet-payments/src/get_authorizenet_fields.php');
		$loader->add_page_requirement('map_authorizenet_fields', '/../vendor/detain/myadmin-authorizenet-payments/src/map_authorizenet_fields.php');
		$loader->add_page_requirement('enable_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/admin/enable_cc.php');
		$loader->add_page_requirement('authorize_cc', '/../vendor/detain/myadmin-authorizenet-payments/src/admin/authorize_cc.php');
		$loader->add_page_requirement('enable_cc_whitelist', '/../vendor/detain/myadmin-authorizenet-payments/src/admin/enable_cc_whitelist.php');
		$loader->add_requirement('class.AuthorizeNetCC', '/../vendor/detain/myadmin-authorizenet-payments/src/AuthorizeNetCC.php');
		$loader->add_page_requirement('cc_refund', '/../vendor/detain/myadmin-authorizenet-payments/src/admin/cc_refund.php');
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event)
	{
		/**
		 * @var \MyAdmin\Settings $settings
		 **/
		$settings = $event->getSubject();
		$settings->add_radio_setting(_('Billing'), _('Authorize.Net'), 'authorizenet_enable', _('Enable Authorize.net'), _('Enable Authorize.net'), AUTHORIZENET_ENABLE, [true, false], ['Enabled', 'Disabled']);
		$settings->add_text_setting(_('Billing'), _('Authorize.Net'), 'authorizenet_login', _('Login Name'), _('Login Name'), (defined('AUTHORIZENET_LOGIN') ? AUTHORIZENET_LOGIN : ''));
		$settings->add_text_setting(_('Billing'), _('Authorize.Net'), 'authorizenet_password', _('Password'), _('Password'), (defined('AUTHORIZENET_PASSWORD') ? AUTHORIZENET_PASSWORD : ''));
		$settings->add_text_setting(_('Billing'), _('Authorize.Net'), 'authorizenet_key', _('API Key'), _('API Key'), (defined('AUTHORIZENET_KEY') ? AUTHORIZENET_KEY : ''));
		$settings->add_text_setting(_('Billing'), _('Authorize.Net'), 'authorizenet_referrer', _('Referrer URL (optional)'), _('Referrer URL (optional)'), (defined('AUTHORIZENET_REFERER') ? AUTHORIZENET_REFERER : ''));
	}
}
