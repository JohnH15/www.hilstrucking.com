/**
 * HILS Trucking: website leads -> Google Sheet
 *
 * 1. Change TOKEN below to your own secret (any long random text).
 * 2. Run setup() once (Run button), allow access.
 * 3. Deploy > New deployment > Web app: Execute as "Me", Who has access "Anyone".
 * 4. Put the Web App URL and the same TOKEN into config.php on the server (google_sheets section).
 */

const TOKEN = 'CHANGE-ME-to-a-long-random-secret';
const SHEET_NAME = 'Leads';
const HEADERS = ['Date', 'Name', 'Phone', 'Email', 'Position', 'Experience', 'CDL State',
                 'Comments', 'Calls/SMS consent', 'Language', 'Source', 'Status', 'Recruiter', 'Notes'];
const STATUSES = ['New', 'Called', 'No answer', 'In onboarding', 'Hired', 'Not a fit'];

function setup() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sh = ss.getSheetByName(SHEET_NAME) || ss.insertSheet(SHEET_NAME);
  if (sh.getLastRow() === 0) sh.appendRow(HEADERS);
  sh.setFrozenRows(1);
  sh.getRange(1, 1, 1, HEADERS.length).setFontWeight('bold').setBackground('#004aad').setFontColor('#ffffff');
  sh.setColumnWidth(1, 150); sh.setColumnWidth(2, 170); sh.setColumnWidth(3, 130); sh.setColumnWidth(8, 260); sh.setColumnWidth(14, 260);
  sh.getRange('A2:A').setNumberFormat('yyyy-mm-dd hh:mm');
  const statusCol = HEADERS.indexOf('Status') + 1;
  const rule = SpreadsheetApp.newDataValidation().requireValueInList(STATUSES, true).setAllowInvalid(false).build();
  sh.getRange(2, statusCol, 2000, 1).setDataValidation(rule);
  const range = sh.getRange(2, 1, 2000, HEADERS.length);
  const col = String.fromCharCode(64 + statusCol);
  const color = (status, bg) => SpreadsheetApp.newConditionalFormatRule()
    .whenFormulaSatisfied('=$' + col + '2="' + status + '"').setBackground(bg).setRanges([range]).build();
  sh.setConditionalFormatRules([
    color('New', '#fff4d6'), color('Hired', '#e3f8ec'), color('Not a fit', '#f1f5f9'), color('In onboarding', '#e5edf8'),
  ]);
}

function doPost(e) {
  const lock = LockService.getScriptLock();
  lock.tryLock(10000);
  try {
    const data = JSON.parse((e && e.postData && e.postData.contents) || '{}');
    if (data.token !== TOKEN) return json_({ ok: false, error: 'unauthorized' });
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const sh = ss.getSheetByName(SHEET_NAME) || (setup(), ss.getSheetByName(SHEET_NAME));
    // text that starts with = + - @ is stored as plain text, never as a formula
    const t = v => { v = (v == null ? '' : String(v)).slice(0, 3000); return /^[=+\-@]/.test(v) ? "'" + v : v; };
    sh.appendRow([new Date(), t(data.name), "'" + (data.phone || ''), t(data.email), t(data.role), t(data.exp), t(data.state),
                  t(data.notes), t(data.sms_consent), t(data.lang), t(data.source), 'New', '', '']);
    return json_({ ok: true });
  } catch (err) {
    return json_({ ok: false, error: String(err) });
  } finally {
    lock.releaseLock();
  }
}

function json_(o) {
  return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON);
}
