<?php

namespace App\Http\Controllers;

use App\Exports\AssetsExport;

/**
 * Handles export of asset data (e.g. CSV download) via the AssetsExport class.
 *
 * @package App\Http\Controllers
 */
class ExportController extends Controller
{
    public function assets()
    {
        $export = new AssetsExport();
        return $export->download();
    }
}