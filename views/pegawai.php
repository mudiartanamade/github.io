<?php
// --- CONFIGURASI TAHUN & SETTING ---
// Ambil pengaturan tahun dari database
$q_app = $conn->query("SELECT * FROM app_settings");
$app_config = [];
while($r = $q_app->fetch()) { $app_config[$r['setting_key']] = $r['setting_value']; }

// Default: Tahun Aplikasi 2026, Saldo Awal dari 2025
$tahun_ini = $app_config['tahun_aplikasi'] ?? '2026'; 
$tahun_basis = $app_config['tahun_saldo_awal'] ?? '2025';

$isAdminSekolah = ($_SESSION['role'] == 'admin_sekolah');

// --- HELPER: AMBIL DATA CUTI BERSAMA (CACHE ARRAY) ---
$list_cb = [];
$q_cb = $conn->query("SELECT tahun, jml_cuti_bersama FROM pengaturan_cuti");
while($row = $q_cb->fetch()) {
    $list_cb[$row['tahun']] = $row['jml_cuti_bersama'];
}

// --- LOGIKA BACKEND (CRUD) ---

// 1. TAMBAH PEGAWAI (Dengan Logika Restore/Anti-Reset)
if(isset($_POST['tambah_pegawai'])) {
    if(!$isAdminSekolah) { echo "<script>alert('Akses Ditolak');</script>"; }
    else {
        try {
            $nip = $_POST['nip'];
            
            // Cek apakah NIP sudah ada (baik aktif maupun nonaktif)
            $cek = $conn->prepare("SELECT id, status_aktif FROM pegawai WHERE nip = ?");
            $cek->execute([$nip]);
            $existing = $cek->fetch();

            if ($existing) {
                if ($existing['status_aktif'] == 'aktif') {
                    // Jika sudah ada dan aktif -> Tolak
                    echo "<script>alert('Gagal! NIP sudah terdaftar dan status Aktif.'); window.location='?page=pegawai';</script>";
                } else {
                    // Jika ada tapi NONAKTIF (Pernah dihapus) -> RESTORE DATA LAMA
                    // Kita update biodata barunya, tapi BIARKAN data cuti (sisa_cuti_thn_lalu & cuti_terpakai) tetap seperti semula
                    // Ini mencegah admin mereset kuota dengan cara hapus-tambah
                    $sql = "UPDATE pegawai SET 
                            nama = ?, pangkat = ?, jabatan = ?, tipe = ?, 
                            sekolah = ?, jenjang = ?, status_aktif = 'aktif' 
                            WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $_POST['nama'], $_POST['pangkat'], $_POST['jabatan'], $_POST['tipe'], 
                        $_SESSION['nama_sekolah'], $_SESSION['jenjang'], $existing['id']
                    ]);
                    
                    echo "<script>alert('Pegawai lama ditemukan (Arsip)! Data berhasil dipulihkan. Riwayat cuti dan kuota dikembalikan seperti semula (Anti-Reset).'); window.location='?page=pegawai';</script>";
                }
            } else {
                // Jika benar-benar baru -> INSERT NORMAL
                // Validasi Sisa Cuti (Max 6)
                $sisa_lalu = intval($_POST['sisa_cuti_thn_lalu']);
                if($sisa_lalu > 6) $sisa_lalu = 6;
                if($sisa_lalu < 0) $sisa_lalu = 0;

                $sql = "INSERT INTO pegawai (nip, nama, pangkat, jabatan, tipe, sekolah, jenjang, kuota_cuti, sisa_cuti_thn_lalu, cuti_terpakai, status_aktif) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 12, ?, 0, 'aktif')";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $nip, $_POST['nama'], $_POST['pangkat'], 
                    $_POST['jabatan'], $_POST['tipe'], $_SESSION['nama_sekolah'], $_SESSION['jenjang'], $sisa_lalu
                ]);
                echo "<script>alert('Berhasil menambah pegawai baru!'); window.location='?page=pegawai';</script>";
            }
        } catch (PDOException $e) { echo "<script>alert('Terjadi kesalahan sistem.');</script>"; }
    }
}

