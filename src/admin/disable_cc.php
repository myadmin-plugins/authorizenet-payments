<?php
	/**
	 * Administrative Functionality
	 * @author Joe Huss <detain@interserver.net>
	 * @copyright 2017
	 * @package MyAdmin
	 * @category Admin
	 */

	/**
	 * disable_cc()
	 *
	 * @return bool|void
	 */
	function disable_cc() {
		page_title('Disable Clients Credit Card');
		function_requirements('has_acl');
		if ($GLOBALS['tf']->ima != 'admin' || !has_acl('client_billing')) {
			dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
			return false;
		}
		if (verify_csrf_referrer(__LINE__, __FILE__)) {
			$module = get_module_name((isset($GLOBALS['tf']->variables->request['module']) ? $GLOBALS['tf']->variables->request['module'] : 'default'));
			$GLOBALS['tf']->accounts->set_db_module($module);
			$GLOBALS['tf']->history->set_db_module($module);
			$customer = $GLOBALS['tf']->variables->request['customer'];
			$data = $GLOBALS['tf']->accounts->read($customer);
			$lid = $data['account_lid'];
			$new_data['disable_cc'] = 1;
			$new_data['payment_method'] = 'paypal';
			foreach ($GLOBALS['modules'] as $module => $settings) {
				$GLOBALS['tf']->accounts->set_db_module($module);
				$GLOBALS['tf']->history->set_db_module($module);
				$customer = $GLOBALS['tf']->accounts->cross_reference($lid);
				if ($customer !== false) {
					$GLOBALS['tf']->accounts->update($customer, $new_data);
				}
			}
			add_output('CC Disabled');
		}
	}
