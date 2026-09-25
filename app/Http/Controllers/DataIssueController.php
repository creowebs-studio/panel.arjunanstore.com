<?php

namespace App\Http\Controllers;

use App\Models\DataIssue;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Data Error (prompt.md §6/§7.3). Menampilkan baris bermasalah dari impor
 * (status tak terpetakan, remark "XX", kunci kosong, dsb.) + tindakan resolusi tercatat.
 */
class DataIssueController extends Controller
{
    public function index(Request $request)
    {
        $q = DataIssue::with(['shipment', 'importRow.batch', 'resolvedBy'])->latest('id');

        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        } else {
            $q->where('status', 'open');
        }
        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }

        return Inertia::render('Issues/Index', [
            'issues'  => $q->paginate(30)->withQueryString(),
            'open'    => DataIssue::where('status', 'open')->count(),
            'filters' => [
                'status' => $status ?? '',
                'type'   => $type ?? '',
            ],
        ]);
    }

    public function update(Request $request, DataIssue $issue)
    {
        $data = $request->validate([
            'action'  => ['required', 'in:resolved,ignored'],
            'note'    => ['nullable', 'string', 'max:500'],
        ]);

        $issue->update([
            'status'      => $data['action'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
            'message'     => $issue->message . (isset($data['note']) ? ' — ' . $data['note'] : ''),
        ]);

        return back()->with('flash', 'Data error ditandai ' . $data['action'] . '.');
    }
}
