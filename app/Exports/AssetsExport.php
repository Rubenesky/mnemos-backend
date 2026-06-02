<?php

namespace App\Exports;

use App\Models\Asset;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AssetsExport
{
    public function download(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Assets');

        // Cabeceras
        $headers = ['ID', 'Nombre', 'Tipo', 'Tamaño (KB)', 'Estado', 'Título IA', 'Tags', 'Subido por', 'Fecha'];
        foreach ($headers as $index => $header) {
            $col = chr(65 + $index);
            $sheet->setCellValue("{$col}1", $header);
        }

        // Estilo cabeceras
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3B82F6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Datos
        $assets = Asset::with(['user', 'metadata'])->latest()->get();
        $row = 2;

        foreach ($assets as $asset) {
            $sheet->setCellValue("A{$row}", $asset->id);
            $sheet->setCellValue("B{$row}", $asset->original_name);
            $sheet->setCellValue("C{$row}", $asset->mime_type);
            $sheet->setCellValue("D{$row}", round($asset->size / 1024, 2));
            $sheet->setCellValue("E{$row}", $asset->status);
            $sheet->setCellValue("F{$row}", $asset->metadata?->title ?? '—');
            $sheet->setCellValue("G{$row}", $asset->metadata?->tags ? implode(', ', $asset->metadata->tags) : '—');
            $sheet->setCellValue("H{$row}", $asset->user->name);
            $sheet->setCellValue("I{$row}", $asset->created_at->format('d/m/Y H:i'));
            $row++;
        }

        // Autoajustar columnas
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Descargar
        $filename = 'assets-' . now()->format('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}