<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\PrTalent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GlobalSearchController extends Controller
{
    /**
     * Search across multiple models and categories
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $query = $request->query('q');

        if (empty($query) || strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => [
                    'employees' => [],
                    'programs' => [],
                    'episodes' => [],
                    'talents' => [],
                ]
            ]);
        }

        $limit = 5; // Limit per category

        // 1. Search Employees
        $employees = Employee::where('nama_lengkap', 'like', "%{$query}%")
            ->orWhere('nik', 'like', "%{$query}%")
            ->orWhere('jabatan_saat_ini', 'like', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->nama_lengkap,
                    'subtitle' => $item->jabatan_saat_ini . " (" . $item->nik . ")",
                    'type' => 'employee',
                    'route' => '/lihat-data-pegawai', // Default route for viewing employees
                    'icon' => 'fas fa-user-tie'
                ];
            });

        // 2. Search Programs
        $programs = PrProgram::where('name', 'like', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->name,
                    'subtitle' => 'Program - ' . $item->status,
                    'type' => 'program',
                    'route' => '/program-regular', // Should ideally point to detail if available
                    'icon' => 'fas fa-tv'
                ];
            });

        // 3. Search Episodes
        $episodes = PrEpisode::with('program')
            ->where('title', 'like', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'subtitle' => ($item->program ? $item->program->name : 'N/A') . " - Episode " . $item->episode_number,
                    'type' => 'episode',
                    'route' => '/program-regular/' . ($item->program_id ?? 'all'),
                    'icon' => 'fas fa-film'
                ];
            });

        // 4. Search Talents
        $talents = PrTalent::where('name', 'like', "%{$query}%")
            ->orWhere('expertise', 'like', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->name,
                    'subtitle' => $item->type . " - " . $item->expertise,
                    'type' => 'talent',
                    'route' => '/program-regular', // General route for PR
                    'icon' => 'fas fa-star'
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'employees' => $employees,
                'programs' => $programs,
                'episodes' => $episodes,
                'talents' => $talents,
            ],
            'total' => count($employees) + count($programs) + count($episodes) + count($talents)
        ]);
    }
}
