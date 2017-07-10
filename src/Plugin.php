<?php

namespace Detain\MyAdminAuthorizenet;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'Authorizenet Plugin';
	public static $description = 'Allows handling of Authorizenet emails and honeypots';
	public static $help = '';
	public static $type = 'plugin';


	public function __construct() {
	}

	public static function getHooks() {
		return [
			'system.settings' => [__CLASS__, 'getSettings'],
			'ui.menu' => [__CLASS__, 'getMenu'],
		];
	}

	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			function_requirements('has_acl');
					if (has_acl('client_billing'))
							$menu->add_link('admin', 'choice=none.abuse_admin', '//my.interserver.net/bower_components/webhostinghub-glyphs-icons/icons/development-16/Black/icon-spam.png', 'Authorizenet');
		}
	}

	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_requirement('class.Authorizenet', '/../vendor/detain/myadmin-authorizenet-payments/src/Authorizenet.php');
		$loader->add_requirement('deactivate_kcare', '/../vendor/detain/myadmin-authorizenet-payments/src/abuse.inc.php');
		$loader->add_requirement('deactivate_abuse', '/../vendor/detain/myadmin-authorizenet-payments/src/abuse.inc.php');
		$loader->add_requirement('get_abuse_licenses', '/../vendor/detain/myadmin-authorizenet-payments/src/abuse.inc.php');
	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting('General', 'Authorizenet', 'abuse_imap_user', 'Authorizenet IMAP User:', 'Authorizenet IMAP Username', ABUSE_IMAP_USER);
		$settings->add_text_setting('General', 'Authorizenet', 'abuse_imap_pass', 'Authorizenet IMAP Pass:', 'Authorizenet IMAP Password', ABUSE_IMAP_PASS);
	}

}
