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
}