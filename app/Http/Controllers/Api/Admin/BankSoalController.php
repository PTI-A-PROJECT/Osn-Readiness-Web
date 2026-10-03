<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\Services\BankSoalServiceInterface;
use App\Models\TingkatSeleksi;
use Illuminate\Http\Request;

class BankSoalController
{
    public function __construct(
        private readonly BankSoalServiceInterface $bankSoalService,
    ) {}

    public function kecukupan(Request $request, TingkatSeleksi $tingkat)
    {
        return response()->json([
            'message' => 'OK',
            'data' => $this->bankSoalService->laporan((int) $tingkat->id),
        ]);
    }
}
