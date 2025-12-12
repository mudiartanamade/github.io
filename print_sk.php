<?php
require_once 'config/database.php';

if(!isset($_GET['id'])) die("ID tidak ditemukan");
$id = $_GET['id'];

// Ambil Data Lengkap Pegawai & Pengajuan yang DISETUJUI
$query = "SELECT pc.*, p.nama, p.nip, p.pangkat, p.jabatan, p.sekolah 
          FROM pengajuan_cuti pc 
          JOIN pegawai p ON pc.pegawai_id = p.id 
          WHERE pc.id = ? AND pc.status = 'disetujui'";
$stmt = $conn->prepare($query);
$stmt->execute([$id]);
$data = $stmt->fetch();

if(!$data) die("Dokumen tidak ditemukan atau belum disetujui.");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Surat Izin Cuti - <?= $data['nama'] ?></title>
    <!-- Library QR Code -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: 'Times New Roman', serif; margin: 0; padding: 20px; background: #f0f0f0; }
        .page { width: 21cm; min-height: 29.7cm; padding: 2cm; margin: 0 auto; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); position: relative; }
        .kop { text-align: center; border-bottom: 4px double black; padding-bottom: 15px; margin-bottom: 30px; }
        .kop img { width: 85px; position: absolute; left: 2cm; top: 2cm; }
        h2, h3 { margin: 0; line-height: 1.2; }
        .content { font-size: 12pt; line-height: 1.6; text-align: justify; }
        table.data { width: 100%; margin: 20px 0 20px 20px; }
        table.data td { padding: 4px 0; vertical-align: top; }
        .ttd { float: right; width: 45%; text-align: center; margin-top: 60px; }
        
        @media print {
            body { background: none; padding: 0; }
            .page { box-shadow: none; margin: 0; width: auto; height: auto; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="no-print" style="position: fixed; top: 20px; right: 20px; padding: 12px 24px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer; font-family: sans-serif; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        🖨️ Cetak Dokumen
    </button>

    <div class="page">
        <div class="kop">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Denpasar_City_Emblem.png/178px-Denpasar_City_Emblem.png">
            <h3 style="font-weight: normal; font-size: 14pt;">PEMERINTAH KOTA DENPASAR</h3>
            <h2 style="font-weight: bold; font-size: 18pt;">DINAS PENDIDIKAN KEPEMUDAAN DAN OLAHRAGA</h2>
            <p style="margin: 5px 0 0 0; font-size: 10pt;">Jalan Mawar No. 1, Denpasar, Bali. Telp: (0361) 123456</p>
            <p style="margin: 0; font-size: 10pt;">Website: disdikpora.denpasarkota.go.id | Email: disdikpora@denpasarkota.go.id</p>
        </div>

        <div style="text-align: center; margin-bottom: 30px;">
            <h3 style="text-decoration: underline; font-weight: bold;">SURAT IZIN CUTI</h3>
            <p>Nomor: 800 / <?= str_pad($data['id'], 4, '0', STR_PAD_LEFT) ?> / DISDIKPORA / <?= date('Y') ?></p>
        </div>

        <div class="content">
            <p>Berdasarkan Peraturan Pemerintah Nomor 11 Tahun 2017 tentang Manajemen Pegawai Negeri Sipil, dengan ini Kepala Dinas Pendidikan Kepemudaan dan Olahraga Kota Denpasar memberikan izin cuti kepada:</p>

            <table class="data">
                <tr><td width="160">Nama</td><td>: <b><?= $data['nama'] ?></b></td></tr>
                <tr><td>NIP</td><td>: <?= $data['nip'] ?></td></tr>
                <tr><td>Pangkat/Gol. Ruang</td><td>: <?= $data['pangkat'] ?></td></tr>
                <tr><td>Jabatan</td><td>: <?= $data['jabatan'] ?></td></tr>
                <tr><td>Unit Kerja</td><td>: <?= $data['sekolah'] ?></td></tr>
            </table>

            <p>Untuk menjalankan <b><?= $data['jenis_cuti'] ?></b> selama <b><?= $data['durasi'] ?> (terbilang) hari kerja</b>, terhitung mulai tanggal <b><?= tgl_indo($data['tgl_mulai']) ?></b> sampai dengan tanggal <b><?= tgl_indo($data['tgl_selesai']) ?></b>.</p>
            
            <p>Selama menjalankan cuti, pelaksanaan tugas kedinasan diserahkan kepada atasan langsung atau pejabat lain yang ditunjuk. Demikian surat izin cuti ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>

            <div class="ttd">
                <p>Denpasar, <?= tgl_indo(date('Y-m-d')) ?></p>
                <p>Kepala Dinas Pendidikan Kepemudaan<br>dan Olahraga Kota Denpasar</p>
                
                <!-- QR Code Area -->
                <div style="display: flex; justify-content: center; margin: 15px 0;">
                    <div id="qrcode"></div>
                </div>
                
                <p style="font-weight: bold; text-decoration: underline;">Dr. Nama Kepala Dinas, M.Pd</p>
                <p>Pembina Utama Muda</p>
                <p>NIP. 19700101 199503 1 001</p>
            </div>
        </div>
    </div>

    <script>
        // Generate QR Code otomatis
        new QRCode(document.getElementById("qrcode"), {
            text: "VALID-SK-<?= $data['id'] ?>-<?= $data['nip'] ?>-DISDIKPORA-DENPASAR-<?= date('Ymd', strtotime($data['tgl_validasi'])) ?>",
            width: 90,
            height: 90,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
    </script>
</body>
</html>