// 2. IMPORT CSV (Dengan Logika Cek Duplikat & Restore)
if(isset($_POST['import_pegawai'])) {
    if(!$isAdminSekolah) { echo "<script>alert('Akses Ditolak');</script>"; }
    else {
        $fileName = $_FILES['file_csv']['tmp_name'];
        if ($_FILES['file_csv']['size'] > 0) {
            $file = fopen($fileName, "r");
            fgetcsv($file); // Skip header
            $sukses = 0;
            $restored = 0;
            
            while (($col = fgetcsv($file, 10000, ",")) !== FALSE) {
                if(!empty($col[0])) {
                    $nip = $col[0];
                    // Cek duplikat
                    $cek = $conn->prepare("SELECT id, status_aktif FROM pegawai WHERE nip = ?");
                    $cek->execute([$nip]);
                    $ada = $cek->fetch();

                    if($ada) {
                        if($ada['status_aktif'] == 'nonaktif') {
                            // Restore otomatis jika inactive (Update Biodata saja, kuota tetap aman)
                            $conn->prepare("UPDATE pegawai SET status_aktif='aktif', nama=?, pangkat=?, jabatan=?, tipe=? WHERE id=?")
                                 ->execute([$col[1], $col[2], $col[3], $col[4], $ada['id']]);
                            $restored++;
                        }
                        // Jika aktif, skip (tidak duplikat)
                    } else {
                        // Insert Baru
                        try {
                            $sql = "INSERT INTO pegawai (nip, nama, pangkat, jabatan, tipe, sekolah, jenjang, kuota_cuti, sisa_cuti_thn_lalu, cuti_terpakai, status_aktif) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 12, 0, 0, 'aktif')";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$col[0], $col[1], $col[2], $col[3], $col[4], $_SESSION['nama_sekolah'], $_SESSION['jenjang']]);
                            $sukses++;
                        } catch(Exception $e) { continue; }
                    }
                }
            }
            echo "<script>alert('Import selesai! Baru: $sukses, Dipulihkan: $restored'); window.location='?page=pegawai';</script>";
        }
    }
}

// 3. EDIT PEGAWAI
if(isset($_POST['edit_pegawai'])) {
    if(!$isAdminSekolah) { echo "<script>alert('Akses Ditolak');</script>"; }
    else {
        try {
            $sql = "UPDATE pegawai SET nama=?, pangkat=?, jabatan=?, tipe=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $_POST['nama'], $_POST['pangkat'], $_POST['jabatan'], 
                $_POST['tipe'], $_POST['id_pegawai']
            ]);
            echo "<script>alert('Data pegawai diperbarui!'); window.location='?page=pegawai';</script>";
        } catch (PDOException $e) { echo "<script>alert('Gagal update.');</script>"; }
    }
}

// 4. HAPUS PEGAWAI (SOFT DELETE)
if(isset($_GET['hapus_id'])) {
    if(!$isAdminSekolah) { echo "<script>alert('Akses Ditolak');</script>"; }
    else {
        // Ubah status jadi nonaktif, JANGAN DELETE row datanya
        $del = $conn->prepare("UPDATE pegawai SET status_aktif = 'nonaktif' WHERE id = ?");
        $del->execute([$_GET['hapus_id']]);
        
        echo "<script>alert('Pegawai dinonaktifkan (Soft Delete). Data cuti tetap tersimpan aman di database.'); window.location='?page=pegawai';</script>";
    }
}
?>

