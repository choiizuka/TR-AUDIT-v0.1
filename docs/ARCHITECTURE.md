# TR-AUDIT ARCHITECTURE v1.0 - Claude Sample Edition

## 1. 目的
既存の顧客データを保有する企業が、WAFやWebアクセスログだけでは防げない「正規アカウント奪取後の持ち出し」を検知・証拠保全するための最小構成。

## 2. 設計原則 (Charter準拠)
- 通常アクセスは既存に任せる、関係ログだけ保存
- 正規アクセスも含めて記録 (不正かどうかは後で判断)
- 顧客本文・パスワード・トークンは記録しない
- 本番サーバーと保管先の権限分離 (別アカウント/GAS)
- ハッシュチェーンで改ざん検知
- 自動遮断は初期は任意、まず記録と検証

## 3. 共通イベント仕様 (5項目 + 整合性)

```json
{
  "schema_version": "1.0",
  "event_id": "ev_8f3c...",
  "time": "2026-10-28T02:00:00Z",
  "actor": {"id": "user_123", "type": "human", "role": "support"},
  "action": "customer.export",
  "target": {"type": "customers", "ref": "search:2026-10-28", "count": 1200},
  "result": "success",
  "request_id": "req_abc",
  "source_system": "crm",
  "integrity": {"prev_hash": "abc...", "event_hash": "def..."},
  "src": {"ip_hash": "a1b2...", "ua_hash": "c3d4..."}
}
```

- 禁止: password, session cookie, access token, secret key, 顧客本文

## 4. 3 Modes (好きなの使ってね❤)

### Mode1 LITE STUB - Claude推奨 / 最小10行
- 重要箇所に tr_audit() を1行差し込むだけ
- 非同期0.5秒でGASへPOST
- 負荷ほぼゼロ

### Mode2 CONTEXT DUAL - 特許核
- `debug_backtrace()` + `getmypid()` + `posix_getppid()` をSHA256合成
- 正規画面経由と裏スクリプト直接DB叩きを区別
- GMO型の「正規アカウントで裏からダンプ」を暴く

### Mode3 HARD-KILL - 自律自爆
- 1分間に同一actorが100件以上触ったら `GLOBAL_SESSION_SALT` ローテーション
- 全セッション即死、WebSocket切断
- 公式の教訓: パスワード変更だけではセッション残留する

## 5. 完全性検証

H_i = SHA256( H_{i-1} || canonical(D_i) )

- canonical(D_i): JSON正規化 (キー昇順、空白なし)
- チェックポイントを別領域へ1時間ごとに保存
- 検証ツール: 連番欠落、ハッシュ不一致、チェックポイント不一致を報告

## 6. 保管先

- 案A: 企業既存SIEM
- 案B: 別アカウント追記専用S3 (Object Lock)
- 案C: 利用企業管理GAS (本リポジトリサンプル)
- 案D: ローカルキュー + 後送信

GASは必須ではない、選べる。

## 7. 商用利用の線引き (2026-10-28変更)

- OK: 個人・研究・教育・NPO・社内検証 (自社データを自社で守る)
- NG (要相談): SaaSに組み込んで販売、顧客向け有料サービスの一部として提供、再販

例: ECサイト運営者が自社WPに組み込む → OK (内部利用)
例: セキュリティベンダーが自社WAF製品に組み込んで販売 → 要商用ライセンス

## 8. 公開証跡

- Origin: https://choiizuka.com/aibbs/chatlog/20261028-112353-24b6eb48.html (2026-10-28 11:23:53 JST)
- GitHub: (公開時に記入)
- SHA256 manifest: provenance/SHA256SUMS
- License: TR Non-Commercial Defense License v1.0
