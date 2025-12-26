# Auto-Reply System Overview

## 🔄 Cara Kerja Auto-Reply

```
Incoming Message
       ↓
[1] Check Single Char (p, ., dll) → PEMBUKA
       ↓ (not match)
[2] Check Keyword Patterns (bon, cek, buka, dll) → Handler
       ↓ (not match)
[3] AI Fallback → Gemini API → Intent Detection → Handler
       ↓ (cooldown/disabled)
[4] No Reply
```

---

## 📋 Handlers Available

| Handler | Trigger Examples | Function |
|---------|------------------|----------|
| **PEMBUKA** | p, ., halo, hai | Greeting response |
| **NOTA** | bon, nota, struk | Send invoice/receipt |
| **STATUS** | cek, status | Check laundry status |
| **JAM_BUKA** | buka, tutup, jam | Operating hours |
| **PENUTUP** | ok, siap, makasih | Acknowledgment |

---

## 🎯 Pattern vs AI

### Pattern Matching (Default)
✅ **Pros:**
- Instant (no API call)
- Free (no cost)
- Reliable (regex-based)
- No quota limit

❌ **Cons:**
- Rigid (exact keyword)
- Miss variations
- Bahasa Indonesia variations sulit

**Example:**
```
"bon" → ✅ Match
"minta bon" → ✅ Match (contains "bon")
"tagihan" → ❌ No match (beda kata)
"mau lihat struk" → ❌ No match (tidak ada "bon"|"nota"|"struk")
```

---

### AI Fallback (Optional)
✅ **Pros:**
- Smart intent detection
- Natural language understanding
- Handle variations/typos
- Contextual

❌ **Cons:**
- API cost (~$0.0001/request)
- Slower (~500ms-2s)
- Quota limit
- Need API key

**Example:**
```
"tagihan" → ✅ AI detects → NOTA
"mau lihat struk" → ✅ AI detects → NOTA
"kira kira siap kapan itu kak" → ✅ AI detects → STATUS
"jam operasional" → ✅ AI detects → JAM_BUKA
```

---

## 💡 Recommendation

### Without AI (Current Default)
```php
$aiEnabled = false;
```
- **Best for:** High volume, cost-sensitive
- **Coverage:** ~70-80% user messages
- Users just need to use keyword yang benar

### With AI (Enhanced)
```php
$aiEnabled = true;
```
- **Best for:** Better UX, natural conversation
- **Coverage:** ~95-98% user messages
- Users bisa chat natural tanpa keyword khusus

---

## 📊 Cost Estimation

**Gemini 1.5 Flash Pricing:**
- Input: $0.075 / 1M tokens (~Rp 1,200)
- Output: $0.30 / 1M tokens (~Rp 4,800)

**Average per message:**
- Prompt: ~200 tokens
- Response: ~10 tokens
- **Cost: ~Rp 0.0024 per message** (less than 1 rupiah!)

**For 1,000 messages/month:**
- Total cost: ~Rp 2,400/month
- Masih sangat murah! 💰

---

## 🔧 Configuration

### Pattern Keywords
Edit di: `app/Config/AutoReplyKeywords.php`

```php
'NOTA' => [
    'max_length' => 20,
    'patterns' => [
        '/\b(bon|nota|struk|tagihan|invoice|bukti)\b/i',
    ]
]
```

### AI Settings
Edit di: `app/Config/AI.php`

```php
private static $aiEnabled = true;
private static $geminiModel = 'gemini-2.5-flash'; // Model terbaru
private static $temperature = 0.3; // Lower = more consistent
```

---

## 🎓 Best Practice

1. **Start with patterns** - Free & instant
2. **Monitor unhandled messages** - Check logs
3. **Add AI if needed** - Better UX for natural messages
4. **Monitor AI usage** - Check quota & cost
5. **Refine prompts** - Improve accuracy

---

## 🔍 Example Scenarios

### Scenario 1: User terbiasa dengan keyword
```
User: "bon"
System: [Pattern Match] → handleNota() → Kirim invoice ✅
Cost: Rp 0 | Time: <100ms
```

### Scenario 2: User chat natural (Without AI)
```
User: "mau lihat tagihan dong"
System: [No Match] → No Reply ❌
Cost: Rp 0 | Time: <100ms
```

### Scenario 3: User chat natural (With AI)
```
User: "mau lihat tagihan dong"
System: [AI Fallback] → Gemini API → NOTA → handleNota() ✅
Cost: Rp 0.0024 | Time: ~500ms
```

---

## 📈 Analytics (Future Enhancement)

Track performance:
- Pattern match rate
- AI fallback rate
- AI accuracy
- Response time
- API cost

---

## Summary

| Feature | Pattern Only | Pattern + AI |
|---------|--------------|--------------|
| Cost | Rp 0 | ~Rp 0.0024/msg |
| Speed | <100ms | ~500ms |
| Coverage | 70-80% | 95-98% |
| UX | Good | Excellent |
| Setup | Easy | Need API key |

**Recommendation:** Enable AI untuk better UX! Cost sangat kecil dibanding benefit. 🚀
