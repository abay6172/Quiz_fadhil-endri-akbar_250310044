<?php
$db = new mysqli("localhost", "root", "", "perpustakaan");

class User {
    public function __construct(protected $nama) {}
}

class Anggota extends User {
    public function simpan($db, $alamat) {
        $s = $db->prepare("INSERT INTO anggota (nama, alamat) VALUES (?, ?)");
        $s->bind_param("ss", $this->nama, $alamat);
        return $s->execute();
    }
}

class Buku {
    public function __construct(private $judul, private $penulis, private $stok) {}

    public function simpan($db) {
        $s = $db->prepare("INSERT INTO buku (judul, penulis, stok) VALUES (?, ?, ?)");
        $s->bind_param("ssi", $this->judul, $this->penulis, $this->stok);
        return $s->execute();
    }

    public static function jumlahBuku($db) {
        return $db->query("SELECT COUNT(*) FROM buku")->fetch_row()[0];
    }
}

class Peminjaman {
    public function pinjam($db, $anggota, $buku) {
        $s = $db->prepare("UPDATE buku SET stok = stok - 1 WHERE id_buku = ? AND stok > 0");
        $s->bind_param("i", $buku);
        $s->execute();
        if (!$s->affected_rows) return "Stok buku habis!";

        $s = $db->prepare("INSERT INTO peminjaman (id_anggota, id_buku, tanggal_pinjam, status)
                           VALUES (?, ?, CURDATE(), 'Dipinjam')");
        $s->bind_param("ii", $anggota, $buku);
        $s->execute();
        return "Peminjaman berhasil!";
    }

    public function kembali($db, $id) {
        $s = $db->prepare("UPDATE peminjaman p JOIN buku b ON b.id_buku = p.id_buku
                           SET p.status = 'Dikembalikan', b.stok = b.stok + 1
                           WHERE p.id_peminjaman = ? AND p.status = 'Dipinjam'");
        $s->bind_param("i", $id);
        $s->execute();
        return $s->affected_rows ? "Buku berhasil dikembalikan!" : "Data tidak ditemukan!";
    }
}

function e($x) { return htmlspecialchars($x, ENT_QUOTES, 'UTF-8'); }

$pesan = "";
if ($_POST) {
    if (isset($_POST['tambah_anggota']))
        $pesan = (new Anggota($_POST['nama']))->simpan($db, $_POST['alamat'])
            ? "Anggota ditambahkan!" : "Gagal menambah anggota!";

    if (isset($_POST['tambah_buku']))
        $pesan = (new Buku($_POST['judul'], $_POST['penulis'], (int)$_POST['stok']))->simpan($db)
            ? "Buku ditambahkan!" : "Gagal menambah buku!";

    if (isset($_POST['pinjam']))
        $pesan = (new Peminjaman)->pinjam($db, (int)$_POST['anggota'], (int)$_POST['buku']);

    if (isset($_POST['kembali']))
        $pesan = (new Peminjaman)->kembali($db, (int)$_POST['id_peminjaman']);
}

$transaksi = $db->query(
    "SELECT p.*, a.nama, b.judul FROM peminjaman p
     JOIN anggota a ON p.id_anggota = a.id_anggota
     JOIN buku b ON p.id_buku = b.id_buku
     ORDER BY p.id_peminjaman DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Perpustakaan</title>
    <style>
        body { font-family: Arial; background: #f2f2f2; padding: 20px; }
        .box { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; }
        input, select, button { padding: 9px; margin: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        button { background: #2878c8; color: #fff; border: none; cursor: pointer; }
    </style>
</head>
<body>
<h2>Aplikasi Perpustakaan</h2>

<?php if ($pesan) echo "<div class='box'>" . e($pesan) . "</div>"; ?>

<div class="box">
    <h3>Anggota</h3>
    <form method="POST">
        <input name="nama" placeholder="Nama" required>
        <input name="alamat" placeholder="Alamat" required>
        <button name="tambah_anggota">Tambah</button>
    </form>
</div>

<div class="box">
    <h3>Buku</h3>
    <form method="POST">
        <input name="judul" placeholder="Judul" required>
        <input name="penulis" placeholder="Penulis" required>
        <input type="number" name="stok" placeholder="Stok" min="0" required>
        <button name="tambah_buku">Tambah</button>
    </form>
    <p>Jumlah jenis buku: <?= Buku::jumlahBuku($db) ?></p>
</div>

<div class="box">
    <h3>Peminjaman</h3>
    <form method="POST">
        <select name="anggota" required>
            <option value="">Pilih Anggota</option>
            <?php foreach ($db->query("SELECT * FROM anggota") as $a): ?>
                <option value="<?= $a['id_anggota'] ?>"><?= e($a['nama']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="buku" required>
            <option value="">Pilih Buku</option>
            <?php foreach ($db->query("SELECT * FROM buku WHERE stok > 0") as $b): ?>
                <option value="<?= $b['id_buku'] ?>"><?= e($b['judul']) ?> (Stok: <?= $b['stok'] ?>)</option>
            <?php endforeach; ?>
        </select>
        <button name="pinjam">Pinjam</button>
    </form>
</div>

<div class="box">
    <h3>Data Peminjaman</h3>
    <table>
        <tr><th>Anggota</th><th>Buku</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr>
        <?php foreach ($transaksi as $t): ?>
        <tr>
            <td><?= e($t['nama']) ?></td>
            <td><?= e($t['judul']) ?></td>
            <td><?= e($t['tanggal_pinjam']) ?></td>
            <td><?= e($t['status']) ?></td>
            <td>
                <?php if ($t['status'] == 'Dipinjam'): ?>
                <form method="POST">
                    <input type="hidden" name="id_peminjaman" value="<?= $t['id_peminjaman'] ?>">
                    <button name="kembali">Kembalikan</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>