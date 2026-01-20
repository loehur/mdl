# Quote Reply - Logging Guide

## 📋 Overview
Complete logging untuk tracking quote/reply message processing di sistem WhatsApp.

---

## 📝 Log File Location
```
api/logs/wa_quote.log
```

---

## 🔍 Log Entries & Meaning

### **INBOUND MESSAGES** (Webhook)

#### **1. Quote Detection**
```
📌 Quote detected - WAMID: wamid.xxx
```
**Meaning:** Webhook menerima message dengan quote reference  
**Action:** System akan mencari quoted message di database

---

#### **2. Quote Found - Inbound**
```
✓ Quote found in wa_messages_in - Body: Halo kak, bisa pesan laundry?
```
**Meaning:** Quoted message ditemukan di table wa_messages_in (inbound)  
**Data:** Menampilkan 50 karakter pertama dari message yang di-quote

---

#### **3. Quote Found - Outbound**
```
✓ Quote found in wa_messages_out - Body: Terima kasih sudah order
```
**Meaning:** Quoted message ditemukan di table wa_messages_out (outbound)  
**Data:** User reply ke message dari agent/sistem

---

#### **4. Quote NOT Found**
```
⚠ Quote message NOT FOUND in database - WAMID: wamid.xxx
```
**Meaning:** Message yang di-quote tidak ada di database  
**Possible Causes:**
- Message terlalu lama (sudah dihapus)
- Message dari sebelum sistem diimplementasi
- WAMID tidak match

---

#### **5. Fetch Error**
```
✗ ERROR fetching quoted message - WAMID: wamid.xxx | Error: Unknown column 'wamid'
```
**Meaning:** Database error saat query quoted message  
**Possible Causes:**
- Column belum ada
- Table schema berubah
- Connection issue

---

#### **6. Save Quote**
```
💾 Saving quote reference - ID: wamid.xxx, Body: Halo kak
```
**Meaning:** System akan menyimpan quote data ke wa_messages_in  
**Data:** WAMID dan preview body yang di-quote

---

#### **7. Quote Save Success**
```
✓ Quote saved successfully - MsgID: 12345, QuotedWAMID: wamid.xxx
```
**Meaning:** Quote data berhasil disimpan ke database  
**Data:** 
- MsgID: Local database ID untuk message baru
- QuotedWAMID: Reference ke message yang di-quote

---

#### **8. Quote Save Failed**
```
✗ Quote data failed to save - WAMID: wamid.xxx
```
**Meaning:** Database insert gagal (akan disertai error detail di log webhook)  
**Action:** Check database error di `webhook` log category

---

#### **9. WebSocket Push**
```
📡 Pushing to WebSocket with quote - WAMID: wamid.xxx, Body: Halo kak
```
**Meaning:** System mengirim quote data ke WebSocket untuk real-time update  
**Data:** Frontend akan terima quote info untuk display

---

### **OUTBOUND MESSAGES** (WhatsAppService)

#### **1. Quote Detection [OUTBOUND]**
```
📌 [OUTBOUND] Quote detected - WAMID: wamid.xxx
```
**Meaning:** Agent/System reply dengan quote ke user  
**Action:** System akan fetch original message untuk display

---

#### **2. Quote Found [OUTBOUND]**
```
✓ [OUTBOUND] Quote found in wa_messages_in - Body: Kapan bisa diambil?
```
**Meaning:** Original message (dari user) ditemukan di database  
**Data:** Agent reply ke message ini

---

#### **3. Quote NOT Found [OUTBOUND]**
```
⚠ [OUTBOUND] Quote message NOT FOUND in database - WAMID: wamid.xxx
```
**Meaning:** Original message tidak ada di database  
**Impact:** Quote akan tetap terkirim ke WhatsApp, tapi body tidak tersimpan

---

#### **4. Save Quote [OUTBOUND]**
```
💾 [OUTBOUND] Saving quote reference - ID: wamid.xxx, Body: Kapan bisa diambil?
```
**Meaning:** System menyimpan quote data ke wa_messages_out

---

#### **5. Quote Save Success [OUTBOUND]**
```
✓ [OUTBOUND] Quote saved successfully - MsgID: 67890, QuotedWAMID: wamid.xxx
```
**Meaning:** Outbound message dengan quote berhasil disimpan

---

#### **6. WebSocket Push [OUTBOUND]**
```
📡 [OUTBOUND] Pushing to WebSocket with quote - WAMID: wamid.xxx, Body: Kapan...
```
**Meaning:** Real-time update ke CRM frontend

---

## 🎯 Log Interpretation Examples

### **Example 1: Normal Inbound Quote**
```
[2026-01-20 10:30:15] 📌 Quote detected - WAMID: wamid.HBgNNjI4MTI5NTIyNjE5MxUCABIYIDRE...
[2026-01-20 10:30:15] ✓ Quote found in wa_messages_out - Body: Silahkan order ya kak
[2026-01-20 10:30:15] 💾 Saving quote reference - ID: wamid.HBgN..., Body: Silahkan order ya kak
[2026-01-20 10:30:15] ✓ Quote saved successfully - MsgID: 12345, QuotedWAMID: wamid.HBgN...
[2026-01-20 10:30:15] 📡 Pushing to WebSocket with quote - WAMID: wamid.HBgN..., Body: Silahkan order ya kak
```
**Status:** ✅ SUKSES - Quote berhasil diproses lengkap

