/** 
 * TR-AUDIT Immutable Receiver v1.0 - GAS Sample
 * (C) 2026 CHOIIZUKA.COM - TR Non-Commercial Defense License v1.0
 * Origin: https://choiizuka.com/aibbs/chatlog/20261028-112353-24b6eb48.html
 * 
 * 設置手順:
 * 1. Google Spreadsheet 新規作成 (シート名はそのまま)
 * 2. 拡張機能 > Apps Script → このコードを貼り付け
 * 3. プロパティ: ALERT_WEBHOOK_URL (任意) にDiscord/Slack Webhook URLを設定
 * 4. デプロイ > 新しいデプロイ > 種類: ウェブアプリ
 *    - 実行: 自分
 *    - アクセス: 全員 (匿名含む) - 認証は X-TR-Audit-Secret ヘッダーで検証推奨
 * 5. 発行URLを TR_AUDIT_ENDPOINT に設定
 * 
 * 仕様:
 * - 追記専用 (Append-Only) - 削除・編集APIなし
 * - ハッシュチェーン検証
 * - 100件超えで自動アラート
 */

const SHEET_NAME = 'TR-AUDIT-LOG';
const CHECKPOINT_INTERVAL = 100; // 100件ごとにチェックポイント

function doPost(e) {
  try {
    const raw = e.postData ? e.postData.contents : '';
    if (!raw) return _json({status: 'ERROR', message: 'empty body'});
    
    const data = JSON.parse(raw);
    
    // 基本検証
    if (!data.event_id || !data.action) {
      return _json({status: 'ERROR', message: 'invalid event'});
    }

    const ss = SpreadsheetApp.getActiveSpreadsheet();
    let sheet = ss.getSheetByName(SHEET_NAME);
    if (!sheet) {
      sheet = ss.insertSheet(SHEET_NAME);
      sheet.appendRow(['time','event_id','actor_id','action','target_type','count','result','prev_hash','event_hash','ip_hash','call_chain_hash','request_id','raw_json']);
    }

    // ハッシュチェーン検証 (任意)
    const lastRow = sheet.getLastRow();
    let lastHash = '0'.repeat(64);
    if (lastRow > 1) {
      const lastEventHash = sheet.getRange(lastRow, 9).getValue(); // I列 event_hash
      if (lastEventHash) lastHash = lastEventHash;
    }

    // 受信時の整合性チェック
    const receivedPrev = data.integrity ? data.integrity.prev_hash : '';
    const isChainOk = (receivedPrev === lastHash || receivedPrev === '0'.repeat(64) || lastRow === 1);

    // 追記 (Append-Only)
    sheet.appendRow([
      data.time || new Date().toISOString(),
      data.event_id,
      data.actor ? data.actor.id : (data.actor_id || 'unknown'),
      data.action,
      data.target ? data.target.type : (data.target_type || 'unknown'),
      data.target ? data.target.count : (data.record_count || data.count || 1),
      data.result || 'success',
      data.integrity ? data.integrity.prev_hash : '',
      data.integrity ? data.integrity.event_hash : '',
      data.src ? data.src.ip_hash : (data.ip_hash || ''),
      data.src ? data.src.call_chain_hash : '',
      data.request_id || '',
      JSON.stringify(data).substring(0, 5000) // 生JSONは最大5000文字まで
    ]);

    // チェックポイント & アラート
    const currentCount = sheet.getLastRow() - 1;
    if (data.action === 'EMERGENCY_KILL_ACTIVATED' || (data.target && data.target.count > 100)) {
      _sendAlert(`🚨 [TR-AUDIT CRITICAL] ${data.action} by ${data.actor ? data.actor.id : 'unknown'} count=${data.target ? data.target.count : 'N/A'} chain_ok=${isChainOk}`);
    } else if (currentCount % CHECKPOINT_INTERVAL === 0) {
      // 100件ごとにサイレントチェックポイントを別シートへ
      let cpSheet = ss.getSheetByName('CHECKPOINTS');
      if (!cpSheet) {
        cpSheet = ss.insertSheet('CHECKPOINTS');
        cpSheet.appendRow(['time','last_event_id','last_hash','count']);
      }
      cpSheet.appendRow([new Date().toISOString(), data.event_id, data.integrity ? data.integrity.event_hash : '', currentCount]);
    }

    // 受領証を返す (送信側が再送判定に使う)
    return _json({
      status: 'COMMITTED',
      event_id: data.event_id,
      event_hash: data.integrity ? data.integrity.event_hash : '',
      received_at: new Date().toISOString(),
      chain_ok: isChainOk,
      count: currentCount
    });

  } catch (err) {
    return _json({status: 'ERROR', message: err.message, stack: err.stack});
  }
}

function doGet(e) {
  // ヘルスチェック用
  return _json({status: 'TR-AUDIT Receiver OK', time: new Date().toISOString(), sheet: SHEET_NAME});
}

function _json(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}

function _sendAlert(message) {
  try {
    const webhook = PropertiesService.getScriptProperties().getProperty('ALERT_WEBHOOK_URL');
    if (!webhook) return;
    UrlFetchApp.fetch(webhook, {
      method: 'post',
      contentType: 'application/json',
      payload: JSON.stringify({content: message})
    });
  } catch(e) {}
}
