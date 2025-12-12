<?php
// QUERY STATISTIK
// Logika: Admin Dinas melihat data global, Admin Sekolah melihat data sekolahnya saja
if($_SESSION['role'] == 'admin_dinas'){
    // Data Global (Dinas)
    $total_pegawai = $conn->query("SELECT COUNT(*) FROM pegawai")->fetchColumn();
    $sedang_cuti = $conn->query("SELECT COUNT(*) FROM pengajuan_cuti WHERE status='disetujui' AND CURDATE() BETWEEN tgl_mulai AND tgl_selesai")->fetchColumn();
    $menunggu = $conn->query("SELECT COUNT(*) FROM pengajuan_cuti WHERE status='pending'")->fetchColumn();
} else {
    // Data Spesifik (Sekolah)
    $sekolah = $_SESSION['nama_sekolah'];
    
    // Hitung Pegawai Sekolah Ini
    $stmt = $conn->prepare("SELECT COUNT(*) FROM pegawai WHERE sekolah = ?");
    $stmt->execute([$sekolah]);
    $total_pegawai = $stmt->fetchColumn();

    // Hitung Pegawai Sekolah Ini yang Sedang Cuti
    $stmt2 = $conn->prepare("SELECT COUNT(*) FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id WHERE p.sekolah = ? AND pc.status='disetujui' AND CURDATE() BETWEEN pc.tgl_mulai AND pc.tgl_selesai");
    $stmt2->execute([$sekolah]);
    $sedang_cuti = $stmt2->fetchColumn();

    $menunggu = 0; // Admin sekolah tidak perlu melihat angka antrian global
}
?>

<!-- Kartu Statistik -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    
    <!-- Card 1: Total Pegawai -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-5 hover:shadow-md transition cursor-pointer group">
        <div class="w-16 h-16 rounded-2xl bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition flex items-center justify-center text-2xl shadow-sm">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Total Pegawai</p>
            <h3 class="text-3xl font-extrabold text-slate-800"><?= $total_pegawai ?></h3>
            <p class="text-xs text-slate-400 mt-1">Terdaftar di database</p>
        </div>
    </div>
    
    <!-- Card 2: Menunggu Persetujuan -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-5 hover:shadow-md transition cursor-pointer group">
        <div class="w-16 h-16 rounded-2xl bg-yellow-50 text-yellow-600 group-hover:bg-yellow-500 group-hover:text-white transition flex items-center justify-center text-2xl shadow-sm">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Menunggu Persetujuan</p>
            <h3 class="text-3xl font-extrabold text-slate-800"><?= $menunggu ?></h3>
            <p class="text-xs text-slate-400 mt-1">
                <?= $_SESSION['role'] == 'admin_dinas' ? 'Perlu verifikasi segera' : 'Menunggu Admin Dinas' ?>
            </p>
        </div>
    </div>

    <!-- Card 3: Sedang Cuti -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-5 hover:shadow-md transition cursor-pointer group">
        <div class="w-16 h-16 rounded-2xl bg-green-50 text-green-600 group-hover:bg-green-600 group-hover:text-white transition flex items-center justify-center text-2xl shadow-sm">
            <i class="fa-solid fa-plane-departure"></i>
        </div>
        <div>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Sedang Cuti</p>
            <h3 class="text-3xl font-extrabold text-slate-800"><?= $sedang_cuti ?></h3>
            <p class="text-xs text-slate-400 mt-1">Tidak aktif hari ini</p>
        </div>
    </div>
</div>

<!-- Grafik Statistik -->
<div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="font-bold text-slate-800 text-lg">Statistik Pengajuan Cuti</h3>
            <p class="text-xs text-slate-400">Gambaran umum aktivitas cuti tahun ini</p>
        </div>
        <span class="text-xs bg-slate-100 text-slate-600 px-3 py-1 rounded-full font-bold">Tahun <?= date('Y') ?></span>
    </div>
    <div class="h-80 w-full">
        <canvas id="chartCuti"></canvas>
    </div>
</div>

<script>
// Inisialisasi Chart.js
const ctx = document.getElementById('chartCuti').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        datasets: [{
            label: 'Jumlah Pegawai Cuti',
            data: [2, 5, 3, 5, 2, 3, 10, 5, 2, 1, 4, 8], // Data Dummy (bisa diganti query PHP real)
            backgroundColor: '#2563eb',
            hoverBackgroundColor: '#1d4ed8',
            borderRadius: 6,
            barThickness: 30
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { 
            y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#f1f5f9' } }, 
            x: { grid: { display: false } } 
        }
    }
});
</script>