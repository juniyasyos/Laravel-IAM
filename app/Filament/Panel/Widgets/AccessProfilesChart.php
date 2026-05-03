<?php

namespace App\Filament\Panel\Widgets;

use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class AccessProfilesChart extends ApexChartWidget
{
    protected static ?int $sort = 9;

    protected static ?string $heading = 'Role Bundles Distribution';

    protected int|string|array $columnSpan = 'full';

    protected function getOptions(): array
    {
        // Get access profiles with user counts
        $profileData = DB::table('access_profiles')
            ->leftJoin('user_access_profiles', 'access_profiles.id', '=', 'user_access_profiles.access_profile_id')
            ->select(
                'access_profiles.name',
                'access_profiles.is_system',
                DB::raw('COUNT(DISTINCT user_access_profiles.user_id) as user_count')
            )
            ->where('access_profiles.is_active', 1)
            ->groupBy('access_profiles.id', 'access_profiles.name', 'access_profiles.is_system')
            ->orderByDesc('user_count')
            ->get();

        $categories = $profileData->pluck('name')->toArray();
        $userCounts = $profileData->pluck('user_count')->toArray();

        // Separate system and non-system profiles for different colors
        $systemProfiles = $profileData->where('is_system', 1);
        $customProfiles = $profileData->where('is_system', 0);

        // Compute colors based on profile type
        $colors = $profileData->map(function ($profile) {
            return $profile->is_system ? '#EF4444' : '#3B82F6'; // Red for system, Blue for custom
        })->toArray();

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 350,
            ],
            'series' => [
                [
                    'name' => 'Users per Profile',
                    'data' => $userCounts,
                ],
            ],
            'xaxis' => [
                'categories' => $categories,
                'labels' => [
                    'rotate' => -45,
                    'style' => [
                        'fontSize' => '12px',
                    ],
                ],
            ],
            'yaxis' => [
                'title' => [
                    'text' => 'Number of Users',
                ],
            ],
            'colors' => $colors,
            'plotOptions' => [
                'bar' => [
                    'horizontal' => false,
                    'columnWidth' => '60%',
                    'borderRadius' => 4,
                    'dataLabels' => [
                        'position' => 'top',
                    ],
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
                'offsetY' => -20,
                'style' => [
                    'fontSize' => '12px',
                    'colors' => ['#304758'],
                ],
            ],
            'legend' => [
                'show' => true,
                'position' => 'top',
                'horizontalAlign' => 'center',
                'customLegendItems' => ['System Profiles', 'Custom Profiles'],
                'markers' => [
                    'fillColors' => ['#EF4444', '#3B82F6'],
                ],
            ],
            'tooltip' => [
                'y' => [
                    'formatter' => 'function(value) { return value + " users"; }',
                ],
            ],
            'responsive' => [
                [
                    'breakpoint' => 480,
                    'options' => [
                        'chart' => [
                            'width' => 300,
                        ],
                        'xaxis' => [
                            'labels' => [
                                'rotate' => -90,
                            ],
                        ],
                        'legend' => [
                            'position' => 'bottom',
                        ],
                    ],
                ],
            ],
        ];
    }
}
