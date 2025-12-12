<?php
// --- LOGIKA BACKEND ---

$id_user = $_SESSION['user_id'];

// 1. Ambil Data User Terbaru (Untuk ditampilkan di form)
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id_user]);
$me = $stmt->fetch();

// 2. Ambil Daftar Sekolah (Untuk Dropdown Admin Sekolah)
$list_sekolah = [];
if($me['role'] == 'admin_sekolah') {
    $q_sekolah = $conn->query("SELECT nama_sekolah FROM data_sekolah ORDER BY nama_sekolah ASC");
    $list_sekolah = $q_sekolah->fetchAll();
}

// 3. Proses Update Profil Dasar
if(isset($_POST['update_profil'])) {
    $nama_sekolah = $_POST['nama_sekolah'];
    
    // Update ke database
    $upd = $conn->prepare("UPDATE users SET nama_sekolah = ? WHERE id = ?");
    $upd->execute([$nama_sekolah, $id_user]);
    
    // Update Session agar nama sekolah di Sidebar langsung berubah
    $_SESSION['nama_sekolah'] = $nama_sekolah;
    
    // Refresh halaman untuk melihat perubahan
    echo "<script>alert('Profil berhasil diperbarui!'); window.location='?page=profil';</script>";
}

// 4. Proses Ganti Password
if(isset($_POST['ganti_password'])) {
    $pass_lama = $_POST['pass_lama'];
    $pass_baru = $_POST['pass_baru'];
    $pass_konfirm = $_POST['pass_konfirm'];
    
    // Verifikasi password lama
    if(password_verify($pass_lama, $me['password'])) {
        // Cek kecocokan password baru
        if($pass_baru === $pass_konfirm) {
            // Hash password baru
            $new_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
            
            // Simpan password baru
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$new_hash, $id_user]);
            
            echo "<script>alert('Password berhasil diganti!'); window.location='?page=profil';</script>";
        } else {
            echo "<script>alert('Gagal: Konfirmasi password baru tidak cocok.');</script>";
        }
    } else {
        echo "<script>alert('Gagal: Password lama salah.');</script>";
    }
}
?>

<!-- --- TAMPILAN FRONTEND --- -->

<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    
    <!-- KARTU PROFIL -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <h3 class="font-bold text-lg text-slate-800">Informasi Akun</h3>
            <p class="text-xs text-slate-400">Detail akun pengguna Anda</p>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <!-- Avatar & Info Singkat -->
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-16 h-16 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-2xl font-bold border-2 border-white shadow-sm">
                        <?= strtoupper(substr($me['username'], 0, 1)) ?>
                    </div>
                    <div>
                        <h4 class="font-bold text-lg text-slate-800"><?= $me['username'] ?></h4>
                        <span class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded text-xs font-bold uppercase border border-blue-100 tracking-wide">
                            <?= $me['role'] == 'admin_sekolah' ? 'Admin Sekolah' : 'Admin Dinas' ?>
                        </span>
                    </div>
                </div>

                <!-- Input Username (Disabled) -->
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Username</label>
                    <input type="text" value="<?= $me['username'] ?>" disabled class="w-full border rounded-lg p-2.5 bg-slate-100 text-slate-500 cursor-not-allowed">
                    <p class="text-[10px] text-slate-400 mt-1">Username tidak dapat diubah.</p>
                </div>

                <!-- Input Unit Kerja / Sekolah (Dinamis) -->
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Unit Kerja / Nama Sekolah</label>
                    
                    <?php if($me['role'] == 'admin_sekolah'): ?>
                        <!-- JIKA ADMIN SEKOLAH: TAMPILKAN DROPDOWN DARI DATABASE -->
                        <select name="nama_sekolah" class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500 bg-white">
                            <option value="">-- Pilih Sekolah --</option>
                            <?php foreach($list_sekolah as $sek): ?>
                                <option value="<?= $sek['nama_sekolah'] ?>" <?= ($me['nama_sekolah'] == $sek['nama_sekolah']) ? 'selected' : '' ?>>
                                    <?= $sek['nama_sekolah'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[10px] text-blue-500 mt-1"><i class="fa-solid fa-circle-info"></i> Pilih nama sekolah sesuai data dinas.</p>
                    
                    <?php else: ?>
                        <!-- JIKA ADMIN DINAS: TAMPILKAN TEXT INPUT BIASA -->
                        <input type="text" name="nama_sekolah" value="<?= $me['nama_sekolah'] ?>" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
                    <?php endif; ?>
                </div>

                <!-- Input Jenjang (Disabled untuk keamanan struktur) -->
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Jenjang</label>
                    <input type="text" value="<?= $me['jenjang'] ?>" disabled class="w-full border rounded-lg p-2.5 bg-slate-100 text-slate-500">
                </div>

                <!-- Tombol Simpan -->
                <div class="pt-4 text-right">
                    <button type="submit" name="update_profil" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg shadow-lg shadow-blue-500/30 hover:bg-blue-700 transition font-bold text-sm">
                        <i class="fa-solid fa-save mr-2"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KARTU GANTI PASSWORD -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden h-fit">
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <h3 class="font-bold text-lg text-slate-800">Keamanan</h3>
            <p class="text-xs text-slate-400">Ganti password akun Anda secara berkala</p>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Password Lama</label>
                    <input type="password" name="pass_lama" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500 placeholder-slate-300" placeholder="••••••••">
                </div>
                
                <hr class="border-slate-100 my-2">

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Password Baru</label>
                    <input type="password" name="pass_baru" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500 placeholder-slate-300" placeholder="••••••••">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Konfirmasi Password Baru</label>
                    <input type="password" name="pass_konfirm" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500 placeholder-slate-300" placeholder="••••••••">
                </div>

                <div class="pt-4 text-right">
                    <button type="submit" name="ganti_password" class="bg-orange-500 text-white px-5 py-2.5 rounded-lg shadow-lg shadow-orange-500/30 hover:bg-orange-600 transition font-bold text-sm">
                        <i class="fa-solid fa-key mr-2"></i> Ganti Password
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>