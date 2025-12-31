<?php
namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

class CashManagement extends Controller
{
    private $db_index = 5; // salon database

    public function __construct()
    {
        $this->verifyAuth();
    }

    private function verifyAuth()
    {
        if (!isset($_SESSION['salon_user_session'])) {
            $this->error('Unauthorized', 401);
        }
    }
    
    // Check if user is admin
    private function isAdmin() {
        return ($_SESSION['salon_user_session']['user']['role'] ?? '') === 'admin';
    }

    /**
     * GET - Main dashboard data (balances, recent transactions)
     * /api/Beauty_Salon/CashManagement/balances?cash_source=main|cashier
     */
    public function balances()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $cash_source = $_GET['cash_source'] ?? 'main';

            if (!$salon_id) {
                $this->error('Session tidak valid', 401);
            }

            $currentBalance = 0;
            $totalIncome = 0;
            $totalExpense = 0;

            // Helper function to safely get numeric value from SUM query
            $getSumValue = function($result) {
                if (!$result || !is_array($result)) return 0;
                $value = $result['total'] ?? null;
                return ($value !== null && $value !== '') ? (float)$value : 0;
            };

            // Calculate total income (from completed orders and potentially other sources)
            $incomeTransactions = $this->db($this->db_index)
                ->query("SELECT COALESCE(SUM(amount), 0) as total FROM cash_transactions WHERE salon_id = ? AND transaction_type = 'income' AND cash_source = ?", [$salon_id, $cash_source])
                ->row_array();
            $totalIncome += $getSumValue($incomeTransactions);

            // Add Income from Orders
            // Cashier receives Cash payments
            // Main receives Non-Cash payments (Assumption based on existing logic)
            if ($cash_source === 'cashier') {
                $orderIncome = $this->db($this->db_index)
                    ->query("SELECT COALESCE(SUM(pay_cash), 0) as total FROM orders WHERE salon_id = ? AND status = 'completed'", [$salon_id])
                    ->row_array();
                $totalIncome += $getSumValue($orderIncome);
            } else {
                // Main Cash receives Non-Cash (Transfer/QRIS/etc)
                $orderIncome = $this->db($this->db_index)
                    ->query("SELECT COALESCE(SUM(pay_non_cash), 0) as total FROM orders WHERE salon_id = ? AND status = 'completed'", [$salon_id])
                    ->row_array();
                $totalIncome += $getSumValue($orderIncome);
            }

            // Calculate total expense (from expenses and transfers out)
            $expenseTransactions = $this->db($this->db_index)
                ->query("SELECT COALESCE(SUM(amount), 0) as total FROM cash_transactions WHERE salon_id = ? AND transaction_type = 'expense' AND cash_source = ?", [$salon_id, $cash_source])
                ->row_array();
            $totalExpense += $getSumValue($expenseTransactions);
            
            // Calculate total transfers out
            $transferOutTransactions = $this->db($this->db_index)
                ->query("SELECT COALESCE(SUM(amount), 0) as total FROM cash_transactions WHERE salon_id = ? AND transaction_type = 'transfer' AND transfer_from = ?", [$salon_id, $cash_source])
                ->row_array();
            $totalExpense += $getSumValue($transferOutTransactions);
            
             // Calculate total transfers in
             // Note: 'income' type transactions are general, but transfers have specific source/dest
             $transferInTransactions = $this->db($this->db_index)
                ->query("SELECT COALESCE(SUM(amount), 0) as total FROM cash_transactions WHERE salon_id = ? AND transaction_type = 'transfer' AND transfer_to = ?", [$salon_id, $cash_source])
                ->row_array();
             $totalIncome += $getSumValue($transferInTransactions);

            $currentBalance = $totalIncome - $totalExpense;

