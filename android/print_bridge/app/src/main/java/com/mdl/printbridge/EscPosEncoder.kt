package com.mdl.printbridge

/**
 * Port of arsip/printer_server/print-cmd.js HTML → ESC/POS encoding.
 */
class EscPosEncoder(
    private val lineWidth: Int = 32,
    private val lineSpacing: Int = 34,
    private val qrSize: Int = 6,
    private val qrErrorLevel: Int = 48,
    private val maxQrLength: Int = 300
) {
    companion object {
        private const val ESC: Byte = 0x1B
        private const val GS: Byte = 0x1D
    }

    fun encodeText(text: String, marginTop: Int = 0, feedLines: Int = 0): ByteArray {
        val processed = processHtml(text)
        val out = ArrayList<Byte>(processed.size + 64)
        out += ESC
        out += 0x33
        out += lineSpacing.toByte()
        repeat(marginTop.coerceAtLeast(0)) {
            out += 0x0D
            out += 0x0A
        }
        out.addAll(processed.toList())
        repeat(feedLines.coerceAtLeast(0)) {
            out += 0x0D
            out += 0x0A
        }
        // ESC d 3 — feed 3 lines flush
        out += ESC
        out += 0x64
        out += 0x03
        return out.toByteArray()
    }

    fun encodeQr(qrDataRaw: String, text: String = "", marginTop: Int = 0, feedLines: Int = 0): ByteArray {
        var qrData = qrDataRaw
        if (qrData.length > maxQrLength) {
            qrData = qrData.substring(0, maxQrLength)
        }

        val out = ArrayList<Byte>(qrData.length + 128)
        out += ESC
        out += '@'.code.toByte()
        out += ESC
        out += 0x33
        out += lineSpacing.toByte()
        repeat(marginTop.coerceAtLeast(0)) {
            out += 0x0D
            out += 0x0A
        }
        out += ESC
        out += 'a'.code.toByte()
        out += 0x01 // center

        // Model 2
        out.addAll(listOf(GS, '('.code.toByte(), 'k'.code.toByte(), 4, 0, 49, 65, 50, 0))
        // Size
        out.addAll(listOf(GS, '('.code.toByte(), 'k'.code.toByte(), 3, 0, 49, 67, qrSize.toByte()))
        // Error level
        out.addAll(listOf(GS, '('.code.toByte(), 'k'.code.toByte(), 3, 0, 49, 69, qrErrorLevel.toByte()))
        // Store
        val dataLength = qrData.length + 3
        val pL = (dataLength % 256).toByte()
        val pH = (dataLength / 256).toByte()
        out.addAll(listOf(GS, '('.code.toByte(), 'k'.code.toByte(), pL, pH, 49, 80, 48))
        out.addAll(qrData.toByteArray(Charsets.ISO_8859_1).toList())
        // Print QR
        out.addAll(listOf(GS, '('.code.toByte(), 'k'.code.toByte(), 3, 0, 49, 81, 48))
        out += 0x0D
        out += 0x0A
        out += 0x0D
        out += 0x0A

        if (text.isNotBlank()) {
            out.addAll(text.toByteArray(Charsets.ISO_8859_1).toList())
            out += 0x0D
            out += 0x0A
        }

        repeat(feedLines.coerceAtLeast(0)) {
            out += 0x0D
            out += 0x0A
        }
        out += ESC
        out += 0x64
        out += 0x03
        out += ESC
        out += 'a'.code.toByte()
        out += 0x00
        return out.toByteArray()
    }

    fun encodeTest(printerLabel: String): ByteArray {
        val html =
            "<center><b>TEST PRINT</b></center>\n" +
                "Printer: $printerLabel\n" +
                "Port: 3000\n" +
                "Time: ${java.text.SimpleDateFormat("dd/MM/yyyy HH:mm:ss", java.util.Locale("id", "ID")).format(java.util.Date())}\n" +
                "Status: Online"
        return encodeText(html, 0, 3)
    }

    private fun processHtml(text: String): ByteArray {
        val boldOn = byteArrayOf(ESC, 0x45, 0x01)
        val boldOff = byteArrayOf(ESC, 0x45, 0x00)
        val h1On = byteArrayOf(GS, 0x21, 0x11)
        val h1Off = byteArrayOf(GS, 0x21, 0x00)
        val centerAlign = byteArrayOf(ESC, 0x61, 0x01)
        val rightAlign = byteArrayOf(ESC, 0x61, 0x02)
        val leftAlign = byteArrayOf(ESC, 0x61, 0x00)

        // STEP 1: <tr> → newline
        var processed = text
            .replace(Regex("<tr>", RegexOption.IGNORE_CASE), "\n")
            .replace(Regex("</tr>", RegexOption.IGNORE_CASE), "")

        // STEP 2: process <td> per line
        processed = processed.split("\n").joinToString("\n") { processTdTags(it) }

        // STEP 3: <br> → newline
        processed = processed.replace(Regex("<br\\s*/?>", RegexOption.IGNORE_CASE), "\n")
        processed = processed.replace(Regex("^\\n+"), "")

        // STEP 4: formatting tags — work as string with placeholders then convert
        // Use rare markers then expand to bytes
        val MARK_BOLD_ON = "\u0001B"
        val MARK_BOLD_OFF = "\u0001b"
        val MARK_H1_ON = "\u0001H"
        val MARK_H1_OFF = "\u0001h"
        val MARK_CENTER = "\u0001C"
        val MARK_RIGHT = "\u0001R"
        val MARK_LEFT = "\u0001L"

        processed = processed
            .replace(Regex("</center>", RegexOption.IGNORE_CASE), "")
            .replace(Regex("</right>", RegexOption.IGNORE_CASE), "")
            .replace(Regex("</left>", RegexOption.IGNORE_CASE), "")
            .replace(Regex("<center>", RegexOption.IGNORE_CASE), MARK_CENTER)
            .replace(Regex("<right>", RegexOption.IGNORE_CASE), MARK_RIGHT)
            .replace(Regex("<left>", RegexOption.IGNORE_CASE), MARK_LEFT)
            .replace(Regex("<b>", RegexOption.IGNORE_CASE), MARK_BOLD_ON)
            .replace(Regex("</b>", RegexOption.IGNORE_CASE), MARK_BOLD_OFF)
            .replace(Regex("<h1>", RegexOption.IGNORE_CASE), MARK_H1_ON)
            .replace(Regex("</h1>", RegexOption.IGNORE_CASE), MARK_H1_OFF)

        // Strip remaining HTML tags
        processed = processed.replace(Regex("<[^>]+>"), "")

        val out = ArrayList<Byte>(processed.length + 64)
        var i = 0
        while (i < processed.length) {
            val ch = processed[i]
            when (ch) {
                '\u0001' -> {
                    if (i + 1 < processed.length) {
                        when (processed[i + 1]) {
                            'B' -> out.addAll(boldOn.toList())
                            'b' -> out.addAll(boldOff.toList())
                            'H' -> out.addAll(h1On.toList())
                            'h' -> out.addAll(h1Off.toList())
                            'C' -> out.addAll(centerAlign.toList())
                            'R' -> out.addAll(rightAlign.toList())
                            'L' -> out.addAll(leftAlign.toList())
                        }
                        i += 2
                    } else {
                        i++
                    }
                }
                '\n' -> {
                    out += 0x0D
                    out += 0x0A
                    i++
                }
                else -> {
                    // Prefer ISO-8859-1 for ESC/POS thermal printers
                    val bytes = ch.toString().toByteArray(Charsets.ISO_8859_1)
                    out.addAll(bytes.toList())
                    i++
                }
            }
        }
        return out.toByteArray()
    }

    private fun processTdTags(line: String): String {
        val tdRegex = Regex("<td>(.*?)</td>", setOf(RegexOption.IGNORE_CASE, RegexOption.DOT_MATCHES_ALL))
        val matches = tdRegex.findAll(line).map { it.groupValues[1] }.toList()
        if (matches.isEmpty()) return line

        val remainder = tdRegex.replace(line, "")
        val stripAlign = { s: String ->
            s.replace(Regex("</?center>", RegexOption.IGNORE_CASE), "")
                .replace(Regex("</?right>", RegexOption.IGNORE_CASE), "")
                .replace(Regex("</?left>", RegexOption.IGNORE_CASE), "")
        }
        val stripTags = { s: String -> s.replace(Regex("<[^>]*>"), "") }

        return when {
            matches.size == 1 -> {
                val content = stripAlign(matches[0])
                "\u0001C$content$remainder"
            }
            else -> {
                val col1 = stripAlign(matches[0])
                val col2 = stripAlign(matches[1])
                val len1 = stripTags(col1).length
                val len2 = stripTags(col2).length
                val spacing = maxOf(1, lineWidth - len1 - len2)
                col1 + " ".repeat(spacing) + col2 + remainder
            }
        }
    }
}
