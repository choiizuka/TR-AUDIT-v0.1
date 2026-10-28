<?php
/**
 * TR-AUDIT LITE STUB v1.0 - Claude Sample Edition
 * (C) 2026 CHOIIZUKA.COM - TR Non-Commercial Defense License v1.0
 * Origin: https://choiizuka.com/aibbs/chatlog/20261028-112353-24b6eb48.html
 * First Pub: 2026-10-28 11:23:53 JST
 * 
 * Mode1: 最小10行スタブ - 既存コードに1行差し込むだけ
 * - 顧客本文・パスワードは記録しない
 * - 本番サーバーとは別権限の外部へ追記専用送信
 * - 商用利用は要相談 (LICENSE参照) - 好きに改造してね❤ でも商用は相談してね
 * 
 * @license TR-NC-1.0
 */

declare(strict_types=1);

if (!function_exists('tr_audit')) {
    /**
     * 正規アクセスの重要操作だけを外部へ退避
     * 
     * @param string $action 例: 'CUSTOMER_VIEW', 'customer.export', 'AUTH_SUCCESS'
     * @param array $meta 追加情報 ['customer_id'=>123, 'count'=>1, 'target_type'=>'customers']
     */
    function tr_audit(string $action, array $meta = []): void {
        // 1. 環境変数から受信先取得 (GASや別アカウントエンドポイント)
        $endpoint = getenv('TR_AUDIT_ENDPOINT');
        if (!$endpoint) {
            $endpoint = defined('TR_AUDIT_ENDPOINT') ? TR_AUDIT_ENDPOINT : '';
        }
        if (empty($endpoint)) {
            // 未設定なら何もしない (業務を止めない)
            return;
        }

        // 2. 実行コンテキスト収集 (最小5項目 + 整合性)
        $event_id = 'ev_' . bin2hex(random_bytes(8));
        $now = gmdate('c');
        $actor_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 'SYSTEM';
        $session_hash = session_id() ? substr(hash('sha256', session_id()), 0, 16) : 'none';
        
        // IP/UAはハッシュのみ (本文保存しない原則)
        $ip_raw = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua_raw = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip_hash = substr(hash('sha256', $ip_raw), 0, 12);
        $ua_hash = substr(hash('sha256', $ua_raw), 0, 12);

        // 3. イベント生成 (顧客本文は含めない)
        $event = [
            'schema_version' => '1.0',
            'event_id' => $event_id,
            'time' => $now,
            'actor' => [
                'id' => (string)$actor_id,
                'type' => 'human',
                'role' => $_SESSION['role'] ?? 'unknown'
            ],
            'action' => $action,
            'target' => [
                'type' => $meta['target_type'] ?? 'unknown',
                'ref' => $meta['target_ref'] ?? ($meta['customer_id'] ?? 'n/a'),
                'count' => $meta['count'] ?? 1
            ],
            'result' => $meta['result'] ?? 'success',
            'request_id' => $meta['request_id'] ?? ($_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_')),
            'source_system' => $meta['source_system'] ?? 'app',
            'src' => [
                'ip_hash' => $ip_hash,
                'ua_hash' => $ua_hash,
                'session_hash' => $session_hash
            ],
            'meta' => [
                // 必要最小限のみ、本文は入れない
                'count' => $meta['count'] ?? null,
                'file' => isset($meta['file']) ? basename($meta['file']) : null,
            ]
        ];

        // 4. ハッシュチェーン (前回のハッシュを読み込み)
        $prev_hash_file = sys_get_temp_dir() . '/tr_audit_prev_hash.txt';
        $prev_hash = @file_get_contents($prev_hash_file) ?: str_repeat('0', 64);
        $canonical = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $event_hash = hash('sha256', $prev_hash . $canonical);
        $event['integrity'] = [
            'prev_hash' => $prev_hash,
            'event_hash' => $event_hash
        ];
        @file_put_contents($prev_hash_file, $event_hash);

        // 5. 非同期送信 (業務をブロックしない - 0.5秒タイムアウト)
        $json = json_encode($event);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nConnection: close\r\nX-TR-Audit-Version: 1.0\r\n",
                'content' => $json,
                'timeout' => 0.5,
                'ignore_errors' => true
            ]
        ]);
        // @で握りつぶす - 送信失敗で業務を止めない
        @file_get_contents($endpoint, false, $ctx);
    }
}

// 使用例:
// tr_audit('CUSTOMER_VIEW', ['customer_id' => 1234, 'target_type' => 'customers']);
// tr_audit('customer.export', ['count' => count($rows), 'target_type' => 'customers', 'target_ref' => 'search:2026-10-28']);
