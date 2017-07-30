<?php

namespace Detain\MyAdminAuthorizenet;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminAuthorizenet
 */
class Plugin {

	public static $name = 'Authorizenet Plugin';
	public static $description = 'Allows handling of Authorizenet based Payments through their Payment Processor/Payment System.';
	public static $help = '';
	public static $type = 'plugin';

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getHooks() {
		return [
			'system.settings' => [__CLASS__, 'getSettings'],
			//'ui.menu' => [__CLASS__, 'getMenu'],
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			function_requirements('has_acl');
					if (has_acl('client_billing'))
							$menu->add_link('admin', 'choice=none.abuse_admin', '//my.interserver.net/bower_components/webhostinghub-glyphs-icons/icons/development-16/Black/icon-spam.png', 'Authorizenet');
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_requirement('class.Authorizenet', '/../vendor/detain/myadmin-authorizenet-payments/src/Authorizenet.php');
		$loader->add_requirement('deactivate_kcare', '/../vendor/detain/myadmin-authorizenet-payments/src/abuse.inc.php');
		$loader->add_requirement('deactivate_abuse', '/../vendor/detain/myadmin-authorizenet-payments/src/abuse.inc.php');
		$loader->add_requirement('get_abuse_licenses', '/../vendor/detain/myadmin-authorizenet-payments/src/abuse.inc.php');
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_radio_setting('Billing', 'Authorize.Net', 'authorizenet_enable', 'Enable Authorize.net', 'Enable Authorize.net', AUTHORIZENET_ENABLE, [true, false], ['Enabled', 'Disabled']);
		$settings->add_text_setting('Billing', 'Authorize.Net', 'authorizenet_login', 'Login Name', 'Login Name', (defined('AUTHORIZENET_LOGIN') ? AUTHORIZENET_LOGIN : ''));
		$settings->add_text_setting('Billing', 'Authorize.Net', 'authorizenet_password', 'Password', 'Password', (defined('AUTHORIZENET_PASSWORD') ? AUTHORIZENET_PASSWORD : ''));
		$settings->add_text_setting('Billing', 'Authorize.Net', 'authorizenet_key', 'API Key', 'API Key', (defined('AUTHORIZENET_KEY') ? AUTHORIZENET_KEY : ''));
		$settings->add_text_setting('Billing', 'Authorize.Net', 'authorizenet_referer', 'Referer URL (optional)', 'Referer URL (optional)', (defined('AUTHORIZENET_REFERER') ? AUTHORIZENET_REFERER : ''));
	}

}
