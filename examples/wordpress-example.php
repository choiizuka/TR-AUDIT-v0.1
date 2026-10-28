<?php
/**
 * WordPress / WooCommerce Example - TR-AUDIT
 * (C) 2026 CHOIIZUKA.COM - TR-NC-1.0
 * functions.php または プラグインに貼り付け
 */
require_once __DIR__ . '/../src/tr_audit_hook.php';
require_once __DIR__ . '/../src/tr_audit_hardkill.php';

define('TR_AUDIT_ENDPOINT', 'https://script.google.com/macros/s/YOUR_GAS_ID/exec');

// 注文閲覧時
add_action('woocommerce_admin_order_data_after_order_details', function($order){
    tr_audit('CUSTOMER_VIEW', [
        'customer_id' => $order->get_customer_id(),
        'target_type' => 'woo_orders',
        'count' => 1,
        'source_system' => 'woocommerce'
    ]);
});

// 一括エクスポート時 (例: WP All Exportなど)
add_action('pmxe_after_export', function($export_id, $records){
    $cnt = tr_audit_count_and_check(get_current_user_id(), count($records));
    tr_audit('customer.export', [
        'count' => count($records),
        'target_type' => 'woo_export',
        'target_ref' => 'export_id:'.$export_id
    ]);
    tr_enforce_kill_switch_if_breached($cnt, (string)get_current_user_id());
}, 10, 2);

// 権限変更時
add_action('set_user_role', function($user_id, $role, $old_roles){
    tr_audit('PRIV_CHANGE', [
        'target_type' => 'wp_role',
        'target_ref' => $user_id,
        'meta' => ['old' => $old_roles, 'new' => $role]
    ]);
}, 10, 3);
