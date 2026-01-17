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
            // Notify JavaScript that app has resumed - trigger reconnection
            android.util.Log.d("MainActivity", "App resumed from background, triggering JS reconnect")
            
            val jsCode = """
                (function() {
                    console.log('Android: App resumed from background');
                    
                    // Trigger visibilitychange event manually for better compatibility
                    if (document.visibilityState === 'visible') {
                        // Dispatch custom event that our Vue app can listen to
                        window.dispatchEvent(new CustomEvent('androidResume'));
                    }
                    
                    // Also directly trigger reconnection if the function exists
                    if (window.triggerReconnect) {
                        window.triggerReconnect();
                        return 'reconnect_triggered';
                    }
                    return 'event_dispatched';
                })();
            """.trimIndent()
            
            webView.evaluateJavascript(jsCode) { result ->
                android.util.Log.d("MainActivity", "Resume JS result: $result")
            }
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
                android.util.Log.d("BackHandler", "=== Back button pressed ===")
                
                // Priority 1: Check if WebView can go back (e.g., user visited a link)
                val currentUrl = webView.url ?: ""
                val isOnMainPage = currentUrl.startsWith(WEB_URL) && !currentUrl.contains("?")
                
                android.util.Log.d("BackHandler", "Current URL: $currentUrl, isOnMainPage: $isOnMainPage")
                
                if (webView.canGoBack() && !isOnMainPage) {
                    android.util.Log.d("BackHandler", "Using WebView.goBack()")
                    webView.goBack()
                    return
                }
                
                // Priority 2: Let Vue app handle the back press
                // Use a more robust check that handles stale state after long sleep
                val jsCode = """
                    (function() {
                        try {
                            // First check if function exists
                            if (typeof window.onAndroidBackPressed === 'function') {
                                var result = window.onAndroidBackPressed();
                                console.log('onAndroidBackPressed returned: ' + result);
                                return result;
                            }
                            
                            // Function undefined - check localStorage for chat state (fallback after memory pressure)
                            console.log('onAndroidBackPressed not ready, checking localStorage...');
                            var activeChatId = localStorage.getItem('active_chat_id');
                            var showMobileChat = localStorage.getItem('show_mobile_chat');
                            
                            console.log('localStorage state - activeChatId: ' + activeChatId + ', showMobileChat: ' + showMobileChat);
                            
                            if (activeChatId && showMobileChat === 'true') {
                                // User was in chat - clean up storage and signal that we handled it
                                localStorage.removeItem('active_chat_id');
                                localStorage.removeItem('show_mobile_chat');
                                
                                // Try to trigger page reload to get back to menu state
                                // This handles the case where Vue state was lost during sleep
                                if (window.location.hash) {
                                    window.history.replaceState({}, '', window.location.pathname);
                                }
                                window.location.reload();
                                return 'chat_closed_fallback';
                            }
                            
                            // No chat state found - OK to exit
                            return 'should_exit';
                        } catch(e) {
                            console.error('Back handler error: ' + e);
                            return 'error';
                        }
                    })();
                """.trimIndent()
                
                webView.evaluateJavascript(jsCode) { result ->
                    val status = result?.replace("\"", "")?.trim() ?: "null"
                    android.util.Log.d("BackHandler", "JavaScript returned: $status")

                    runOnUiThread {
                        when (status) {
                            "should_exit" -> {
                                android.util.Log.d("BackHandler", "Exiting app")
                                isEnabled = false
                                onBackPressedDispatcher.onBackPressed()
                            }
                            "chat_closed",
                            "chat_closed_fallback",
                            "lightbox_closed",
                            "settings_closed",
                            "internal_browser_closed" -> {
                                android.util.Log.d("BackHandler", "Action handled: $status")
                                // Stay in app - action was handled
                            }
                            "toast_shown" -> {
                                android.util.Log.d("BackHandler", "Toast shown, waiting for second press")
                            }
                            "null", "undefined", "error", "" -> {
                                // JS function not available or error occurred
                                // Check localStorage directly as final fallback
                                android.util.Log.d("BackHandler", "JS error/undefined, checking localStorage directly")
                                
                                val storageCheckJs = """
                                    (function() {
                                        var chat = localStorage.getItem('active_chat_id');
                                        var mobile = localStorage.getItem('show_mobile_chat');
                                        if (chat && mobile === 'true') {
                                            localStorage.removeItem('active_chat_id');
                                            localStorage.removeItem('show_mobile_chat');
                                            window.location.reload();
                                            return 'reloading';
                                        }
                                        return 'no_chat';
                                    })();
                                """.trimIndent()
                                
                                webView.evaluateJavascript(storageCheckJs) { fallbackResult ->
                                    val fallbackStatus = fallbackResult?.replace("\"", "") ?: ""
                                    android.util.Log.d("BackHandler", "Fallback result: $fallbackStatus")
                                    
                                    runOnUiThread {
                                        if (fallbackStatus == "no_chat") {
                                            isEnabled = false
                                            onBackPressedDispatcher.onBackPressed()
                                        }
                                        // If 'reloading', the page will reload - don't exit
                                    }
                                }
                            }
                            else -> {
                                android.util.Log.d("BackHandler", "Unknown status: $status, using WebView fallback")
                                if (webView.canGoBack()) {
                                    webView.goBack()
                                } else {
                                    isEnabled = false
                                    onBackPressedDispatcher.onBackPressed()
                                }
                            }
                        }
                    }
                }
            }
        })
    }
}
