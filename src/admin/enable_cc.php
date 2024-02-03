<?php
/**
 * Administrative Functionality
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2019
 * @package MyAdmin
 * @category Admin
 */

/**
 * enable_cc()
 *
 * @return bool|void
 */
function enable_cc()
{
    page_title('Enable Clients Credit-Card');
    function_requirements('has_acl');
    if ($GLOBALS['tf']->ima != 'admin' || !has_acl('edit_customer')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    if (verify_csrf_referrer(__LINE__, __FILE__)) {
        $module = get_module_name(($GLOBALS['tf']->variables->request['module'] ?? 'default'));
        myadmin_log('admin', 'info', "Module $module", __LINE__, __FILE__);
        $customer = $GLOBALS['tf']->variables->request['customer'];
        myadmin_log('admin', 'info', "Customer $customer", __LINE__, __FILE__);
        $data = $GLOBALS['tf']->accounts->read($customer);
        myadmin_log('admin', 'info', 'Customer Data ' . json_encode($data), __LINE__, __FILE__);
        $lid = $data['account_lid'];
        $new_data['disable_cc'] = 0;
        myadmin_log('admin', 'info', "LID $lid", __LINE__, __FILE__);
        foreach ($GLOBALS['modules'] as $module => $settings) {
            $customer = $GLOBALS['tf']->accounts->cross_reference($lid);
            if ($customer !== false) {
                $GLOBALS['tf']->accounts->update($customer, $new_data);
            }
        }
        add_output('CC Enabled');
        if (isset($GLOBALS['tf']->variables->request['rd']) && $GLOBALS['tf']->variables->request['rd'] === 'ec') {
            myadmin_log('admin', 'info', "Admin - {$GLOBALS['tf']->session->account_id} enabled cc and now back to requested page.", __LINE__, __FILE__, $module);
            $GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=none.edit_customer&customer='.$customer));
        }
    }
}
