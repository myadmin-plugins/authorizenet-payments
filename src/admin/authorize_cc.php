<?php
	/**
	 * Administrative Functionality
	 * Last Changed: $LastChangedDate: 2017-08-06 18:14:48 -0400 (Sun, 06 Aug 2017) $
	 * @author detain
	 * @copyright 2017
	 * @package MyAdmin
	 * @category Admin
	 */

	/**
	 * authorize_cc()
	 *
	 * @return bool|void
	 */
	function authorize_cc() {
		page_title('Enable/Add To CC White-List');
		function_requirements('has_acl');
		if ($GLOBALS['tf']->ima != 'admin' || !has_acl('client_billing')) {
			dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
			return false;
		}
		$customer = $GLOBALS['tf']->variables->request['customer'];
		$module = get_module_name((isset($GLOBALS['tf']->variables->request['module']) ? $GLOBALS['tf']->variables->request['module'] : 'default'));
		$GLOBALS['tf']->accounts->set_db_module($module);
		$GLOBALS['tf']->history->set_db_module($module);
		$data = $GLOBALS['tf']->accounts->read($customer);
		if ($GLOBALS['tf']->ima == 'admin' && verify_csrf_referrer(__LINE__, __FILE__)) {
			$new_data['disable_cc'] = 0;
			$new_data['cc_whitelist'] = 1;
			$GLOBALS['tf']->accounts->update($customer, $new_data);
			foreach ($GLOBALS['modules'] as $module => $settings) {
				$GLOBALS['tf']->accounts->set_db_module($module);
				$GLOBALS['tf']->history->set_db_module($module);
				$custid = $GLOBALS['tf']->accounts->cross_reference($data['account_lid']);
				if ($custid !== false)
					$GLOBALS['tf']->accounts->update($custid, $new_data);
			}
			add_output('Authorized CC');
		}
	}
