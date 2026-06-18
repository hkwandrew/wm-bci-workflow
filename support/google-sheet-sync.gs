/**
 * Google Apps Script endpoint for approved BCI opportunity sync.
 *
 * Required Script Properties:
 * - BCI_SPREADSHEET_ID
 * - BCI_SHEET_NAME
 * - BCI_SHARED_SECRET
 */

function doPost(e) {
  try {
    var request = parseRequest_(e);
    var spreadsheet = SpreadsheetApp.openById(request.config.spreadsheetId);
    var sheet = spreadsheet.getSheetByName(request.config.sheetName);

    if (!sheet) {
      throw new Error('Target sheet not found: ' + request.config.sheetName);
    }

    var lock = LockService.getScriptLock();
    lock.waitLock(30000);

    try {
      ensureHeadersMatch_(sheet, request.payload.headers);

      var logSheet = getOrCreateLogSheet_(spreadsheet);
      if (findLoggedEntryRow_(logSheet, request.payload.entryId)) {
        return jsonResponse_({
          ok: true,
          disposition: 'duplicate',
          entryId: request.payload.entryId
        });
      }

      appendRow_(sheet, request.payload.row);
      logSync_(logSheet, request.payload);

      return jsonResponse_({
        ok: true,
        disposition: 'appended',
        entryId: request.payload.entryId
      });
    } finally {
      lock.releaseLock();
    }
  } catch (error) {
    return jsonResponse_({
      ok: false,
      error: error && error.message ? error.message : String(error)
    });
  }
}

function parseRequest_(e) {
  if (!e || !e.postData || !e.postData.contents) {
    throw new Error('Missing request body.');
  }

  var config = getConfig_();
  var rawBody = String(e.postData.contents);
  var providedSignature = e.parameter && e.parameter.signature ? String(e.parameter.signature) : '';

  // Apps Script web-app event objects expose query params and postData, but not
  // arbitrary request headers, so the HMAC must be readable from the URL.
  if (!providedSignature) {
    throw new Error('Missing request signature.');
  }

  var expectedSignature = computeSignature_(rawBody, config.sharedSecret);
  if (providedSignature.toLowerCase() !== expectedSignature.toLowerCase()) {
    throw new Error('Invalid request signature.');
  }

  var payload = JSON.parse(rawBody);
  validatePayload_(payload);

  return {
    config: config,
    payload: payload
  };
}

function getConfig_() {
  var properties = PropertiesService.getScriptProperties();
  var spreadsheetId = String(properties.getProperty('BCI_SPREADSHEET_ID') || '');
  var sheetName = String(properties.getProperty('BCI_SHEET_NAME') || '');
  var sharedSecret = String(properties.getProperty('BCI_SHARED_SECRET') || '');

  if (!spreadsheetId || !sheetName || !sharedSecret) {
    throw new Error('Missing BCI_SPREADSHEET_ID, BCI_SHEET_NAME, or BCI_SHARED_SECRET script property.');
  }

  return {
    spreadsheetId: spreadsheetId,
    sheetName: sheetName,
    sharedSecret: sharedSecret
  };
}

function validatePayload_(payload) {
  if (!payload || payload.event !== 'bci_entry_approved') {
    throw new Error('Unexpected event payload.');
  }

  if (!payload.entryId) {
    throw new Error('Missing entryId.');
  }

  if (!Array.isArray(payload.headers) || !payload.headers.length) {
    throw new Error('Missing headers array.');
  }

  if (!Array.isArray(payload.row)) {
    throw new Error('Missing row array.');
  }

  if (payload.headers.length !== payload.row.length) {
    throw new Error('Header and row lengths do not match.');
  }
}

function ensureHeadersMatch_(sheet, headers) {
  var width = headers.length;
  var headerRange = sheet.getRange(1, 1, 1, width);
  var currentHeaders = headerRange.getValues()[0];
  var hasExistingHeaders = currentHeaders.some(function(cell) {
    return String(cell || '') !== '';
  });

  if (!hasExistingHeaders) {
    headerRange.setValues([headers]);
    return;
  }

  for (var i = 0; i < headers.length; i++) {
    if (String(currentHeaders[i] || '') !== String(headers[i] || '')) {
      throw new Error('Target sheet headers do not match the WordPress export headers.');
    }
  }
}

function getOrCreateLogSheet_(spreadsheet) {
  var logSheet = spreadsheet.getSheetByName('_wm_bci_sync_log');

  if (!logSheet) {
    logSheet = spreadsheet.insertSheet('_wm_bci_sync_log');
    logSheet.getRange(1, 1, 1, 4).setValues([[
      'entryId',
      'approvedAt',
      'syncedAt',
      'entryUrl'
    ]]);
    logSheet.hideSheet();
  }

  return logSheet;
}

function findLoggedEntryRow_(logSheet, entryId) {
  var lastRow = logSheet.getLastRow();

  if (lastRow < 2) {
    return 0;
  }

  var values = logSheet.getRange(2, 1, lastRow - 1, 1).getValues();
  var target = String(entryId);

  for (var i = 0; i < values.length; i++) {
    if (String(values[i][0] || '') === target) {
      return i + 2;
    }
  }

  return 0;
}

function appendRow_(sheet, row) {
  var nextRow = sheet.getLastRow() + 1;
  ensureSheetCapacity_(sheet, nextRow, row.length);
  var templateRow = nextRow > 2 ? nextRow - 1 : 2;

  if (templateRow >= 2 && templateRow < nextRow) {
    copyRowPresentation_(sheet, templateRow, nextRow, row.length);
  }

  sheet.getRange(nextRow, 1, 1, row.length).setValues([row]);
}

function copyRowPresentation_(sheet, sourceRow, targetRow, columnCount) {
  var sourceRange = sheet.getRange(sourceRow, 1, 1, columnCount);
  var targetRange = sheet.getRange(targetRow, 1, 1, columnCount);

  sourceRange.copyTo(targetRange, SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
  sourceRange.copyTo(targetRange, SpreadsheetApp.CopyPasteType.PASTE_DATA_VALIDATION, false);
  sheet.setRowHeight(targetRow, sheet.getRowHeight(sourceRow));
}

function ensureSheetCapacity_(sheet, requiredRow, requiredColumnCount) {
  var maxRows = sheet.getMaxRows();
  var maxColumns = sheet.getMaxColumns();

  if (requiredRow > maxRows) {
    sheet.insertRowsAfter(maxRows, requiredRow - maxRows);
  }

  if (requiredColumnCount > maxColumns) {
    sheet.insertColumnsAfter(maxColumns, requiredColumnCount - maxColumns);
  }
}

function logSync_(logSheet, payload) {
  logSheet.appendRow([
    String(payload.entryId),
    String(payload.approvedAt || ''),
    isoTimestamp_(new Date()),
    String(payload.sourceEntryUrl || '')
  ]);
}

function isoTimestamp_(date) {
  return Utilities.formatDate(date, 'Etc/UTC', "yyyy-MM-dd'T'HH:mm:ss'Z'");
}

function computeSignature_(rawBody, secret) {
  var bytes = Utilities.computeHmacSha256Signature(rawBody, secret);
  return bytesToHex_(bytes);
}

function bytesToHex_(bytes) {
  var output = [];

  for (var i = 0; i < bytes.length; i++) {
    var value = bytes[i];

    if (value < 0) {
      value += 256;
    }

    var hex = value.toString(16);
    output.push(hex.length === 1 ? '0' + hex : hex);
  }

  return output.join('');
}

function jsonResponse_(payload) {
  return ContentService
    .createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}
