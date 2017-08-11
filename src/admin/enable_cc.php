<?php
	/**
	 * Administrative Functionality
	 * @author Joe Huss <detain@interserver.net>
	 * @copyright 2017
	 * @package MyAdmin
	 * @category Admin
	 */

	/**
	 * enable_cc()
	 *
	 * @return bool|void
	 */
	function enable_cc() {
		page_title('Enable Clients Credit-Card');
		function_requirements('has_acl');
		if ($GLOBALS['tf']->ima != 'admin' || !has_acl('client_billing')) {
			dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
			return false;
		}
		if (verify_csrf_referrer(__LINE__, __FILE__)) {
			$module = get_module_name((isset($GLOBALS['tf']->variables->request['module']) ? $GLOBALS['tf']->variables->request['module'] : 'default'));
			myadmin_log('admin', 'info', "Module $module", __LINE__, __FILE__);
			$GLOBALS['tf']->accounts->set_db_module($module);
			$GLOBALS['tf']->history->set_db_module($module);
			$customer = $GLOBALS['tf']->variables->request['customer'];
			myadmin_log('admin', 'info', "Customer $customer", __LINE__, __FILE__);
			$data = $GLOBALS['tf']->accounts->read($customer);
			myadmin_log('admin', 'info', 'Customer Data ' . json_encode($data), __LINE__, __FILE__);
			$lid = $data['account_lid'];
			$new_data['disable_cc'] = 0;
			myadmin_log('admin', 'info', "LID $lid", __LINE__, __FILE__);
			foreach ($GLOBALS['modules'] as $module => $settings) {
				$GLOBALS['tf']->accounts->set_db_module($module);
				$GLOBALS['tf']->history->set_db_module($module);
				$customer = $GLOBALS['tf']->accounts->cross_reference($lid);
				if ($customer !== false) {
					$GLOBALS['tf']->accounts->update($customer, $new_data);
				}
			}
			add_output('CC Enabled');
		}
	}
