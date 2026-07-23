package com.mdl.printbridge

import android.Manifest
import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.mdl.printbridge.databinding.ActivityMainBinding
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import kotlin.concurrent.thread

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var prefs: Prefs
    private val bonded = mutableListOf<BluetoothDevice>()
    private val bluetoothPrinter = BluetoothPrinter()

    private val permissionLauncher =
        registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { result ->
            if (result.values.all { it }) {
                loadBondedDevices()
            } else {
                toast(getString(R.string.perm_required))
            }
        }

    private val statusReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            if (intent?.action != PrintBridgeService.ACTION_STATUS) return
            val running = intent.getBooleanExtra(PrintBridgeService.EXTRA_RUNNING, false)
            updateServerUi(running)
            intent.getStringExtra(PrintBridgeService.EXTRA_LOG)?.let { appendLog(it) }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)
        prefs = Prefs(this)

        if (prefs.lineWidth == 48) {
            binding.width48.isChecked = true
        } else {
            binding.width32.isChecked = true
        }

        binding.btnRefreshPrinters.setOnClickListener { ensureBluetoothThen { loadBondedDevices() } }
        binding.btnSave.setOnClickListener { saveSettings() }
        binding.btnToggleServer.setOnClickListener { toggleServer() }
        binding.btnTestPrint.setOnClickListener { testPrint() }

        updateServerUi(PrintBridgeService.isRunning)
        ensureBluetoothThen { loadBondedDevices() }
    }

    override fun onStart() {
        super.onStart()
        val filter = IntentFilter(PrintBridgeService.ACTION_STATUS)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            registerReceiver(statusReceiver, filter, RECEIVER_NOT_EXPORTED)
        } else {
            registerReceiver(statusReceiver, filter)
        }
        updateServerUi(PrintBridgeService.isRunning)
    }

    override fun onStop() {
        try {
            unregisterReceiver(statusReceiver)
        } catch (_: Exception) {
        }
        super.onStop()
    }

    override fun onDestroy() {
        bluetoothPrinter.shutdown()
        super.onDestroy()
    }

    private fun neededPermissions(): Array<String> {
        val list = mutableListOf<String>()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            list += Manifest.permission.BLUETOOTH_CONNECT
            list += Manifest.permission.BLUETOOTH_SCAN
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            list += Manifest.permission.POST_NOTIFICATIONS
        }
        return list.toTypedArray()
    }

    private fun hasPermissions(): Boolean {
        return neededPermissions().all {
            ContextCompat.checkSelfPermission(this, it) == PackageManager.PERMISSION_GRANTED
        }
    }

    private fun ensureBluetoothThen(block: () -> Unit) {
        if (!hasPermissions()) {
            permissionLauncher.launch(neededPermissions())
            return
        }
        val adapter = bluetoothAdapter()
        if (adapter == null || !adapter.isEnabled) {
            toast(getString(R.string.bt_off))
            return
        }
        block()
    }

    private fun bluetoothAdapter(): BluetoothAdapter? {
        val mgr = getSystemService(BluetoothManager::class.java)
        return mgr?.adapter
    }

    @SuppressLint("MissingPermission")
    private fun loadBondedDevices() {
        bonded.clear()
        val adapter = bluetoothAdapter() ?: return
        val devices = adapter.bondedDevices?.sortedBy { it.name ?: it.address } ?: emptyList()
        bonded.addAll(devices)

        val labels = devices.map { d ->
            val n = d.name ?: "Unknown"
            "$n (${d.address})"
        }
        val adapterUi = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, labels)
        binding.printerSpinner.adapter = adapterUi

        val saved = prefs.printerAddress
        val idx = devices.indexOfFirst { it.address.equals(saved, ignoreCase = true) }
        if (idx >= 0) {
            binding.printerSpinner.setSelection(idx)
            binding.printerInfo.text = "${devices[idx].name}\n${devices[idx].address}"
        } else if (devices.isNotEmpty()) {
            binding.printerInfo.text = getString(R.string.no_printer)
        } else {
            binding.printerInfo.text = "Tidak ada perangkat bonded. Pair printer di Settings Android dulu."
        }
        appendLog("Bonded printers: ${devices.size}")
    }

    private fun saveSettings() {
        ensureBluetoothThen {
            val pos = binding.printerSpinner.selectedItemPosition
            if (pos in bonded.indices) {
                val d = bonded[pos]
                prefs.printerAddress = d.address
                prefs.printerName = d.name ?: d.address
                binding.printerInfo.text = "${prefs.printerName}\n${prefs.printerAddress}"
            }
            prefs.lineWidth = if (binding.width48.isChecked) 48 else 32
            appendLog("Saved printer=${prefs.printerName} width=${prefs.lineWidth}")
            toast("Pengaturan disimpan")
        }
    }

    private fun toggleServer() {
        if (PrintBridgeService.isRunning) {
            PrintBridgeService.stop(this)
        } else {
            if (prefs.printerAddress.isBlank()) {
                toast("Pilih & simpan printer dulu")
                return
            }
            ensureBluetoothThen {
                PrintBridgeService.start(this)
            }
        }
    }

    private fun testPrint() {
        ensureBluetoothThen {
            if (prefs.printerAddress.isBlank()) {
                toast("Pilih & simpan printer dulu")
                return@ensureBluetoothThen
            }
            binding.btnTestPrint.isEnabled = false
            thread {
                try {
                    val encoder = EscPosEncoder(
                        lineWidth = prefs.lineWidth,
                        lineSpacing = prefs.lineSpacing,
                        qrSize = prefs.qrSize,
                        qrErrorLevel = prefs.qrErrorLevel,
                        maxQrLength = prefs.maxQrLength
                    )
                    val data = encoder.encodeTest(prefs.printerName.ifBlank { prefs.printerAddress })
                    bluetoothPrinter.write(prefs.printerAddress, data)
                    runOnUiThread {
                        toast(getString(R.string.print_ok))
                        appendLog("Test print OK")
                        binding.btnTestPrint.isEnabled = true
                    }
                } catch (e: Exception) {
                    runOnUiThread {
                        toast("${getString(R.string.print_fail)}: ${e.message}")
                        appendLog("Test print FAIL: ${e.message}")
                        binding.btnTestPrint.isEnabled = true
                    }
                }
            }
        }
    }

    private fun updateServerUi(running: Boolean) {
        binding.statusText.text =
            if (running) getString(R.string.service_running) else getString(R.string.service_stopped)
        binding.statusText.setTextColor(
            ContextCompat.getColor(this, if (running) R.color.ok else R.color.warn)
        )
        binding.btnToggleServer.text =
            if (running) getString(R.string.stop_server) else getString(R.string.start_server)
    }

    private fun appendLog(msg: String) {
        val ts = SimpleDateFormat("HH:mm:ss", Locale.getDefault()).format(Date())
        val line = "[$ts] $msg"
        val cur = binding.logText.text?.toString().orEmpty()
        val next = if (cur.isBlank()) line else "$line\n$cur"
        binding.logText.text = next.lines().take(40).joinToString("\n")
    }

    private fun toast(msg: String) {
        Toast.makeText(this, msg, Toast.LENGTH_SHORT).show()
    }
}
