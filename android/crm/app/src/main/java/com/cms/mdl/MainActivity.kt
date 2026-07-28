package com.cms.mdl

import android.annotation.SuppressLint
import android.app.Activity
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import com.onesignal.OneSignal
import com.onesignal.notifications.INotificationClickEvent
import com.onesignal.notifications.INotificationClickListener
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.Executors

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private var filePathCallback: ValueCallback<Array<Uri>>? = null
    private var pendingPhone: String? = null // Store phone from notification
    private var isVersionCheckRunning = false
    private var lastVersionCheckAt = 0L

    companion object {
        private const val WEB_URL = "https://cms.nalju.com/"
        private const val VERSION_URL = "${WEB_URL}version.json"
        private const val PREFS_NAME = "cms_prefs"
        private const val PREF_WEB_VERSION = "web_app_version"
        private const val PREF_DEVICE_ID = "crm_device_id"
        private const val VERSION_CHECK_MIN_INTERVAL_MS = 30_000L
        private val versionCheckExecutor = Executors.newSingleThreadExecutor()
    }
    
    // ⭐ JavaScript Bridge for Android functions
    inner class JSBridge {
        @android.webkit.JavascriptInterface
        fun exitApp() {
            android.util.Log.d("JSBridge", "🚪 exitApp() called from JavaScript")
            runOnUiThread {
                finish()
            }
        }

        @android.webkit.JavascriptInterface
        fun openUrl(url: String) {
            android.util.Log.d("JSBridge", "🔗 openUrl() called: $url")
            runOnUiThread {
                try {
                    startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }

        /**
         * Stable device ID for CRM device-lock (persists in SharedPreferences).
         * Called from JS: window.Android.getDeviceId()
         */
        @android.webkit.JavascriptInterface
        fun getDeviceId(): String {
            val prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
            var id = prefs.getString(PREF_DEVICE_ID, null)
            if (id.isNullOrBlank()) {
                id = java.util.UUID.randomUUID().toString()
                prefs.edit().putString(PREF_DEVICE_ID, id).apply()
                android.util.Log.i("JSBridge", "Generated new device id")
            }
            return id
        }
    }

    private val fileChooserLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        val results: Array<Uri>? = if (result.resultCode == Activity.RESULT_OK) {
            result.data?.data?.let { arrayOf(it) }
        } else {
            null
        }
        filePathCallback?.onReceiveValue(results)
        filePathCallback = null
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webView)
        setupWebView()
        setupBackHandler()
        setupOneSignalClickListener()

        // ⭐ Handle cold start from notification
        checkLaunchIntent(intent)
    }

    // ⭐ Handle when app resumes from background/sleep
    // PURE WEBVIEW APPROACH: Let JavaScript handle all navigation logic
    override fun onResume() {
        super.onResume()

        if (::webView.isInitialized) {
            android.util.Log.d("MainActivity", "🔄 App resumed - triggering JS handler")

            // Simply trigger JavaScript handler - let Pinia/Vue handle the rest
            webView.evaluateJavascript("window.__ANDROID_RESUME && window.__ANDROID_RESUME()", null)

            // Re-check web version on resume (throttled) so deploy is picked up without manual clear
            checkWebVersion(reloadIfUpdated = true)
        }
    }

    // ⭐ Handle when app is brought to foreground from background via notification
    override fun onNewIntent(intent: Intent?) {
        super.onNewIntent(intent)
        setIntent(intent) // Update the intent

        intent?.let { newIntent ->
            val phone = extractPhoneFromIntent(newIntent)
            if (!phone.isNullOrEmpty()) {
                android.util.Log.d("OneSignal", "onNewIntent with phone: $phone")
                if (::webView.isInitialized) {
                    openChatByPhone(phone)
                } else {
                    pendingPhone = phone
                }
            }
        }
    }

    // ⭐ Check if app was launched from notification (cold start)
    private fun checkLaunchIntent(intent: Intent?) {
        intent?.let {
            val phone = extractPhoneFromIntent(it)
            if (!phone.isNullOrEmpty()) {
                android.util.Log.d("OneSignal", "Cold start with phone: $phone")
                pendingPhone = phone
            }
        }
    }

    // ⭐ Extract phone from various possible locations in the intent
    private fun extractPhoneFromIntent(intent: Intent): String? {
        val extras = intent.extras ?: return null

        // Method 1: Direct phone extra (from OneSignal additionalData)
        extras.getString("phone")?.let {
            if (it.isNotEmpty()) return it
        }

        // Method 2: Check in nested Bundle (OneSignal sometimes nests data)
        extras.getBundle("onesignal_data")?.getString("phone")?.let {
            if (it.isNotEmpty()) return it
        }

        // Method 3: Parse from custom data string
        extras.getString("custom")?.let { custom ->
            try {
                val json = org.json.JSONObject(custom)
                val additionalData = json.optJSONObject("a")
                additionalData?.optString("phone")?.let {
                    if (it.isNotEmpty()) return it
                }
            } catch (e: Exception) {
                android.util.Log.e("OneSignal", "Error parsing custom data: ${e.message}")
            }
        }

        return null
    }

    // ⭐ Handle OneSignal notification click (when app is in foreground)
    private fun setupOneSignalClickListener() {
        OneSignal.Notifications.addClickListener(object : INotificationClickListener {
            override fun onClick(event: INotificationClickEvent) {
                android.util.Log.d("OneSignal", "=== Notification Clicked ===")

                val notification = event.notification
                val additionalData = notification.additionalData

                // Debug: Log all available data
                android.util.Log.d("OneSignal", "Title: ${notification.title}")
                android.util.Log.d("OneSignal", "Body: ${notification.body}")
                android.util.Log.d("OneSignal", "AdditionalData: $additionalData")

                // Try to get phone from additionalData
                var phone: String? = null

                if (additionalData != null) {
                    // Method 1: Direct phone key
                    phone = additionalData.optString("phone", null)

                    // Method 2: If phone is empty, try other keys
                    if (phone.isNullOrEmpty()) {
                        phone = additionalData.optString("wa_number", null)
                    }
                }


                if (!phone.isNullOrEmpty()) {
                    android.util.Log.d("OneSignal", "✅ Phone found: $phone")
                    runOnUiThread {
                        if (::webView.isInitialized) {
                            openChatByPhone(phone)
                        } else {
                            pendingPhone = phone
                            android.util.Log.d("OneSignal", "WebView not ready, stored pendingPhone")
                        }
                    }
                } else {
                    android.util.Log.d("OneSignal", "❌ No phone found in notification data")
                }
            }
        })
    }

    // ⭐ Call JavaScript to open specific chat with retry mechanism
    private fun openChatByPhone(phone: String, retryAttempt: Int = 0) {
        val cleanPhone = phone.replace(Regex("[^0-9]"), "") // Clean phone number
        android.util.Log.d("OneSignal", "openChatByPhone attempt $retryAttempt: $cleanPhone")

        // Cancel the notification for this phone immediately
        NotificationHelper.cancelNotification(this, cleanPhone)

        val jsCode = """
            (function() {
                console.log('Android calling openChatByPhone: $cleanPhone');
                if(window.openChatByPhone) { 
                    window.openChatByPhone('$cleanPhone'); 
                    return 'called';
                } else { 
                    console.log('openChatByPhone not ready');
                    return 'not_ready';
                }
            })();
        """.trimIndent()

        webView.evaluateJavascript(jsCode) { result ->
            val status = result?.replace("\"", "") ?: ""
            android.util.Log.d("OneSignal", "openChatByPhone result: $status")

            // If function not ready and we haven't exceeded retries, try again
            if (status == "not_ready" && retryAttempt < 5) {
                webView.postDelayed({
                    openChatByPhone(phone, retryAttempt + 1)
                }, 1000) // Wait 1 second before retry
            }
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun setupWebView() {
        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            databaseEnabled = true
            // Prefer network when Cache-Control says so (index.html / version.json)
            cacheMode = WebSettings.LOAD_DEFAULT
            allowFileAccess = true
            allowContentAccess = true
            mediaPlaybackRequiresUserGesture = false
            builtInZoomControls = true
            displayZoomControls = false
            userAgentString = "$userAgentString CMSChatApp/1.0"
            loadsImagesAutomatically = true
            mixedContentMode = WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE
        }

        webView.webViewClient = object : WebViewClient() {
            // ⭐ Check for pending notification after page loads
            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)

                // If there's a pending phone from notification, open it now
                pendingPhone?.let { phone ->
                    android.util.Log.d("OneSignal", "Page loaded, opening pending chat: $phone")
                    // Small delay to ensure Vue app is fully initialized
                    view?.postDelayed({
                        openChatByPhone(phone)
                    }, 500)
                    pendingPhone = null
                }
            }

            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val url = request?.url?.toString() ?: return false
                val uri = request.url ?: return false
                val host = (uri.host ?: "").lowercase()

                if (url.startsWith("intent://")) {
                    try {
                        val intent = Intent.parseUri(url, Intent.URI_INTENT_SCHEME)
                        val info = packageManager.resolveActivity(intent, PackageManager.MATCH_DEFAULT_ONLY)
                        if (info != null) {
                            startActivity(intent)
                        } else {
                            val fallbackUrl = intent.getStringExtra("browser_fallback_url")
                            if (fallbackUrl != null) {
                                view?.loadUrl(fallbackUrl)
                            }
                        }
                    } catch (e: Exception) {
                        e.printStackTrace()
                    }
                    return true
                }

                if (url.contains("maps.google.com") || url.contains("google.com/maps")) {
                    try {
                        startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
                        return true
                    } catch (e: Exception) {
                        return false
                    }
                }

                if (url.startsWith("tel:") || url.startsWith("mailto:") || url.startsWith("whatsapp:")) {
                    try {
                        startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
                        return true
                    } catch (e: Exception) {
                        return false
                    }
                }

                // Keep CRM itself inside WebView; open everything else externally
                // (invoice ml.nalju.com, laundry portal, etc.)
                val isCmsApp =
                    host == "cms.nalju.com" ||
                    host.endsWith(".cms.nalju.com") ||
                    host == "localhost" ||
                    host == "127.0.0.1"

                if ((url.startsWith("http://") || url.startsWith("https://")) && !isCmsApp) {
                    try {
                        startActivity(Intent(Intent.ACTION_VIEW, uri))
                        return true
                    } catch (e: Exception) {
                        e.printStackTrace()
                        return false
                    }
                }

                return false
            }
        }

        webView.settings.setSupportMultipleWindows(true)
        webView.settings.javaScriptCanOpenWindowsAutomatically = true

        webView.webChromeClient = object : WebChromeClient() {
            override fun onCreateWindow(
                view: WebView?,
                isDialog: Boolean,
                isUserGesture: Boolean,
                resultMsg: android.os.Message?
            ): Boolean {
                // target=_blank from bubble chat → open in external browser
                val transport = resultMsg?.obj as? WebView.WebViewTransport ?: return false
                val tempWebView = WebView(view?.context ?: this@MainActivity)
                tempWebView.webViewClient = object : WebViewClient() {
                    override fun shouldOverrideUrlLoading(
                        v: WebView?,
                        request: WebResourceRequest?
                    ): Boolean {
                        val target = request?.url?.toString() ?: return true
                        try {
                            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(target)))
                        } catch (e: Exception) {
                            e.printStackTrace()
                        }
                        return true
                    }
                }
                transport.webView = tempWebView
                resultMsg.sendToTarget()
                return true
            }

            override fun onShowFileChooser(
                webView: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                this@MainActivity.filePathCallback?.onReceiveValue(null)
                this@MainActivity.filePathCallback = filePathCallback

                try {
                    val intent = Intent(Intent.ACTION_GET_CONTENT).apply {
                        addCategory(Intent.CATEGORY_OPENABLE)
                        type = "image/*"
                    }
                    fileChooserLauncher.launch(intent)
                } catch (e: Exception) {
                    this@MainActivity.filePathCallback = null
                    return false
                }
                return true
            }
        }

        webView.addJavascriptInterface(OneSignalInterface(this), "OneSignalInterface")
        webView.addJavascriptInterface(JSBridge(), "Android") // For exitApp() bridge

        // Check remote version first; clear WebView cache when deploy is newer
        checkWebVersion(reloadIfUpdated = false, forceLoad = true)
    }

    /**
     * Fetch cms.nalju.com/version.json (no-cache). If version changed vs SharedPreferences,
     * clear WebView HTTP cache and reload so JS/CSS updates appear without manual clear.
     */
    private fun checkWebVersion(reloadIfUpdated: Boolean, forceLoad: Boolean = false) {
        val now = System.currentTimeMillis()
        if (!forceLoad && now - lastVersionCheckAt < VERSION_CHECK_MIN_INTERVAL_MS) return
        if (isVersionCheckRunning) return
        isVersionCheckRunning = true
        lastVersionCheckAt = now

        versionCheckExecutor.execute {
            var remoteVersion: String? = null
            try {
                val url = URL("$VERSION_URL?_t=$now")
                val conn = (url.openConnection() as HttpURLConnection).apply {
                    connectTimeout = 5000
                    readTimeout = 5000
                    useCaches = false
                    setRequestProperty("Cache-Control", "no-cache")
                    setRequestProperty("Pragma", "no-cache")
                }
                conn.inputStream.bufferedReader().use { reader ->
                    val body = reader.readText()
                    remoteVersion = JSONObject(body).optString("version", "").ifBlank { null }
                }
                conn.disconnect()
            } catch (e: Exception) {
                android.util.Log.w("VersionCheck", "Failed to fetch version.json: ${e.message}")
            }

            val prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
            val localVersion = prefs.getString(PREF_WEB_VERSION, null)
            val versionChanged =
                !remoteVersion.isNullOrEmpty() && remoteVersion != localVersion
            val shouldClear =
                versionChanged && localVersion != null // skip clear on first install

            runOnUiThread {
                try {
                    if (!::webView.isInitialized) return@runOnUiThread

                    if (shouldClear) {
                        android.util.Log.d(
                            "VersionCheck",
                            "New web version $remoteVersion (was $localVersion) — clearing cache"
                        )
                        webView.clearCache(true)
                    } else if (versionChanged) {
                        android.util.Log.d(
                            "VersionCheck",
                            "First web version recorded: $remoteVersion"
                        )
                    }

                    if (!remoteVersion.isNullOrEmpty() && versionChanged) {
                        prefs.edit().putString(PREF_WEB_VERSION, remoteVersion).apply()
                    }

                    when {
                        forceLoad -> webView.loadUrl(WEB_URL)
                        shouldClear && reloadIfUpdated -> {
                            // Bust any leftover HTML cache
                            webView.loadUrl("$WEB_URL?_v=${System.currentTimeMillis()}")
                        }
                    }
                } finally {
                    isVersionCheckRunning = false
                }
            }
        }
    }

    private fun setupBackHandler() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                android.util.Log.d("BackHandler", "🔙 Back button pressed")
                
                // PURE WEBVIEW APPROACH: Simply trigger JavaScript handler
                // Let Pinia/Vue decide what to do (navigate or exit)
                webView.evaluateJavascript("window.__ANDROID_BACK && window.__ANDROID_BACK()", null)
            }
        })
    }
}
