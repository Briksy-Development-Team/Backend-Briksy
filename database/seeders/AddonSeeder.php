<?php

namespace Database\Seeders;

use App\Models\Addon;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    public function run(): void
    {
        $addons = [
            ['name' => 'Extra Properties', 'slug' => 'extra-properties', 'feature_key' => 'property_management', 'pricing_type' => 'monthly', 'monthly_price' => 10, 'yearly_price' => 100, 'one_time_price' => null, 'limits' => ['properties' => 25], 'sort_order' => 1],
            ['name' => 'Extra Employees', 'slug' => 'extra-employees', 'feature_key' => 'user_management', 'pricing_type' => 'monthly', 'monthly_price' => 8, 'yearly_price' => 80, 'one_time_price' => null, 'limits' => ['employees' => 5], 'sort_order' => 2],
            ['name' => 'Map View', 'slug' => 'map-view', 'feature_key' => 'property_management', 'pricing_type' => 'monthly', 'monthly_price' => 15, 'yearly_price' => 150, 'one_time_price' => null, 'limits' => null, 'sort_order' => 3],
            ['name' => 'Email Notifications', 'slug' => 'email-notifications', 'feature_key' => 'notifications', 'pricing_type' => 'monthly', 'monthly_price' => 12, 'yearly_price' => 120, 'one_time_price' => null, 'limits' => null, 'sort_order' => 4],
            ['name' => 'SMS Notifications', 'slug' => 'sms-notifications', 'feature_key' => 'notifications', 'pricing_type' => 'monthly', 'monthly_price' => 20, 'yearly_price' => 200, 'one_time_price' => null, 'limits' => null, 'sort_order' => 5],
            ['name' => 'AI Features', 'slug' => 'ai-features', 'feature_key' => 'ai', 'pricing_type' => 'monthly', 'monthly_price' => 25, 'yearly_price' => 250, 'one_time_price' => null, 'limits' => null, 'sort_order' => 6],
            ['name' => 'Priority Support', 'slug' => 'priority-support', 'feature_key' => 'support', 'pricing_type' => 'monthly', 'monthly_price' => 30, 'yearly_price' => 300, 'one_time_price' => null, 'limits' => null, 'sort_order' => 7],
            ['name' => 'Custom Branding', 'slug' => 'custom-branding', 'feature_key' => 'branding', 'pricing_type' => 'monthly', 'monthly_price' => 18, 'yearly_price' => 180, 'one_time_price' => null, 'limits' => null, 'sort_order' => 8],
            ['name' => 'Extra Storage', 'slug' => 'extra-storage', 'feature_key' => 'storage', 'pricing_type' => 'usage_based', 'monthly_price' => null, 'yearly_price' => null, 'one_time_price' => null, 'limits' => ['gb' => 100], 'sort_order' => 9],
            ['name' => 'Service Management', 'slug' => 'service-management', 'feature_key' => 'service_management', 'pricing_type' => 'monthly', 'monthly_price' => 14, 'yearly_price' => 140, 'one_time_price' => null, 'limits' => null, 'sort_order' => 10],
            ['name' => 'Property Management', 'slug' => 'property-management-addon', 'feature_key' => 'property_management', 'pricing_type' => 'monthly', 'monthly_price' => 14, 'yearly_price' => 140, 'one_time_price' => null, 'limits' => null, 'sort_order' => 11],
        ];

        foreach ($addons as $addon) {
            Addon::withTrashed()->updateOrCreate(
                ['slug' => $addon['slug']],
                [
                    'name' => $addon['name'],
                    'description' => $addon['name'] . ' add-on.',
                    'feature_key' => $addon['feature_key'],
                    'pricing_type' => $addon['pricing_type'],
                    'monthly_price' => $addon['monthly_price'],
                    'yearly_price' => $addon['yearly_price'],
                    'one_time_price' => $addon['one_time_price'],
                    'currency' => 'AUD',
                    'limits' => $addon['limits'],
                    'is_active' => true,
                    'sort_order' => $addon['sort_order'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}