            $this->json([
                'success' => true,
                'data' => [
                    'current_balance' => $currentBalance,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense
                ]
            ]);

        } catch (\Exception $e) {
            error_log("CashManagement balances error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Transaction History
     * /api/Beauty_Salon/CashManagement/transactions?cash=main|cashier&start_date=..&end_date=..
     */
    /**
     * GET - Transaction History
     * /api/Beauty_Salon/CashManagement/transactions?cash=main|cashier&type=expense|income|transfer
     */
    public function transactions()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $cash_source = $_GET['cash'] ?? 'main';
            $type = $_GET['type'] ?? '';
            
            $limit = 100; // Limit last 100 transactions

            // Build query
            $sql = "SELECT 
                    ct.id,
                    ct.transaction_type,
                    ct.transaction_date,
                    ct.amount,
                    ct.cash_source,
                    ct.transfer_from,
                    ct.transfer_to,
                    ct.category_id,
                    ec.name as category_name,
                    ec.is_expense,
                    ct.description,
                    ct.notes,
                    ct.reference_type,
                    ct.reference_id,
                    ct.created_at,
                    (SELECT COUNT(*) FROM inventory_purchases ip WHERE ip.transaction_id = ct.id) as inventory_count
                    FROM cash_transactions ct
                    LEFT JOIN expense_categories ec ON ct.category_id = ec.id
                    WHERE ct.salon_id = ?";
            
            $params = [$salon_id];

            // Filter by Type
            if ($type) {
                $sql .= " AND ct.transaction_type = ?";
                $params[] = $type;
            }

            // Filter by Cash Source context
            // We want transactions involving this cash source
            if ($type === 'expense') {
                $sql .= " AND ct.cash_source = ?";
                $params[] = $cash_source;
            } elseif ($type === 'income') {
                $sql .= " AND ct.cash_source = ?";
                $params[] = $cash_source;
            } elseif ($type === 'transfer') {
                // For transfers, we want to see if it's From OR To this source
                // If specific source is requested. 
                // However, the frontend might request 'type=transfer' without specific cash source to see all?
                // But usually it sends cash=main context.
                // Let's stick to the requested cash source logic if provided.
                if (!empty($_GET['cash'])) {
                     $sql .= " AND (ct.transfer_from = ? OR ct.transfer_to = ?)";
                     $params[] = $cash_source;
                     $params[] = $cash_source;
                }
            } else {
                // No specific type filtered, so show all related to this cash source
                if (!empty($_GET['cash'])) {
                    $sql .= " AND (
                        ct.cash_source = ? 
                        OR ct.transfer_from = ? 
                        OR ct.transfer_to = ?
                    )";
                    $params[] = $cash_source;
                    $params[] = $cash_source;
                    $params[] = $cash_source;
                }
            }

            $sql .= " ORDER BY ct.transaction_date DESC, ct.created_at DESC LIMIT ?";
            $params[] = $limit;

            $transactions = $this->db($this->db_index)
                ->query($sql, $params)
                ->result_array();

            $this->json([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (\Exception $e) {
            error_log("CashManagement transactions error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Expense Categories
     * /api/Beauty_Salon/CashManagement/categories
     */
    public function categories()
    {
        try {
            $categories = $this->db($this->db_index)
                ->query("SELECT id, name, is_expense, description, is_active 
                        FROM expense_categories 
                        WHERE is_active = 1 
                        ORDER BY is_expense DESC, name ASC")
                ->result_array();

            $this->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            $this->error('Gagal mengambil kategori', 500);
        }
    }

    /**
     * POST - Create Expense / Transfer
     * /api/Beauty_Salon/CashManagement/expense (or transfer)
     */
    public function expense()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $user_id = $_SESSION['salon_user_session']['user']['id'] ?? null;

            if (!$salon_id) {
                $this->error('Session tidak valid', 401);
            }

            // Validation
            if (empty($body['amount']) || !is_numeric($body['amount']) || $body['amount'] <= 0) {
                $this->error('Jumlah tidak valid', 400);
            }
            if (empty($body['cash_source'])) {
                $this->error('Sumber kas harus ditentukan', 400);
            }
            if (empty($body['category_id'])) {
                $this->error('Kategori harus dipilih', 400);
            }

            // Insert expense transaction
            $data = [
                'salon_id' => $salon_id,
                'transaction_type' => 'expense',
                'transaction_date' => $body['date'] ?? date('Y-m-d'),
                'amount' => $body['amount'],
                'cash_source' => $body['cash_source'],
                'category_id' => $body['category_id'],
                'description' => $body['description'],
                'notes' => $body['notes'] ?? null,
                'reference_type' => 'manual',
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->db($this->db_index)->insert('cash_transactions', $data);

            if ($id) {
                $this->json([
                    'success' => true,
                    'message' => 'Pengeluaran berhasil disimpan',
                    'data' => ['transaction_id' => $id]
                ]);
            } else {
                $this->error('Gagal menyimpan pengeluaran', 500);
            }
        } catch (\Exception $e) {
            error_log("CashManagement expense error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Create Income (e.g. Capital Injection)
     * /api/Beauty_Salon/CashManagement/addIncome
     */
    public function addIncome()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $user_id = $_SESSION['salon_user_session']['user']['id'] ?? null;

            if (!$salon_id) {
                $this->error('Session tidak valid', 401);
            }

            // Validation
            if (empty($body['amount']) || !is_numeric($body['amount']) || $body['amount'] <= 0) {
                $this->error('Jumlah tidak valid', 400);
            }
            if (empty($body['cash_source'])) {
                $this->error('Sumber kas harus ditentukan', 400);
            }
            // Category ID is optional for income

            // Insert income transaction
            $data = [
                'salon_id' => $salon_id,
                'transaction_type' => 'income',
                'transaction_date' => $body['date'] ?? date('Y-m-d'),
                'amount' => $body['amount'],
                'cash_source' => $body['cash_source'], // e.g., 'main'
                'category_id' => $body['category_id'] ?? null,
                'description' => $body['description'],
                'notes' => $body['notes'] ?? null,
                'reference_type' => 'manual',
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->db($this->db_index)->insert('cash_transactions', $data);

            if ($id) {
                $this->json([
                    'success' => true,
                    'message' => 'Pemasukan berhasil disimpan',
                    'data' => ['transaction_id' => $id]
                ]);
            } else {
                $this->error('Gagal menyimpan pemasukan', 500);
            }
        } catch (\Exception $e) {
            error_log("CashManagement addIncome error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    public function transfer()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $user_id = $_SESSION['salon_user_session']['user']['id'] ?? null;

            if (!$salon_id) {
                $this->error('Session tidak valid', 401);
            }

             // Validation
             if (empty($body['amount']) || !is_numeric($body['amount']) || $body['amount'] <= 0) {
                $this->error('Jumlah tidak valid', 400);
            }
            if (empty($body['from']) || empty($body['to'])) {
                $this->error('Sumber dan tujuan transfer harus ditentukan', 400);
            }
            if ($body['from'] === $body['to']) {
                $this->error('Sumber dan tujuan tidak boleh sama', 400);
            }

            // Insert transfer transaction
            // We record this as a 'transfer' type.
            // In a double-entry system we'd have 2 rows, but for this simple schema:
            // One row with 'transfer' type, cash_source='from', transfer_to='to'
            
            $data = [
                'salon_id' => $salon_id,
                'transaction_type' => 'transfer',
                'transaction_date' => date('Y-m-d'),
                'amount' => $body['amount'],
                'cash_source' => $body['from'], // Source cash for tracking
                'transfer_from' => $body['from'],
                'transfer_to' => $body['to'],
                'description' => $body['description'],
                'notes' => $body['notes'] ?? null,
                'reference_type' => 'transfer',
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->db($this->db_index)->insert('cash_transactions', $data);

            if ($id) {
                $this->json([
                    'success' => true,
                    'message' => 'Transfer berhasil'
                ]);
            } else {
                $this->error('Gagal menyimpan transfer', 500);
            }

        } catch (\Exception $e) {
            error_log("CashManagement transfer error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Settle Inventory (Input Barang for Inventory Expense)
     * /api/Beauty_Salon/CashManagement/settleInventory
     */
    public function settleInventory()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;

            $trxId = $body['transaction_id'] ?? null;
            $items = $body['items'] ?? [];

            if (!$trxId || empty($items)) {
                $this->error('Data tidak lengkap', 400);
            }

            // 1. Get Transaction
            $trx = $this->db($this->db_index)
                ->get_where('cash_transactions', ['id' => $trxId, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$trx) {
                $this->error('Transaksi tidak ditemukan', 404);
            }

            // 2. Validate Item Total matches Transaction Amount
            $totalItems = 0;
            foreach ($items as $item) {
                $totalItems += ($item['qty'] * $item['buy_price']);
            }

            if (abs($totalItems - $trx['amount']) > 1.0) { // Tolerance of 1
                $this->error("Total barang (Rp " . number_format($totalItems) . ") tidak sesuai dengan total nota (Rp " . number_format($trx['amount']) . ")", 400);
            }

            // 3. Insert Items and Update Stock
            foreach ($items as $item) {
                if (empty($item['name'])) continue; // Skip empty names
                
                // Determine total for this line
                $lineTotal = (float)$item['qty'] * (float)$item['buy_price'];
                
                // Logic for item_id: reuse if name and price match (case-insensitive) for THIS salon
                $existing = $this->db($this->db_index)
                    ->query("SELECT ip.item_id 
                            FROM inventory_purchases ip 
                            JOIN cash_transactions ct ON ip.transaction_id = ct.id 
                            WHERE ct.salon_id = ? AND LOWER(ip.item_name) = LOWER(?) AND ip.buy_price = ? 
                            LIMIT 1", [$salon_id, $item['name'], $item['buy_price']])
                    ->row_array();
                
                if ($existing && !empty($existing['item_id'])) {
                    $itemId = $existing['item_id'];
                } else {
                    $itemId = strtoupper(substr(md5(uniqid(microtime(), true)), 0, 8));
                }

                $res = $this->db($this->db_index)->insert('inventory_purchases', [
                    'transaction_id' => $trxId,
                    'item_id' => $itemId,
                    'item_name' => $item['name'],
                    'qty' => $item['qty'],
                    'buy_price' => $item['buy_price'],
                    'total_price' => $lineTotal,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                if (!$res) {
                    error_log("Failed to insert inventory purchase for item: " . $item['name']);
                }

                // Update Stock
                $prod = $this->db($this->db_index)
                     ->query("SELECT id, stock FROM products WHERE salon_id = ? AND name = ? LIMIT 1", [$salon_id, $item['name']])
                     ->row_array();

                 if ($prod) {
                     $newStock = (int)($prod['stock'] ?? 0) + (int)$item['qty'];
                     $this->db($this->db_index)->update('products', ['stock' => $newStock, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $prod['id']]);
                 }
            }

            $this->json([
                'success' => true,
                'message' => 'Pertanggungjawaban belanja berhasil disimpan'
            ]);

        } catch (\Exception $e) {
            error_log("CashManagement settleInventory error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Inventory History for Autocomplete
     * /api/Beauty_Salon/CashManagement/inventoryHistory
     */
    public function inventoryHistory()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            if (!$salon_id) $this->error('Session error', 401);

            // Group by item_id, name and price to get unique purchasing options
            $sql = "SELECT item_id, item_name, buy_price 
                    FROM inventory_purchases ip
                    JOIN cash_transactions ct ON ip.transaction_id = ct.id
                    WHERE ct.salon_id = ?
                    GROUP BY item_id, item_name, buy_price
                    ORDER BY MAX(ct.transaction_date) DESC, MAX(ip.id) DESC
                    LIMIT 200";

            $history = $this->db($this->db_index)
                ->query($sql, [$salon_id])
                ->result_array();

            $this->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            error_log("CashManagement inventoryHistory error: " . $e->getMessage());
            $this->error('Gagal memuat history', 500);
        }
    }

    /**
     * GET - Inventory Items List (Grouped by item_id)
     * /api/Beauty_Salon/CashManagement/inventoryItemsList
     */
    public function inventoryItemsList()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            if (!$salon_id) $this->error('Session error', 401);

            // Get all inventory items grouped by item_id
            $sql = "SELECT 
                        ip.item_id,
                        ip.item_name,
                        ip.buy_price,
                        ip.sell_price,
                        SUM(ip.qty) as total_purchased,
                        COUNT(DISTINCT ip.transaction_id) as transaction_count,
                        MAX(ct.transaction_date) as last_purchase_date
                    FROM inventory_purchases ip
                    JOIN cash_transactions ct ON ip.transaction_id = ct.id
                    WHERE ct.salon_id = ?
                    GROUP BY ip.item_id, ip.item_name, ip.buy_price, ip.sell_price";

            $items = $this->db($this->db_index)
                ->query($sql, [$salon_id])
                ->result_array();

            // Calculate current stock (purchased - sold) for each item
            $result = [];
            foreach ($items as $item) {
                // Get sold quantity for this item_id from inventory_sales
                $soldResult = $this->db($this->db_index)
                    ->query("SELECT COALESCE(SUM(qty), 0) as total_sold 
                            FROM inventory_sales 
                            WHERE salon_id = ? AND item_id = ?", [$salon_id, $item['item_id']])
                    ->row_array();
                
                $totalSold = (int)($soldResult['total_sold'] ?? 0);
                $totalPurchased = (int)$item['total_purchased'];
                $currentStock = $totalPurchased - $totalSold;
                
                $item['total_qty'] = $currentStock; // Current available stock
                $item['total_purchased'] = $totalPurchased;
                $item['total_sold'] = $totalSold;
                
                $result[] = $item;
            }

            // Sort: items without sell_price first, then by name
            usort($result, function($a, $b) {
                // Priority 1: Items without sell_price come first
                $aHasPrice = !empty($a['sell_price']) && $a['sell_price'] > 0;
                $bHasPrice = !empty($b['sell_price']) && $b['sell_price'] > 0;
                
                if (!$aHasPrice && $bHasPrice) return -1;
                if ($aHasPrice && !$bHasPrice) return 1;
                
                // Priority 2: Sort by name alphabetically
                return strcasecmp($a['item_name'], $b['item_name']);
            });

            $this->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            error_log("CashManagement inventoryItemsList error: " . $e->getMessage());
            $this->error('Gagal memuat data item', 500);
        }
    }

    /**
     * GET - Pending Inventory Transactions (expenses that need item details)
     * /api/Beauty_Salon/CashManagement/pendingInventoryTransactions
     */
    public function pendingInventoryTransactions()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            if (!$salon_id) $this->error('Session error', 401);

            // Get expense transactions with category containing 'persediaan' or 'stok'
            // that don't have any inventory_purchases linked to them
            $sql = "SELECT ct.id, ct.transaction_date, ct.description, ct.amount, 
                           ec.name as category_name
                    FROM cash_transactions ct
                    LEFT JOIN expense_categories ec ON ct.category_id = ec.id
                    LEFT JOIN inventory_purchases ip ON ct.id = ip.transaction_id
                    WHERE ct.salon_id = ? 
                      AND ct.transaction_type = 'expense'
                      AND (LOWER(ec.name) LIKE '%persediaan%' OR LOWER(ec.name) LIKE '%stok%')
                      AND ip.id IS NULL
                    ORDER BY ct.transaction_date DESC";

            $transactions = $this->db($this->db_index)
                ->query($sql, [$salon_id])
                ->result_array();

            $this->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            error_log("CashManagement pendingInventoryTransactions error: " . $e->getMessage());
            $this->error('Gagal memuat data', 500);
        }
    }

    /**
     * GET - Settled Inventory Transactions (expenses that already have item details)
     * /api/Beauty_Salon/CashManagement/settledInventoryTransactions?date_from=&date_to=&limit=
     */
    public function settledInventoryTransactions()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            if (!$salon_id) $this->error('Session error', 401);

            // Get query params
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $limit = min(intval($_GET['limit'] ?? 100), 200); // Max 200

            // Build query with optional date filter
            $params = [$salon_id];
            $dateCondition = "";
            
            if ($date_from) {
                $dateCondition .= " AND DATE(ct.transaction_date) >= ?";
                $params[] = $date_from;
            }
            if ($date_to) {
                $dateCondition .= " AND DATE(ct.transaction_date) <= ?";
                $params[] = $date_to;
            }

            // Get expense transactions that have inventory_purchases linked
            $sql = "SELECT ct.id, ct.transaction_date, ct.description, ct.amount, 
                           ec.name as category_name,
                           COUNT(ip.id) as item_count,
                           SUM(ip.qty) as total_qty
                    FROM cash_transactions ct
                    LEFT JOIN expense_categories ec ON ct.category_id = ec.id
                    INNER JOIN inventory_purchases ip ON ct.id = ip.transaction_id
                    WHERE ct.salon_id = ? 
                      AND ct.transaction_type = 'expense'
                      {$dateCondition}
                    GROUP BY ct.id
                    ORDER BY ct.transaction_date DESC
                    LIMIT {$limit}";

            $transactions = $this->db($this->db_index)
                ->query($sql, $params)
                ->result_array();

            $this->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            error_log("CashManagement settledInventoryTransactions error: " . $e->getMessage());
            $this->error('Gagal memuat data', 500);
        }
    }

    /**
     * POST - Update Sell Price for Inventory Item
     * /api/Beauty_Salon/CashManagement/updateSellPrice
     */
    public function updateSellPrice()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;

            if (!$salon_id) {
                $this->error('Session tidak valid', 401);
            }

            $item_id = $body['item_id'] ?? null;
            $sell_price = $body['sell_price'] ?? 0;

            if (!$item_id) {
                $this->error('Item ID tidak valid', 400);
            }

            if (!is_numeric($sell_price) || $sell_price < 0) {
                $this->error('Harga jual tidak valid', 400);
            }

            // Verify item belongs to this salon
            $item = $this->db($this->db_index)
                ->query("SELECT ip.id FROM inventory_purchases ip 
                        JOIN cash_transactions ct ON ip.transaction_id = ct.id 
                        WHERE ct.salon_id = ? AND ip.item_id = ? LIMIT 1", [$salon_id, $item_id])
                ->row_array();

            if (!$item) {
                $this->error('Item tidak ditemukan', 404);
            }

            // Update sell_price for all items with this item_id for this salon
            $sql = "UPDATE inventory_purchases ip
                    JOIN cash_transactions ct ON ip.transaction_id = ct.id
                    SET ip.sell_price = ?, ip.updated_at = NOW()
                    WHERE ct.salon_id = ? AND ip.item_id = ?";

            $this->db($this->db_index)->query($sql, [$sell_price, $salon_id, $item_id]);

            $this->json([
                'success' => true,
                'message' => 'Harga jual berhasil diupdate'
            ]);

        } catch (\Exception $e) {
            error_log("CashManagement updateSellPrice error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Inventory Items Available for Sale
     * /api/Beauty_Salon/CashManagement/inventoryForSale
     */
    public function inventoryForSale()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            if (!$salon_id) $this->error('Session error', 401);

            // Get inventory items with sell_price set, grouped by item_id
            $sql = "SELECT 
                        ip.item_id,
                        ip.item_name,
                        ip.buy_price,
                        ip.sell_price,
                        SUM(ip.qty) as total_purchased
                    FROM inventory_purchases ip
                    JOIN cash_transactions ct ON ip.transaction_id = ct.id
                    WHERE ct.salon_id = ? AND ip.sell_price IS NOT NULL AND ip.sell_price > 0
                    GROUP BY ip.item_id, ip.item_name, ip.buy_price, ip.sell_price
                    HAVING total_purchased > 0
                    ORDER BY ip.item_name ASC";

            $items = $this->db($this->db_index)
                ->query($sql, [$salon_id])
                ->result_array();

            // Calculate stock = purchased - sold
            $result = [];
            foreach ($items as $item) {
                // Get sold quantity for this item_id from inventory_sales table
                $soldResult = $this->db($this->db_index)
                    ->query("SELECT COALESCE(SUM(qty), 0) as total_sold 
                            FROM inventory_sales 
                            WHERE salon_id = ? AND item_id = ?", [$salon_id, $item['item_id']])
                    ->row_array();
                
                $sold = (int)($soldResult['total_sold'] ?? 0);
                $stock = (int)$item['total_purchased'] - $sold;
                
                if ($stock > 0) {
                    $item['stock'] = $stock;
                    $item['total_sold'] = $sold;
                    $result[] = $item;
                }
            }

            $this->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            error_log("CashManagement inventoryForSale error: " . $e->getMessage());
            $this->error('Gagal memuat data', 500);
        }
    }

    /**
     * POST - Delete transaction
     * /api/Beauty_Salon/CashManagement/deleteTransaction/{id}
     */
    public function deleteTransaction($id)
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            // Only admin can delete transactions
            if (!$this->isAdmin()) {
                $this->error('Akses ditolak. Hanya admin yang bisa menghapus transaksi', 403);
            }

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Get transaction
            $transaction = $this->db($this->db_index)
                ->get_where('cash_transactions', ['id' => $id, 'salon_id' => $salon_id], 1)
                ->row_array();

            if (!$transaction) {
                $this->error('Transaksi tidak ditemukan', 404);
            }

            // Cannot delete income from orders
            if ($transaction['reference_type'] === 'order') {
                $this->error('Tidak bisa menghapus transaksi dari order. Batalkan order terlebih dahulu', 400);
            }

            // Delete transaction
            $this->db($this->db_index)->delete('cash_transactions', ['id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Transaksi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            error_log("CashManagement deleteTransaction error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Profit/Loss Report
     * /api/Beauty_Salon/CashManagement/profitLossReport?date_from=&date_to=
     */
    public function profitLossReport()
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            if (!$salon_id) $this->error('Session error', 401);

            // Only admin can access
            if (!$this->isAdmin()) {
                $this->error('Akses ditolak. Hanya admin yang bisa melihat laporan ini', 403);
            }

            // Get date range
            $date_from = $_GET['date_from'] ?? date('Y-m-01'); // Default: first day of month
            $date_to = $_GET['date_to'] ?? date('Y-m-d'); // Default: today

            // Validate max 31 days
            $from = new \DateTime($date_from);
            $to = new \DateTime($date_to);
            $diff = $from->diff($to)->days;
            
            if ($diff > 31) {
                $this->error('Rentang tanggal maksimal 31 hari', 400);
            }

            $getSumValue = function($result) {
                if (!$result || !is_array($result)) return 0;
                $value = $result['total'] ?? null;
                return ($value !== null && $value !== '') ? (float)$value : 0;
            };

            // ========== PENDAPATAN (REVENUE) ==========
            
            // 1. Service Revenue (from completed orders - services only)
            $serviceRevenue = $this->db($this->db_index)
                ->query("SELECT COALESCE(SUM(total_price), 0) as total 
                        FROM orders 
                        WHERE salon_id = ? 
                          AND status = 'completed'
                          AND DATE(order_date) >= ? 
                          AND DATE(order_date) <= ?", 
                    [$salon_id, $date_from, $date_to])
                ->row_array();

            // 2. Inventory Sales Revenue (sell_price from inventory_sales)
            $inventorySalesRevenue = $this->db($this->db_index)
                ->query("SELECT COALESCE(SUM(total_price), 0) as total 
                        FROM inventory_sales 
                        WHERE salon_id = ? 
                          AND DATE(created_at) >= ? 
                          AND DATE(created_at) <= ?", 
                    [$salon_id, $date_from, $date_to])
                ->row_array();

            // 3. Other Income (from cash_transactions with type = income)
            $otherIncome = $this->db($this->db_index)
                ->query("SELECT COALESCE(SUM(amount), 0) as total 
                        FROM cash_transactions 
                        WHERE salon_id = ? 
                          AND transaction_type = 'income'
                          AND DATE(transaction_date) >= ? 
                          AND DATE(transaction_date) <= ?", 
                    [$salon_id, $date_from, $date_to])
                ->row_array();

            // ========== HPP (Cost of Goods Sold) ==========
            
            // Cost of inventory sold (buy_price from inventory_sales)
            $inventoryCOGS = $this->db($this->db_index)
                ->query("SELECT COALESCE(SUM(buy_price * qty), 0) as total 
                        FROM inventory_sales 
                        WHERE salon_id = ? 
                          AND DATE(created_at) >= ? 
                          AND DATE(created_at) <= ?", 
                    [$salon_id, $date_from, $date_to])
                ->row_array();

            // ========== BIAYA OPERASIONAL (Operating Expenses) ==========
            
            // Get all expenses by category
            $expensesByCategory = $this->db($this->db_index)
                ->query("SELECT 
                            ec.name as category_name,
                            COALESCE(SUM(ct.amount), 0) as total
                        FROM cash_transactions ct
                        LEFT JOIN expense_categories ec ON ct.category_id = ec.id
                        WHERE ct.salon_id = ? 
                          AND ct.transaction_type = 'expense'
                          AND DATE(ct.transaction_date) >= ? 
                          AND DATE(ct.transaction_date) <= ?
                        GROUP BY ec.id, ec.name
                        ORDER BY total DESC", 
                    [$salon_id, $date_from, $date_to])
                ->result_array();

            // Total Operating Expenses (excluding inventory purchases - those are COGS)
            $totalExpenses = $this->db($this->db_index)
                ->query("SELECT COALESCE(SUM(ct.amount), 0) as total 
                        FROM cash_transactions ct
                        LEFT JOIN expense_categories ec ON ct.category_id = ec.id
                        WHERE ct.salon_id = ? 
                          AND ct.transaction_type = 'expense'
                          AND DATE(ct.transaction_date) >= ? 
                          AND DATE(ct.transaction_date) <= ?
                          AND (ec.name IS NULL OR LOWER(ec.name) NOT LIKE '%persediaan%')", 
                    [$salon_id, $date_from, $date_to])
                ->row_array();

            // ========== KALKULASI ==========
            
            $totalOrderRevenue = $getSumValue($serviceRevenue); // Total dari orders (services + inventory)
            $totalInventoryRevenue = $getSumValue($inventorySalesRevenue); // Revenue dari penjualan barang
            $totalOtherIncome = $getSumValue($otherIncome);
            $totalCOGS = $getSumValue($inventoryCOGS); // HPP barang
            $totalOperatingExpenses = $getSumValue($totalExpenses);

            // Pendapatan Layanan Murni = Total Order - Pendapatan Barang
            $serviceOnlyRevenue = $totalOrderRevenue - $totalInventoryRevenue;
            
            // Laba Kotor Barang = Pendapatan Barang - HPP
            $inventoryGrossProfit = $totalInventoryRevenue - $totalCOGS;
            
            // Total Pendapatan = Layanan + Barang + Lain-lain
            $grossRevenue = $totalOrderRevenue + $totalOtherIncome;
            
            // Gross Profit = Revenue - COGS
            $grossProfit = $grossRevenue - $totalCOGS;
            
            // Net Profit = Gross Profit - Operating Expenses
            $netProfit = $grossProfit - $totalOperatingExpenses;

            // Order statistics
            $orderStats = $this->db($this->db_index)
                ->query("SELECT 
                            COUNT(*) as total_orders,
                            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders
                        FROM orders 
                        WHERE salon_id = ? 
                          AND DATE(order_date) >= ? 
                          AND DATE(order_date) <= ?", 
                    [$salon_id, $date_from, $date_to])
                ->row_array();

            // Inventory items sold count
            $inventorySoldCount = $this->db($this->db_index)
                ->query("SELECT COALESCE(SUM(qty), 0) as total 
                        FROM inventory_sales 
                        WHERE salon_id = ? 
                          AND DATE(created_at) >= ? 
                          AND DATE(created_at) <= ?", 
                    [$salon_id, $date_from, $date_to])
                ->row_array();

            $this->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'from' => $date_from,
                        'to' => $date_to,
                        'days' => $diff + 1
                    ],
                    'revenue' => [
                        'services' => $serviceOnlyRevenue,
                        'inventory' => $totalInventoryRevenue,
                        'other_income' => $totalOtherIncome,
                        'total' => $grossRevenue
                    ],
                    'cogs' => [
                        'inventory' => $totalCOGS,
                        'total' => $totalCOGS
                    ],
                    'inventory_profit' => $inventoryGrossProfit,
                    'gross_profit' => $grossProfit,
                    'expenses' => [
                        'by_category' => $expensesByCategory,
                        'total' => $totalOperatingExpenses
                    ],
                    'net_profit' => $netProfit,
                    'profit_margin' => $grossRevenue > 0 ? round(($netProfit / $grossRevenue) * 100, 2) : 0,
                    'statistics' => [
                        'total_orders' => (int)($orderStats['total_orders'] ?? 0),
                        'completed_orders' => (int)($orderStats['completed_orders'] ?? 0),
                        'items_sold' => (int)($inventorySoldCount['total'] ?? 0)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            error_log("CashManagement profitLossReport error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }
}
