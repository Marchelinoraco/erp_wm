<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    protected $guarded = [];

    protected $casts = [
        'acquisition_date'  => 'date',
        'acquisition_cost'  => 'decimal:2',
        'residual_value'    => 'decimal:2',
        'useful_life_years' => 'integer',
        'is_active'         => 'boolean',
    ];

    public const CATEGORIES = [
        'vehicle'   => 'Kendaraan',
        'equipment' => 'Peralatan / Inventaris',
        'building'  => 'Bangunan / Renovasi',
        'other'     => 'Lainnya',
    ];

    // Kelompok penyusutan fiskal — PMK 96/2009, metode garis lurus, tanpa nilai sisa
    public const FISCAL_GROUPS = [
        'kelompok_1'              => ['label' => 'Kelompok 1 — 4 thn (25%)',          'years' => 4],
        'kelompok_2'              => ['label' => 'Kelompok 2 — 8 thn (12,5%)',        'years' => 8],
        'kelompok_3'              => ['label' => 'Kelompok 3 — 16 thn (6,25%)',       'years' => 16],
        'kelompok_4'              => ['label' => 'Kelompok 4 — 20 thn (5%)',          'years' => 20],
        'bangunan_permanen'       => ['label' => 'Bangunan Permanen — 20 thn (5%)',   'years' => 20],
        'bangunan_tidak_permanen' => ['label' => 'Bangunan Tidak Permanen — 10 thn (10%)', 'years' => 10],
    ];

    // Penyusutan tahunan (garis lurus / straight-line)
    public function annualDepreciation(): float
    {
        if ($this->useful_life_years <= 0) return 0.0;
        return (float) (($this->acquisition_cost - $this->residual_value) / $this->useful_life_years);
    }

    // Penyusutan untuk tahun tertentu (diproratakan tahun pertama)
    public function depreciationForYear(int $year): float
    {
        $acqYear  = (int) $this->acquisition_date->year;
        $acqMonth = (int) $this->acquisition_date->month;
        $endYear  = $acqYear + $this->useful_life_years;

        if ($year < $acqYear || $year >= $endYear) return 0.0;

        $annual = $this->annualDepreciation();

        // Tahun perolehan: dihitung dari bulan perolehan s/d Desember
        if ($year === $acqYear) {
            $months = 13 - $acqMonth;
            return round($annual * $months / 12, 2);
        }

        return round($annual, 2);
    }

    // Akumulasi penyusutan s/d akhir tahun tertentu
    public function accumulatedAsOf(int $year): float
    {
        $acqYear        = (int) $this->acquisition_date->year;
        $maxDepreciation = (float) ($this->acquisition_cost - $this->residual_value);
        $accumulated    = 0.0;

        for ($y = $acqYear; $y <= $year; $y++) {
            $accumulated += $this->depreciationForYear($y);
        }

        return round(min($accumulated, $maxDepreciation), 2);
    }

    // Nilai buku (nilai bersih) s/d akhir tahun tertentu
    public function bookValueAsOf(int $year): float
    {
        return round((float) $this->acquisition_cost - $this->accumulatedAsOf($year), 2);
    }

    // Penyusutan fiskal untuk tahun tertentu (garis lurus, tanpa nilai sisa, PMK 96/2009)
    public function fiscalDepreciationForYear(int $year): float
    {
        if (! $this->fiscal_group || ! isset(self::FISCAL_GROUPS[$this->fiscal_group])) {
            return 0.0;
        }

        $fiscalYears = self::FISCAL_GROUPS[$this->fiscal_group]['years'];
        $acqYear     = (int) $this->acquisition_date->year;
        $acqMonth    = (int) $this->acquisition_date->month;
        $endYear     = $acqYear + $fiscalYears;

        if ($year < $acqYear || $year >= $endYear) return 0.0;

        $annual = (float) $this->acquisition_cost / $fiscalYears; // no residual value

        if ($year === $acqYear) {
            $months = 13 - $acqMonth;
            return round($annual * $months / 12, 2);
        }

        return round($annual, 2);
    }
}
