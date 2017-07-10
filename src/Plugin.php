<?php

namespace Detain\MyAdminAuthorizeNet;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'AuthorizeNet Plugin';
	public static $description = 'Allows handling of AuthorizeNet emails and honeypots';
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
							$menu->add_link('admin', 'choice=none.abuse_admin', '//my.interserver.net/bower_components/webhostinghub-glyphs-icons/icons/development-16/Black/icon-spam.png', 'AuthorizeNet');
		}
	}

	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_requirement('class.AuthorizeNet', '/../vendor/detain/myadmin-authorizenet-payments/src/AuthorizeNet.php');
		$loader->add_requirement('deactivate_kcare', '/../vendor/detain/myadmin-authorizenet-payments/src/abuse.inc.php');
		$loader->add_requirement('deactivate_abuse', '/../vendor/detain/myadmin-authorizenet-payments/src/abuse.inc.php');
		$loader->add_requirement('get_abuse_licenses', '/../vendor/detain/myadmin-authorizenet-payments/src/abuse.inc.php');
	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting('General', 'AuthorizeNet', 'abuse_imap_user', 'AuthorizeNet IMAP User:', 'AuthorizeNet IMAP Username', ABUSE_IMAP_USER);
		$settings->add_text_setting('General', 'AuthorizeNet', 'abuse_imap_pass', 'AuthorizeNet IMAP Pass:', 'AuthorizeNet IMAP Password', ABUSE_IMAP_PASS);
	}

}
