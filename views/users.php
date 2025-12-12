<?php
// Proteksi: Hanya Admin Dinas
if($_SESSION['role'] != 'admin_dinas') { echo "<script>alert('Akses Ditolak'); window.location='?page=home';</script>"; exit; }

// --- AMBIL DATA SEKOLAH (UNTUK DROPDOWN) ---
$q_sekolah = $conn->query("SELECT nama_sekolah FROM data_sekolah ORDER BY nama_sekolah ASC");
$list_sekolah = $q_sekolah->fetchAll();

// --- LOGIKA BACKEND ---

// 1. Tambah User Baru
if(isset($_POST['tambah_user'])) {
    try {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); 
        $role = $_POST['role'];
        $jenjang = $_POST['jenjang'];
        
        // Jika role admin dinas, kosongkan nama sekolah
        $sekolah = ($role == 'admin_sekolah') ? $_POST['nama_sekolah'] : '';

        $sql = "INSERT INTO users (username, password, role, nama_sekolah, jenjang) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $password, $role, $sekolah, $jenjang]);
        
        echo "<script>alert('User Berhasil Ditambahkan!'); window.location='?page=users';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Gagal! Username mungkin sudah ada.');</script>";
    }
}

// 2. Import User CSV
if(isset($_POST['import_user'])) {
    $fileName = $_FILES['file_csv']['tmp_name'];
    if ($_FILES['file_csv']['size'] > 0) {
        $file = fopen($fileName, "r");
        fgetcsv($file); // Skip header
        $sukses = 0;
        
        while (($col = fgetcsv($file, 10000, ",")) !== FALSE) {
            // Kolom CSV: 0=Username, 1=Password, 2=Role, 3=Nama Sekolah, 4=Jenjang
            if(!empty($col[0])) {
                try {
                    $passHash = password_hash($col[1], PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (username, password, role, nama_sekolah, jenjang) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$col[0], $passHash, $col[2], $col[3], $col[4]]);
                    $sukses++;
                } catch(Exception $e) { continue; }
            }
        }
        echo "<script>alert('Import selesai! $sukses user berhasil dibuat.'); window.location='?page=users';</script>";
    }
}

// 3. Edit User
if(isset($_POST['edit_user'])) {
    try {
        $role = $_POST['role'];
        $sekolah = ($role == 'admin_sekolah') ? $_POST['nama_sekolah'] : '';
        
        $sql = "UPDATE users SET nama_sekolah=?, jenjang=?, role=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$sekolah, $_POST['jenjang'], $role, $_POST['id_user']]);
        echo "<script>alert('Data User Berhasil Diupdate!'); window.location='?page=users';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Gagal update data.');</script>";
    }
}

// 4. Reset Password
if(isset($_POST['reset_password'])) {
    $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->execute([$new_pass, $_POST['id_user_reset']]);
    echo "<script>alert('Password Berhasil Direset!'); window.location='?page=users';</script>";
}

// 5. Hapus User
if(isset($_GET['hapus_id'])) {
    if($_GET['hapus_id'] == $_SESSION['user_id']) {
        echo "<script>alert('Tidak bisa menghapus akun sendiri!');</script>";
    } else {
        $del = $conn->prepare("DELETE FROM users WHERE id = ?");
        $del->execute([$_GET['hapus_id']]);
        echo "<script>alert('User Dihapus!'); window.location='?page=users';</script>";
    }
}
?>

