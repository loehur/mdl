<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

class CashManagement extends Controller
{
    private $db_index = 5;

    /**
     * Helper: Check if user is admin
     */
    private function isAdmin()
    {
        $role = $_SESSION['salon_user_session']['user']['role'] ?? null;
        return $role === 'admin';
    }

    /**
     * GET - Get cash balance (cashier or main)
     * /api/Beauty_Salon/CashManagement/balance/{type}
     */
    public function balance($type = 'cashier')
    {
        try {
            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan', 401);
            }

            // Validate type
            if (!in_array($type, ['cashier', 'main'])) {
                $this->error('Tipe kas tidak valid (cashier atau main)', 400);
            }

            // For main cash, only admin can access
            if ($type === 'main' && !$this->isAdmin()) {
                $this->error('Akses ditolak. Hanya admin yang bisa melihat Kas Besar', 403);
            }

            // Get balance from view
            $view_name = $type === 'cashier' ? 'v_cashier_balance' : 'v_main_cash_balance';
            
            $balance = $this->db($this->db_index)
                ->query("SELECT * FROM {$view_name} WHERE salon_id = ?", [$salon_id])
                ->row_array();

            if (!$balance) {
                // Return zero balance if no data
                $balance = [
                    'balance' => 0,
                    'total_income' => 0,
                    'total_expense' => 0,
                    'total_transfer_in' => 0,
                    'total_transfer_out' => 0
                ];
            }

            $this->json([
                'success' => true,
                'data' => $balance
            ]);
        } catch (\Exception $e) {
            error_log("CashManagement balance error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Get expense categories
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
            error_log("CashManagement categories error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Get cash transactions with filters
     * /api/Beauty_Salon/CashManagement/transactions
     */
    public function transactions()
    {
        try {
        $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
        
        // Get filters from query string
        $type = $_GET['type'] ?? null;
        $cash = $_GET['cash'] ?? null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

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
                ct.created_at
                FROM cash_transactions ct
                LEFT JOIN expense_categories ec ON ct.category_id = ec.id
                WHERE 1=1";
        
        $params = [];

        // Apply salon_id filter if available
        if ($salon_id) {
            $sql .= " AND ct.salon_id = ?";
            $params[] = $salon_id;
        }

        // Apply filters
        if ($type) {
            $sql .= " AND ct.transaction_type = ?";
            $params[] = $type;
        }

        if ($cash) {
            $sql .= " AND ct.cash_source = ?";
            $params[] = $cash;
        }

        $sql .= " ORDER BY ct.transaction_date DESC, ct.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        // Debug logging
        error_log("CashManagement transactions SQL: " . $sql);
        error_log("CashManagement transactions params: " . json_encode($params));

        $transactions = $this->db($this->db_index)
            ->query($sql, $params)
            ->result_array();

        error_log("CashManagement transactions count: " . count($transactions));

        $this->json([
            'success' => true,
            'data' => $transactions
        ]);
    } catch (\Exception $e) {
        error_log("CashManagement transactions error: " . $e->getMessage());
        $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
    }    }

    /**
     * POST - Transfer between cash
     * /api/Beauty_Salon/CashManagement/transfer
     */
    public function transfer()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['from', 'to', 'amount', 'description']);

            // Only admin can transfer between all cash
            // Cashier can only transfer from cashier to main
            if (!$this->isAdmin()) {
                if ($body['from'] !== 'cashier' || $body['to'] !== 'main') {
                    $this->error('Akses ditolak. Anda hanya diperbolehkan transfer dari Kas Kasir ke Kas Besar', 403);
                }
            }

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $user_id = $_SESSION['salon_user_session']['user']['id'] ?? null;
            
            if (!$salon_id || !$user_id) {
                $this->error('Session tidak valid', 401);
            }

            // Validation
            if ($body['from'] === $body['to']) {
                $this->error('Tidak bisa transfer ke kas yang sama', 400);
            }

            if ($body['amount'] <= 0) {
                $this->error('Jumlah harus lebih dari 0', 400);
            }

            if (!in_array($body['from'], ['cashier', 'main']) || !in_array($body['to'], ['cashier', 'main'])) {
                $this->error('Sumber atau tujuan kas tidak valid', 400);
            }

            // Insert transfer transaction
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
                    'message' => 'Transfer berhasil',
                    'data' => ['transaction_id' => $id]
                ]);
            } else {
                $this->error('Gagal melakukan transfer', 500);
            }
        } catch (\Exception $e) {
            error_log("CashManagement transfer error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Add expense
     * /api/Beauty_Salon/CashManagement/expense
     */
    public function expense()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['cash_source', 'category_id', 'amount', 'description']);

            $salon_id = $_SESSION['salon_user_session']['user']['salon_id'] ?? null;
            $user_id = $_SESSION['salon_user_session']['user']['id'] ?? null;
            
            if (!$salon_id || !$user_id) {
                $this->error('Session tidak valid', 401);
            }

            // For main cash, only admin can add expense
            if ($body['cash_source'] === 'main' && !$this->isAdmin()) {
                $this->error('Akses ditolak. Hanya admin yang bisa input pengeluaran Kas Besar', 403);
            }

            // Validation
            if ($body['amount'] <= 0) {
                $this->error('Jumlah harus lebih dari 0', 400);
            }

            if (!in_array($body['cash_source'], ['cashier', 'main'])) {
                $this->error('Sumber kas tidak valid', 400);
            }

            // Verify category exists
            $category = $this->db($this->db_index)
                ->get_where('expense_categories', ['id' => $body['category_id'], 'is_active' => 1], 1)
                ->row_array();

            if (!$category) {
                $this->error('Kategori tidak ditemukan atau tidak aktif', 400);
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
}
