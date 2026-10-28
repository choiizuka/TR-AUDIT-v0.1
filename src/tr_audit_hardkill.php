<?php
/**
 * TR-AUDIT HARD-KILL v1.0 - Autonomous Session Termination
 * (C) 2026 CHOIIZUKA.COM - TR Non-Commercial Defense License v1.0
 * Origin: https://choiizuka.com/aibbs/chatlog/20261028-112353-24b6eb48.html
 * 
 * Mode3: GMO事件の教訓 - パスワード変更だけではセッション残留する
 * 閾値超過で全セッションを自爆させるキルスイッチ
 */

declare(strict_types=1);

if (!function_exists('tr_enforce_kill_switch_if_breached')) {
    /**
     * @param int $fetch_count 直近1分の取得件数 (呼び出し側でカウント)
     * @param string $actor_id 実行主体
     */
    function tr_enforce_kill_switch_if_breached(int $fetch_count, string $actor_id = ''): void {
        $threshold = (int)(getenv('TR_AUDIT_KILL_THRESHOLD') ?: 100);
        if ($fetch_count <= $threshold) return;

        // 1. グローバルソルト ローテーション (全セッションを一撃で無効化)
        // APCu / Redis / DB に保存している想定
        if (function_exists('apcu_store')) {
            apcu_store('GLOBAL_SESSION_SALT', bin2hex(random_bytes(32)));
        }
        // WordPress例: wp_options に保存
        if (function_exists('update_option')) {
            update_option('tr_global_session_salt', bin2hex(random_bytes(32)), true);
        }
        // ファイルベースセッションならセッション保存先を無効化するフラグ
        @file_put_contents(sys_get_temp_dir() . '/tr_audit_kill.flag', gmdate('c') . '|' . $actor_id . '|' . $fetch_count);

        // 2. 現在のセッションを物理破壊
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // 3. 最優先警報を監査サーバーへ (tr_auditが定義されていれば使う)
        if (function_exists('tr_audit')) {
            tr_audit('EMERGENCY_KILL_ACTIVATED', [
                'reason' => 'BULK_FETCH_THRESHOLD_EXCEEDED',
                'count' => $fetch_count,
                'actor_id' => $actor_id,
                'target_type' => 'session',
                'result' => 'kill_triggered'
            ]);
        }

        // 4. 通信即時切断
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'BREACH_DETECTED_ALL_SESSIONS_TERMINATED',
            'event' => 'EMERGENCY_KILL',
            'message' => 'Unusual bulk access detected. All sessions have been terminated for protection.'
        ]);
        exit;
    }

    /**
     * 簡易カウンター (Redis/APCuが無い環境向け - ファイルベース)
     * 1分間の同一actorのアクセス件数を数える
     */
    function tr_audit_count_and_check(string $actor_id, int $increment = 1): int {
        $file = sys_get_temp_dir() . '/tr_audit_counter_' . md5($actor_id) . '.json';
        $now = time();
        $data = ['start' => $now, 'count' => 0];
        if (is_file($file)) {
            $json = @file_get_contents($file);
            $data = json_decode($json, true) ?: $data;
            // 1分経過でリセット
            if ($now - ($data['start'] ?? $now) > 60) {
                $data = ['start' => $now, 'count' => 0];
            }
        }
        $data['count'] += $increment;
        @file_put_contents($file, json_encode($data));
        return $data['count'];
    }
}

// 使用例:
// $cnt = tr_audit_count_and_check($_SESSION['user_id'] ?? 'anon', count($rows));
// tr_enforce_kill_switch_if_breached($cnt, $_SESSION['user_id'] ?? '');