<!-- --- TAMPILAN FRONTEND --- -->

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden relative z-0">
    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-white">
        <div>
            <h3 class="font-bold text-slate-800 text-lg">Manajemen Pengguna Sistem</h3>
            <p class="text-slate-400 text-xs mt-0.5">Kelola akun Admin Dinas dan Admin Sekolah</p>
        </div>
        
        <div class="flex gap-2">
            <!-- Tombol Tambah -->
            <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-blue-500/30 hover:bg-blue-700 transition flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i> <span class="hidden sm:inline">Tambah User</span>
            </button>
            
            <!-- Tombol Import -->
            <button onclick="document.getElementById('modalImport').classList.remove('hidden')" class="bg-green-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-green-500/30 hover:bg-green-700 transition flex items-center gap-2">
                <i class="fa-solid fa-file-csv"></i> <span class="hidden sm:inline">Import User</span>
            </button>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-slate-600">
            <thead class="text-xs text-slate-500 uppercase bg-slate-50/50 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-4 font-semibold text-center w-12">No</th>
                    <th class="px-6 py-4 font-semibold">Username</th>
                    <th class="px-6 py-4 font-semibold">Role</th>
                    <th class="px-6 py-4 font-semibold">Unit Kerja (Sekolah)</th>
                    <th class="px-6 py-4 font-semibold text-center">Jenjang</th>
                    <th class="px-6 py-4 font-semibold text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                $stmt = $conn->query("SELECT * FROM users ORDER BY role ASC, username ASC");
                $no = 1;
                while($row = $stmt->fetch()):
                    $roleBadge = $row['role'] == 'admin_dinas' ? 'bg-purple-100 text-purple-700 border-purple-200' : 'bg-blue-100 text-blue-700 border-blue-200';
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="px-6 py-4 text-center text-xs text-slate-400"><?= $no++ ?></td>
                    <td class="px-6 py-4 font-bold text-slate-800"><?= $row['username'] ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-[10px] font-bold border <?= $roleBadge ?>">
                            <?= $row['role'] == 'admin_dinas' ? 'ADMIN DINAS' : 'ADMIN SEKOLAH' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-slate-600"><?= $row['nama_sekolah'] ?: '-' ?></td>
                    <td class="px-6 py-4 text-center text-xs font-bold"><?= $row['jenjang'] ?></td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <!-- Edit Data -->
                            <button onclick='bukaEditUser(<?= json_encode($row) ?>)' class="w-8 h-8 rounded-lg bg-yellow-50 text-yellow-600 hover:bg-yellow-100 transition flex items-center justify-center border border-yellow-200" title="Edit User">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            
                            <!-- Reset Password -->
                            <button onclick="bukaResetPass(<?= $row['id'] ?>, '<?= $row['username'] ?>')" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition flex items-center justify-center border border-slate-300" title="Reset Password">
                                <i class="fa-solid fa-key"></i>
                            </button>

                            <!-- Hapus -->
                            <?php if($row['id'] != $_SESSION['user_id']): ?>
                            <a href="?page=users&hapus_id=<?= $row['id'] ?>" onclick="return confirm('Hapus user ini?')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition flex items-center justify-center border border-red-200" title="Hapus">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH USER -->
