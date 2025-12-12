<?php
session_start();
// Jika sudah login, langsung ke dashboard
if(isset($_SESSION['user_id'])) { 
    header("Location: dashboard.php"); 
    exit; 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Cuti Disdikpora Denpasar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-900 h-screen flex items-center justify-center relative overflow-hidden">
    
    <!-- Dekorasi Background -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-[20%] -left-[10%] w-[500px] h-[500px] bg-blue-600 rounded-full blur-[100px] opacity-20 animate-pulse"></div>
        <div class="absolute top-[60%] -right-[10%] w-[400px] h-[400px] bg-purple-600 rounded-full blur-[100px] opacity-20 animate-pulse"></div>
    </div>

    <div class="relative z-10 w-full max-w-md p-8 bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-white rounded-2xl mx-auto flex items-center justify-center shadow-lg mb-4 p-2">
                <img src="https://upload.wikimedia.org/wikipedia/commons/6/65/Lambang_Kota_Denpasar_%281%29.png" class="w-full h-full object-contain">
            </div>
            <h1 class="text-2xl font-bold text-white tracking-wide">E-CUTI DISDIKPORA</h1>
            <p class="text-slate-300 text-sm mt-1">Sistem Informasi Manajemen Cuti</p>
        </div>

        <?php if(isset($_GET['error'])): ?>
            <div class="bg-red-500/20 border border-red-500/50 text-red-100 text-sm p-4 rounded-xl mb-6 text-center backdrop-blur-md flex items-center justify-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i> Username atau Password salah!
            </div>
        <?php endif; ?>

        <form action="auth_handler.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-slate-300 text-xs font-bold uppercase mb-2 pl-1 tracking-wider">Username</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-slate-400"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="username" required placeholder="Masukkan username..." class="w-full bg-slate-800/50 border border-slate-600 rounded-xl pl-10 pr-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition">
                </div>
            </div>
            <div>
                <label class="block text-slate-300 text-xs font-bold uppercase mb-2 pl-1 tracking-wider">Password</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-slate-400"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-800/50 border border-slate-600 rounded-xl pl-10 pr-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition">
                </div>
            </div>
            <button type="submit" name="login" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-500/30 transition transform hover:scale-[1.02] mt-4">
                MASUK APLIKASI
            </button>
        </form>
        
        <p class="text-center text-slate-500 text-xs mt-8">&copy; 2025 Pemerintah Kota Denpasar</p>
    </div>
</body>
</html>