<?php

/**
 * Admin Users Controller
 * Handles all user management for Admin application
 * 
 * Endpoints:
 * - GET  /Admin/Users/list     - Get all users
 * - POST /Admin/Users/login    - User login
 * - POST /Admin/Users/add      - Add new user
 * - POST /Admin/Users/edit     - Edit user
 * - POST /Admin/Users/delete   - Delete user
 */
class Users extends Controller
{
    private $table = 'admin_users';
    private $dbIndex = 2000; // Database index from DBC config

    public function index()
    {
        $this->handleCors();
        $this->success(null, 'Admin Users API');
    }

    /**
     * Login user
     * POST /Admin/Users/login
     */
    public function login()
    {
        $this->handleCors();
        
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['username', 'password']);

        $username = $body['username'];
        $password = $body['password'];

        $user = $this->db($this->dbIndex)
            ->get_where($this->table, ['username' => $username])
            ->row();

        if (!$user) {
            $this->error('User not found', 404);
        }

        if (!password_verify($password, $user->password)) {
            $this->error('Invalid password', 401);
        }

        // Generate token (simple)
        $token = bin2hex(random_bytes(32));
        
        // Update last login
        $this->db($this->dbIndex)->update($this->table, [
            'last_login' => $GLOBALS['now'],
            'token' => $token
        ], ['id' => $user->id]);

        $this->success([
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role,
            'token' => $token
        ], 'Login successful');
    }

    /**
     * Get all users
     * GET /Admin/Users/list
     */
    public function list()
    {
        $this->handleCors();

        $users = $this->db($this->dbIndex)
            ->query("SELECT id, username, name, role, email, last_login, created_at FROM {$this->table} ORDER BY id DESC")
            ->result();

        $this->success($users);
    }

    /**
     * Add new user
     * POST /Admin/Users/add
     */
    public function add()
    {
        $this->handleCors();
        
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['username', 'password', 'name']);

        // Check if username exists
        $exists = $this->db($this->dbIndex)
            ->get_where($this->table, ['username' => $body['username']])
            ->row();

        if ($exists) {
            $this->error('Username already exists', 400);
        }

        $insertData = [
            'username' => $body['username'],
            'password' => password_hash($body['password'], PASSWORD_DEFAULT),
            'name' => $body['name'],
            'email' => $body['email'] ?? '',
            'role' => $body['role'] ?? 'user',
            'created_at' => $GLOBALS['now']
        ];

        $id = $this->db($this->dbIndex)->insert($this->table, $insertData);

        if ($id) {
            $this->success(['id' => $id], 'User added successfully');
        } else {
            $this->error('Failed to add user', 500);
        }
    }

    /**
     * Edit user
     * POST /Admin/Users/edit
     */
    public function edit()
    {
        $this->handleCors();
        
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id']);

        $updateData = [];
        
        if (isset($body['name'])) $updateData['name'] = $body['name'];
        if (isset($body['email'])) $updateData['email'] = $body['email'];
        if (isset($body['role'])) $updateData['role'] = $body['role'];
        if (isset($body['password']) && !empty($body['password'])) {
            $updateData['password'] = password_hash($body['password'], PASSWORD_DEFAULT);
        }

        if (empty($updateData)) {
            $this->error('No data to update', 400);
        }

        $updateData['updated_at'] = $GLOBALS['now'];

        $result = $this->db($this->dbIndex)->update($this->table, $updateData, ['id' => $body['id']]);

        if ($result) {
            $this->success(null, 'User updated successfully');
        } else {
            $this->error('Failed to update user', 500);
        }
    }

    /**
     * Delete user
     * POST /Admin/Users/delete
     */
    public function delete()
    {
        $this->handleCors();
        
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['id']);

        $result = $this->db($this->dbIndex)->delete($this->table, ['id' => $body['id']]);

        if ($result) {
            $this->success(null, 'User deleted successfully');
        } else {
            $this->error('Failed to delete user', 500);
        }
    }

    /**
     * Check auth token
     * POST /Admin/Users/check
     */
    public function check()
    {
        $this->handleCors();
        
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $this->validate($body, ['token']);

        $user = $this->db($this->dbIndex)
            ->query("SELECT id, username, name, role FROM {$this->table} WHERE token = ?", [$body['token']])
            ->row();

        if ($user) {
            $this->success($user, 'Token valid');
        } else {
            $this->error('Invalid token', 401);
        }
    }
}
