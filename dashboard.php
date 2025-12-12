<?php
session_start();
require_once 'config/database.php';

// Cek sesi login
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard E-Cuti Disdikpora</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* Custom Scrollbar Mewah */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">

    <div class="flex h-screen overflow-hidden">
        
        <!-- MOBILE OVERLAY -->
        <div id="mobile-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

        <!-- SIDEBAR (Tema Midnight Royal) -->
        <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 w-72 bg-gradient-to-b from-[#0f172a] to-[#1e293b] text-white flex flex-col shadow-2xl z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out border-r border-slate-700/50">
            
            <!-- Header Sidebar -->
            <div class="p-6 flex items-center gap-4 border-b border-slate-700/50 bg-[#0f172a]">
                <!-- Logo Container Aksen Emas -->
                <div class="w-12 h-12 bg-gradient-to-br from-amber-400 to-yellow-600 rounded-xl flex items-center justify-center font-bold shadow-lg shadow-amber-500/20 text-white transform hover:scale-105 transition duration-300">
                    <i class="fa-solid fa-graduation-cap text-xl"></i>
                </div>
                <div>
                    <h1 class="font-bold text-xl tracking-wide leading-tight text-white">E-CUTI</h1>
                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Kota Denpasar</p>
                </div>
                <!-- Close Button Mobile -->
                <button onclick="toggleSidebar()" class="lg:hidden ml-auto text-slate-400 hover:text-white transition">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>

            <!-- Navigasi Menu -->
            <nav class="flex-1 py-6 px-4 space-y-2 overflow-y-auto">
                
                <!-- Label Group -->
                <p class="text-[10px] font-bold text-slate-500 uppercase px-4 mb-2 tracking-widest">Menu Utama</p>
                
                <!-- Menu Dashboard (Semua Role) -->
                <a href="?page=home" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='home' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-chart-pie w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='home' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Dashboard</span>
                    <?php if($page=='home'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>
                
                <!-- DATA PEGAWAI (POSISI KHUSUS ADMIN SEKOLAH: Di Bawah Dashboard) -->
                <?php if($role == 'admin_sekolah'): ?>
                <a href="?page=pegawai" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='pegawai' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-users w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='pegawai' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Data Pegawai</span>
                    <?php if($page=='pegawai'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>
                <?php endif; ?>

                <!-- SECTION ADMIN SEKOLAH -->
                <?php if($role == 'admin_sekolah'): ?>
                <div class="mt-6 mb-2 px-4 border-t border-slate-700/50 pt-4">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Administrasi</p>
                </div>
                <a href="?page=pengajuan" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='pengajuan' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-paper-plane w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='pengajuan' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Ajukan Cuti</span>
                    <?php if($page=='pengajuan'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>
                <?php endif; ?>

                <!-- SECTION ADMIN DINAS -->
                <?php if($role == 'admin_dinas'): ?>
                <div class="mt-6 mb-2 px-4 border-t border-slate-700/50 pt-4">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Verifikasi & Data</p>
                </div>
                
                <!-- Data Sekolah -->
                <a href="?page=data_sekolah" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='data_sekolah' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-school w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='data_sekolah' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Data Sekolah</span>
                    <?php if($page=='data_sekolah'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>

                <!-- DATA PEGAWAI (POSISI KHUSUS ADMIN DINAS: Di Bawah Data Sekolah) -->
                <a href="?page=pegawai" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='pegawai' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-users w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='pegawai' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Data Pegawai</span>
                    <?php if($page=='pegawai'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>

                <!-- Persetujuan -->
                <a href="?page=persetujuan" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='persetujuan' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-check-double w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='persetujuan' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm flex-1">Persetujuan</span>
                    <?php 
                    $cek = $conn->query("SELECT COUNT(*) FROM pengajuan_cuti WHERE status='pending'")->fetchColumn();
                    if($cek > 0) echo "<span class='bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-lg shadow-red-500/50 animate-pulse'>$cek</span>";
                    ?>
                </a>
                
                <!-- Laporan -->
                <a href="?page=laporan" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='laporan' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-file-pdf w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='laporan' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Laporan Rekap</span>
                    <?php if($page=='laporan'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>

                <!-- SECTION PENGATURAN (ADMIN DINAS) -->
                <div class="mt-6 mb-2 px-4 border-t border-slate-700/50 pt-4">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Pengaturan</p>
                </div>
                
                <!-- Pengaturan Cuti -->
                <a href="?page=pengaturan_cuti" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='pengaturan_cuti' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-calendar-check w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='pengaturan_cuti' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Pengaturan Cuti</span>
                    <?php if($page=='pengaturan_cuti'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>

                <!-- Manajemen User -->
                <a href="?page=users" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='users' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-user-shield w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='users' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Manajemen User</span>
                    <?php if($page=='users'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>
                <?php endif; ?>

                <!-- SECTION AKUN (ADMIN SEKOLAH) -->
                <?php if($role != 'admin_dinas'): ?>
                <div class="mt-6 mb-2 px-4 border-t border-slate-700/50 pt-4">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Akun Saya</p>
                </div>
                <?php endif; ?>
                
                <!-- Profil Saya (Semua Role) -->
                <a href="?page=profil" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='profil' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-id-card w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='profil' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Profil Saya</span>
                    <?php if($page=='profil'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>

            </nav>

            <!-- Profil User Bottom -->
            <div class="p-4 border-t border-slate-700/50 bg-[#0f172a]">
                <div class="flex items-center gap-3 mb-4 px-2">
                    <div class="w-10 h-10 rounded-full bg-slate-800 border border-slate-600 flex items-center justify-center text-slate-300 overflow-hidden shadow-inner">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-sm font-semibold text-white truncate w-32"><?= $_SESSION['username'] ?></p>
                        <p class="text-[10px] text-slate-400 truncate"><?= $role == 'admin_sekolah' ? 'Operator Sekolah' : 'Administrator Dinas' ?></p>
                    </div>
                </div>
                <a href="logout.php" onclick="return confirm('Yakin ingin keluar dari sistem?')" class="flex items-center justify-center gap-2 w-full bg-red-600 hover:bg-red-500 text-white py-2.5 rounded-lg text-sm font-medium transition shadow-lg shadow-red-900/20 border border-red-500/20 group">
                    <i class="fa-solid fa-right-from-bracket group-hover:scale-110 transition"></i> Logout
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 relative">
            
            <!-- Header -->
            <header class="bg-white/80 backdrop-blur-md border-b border-slate-200 h-20 flex items-center justify-between px-4 lg:px-8 shadow-sm z-30 sticky top-0">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="lg:hidden text-slate-500 hover:text-blue-600 transition p-2 rounded-lg hover:bg-slate-100">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>

                    <div>
                        <h2 class="text-xl font-bold text-slate-800 capitalize tracking-tight line-clamp-1">
                            <?= 
                                $page == 'data_sekolah' ? 'Data Sekolah & Unit Kerja' : 
                                ($page == 'pengaturan_cuti' ? 'Pengaturan Cuti Bersama' : 
                                ($page == 'users' ? 'Manajemen Pengguna' : 
                                ($page == 'profil' ? 'Profil Saya' : 
                                ($page == 'home' ? 'Dashboard Overview' : str_replace('_', ' ', $page))))) 
                            ?>
                        </h2>
                        <p class="text-xs text-slate-500 mt-1 hidden sm:block">Sistem Informasi Manajemen Cuti Terpadu</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-6">
                    <div class="hidden md:block text-right">
                        <p class="text-sm font-bold text-slate-700"><?= tgl_indo(date('Y-m-d')) ?></p>
                        <p class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Denpasar, Bali</p>
                    </div>
                    <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 border border-slate-200 shadow-sm hover:bg-slate-50 transition cursor-pointer">
                        <i class="fa-regular fa-bell"></i>
                    </div>
                </div>
            </header>

            <!-- Dynamic Content -->
            <div class="flex-1 overflow-auto p-4 lg:p-8 scroll-smooth">
                <div class="max-w-7xl mx-auto pb-10">
                    <?php
                    $filename = 'views/'.$page.'.php';
                    if(file_exists($filename)){
                        include $filename;
                    } else {
                        echo "
                        <div class='flex flex-col items-center justify-center py-24 text-slate-400'>
                            <div class='w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mb-4 text-3xl'>
                                <i class='fa-regular fa-file-circle-question text-slate-300'></i>
                            </div>
                            <h3 class='text-xl font-bold text-slate-600'>Halaman Tidak Ditemukan</h3>
                            <p class='text-sm mt-1'>File view tidak tersedia di server.</p>
                        </div>";
                    }
                    ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }
    </script>
</body>
</html>