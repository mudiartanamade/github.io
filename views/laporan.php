<?php 
// Proteksi
if($_SESSION['role'] != 'admin_dinas') exit; 
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
    <!-- Header Laporan -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h3 class="font-bold text-lg text-slate-800">Laporan Rekapitulasi Cuti</h3>
            <p class="text-xs text-slate-400">50 riwayat pengajuan terakhir</p>
        </div>
        <button onclick="window.print()" class="text-slate-500 hover:text-blue-600 transition flex items-center gap-2 bg-slate-100 px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fa-solid fa-print"></i> Print Laporan
        </button>
    </div>
    
    <!-- Tabel Laporan -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
            <thead class="bg-slate-50 text-slate-500 font-bold border-b">
                <tr>
                    <th class="p-3">Nama Pegawai</th>
                    <th class="p-3">Sekolah</th>
                    <th class="p-3">Jenis Cuti</th>
                    <th class="p-3">Tanggal</th>
                    <th class="p-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Query Join untuk mengambil detail pegawai dan pengajuannya
                $rek = $conn->query("SELECT pc.*, p.nama, p.sekolah FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id ORDER BY pc.id DESC LIMIT 50");
                
                while($r = $rek->fetch()):
                    // Badge Warna berdasarkan Status
                    $badgeClass = '';
                    if($r['status'] == 'disetujui') $badgeClass = 'bg-green-100 text-green-700 border-green-200';
                    elseif($r['status'] == 'pending') $badgeClass = 'bg-yellow-100 text-yellow-700 border-yellow-200';
                    else $badgeClass = 'bg-red-100 text-red-700 border-red-200';
                ?>
                <tr class="border-b hover:bg-slate-50">
                    <td class="p-3 font-medium"><?= $r['nama'] ?></td>
                    <td class="p-3 text-xs text-slate-500"><?= $r['sekolah'] ?></td>
                    <td class="p-3"><?= $r['jenis_cuti'] ?></td>
                    <td class="p-3 text-xs text-slate-600">
                        <?= tgl_indo($r['tgl_mulai']) ?>
                    </td>
                    <td class="p-3 text-center">
                        <span class="px-2 py-1 rounded text-[10px] font-bold uppercase border <?= $badgeClass ?>">
                            <?= $r['status'] ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>