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

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private var filePathCallback: ValueCallback<Array<Uri>>? = null
    private var pendingPhone: String? = null // Store phone from notification

    companion object {
        private const val WEB_URL = "https://cms.nalju.com/"
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
    override fun onResume() {
        super.onResume()

        if (::webView.isInitialized) {
            android.util.Log.d("MainActivity", "App resumed from background")

            // CRITICAL FIX: After long sleep, WebView JavaScript context may be broken
            // We need to ensure:
            // 1. History state is properly set for back button
            // 2. Vue handlers are still functional
            // 3. Force reload if everything is broken

            val jsCode = """
                (function() {
                    try {
                        console.log('Android: App resumed from background');
                        
                        // STEP 1: Force-push history state to fix back button
                        // After long sleep, history stack may be corrupted
                        var hash = window.location.hash;
                        var savedChatId = localStorage.getItem('active_chat_id');
                        var savedMobileChat = localStorage.getItem('show_mobile_chat');
                        
                        console.log('Resume state: hash=' + hash + ', chatId=' + savedChatId + ', mobileChat=' + savedMobileChat);
                        
                        // If user was in chat (based on hash or localStorage), ensure history state exists
                        if (hash && hash.startsWith('#chat=')) {
                            // Re-push state to ensure back button works
                            window.history.pushState({chatOpen: true, timestamp: Date.now()}, '', hash);
                            console.log('Re-pushed history state for hash: ' + hash);
                        } else if (savedChatId && savedMobileChat === 'true') {
                            // Push state based on localStorage
                            window.history.pushState({chatOpen: true, timestamp: Date.now()}, '', '#chat=' + savedChatId);
                            console.log('Pushed history state from localStorage: #chat=' + savedChatId);
                        }
                        
                        // STEP 2: Check if Vue app is still alive
                        var isAppAlive = typeof window.onAndroidBackPressed === 'function';
                        console.log('Vue app alive = ' + isAppAlive);
                        
                        if (!isAppAlive) {
                            console.log('Vue app not ready, setting recovery flags...');
                            
                            if (savedChatId && savedMobileChat === 'true') {
                                sessionStorage.setItem('android_recovery_mode', 'chat');
                                sessionStorage.setItem('android_recovery_chat_id', savedChatId);
                            }
                            
                            // Mark that we need recovery on back press
                            window.__androidRecoveryPending = true;
                            
                            return 'recovery_pending';
                        }
                        
                        // STEP 3: App is alive - trigger resume handlers
                        window.dispatchEvent(new CustomEvent('androidResume'));
                        
                        if (window.triggerReconnect) {
                            window.triggerReconnect();
                        }
                        
                        if (window.__reExposeBackHandler) {
                            window.__reExposeBackHandler();
                        }
                        
                        // STEP 4: Return alive confirmation with timestamp
                        return 'alive_' + Date.now();
                        
                    } catch(e) {
                        console.error('Resume error: ' + e);
                        return 'error_' + e.message;
                    }
                })();
            """.trimIndent()

            // Use a timeout mechanism - if JS doesn't respond, WebView may be broken
            var jsResponded = false

            webView.evaluateJavascript(jsCode) { result ->
                jsResponded = true
                val status = result?.replace("\"", "") ?: "null"
                android.util.Log.d("MainActivity", "Resume JS result: $status")

                when {
                    status.startsWith("alive_") -> {
                        android.util.Log.d("MainActivity", "✅ WebView healthy, back button should work")
                    }
                    status.startsWith("recovery_pending") -> {
                        android.util.Log.d("MainActivity", "⚠️ Vue app stale, recovery mode active")
                    }
                    status.startsWith("error_") || status == "null" -> {
                        android.util.Log.e("MainActivity", "❌ JS execution failed, may need reload")
                    }
                }
            }

            // Fallback: If JS doesn't respond within 2 seconds, WebView is likely broken
            webView.postDelayed({
                if (!jsResponded) {
                    android.util.Log.e("MainActivity", "❌ JS did not respond within 2s, forcing reload")
                    webView.reload()
                }
            }, 2000)
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
            cacheMode = WebSettings.LOAD_CACHE_ELSE_NETWORK
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

                return false
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
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
        webView.loadUrl(WEB_URL)
    }

    private fun setupBackHandler() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                android.util.Log.d("BackHandler", "🔙 Back button pressed")

                // Priority 1: Check if WebView can go back (external links)
                val currentUrl = webView.url ?: ""
                val isOnMainPage = currentUrl.startsWith(WEB_URL) && !currentUrl.contains("?")

                if (webView.canGoBack() && !isOnMainPage) {
                    android.util.Log.d("BackHandler", "📄 WebView.goBack() - external URL")
                    webView.goBack()
                    return
                }

                // Priority 2: Let Vue/Pinia handle navigation (CLEAN APPROACH)
                val jsCode = """
                    (function() {
                        try {
                            console.log('🔙 Android back press → JS Bridge');
                            
                            // Check if Vue app has exposed the handler
                            if (typeof window.onAndroidBackPressed === 'function') {
                                var result = window.onAndroidBackPressed();
                                console.log('✅ Pinia/Vue handled back:', result);
                                return result;
                            }
                            
                            // Fallback: Vue app not ready (stale after sleep or initial load)
                            console.warn('⚠️ Vue handler not ready, using localStorage fallback');
                            
                            // Check localStorage for navigation state
                            var navState = localStorage.getItem('nav_state');
                            if (navState) {
                                try {
                                    var state = JSON.parse(navState);
                                    console.log('📦 Restored state:', state);
                                    
                                    // If user is in a child view (chat, settings, etc)
                                    if (state.isChildView) {
                                        // Clear child view state
                                        localStorage.setItem('nav_state', JSON.stringify({
                                            isChildView: false,
                                            parentView: 'list',
                                            timestamp: Date.now()
                                        }));
                                        
                                        // Reload to reset to parent view
                                        console.log('🔄 Reloading to parent view...');
                                        window.location.reload();
                                        return 'navigated_to_parent';
                                    }
                                } catch(e) {
                                    console.error('Failed to parse nav_state:', e);
                                }
                            }
                            
                            // No child view state - user is at root, OK to exit
                            console.log('✅ At root view, exit app');
                            return 'should_exit';
                        } catch(e) {
                            console.error('Back handler error: ' + e);
                            return 'error';
                        }
                    })();
                """.trimIndent()

                // Timeout safety: if JS doesn't respond in 800ms, exit app
                var jsResponded = false
                webView.postDelayed({
                    if (!jsResponded) {
                        android.util.Log.w("BackHandler", "⏱️ JS timeout (800ms), exiting app")
                        runOnUiThread {
                            isEnabled = false
                            onBackPressedDispatcher.onBackPressed()
                        }
                    }
                }, 800)

                webView.evaluateJavascript(jsCode) { result ->
                    jsResponded = true
                    val status = result?.replace("\"", "")?.trim() ?: "null"
                    android.util.Log.d("BackHandler", "📩 JS response: $status")

                    runOnUiThread {
                        when (status) {
                            "should_exit" -> {
                                // User at root view, exit app
                                android.util.Log.d("BackHandler", "🚪 Exiting app")
                                isEnabled = false
                                onBackPressedDispatcher.onBackPressed()
                            }
                            "navigated_to_parent",
                            "chat_closed",
                            "settings_closed",
                            "modal_closed" -> {
                                // Navigation handled by Vue/Pinia - stay in app
                                android.util.Log.d("BackHandler", "✅ $status - staying in app")
                            }
                            else -> {
                                // Unknown status or error - exit safely
                                android.util.Log.w("BackHandler", "⚠️ Unexpected status: $status, exiting")
                                isEnabled = false
                                onBackPressedDispatcher.onBackPressed()
                            }
                        }
                    }
                }
            }
        })
    }
}
