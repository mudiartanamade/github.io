<?php
// Proteksi: Hanya Admin Sekolah yang boleh mengakses
if($_SESSION['role'] != 'admin_sekolah') { echo "<script>alert('Akses Ditolak');</script>"; exit; }

// --- AMBIL CONFIGURASI DARI DATABASE ---

// 1. Tahun Sistem Aktif (untuk validasi kuota & cuti bersama)
$q_app = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key='tahun_aplikasi'");
$row_app = $q_app->fetch();
$tahun_ini = $row_app ? $row_app['setting_value'] : date('Y');

// 2. Ambil Daftar Jenis Cuti yang AKTIF (Status = '1')
$q_jenis = $conn->query("SELECT nama_cuti FROM jenis_cuti WHERE status = '1'");
$jenis_opsi = $q_jenis->fetchAll();

// 3. Ambil Daftar Hari Libur Nasional (Array Tanggal)
$libur_nasional = [];
$q_lib = $conn->query("SELECT tanggal FROM hari_libur");
while($l = $q_lib->fetch()) { $libur_nasional[] = $l['tanggal']; }

// 4. Ambil Data Pegawai Sekolah Ini (Untuk Dropdown Form)
$sekolah = $_SESSION['nama_sekolah'];
$q_peg = $conn->prepare("SELECT * FROM pegawai WHERE sekolah = ? AND status_aktif='aktif'");
$q_peg->execute([$sekolah]);
$list_pegawai = $q_peg->fetchAll();

// 5. Ambil Hari Belajar Sekolah Ini (Untuk Logika Sabtu)
$q_sek = $conn->prepare("SELECT hari_belajar FROM data_sekolah WHERE nama_sekolah = ?");
$q_sek->execute([$sekolah]);
$d_sek = $q_sek->fetch();
$hari_kerja_sekolah = $d_sek ? $d_sek['hari_belajar'] : '6 Hari'; // Default 6 hari jika data kosong

