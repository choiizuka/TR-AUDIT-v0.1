<?php
/**
 * Plain PHP Example - TR-AUDIT LITE
 * (C) 2026 CHOIIZUKA.COM - TR-NC-1.0
 */
require_once __DIR__ . '/../src/tr_audit_hook.php';

// 環境変数または定数でGAS URLを設定
putenv('TR_AUDIT_ENDPOINT=https://script.google.com/macros/s/YOUR_GAS_ID/exec');

// 顧客一覧表示
function show_customer_list($customer_id) {
    // ... 既存のDB取得処理 ...
    $rows = []; // DBから取得した想定

    // ★ここに1行追加するだけ
    tr_audit('CUSTOMER_VIEW', [
        'customer_id' => $customer_id,
        'target_type' => 'customers',
        'count' => 1,
        'source_system' => 'crm'
    ]);

    return $rows;
}

// 一括エクスポート
function export_customers($rows) {
    tr_audit('customer.export', [
        'count' => count($rows),
        'target_type' => 'customers',
        'target_ref' => 'export:'.date('Y-m-d'),
        'file' => 'export.csv'
    ]);
    // ... CSV生成 ...
}

// 権限変更
function change_role($target_user_id, $new_role) {
    tr_audit('PRIV_CHANGE', [
        'target_type' => 'user_role',
        'target_ref' => $target_user_id,
        'meta' => ['new_role' => $new_role]
    ]);
}
