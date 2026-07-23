package com.mdl.qrisdisplay

import android.annotation.SuppressLint
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.os.PowerManager
import android.provider.Settings
import android.view.KeyEvent
import android.view.View
import android.view.WindowManager
import android.app.ActivityManager
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebResourceResponse
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.ProgressBar
import android.widget.TextView
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import android.Manifest
import android.content.pm.PackageManager

class MainActivity : AppCompatActivity() {

    companion object {
        private const val WEB_URL = "https://qrc.nalju.com/"
        private const val EXIT_HOLD_MS = 3000L
        private const val PREFS_NAME = "qris_display_prefs"
        private const val KEY_BATTERY_PROMPTED = "battery_prompted"
    }

    private lateinit var webView: WebView
    private lateinit var offlineOverlay: View
    private lateinit var exitProgressContainer: View
    private lateinit var exitProgressBar: ProgressBar
    private lateinit var exitProgressText: TextView

    private var networkMonitor: NetworkMonitor? = null
    private var pageLoadFailed = false
    private var isOfflineVisible = false

    private var backDown = false
    private var volumeDown = false
    private var holdStartMs = 0L
    private val handler = Handler(Looper.getMainLooper())
    private var exitProgressRunnable: Runnable? = null
    private var immersiveRetryRunnable: Runnable? = null

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
        }

        window.addFlags(
            WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON or
                WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD or
                WindowManager.LayoutParams.FLAG_FULLSCREEN
        )

        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webView)
        offlineOverlay = findViewById(R.id.offlineOverlay)
        exitProgressContainer = findViewById(R.id.exitProgressContainer)
        exitProgressBar = findViewById(R.id.exitProgressBar)
        exitProgressText = findViewById(R.id.exitProgressText)

        enterKioskMode()
        setupWebView()
        setupBackHandler()
        setupNetworkMonitor()
        requestNotificationPermission()
        promptBatteryOptimizationIfNeeded()

        KeepAliveService.start(this)
        loadWebApp()
    }

    override fun onResume() {
        super.onResume()
        enterKioskMode()
        webView.onResume()
        // Reconnect WebSocket setelah pause (cookie masih ada di halaman)
        webView.evaluateJavascript(
            "(function(){var id=document.cookie.match(/(?:^|; )kasir_id=([^;]+)/);if(id&&id[1]&&window.connectWebSocket){window.connectWebSocket(decodeURIComponent(id[1]),{force:true});}})();",
            null
        )
        networkMonitor?.let {
            if (!it.hasInternet()) showOfflineOverlay() else hideOfflineOverlay()
        }
    }

    override fun onPause() {
        // Lepas slot WebSocket di server sebelum app background/kill
        if (::webView.isInitialized) {
            webView.evaluateJavascript(
                "window.closeQrSocket && window.closeQrSocket()",
                null
            )
        }
        webView.onPause()
        super.onPause()
    }

    override fun onDestroy() {
        cancelExitProgress()
        immersiveRetryRunnable?.let { handler.removeCallbacks(it) }
        networkMonitor?.stop()
        super.onDestroy()
    }

    override fun onWindowFocusChanged(hasFocus: Boolean) {
        super.onWindowFocusChanged(hasFocus)
        if (hasFocus) enterKioskMode()
    }

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        when (keyCode) {
            KeyEvent.KEYCODE_BACK -> {
                backDown = true
                updateExitHold()
                return true
            }
            KeyEvent.KEYCODE_VOLUME_DOWN -> {
                volumeDown = true
                updateExitHold()
                return true
            }
        }
        return super.onKeyDown(keyCode, event)
    }

    override fun onKeyUp(keyCode: Int, event: KeyEvent?): Boolean {
        when (keyCode) {
            KeyEvent.KEYCODE_BACK -> {
                backDown = false
                cancelExitProgress()
                return true
            }
            KeyEvent.KEYCODE_VOLUME_DOWN -> {
                volumeDown = false
                cancelExitProgress()
                return true
            }
        }
        return super.onKeyUp(keyCode, event)
    }

    /** Immersive fullscreen + lock task (jika diizinkan sistem). Dipanggil tiap create/resume/focus. */
    private fun enterKioskMode() {
        setupImmersiveMode()
        scheduleImmersiveRetry()
        tryStartLockTask()
    }

    private fun setupImmersiveMode() {
        WindowCompat.setDecorFitsSystemWindows(window, false)

        val controller = WindowInsetsControllerCompat(window, window.decorView)
        controller.hide(WindowInsetsCompat.Type.statusBars() or WindowInsetsCompat.Type.navigationBars())
        controller.systemBarsBehavior =
            WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE

        // Fallback WebView/Android lama
        @Suppress("DEPRECATION")
        window.decorView.systemUiVisibility = (
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY or
                View.SYSTEM_UI_FLAG_FULLSCREEN or
                View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
                View.SYSTEM_UI_FLAG_LAYOUT_STABLE or
                View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN or
                View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
            )

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            window.attributes.layoutInDisplayCutoutMode =
                WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES
        }
    }

    /** Beberapa device baru menerapkan immersive baru setelah window siap — retry singkat. */
    private fun scheduleImmersiveRetry() {
        immersiveRetryRunnable?.let { handler.removeCallbacks(it) }
        immersiveRetryRunnable = Runnable {
            if (!isFinishing) setupImmersiveMode()
        }
        handler.postDelayed(immersiveRetryRunnable!!, 300)
        handler.postDelayed(immersiveRetryRunnable!!, 1000)
    }

    private fun tryStartLockTask() {
        try {
            val am = getSystemService(ACTIVITY_SERVICE) as ActivityManager
            if (am.lockTaskModeState == ActivityManager.LOCK_TASK_MODE_NONE) {
                startLockTask()
            }
        } catch (_: Exception) {
            // Screen pinning belum diaktifkan / tidak diizinkan — immersive tetap jalan
        }
    }

    private fun tryStopLockTask() {
        try {
            val am = getSystemService(ACTIVITY_SERVICE) as ActivityManager
            if (am.lockTaskModeState != ActivityManager.LOCK_TASK_MODE_NONE) {
                stopLockTask()
            }
        } catch (_: Exception) {
            // ignore
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun setupWebView() {
        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            databaseEnabled = true
            cacheMode = WebSettings.LOAD_DEFAULT
            allowFileAccess = false
            mediaPlaybackRequiresUserGesture = false
            loadsImagesAutomatically = true
            mixedContentMode = WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE
            userAgentString = "${userAgentString} QRISDisplay/1.0"
        }

        webView.webViewClient = object : WebViewClient() {
            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                if (url != null && url != "about:blank") {
                    pageLoadFailed = false
                }
            }

            override fun onReceivedError(
                view: WebView?,
                request: WebResourceRequest?,
                error: WebResourceError?
            ) {
                if (request?.isForMainFrame == true) {
                    pageLoadFailed = true
                    view?.loadUrl("about:blank")
                    showOfflineOverlay()
                }
            }

            @Deprecated("Deprecated in API 23")
            override fun onReceivedError(
                view: WebView?,
                errorCode: Int,
                description: String?,
                failingUrl: String?
            ) {
                if (failingUrl == WEB_URL || failingUrl?.startsWith("https://qrc.nalju.com") == true) {
                    pageLoadFailed = true
                    view?.loadUrl("about:blank")
                    showOfflineOverlay()
                }
            }

            override fun onReceivedHttpError(
                view: WebView?,
                request: WebResourceRequest?,
                errorResponse: WebResourceResponse?
            ) {
                if (request?.isForMainFrame == true) {
                    val code = errorResponse?.statusCode ?: 0
                    if (code >= 500) {
                        pageLoadFailed = true
                        view?.loadUrl("about:blank")
                        showOfflineOverlay()
                    }
                }
            }
        }

        webView.setBackgroundColor(ContextCompat.getColor(this, R.color.black))
    }

    private fun setupBackHandler() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                // Consumed — exit only via Back + Volume Down hold
            }
        })
    }

    private fun setupNetworkMonitor() {
        networkMonitor = NetworkMonitor(
            context = this,
            onOnline = {
                runOnUiThread {
                    hideOfflineOverlay()
                    if (pageLoadFailed || webView.url == "about:blank" || webView.url.isNullOrEmpty()) {
                        loadWebApp()
                    }
                }
            },
            onOffline = {
                runOnUiThread {
                    pageLoadFailed = true
                    webView.loadUrl("about:blank")
                    showOfflineOverlay()
                }
            }
        ).also { it.start() }
    }

    private fun loadWebApp() {
        if (networkMonitor?.hasInternet() != true) {
            pageLoadFailed = true
            webView.loadUrl("about:blank")
            showOfflineOverlay()
            return
        }
        webView.loadUrl(WEB_URL)
    }

    private fun showOfflineOverlay() {
        isOfflineVisible = true
        offlineOverlay.visibility = View.VISIBLE
    }

    private fun hideOfflineOverlay() {
        isOfflineVisible = false
        offlineOverlay.visibility = View.GONE
    }

    private fun updateExitHold() {
        if (backDown && volumeDown) {
            if (holdStartMs == 0L) {
                holdStartMs = System.currentTimeMillis()
                startExitProgress()
            }
        } else {
            cancelExitProgress()
        }
    }

    private fun startExitProgress() {
        exitProgressContainer.visibility = View.VISIBLE
        exitProgressBar.progress = 0

        exitProgressRunnable?.let { handler.removeCallbacks(it) }
        exitProgressRunnable = object : Runnable {
            override fun run() {
                if (!backDown || !volumeDown) {
                    cancelExitProgress()
                    return
                }

                val elapsed = System.currentTimeMillis() - holdStartMs
                val progress = ((elapsed.toFloat() / EXIT_HOLD_MS) * 100).toInt().coerceIn(0, 100)
                exitProgressBar.progress = progress

                if (elapsed >= EXIT_HOLD_MS) {
                    exitKiosk()
                } else {
                    handler.postDelayed(this, 50)
                }
            }
        }
        handler.post(exitProgressRunnable!!)
    }

    private fun cancelExitProgress() {
        holdStartMs = 0L
        exitProgressRunnable?.let { handler.removeCallbacks(it) }
        exitProgressRunnable = null
        exitProgressContainer.visibility = View.GONE
        exitProgressBar.progress = 0
    }

    private fun exitKiosk() {
        cancelExitProgress()
        tryStopLockTask()
        KeepAliveService.stop(this)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            finishAndRemoveTask()
        } else {
            finish()
        }
    }

    private fun requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED
            ) {
                ActivityCompat.requestPermissions(
                    this,
                    arrayOf(Manifest.permission.POST_NOTIFICATIONS),
                    1001
                )
            }
        }
    }

    private fun promptBatteryOptimizationIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return

        val powerManager = getSystemService(POWER_SERVICE) as PowerManager
        if (powerManager.isIgnoringBatteryOptimizations(packageName)) return

        val prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
        if (prefs.getBoolean(KEY_BATTERY_PROMPTED, false)) return

        AlertDialog.Builder(this)
            .setTitle(R.string.battery_dialog_title)
            .setMessage(R.string.battery_dialog_message)
            .setPositiveButton(R.string.battery_dialog_positive) { _, _ ->
                prefs.edit().putBoolean(KEY_BATTERY_PROMPTED, true).apply()
                try {
                    val intent = Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS).apply {
                        data = Uri.parse("package:$packageName")
                    }
                    startActivity(intent)
                } catch (_: Exception) {
                    startActivity(Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS))
                }
                enterKioskMode()
            }
            .setNegativeButton(R.string.battery_dialog_negative) { _, _ ->
                prefs.edit().putBoolean(KEY_BATTERY_PROMPTED, true).apply()
                enterKioskMode()
            }
            .setOnDismissListener {
                enterKioskMode()
            }
            .show()
    }
}