// --- PROSES SUBMIT PENGAJUAN ---
if(isset($_POST['ajukan_cuti'])){
    $pegawai_id = $_POST['pegawai_id'];
    $jenis      = $_POST['jenis_cuti'];
    $alasan     = $_POST['alasan'];
    $start      = $_POST['tgl_mulai'];
    $end        = $_POST['tgl_selesai'];
    
    $start_date = new DateTime($start);
    $end_date   = new DateTime($end);
    
    // Validasi Tanggal Terbalik
    if($start_date > $end_date) {
        echo "<script>alert('Error: Tanggal Selesai tidak boleh sebelum Tanggal Mulai');</script>";
    } else {
        // --- LOGIKA HITUNG DURASI EFEKTIF (Skip Libur & Weekend) ---
        $durasi_efektif = 0;
        // Loop setiap hari dari start sampai end
        $period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date->modify('+1 day'));
        
        foreach($period as $dt) {
            $curr = $dt->format('Y-m-d');
            $dayW = $dt->format('w'); // 0=Minggu, 6=Sabtu
            
            // Cek 1: Apakah Hari Minggu? (Selalu Libur)
            if($dayW == 0) continue;
            
            // Cek 2: Apakah Hari Sabtu & Sekolah 5 Hari Kerja?
            if($dayW == 6 && $hari_kerja_sekolah == '5 Hari') continue;
            
            // Cek 3: Apakah Tanggal Merah (Libur Nasional)?
            if(in_array($curr, $libur_nasional)) continue;
            
            $durasi_efektif++; // Jika lolos semua cek, hitung sebagai hari kerja
        }
        
        if($durasi_efektif <= 0) {
            echo "<script>alert('Gagal: Rentang tanggal yang dipilih adalah hari libur semua.');</script>";
        } else {
            // --- LOGIKA UPLOAD BUKTI ---
            $file_name = null;
            $upload_ok = true;
            
            // Aturan: Jika BUKAN Cuti Tahunan, Wajib Upload Bukti
            if($jenis != 'Cuti Tahunan') {
                if(isset($_FILES['bukti']) && $_FILES['bukti']['error'] == 0) {
                    $allowed = ['jpg','jpeg','png','pdf'];
                    $ext = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
                    $size = $_FILES['bukti']['size'];
                    
                    if(!in_array($ext, $allowed)) {
                        echo "<script>alert('Format file harus PDF atau Gambar (JPG/PNG)');</script>"; $upload_ok = false;
                    } elseif($size > 2097152) { // Max 2MB
                        echo "<script>alert('Ukuran file maksimal 2MB');</script>"; $upload_ok = false;
                    } else {
                        // Proses Upload
                        $target_dir = "uploads/";
                        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true); // Buat folder jika belum ada
                        
                        $file_name = time() . "_" . rand(100,999) . "." . $ext;
                        move_uploaded_file($_FILES['bukti']['tmp_name'], $target_dir . $file_name);
                    }
                } else {
                    echo "<script>alert('Wajib melampirkan file bukti untuk jenis cuti ini!');</script>"; $upload_ok = false;
                }
            }
            
            // --- FINAL INSERT KE DATABASE ---
            if($upload_ok) {
                // Khusus Cuti Tahunan: Cek Kuota dengan Rumus Lengkap
                $lanjut_simpan = true;
                if($jenis == 'Cuti Tahunan') {
                    // Ambil Data Pegawai untuk hitung sisa
                    $q_data_peg = $conn->prepare("SELECT kuota_cuti, sisa_cuti_thn_lalu, cuti_terpakai FROM pegawai WHERE id = ?");
                    $q_data_peg->execute([$pegawai_id]);
                    $d_peg = $q_data_peg->fetch();

                    // Ambil Potongan Cuti Bersama Tahun Ini
                    $q_cb = $conn->prepare("SELECT jml_cuti_bersama FROM pengaturan_cuti WHERE tahun = ?");
                    $q_cb->execute([$tahun_ini]);
                    $row_cb = $q_cb->fetch();
                    $potongan_cb = $row_cb ? $row_cb['jml_cuti_bersama'] : 0;
                    
                    // Rumus: (Carry Over Max 6) + (12 - Cuti Bersama) - Terpakai
                    $carry_over = ($d_peg['sisa_cuti_thn_lalu'] > 6) ? 6 : $d_peg['sisa_cuti_thn_lalu'];
                    $hak_murni = 12 - $potongan_cb;
                    $total_hak = $carry_over + $hak_murni;
                    $sisa_real = $total_hak - $d_peg['cuti_terpakai'];
                    
                    if($sisa_real < $durasi_efektif) {
                        echo "<script>alert('Gagal: Sisa cuti tahunan tidak mencukupi. Sisa: $sisa_real hari, Pengajuan: $durasi_efektif hari.');</script>";
                        $lanjut_simpan = false;
                    }
                }
                
                if($lanjut_simpan) {
                    $sql = "INSERT INTO pengajuan_cuti (pegawai_id, jenis_cuti, alasan, tgl_mulai, tgl_selesai, durasi, file_bukti, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$pegawai_id, $jenis, $alasan, $start, $end, $durasi_efektif, $file_name]);
                    echo "<script>alert('Pengajuan Berhasil! Durasi efektif dihitung: $durasi_efektif Hari Kerja.'); window.location='?page=pengajuan';</script>";
                }
            }
        }
    }
}
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Bagian Kiri: Form Input -->
    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-lg shadow-slate-200/50 border border-slate-100 sticky top-24">
            <h3 class="font-bold text-lg mb-1 text-slate-800">Formulir Cuti Baru</h3>
            <p class="text-xs text-slate-400 mb-5 border-b pb-4">Hari libur & akhir pekan otomatis tidak dihitung dalam durasi.</p>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <!-- Pilih Pegawai -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Pilih Pegawai</label>
                    <select name="pegawai_id" required class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white transition">
                        <option value="">-- Pilih Pegawai --</option>
                        <?php foreach($list_pegawai as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= $p['nama'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Pilih Jenis Cuti (Dinamis dari Database) -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Jenis Cuti</label>
                    <select name="jenis_cuti" id="jenis_cuti" onchange="toggleUpload()" required class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white transition">
                        <option value="">-- Pilih Jenis --</option>
                        <?php foreach($jenis_opsi as $j): ?>
                            <option value="<?= $j['nama_cuti'] ?>"><?= $j['nama_cuti'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Input Tanggal -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Mulai</label>
                        <input type="date" name="tgl_mulai" required class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Selesai</label>
                        <input type="date" name="tgl_selesai" required class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition">
                    </div>
                </div>

                <!-- Input File Upload (Hidden by default, muncul via JS) -->
                <div id="upload_area" class="hidden animate-[fadeIn_0.3s_ease-out]">
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Bukti Pendukung (PDF/IMG Max 2MB)</label>
                    <input type="file" name="bukti" id="input_bukti" accept=".pdf,.jpg,.jpeg,.png" class="w-full border border-slate-300 rounded-xl p-2 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition cursor-pointer">
                    <p class="text-[10px] text-red-500 mt-1 italic">* Wajib diunggah untuk jenis cuti ini.</p>
                </div>

                <!-- Alasan -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Alasan Cuti</label>
                    <textarea name="alasan" rows="3" required class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-slate-400 transition" placeholder="Jelaskan alasan pengajuan..."></textarea>
                </div>
                
                <!-- Info Tambahan -->
                <div class="bg-blue-50 p-3 rounded-lg border border-blue-100 flex items-start gap-2">
                    <i class="fa-solid fa-circle-info text-blue-600 mt-0.5 text-xs"></i>
                    <p class="text-[11px] text-blue-800 leading-tight">
                        Sistem otomatis mengecualikan <b>Hari Libur Nasional</b> dan <b>Akhir Pekan</b> (sesuai jam kerja sekolah: <?= $hari_kerja_sekolah ?>) dari perhitungan durasi cuti.
                    </p>
                </div>

                <!-- Tombol Submit -->
                <button type="submit" name="ajukan_cuti" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-500/30 transition transform active:scale-95 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> Kirim Pengajuan
                </button>
            </form>
        </div>
    </div>

    <!-- Bagian Kanan: Tabel Riwayat -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-white font-bold text-slate-700 flex justify-between items-center">
                <span class="text-lg">Riwayat Pengajuan Sekolah</span>
                <span class="text-xs font-medium bg-slate-100 px-3 py-1.5 rounded-full text-slate-500 border border-slate-200"><?= $sekolah ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-slate-600">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Pegawai</th>
                            <th class="px-6 py-4 font-semibold">Jenis Cuti</th>
                            <th class="px-6 py-4 font-semibold text-center">Durasi</th>
                            <th class="px-6 py-4 font-semibold text-center">Bukti</th>
                            <th class="px-6 py-4 font-semibold text-center">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $hist = $conn->prepare("SELECT pc.*, p.nama FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id WHERE p.sekolah = ? ORDER BY pc.id DESC");
                        $hist->execute([$sekolah]);
                        
                        if($hist->rowCount() == 0) {
                            echo "<tr><td colspan='6' class='p-8 text-center text-slate-400 italic'>Belum ada riwayat pengajuan.</td></tr>";
                        }

                        while($row = $hist->fetch()):
                        ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-6 py-4 font-medium text-slate-800"><?= $row['nama'] ?></td>
                            <td class="px-6 py-4">
                                <div class="text-indigo-600 font-bold text-xs uppercase mb-1"><?= $row['jenis_cuti'] ?></div>
                                <div class="text-xs text-slate-500 flex items-center gap-1">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= tgl_indo($row['tgl_mulai']) ?> - <?= tgl_indo($row['tgl_selesai']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-bold border border-slate-200"><?= $row['durasi'] ?> Hari</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if($row['file_bukti']): ?>
                                    <a href="uploads/<?= $row['file_bukti'] ?>" target="_blank" class="text-xs font-bold text-blue-600 hover:text-blue-800 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100 transition flex items-center justify-center gap-1 w-fit mx-auto">
                                        <i class="fa-solid fa-paperclip"></i> Lihat
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 italic">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if($row['status']=='disetujui'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-700 border border-green-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span> Disetujui
                                    </span>
                                <?php elseif($row['status']=='ditolak'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-red-100 text-red-700 border border-red-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span> Ditolak
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-700 border border-yellow-200 animate-pulse">
                                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-600"></span> Menunggu
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if($row['status']=='disetujui'): ?>
                                    <a href="print_sk.php?id=<?= $row['id'] ?>" target="_blank" class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-bold text-xs bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-100 transition">
                                        <i class="fa-solid fa-print"></i> Cetak SK
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-300 text-xs">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Javascript: Toggle Input File -->
<script>
    function toggleUpload() {
        var jenis = document.getElementById("jenis_cuti").value;
        var area = document.getElementById("upload_area");
        var input = document.getElementById("input_bukti");
        
        // Logika: Jika "Cuti Tahunan" atau kosong, sembunyikan upload
        if (jenis === "Cuti Tahunan" || jenis === "") {
            area.classList.add("hidden");
            input.required = false; // Tidak wajib
            input.value = ""; // Reset value
        } else {
            area.classList.remove("hidden");
            input.required = true; // Wajib upload
        }
    }
</script>