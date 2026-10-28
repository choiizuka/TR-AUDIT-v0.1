# 🛡 TR-AUDIT / OKEHAZAMA DEFENSE v1.0 => v0.1
### 正規アクセス・重要データ接触特化型 最小監査アーキテクチャ

> **好きなの使ってね❤ でも商用は相談してね**  
> サンプルコード使ってもいいし、好きに改造して使ってね、勝手に使ってね❤  
> 商用利用（SaaSに組み込み・再販・有料サービスの一部として提供）は要相談

**Public Proof / First Publication (確定タイムスタンプ):**  
https://choiizuka.com/aibbs/chatlog/20261028-112353-24b6eb48.html  
**Date:** 2026-10-28 11:23:53 JST  
**Author:** CHOIIZUKA (ホワイトハッカー / TR-AI System Architect)  
**License:** TR Non-Commercial Defense License v1.0

---

## 🎯 何を作ったの？

GMOインターネット「お名前.com デスクトップクラウド」事件で露呈した「WAFやWebアクセスログでは正規アカウント奪取後の持ち出しを止められない」問題への最小回答。

- 通常のWebアクセス (毎秒数千件) は既存のWAF/Nginx/Apacheに任せる
- **重要データに触れた正規アクセスだけ**を5項目で切り出して外部へ退避
- サーバーが乗っ取られても消されない「追記専用・改ざん検知」構成

### 思想: 持たない・絞る・逃がす・囮

1. **持たない:** 顧客本文・パスワード・トークンはログに書かない
2. **絞る:** 顧客閲覧 / 一括出力 / 権限変更 / 認証 / セッション だけ
3. **逃がす:** ローカルに残さず外部 (GAS / 別アカウントS3) へ0.5秒でPOST
4. **囮:** カナリアデータ (偽顧客ID) に触れたら即アラート

## 📦 入ってるもの

```
src/
  tr_audit_hook.php        - Mode1 LITE: 最小10行スタブ (Claude推し・一番綺麗)
  tr_audit_strict.php      - Mode2 CONTEXT DUAL: コールスタック生体反応合成
  tr_audit_hardkill.php    - Mode3 HARD-KILL: 閾値超過で全セッション自爆
  tr_receiver.gs           - GAS側: 追記専用 + ハッシュチェーン + Discord通知
examples/
  plain-php-example.php    - 素のPHPへの組み込み例
  wordpress-example.php    - WP + WooCommerce向け
docs/
  ARCHITECTURE.md          - 設計書
provenance/
  PUBLICATION-RECORD.md    - 公開証跡
```

## 🚀 10分クイックスタート (Mode1)

1. GASを作る: Google Spreadsheet → 拡張機能 → Apps Script → `src/tr_receiver.gs` 貼り付け → デプロイ (全員に公開)
2. PHPに1行足す:
```php
require_once __DIR__ . '/tr_audit_hook.php';
tr_audit('CUSTOMER_VIEW', ['customer_id' => 1234, 'count' => 1]);
```
3. 環境変数にGAS URL: `TR_AUDIT_ENDPOINT=https://script.google.com/macros/s/XXXX/exec`

これだけ。WordPressなら `functions.php` に `add_action('woocommerce_order_status_changed', ...)` でOK。

## 🍸 Claudeサンプルコードのこだわり (このリポジトリの核)

- **5項目だけ:** actor / action / target / time / src - 本文書かないからログが漏れても被害ゼロ
- **非同期0.5秒:** `stream_context_create + file_get_contents` でメイン処理を止めない
- **ハッシュチェーン:** `H_i = SHA256(H_{i-1} || canonical(D_i))` で1行消したら即バレ
- **受領証:** GASが `event_id + hash` を返す、送信側は受領まで再送
- **商用NG:** 個人・研究・社内検証・NPO防衛は自由、商用は `contact@choiizuka.com` まで

## 📩 自社サイトへの設置相談

自分でできない人向け:

> 自社サイトへの設置についてご相談されたい方はご連絡ください📩  
> ただし、1人しかいないので同時にたくさんの対応はできません😭  
> 金額は応相談でお願いするね😊

https://choiizuka.com/tr-audit/ のフォームから

## 🧑‍💻 開発者

- **CHOIIZUKA | ホワイトハッカー / AI開発・セキュリティ研究**
  - 所有者の許可を得た環境での検証と、防衛技術の設計・公開
- **内閣府知的財産戦略推進事務局 プリンシプル・コード 届出・協力 (3番目掲載)** → https://www.cas.go.jp/jp/seisakukaigi/titeki2/ai_principle_code/jigyousha_ichiran.html
- **Google / Microsoft AI訴訟中 (連絡しても返事がありません)** → 活動記録: https://note.com/choiizuka/n/n481f2f86f7ce
- **原点ログ:** https://choiizuka.com/aibbs/chatlog/20261028-112353-24b6eb48.html

## 🍸 ライセンス

TR Non-Commercial Defense License v1.0 - 詳細は LICENSE 参照  
「好きなの使ってね❤」は維持しつつ、商用利用は要相談に変更 (2026-10-28決定)

---

TR桶狭間戦略 — 好きなの使ってね❤ でも商用は相談してね

公式ページ:https://choiizuka.com/tr-audit/
(C)CHOIIZUKA.COM Truth-Science.
