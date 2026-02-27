<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin','guru','wali']);

require_once __DIR__ . '/models/Laporan.php';
require_once __DIR__ . '/models/Kelas.php';

$laporanModel = new Laporan();
$kelasModel = new Kelas();

$pageTitle = 'Laporan Absensi';

function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }

// Read filters from GET
$selected_kelas = isset($_GET['kelas']) ? intval($_GET['kelas']) : 0;
$from = isset($_GET['from']) && $_GET['from'] !== '' ? sanitizeInput($_GET['from']) : null;
$to = isset($_GET['to']) && $_GET['to'] !== '' ? sanitizeInput($_GET['to']) : null;
$export = isset($_GET['export']) ? $_GET['export'] : null;

$kelass = $kelasModel->all();

// If export requested, produce CSV and exit
if ($export === 'csv' && $selected_kelas) {
	$rows = $laporanModel->getAttendanceSummary($selected_kelas, $from, $to);
	$filename = 'laporan_absensi_kelas_' . $selected_kelas . '_' . date('Ymd_His') . '.csv';
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	$out = fopen('php://output', 'w');
	// header
	fputcsv($out, ['ID Siswa','NIS','Nama','Hadir','Total Absensi']);
	// Export data even if empty (will only have header row)
	if ($rows && count($rows) > 0) {
		foreach ($rows as $r) {
			fputcsv($out, [
				$r['id_siswa'],
				$r['nis'],
				$r['nama'],
				$r['hadir'],
				$r['total_absensi']
			]);
		}
	}
	fclose($out);
	exit();
}

$data = [];
if ($selected_kelas) {
	$data = $laporanModel->getAttendanceSummary($selected_kelas, $from, $to);
}

include __DIR__ . '/layout/header.php';
?>

<div class="form-container full-width-from-sidebar no-left-accent" style="margin-top:10px">
	<h2>Laporan Absensi</h2>
	<form method="get" action="laporan.php">
		<div class="form-group">
			<label for="kelas">Pilih Kelas</label>
			<select id="kelas" name="kelas" required>
				<option value="">-- Pilih kelas --</option>
				<?php foreach ($kelass as $k): ?>
					<option value="<?php echo (int)$k['id_kelas']; ?>" <?php echo $selected_kelas == $k['id_kelas'] ? 'selected' : ''; ?>><?php echo esc($k['nama_kelas']); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="form-group">
			<label for="from">Dari Tanggal</label>
			<input id="from" name="from" type="date" value="<?php echo esc($from); ?>">
		</div>

		<div class="form-group">
			<label for="to">Sampai Tanggal</label>
			<input id="to" name="to" type="date" value="<?php echo esc($to); ?>">
		</div>

		<div class="form-buttons">
			<button type="submit" class="btn btn-success">Tampilkan</button>
			
		</div>
	</form>
</div>

<style>
	.table-header-wrapper {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 15px;
	}
	
	.table-header-wrapper h2 {
		margin: 0;
	}
	
	.export-btn-wrapper {
		display: flex;
		align-items: center;
	}
	
	.export-btn-wrapper .btn {
		margin-left: 10px;
	}
</style>

<div class="table-container full-width-from-sidebar" style="margin-top:18px">
	<div class="table-header-wrapper">
		<h2>Hasil</h2>
		<div class="export-btn-wrapper">
			<?php if ($selected_kelas): ?>
				<a class="btn" href="laporan.php?kelas=<?php echo (int)$selected_kelas; ?>&from=<?php echo urlencode($from ?? ''); ?>&to=<?php echo urlencode($to ?? ''); ?>&export=csv" style="padding: 10px 16px; font-size: 13px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; text-decoration: none;">
					<i class="fas fa-download"></i> Ekspor CSV
				</a>
			<?php else: ?>
				<button type="button" class="btn" disabled style="padding: 10px 16px; font-size: 13px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; opacity: 0.6; cursor: not-allowed;" title="Pilih kelas terlebih dahulu">
					<i class="fas fa-download"></i> Ekspor CSV
				</button>
			<?php endif; ?>
		</div>
	</div>
	<table>
		<thead>
			<tr>
				<th>ID</th>
				<th>NIS</th>
				<th>Nama</th>
				<th>Hadir</th>
				<th>Total Absensi</th>
			</tr>
		</thead>
		<tbody>
			<?php if ($data): foreach ($data as $row): ?>
			<tr>
				<td><?php echo (int)$row['id_siswa']; ?></td>
				<td><?php echo esc($row['nis']); ?></td>
				<td><?php echo esc($row['nama']); ?></td>
				<td><?php echo (int)$row['hadir']; ?></td>
				<td><?php echo (int)$row['total_absensi']; ?></td>
			</tr>
			<?php endforeach; else: ?>
			<tr><td colspan="5" style="text-align:center;color:#999;padding:18px">Tidak ada data untuk filter ini</td></tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