<!-- --- TAMPILAN FRONTEND --- -->

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden relative z-0">
    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-white">
        <div>
            <h3 class="font-bold text-slate-800 text-lg">Direktori Pegawai</h3>
            <p class="text-slate-400 text-xs mt-0.5">
                Tahun Sistem: <span class="font-bold text-slate-700"><?= $tahun_ini ?></span> &bull; 
                Cuti Bersama: <span class="font-bold text-red-500"><?= $list_cb[$tahun_ini] ?? 0 ?> Hari</span>
            </p>
        </div>
        
        <?php if($isAdminSekolah): ?>
        <div class="flex gap-2">
            <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-blue-500/30 hover:bg-blue-700 transition flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Tambah
            </button>
            <button onclick="document.getElementById('modalImport').classList.remove('hidden')" class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/30 hover:bg-emerald-700 transition flex items-center gap-2">
                <i class="fa-solid fa-file-csv"></i> Import
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-slate-600">
            <thead class="text-xs text-slate-500 uppercase bg-slate-50/50 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-4 font-semibold">Nama / NIP</th>
                    <th class="px-6 py-4 font-semibold">Jabatan</th>
                    
                    <th class="px-6 py-4 font-semibold text-center bg-blue-50/30">Sisa <?= $tahun_basis ?></th>
                    <th class="px-6 py-4 font-semibold text-center bg-green-50/30">Hak <?= $tahun_ini ?></th>
                    <th class="px-6 py-4 font-semibold text-center">Terpakai</th>
                    <th class="px-6 py-4 font-semibold text-center font-bold text-slate-700">Total Sisa</th>
                    
                    <?php if($isAdminSekolah): ?><th class="px-6 py-4 text-center">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                // FILTER: Hanya tampilkan pegawai yang status_aktif = 'aktif'
                if($_SESSION['role'] == 'admin_dinas'){
                    $stmt = $conn->query("SELECT * FROM pegawai WHERE status_aktif = 'aktif' ORDER BY sekolah ASC");
                } else {
                    $sekolah = $_SESSION['nama_sekolah'];
                    $stmt = $conn->prepare("SELECT * FROM pegawai WHERE sekolah = ? AND status_aktif = 'aktif'");
                    $stmt->execute([$sekolah]);
                }
                
                while($p = $stmt->fetch()):
                    // =================================================================
                    // LOGIKA PERHITUNGAN CUTI OTOMATIS (WALKING BALANCE)
                    // =================================================================
                    
                    // 1. Ambil Saldo Awal Murni (Inputan 2025)
                    $saldo_berjalan = $p['sisa_cuti_thn_lalu']; 
                    $cuti_terpakai_tahun_ini = 0;
                    
                    // 2. Loop dari Tahun Basis+1 (2026) sampai Tahun Sistem
                    for ($y = ($tahun_basis + 1); $y <= $tahun_ini; $y++) {
                        
                        // A. Carry Over (Maksimal 6)
                        $carry_over = ($saldo_berjalan > 6) ? 6 : $saldo_berjalan;
                        
                        // B. Hak Cuti (12 - Cuti Bersama)
                        $hak_murni = 12 - ($list_cb[$y] ?? 0);
                        
                        // C. Total Hak
                        $total_hak = $carry_over + $hak_murni;
                        
                        // D. Ambil Penggunaan Cuti di tahun $y
                        $q_used = $conn->prepare("SELECT SUM(durasi) as total FROM pengajuan_cuti WHERE pegawai_id = ? AND YEAR(tgl_mulai) = ? AND status = 'disetujui' AND jenis_cuti = 'Cuti Tahunan'");
                        $q_used->execute([$p['id'], $y]);
                        $used = $q_used->fetch()['total'] ?? 0;
                        
                        // E. Sisa Akhir Tahun $y
                        $saldo_berjalan = $total_hak - $used;
                        
                        // Simpan nilai untuk display jika ini tahun berjalan
                        if ($y == $tahun_ini) {
                            $display_carry_over = $carry_over;
                            $display_hak_murni = $hak_murni;
                            $display_terpakai = $used;
                            $display_total_sisa = $saldo_berjalan;
                        }
                    }
                    
                    // Fallback jika tahun sistem == tahun basis (belum ada loop)
                    if ($tahun_ini == $tahun_basis) {
                        $display_carry_over = 0; 
                        $display_hak_murni = 12 - ($list_cb[$tahun_ini] ?? 0);
                        $display_terpakai = 0; // Bisa ambil query jika perlu
                        $display_total_sisa = $p['sisa_cuti_thn_lalu'] + $display_hak_murni;
                    }

                    $bg_sisa = $display_total_sisa < 4 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800"><?= $p['nama'] ?></div>
                        <div class="text-xs text-slate-500 font-mono mt-0.5"><?= $p['nip'] ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-medium text-slate-700"><?= $p['pangkat'] ?></div>
                        <div class="text-xs text-slate-500"><?= $p['tipe'] ?> - <?= $p['jabatan'] ?></div>
                    </td>
                    
                    <!-- Sisa Tahun Lalu (Calculated Carry Over) -->
                    <td class="px-6 py-4 text-center bg-blue-50/30 font-medium text-slate-600">
                        <?= $display_carry_over ?>
                        <span class="text-[9px] text-slate-400 block">(Max 6)</span>
                    </td>
                    
                    <!-- Hak Tahun Ini -->
                    <td class="px-6 py-4 text-center bg-green-50/30 font-medium text-slate-600">
                        <?= $display_hak_murni ?>
                    </td>
                    
                    <!-- Terpakai (Realtime DB) -->
                    <td class="px-6 py-4 text-center font-medium text-red-500">
                        - <?= $display_terpakai ?>
                    </td>
                    
                    <!-- Total Sisa -->
                    <td class="px-6 py-4 text-center">
                        <div class="inline-flex w-8 h-8 rounded-lg items-center justify-center font-bold text-sm <?= $bg_sisa ?>">
                            <?= $display_total_sisa ?>
                        </div>
                    </td>
                    
                    <?php if($isAdminSekolah): ?>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick='bukaEdit(<?= json_encode($p) ?>)' class="w-8 h-8 rounded-lg bg-yellow-50 text-yellow-600 hover:bg-yellow-100 transition flex items-center justify-center border border-yellow-200">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <!-- Tombol Hapus dengan Konfirmasi -->
                            <a href="?page=pegawai&hapus_id=<?= $p['id'] ?>" onclick="return confirm('Hapus pegawai ini? Data cuti akan tersimpan di arsip (Soft Delete). Jika Anda menambahkan kembali NIP ini, data lama akan dipulihkan.')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition flex items-center justify-center border border-red-200">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH PEGAWAI (Z-Index 9999) -->
