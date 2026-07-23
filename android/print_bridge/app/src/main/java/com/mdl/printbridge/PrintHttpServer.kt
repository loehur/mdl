package com.mdl.printbridge

import android.util.Log
import fi.iki.elonen.NanoHTTPD
import org.json.JSONObject
import java.io.IOException
import java.net.InetAddress
import java.net.ServerSocket
import java.nio.charset.Charset

/**
 * HTTP API compatible with arsip/printer_server/server.js
 * Binds to 127.0.0.1:3000 only.
 */
class PrintHttpServer(
    private val prefs: Prefs,
    private val printer: BluetoothPrinter,
    private val onLog: (String) -> Unit = {}
) : NanoHTTPD("127.0.0.1", PORT) {

    companion object {
        const val PORT = 3000
        private const val TAG = "PrintHttpServer"

        fun isPortFree(): Boolean {
            return try {
                ServerSocket(PORT, 1, InetAddress.getByName("127.0.0.1")).use { true }
            } catch (_: IOException) {
                false
            }
        }
    }

    /** Current request — used so CORS can echo Origin / Private-Network. */
    @Volatile
    private var currentSession: IHTTPSession? = null

    override fun serve(session: IHTTPSession): Response {
        currentSession = session
        try {
            if (session.method == Method.OPTIONS) {
                onLog("OPTIONS ${session.uri} origin=${session.headers["origin"]}")
                val cors = newFixedLengthResponse(Response.Status.NO_CONTENT, MIME_PLAINTEXT, "")
                addCors(cors)
                return cors
            }

            return try {
                when {
                    session.method == Method.GET && session.uri == "/" -> handleRoot()
                    session.method == Method.GET && session.uri == "/health" -> handleHealth()
                    session.method == Method.POST && session.uri == "/print" -> handlePrint(session)
                    session.method == Method.POST && session.uri == "/printqr" -> handlePrintQr(session)
                    else -> jsonError(404, "Not found")
                }
            } catch (e: Exception) {
                Log.e(TAG, "serve error", e)
                onLog("ERROR: ${e.message}")
                jsonError(500, e.message ?: "Internal error")
            }
        } finally {
            currentSession = null
        }
    }

    private fun handleRoot(): Response {
        val body = JSONObject()
            .put("name", "Print Server")
            .put("version", "1.0.0")
            .put("status", "running")
            .put("port", prefs.printerAddress.ifBlank { "bluetooth" })
            .put(
                "endpoints",
                JSONObject()
                    .put(
                        "print",
                        JSONObject()
                            .put("method", "POST")
                            .put("path", "/print")
                            .put("description", "Cetak text atau QR code ke printer")
                    )
                    .put(
                        "printqr",
                        JSONObject()
                            .put("method", "POST")
                            .put("path", "/printqr")
                            .put("description", "Cetak QR Code dengan text di bawahnya")
                    )
                    .put(
                        "health",
                        JSONObject()
                            .put("method", "GET")
                            .put("path", "/health")
                            .put("description", "Cek status server")
                    )
            )
        return jsonOk(body)
    }

    private fun handleHealth(): Response {
        val body = JSONObject()
            .put("status", "ok")
            .put("timestamp", java.time.Instant.now().toString())
            .put("port", prefs.printerAddress.ifBlank { "bluetooth" })
            .put("printer", prefs.printerName)
        return jsonOk(body)
    }

    private fun handlePrint(session: IHTTPSession): Response {
        val bodyMap = parseBody(session)
        val encoder = encoder()

        if (bodyMap.has("data_b64")) {
            val qrData = bodyMap.optString("data_b64", "")
            if (qrData.isBlank()) return jsonError(400, "data_b64 tidak boleh kosong")
            onLog("QR (data_b64) len=${qrData.length}")
            send(encoder.encodeQr(qrData))
            return jsonOk(
                JSONObject()
                    .put("success", true)
                    .put("message", "QR Code berhasil dicetak")
                    .put(
                        "data",
                        JSONObject()
                            .put("type", "qrcode")
                            .put("data", qrData)
                            .put("port", prefs.printerAddress)
                    )
            )
        }

        val text = when {
            bodyMap.has("text") -> bodyMap.optString("text", "")
            bodyMap.has("message") -> bodyMap.optString("message", "")
            bodyMap.length() > 0 -> bodyMap.toString()
            else -> ""
        }
        if (text.isBlank()) return jsonError(400, "Body tidak boleh kosong")

        val marginTop = bodyMap.optInt("margin_top", 0)
        val feedLines = bodyMap.optInt("feed_lines", 0)
        onLog("PRINT text len=${text.length} margin=$marginTop feed=$feedLines")
        send(encoder.encodeText(text, marginTop, feedLines))

        return jsonOk(
            JSONObject()
                .put("success", true)
                .put("message", "Data berhasil dicetak")
                .put(
                    "data",
                    JSONObject()
                        .put("type", "text")
                        .put("text", text)
                        .put("margin_top", marginTop)
                        .put("feed_lines", feedLines)
                        .put("port", prefs.printerAddress)
                )
        )
    }

    private fun handlePrintQr(session: IHTTPSession): Response {
        val bodyMap = parseBody(session)
        val qrString = bodyMap.optString("qr_string", "")
        if (qrString.isBlank()) return jsonError(400, "qr_string tidak boleh kosong")

        val text = bodyMap.optString("text", "")
        val marginTop = bodyMap.optInt("margin_top", 0)
        val feedLines = bodyMap.optInt("feed_lines", 0)
        onLog("PRINTQR len=${qrString.length} text=${text.take(40)}")
        send(encoder().encodeQr(qrString, text, marginTop, feedLines))

        return jsonOk(
            JSONObject()
                .put("success", true)
                .put("message", "QR Code berhasil dicetak")
                .put(
                    "data",
                    JSONObject()
                        .put("qr_string", qrString)
                        .put("text", text)
                        .put("margin_top", marginTop)
                        .put("feed_lines", feedLines)
                        .put("port", prefs.printerAddress)
                )
        )
    }

    private fun send(data: ByteArray) {
        val address = prefs.printerAddress
        if (address.isBlank()) {
            throw IOException("Printer Bluetooth belum dipilih di Print Bridge")
        }
        printer.write(address, data)
    }

    private fun encoder(): EscPosEncoder = EscPosEncoder(
        lineWidth = prefs.lineWidth,
        lineSpacing = prefs.lineSpacing,
        qrSize = prefs.qrSize,
        qrErrorLevel = prefs.qrErrorLevel,
        maxQrLength = prefs.maxQrLength
    )

    private fun parseBody(session: IHTTPSession): JSONObject {
        val contentType = (session.headers["content-type"] ?: "").lowercase()
        val files = HashMap<String, String>()
        try {
            session.parseBody(files)
        } catch (_: Exception) {
            // ignore — may already be consumed / empty
        }

        val raw = files["postData"]
            ?: readRawBodyFallback(session)
            ?: ""

        if (raw.isBlank()) {
            if (session.parms.isNotEmpty()) {
                val o = JSONObject()
                session.parms.forEach { (k, v) -> o.put(k, v) }
                return o
            }
            return JSONObject()
        }

        val trimmed = raw.trim()
        return when {
            trimmed.startsWith("{") -> JSONObject(trimmed)
            contentType.contains("application/json") -> {
                try {
                    JSONObject(trimmed)
                } catch (_: Exception) {
                    JSONObject().put("text", raw)
                }
            }
            else -> JSONObject().put("text", raw)
        }
    }

    private fun readRawBodyFallback(session: IHTTPSession): String? {
        return try {
            val len = session.headers["content-length"]?.toIntOrNull() ?: return null
            if (len <= 0) return null
            val input = session.inputStream ?: return null
            val buf = ByteArray(len)
            var read = 0
            while (read < len) {
                val n = input.read(buf, read, len - read)
                if (n < 0) break
                read += n
            }
            String(buf, 0, read, Charset.forName("UTF-8"))
        } catch (_: Exception) {
            null
        }
    }

    private fun jsonOk(obj: JSONObject): Response {
        val r = newFixedLengthResponse(Response.Status.OK, "application/json", obj.toString())
        addCors(r)
        return r
    }

    private fun jsonError(code: Int, message: String): Response {
        val status = Response.Status.lookup(code) ?: Response.Status.INTERNAL_ERROR
        val obj = JSONObject().put("success", false).put("error", message)
        val r = newFixedLengthResponse(status, "application/json", obj.toString())
        addCors(r)
        return r
    }

    /**
     * CORS + Chrome Private Network Access (halaman LAN → localhost).
     * Echo Origin (dibutuhkan bersama Allow-Private-Network di Chrome modern).
     */
    private fun addCors(r: Response) {
        val session = currentSession
        val origin = session?.headers?.get("origin")
            ?: session?.headers?.get("Origin")

        if (!origin.isNullOrBlank()) {
            r.addHeader("Access-Control-Allow-Origin", origin)
            r.addHeader("Vary", "Origin")
        } else {
            r.addHeader("Access-Control-Allow-Origin", "*")
        }

        r.addHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        r.addHeader(
            "Access-Control-Allow-Headers",
            "Content-Type, Authorization, Access-Control-Request-Private-Network"
        )
        r.addHeader("Access-Control-Allow-Private-Network", "true")
        r.addHeader("Access-Control-Max-Age", "86400")
    }
}
