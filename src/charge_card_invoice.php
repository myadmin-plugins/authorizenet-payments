<?php
/**
 * Billing Related Services
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2025
 * @package MyAdmin
 * @category Billing
 */

/**
 * charge_card_invoice()
 * charges an invoice to the clients creditcard.
 *
 * @return void
 * @throws \Exception
 * @throws \SmartyException
 */
function charge_card_invoice()
{
    $custid = $GLOBALS['tf']->session->account_id;
    $db = clone $GLOBALS['tf']->db;
    $module = 'default';
    if (isset($GLOBALS['tf']->variables->request['module'])) {
        $module = $GLOBALS['tf']->variables->request['module'];
        $module = get_module_name($module);
        $db = get_module_db($module);
        $custid = get_custid($custid, $module);
    }
    function_requirements('charge_card');
    //$table->set_title($GLOBALS['modules'][$module]['TBLNAME'].' Services Package Management');
    if ($GLOBALS['tf']->ima == 'client') {
        $db->query("select * from invoices where invoices_custid={$custid} and invoices_id='".(int) $GLOBALS['tf']->variables->request['invoice']."'", __LINE__, __FILE__);
    } else {
        $db->query("select * from invoices where invoices_id='".(int) $GLOBALS['tf']->variables->request['invoice']."'", __LINE__, __FILE__);
    }
    if ($db->num_rows() > 0) {
        $db->next_record(MYSQL_ASSOC);
        $data = $GLOBALS['tf']->accounts->read($db->Record['invoices_custid']);
        if ((!isset($data['disable_cc']) || $data['disable_cc'] != 1) && verify_csrf_referrer(__LINE__, __FILE__)) {
            if (charge_card($db->Record['invoices_custid'], $db->Record['invoices_amount'], $db->Record['invoices_id'], $module, true)) {
                dialog('Credit Card Charged', 'The Creditcard Charge has been accepted.');
                add_output('The Creditcard Charge has been accepted.');
            } else {
                dialog('Error', 'There was an error processing your Credit Card transaction.');
                add_output('There was an error processing your Credit Card transaction.');
            }
        } else {
            dialog('CC Disabled', 'CreditCard Billing was disabled for this account.');
            add_output('CreditCard Billing was disabled for this account.');
        }
    } else {
        dialog('Invalid Invoice', 'Invalid Invoice Number Passed or missing Module information.');
        add_output('Invalid Invoice Number Passed or missing Module information.');
    }
}