<div id="modalTambah" class="fixed inset-0 bg-slate-900/60 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-[fadeIn_0.3s]">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Tambah User Baru</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Username</label>
                <input type="text" name="username" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Password</label>
                <input type="password" name="password" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Role</label>
                    <select name="role" id="role_tambah" class="w-full border rounded-lg p-2.5 bg-white" onchange="toggleSekolahField('tambah')">
                        <option value="admin_sekolah">Admin Sekolah</option>
                        <option value="admin_dinas">Admin Dinas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Jenjang</label>
                    <select name="jenjang" class="w-full border rounded-lg p-2.5 bg-white">
                        <option value="TK">TK</option>
                        <option value="SD">SD</option>
                        <option value="SMP">SMP</option>
                        <option value="DINAS">DINAS</option>
                    </select>
                </div>
            </div>
            
            <!-- Dropdown Sekolah (Hanya muncul jika Admin Sekolah) -->
            <div id="field_sekolah_tambah">
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Sekolah</label>
                <select name="nama_sekolah" class="w-full border rounded-lg p-2.5 bg-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Pilih Sekolah --</option>
                    <?php foreach($list_sekolah as $s): ?>
                        <option value="<?= $s['nama_sekolah'] ?>"><?= $s['nama_sekolah'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="pt-4 flex justify-end gap-2 border-t mt-4">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition font-medium">Batal</button>
                <button type="submit" name="tambah_user" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg transition font-medium">Simpan User</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL IMPORT USER -->
<div id="modalImport" class="fixed inset-0 bg-slate-900/60 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-[fadeIn_0.3s]">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Import Data User</h3>
            <button onclick="document.getElementById('modalImport').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-800 mb-4">
                <p class="font-bold mb-1"><i class="fa-solid fa-circle-info mr-1"></i> Format CSV:</p>
                <p>Username, Password, Role, Nama Sekolah, Jenjang</p>
                <a href="template_users.csv" download class="text-blue-600 underline font-bold mt-2 block hover:text-blue-800">Download Template CSV</a>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Upload File CSV</label>
                <input type="file" name="file_csv" accept=".csv" required class="w-full border border-slate-300 rounded-lg p-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition">
            </div>
            
            <div class="pt-4 flex justify-end gap-2 border-t mt-2">
                <button type="button" onclick="document.getElementById('modalImport').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition font-medium">Batal</button>
                <button type="submit" name="import_user" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 shadow-lg transition font-medium">Mulai Import</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT USER -->
<div id="modalEdit" class="fixed inset-0 bg-slate-900/60 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Edit Data User</h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id_user" id="edit_id">
            
            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-100 text-xs text-yellow-700 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-lock"></i> Username tidak dapat diubah.
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Username</label>
                <input type="text" id="edit_username" disabled class="w-full border rounded-lg p-2.5 bg-slate-100 text-slate-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Role</label>
                    <select name="role" id="edit_role" class="w-full border rounded-lg p-2.5 bg-white" onchange="toggleSekolahField('edit')">
                        <option value="admin_sekolah">Admin Sekolah</option>
                        <option value="admin_dinas">Admin Dinas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Jenjang</label>
                    <select name="jenjang" id="edit_jenjang" class="w-full border rounded-lg p-2.5 bg-white">
                        <option value="TK">TK</option>
                        <option value="SD">SD</option>
                        <option value="SMP">SMP</option>
                        <option value="DINAS">DINAS</option>
                    </select>
                </div>
            </div>
            
            <div id="field_sekolah_edit">
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Sekolah</label>
                <select name="nama_sekolah" id="edit_sekolah" class="w-full border rounded-lg p-2.5 bg-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Pilih Sekolah --</option>
                    <?php foreach($list_sekolah as $s): ?>
                        <option value="<?= $s['nama_sekolah'] ?>"><?= $s['nama_sekolah'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="pt-4 flex justify-end gap-2 border-t mt-4">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition font-medium">Batal</button>
                <button type="submit" name="edit_user" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg transition font-medium">Update Data</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL RESET PASSWORD -->
<div id="modalReset" class="fixed inset-0 bg-slate-900/60 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Reset Password</h3>
            <button onclick="document.getElementById('modalReset').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id_user_reset" id="reset_id">
            
            <p class="text-sm text-slate-600">Masukkan password baru untuk user: <b id="reset_username"></b></p>
            
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Password Baru</label>
                <input type="password" name="new_password" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="pt-4 flex justify-end gap-2 border-t mt-4">
                <button type="button" onclick="document.getElementById('modalReset').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition font-medium">Batal</button>
                <button type="submit" name="reset_password" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 shadow-lg transition font-medium">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Logic untuk menyembunyikan field Sekolah jika Role = Admin Dinas
    function toggleSekolahField(mode) {
        let role = document.getElementById('role_' + mode) ? document.getElementById('role_' + mode).value : document.getElementById('edit_role').value;
        let field = document.getElementById('field_sekolah_' + mode);
        
        if (role === 'admin_dinas') {
            field.classList.add('hidden');
        } else {
            field.classList.remove('hidden');
        }
    }

    function bukaEditUser(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_username').value = data.username;
        document.getElementById('edit_role').value = data.role;
        document.getElementById('edit_jenjang').value = data.jenjang;
        document.getElementById('edit_sekolah').value = data.nama_sekolah;
        
        // Jalankan logic toggle agar field sekolah sesuai dengan role
        toggleSekolahField('edit');
        
        document.getElementById('modalEdit').classList.remove('hidden');
    }

    function bukaResetPass(id, username) {
        document.getElementById('reset_id').value = id;
        document.getElementById('reset_username').innerText = username;
        document.getElementById('modalReset').classList.remove('hidden');
    }
</script>