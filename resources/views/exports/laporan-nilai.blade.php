<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Nilai - {{ $kelas->nama_kelas }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }

        .header {
            background: #1e40af;
            color: white;
            padding: 16px 24px;
            margin-bottom: 16px;
        }
        .header h1 { font-size: 18px; font-weight: bold; }
        .header p  { font-size: 11px; opacity: 0.85; margin-top: 2px; }

        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #1e40af;
            padding: 10px 14px;
            margin-bottom: 14px;
            display: flex;
            gap: 24px;
        }
        .info-item label { font-weight: bold; color: #6b7280; font-size: 9px; text-transform: uppercase; }
        .info-item p     { font-size: 12px; color: #111827; }

        .statistik {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }
        .stat-card {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
        }
        .stat-card .angka { font-size: 20px; font-weight: bold; color: #1e40af; }
        .stat-card .label { font-size: 9px; color: #6b7280; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        thead tr { background: #1e40af; color: white; }
        thead th { padding: 8px 6px; text-align: left; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #eff6ff; }
        tbody td { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 9px;
            font-weight: 600;
        }
        .badge-selesai  { background: #dcfce7; color: #166534; }
        .badge-aktif    { background: #dbeafe; color: #1e40af; }
        .badge-belum    { background: #f3f4f6; color: #6b7280; }

        .nilai-cell { font-weight: bold; }
        .nilai-lulus { color: #16a34a; }
        .nilai-gagal { color: #dc2626; }

        .footer {
            margin-top: 16px;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            color: #6b7280;
            font-size: 9px;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <h1>📊 Laporan Nilai & Progress Mahasiswa</h1>
        <p>{{ $kelas->nama_kelas }} &bull; {{ $kelas->dosen->nama_lengkap }} &bull; Dicetak: {{ $tanggal_cetak }}</p>
    </div>

    {{-- Info Kelas --}}
    <div class="info-box">
        <div class="info-item">
            <label>Kode Kelas</label>
            <p>{{ $kelas->kode_kelas }}</p>
        </div>
        <div class="info-item">
            <label>Semester</label>
            <p>{{ $kelas->semester ?? '-' }}</p>
        </div>
        <div class="info-item">
            <label>Tahun Ajaran</label>
            <p>{{ $kelas->tahun_ajaran ?? '-' }}</p>
        </div>
        <div class="info-item">
            <label>Dosen Pengampu</label>
            <p>{{ $kelas->dosen->nama_lengkap }}</p>
        </div>
    </div>

    {{-- Statistik Ringkasan --}}
    <div class="statistik">
        <div class="stat-card">
            <div class="angka">{{ $statistik['total_mahasiswa'] }}</div>
            <div class="label">Total Mahasiswa</div>
        </div>
        <div class="stat-card">
            <div class="angka">{{ $statistik['rata_rata'] ?? '-' }}</div>
            <div class="label">Rata-rata Nilai</div>
        </div>
        <div class="stat-card">
            <div class="angka" style="color:#16a34a">{{ $statistik['tertinggi'] ?? '-' }}</div>
            <div class="label">Nilai Tertinggi</div>
        </div>
        <div class="stat-card">
            <div class="angka" style="color:#dc2626">{{ $statistik['terendah'] ?? '-' }}</div>
            <div class="label">Nilai Terendah</div>
        </div>
        <div class="stat-card">
            <div class="angka" style="color:#16a34a">{{ $statistik['lulus'] }}</div>
            <div class="label">Mahasiswa Lulus (≥70)</div>
        </div>
    </div>

    {{-- Tabel Nilai --}}
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIM</th>
                <th>Nama Mahasiswa</th>
                @foreach($kelas->moduls as $modul)
                    <th>{{ $modul->judul }}</th>
                @endforeach
                <th>Nilai Akhir</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data_mahasiswa as $index => $mhs)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $mhs['nim'] }}</td>
                <td><strong>{{ $mhs['nama'] }}</strong></td>
                @foreach($mhs['progress_modul'] as $prog)
                    <td>
                        @if($prog['nilai_tugas'] !== '-')
                            <span class="nilai-cell {{ $prog['nilai_tugas'] >= 70 ? 'nilai-lulus' : 'nilai-gagal' }}">
                                {{ $prog['nilai_tugas'] }}
                            </span>
                            <br><small>{{ $prog['persentase'] }}%</small>
                        @else
                            <span style="color:#9ca3af">-</span>
                        @endif
                    </td>
                @endforeach
                <td>
                    @if($mhs['nilai_akhir'] !== '-')
                        <span class="nilai-cell {{ $mhs['nilai_akhir'] >= 70 ? 'nilai-lulus' : 'nilai-gagal' }}">
                            {{ $mhs['nilai_akhir'] }}
                        </span>
                    @else
                        <span style="color:#9ca3af">-</span>
                    @endif
                </td>
                <td>
                    <span class="badge badge-{{ $mhs['status_kelas'] === 'selesai' ? 'selesai' : ($mhs['status_kelas'] === 'aktif' ? 'aktif' : 'belum') }}">
                        {{ ucfirst($mhs['status_kelas']) }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Footer --}}
    <div class="footer">
        <span>LMS Backend Core — Laporan otomatis dibuat oleh sistem</span>
        <span>{{ $tanggal_cetak }}</span>
    </div>

</body>
</html>
