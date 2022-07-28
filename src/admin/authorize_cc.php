<?php
/**
 * Administrative Functionality
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2019
 * @package MyAdmin
 * @category Admin
 */

/**
 * authorize_cc()
 *
 * @return bool|void
 */
function authorize_cc()
{
    page_title('Enable/Add To CC White-List');
    function_requirements('has_acl');
    if ($GLOBALS['tf']->ima != 'admin' || !has_acl('edit_customer')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    $customer = $GLOBALS['tf']->variables->request['customer'];
    $module = get_module_name((isset($GLOBALS['tf']->variables->request['module']) ? $GLOBALS['tf']->variables->request['module'] : 'default'));
    $data = $GLOBALS['tf']->accounts->read($customer);
    if ($GLOBALS['tf']->ima == 'admin' && verify_csrf_referrer(__LINE__, __FILE__)) {
        $new_data['disable_cc'] = 0;
        $new_data['cc_whitelist'] = 1;
        $GLOBALS['tf']->accounts->update($customer, $new_data);
        foreach ($GLOBALS['modules'] as $module => $settings) {
            $custid = $GLOBALS['tf']->accounts->cross_reference($data['account_lid']);
            if ($custid !== false) {
                $GLOBALS['tf']->accounts->update($custid, $new_data);
            }
        }
        add_output('Authorized CC');
    }
}
