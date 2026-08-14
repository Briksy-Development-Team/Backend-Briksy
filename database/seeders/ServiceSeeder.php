<?php

namespace Database\Seeders;

use App\Models\OrganizationType;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $types = OrganizationType::all()->keyBy('slug');

        $servicesByType = [
            'real-estate' => [
                ['name' => 'Sales Appraisal', 'slug' => 'sales-appraisal', 'description' => 'Property pricing advice, market appraisal, and sale preparation.'],
                ['name' => 'Property Listing', 'slug' => 'property-listing', 'description' => 'End-to-end listing setup, copywriting, and launch support.'],
                ['name' => 'Open Home Hosting', 'slug' => 'open-home-hosting', 'description' => 'Open home preparation, scheduling, and attendance support.'],
                ['name' => 'Property Styling', 'slug' => 'property-styling', 'description' => 'Staging and presentation guidance to improve buyer appeal.'],
                ['name' => 'Buyer Enquiry Management', 'slug' => 'buyer-enquiry-management', 'description' => 'Lead capture and follow-up for interested buyers.'],
                ['name' => 'Auction Campaigns', 'slug' => 'auction-campaigns', 'description' => 'Auction planning, promotion, and campaign support.'],
                ['name' => 'Vendor Reporting', 'slug' => 'vendor-reporting', 'description' => 'Weekly updates, campaign insights, and seller communication.'],
                ['name' => 'Property Photography Coordination', 'slug' => 'property-photography-coordination', 'description' => 'Photo booking, media coordination, and presentation prep.'],
                ['name' => 'Listing Copywriting', 'slug' => 'listing-copywriting', 'description' => 'Property descriptions, marketing copy, and campaign messaging.'],
                ['name' => 'CRM Setup', 'slug' => 'real-estate-crm-setup', 'description' => 'Pipeline setup, lead tracking, and automation for sales teams.'],
            ],
            'buyers-agent' => [
                ['name' => 'Buyer Briefs', 'slug' => 'buyer-briefs', 'description' => 'Capture buyer requirements, budgets, and timelines.'],
                ['name' => 'Property Shortlists', 'slug' => 'property-shortlists', 'description' => 'Curated property shortlists for client review.'],
                ['name' => 'Search Management', 'slug' => 'search-management', 'description' => 'Track property search progress and saved searches.'],
                ['name' => 'Client Updates', 'slug' => 'client-updates', 'description' => 'Client communication, progress updates, and follow-up.'],
                ['name' => 'Negotiation Support', 'slug' => 'negotiation-support', 'description' => 'Offer strategy, negotiation, and purchase support.'],
                ['name' => 'Off-Market Access', 'slug' => 'off-market-access', 'description' => 'Access to off-market opportunities and agent networks.'],
                ['name' => 'Suburb Research', 'slug' => 'suburb-research', 'description' => 'Area analysis, comparables, and live market intelligence.'],
                ['name' => 'Due Diligence Coordination', 'slug' => 'due-diligence-coordination', 'description' => 'Checks, inspections, and purchase due diligence support.'],
                ['name' => 'Settlement Support', 'slug' => 'settlement-support', 'description' => 'Contract milestones, settlement reminders, and completion tracking.'],
                ['name' => 'Investor Strategy', 'slug' => 'investor-strategy', 'description' => 'Investment criteria, portfolio goals, and acquisition planning.'],
            ],
            'builders' => [
                ['name' => 'Project Planning', 'slug' => 'project-planning', 'description' => 'Scope, milestones, and delivery planning for build projects.'],
                ['name' => 'Tender Management', 'slug' => 'tender-management', 'description' => 'Tender preparation, submission, and review workflows.'],
                ['name' => 'Site Notes', 'slug' => 'site-notes', 'description' => 'Track site observations, progress, and defect notes.'],
                ['name' => 'Client Updates', 'slug' => 'builder-client-updates', 'description' => 'Progress updates and stakeholder communication.'],
                ['name' => 'Project Listings', 'slug' => 'project-listings', 'description' => 'Public-facing project and build listing support.'],
                ['name' => 'Variation Management', 'slug' => 'variation-management', 'description' => 'Variation approvals, pricing changes, and scope tracking.'],
                ['name' => 'Schedule Tracking', 'slug' => 'schedule-tracking', 'description' => 'Milestone tracking, due dates, and delivery visibility.'],
                ['name' => 'Progress Claims', 'slug' => 'progress-claims', 'description' => 'Progress payment claims and stage billing support.'],
                ['name' => 'Defect Management', 'slug' => 'defect-management', 'description' => 'Defect logging, follow-up, and rectification tracking.'],
                ['name' => 'Handover Packs', 'slug' => 'handover-packs', 'description' => 'Completion documents, handover notes, and close-out packs.'],
            ],
            'trades-professionals' => [
                ['name' => 'Electrical', 'slug' => 'electrical', 'description' => 'Electrical installation, repair, and maintenance services.'],
                ['name' => 'Plumbing', 'slug' => 'plumbing', 'description' => 'Plumbing repair, installation, and maintenance services.'],
                ['name' => 'Fencing', 'slug' => 'fencing', 'description' => 'Fence installation and repairs.'],
                ['name' => 'Landscapers', 'slug' => 'landscapers', 'description' => 'Outdoor maintenance, garden care, and site presentation.'],
                ['name' => 'Excavation', 'slug' => 'excavation', 'description' => 'Land clearing, trenching, and excavation services.'],
                ['name' => 'Concreting', 'slug' => 'concreting', 'description' => 'Concrete pours, driveways, slabs, and pathways.'],
                ['name' => 'Carpentry', 'slug' => 'carpentry', 'description' => 'Framing, fit-out, repairs, and general carpentry work.'],
                ['name' => 'Painting', 'slug' => 'painting', 'description' => 'Interior and exterior painting and surface preparation.'],
                ['name' => 'Roofing', 'slug' => 'roofing', 'description' => 'Roof repairs, replacements, and leak management.'],
                ['name' => 'Air Conditioning', 'slug' => 'air-conditioning', 'description' => 'Air conditioning install, servicing, and repairs.'],
                ['name' => 'Handyman', 'slug' => 'handyman', 'description' => 'General repairs, small jobs, and property maintenance.'],
                ['name' => 'Tiling', 'slug' => 'tiling', 'description' => 'Wall and floor tiling, repairs, and finishing.'],
                ['name' => 'Pest Control', 'slug' => 'pest-control-solo', 'description' => 'Residential and commercial pest treatment services.'],
                ['name' => 'Conveyancers', 'slug' => 'conveyancers', 'description' => 'Property transfer and conveyancing services.'],
                ['name' => 'Brokers', 'slug' => 'brokers', 'description' => 'Mortgage, finance, and deal facilitation services.'],
                ['name' => 'Solar Installation', 'slug' => 'solar-installation', 'description' => 'Solar panel installation, maintenance, and upgrades.'],
                ['name' => 'Waterproofing', 'slug' => 'waterproofing', 'description' => 'Wet-area sealing, leak protection, and remediation.'],
                ['name' => 'Fire Safety', 'slug' => 'fire-safety', 'description' => 'Fire system checks, compliance, and certification support.'],
            ],
        ];

        foreach ($servicesByType as $typeSlug => $services) {
            $type = $types->get($typeSlug);
            if (!$type) {
                continue;
            }

            foreach ($services as $service) {
                Service::withTrashed()->updateOrCreate(
                    ['slug' => $service['slug']],
                    [
                        'type_id' => $type->id,
                        'name' => $service['name'],
                        'description' => $service['description'],
                        'is_active' => true,
                        'deleted_at' => null,
                    ]
                );
            }
        }
    }
}
