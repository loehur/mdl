package com.mdl.printbridge

import android.content.Context
import android.content.SharedPreferences

class Prefs(context: Context) {
    private val sp: SharedPreferences =
        context.getSharedPreferences("print_bridge", Context.MODE_PRIVATE)

    var printerAddress: String
        get() = sp.getString(KEY_PRINTER, "") ?: ""
        set(value) = sp.edit().putString(KEY_PRINTER, value).apply()

    var printerName: String
        get() = sp.getString(KEY_PRINTER_NAME, "") ?: ""
        set(value) = sp.edit().putString(KEY_PRINTER_NAME, value).apply()

    var lineWidth: Int
        get() = sp.getInt(KEY_LINE_WIDTH, 32)
        set(value) = sp.edit().putInt(KEY_LINE_WIDTH, value).apply()

    var lineSpacing: Int
        get() = sp.getInt(KEY_LINE_SPACING, 34)
        set(value) = sp.edit().putInt(KEY_LINE_SPACING, value).apply()

    var qrSize: Int
        get() = sp.getInt(KEY_QR_SIZE, 6)
        set(value) = sp.edit().putInt(KEY_QR_SIZE, value).apply()

    var qrErrorLevel: Int
        get() = sp.getInt(KEY_QR_ERROR, 48)
        set(value) = sp.edit().putInt(KEY_QR_ERROR, value).apply()

    var maxQrLength: Int
        get() = sp.getInt(KEY_MAX_QR, 300)
        set(value) = sp.edit().putInt(KEY_MAX_QR, value).apply()

    companion object {
        private const val KEY_PRINTER = "printer_address"
        private const val KEY_PRINTER_NAME = "printer_name"
        private const val KEY_LINE_WIDTH = "line_width"
        private const val KEY_LINE_SPACING = "line_spacing"
        private const val KEY_QR_SIZE = "qr_size"
        private const val KEY_QR_ERROR = "qr_error_level"
        private const val KEY_MAX_QR = "max_qr_length"
    }
}
