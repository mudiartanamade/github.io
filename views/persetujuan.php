<?php
// Proteksi: Hanya Admin Dinas
if($_SESSION['role'] != 'admin_dinas') { echo "<div class='p-4 bg-red-100 text-red-600 rounded-lg'>Akses Ditolak: Halaman ini hanya untuk Admin Dinas.</div>"; exit; }

// LOGIKA APPROVE / REJECT
if(isset($_GET['aksi']) && isset($_GET['id'])){
    $id = $_GET['id'];
    $status = $_GET['aksi'];
    
    // 1. Update Status Pengajuan
    $upd = $conn->prepare("UPDATE pengajuan_cuti SET status=?, tgl_validasi=NOW() WHERE id=?");
    $upd->execute([$status, $id]);
    
    // 2. Jika Disetujui, Kurangi Kuota Pegawai
    if($status == 'disetujui'){
        // Ambil info durasi dan ID pegawai
        $get = $conn->prepare("SELECT pegawai_id, durasi FROM pengajuan_cuti WHERE id=?");
        $get->execute([$id]);
        $data = $get->fetch();
        
        // Update cuti_terpakai di tabel pegawai
        $potong = $conn->prepare("UPDATE pegawai SET cuti_terpakai = cuti_terpakai + ? WHERE id=?");
        $potong->execute([$data['durasi'], $data['pegawai_id']]);
    }
    
    // Redirect agar URL bersih kembali
    echo "<script>window.location='?page=persetujuan';</script>";
}
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-gradient-to-r from-slate-50 to-white">
        <div>
            <h3 class="font-bold text-slate-700 text-lg">Persetujuan Cuti Masuk</h3>
            <p class="text-xs text-slate-400">Verifikasi pengajuan dari sekolah</p>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-slate-600">
            <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-4">Sekolah / Jenjang</th>
                    <th class="px-6 py-4">Pegawai</th>
                    <th class="px-6 py-4">Detail Cuti</th>
                    <th class="px-6 py-4">Alasan</th>
                    <th class="px-6 py-4 text-center">Validasi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                // Hanya ambil data yang statusnya 'pending'
                $query = "SELECT pc.*, p.nama, p.sekolah, p.jenjang, p.kuota_cuti, p.cuti_terpakai 
                          FROM pengajuan_cuti pc 
                          JOIN pegawai p ON pc.pegawai_id = p.id 
                          WHERE pc.status = 'pending' 
                          ORDER BY pc.tgl_pengajuan ASC";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $rows = $stmt->fetchAll();
                
                // Jika tidak ada data
                if(count($rows) == 0):
                ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center justify-center text-slate-300">
                            <i class="fa-regular fa-folder-open text-4xl mb-3"></i>
                            <p class="text-sm">Tidak ada pengajuan cuti yang menunggu persetujuan.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach($rows as $r): ?>
                <tr class="hover:bg-blue-50/30 transition">
                    <!-- Sekolah -->
                    <td class="px-6 py-4 align-top">
                        <div class="font-bold text-slate-700"><?= $r['sekolah'] ?></div>
                        <span class="text-[10px] bg-slate-100 px-2 py-0.5 rounded text-slate-600 font-bold tracking-wide uppercase mt-1 inline-block border border-slate-200"><?= $r['jenjang'] ?></span>
                    </td>
                    <!-- Pegawai -->
                    <td class="px-6 py-4 align-top">
                        <div class="font-medium text-slate-900"><?= $r['nama'] ?></div>
                        <div class="text-xs text-slate-500 mt-1">
                            Sisa Cuti: <span class="font-bold text-slate-700"><?= $r['kuota_cuti'] - $r['cuti_terpakai'] ?> Hari</span>
                        </div>
                    </td>
                    <!-- Detail -->
                    <td class="px-6 py-4 align-top">
                        <div class="text-blue-600 font-bold text-xs uppercase tracking-wide mb-1"><?= $r['jenis_cuti'] ?></div>
                        <div class="text-sm text-slate-700"><?= tgl_indo($r['tgl_mulai']) ?> <span class="text-slate-400 mx-1">s/d</span> <?= tgl_indo($r['tgl_selesai']) ?></div>
                        <div class="text-xs font-bold mt-1 bg-blue-50 text-blue-600 inline-block px-2 py-0.5 rounded"><?= $r['durasi'] ?> Hari Kerja</div>
                    </td>
                    <!-- Alasan -->
                    <td class="px-6 py-4 align-top italic text-slate-500 bg-slate-50/50 rounded-lg">
                        "<?= $r['alasan'] ?>"
                    </td>
                    <!-- Tombol Aksi -->
                    <td class="px-6 py-4 align-middle text-center">
                        <div class="flex items-center justify-center gap-2">
                            <!-- Tombol Tolak -->
                            <a href="?page=persetujuan&aksi=ditolak&id=<?= $r['id'] ?>" onclick="return confirm('Yakin tolak pengajuan ini?')" class="w-10 h-10 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-600 border border-red-100 transition" title="Tolak">
                                <i class="fa-solid fa-times"></i>
                            </a>
                            <!-- Tombol Setuju -->
                            <a href="?page=persetujuan&aksi=disetujui&id=<?= $r['id'] ?>" onclick="return confirm('Setujui pengajuan ini?')" class="h-10 px-4 flex items-center justify-center gap-2 rounded-lg bg-green-600 text-white hover:bg-green-700 shadow-lg shadow-green-500/30 transition text-sm font-bold">
                                <i class="fa-solid fa-check"></i> Setujui
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>