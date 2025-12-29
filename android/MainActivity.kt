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
                
                // 🔧 DEBUG TOAST - Hapus setelah debugging selesai
                runOnUiThread {
                    val debugMsg = if (!phone.isNullOrEmpty()) {
                        "Phone: $phone"
                    } else {
                        "No phone in data: $additionalData"
                    }
                    android.widget.Toast.makeText(
                        this@MainActivity,
                        "Notif clicked: $debugMsg",
                        android.widget.Toast.LENGTH_LONG
                    ).show()
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
                // Priority 1: Check if WebView can go back (e.g., user visited a link)
                // This handles the case where user clicked a link and wants to go back to chat
                val currentUrl = webView.url ?: ""
                val isOnMainPage = currentUrl.startsWith(WEB_URL) && !currentUrl.contains("?")
                
                if (webView.canGoBack() && !isOnMainPage) {
                    // If WebView has back history and we're not on main page, go back in WebView
                    webView.goBack()
                    return
                }
                
                // Priority 2: Let Vue app handle the back press
                webView.evaluateJavascript("window.onAndroidBackPressed()") { result ->
                    val status = result?.replace("\"", "") ?: ""

                    runOnUiThread {
                        when (status) {
                            "should_exit" -> {
                                isEnabled = false
                                onBackPressedDispatcher.onBackPressed()
                            }
                            "chat_closed",
                            "lightbox_closed",
                            "settings_closed" -> {}
                            "toast_shown" -> {}
                            else -> {
                                // Fallback: try WebView back or exit
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
