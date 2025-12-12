<?php
session_start();
require_once 'config/database.php';

// PROSES LOGIN
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Ambil data user dari database
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Verifikasi Password
    if ($user && password_verify($password, $user['password'])) {
        // Set Variabel Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // 'admin_sekolah' atau 'admin_dinas'
        $_SESSION['nama_sekolah'] = $user['nama_sekolah'];
        $_SESSION['jenjang'] = $user['jenjang'];
        
        // Redirect ke Dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        // Gagal Login
        header("Location: index.php?error=1");
        exit;
    }
}

// Jika file diakses langsung tanpa submit form
header("Location: index.php");
?>