<div id="modalTambah" class="fixed inset-0 bg-slate-900/60 z-[9999] hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Tambah Pegawai Baru</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">NIP</label>
                <input type="number" name="nip" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Lengkap</label>
                <input type="text" name="nama" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Tipe</label>
                    <select name="tipe" class="w-full border rounded-lg p-2.5 bg-white">
                        <option value="PNS">PNS</option>
                        <option value="P3K">P3K</option>
                        <option value="Kontrak">Kontrak</option>
                    </select>
                </div>
                
                <!-- INPUT SISA CUTI AWAL (VALIDASI MAX 6) -->
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Sisa Cuti <?= $tahun_basis ?> (Awal)</label>
                    <div class="relative">
                        <input type="number" name="sisa_cuti_thn_lalu" value="0" min="0" max="6" oninput="if(this.value > 6) this.value = 6;" required class="w-full border rounded-lg p-2.5 bg-yellow-50 focus:ring-yellow-500 focus:border-yellow-500 border-yellow-200">
                        <div class="absolute right-3 top-2.5 text-xs text-slate-400 font-bold">Max 6</div>
                    </div>
                    <p class="text-[9px] text-slate-400 mt-1">Input sisa tahun <?= $tahun_basis ?>. Otomatis dikalkulasi tahun berikutnya.</p>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Jabatan</label>
                <input type="text" name="jabatan" value="Guru Kelas" class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Pangkat / Golongan</label>
                <input type="text" name="pangkat" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="pt-4 flex justify-end gap-2 border-t mt-4">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200">Batal</button>
                <button type="submit" name="tambah_pegawai" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg transition font-medium">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL IMPORT CSV (Z-Index 9999) -->
<div id="modalImport" class="fixed inset-0 bg-slate-900/60 z-[9999] hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-[fadeIn_0.3s]">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Import Data Pegawai</h3>
            <button onclick="document.getElementById('modalImport').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-800 mb-4">
                <p class="font-bold mb-1"><i class="fa-solid fa-circle-info mr-1"></i> Format CSV:</p>
                <p>NIP, Nama, Pangkat, Jabatan, Tipe</p>
                <a href="template_pegawai.csv" download class="text-blue-600 underline font-bold mt-2 block hover:text-blue-800">Download Template CSV</a>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Upload File CSV</label>
                <input type="file" name="file_csv" accept=".csv" required class="w-full border border-slate-300 rounded-lg p-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition">
            </div>
            
            <div class="pt-4 flex justify-end gap-2 border-t mt-2">
                <button type="button" onclick="document.getElementById('modalImport').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition font-medium">Batal</button>
                <button type="submit" name="import_pegawai" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 shadow-lg transition font-medium">Mulai Import</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT PEGAWAI (Locked Sisa Cuti) -->
<div id="modalEdit" class="fixed inset-0 bg-slate-900/60 z-[9999] hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Edit Pegawai</h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id_pegawai" id="edit_id">
            
            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-100 text-xs text-yellow-700 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-lock"></i> Info NIP dan Sisa Cuti Awal terkunci (Locked).
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Lengkap</label>
                <input type="text" name="nama" id="edit_nama" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Tipe</label>
                    <select name="tipe" id="edit_tipe" class="w-full border rounded-lg p-2.5 bg-white">
                        <option value="PNS">PNS</option>
                        <option value="P3K">P3K</option>
                        <option value="Kontrak">Kontrak</option>
                    </select>
                </div>
                <!-- SISA CUTI LOCKED -->
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Sisa Cuti <?= $tahun_basis ?> (Locked)</label>
                    <input type="number" name="sisa_cuti_thn_lalu" id="edit_sisa_lalu" readonly class="w-full border rounded-lg p-2.5 bg-slate-200 text-slate-500 cursor-not-allowed border-slate-300">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Jabatan</label>
                    <input type="text" name="jabatan" id="edit_jabatan" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Pangkat</label>
                    <input type="text" name="pangkat" id="edit_pangkat" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            
            <div class="pt-4 flex justify-end gap-2 border-t mt-4">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200">Batal</button>
                <button type="submit" name="edit_pegawai" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg">Update Data</button>
            </div>
        </form>
    </div>
</div>

<script>
    function bukaEdit(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_nama').value = data.nama;
        document.getElementById('edit_tipe').value = data.tipe;
        document.getElementById('edit_jabatan').value = data.jabatan;
        document.getElementById('edit_pangkat').value = data.pangkat;
        document.getElementById('edit_sisa_lalu').value = data.sisa_cuti_thn_lalu;
        document.getElementById('modalEdit').classList.remove('hidden');
    }
</script>