---

### **Example 2: Quote Message Not Found**
```
[2026-01-20 10:35:20] 📌 Quote detected - WAMID: wamid.OLD123456...
[2026-01-20 10:35:20] ⚠ Quote message NOT FOUND in database - WAMID: wamid.OLD123456...
[2026-01-20 10:35:20] 💾 Saving quote reference - ID: wamid.OLD123456..., Body: NULL
[2026-01-20 10:35:20] ✓ Quote saved successfully - MsgID: 12346, QuotedWAMID: wamid.OLD123456...
```
**Status:** ⚠️ PARTIAL - Message tersimpan tapi quoted body tidak ada  
**Impact:** Frontend tidak bisa tampilkan preview quoted message

---

### **Example 3: Database Error**
```
[2026-01-20 10:40:30] 📌 Quote detected - WAMID: wamid.HBgN...
[2026-01-20 10:40:30] ✗ ERROR fetching quoted message - WAMID: wamid.HBgN... | Error: Unknown column 'quoted_message_id'
[2026-01-20 10:40:30] ✗ DB INSERT FAILED - Error (1054): Unknown column 'quoted_message_id'
[2026-01-20 10:40:30] ✗ Quote data failed to save - WAMID: wamid.HBgN...
```
**Status:** ❌ FAILED - Column belum ada di database  
**Action Required:** Jalankan SQL migration untuk add columns

---

### **Example 4: Normal Outbound Quote**
```
[2026-01-20 11:00:00] 📌 [OUTBOUND] Quote detected - WAMID: wamid.ABC123...
[2026-01-20 11:00:00] ✓ [OUTBOUND] Quote found in wa_messages_in - Body: Kapan bisa diambil kak?
[2026-01-20 11:00:00] 💾 [OUTBOUND] Saving quote reference - ID: wamid.ABC123..., Body: Kapan bisa diambil kak?
[2026-01-20 11:00:00] ✓ [OUTBOUND] Quote saved successfully - MsgID: 67890, QuotedWAMID: wamid.ABC123...
[2026-01-20 11:00:00] 📡 [OUTBOUND] Pushing to WebSocket with quote - WAMID: wamid.ABC123..., Body: Kapan bisa diambil...
```
**Status:** ✅ SUKSES - Agent reply dengan quote berhasil

---

## 🔧 Troubleshooting

### **Issue: No logs appear**
**Check:**
1. Log class loaded: `class_exists('\Log')`
2. Log directory writable: `api/logs/`
3. Category enabled: `wa_quote`

---

### **Issue: Quote body always NULL**
**Possible Causes:**
```
⚠ Quote message NOT FOUND in database
```
**Solutions:**
1. Message mungkin terlalu lama (retention policy)
2. Check WAMID format di webhook vs database
3. Verify message ID mapping

---

### **Issue: Database insert failed**
**Error:**
```
✗ ERROR fetching quoted message | Error: Unknown column 'quoted_message_id'
```
**Solution:**
```sql
-- Run migration
ALTER TABLE wa_messages_in ADD COLUMN quoted_message_id VARCHAR(255) NULL;
ALTER TABLE wa_messages_in ADD COLUMN quoted_message_body TEXT NULL;
ALTER TABLE wa_messages_out ADD COLUMN quoted_message_id VARCHAR(255) NULL;
ALTER TABLE wa_messages_out ADD COLUMN quoted_message_body TEXT NULL;
```

---

### **Issue: WebSocket not receiving quote data**
**Check:**
1. Log shows: `📡 Pushing to WebSocket with quote`
2. WebSocket server running: `https://waserver.nalju.com/incoming`
3. Frontend listening for quote fields
4. Browser console for payload inspection

---

## 📊 Log Analysis Commands

### **Count successful quote saves (today)**
```bash
grep "✓ Quote saved successfully" api/logs/wa_quote_$(date +%Y%m%d).log | wc -l
```

### **Find quote failures**
```bash
grep "✗" api/logs/wa_quote_$(date +%Y%m%d).log
```

### **Check quote not found (missing messages)**
```bash
grep "⚠ Quote message NOT FOUND" api/logs/wa_quote_$(date +%Y%m%d).log
```

### **Track specific WAMID**
```bash
grep "wamid.HBgNNjI4MTI5" api/logs/wa_quote_$(date +%Y%m%d).log
```

### **Monitor real-time (live tail)**
```bash
tail -f api/logs/wa_quote_$(date +%Y%m%d).log
```

---

## 🎯 Success Metrics

**Healthy System:**
- ✅ 95%+ quote saves successful
- ✅ <5% "NOT FOUND" warnings
- ✅ 0 database errors
- ✅ All WebSocket pushes successful

**Warning Signs:**
- ⚠️ >10% "NOT FOUND" warnings → Check message retention
- ⚠️ Multiple database errors → Check schema migration
- ⚠️ WebSocket failures → Check server connectivity

---

## 📞 Support

**Log Categories:**
- `wa_quote` - Quote-specific logs (this file)
- `webhook` - General webhook processing
- `wa_error` - WhatsApp API errors

**Log Format:**
```
[YYYY-MM-DD HH:MM:SS] <emoji> [TAG] Message - Detail
```

**Happy Debugging!** 🐛🔍
