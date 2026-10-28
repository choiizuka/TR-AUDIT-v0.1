<?php
/**
 * TR-AUDIT CONTEXT DUAL v1.0 - Callstack Fingerprint Mode
 * (C) 2026 CHOIIZUKA.COM - TR Non-Commercial Defense License v1.0
 * Origin: https://choiizuka.com/aibbs/chatlog/20261028-112353-24b6eb48.html
 * 
 * Mode2: 特許核 - 正規アカウント奪取後の裏スクリプト実行を暴く
 * - debug_backtrace() + PID + PPID + ホスト名を合成して生体反応署名
 * - 通常の管理画面経由と、攻撃者の直接DB叩きを区別
 */

declare(strict_types=1);

if (!function_exists('tr_audit_strict')) {
    function tr_audit_strict(string $action, array $meta = []): void {
        $endpoint = getenv('TR_AUDIT_ENDPOINT') ?: (defined('TR_AUDIT_ENDPOINT') ? TR_AUDIT_ENDPOINT : '');
        if (empty($endpoint)) return;

        $event_id = 'ev_' . bin2hex(random_bytes(8));
        $now = gmdate('c');

        // 【新規アイデア①】コールスタック生体反応採取
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
        $chain = [];
        foreach ($trace as $t) {
            $cls = $t['class'] ?? '';
            $func = $t['function'] ?? '';
            if ($cls || $func) $chain[] = ($cls ? $cls.'::' : '') . $func;
        }
        // 3階層目までが通常のMVC経由か、直接呼ばれたかを判定できる
        $call_chain_str = implode('->', $chain);
        $context_sig = hash('sha256', $call_chain_str . '|' . php_uname('n') . '|' . getmypid() . '|' . (function_exists('posix_getppid') ? posix_getppid() : '0'));

        $actor_id = $_SESSION['user_id'] ?? 'ANON';
        $ip_hash = substr(hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), 0, 12);

        $event = [
            'schema_version' => '1.0',
            'event_id' => $event_id,
            'time' => $now,
            'actor' => ['id' => (string)$actor_id, 'type' => 'human'],
            'action' => $action,
            'target' => ['type' => $meta['target_type'] ?? 'customers', 'count' => $meta['count'] ?? 1],
            'result' => 'success',
            'request_id' => uniqid('req_'),
            'source_system' => 'app-strict',
            'src' => ['ip_hash' => $ip_hash, 'call_chain_hash' => substr($context_sig, 0, 16)],
            // 生体反応フル署名 (検証用)
            'context_fingerprint' => [
                'call_chain' => array_slice($chain, 0, 5),
                'stack_hash' => $context_sig,
                'pid' => getmypid(),
                'ppid' => function_exists('posix_getppid') ? posix_getppid() : null,
                'hostname_hash' => substr(hash('sha256', php_uname('n')), 0, 12)
            ],
            'meta' => $meta
        ];

        // ハッシュチェーン
        $prev_file = sys_get_temp_dir() . '/tr_audit_prev_hash.txt';
        $prev = @file_get_contents($prev_file) ?: str_repeat('0',64);
        $event['integrity'] = [
            'prev_hash' => $prev,
            'event_hash' => hash('sha256', $prev . json_encode($event))
        ];
        @file_put_contents($prev_file, $event['integrity']['event_hash']);

        // cURLで少しだけ信頼性高く (でもブロックしない)
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-TR-Audit-Mode: strict']);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        @curl_exec($ch);
        @curl_close($ch);
    }
}
