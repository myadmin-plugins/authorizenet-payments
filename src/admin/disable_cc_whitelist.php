<?php
/**
 * Administrative Functionality
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2025
 * @package MyAdmin
 * @category Admin
 */

/**
 * disable_cc_whitelist()
 *
 * @return bool|void
 */
function disable_cc_whitelist()
{
    page_title('Disable/Remove From CC White-List');
    function_requirements('has_acl');
    if (\MyAdmin\App::ima() != 'admin' || !has_acl('edit_customer')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    $customer = \MyAdmin\App::variables()->request['customer'];
    $module = get_module_name((\MyAdmin\App::variables()->request['module'] ?? 'default'));
    $data = \MyAdmin\App::accounts()->read($customer);
    if (\MyAdmin\App::ima() == 'admin' && verify_csrf_referrer(__LINE__, __FILE__)) {
        $new_data['cc_whitelist'] = 0;
        \MyAdmin\App::accounts()->update($customer, $new_data);
        foreach ($GLOBALS['modules'] as $module => $settings) {
            $custid = \MyAdmin\App::accounts()->cross_reference($data['account_lid']);
            if ($custid !== false) {
                \MyAdmin\App::accounts()->update($custid, $new_data);
            }
        }
        add_output('CC WhiteList Disabled');
    }
}
