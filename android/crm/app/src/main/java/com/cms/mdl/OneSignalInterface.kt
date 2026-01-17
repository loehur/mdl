package com.cms.mdl

import android.content.Context
import android.util.Log
import android.webkit.JavascriptInterface
import com.onesignal.OneSignal

class OneSignalInterface(private val context: Context) {

    companion object {
        private const val TAG = "OneSignalInterface"
    }

    /**
     * Login user ke OneSignal dengan External User ID
     * Dipanggil dari JavaScript: window.OneSignalInterface.login("USER_ID")
     */
    @JavascriptInterface
    fun login(userId: String) {
        Log.d(TAG, "OneSignal login called with userId: $userId")

        if (userId.isNotEmpty()) {
            OneSignal.login(userId)
            Log.i(TAG, "OneSignal: User logged in - $userId")
        } else {
            Log.w(TAG, "OneSignal: Login failed - userId is empty")
        }
    }

    /**
     * Logout user dari OneSignal
     * Dipanggil dari JavaScript: window.OneSignalInterface.logout()
     */
    @JavascriptInterface
    fun logout() {
        Log.d(TAG, "OneSignal logout called")
        OneSignal.logout()
        Log.i(TAG, "OneSignal: User logged out")
    }
    
    /**
     * Cancel notification for a specific phone number
     * Called when user opens a chat - clears the notification for that contact
     * Dipanggil dari JavaScript: window.OneSignalInterface.cancelNotification("6281234567890")
     */
    @JavascriptInterface
    fun cancelNotification(phone: String) {
        Log.d(TAG, "cancelNotification called for phone: $phone")
        
        if (phone.isNotEmpty()) {
            val cleanPhone = phone.replace(Regex("[^0-9]"), "")
            NotificationHelper.cancelNotification(context, cleanPhone)
            Log.i(TAG, "Notification cancelled for: $cleanPhone")
        } else {
            Log.w(TAG, "cancelNotification: phone is empty")
        }
    }
    
    /**
     * Cancel all chat notifications
     * Dipanggil dari JavaScript: window.OneSignalInterface.cancelAllNotifications()
     */
    @JavascriptInterface
    fun cancelAllNotifications() {
        Log.d(TAG, "cancelAllNotifications called")
        NotificationHelper.cancelAllNotifications(context)
        Log.i(TAG, "All notifications cancelled")
    }
}