<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring Anggaran</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f5f6fa;
            font-family: 'Inter', sans-serif;
        }

        .content {
            padding: 30px 60px;
        }

        .content h3 {
            font-weight: 600;
        }

        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .card h6 {
            font-weight: 600;
            margin-bottom: 15px;
        }

        .table th,
        .table td {
            vertical-align: middle;
            font-size: 14px;
        }

        .form-control {
            font-size: 14px;
        }

        canvas {
            max-height: 300px;
        }

        .card-chart {
            height: 380px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px;
        }

        .card-chart canvas {
            flex-grow: 1;
        }
    </style>
</head>

<body>

    {{-- Role-based controls added by automated patch --}}
    @php $role = auth()->check() ? auth()->user()->role : null; @endphp

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var role = "{{ $role }}";
            if (role !== 'admin') {
                document.querySelectorAll('.admin-only').forEach(function (el) { el.style.display = 'none'; });
            }
            if (role !== 'manajer') {
                document.querySelectorAll('.manajer-only').forEach(function (el) { el.style.display = 'none'; });
            }
        });
    </script>

    <!-- Bootstrap Bundle JS (sudah termasuk Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <div @class(['container-fluid'])>
        <div @class(['row'])>
            <!-- Content -->
            <div @class(['col-12', 'content'])>
                <!-- Dashboard Section -->
                <h3 class="mb-4">Dashboard</h3>
                <!-- Filter Persen -->
                <div class="mb-3 d-flex align-items-center">
                    <label for="filterPersen" class="me-2 fw-bold">Tampilkan Anggaran (%):</label>
                    <input type="number" id="filterPersen" class="form-control w-auto me-2" value="70" min="1" max="200"
                        style="width:80px;">
                    <button class="btn btn-primary btn-sm" onclick="updateChart()">Terapkan</button>
                </div>

                <div class="row g-4 mt-3">
                    <!-- Diagram 70% -->
                    <div class="col-md-6">
                        <div class="card card-chart">
                            <h6 id="chartTitle">Diagram Anggaran (70%)</h6>
                            <canvas id="barChart70"></canvas>
                        </div>
                    </div>

                    <!-- Diagram 100% -->
                    <div class="col-md-6">
                        <div class="card card-chart">
                            <h6>Diagram Anggaran (100%)</h6>
                            <canvas id="barChart100"></canvas>
                        </div>
                    </div>
                </div>

                <style>
                    /* Pastikan card dan chart bisa menyesuaikan ukuran layar */
                    .card-chart {
                        background: #fff;
                        border-radius: 12px;
                        border: 1px solid #e0e0e0;
                        transition: transform 0.2s ease, box-shadow 0.2s ease;
                    }

                    .card-chart:hover {
                        transform: translateY(-3px);
                        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                    }

                    /* Kontainer agar canvas proporsional */
                    .chart-container {
                        position: relative;
                        width: 100%;
                        height: 350px;
                        /* default tinggi di desktop */
                    }

                    /* Responsif: di layar kecil, tinggi grafik menyesuaikan */
                    @media (max-width: 768px) {
                        .chart-container {
                            height: 250px;
                        }
                    }

                    @media (max-width: 576px) {
                        .chart-container {
                            height: 200px;
                        }

                        .card-chart h6 {
                            font-size: 14px;
                        }
                    }
                </style>

                <!-- Program Kerja Detail Section -->
                <h3 @class(['mt-5', 'mb-4'])>Program Kerja</h3>
                <div @class(['row', 'g-4', 'mb-4'])>
                    <!-- Dropdown Filter -->
                    <div class="mb-3 d-flex justify-content-end">
                        <label class="me-2 fw-bold">Filter Kategori:</label>
                        <select id="filterKategori" class="form-select w-auto">
                            <option value="all">Semua</option>
                            @foreach ($kategori as $namaKategori)
                                <option value="{{ $namaKategori }}">{{ $namaKategori }}</option>
                            @endforeach
                            <option value="keseluruhan">Keseluruhan Anggaran</option>
                        </select>
                    </div>

                    <!-- Tabel Keseluruhan (hidden by default) -->
                    <div class="row d-none" id="anggaranTable">
                        <div class="col-12">
                            <table class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Kategori</th>
                                        <th>Anggaran Total</th>
                                        <th>Terserap</th>
                                        <th>Tersisa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $grandTotal = 0;
                                        $grandTerserap = 0;
                                        $grandTersisa = 0;
                                    @endphp
                                    @foreach ($kategori as $namaKategori)
                                        @php
                                            $anggaran = $anggarans[$namaKategori] ?? null;
                                            $total = $anggaran->total ?? 0;
                                            $terserap = $anggaran->terserap ?? 0;
                                            $tersisa = $anggaran->tersisa ?? 0;

                                            $grandTotal += $total;
                                            $grandTerserap += $terserap;
                                            $grandTersisa += $tersisa;
                                        @endphp
                                        <tr>
                                            <td>{{ $namaKategori }}</td>
                                            <td>Rp {{ number_format($total, 0, ',', '.') }}</td>
                                            <td>Rp {{ number_format($terserap, 0, ',', '.') }}</td>
                                            <td>Rp {{ number_format($tersisa, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td>Total Keseluruhan</td>
                                        <td>Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($grandTerserap, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($grandTersisa, 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Script Switcher -->
                    <script>
                        document.getElementById('filterKategori').addEventListener('change', function () {
                            let value = this.value;
                            let cards = document.getElementById('anggaranCards');
                            let table = document.getElementById('anggaranTable');

                            if (value === "keseluruhan") {
                                cards.classList.add('d-none');
                                table.classList.remove('d-none');
                            } else {
                                table.classList.add('d-none');
                                cards.classList.remove('d-none');
                            }
                        });
                    </script>

                    <!-- Detail Anggaran -->
                    <div @class(['row']) id="anggaranCards">
                        @foreach ($kategori as $namaKategori)
                            @php
                                $anggaran = $anggarans[$namaKategori] ?? null;
                                $total = $anggaran->total ?? 0;
                                $terserap = $anggaran->terserap ?? 0;
                                $tersisa = $anggaran->tersisa ?? 0;
                            @endphp

                            <div @class(['col-md-4', 'anggaran-card']) data-kategori="{{ $namaKategori }}">
                                <div @class(['card', 'p-3', 'h-100', 'position-relative'])>
                                    <h6>Detail Anggaran {{ $namaKategori }}</h6>
                                    <p><strong>Anggaran Total:</strong> Rp {{ number_format($total, 0, ',', '.') }}</p>
                                    <p><strong>Terserap:</strong> Rp {{ number_format($terserap, 0, ',', '.') }}</p>
                                    <p><strong>Tersisa:</strong> Rp {{ number_format($tersisa, 0, ',', '.') }}</p>
                                    <div @class(['progress', 'mb-2'])>
                                        <div @class(['progress-bar', 'bg-success'])
                                            style="width:{{ $total > 0 ? ($terserap / $total * 100) : 0 }}%">
                                            {{ $total > 0 ? round($terserap / $total * 100) : 0 }}%
                                        </div>
                                    </div>

                                    <!-- Jika belum ada data di DB, tampil tombol Tambah -->
                                    @if(!$anggaran)
                                        <button @class(['btn', 'btn-sm', 'btn-outline-success']) data-bs-toggle="modal"
                                            data-bs-target="#tambahAnggaran{{ Str::slug($namaKategori) }}">
                                            Tambah Anggaran
                                        </button>
                                    @endif

                                    <!-- Tombol detail -->
                                    <button @class(['btn', 'btn-sm', 'btn-outline-primary']) data-bs-toggle="modal"
                                        data-bs-target="#detailAnggaran{{ Str::slug($namaKategori) }}">
                                        Detail
                                    </button>
                                </div>
                            </div>

                            <!-- Modal Tambah Anggaran -->
                            <div @class(['modal', 'fade']) id="tambahAnggaran{{ Str::slug($namaKategori) }}" tabindex="-1"
                                aria-hidden="true">
                                <div @class(['modal-dialog'])>
                                    <div @class(['modal-content'])>
                                        <form action="{{ route('anggaran.store') }}" method="POST">
                                            @csrf
                                            <div @class(['modal-header'])>
                                                <h5 @class(['modal-title'])>Tambah Anggaran - {{ $namaKategori }}</h5>
                                                <button type="button" @class(['btn-close'])
                                                    data-bs-dismiss="modal"></button>
                                            </div>

                                            <div @class(['modal-body'])>
                                                <input type="hidden" name="nama" value="{{ $namaKategori }}">

                                                <div @class(['mb-3'])>
                                                    <label @class(['form-label'])>Total Anggaran</label>
                                                    <input type="text" id="totalAnggaran{{ Str::slug($namaKategori) }}"
                                                        name="total" @class(['form-control']) required
                                                        placeholder="Masukkan nominal">
                                                </div>
                                            </div>

                                            <div @class(['modal-footer'])>
                                                <button type="button" @class(['btn', 'btn-secondary'])
                                                    data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" @class(['btn', 'btn-success'])>Simpan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Script Format Otomatis -->
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    const input = document.getElementById('totalAnggaran{{ Str::slug($namaKategori) }}');

                                    input.addEventListener('input', function (e) {
                                        // Hapus semua karakter selain angka
                                        let value = e.target.value.replace(/\D/g, '');

                                        // Format dengan titik setiap 3 digit
                                        value = new Intl.NumberFormat('id-ID').format(value);

                                        // Tampilkan kembali di input
                                        e.target.value = value;
                                    });

                                    // Saat form dikirim, ubah ke angka tanpa titik
                                    input.form.addEventListener('submit', function () {
                                        input.value = input.value.replace(/\./g, '');
                                    });
                                });
                            </script>

                            <!-- Modal Detail -->
                            <div @class(['modal', 'fade']) id="detailAnggaran{{ Str::slug($namaKategori) }}" tabindex="-1"
                                aria-hidden="true">
                                <div @class(['modal-dialog', 'modal-lg'])>
                                    <div @class(['modal-content'])>
                                        <div @class(['modal-header'])>
                                            <h5 @class(['modal-title'])>Detail Penggunaan Anggaran - {{ $namaKategori }}
                                            </h5>
                                            <button type="button" @class(['btn-close']) data-bs-dismiss="modal"></button>
                                        </div>
                                        <div @class(['modal-body'])>
                                            <ul>
                                                @if($anggaran && $anggaran->programKerjas->count())
                                                    @foreach ($anggaran->programKerjas as $program)
                                                        <li>
                                                            Rp {{ number_format($program->dana, 0, ',', '.') }}
                                                            – {{ $program->deskripsi }}
                                                        </li>
                                                    @endforeach
                                                @else
                                                    <li>Belum ada penggunaan anggaran.</li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Program Kerja -->
                    <div @class(['card', 'mt-4', 'p-4'])>
                        <h5 @class(['mb-3'])>Program Kerja</h5>
                        <!-- Input Pencarian -->
                        <div @class(['mb-3'])>
                            <input type="text" id="searchProgram" @class(['form-control'])
                                placeholder="Cari program kerja...">
                        </div>
                        <div @class(['table-responsive'])>
                            <table @class(['table', 'table-hover', 'align-middle'])>
                                <thead @class(['table-light'])>
                                    <tr>
                                        <th>Deskripsi</th>
                                        <th>Penanggung Jawab</th>
                                        <th>Vendor</th>
                                        <th>Waktu Dimulai</th>
                                        <th>Target Waktu</th>
                                        <th>Status</th>
                                        <th>Kategori</th>
                                        <th>Dana</th> <!-- Kolom baru untuk dana -->
                                        <th>Detail</th>
                                        <th @class(['text-center'])>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <tbody>
                                    @foreach($programKerjas as $program)
                                                        <tr data-kategori="{{ $program->kategori ?? 'Umum' }}">
                                                            <td>{{ $program->deskripsi }}</td>
                                                            <td>{{ $program->penanggung_jawab }}</td>
                                                            <td>
                                                                @if ($program->vendor === 'Tidak Ada Vendor' || empty($program->vendor))
                                                                    -
                                                                @else
                                                                    {{ $program->vendor }}
                                                                @endif
                                                            </td>
                                                            <td>{{ $program->created_at->format('d-m-Y') }}</td>
                                                            <td>{{ \Carbon\Carbon::parse($program->target_waktu)->format('d-m-Y') }}</td>
                                                            <td>
                                                                <span class="badge
                                        @if($program->status == 'Belum Selesai') bg-secondary
                                        @elseif($program->status == 'Proses') bg-warning text-dark
                                        @else bg-success
                                        @endif">
                                                                    {{ $program->status }}
                                                                </span>
                                                            </td>
                                                            <td>{{ $program->kategori ?? '-' }}</td>
                                                            <td>Rp {{ number_format($program->dana ?? 0, 0, ',', '.') }}</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                                    data-bs-target="#detailProgram{{ $program->id }}">
                                                                    Detail
                                                                </button>
                                                            </td>
                                                            <td class="text-center">
                                                                @if(auth()->user()->role == 'admin')
                                                                    <!-- Admin -->
                                                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal"
                                                                        data-bs-target="#editModal{{ $program->id }}" title="Edit">
                                                                        <i class="bi bi-pencil-square"></i>
                                                                    </button>

                                                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                                        data-bs-target="#progressModal{{ $program->id }}"
                                                                        title="Update Progres">
                                                                        <i class="bi bi-clipboard-check"></i>
                                                                    </button>

                                                                    <form action="{{ route('program-kerja.destroy', $program->id) }}"
                                                                        method="POST" style="display:inline;">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-sm btn-danger"
                                                                            onclick="return confirm('Yakin ingin hapus program kerja ini?')"
                                                                            title="Hapus">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    </form>

                                                                @elseif(auth()->user()->role == 'manajer')
                                                                    <!-- Manajer -->
                                                                    @if(!$program->approved && $program->status != 'Selesai')
                                                                        <form action="{{ route('program-kerja.approve', $program->id) }}"
                                                                            method="POST" style="display:inline;">
                                                                            @csrf
                                                                            <button type="submit" class="btn btn-sm btn-outline-success"
                                                                                title="Approve">
                                                                                <i class="bi bi-check"></i> Approve
                                                                            </button>
                                                                        </form>
                                                                    @else
                                                                        <span class="badge bg-success">Disetujui</span>
                                                                    @endif

                                                                @elseif(auth()->user()->role == 'user')
                                                                    <!-- User -->
                                                                    <span class="text-muted">-</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    </tbody>

                                                    <!-- MODAL UPDATE PROGRES -->
                                                    <div class="modal fade" id="progressModal{{ $program->id }}" tabindex="-1"
                                                        aria-labelledby="progressModalLabel{{ $program->id }}" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="progressModalLabel{{ $program->id }}">
                                                                        Update Progres – {{ $program->deskripsi }}
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                        aria-label="Close"></button>
                                                                </div>

                                                                <form action="{{ route('progres.store') }}" method="POST">
                                                                    @csrf
                                                                    <input type="hidden" name="program_kerja_id" value="{{ $program->id }}">

                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label for="tanggal-{{ $program->id }}"
                                                                                class="form-label">Tanggal</label>
                                                                            <input type="date" class="form-control"
                                                                                id="tanggal-{{ $program->id }}" name="tanggal" required>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label for="catatan-{{ $program->id }}"
                                                                                class="form-label">Catatan</label>
                                                                            <textarea class="form-control" id="catatan-{{ $program->id }}"
                                                                                name="catatan" required></textarea>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label for="persentase-{{ $program->id }}"
                                                                                class="form-label">Persentase (%)</label>
                                                                            <input type="number" class="form-control"
                                                                                id="persentase-{{ $program->id }}" name="persentase" min="0"
                                                                                max="100" required>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label for="status-{{ $program->id }}"
                                                                                class="form-label">Status</label>
                                                                            <select name="status" id="status-{{ $program->id }}"
                                                                                class="form-select" required>
                                                                                <option value="Belum Selesai" {{ $program->status == 'Belum Selesai' ? 'selected' : '' }}>Belum Selesai</option>
                                                                                <option value="Proses" {{ $program->status == 'Proses' ? 'selected' : '' }}>Proses</option>
                                                                                <option value="Selesai" {{ $program->status == 'Selesai' ? 'selected' : '' }}>Selesai</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>

                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary"
                                                                            data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-primary">Simpan
                                                                            Progres</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- ========================= -->
                                                    <!-- ✏ MODAL EDIT PROGRAM -->
                                                    <!-- ========================= -->
                                                    <div class="modal fade" id="editModal{{ $program->id }}" tabindex="-1"
                                                        aria-labelledby="editModalLabel{{ $program->id }}" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="editModalLabel{{ $program->id }}">Edit
                                                                        Program Kerja</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                        aria-label="Close"></button>
                                                                </div>

                                                                <form action="{{ route('program-kerja.update', $program->id) }}"
                                                                    method="POST">
                                                                    @csrf
                                                                    @method('PUT')

                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Deskripsi</label>
                                                                            <input type="text" name="deskripsi" class="form-control"
                                                                                value="{{ $program->deskripsi }}">
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label">Deskripsi Lengkap</label>
                                                                            <textarea name="deskripsi_lengkap" class="form-control"
                                                                                rows="3">{{ $program->deskripsi_lengkap }}</textarea>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label">Penanggung Jawab</label>
                                                                            <input type="text" name="penanggung_jawab" class="form-control"
                                                                                value="{{ $program->penanggung_jawab }}">
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label">Vendor</label>
                                                                            <select name="vendor_option"
                                                                                id="vendorOptionEdit{{ $program->id }}"
                                                                                class="form-select vendorOptionEdit"
                                                                                data-id="{{ $program->id }}">
                                                                                <option value="tidak_ada" {{ $program->vendor == 'Tidak Ada Vendor' ? 'selected' : '' }}>Tidak Ada Vendor</option>
                                                                                <option value="ada" {{ in_array($program->vendor, ['Vendor Internal', 'Vendor Eksternal', 'Vendor IT Supreg Jawa']) ? 'selected' : '' }}>Ada Vendor</option>
                                                                            </select>
                                                                        </div>

                                                                        <div class="mb-3" id="vendorDetailDivEdit{{ $program->id }}"
                                                                            style="{{ in_array($program->vendor, ['Vendor Internal', 'Vendor Eksternal', 'Vendor IT Supreg Jawa']) ? '' : 'display:none;' }}">
                                                                            <label class="form-label">Pilih Jenis Vendor</label>
                                                                            <select name="vendor" id="vendorTypeEdit{{ $program->id }}"
                                                                                class="form-select">
                                                                                <option value="">Pilih Jenis Vendor</option>
                                                                                <option value="Vendor Internal" {{ $program->vendor == 'Vendor Internal' ? 'selected' : '' }}>Vendor Internal</option>
                                                                                <option value="Vendor Eksternal" {{ $program->vendor == 'Vendor Eksternal' ? 'selected' : '' }}>Vendor Eksternal</option>
                                                                                <option value="Vendor IT Supreg Jawa" {{ $program->vendor == 'Vendor IT Supreg Jawa' ? 'selected' : '' }}>Vendor IT Supreg Jawa</option>
                                                                            </select>
                                                                        </div>

                                                                        <div class="mb-3" id="vendorNameDivEdit{{ $program->id }}"
                                                                            style="{{ in_array($program->vendor, ['Vendor Internal', 'Vendor Eksternal', 'Vendor IT Supreg Jawa']) ? '' : 'display:none;' }}">
                                                                            <label class="form-label">Nama Vendor</label>
                                                                            <input type="text" name="nama_vendor"
                                                                                id="vendorNameEdit{{ $program->id }}" class="form-control"
                                                                                value="{{ old('nama_vendor', $program->nama_vendor) }}"
                                                                                placeholder="Masukkan nama vendor...">
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label">Target Waktu</label>
                                                                            <input type="date" name="target_waktu" class="form-control"
                                                                                value="{{ $program->target_waktu }}">
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label">Kategori</label>
                                                                            <select name="kategori" class="form-select kategoriEdit"
                                                                                data-id="{{ $program->id }}" required>
                                                                                <option value="Pemeliharaan" {{ $program->kategori == 'Pemeliharaan' ? 'selected' : '' }}>
                                                                                    Pemeliharaan</option>
                                                                                <option value="Perlengkapan Perangkat" {{ $program->kategori == 'Perlengkapan Perangkat' ? 'selected' : '' }}>Perlengkapan Perangkat</option>
                                                                                <option value="Komunikasi Data" {{ $program->kategori == 'Komunikasi Data' ? 'selected' : '' }}>Komunikasi Data</option>
                                                                                <option value="Non-Finansial" {{ $program->kategori == 'Non-Finansial' ? 'selected' : '' }}>Non-Finansial</option>
                                                                            </select>
                                                                        </div>

                                                                        <div class="mb-3 danaWrapper" id="danaWrapper{{ $program->id }}">
                                                                            <label class="form-label">Dana (Rp)</label>
                                                                            <input type="text" id="danaDisplay{{ $program->id }}"
                                                                                class="form-control"
                                                                                value="{{ number_format($program->dana ?? 0, 0, ',', '.') }}">
                                                                            <input type="hidden" name="dana"
                                                                                id="danaHidden{{ $program->id }}"
                                                                                value="{{ $program->dana ?? 0 }}">
                                                                        </div>
                                                                    </div>

                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary"
                                                                            data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-primary">Simpan
                                                                            Perubahan</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- ========================= -->
                                                    <!-- 📜 MODAL DETAIL PROGRAM -->
                                                    <!-- ========================= -->
                                                    <div class="modal fade" id="detailProgram{{ $program->id }}" tabindex="-1"
                                                        aria-labelledby="detailProgramLabel{{ $program->id }}" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="detailProgramLabel{{ $program->id }}">
                                                                        Detail Progres Kerja - {{ $program->deskripsi }}
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                        aria-label="Close"></button>
                                                                </div>

                                                                <div class="modal-body">
                                                                    <h6 class="fw-bold">Deskripsi Lengkap</h6>
                                                                    <p>{{ $program->deskripsi_lengkap ?? 'Belum ada deskripsi detail.' }}
                                                                    </p>
                                                                    <hr>

                                                                    <h6 class="fw-bold">Penanggung Jawab</h6>
                                                                    <p>{{ $program->penanggung_jawab ?? 'Belum ditentukan.' }}</p>
                                                                    <hr>

                                                                    <h6 class="fw-bold">Vendor</h6>
                                                                    @if(!empty($program->vendor) && $program->vendor != 'Tidak Ada Vendor')
                                                                        <p>Jenis Vendor: <strong>{{ $program->vendor }}</strong></p>
                                                                        <p>Nama Vendor: <strong>{{ $program->nama_vendor ?? '-' }}</strong></p>

                                                                    @else
                                                                        <p>Tidak ada vendor.</p>
                                                                    @endif
                                                                    <hr>

                                                                    <h6 class="fw-bold">Kategori</h6>
                                                                    <p>{{ $program->kategori ?? '-' }}</p>
                                                                    <hr>

                                                                    <h6 class="fw-bold">Target Waktu</h6>
                                                                    <p>{{ \Carbon\Carbon::parse($program->target_waktu)->format('d-m-Y') ?? '-' }}
                                                                    </p>
                                                                    <hr>

                                                                    <h6 class="fw-bold">Progres Kerja</h6>
                                                                    @if($program->progressKerjas->count())
                                                                        <ul>
                                                                            @foreach($program->progressKerjas as $progres)
                                                                                <li>
                                                                                    <strong>{{ \Carbon\Carbon::parse($progres->tanggal)->format('d-m-Y') }}</strong>
                                                                                    –
                                                                                    {{ $progres->catatan }} ({{ $progres->persentase }}%)
                                                                                </li>
                                                                            @endforeach
                                                                        </ul>
                                                                    @else
                                                                        <p>Belum ada progres yang tercatat.</p>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- ========================= -->
                                                    <!-- 🧠 SCRIPT VENDOR + DANA -->
                                                    <!-- ========================= -->
                                                    <script>
                                                        document.addEventListener('DOMContentLoaded', function () {
                                                            const id = "{{ $program->id }}";

                                                            // ==========================
                                                            // 🔹 Elemen untuk vendor
                                                            // ==========================
                                                            const vendorOption = document.getElementById(vendorOptionEdit${ id });
                                                            const vendorDetailDiv = document.getElementById(vendorDetailDivEdit${ id });
                                                            const vendorNameDiv = document.getElementById(vendorNameDivEdit${ id });
                                                            const vendorTypeSelect = document.getElementById(vendorTypeEdit${ id });
                                                            const vendorNameInput = document.getElementById(vendorNameEdit${ id });

                                                            // Saat memilih ada/tidak ada vendor
                                                            if (vendorOption) {
                                                                vendorOption.addEventListener('change', function () {
                                                                    if (this.value === 'ada') {
                                                                        vendorDetailDiv.style.display = 'block';
                                                                        vendorNameDiv.style.display = 'block';
                                                                    } else {
                                                                        vendorDetailDiv.style.display = 'none';
                                                                        vendorNameDiv.style.display = 'none';
                                                                        vendorTypeSelect.value = '';
                                                                        vendorNameInput.value = '';
                                                                    }
                                                                });
                                                            }

                                                            // ==========================
                                                            // 🔹 Elemen untuk kategori & dana
                                                            // ==========================
                                                            const kategoriSelect = document.querySelector(#editModal${ id }.kategoriEdit);
                                                            const danaWrapper = document.getElementById(danaWrapper${ id });
                                                            const danaDisplay = document.getElementById(danaDisplay${ id });
                                                            const danaHidden = document.getElementById(danaHidden${ id });

                                                            // Format input dana otomatis
                                                            if (danaDisplay) {
                                                                danaDisplay.addEventListener('input', function () {
                                                                    let value = danaDisplay.value.replace(/\D/g, ''); // Hanya angka
                                                                    if (value === '') {
                                                                        danaDisplay.value = '';
                                                                        danaHidden.value = '';
                                                                        return;
                                                                    }
                                                                    danaDisplay.value = new Intl.NumberFormat('id-ID').format(value);
                                                                    danaHidden.value = value;
                                                                });
                                                            }

                                                            // Sembunyikan input dana jika kategori = "Non-Finansial"
                                                            function toggleDana() {
                                                                if (kategoriSelect && kategoriSelect.value === 'Non-Finansial') {
                                                                    danaWrapper.style.display = 'none';
                                                                    danaHidden.value = 0;
                                                                } else {
                                                                    danaWrapper.style.display = 'block';
                                                                }
                                                            }

                                                            if (kategoriSelect) {
                                                                kategoriSelect.addEventListener('change', toggleDana);
                                                                toggleDana(); // Jalankan saat modal dibuka
                                                            }
                                                        });
                                                    </script>

                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <style>
                            /* =========================
                            💠 RESPONSIVE TABEL & MODAL
                            ========================= */

                            /* Wrapper table */
                            .table-responsive {
                                overflow-x: auto;
                                -webkit-overflow-scrolling: touch;
                                border-radius: 10px;
                                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
                            }

                            /* Tabel dasar */
                            #programTable {
                                width: 100%;
                                min-width: 900px;
                                border-collapse: separate;
                                border-spacing: 0;
                                background: #fff;
                            }

                            #programTable thead th {
                                font-size: 14px;
                                text-transform: uppercase;
                                letter-spacing: 0.03em;
                                white-space: nowrap;
                            }

                            #programTable td {
                                vertical-align: middle;
                                white-space: nowrap;
                            }

                            /* Hover & border halus */
                            #programTable tr:hover {
                                background-color: #f8f9fa;
                                transition: background 0.2s ease;
                            }

                            /* Tombol kecil di tabel */
                            #programTable .btn {
                                font-size: 13px;
                                border-radius: 6px;
                                transition: all 0.25s ease-in-out;
                            }

                            #programTable .btn:hover {
                                transform: scale(1.05);
                            }

                            /* ===================================
                              📱 RESPONSIVE BEHAVIOR UNTUK MOBILE
                             =================================== */

                            /* Ketika layar < 768px */
                            @media (max-width: 768px) {
                                #programTable thead {
                                    display: none;
                                }

                                #programTable,
                                #programTable tbody,
                                #programTable tr,
                                #programTable td {
                                    display: block;
                                    width: 100%;
                                }

                                #programTable tr {
                                    background: #fff;
                                    margin-bottom: 12px;
                                    border-radius: 8px;
                                    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
                                    padding: 10px;
                                }

                                #programTable td {
                                    text-align: left;
                                    padding: 8px 12px;
                                    position: relative;
                                }

                                #programTable td::before {
                                    content: attr(data-label);
                                    display: block;
                                    font-weight: 600;
                                    color: #0d6efd;
                                    margin-bottom: 4px;
                                }

                                /* Tombol di bawah baris agar tidak bertumpuk */
                                #programTable td .btn {
                                    width: 100%;
                                    margin-top: 6px;
                                }

                                #programTable td.text-center {
                                    text-align: left !important;
                                }
                            }

                            /* ===================================
                               🪶 MODAL STYLING AGAR LEBIH HALUS
                            =================================== */

                            .modal-content {
                                border-radius: 14px;
                                border: none;
                                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                            }

                            .modal-header {
                                background: linear-gradient(90deg, #0d6efd, #4c8efc);
                                color: #fff;
                                border-bottom: none;
                            }

                            .modal-title {
                                font-size: 16px;
                                font-weight: 600;
                            }

                            .modal-body {
                                font-size: 14px;
                                line-height: 1.6;
                            }

                            .modal-footer {
                                border-top: none;
                                background-color: #f8f9fa;
                            }

                            /* Efek transisi lembut */
                            .modal.fade .modal-dialog {
                                transform: translateY(-15px);
                                transition: transform 0.25s ease-out, opacity 0.25s ease-out;
                            }

                            .modal.show .modal-dialog {
                                transform: translateY(0);
                                opacity: 1;
                            }
                        </style>

                        <script>
                            // Tambahkan label otomatis agar versi mobile bisa menampilkan kolom
                            document.addEventListener("DOMContentLoaded", function () {
                                const headers = Array.from(document.querySelectorAll("#programTable thead th"))
                                    .map(th => th.textContent.trim());
                                document.querySelectorAll("#programTable tbody tr").forEach(row => {
                                    row.querySelectorAll("td").forEach((td, i) => {
                                        td.setAttribute("data-label", headers[i] || "");
                                    });
                                });
                            });
                        </script>

                        <script>
                            document.getElementById("filterKategori").addEventListener("change", function () {
                                let filter = this.value;

                                // Filter kotak detail anggaran
                                document.querySelectorAll(".anggaran-card").forEach(card => {
                                    let kategori = card.getAttribute("data-kategori");
                                    if (filter === "all" || kategori === filter) {
                                        card.style.display = "";
                                    } else {
                                        card.style.display = "none";
                                    }
                                });

                                // Filter tabel program kerja
                                document.querySelectorAll("tbody tr").forEach(row => {
                                    let kategori = row.getAttribute("data-kategori");

                                    // Kalau baris utama (punya data-kategori)
                                    if (kategori) {
                                        if (filter === "all" || kategori === filter) {
                                            row.style.display = "";
                                            // Pastikan baris detail terkait tetap ikut
                                            let detailId = row.querySelector("[data-bs-target]")?.getAttribute("data-bs-target");
                                            if (detailId) {
                                                let detailRow = document.querySelector(detailId);
                                                if (detailRow) detailRow.style.display = "";
                                            }
                                        } else {
                                            row.style.display = "none";
                                            // Sembunyikan baris detail terkait
                                            let detailId = row.querySelector("[data-bs-target]")?.getAttribute("data-bs-target");
                                            if (detailId) {
                                                let detailRow = document.querySelector(detailId);
                                                if (detailRow) detailRow.style.display = "none";
                                            }
                                        }
                                    }
                                });
                            });
                        </script>

                        <!-- Tombol Aksi -->
                        <div @class(['d-flex', 'justify-content-end', 'gap-2', 'mt-3'])>
                            <button @class(['btn', 'btn-secondary'])>Cetak</button>

                            <!-- Tombol buka modal tambah -->
                            <button @class(['btn', 'btn-primary']) data-bs-toggle="modal" data-bs-target="#modalTambah">
                                + Tambah Program Baru
                            </button>
                        </div>

                        <!-- Modal Tambah Program -->
                        <div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="modalTambahLabel"
                            aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form action="{{ route('program-kerja.store') }}" method="POST">
                                        @csrf
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modalTambahLabel">Tambah Program Kerja Baru</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>

                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Deskripsi</label>
                                                <input type="text" name="deskripsi" class="form-control" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Deskripsi Lengkap</label>
                                                <textarea name="deskripsi_lengkap" class="form-control"
                                                    rows="3"></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Penanggung Jawab</label>
                                                <input type="text" name="penanggung_jawab" class="form-control"
                                                    required>
                                            </div>

                                            <!-- Vendor -->
                                            <div class="mb-3">
                                                <label class="form-label">Vendor</label>
                                                <select id="vendorOption" name="vendor_option" class="form-select"
                                                    required>
                                                    <option value="" disabled selected>Pilih Vendor</option>
                                                    <option value="tidak_ada">Tidak Ada Vendor</option>
                                                    <option value="ada">Ada Vendor</option>
                                                </select>
                                            </div>

                                            <div class="mb-3" id="vendorTypeDiv" style="display: none;">
                                                <label class="form-label">Jenis Vendor</label>
                                                <select name="vendor" id="vendorType" class="form-select">
                                                    <option value="" disabled selected>Pilih Jenis Vendor</option>
                                                    <option value="Vendor Eksternal">Vendor Eksternal</option>
                                                    <option value="Vendor Internal">Vendor Internal</option>
                                                    <option value="Vendor IT Supreg Jawa">Vendor IT Supreg Jawa</option>
                                                </select>
                                            </div>

                                            <!-- Input Nama Vendor -->
                                            <div class="mb-3" id="vendorNameDiv" style="display: none;">
                                                <label class="form-label">Nama Vendor</label>
                                                <input type="text" name="nama_vendor" id="vendorName"
                                                    class="form-control" placeholder="Masukkan nama vendor">
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Waktu Dimulai</label>
                                                    <input type="date" name="waktu_dimulai" class="form-control"
                                                        required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Target Waktu</label>
                                                    <input type="date" name="target_waktu" class="form-control"
                                                        required>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Kategori</label>
                                                <select id="kategoriSelect" name="kategori" class="form-select"
                                                    required>
                                                    <option value="" disabled selected>Pilih Kategori</option>
                                                    @foreach ($anggarans as $anggaran)
                                                        <option value="{{ $anggaran->nama }}" data-id="{{ $anggaran->id }}"
                                                            data-total="{{ $anggaran->total }}"
                                                            data-tersisa="{{ $anggaran->tersisa }}">
                                                            {{ $anggaran->nama }}
                                                        </option>
                                                    @endforeach
                                                    <option value="Non-Finansial">Non-Finansial</option>
                                                </select>
                                            </div>

                                            <!-- Anggaran -->
                                            <div class="mb-3" id="anggaranWrapper">
                                                <label class="form-label">Anggaran</label>
                                                <input type="text" id="anggaranInput" class="form-control" readonly>
                                                <input type="hidden" name="anggaran_id" id="anggaranId">
                                            </div>

                                            <!-- Dana -->
                                            <div class="mb-3" id="danaWrapper">
                                                <label class="form-label">Dana (Rp)</label>
                                                <input type="text" id="danaDisplay" class="form-control"
                                                    placeholder="Masukkan jumlah dana">
                                                <input type="hidden" name="dana" id="danaHidden">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select">
                                                    <option value="" disabled selected>Pilih Status</option>
                                                    <option value="Belum Selesai">Belum Selesai</option>
                                                    <option value="Proses">Proses</option>
                                                    <option value="Selesai">Selesai</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary"
                                                data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const vendorOption = document.getElementById('vendorOption');
                                const vendorTypeDiv = document.getElementById('vendorTypeDiv');
                                const vendorType = document.getElementById('vendorType');
                                const vendorNameDiv = document.getElementById('vendorNameDiv');
                                const vendorName = document.getElementById('vendorName');

                                vendorOption.addEventListener('change', function () {
                                    if (this.value === 'ada') {
                                        // Jika ada vendor
                                        vendorTypeDiv.style.display = 'block';
                                        vendorType.required = true;
                                    } else {
                                        // Jika tidak ada vendor
                                        vendorTypeDiv.style.display = 'none';
                                        vendorNameDiv.style.display = 'none';
                                        vendorType.required = false;
                                        vendorName.required = false;
                                        vendorType.selectedIndex = 0;
                                        vendorName.value = '';

                                        // Tambahkan input hidden agar backend tidak null
                                        let hiddenVendor = document.getElementById('hiddenVendor');
                                        let hiddenNamaVendor = document.getElementById('hiddenNamaVendor');

                                        if (!hiddenVendor) {
                                            hiddenVendor = document.createElement('input');
                                            hiddenVendor.type = 'hidden';
                                            hiddenVendor.name = 'vendor';
                                            hiddenVendor.id = 'hiddenVendor';
                                            document.forms[0].appendChild(hiddenVendor);
                                        }

                                        if (!hiddenNamaVendor) {
                                            hiddenNamaVendor = document.createElement('input');
                                            hiddenNamaVendor.type = 'hidden';
                                            hiddenNamaVendor.name = 'nama_vendor';
                                            hiddenNamaVendor.id = 'hiddenNamaVendor';
                                            document.forms[0].appendChild(hiddenNamaVendor);
                                        }

                                        hiddenVendor.value = 'Tidak Ada Vendor';
                                        hiddenNamaVendor.value = '-';
                                    }
                                });

                                vendorType.addEventListener('change', function () {
                                    if (
                                        this.value === 'Vendor Internal' ||
                                        this.value === 'Vendor Eksternal' ||
                                        this.value === 'Vendor IT Supreg Jawa'
                                    ) {
                                        vendorNameDiv.style.display = 'block';
                                        vendorName.required = true;
                                    } else {
                                        vendorNameDiv.style.display = 'none';
                                        vendorName.required = false;
                                        vendorName.value = '';
                                    }
                                });
                            });
                        </script>

                        <!-- ===== SCRIPT SECTION ===== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const kategoriSelect = document.getElementById('kategoriSelect');
    const anggaranInput = document.getElementById('anggaranInput');
    const anggaranId = document.getElementById('anggaranId');
    const danaDisplay = document.getElementById('danaDisplay');
    const danaHidden = document.getElementById('danaHidden');
    const anggaranWrapper = document.getElementById('anggaranWrapper');
    const danaWrapper = document.getElementById('danaWrapper');

    // Format Rupiah helper
    function formatRupiah(angka) {
        if (!angka) return "Rp 0";
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(angka);
    }

    // Saat kategori berubah
    kategoriSelect.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        const kategoriNama = selected.value;
        const anggaranIdVal = selected.getAttribute('data-id');
        const total = selected.getAttribute('data-total');
        const tersisa = selected.getAttribute('data-tersisa');

        if (kategoriNama === "Non-Finansial") {
            // Sembunyikan bagian anggaran & dana
            anggaranWrapper.style.display = "none";
            danaWrapper.style.display = "none";
            anggaranInput.value = "-";
            anggaranId.value = "";
            danaHidden.value = 0;
            danaDisplay.value = "";
        } else {
            // Tampilkan info anggaran
            anggaranWrapper.style.display = "block";
            danaWrapper.style.display = "block";
            anggaranInput.value = `${kategoriNama} - Total: ${formatRupiah(total)} | Tersisa: ${formatRupiah(tersisa)}`;
            anggaranId.value = anggaranIdVal;
        }
    });

    // Format input dana ke ribuan otomatis
    danaDisplay.addEventListener('input', function () {
        const value = this.value.replace(/\D/g, '');
        this.value = new Intl.NumberFormat('id-ID').format(value);
        danaHidden.value = value;
    });

    // Logika vendor
    const vendorOption = document.getElementById('vendorOption');
    const vendorDetailDiv = document.getElementById('vendorDetailDiv');
    if (vendorOption && vendorDetailDiv) {
        vendorOption.addEventListener('change', function () {
            if (this.value === 'ada') {
                vendorDetailDiv.style.display = 'block';
            } else {
                vendorDetailDiv.style.display = 'none';
                vendorDetailDiv.querySelector('select').selectedIndex = 0;
            }
        });
    }
});
</script>


                        <!-- SweetAlert2 -->
                        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                        <!-- Chart.js -->
                        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

                        <script>
                            const kategori = @json($kategori);
                            const anggarans = @json($anggarans);
                            const programKerjas = @json($programKerjas);

                            const total = kategori.map(k => anggarans[k]?.total ?? 0);
                            const terserap = kategori.map(k => anggarans[k]?.terserap ?? 0);
                            const tersisa = total.map((t, i) => t - terserap[i]);

                            function roundUpToTenMillion(value) {
                                return Math.ceil(value / 10000000) * 10000000;
                            }

                            const allData = [...total, ...terserap, ...tersisa];
                            let maxData = roundUpToTenMillion(Math.max(...allData));

                            // ---------- Chart 100% ----------
                            const datasets100 = [
                                { label: 'Total Anggaran', data: total, backgroundColor: '#0d6efd' },
                                { label: 'Terserap', data: terserap, backgroundColor: '#198754' },
                                { label: 'Tersisa', data: tersisa, backgroundColor: '#ffc107' }
                            ];

                            const dataLabelPlugin = {
                                id: 'dataLabelPlugin',
                                afterDatasetsDraw(chart) {
                                    const { ctx } = chart;
                                    chart.data.datasets.forEach((dataset, i) => {
                                        const meta = chart.getDatasetMeta(i);
                                        meta.data.forEach((bar, index) => {
                                            const value = dataset.data[index];
                                            if (value > 0) {
                                                ctx.fillStyle = "#000";
                                                ctx.font = "12px Arial";
                                                ctx.textAlign = "center";
                                                ctx.fillText("Rp " + value.toLocaleString('id-ID'), bar.x, bar.y - 5);
                                            }
                                        });
                                    });
                                }
                            };

                            const ctx100 = document.getElementById('barChart100');
                            const chart100 = new Chart(ctx100, {
                                type: 'bar',
                                data: { labels: kategori, datasets: datasets100 },
                                options: {
                                    responsive: true,
                                    animation: {
                                        duration: 1000,
                                        easing: 'easeOutCubic'
                                    },
                                    plugins: { legend: { position: 'bottom' } },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: maxData,
                                            ticks: {
                                                stepSize: 10000000,
                                                callback: value => 'Rp ' + value.toLocaleString('id-ID')
                                            }
                                        }
                                    },
                                    onClick: (e, elements) => {
                                        if (elements.length > 0) {
                                            const idx = elements[0].index;
                                            const namaKategori = kategori[idx];
                                            const anggaran = anggarans[namaKategori];
                                            if (!anggaran) return;

                                            const totalVal = anggaran.total ?? 0;
                                            const terserapVal = anggaran.terserap ?? 0;
                                            const tersisaVal = totalVal - terserapVal;
                                            const programlist = programKerjas.filter(p => p.kategori === namaKategori);

                                            let detailHTML = '';
                                            if (programlist.length > 0) {
                                                detailHTML = `
                        <hr>
                        <h6><strong>Detail Terserap:</strong></h6>
                        ${programlist.map(p => `
                            <div style="padding:10px;border:1px solid #dee2e6;border-radius:8px;background:#f8f9fa;margin-bottom:8px;">
                                <div style="font-weight:600;color:#198754;">${p.deskripsi}</div>
                                <div style="font-size:14px;color:#555;">
                                    Dana: <strong>Rp ${Number(p.dana).toLocaleString('id-ID')}</strong>
                                </div>
                            </div>
                        `).join('')}
                    `;
                                            } else {
                                                detailHTML = `<p style="margin-top:10px"><em>Tidak ada data program untuk kategori ini.</em></p>`;
                                            }

                                            Swal.fire({
                                                title: `Detail Anggaran – ${namaKategori}`,
                                                html: `
                        <p><strong>Total:</strong> Rp ${totalVal.toLocaleString('id-ID')}</p>
                        <p><strong>Terserap:</strong> Rp ${terserapVal.toLocaleString('id-ID')}</p>
                        <p><strong>Tersisa:</strong> Rp ${tersisaVal.toLocaleString('id-ID')}</p>
                        <hr>
                        <canvas id="popupChart100" style="width:100%;height:250px"></canvas>
                        ${detailHTML}`,
                                                width: 600,
                                                didOpen: () => {
                                                    new Chart(document.getElementById("popupChart100"), {
                                                        type: "doughnut",
                                                        data: {
                                                            labels: ["Terserap", "Tersisa"],
                                                            datasets: [{
                                                                data: [terserapVal, tersisaVal],
                                                                backgroundColor: ["#198754", "#ffc107"]
                                                            }]
                                                        },
                                                        options: {
                                                            responsive: true,
                                                            animation: {
                                                                animateRotate: true,
                                                                animateScale: true,
                                                                duration: 1200,
                                                                easing: 'easeOutQuart'
                                                            },
                                                            plugins: { legend: { position: 'bottom' } }
                                                        }
                                                    });
                                                }
                                            });
                                        }
                                    }
                                },
                                plugins: [dataLabelPlugin]
                            });

                            // ---------- Chart 70% (dinamis) ----------
                            let chart70;
                            let lastFiltered = { persen: 70, total: [], terserap: [], tersisa: [] };

                            function renderChart(persen, updateCards = true) {
                                const faktor = persen / 100;
                                const totalX = total.map(t => Math.round(t * faktor));
                                const terserapX = terserap.map(t => Math.round(t * faktor));
                                const tersisaX = tersisa.map(t => Math.round(t * faktor));

                                lastFiltered = { persen, total: totalX, terserap: terserapX, tersisa: tersisaX };
                                const maxX = roundUpToTenMillion(Math.max(...totalX, ...terserapX, ...tersisaX));

                                const datasetsX = [
                                    { label: `Total Anggaran (${persen}%)`, data: totalX, backgroundColor: '#0d6efd' },
                                    { label: `Terserap (${persen}%)`, data: terserapX, backgroundColor: '#198754' },
                                    { label: `Tersisa (${persen}%)`, data: tersisaX, backgroundColor: '#ffc107' }
                                ];

                                if (chart70) chart70.destroy();

                                chart70 = new Chart(document.getElementById('barChart70'), {
                                    type: 'bar',
                                    data: { labels: kategori, datasets: datasetsX },
                                    options: {
                                        responsive: true,
                                        animation: {
                                            duration: 1000,
                                            easing: 'easeOutCubic'
                                        },
                                        plugins: { legend: { position: 'bottom' } },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                max: maxX,
                                                ticks: {
                                                    stepSize: 10000000,
                                                    callback: value => 'Rp ' + value.toLocaleString('id-ID')
                                                }
                                            }
                                        }
                                    },
                                    plugins: [dataLabelPlugin]
                                });

                                document.getElementById('chartTitle').textContent = `Diagram Anggaran (${persen}%)`;
                            }

                            // Tombol update
                            function updateChart() {
                                let persen = parseInt(document.getElementById('filterPersen').value);
                                if (isNaN(persen) || persen <= 0) persen = 70;
                                renderChart(persen);
                            }

                            document.addEventListener('DOMContentLoaded', () => {
                                renderChart(70, false);
                                document.getElementById('filterPersen').value = 70;
                            });
                        </script>

</body>

</html>
