<?php
/**
 * Administrative Functionality
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2025
 * @package MyAdmin
 * @category Admin
 */

/**
 * disable_cc()
 *
 * @return bool|void
 */
function disable_cc()
{
    page_title('Disable Clients Credit Card');
    function_requirements('has_acl');
    if (\MyAdmin\App::ima() != 'admin' || !has_acl('edit_customer')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    if (verify_csrf_referrer(__LINE__, __FILE__)) {
        $module = get_module_name((\MyAdmin\App::variables()->request['module'] ?? 'default'));
        $customer = \MyAdmin\App::variables()->request['customer'];
        $data = \MyAdmin\App::accounts()->read($customer);
        $lid = $data['account_lid'];
        $new_data['disable_cc'] = 1;
        $new_data['payment_method'] = 'paypal';
        foreach ($GLOBALS['modules'] as $module => $settings) {
            $customer = \MyAdmin\App::accounts()->cross_reference($lid);
            if ($customer !== false) {
                \MyAdmin\App::accounts()->update($customer, $new_data);
            }
        }
        add_output('CC Disabled');
        if (isset(\MyAdmin\App::variables()->request['rd']) && \MyAdmin\App::variables()->request['rd'] === 'ec') {
            myadmin_log('admin', 'info', "Admin - " . \MyAdmin\App::session()->account_id . " disabled cc and now back to requested page.", __LINE__, __FILE__, $module);
            \MyAdmin\App::output()->redirect(\MyAdmin\App::link('index.php', 'choice=none.edit_customer&customer='.$customer));
        }
    }
}
