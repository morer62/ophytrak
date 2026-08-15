<?php

namespace App\Services;

class OphyraLandingPageService
{
    private const SIGNUP_ROUTE = 'signup';
    private const BUSINESS_SLUGS = [
        'project-based-businesses',
        'service-businesses',
        'retail-online-stores',
        'logistics-delivery',
        'consultants-agencies',
        'venues-hospitality',
    ];

    public function getPage(string $group, string $slug, string $appUrl): ?array
    {
        $pages = $this->pages($appUrl);

        return $pages[$group][$slug] ?? null;
    }

    public function getModulePages(string $appUrl): array
    {
        return array_values($this->pages($appUrl)['modules']);
    }

    public function getIndustryPages(string $appUrl): array
    {
        $pages = $this->pages($appUrl)['industries'];

        return array_values(array_filter(
            array_map(static fn(string $slug): ?array => $pages[$slug] ?? null, self::BUSINESS_SLUGS)
        ));
    }

    private function pages(string $appUrl): array
    {
        $appUrl = rtrim($appUrl, '/');

        $modules = [
            'orders-operations' => $this->page($appUrl, [
                'group' => 'modules',
                'slug' => 'orders-operations',
                'route' => 'modules/orders-operations',
                'label' => 'Service Operations',
                'keyword' => 'operations management software for service businesses',
                'intent' => 'Business owners looking for a cleaner way to run jobs, orders, tasks and execution.',
                'eyebrow' => 'Service Operations capability',
                'h1' => 'Stop letting orders, notes, approvals and tasks live in different places.',
                'title' => 'Ophyra Service Operations | CRM, Orders, Contracts and Team',
                'description' => 'Manage clients, service orders, contracts, team work, payroll basics, communication and reports from one service operations workspace.',
                'hero_image' => 'assets/public/ophyra/modules/orders-operations-hero.webp',
                'hero_alt' => 'Ophyra orders and operations dashboard for service businesses',
                'hero_subtitle' => 'Ophyra turns scattered execution into a visible operating flow, so every client, job, note and next step has a place.',
                'problem_title' => 'Manual operations look flexible until work starts slipping.',
                'problem_points' => [
                    'Orders begin in one conversation, change in another, and get executed from memory.',
                    'Files, notes, signatures and payment context are separated from the actual job.',
                    'Owners spend too much time asking for status instead of moving the business forward.',
                ],
                'cost_title' => 'The hidden cost is not just confusion. It is rework, delay and missed revenue.',
                'cost_points' => [
                    'Small details get lost before the team reaches execution.',
                    'Clients feel the business is reactive, even when the team is working hard.',
                    'Every repeated question becomes another tax on the owner’s time.',
                ],
                'solution_title' => 'Ophyra gives each order a home.',
                'solution_body' => 'Use Ophyra to connect customers, services, operational notes, contracts, files and status in one place. The work becomes easier to inspect, assign and complete.',
                'benefits' => [
                    'Keep client work organized from intake to completion.',
                    'Reduce status chasing across texts and disconnected spreadsheets.',
                    'Give platform admins and business owners better visibility into active work.',
                    'Preserve context when staff changes, a client asks for updates or a job needs review.',
                ],
                'ideal_for' => [
                    'Service businesses with repeated jobs or client requests.',
                    'Event teams managing moving parts and approvals.',
                    'Operations teams that need clean status visibility.',
                ],
                'use_cases' => [
                    'Create and manage operational orders.',
                    'Attach notes, files and service details.',
                    'Track job status without rebuilding the story every day.',
                ],
                'related' => [
                    ['label' => 'CRM capability', 'route' => 'modules/crm'],
                    ['label' => 'Team coordination', 'route' => 'modules/team-payroll'],
                    ['label' => 'Project-Based Businesses', 'route' => 'business/project-based-businesses'],
                ],
                'faqs' => [
                    ['question' => 'Does Ophyra replace spreadsheets for orders?', 'answer' => 'It gives the business a structured place for order context, status and execution so spreadsheets stop carrying the whole operation.'],
                    ['question' => 'Is this a separate add-on from Service Operations?', 'answer' => 'No. This page explains the operational capability inside the Service Operations layer, while paid add-ons can be activated later when the business needs more specialized tools.'],
                ],
            ]),
            'crm' => $this->page($appUrl, [
                'group' => 'modules',
                'slug' => 'crm',
                'route' => 'modules/crm',
                'label' => 'CRM',
                'keyword' => 'CRM for service businesses',
                'intent' => 'Businesses that need to track leads, clients, follow-ups and sales movement.',
                'eyebrow' => 'Service Operations capability',
                'h1' => 'Turn inquiries, leads and client follow-ups into a visible pipeline.',
                'title' => 'CRM for Service Businesses | Ophyra',
                'description' => 'Use Ophyra CRM to organize leads, client records, follow-ups and business opportunities without losing context.',
                'hero_image' => 'assets/public/ophyra/modules/crm-hero.webp',
                'hero_alt' => 'Ophyra CRM pipeline for leads and client follow-ups',
                'hero_subtitle' => 'A lead is not valuable because it arrived. It becomes valuable when your team can follow it, understand it and move it forward.',
                'problem_title' => 'Most businesses do not lose leads all at once. They leak them slowly.',
                'problem_points' => [
                    'A prospect asks a question and the follow-up depends on memory.',
                    'Client notes are buried in texts, emails and personal notebooks.',
                    'Owners cannot quickly see which opportunities need attention today.',
                ],
                'cost_title' => 'Every forgotten follow-up is a quiet revenue leak.',
                'cost_points' => [
                    'Hot leads go cold before anyone notices.',
                    'Clients repeat information because the business has no shared record.',
                    'Sales activity becomes impossible to measure honestly.',
                ],
                'solution_title' => 'Ophyra keeps the relationship moving.',
                'solution_body' => 'Use CRM to track leads, customer records, notes, assignments and follow-up movement inside the same operational system that will later handle the work.',
                'benefits' => [
                    'See leads and clients without digging through conversations.',
                    'Keep follow-up ownership clear.',
                    'Connect sales context to future orders and services.',
                    'Build a repeatable client process without adding a heavy enterprise CRM.',
                ],
                'ideal_for' => [
                    'Service providers with inbound requests.',
                    'Agencies and consultants managing prospects.',
                    'Event planners and venues with long sales conversations.',
                ],
                'use_cases' => [
                    'Capture new inquiries.',
                    'Assign follow-ups.',
                    'Track client history before the first order is created.',
                ],
                'related' => [
                    ['label' => 'Service Operations', 'route' => 'modules/orders-operations'],
                    ['label' => 'Consultants / Agencies', 'route' => 'business/consultants-agencies'],
                    ['label' => 'Service Businesses', 'route' => 'business/service-businesses'],
                ],
                'faqs' => [
                    ['question' => 'Is Ophyra CRM only for event businesses?', 'answer' => 'No. It is designed for service and operations-driven businesses that need clearer client follow-up.'],
                    ['question' => 'Is CRM sold as a separate module?', 'answer' => 'No. CRM is presented as a client and follow-up capability inside the operating workflow, not as a standalone paid add-on.'],
                ],
            ]),
            'team-payroll' => $this->page($appUrl, [
                'group' => 'modules',
                'slug' => 'team-payroll',
                'route' => 'modules/team-payroll',
                'label' => 'Team Coordination',
                'keyword' => 'team operations and payroll software',
                'intent' => 'Owners who need clearer team coordination, internal users, hours and basic payroll control.',
                'eyebrow' => 'Shared operations capability',
                'h1' => 'Give your team structure before the operation depends on guesswork.',
                'title' => 'Team Coordination & Payroll Visibility | Ophyra',
                'description' => 'Coordinate team members, permissions, internal work, basic chat and payroll visibility inside Ophyra.',
                'hero_image' => 'assets/public/ophyra/modules/team-payroll-hero.webp',
                'hero_alt' => 'Ophyra team coordination and basic payroll workspace',
                'hero_subtitle' => 'When responsibility lives only in group chats, every task needs extra explanation. Ophyra helps the team work from shared context.',
                'problem_title' => 'Team chaos usually starts as “quick communication.”',
                'problem_points' => [
                    'Responsibilities are discussed but not always assigned clearly.',
                    'Owners become the only person who knows who is doing what.',
                    'Hours, payroll context and work history are difficult to review later.',
                ],
                'cost_title' => 'A disorganized team creates invisible management debt.',
                'cost_points' => [
                    'Staff wait for direction instead of moving with confidence.',
                    'Payroll review becomes emotional because the record is incomplete.',
                    'Owners spend energy translating the same operation to everyone again.',
                ],
                'solution_title' => 'Ophyra creates a practical team layer.',
                'solution_body' => 'Manage team users, basic permissions, internal coordination and payroll visibility from the same place where operational work is tracked.',
                'benefits' => [
                    'Create clearer team access and responsibilities.',
                    'Support basic payroll review without separating it from operations.',
                    'Reduce dependency on the owner as the only source of truth.',
                    'Keep internal work closer to the actual client or order context.',
                ],
                'ideal_for' => [
                    'Growing teams that are no longer manageable by memory.',
                    'Operations with staff, contractors or assistants.',
                    'Businesses that need basic payroll visibility tied to work.',
                ],
                'use_cases' => [
                    'Add internal team members.',
                    'Coordinate operational assignments.',
                    'Review work and basic payroll context.',
                ],
                'related' => [
                    ['label' => 'Service Operations', 'route' => 'modules/orders-operations'],
                    ['label' => 'Venues / Hospitality', 'route' => 'business/venues-hospitality'],
                    ['label' => 'Project-Based Businesses', 'route' => 'business/project-based-businesses'],
                ],
                'faqs' => [
                    ['question' => 'Is payroll a separate accounting system?', 'answer' => 'No. Ophyra focuses on basic operational payroll visibility, not replacing full accounting software.'],
                    ['question' => 'Is team access a standalone paid module?', 'answer' => 'No. Team access is a shared operational capability. The business still controls which paid work areas are active for each team workflow.'],
                ],
            ]),
            'inventory-storage' => $this->page($appUrl, [
                'group' => 'modules',
                'slug' => 'inventory-storage',
                'route' => 'modules/inventory-storage',
                'label' => 'Advanced Storage / QR Inventory',
                'keyword' => 'inventory storage tracking software',
                'intent' => 'Businesses that need control over items, containers, stock, storage and physical assets.',
                'eyebrow' => 'Paid add-on',
                'h1' => 'Know what you own, where it is and what operation depends on it.',
                'title' => 'Ophyra Advanced Storage | QR Inventory, Containers and Item Tracking',
                'description' => 'Track containers, items, storage locations, QR labels, equipment and rental movement with Ophyra Advanced Storage.',
                'hero_image' => 'assets/public/ophyra/modules/inventory-storage-hero.webp',
                'hero_alt' => 'Ophyra inventory and storage tracking module',
                'hero_subtitle' => 'Inventory becomes expensive when it is invisible. Ophyra helps your team track items, containers and storage before small losses become operational drag.',
                'problem_title' => 'Physical items are easy to buy and hard to control.',
                'problem_points' => [
                    'Supplies move between jobs, rooms, vehicles and storage without a clean record.',
                    'Teams lose time asking where items are or whether they exist.',
                    'Replacement purchases happen because nobody trusts the inventory picture.',
                ],
                'cost_title' => 'Missing inventory is not only a missing item. It is delayed work.',
                'cost_points' => [
                    'Teams prepare late because items cannot be found.',
                    'Owners buy duplicates while useful stock sits forgotten.',
                    'Clients feel the effect when execution slows down.',
                ],
                'solution_title' => 'Ophyra turns storage into an accountable system.',
                'solution_body' => 'Use the Advanced Storage / QR Inventory add-on to organize items, containers, locations and QR workflows so physical assets are easier to inspect and reuse.',
                'benefits' => [
                    'Track items and containers with cleaner visibility.',
                    'Support storage workflows with QR-friendly organization.',
                    'Reduce duplicate purchases and last-minute searching.',
                    'Connect physical resources to real operations.',
                ],
                'ideal_for' => [
                    'Event teams with decor, equipment or supplies.',
                    'Venues and hospitality teams managing stored assets.',
                    'Service businesses that reuse tools, kits or materials.',
                ],
                'use_cases' => [
                    'Track containers and items.',
                    'Prepare QR labels for storage workflows.',
                    'Review where operational assets belong.',
                ],
                'related' => [
                    ['label' => 'Store + Logistics', 'route' => 'modules/store-delivery-tracking'],
                    ['label' => 'Venues / Hospitality', 'route' => 'business/venues-hospitality'],
                    ['label' => 'Logistics / Delivery', 'route' => 'business/logistics-delivery'],
                ],
                'faqs' => [
                    ['question' => 'Is Advanced Storage / QR Inventory included in the starter workspace?', 'answer' => 'It is a paid add-on, so businesses can activate it when advanced physical inventory control becomes important.'],
                    ['question' => 'Does deactivating the module delete inventory history?', 'answer' => 'No. Paid modules should preserve historical data so the business can reactivate without losing context.'],
                ],
            ]),
            'store-delivery-tracking' => $this->page($appUrl, [
                'group' => 'modules',
                'slug' => 'store-delivery-tracking',
                'route' => 'modules/store-delivery-tracking',
                'coming_soon' => true,
                'label' => 'Store + Logistics',
                'keyword' => 'store delivery tracking software',
                'intent' => 'Businesses that sell, fulfill, deliver or track product movement.',
                'eyebrow' => 'Core module',
                'h1' => 'Move products, orders and delivery updates out of scattered conversations.',
                'title' => 'Ophyra Store & Logistics | Orders, Fulfillment, Delivery and Inventory',
                'description' => 'Manage products, store orders, fulfillment, delivery tracking, basic inventory, customers, teams and reports from one logistics workspace.',
                'hero_image' => 'assets/public/ophyra/modules/store-delivery-tracking-hero.webp',
                'hero_alt' => 'Ophyra store delivery and tracking module',
                'hero_subtitle' => 'When a business sells and delivers, status becomes part of the customer experience. Ophyra helps that movement become trackable.',
                'problem_title' => 'Delivery operations break when status is informal.',
                'problem_points' => [
                    'Orders are accepted in one place and fulfilled somewhere else.',
                    'Customers ask for updates because tracking is not visible.',
                    'Teams prepare, package and deliver without one shared operational source.',
                ],
                'cost_title' => 'Every unclear delivery status creates extra work and weaker trust.',
                'cost_points' => [
                    'Customers ask the same questions repeatedly.',
                    'Staff waste time reconstructing what happened to an order.',
                    'Owners cannot easily see bottlenecks in fulfillment.',
                ],
                'solution_title' => 'Ophyra connects store activity to operational movement.',
                'solution_body' => 'Use the Store + Logistics core module to support products, fulfillment steps, delivery state and tracking workflows that make movement easier to manage.',
                'benefits' => [
                    'Organize store products and operational fulfillment.',
                    'Track delivery status with more visibility.',
                    'Reduce repeated update requests.',
                    'Support teams that prepare, package, deliver or coordinate logistics.',
                ],
                'ideal_for' => [
                    'Food, catering or product-based service businesses.',
                    'Delivery and logistics teams.',
                    'Venues or event operations selling items or packages.',
                ],
                'use_cases' => [
                    'Manage product-based orders.',
                    'Coordinate fulfillment and delivery states.',
                    'Give teams clearer tracking context.',
                ],
                'related' => [
                    ['label' => 'Advanced Storage / QR Inventory', 'route' => 'modules/inventory-storage'],
                    ['label' => 'Logistics / Delivery', 'route' => 'business/logistics-delivery'],
                    ['label' => 'Service Businesses', 'route' => 'business/service-businesses'],
                ],
                'faqs' => [
                    ['question' => 'Is this only for restaurants?', 'answer' => 'No. It supports any business that sells, prepares, fulfills or delivers products or packages.'],
                    ['question' => 'Can this be added after signup?', 'answer' => 'Yes. It is designed as a paid module that can be activated when delivery or store workflows matter.'],
                ],
            ]),
            'marketplace-connectors' => $this->page($appUrl, [
                'group' => 'modules',
                'slug' => 'marketplace-connectors',
                'route' => 'modules/marketplace-connectors',
                'label' => 'Marketplace Connectors',
                'keyword' => 'marketplace connector software for small business stores',
                'intent' => 'Businesses that want to connect marketplace sales channels to their Ophyra store workflow.',
                'eyebrow' => 'Paid add-on',
                'h1' => 'Connect marketplace channels without turning Store + Logistics into a bigger bundle.',
                'title' => 'Ophyra Marketplace Connectors | Shopify, Mercado Libre and TikTok Business',
                'description' => 'Connect marketplace tools, track external order status and manage manual sync workflows with Ophyra Marketplace Connectors.',
                'hero_image' => 'assets/public/ophyra/modules/store-delivery-tracking-hero.webp',
                'hero_alt' => 'Ophyra marketplace connector workspace for external commerce channels',
                'hero_subtitle' => 'Marketplace work needs its own activation, credentials and sync controls. Ophyra keeps that layer separate from the core store module.',
                'problem_title' => 'External channels create operational noise when they are not mapped cleanly.',
                'problem_points' => [
                    'Marketplace orders arrive with external IDs, statuses and fulfillment rules.',
                    'Teams need tokens and sync controls without exposing them across the whole store workflow.',
                    'Owners need to avoid duplicate imports and preserve the original marketplace payload.',
                ],
                'cost_title' => 'A messy connector can create duplicate orders, wrong status updates and unclear fulfillment.',
                'cost_points' => [
                    'Staff may fulfill from stale or incomplete marketplace status.',
                    'Support questions increase when public tracking links are not aligned.',
                    'Store operations become harder to audit when raw external data is not preserved.',
                ],
                'solution_title' => 'Ophyra separates marketplace connection from core store operations.',
                'solution_body' => 'Activate Marketplace Connectors when the business needs Mercado Libre, TikTok Business / Shop or Shopify token setup, manual sync tracking and external-to-internal status mapping.',
                'benefits' => [
                    'Keep marketplace tokens in a separate paid module.',
                    'Prepare manual sync records for external providers.',
                    'Map external statuses into Ophyra internal statuses.',
                    'Support public tracking-link preparation without bundling it into Store + Logistics.',
                ],
                'ideal_for' => [
                    'Stores selling through marketplaces and their own public profile.',
                    'Food, retail or delivery businesses preparing external channel imports.',
                    'Operators that need to audit external order data before automation.',
                ],
                'use_cases' => [
                    'Connect Mercado Libre credentials.',
                    'Prepare TikTok Business / Shop and Shopify tokens.',
                    'Record manual sync attempts and status mapping.',
                ],
                'related' => [
                    ['label' => 'Store + Logistics', 'route' => 'modules/store-delivery-tracking'],
                    ['label' => 'Logistics / Delivery', 'route' => 'business/logistics-delivery'],
                    ['label' => 'Retail / Online Stores', 'route' => 'business/retail-online-stores'],
                ],
                'faqs' => [
                    ['question' => 'Is Marketplace Connectors included with Store + Logistics?', 'answer' => 'No. It is a separate paid add-on so external channel work can be activated only when needed.'],
                    ['question' => 'Does this replace marketplace dashboards?', 'answer' => 'No. It prepares connector credentials, sync records and Ophyra status mapping so external orders can become easier to manage inside the operating base.'],
                ],
            ]),
        ];

        $modules['service-operations'] = $this->aliasPage($appUrl, $modules['orders-operations'], 'service-operations', 'modules/service-operations', [
            'eyebrow' => 'Core module',
            'title' => 'Ophyra Service Operations | CRM, Orders, Contracts and Team',
            'description' => 'Manage clients, service orders, contracts, team work, payroll basics, communication and reports from one service operations workspace.',
            'related' => [
                ['label' => 'CRM capability', 'route' => 'modules/crm'],
                ['label' => 'Team coordination', 'route' => 'modules/team-payroll'],
                ['label' => 'Project-Based Businesses', 'route' => 'business/project-based-businesses'],
            ],
        ]);

        $modules['store-logistics'] = $this->aliasPage($appUrl, $modules['store-delivery-tracking'], 'store-logistics', 'modules/store-logistics', [
            'eyebrow' => 'Core module',
            'coming_soon' => true,
            'title' => 'Ophyra Store & Logistics | Orders, Fulfillment, Delivery and Inventory',
            'description' => 'Manage products, store orders, fulfillment, delivery tracking, basic inventory, customers, teams and reports from one logistics workspace.',
            'related' => [
                ['label' => 'Advanced Storage / QR Inventory', 'route' => 'modules/advanced-storage-qr-inventory'],
                ['label' => 'Logistics / Delivery', 'route' => 'business/logistics-delivery'],
                ['label' => 'Retail / Online Stores', 'route' => 'business/retail-online-stores'],
            ],
        ]);

        $modules['advanced-storage-qr-inventory'] = $this->aliasPage($appUrl, $modules['inventory-storage'], 'advanced-storage-qr-inventory', 'modules/advanced-storage-qr-inventory', [
            'eyebrow' => 'Paid add-on',
            'title' => 'Ophyra Advanced Storage | QR Inventory, Containers and Item Tracking',
            'description' => 'Track containers, items, storage locations, QR labels, equipment and rental movement with Ophyra Advanced Storage.',
            'related' => [
                ['label' => 'Store + Logistics', 'route' => 'modules/store-logistics'],
                ['label' => 'Venues / Hospitality', 'route' => 'business/venues-hospitality'],
                ['label' => 'Logistics / Delivery', 'route' => 'business/logistics-delivery'],
            ],
        ]);

        $modules['ai-advisor'] = $this->page($appUrl, [
            'group' => 'modules',
            'slug' => 'ai-advisor',
            'route' => 'modules/ai-advisor',
            'label' => 'AI Advisor',
            'keyword' => 'AI advisor for business operations',
            'intent' => 'Business owners who want operational guidance, summaries, ideas and content support inside their workflow.',
            'eyebrow' => 'Paid add-on',
            'h1' => 'Use AI support where the operational context already lives.',
            'title' => 'Ophyra AI Advisor | Operational Insights and Business Recommendations',
            'description' => 'Use Ophyra AI Advisor to summarize operations, generate ideas, suggest next actions and support business decisions.',
            'hero_image' => 'assets/public/ophyra/modules/orders-operations-hero.webp',
            'hero_alt' => 'Ophyra AI Advisor module for operational guidance',
            'hero_subtitle' => 'AI is most useful when it understands the work. Ophyra keeps guidance close to clients, orders, modules and business context.',
            'problem_title' => 'Generic AI answers often miss the business operation behind the question.',
            'problem_points' => [
                'Owners need summaries and recommendations that connect to real clients, orders and modules.',
                'Teams lose time rewriting the same operational context before asking for help.',
                'Content and planning support becomes disconnected from the workspace where work happens.',
            ],
            'cost_title' => 'When AI sits outside operations, the business still has to translate everything.',
            'cost_points' => [
                'Useful ideas are harder to apply.',
                'Summaries do not always match the current workflow.',
                'Owners spend more time turning advice into action.',
            ],
            'solution_title' => 'Ophyra AI Advisor keeps help closer to the work.',
            'solution_body' => 'Activate AI Advisor when the business needs ideas, summaries, recommendations, content support or operational guidance inside the same modular operating base.',
            'benefits' => [
                'Support business planning without leaving the workspace.',
                'Summarize operational context faster.',
                'Generate practical recommendations tied to real workflows.',
                'Help teams move from ideas to next steps with less friction.',
            ],
            'ideal_for' => [
                'Owners who need faster operational planning.',
                'Teams that create repeated business content.',
                'Businesses that want guidance connected to active modules.',
            ],
            'use_cases' => [
                'Summarize customer or order context.',
                'Draft operational recommendations.',
                'Support content and growth planning.',
            ],
            'related' => [
                ['label' => 'Service Operations', 'route' => 'modules/service-operations'],
                ['label' => 'Store + Logistics', 'route' => 'modules/store-logistics'],
                ['label' => 'Consultants / Agencies', 'route' => 'business/consultants-agencies'],
            ],
            'faqs' => [
                ['question' => 'Does AI Advisor replace a business owner or manager?', 'answer' => 'No. It supports ideas, summaries and recommendations while the business keeps control of decisions and execution.'],
                ['question' => 'Is AI Advisor a separate paid add-on?', 'answer' => 'Yes. It can be activated when a business wants AI support inside Ophyra workflows.'],
            ],
        ]);

        $modules['ticket-sales-rsvp'] = $this->page($appUrl, [
            'group' => 'modules',
            'slug' => 'ticket-sales-rsvp',
            'route' => 'modules/ticket-sales-rsvp',
            'label' => 'Ticket Sales + RSVP',
            'keyword' => 'ticket sales and RSVP software for event teams',
            'intent' => 'Event businesses that need registrations, RSVP, ticket sales and guest workflows.',
            'eyebrow' => 'Paid add-on',
            'h1' => 'Manage event attendance without separating guests from operations.',
            'title' => 'Ophyra Ticket Sales & RSVP | Registration, Attendance and Guest Lists',
            'description' => 'Run ticket sales, RSVP, event registration, guest lists and attendance workflows as an optional Ophyra add-on.',
            'hero_image' => 'assets/public/ophyra/industries/event-planners-hero.webp',
            'hero_alt' => 'Ophyra ticket sales and RSVP module for event operations',
            'hero_subtitle' => 'Guest lists, registrations and ticket sales should connect back to the event operation they belong to.',
            'problem_title' => 'Attendance workflows get messy when they live outside the event plan.',
            'problem_points' => [
                'Guest lists and RSVP updates are often separated from service orders and team execution.',
                'Ticket sales create payment and attendee context that needs clean visibility.',
                'Event teams need registration answers without rebuilding the story across tools.',
            ],
            'cost_title' => 'Disconnected attendance creates confusion before the event starts.',
            'cost_points' => [
                'Teams work from outdated lists.',
                'Client questions require manual checks.',
                'Revenue and attendance context becomes harder to review.',
            ],
            'solution_title' => 'Ophyra connects attendance workflows to event operations.',
            'solution_body' => 'Activate Ticket Sales + RSVP when event teams need registration, guest list, RSVP and ticket-sale workflows connected to the broader operating base.',
            'benefits' => [
                'Support RSVP and attendee workflows.',
                'Keep ticket sales context closer to event operations.',
                'Reduce manual guest-list reconciliation.',
                'Connect event registration work to the business profile.',
            ],
            'ideal_for' => [
                'Event planners and producers.',
                'Venues with recurring events.',
                'Businesses that sell tickets or manage attendance.',
            ],
            'use_cases' => [
                'Collect RSVP responses.',
                'Manage attendee lists.',
                'Support ticketed event workflows.',
            ],
            'related' => [
                ['label' => 'Event Planners', 'route' => 'business/event-planners'],
                ['label' => 'Venues / Hospitality', 'route' => 'business/venues-hospitality'],
                ['label' => 'Service Operations', 'route' => 'modules/service-operations'],
            ],
            'faqs' => [
                ['question' => 'Is Ticket Sales + RSVP required for every event business?', 'answer' => 'No. It is a paid add-on for teams that need attendance, registration or ticket-sale workflows.'],
                ['question' => 'Does it replace the Service Operations module?', 'answer' => 'No. It extends event workflows with RSVP and ticketing features while Service Operations remains the main operating layer.'],
            ],
        ]);

        $industries = [
            'project-based-businesses' => $this->page($appUrl, [
                'group' => 'industries',
                'slug' => 'project-based-businesses',
                'route' => 'business/project-based-businesses',
                'label' => 'Project-Based Businesses',
                'keyword' => 'project business operations software',
                'intent' => 'Companies that work by project, production, installation, event, execution or custom job.',
                'eyebrow' => 'Built for project work',
                'h1' => 'Run every project, client and team workflow from one place.',
                'title' => 'Ophyra for Project-Based Businesses | Clients, Orders, Teams and Execution',
                'description' => 'Ophyra helps project-based businesses manage clients, orders, timelines, teams, services and execution details from one workspace.',
                'hero_image' => 'assets/public/ophyra/industries/event-planners-hero.webp',
                'hero_alt' => 'Ophyra workspace for project-based businesses coordinating clients orders and teams',
                'hero_subtitle' => 'Project work moves through clients, teams, timelines, services and execution details. Ophyra keeps that context from splitting across too many tools.',
                'problem_title' => 'Project work has too many details to live in memory.',
                'problem_points' => [
                    'Client notes, service changes and timelines move through too many disconnected places.',
                    'Team assignments are clear in the moment but hard to verify later.',
                    'Inventory, contracts and order details often sit outside the same workflow.',
                ],
                'cost_title' => 'The price of scattered project management is paid during execution.',
                'cost_points' => [
                    'Small details become urgent problems.',
                    'Clients feel stress that could have been prevented.',
                    'Owners become the emergency coordinator for every missing answer.',
                ],
                'solution_title' => 'Ophyra gives project-based teams an operating base.',
                'solution_body' => 'Use Service Operations to connect leads, clients, orders, contracts, timelines, team responsibilities and execution context in one workflow.',
                'benefits' => [
                    'Keep project clients, orders and execution details connected.',
                    'Coordinate team work without relying only on chat threads.',
                    'Add advanced inventory, ticketing or store workflows only when the operation needs them.',
                    'Give the business a repeatable process for each project.',
                ],
                'ideal_for' => [
                    'Companies managing custom jobs or projects.',
                    'Production, installation and execution teams.',
                    'Event or field teams that need visibility across planning, staff and fulfillment.',
                ],
                'use_cases' => [
                    'Track leads and client conversations.',
                    'Build operational orders and service details.',
                    'Coordinate team, inventory and fulfillment around project execution.',
                ],
                'related' => [
                    ['label' => 'Orders / Operations', 'route' => 'modules/orders-operations'],
                    ['label' => 'Team / Payroll', 'route' => 'modules/team-payroll'],
                    ['label' => 'Advanced Storage / QR Inventory', 'route' => 'modules/inventory-storage'],
                ],
                'faqs' => [
                    ['question' => 'Can project-based businesses start without buying every module?', 'answer' => 'Yes. Ophyra starts with a free Base Profile and lets businesses activate paid modules as their operation requires more control.'],
                    ['question' => 'Is this only for event businesses?', 'answer' => 'No. Project-Based Businesses covers production, installation, event, execution and custom-job companies.'],
                ],
            ]),
            'service-businesses' => $this->page($appUrl, [
                'group' => 'industries',
                'slug' => 'service-businesses',
                'route' => 'business/service-businesses',
                'label' => 'Service Businesses',
                'keyword' => 'service business operations software',
                'intent' => 'Service businesses that need better intake, clients, orders, staff and payment visibility.',
                'eyebrow' => 'Built for service businesses',
                'h1' => 'Turn service requests into organized work instead of scattered promises.',
                'title' => 'Ophyra for Service Businesses | Jobs, Customers and Follow-up',
                'description' => 'Ophyra helps service businesses manage leads, customers, jobs, team responsibilities and follow-up from one operational workspace.',
                'hero_image' => 'assets/public/ophyra/industries/service-providers-hero.webp',
                'hero_alt' => 'Ophyra service provider operations dashboard',
                'hero_subtitle' => 'A growing service business needs more than a calendar and text messages. It needs a place where client work becomes trackable.',
                'problem_title' => 'Service businesses often grow faster than their process.',
                'problem_points' => [
                    'Requests arrive through texts, calls, forms and referrals.',
                    'The team knows the work, but the system does not.',
                    'Clients expect updates before the business has a clean way to give them.',
                ],
                'cost_title' => 'Disorganization makes good service feel unreliable.',
                'cost_points' => [
                    'Leads are missed or followed up too late.',
                    'Work is repeated because the original details are unclear.',
                    'Owners cannot confidently measure what is active, stuck or complete.',
                ],
                'solution_title' => 'Ophyra gives service businesses an operating rhythm.',
                'solution_body' => 'Use Service Operations to move from inquiry to customer, job, team responsibility and follow-up with more visibility.',
                'benefits' => [
                    'Organize client intake and service follow-up.',
                    'Keep orders and work context in one place.',
                    'Coordinate staff responsibilities with less owner dependency.',
                    'Add modules only when the business truly needs them.',
                ],
                'ideal_for' => [
                    'Local service companies.',
                    'Specialized providers with repeat clients.',
                    'Businesses moving from manual tracking to a real operating system.',
                ],
                'use_cases' => [
                    'Capture leads and client records.',
                    'Manage service jobs and operational status.',
                    'Coordinate staff and follow-ups.',
                ],
                'related' => [
                    ['label' => 'CRM', 'route' => 'modules/crm'],
                    ['label' => 'Orders / Operations', 'route' => 'modules/orders-operations'],
                    ['label' => 'Store + Logistics', 'route' => 'modules/store-delivery-tracking'],
                ],
                'faqs' => [
                    ['question' => 'Is Ophyra only for large teams?', 'answer' => 'No. It is useful as soon as a business needs shared visibility across clients, orders and team work.'],
                    ['question' => 'Can a service provider add paid modules later?', 'answer' => 'Yes. Paid modules can be activated when workflows like inventory or delivery become necessary.'],
                ],
            ]),
            'retail-online-stores' => $this->page($appUrl, [
                'group' => 'industries',
                'slug' => 'retail-online-stores',
                'route' => 'business/retail-online-stores',
                'coming_soon' => true,
                'label' => 'Retail / Online Stores',
                'keyword' => 'retail online store operations software',
                'intent' => 'Retail and online businesses that sell products, menus, boxes, bundles or merchandise online.',
                'eyebrow' => 'Built for product sales',
                'h1' => 'Sell products, manage orders and coordinate fulfillment from one workspace.',
                'title' => 'Ophyra for Retail and Online Stores | Products, Orders and Fulfillment',
                'description' => 'Ophyra helps retail and online businesses manage products, customer orders, fulfillment, delivery and follow-up from one workspace.',
                'hero_image' => 'assets/public/ophyra/industries/logistics-delivery-hero.webp',
                'hero_alt' => 'Ophyra retail and online store fulfillment workspace',
                'hero_subtitle' => 'Selling online is only clean when products, orders, fulfillment and customer updates stay connected. Ophyra gives product-based teams a practical operating base.',
                'problem_title' => 'Product sales get messy when fulfillment lives outside the order.',
                'problem_points' => [
                    'Products, customer requests and fulfillment notes are tracked in different places.',
                    'Delivery updates depend on manual messages instead of a shared operational record.',
                    'The team has to reconstruct what happened to each order.',
                ],
                'cost_title' => 'Disconnected commerce creates delays, support questions and missed follow-up.',
                'cost_points' => [
                    'Customers ask for status because the process is not visible.',
                    'Staff spend time searching for product and order context.',
                    'Owners cannot quickly see what is sold, pending, packed or delivered.',
                ],
                'solution_title' => 'Ophyra connects store work to fulfillment.',
                'solution_body' => 'Use Store + Logistics to organize products, customer orders, fulfillment, delivery status and follow-up from one workspace.',
                'benefits' => [
                    'Manage product-based orders with clearer context.',
                    'Coordinate fulfillment and delivery status.',
                    'Keep customers, products and follow-up closer together.',
                    'Add Advanced Storage / QR Inventory when physical control needs to go deeper.',
                ],
                'ideal_for' => [
                    'Retail and online stores.',
                    'Meal, box, bundle or merchandise businesses.',
                    'Product teams coordinating fulfillment and delivery.',
                ],
                'use_cases' => [
                    'Manage products and customer orders.',
                    'Coordinate packing, fulfillment and delivery status.',
                    'Track follow-up around product-based sales.',
                ],
                'related' => [
                    ['label' => 'Store + Logistics', 'route' => 'modules/store-delivery-tracking'],
                    ['label' => 'Advanced Storage / QR Inventory', 'route' => 'modules/inventory-storage'],
                    ['label' => 'Logistics / Delivery', 'route' => 'business/logistics-delivery'],
                ],
                'faqs' => [
                    ['question' => 'Which module fits retail and online stores?', 'answer' => 'Store + Logistics is the main core for product sales, online orders, fulfillment and delivery visibility.'],
                    ['question' => 'Can inventory be added later?', 'answer' => 'Yes. Basic inventory can be part of Store + Logistics, and Advanced Storage / QR Inventory can be added when physical control needs to go deeper.'],
                ],
            ]),
            'logistics-delivery' => $this->page($appUrl, [
                'group' => 'industries',
                'slug' => 'logistics-delivery',
                'route' => 'business/logistics-delivery',
                'coming_soon' => true,
                'label' => 'Logistics / Delivery',
                'keyword' => 'delivery operations tracking software',
                'intent' => 'Businesses that coordinate fulfillment, delivery status, stock movement and customer updates.',
                'eyebrow' => 'Built for movement',
                'h1' => 'Make fulfillment and delivery visible before customers start asking for updates.',
                'title' => 'Ophyra for Logistics and Delivery Businesses | Tracking and Fulfillment',
                'description' => 'Ophyra helps logistics and delivery businesses organize orders, fulfillment, inventory, delivery status and team coordination.',
                'hero_image' => 'assets/public/ophyra/industries/logistics-delivery-hero.webp',
                'hero_alt' => 'Ophyra logistics delivery and tracking workspace',
                'hero_subtitle' => 'Delivery work is measured by movement and trust. Ophyra helps teams see what is being prepared, moved, delivered and followed up.',
                'problem_title' => 'Delivery pressure increases when status is invisible.',
                'problem_points' => [
                    'Orders move through preparation, pickup, delivery and customer updates without one clear record.',
                    'Inventory and fulfillment may be separated from client communication.',
                    'Dispatch questions interrupt the team because tracking is not shared.',
                ],
                'cost_title' => 'Unclear delivery status creates support load and trust problems.',
                'cost_points' => [
                    'Customers ask for updates because they cannot see progress.',
                    'Staff lose time reconstructing route and fulfillment context.',
                    'Owners cannot identify where the operation slows down.',
                ],
                'solution_title' => 'Ophyra connects orders, inventory and delivery movement.',
                'solution_body' => 'Use Ophyra to organize the customer, the order, the team and the delivery flow. Activate Store + Logistics when the business needs deeper fulfillment visibility.',
                'benefits' => [
                    'Keep fulfillment and order context closer together.',
                    'Reduce repeated delivery status questions.',
                    'Support inventory and store workflows as paid modules.',
                    'Give platform admins or owners a cleaner review path for operational exceptions.',
                ],
                'ideal_for' => [
                    'Delivery businesses handling multiple active orders.',
                    'Product-based service teams.',
                    'Operations that need inventory and movement visibility.',
                ],
                'use_cases' => [
                    'Track fulfillment status.',
                    'Coordinate delivery-related work.',
                    'Connect product movement to customer context.',
                ],
                'related' => [
                    ['label' => 'Store + Logistics', 'route' => 'modules/store-delivery-tracking'],
                    ['label' => 'Advanced Storage / QR Inventory', 'route' => 'modules/inventory-storage'],
                    ['label' => 'Service Businesses', 'route' => 'business/service-businesses'],
                ],
                'faqs' => [
                    ['question' => 'Does Ophyra include delivery tracking as a starter feature?', 'answer' => 'Delivery tracking is positioned as a paid module so businesses can add it when fulfillment becomes central.'],
                    ['question' => 'Can platform admins help with customer issues?', 'answer' => 'Yes. The platform keeps manual oversight available so billing, modules and account issues can be reviewed.'],
                ],
            ]),
            'consultants-agencies' => $this->page($appUrl, [
                'group' => 'industries',
                'slug' => 'consultants-agencies',
                'route' => 'business/consultants-agencies',
                'label' => 'Consultants / Agencies',
                'keyword' => 'agency client operations software',
                'intent' => 'Consultants and agencies that need client follow-up, project/order visibility and team coordination.',
                'eyebrow' => 'Built for client work',
                'h1' => 'Keep leads, clients, tasks and delivery promises from spreading across too many tools.',
                'title' => 'Ophyra for Consultants and Agencies | CRM, Clients and Operations',
                'description' => 'Ophyra helps consultants and agencies manage leads, clients, orders, team responsibilities and service delivery context.',
                'hero_image' => 'assets/public/ophyra/industries/consultants-agencies-hero.webp',
                'hero_alt' => 'Ophyra client operations workspace for consultants and agencies',
                'hero_subtitle' => 'Client work becomes harder to scale when every promise lives in a different channel. Ophyra gives agencies and consultants a steadier operating base.',
                'problem_title' => 'Agencies often have visibility in sales tools but chaos in delivery.',
                'problem_points' => [
                    'Leads, proposals, client requests and internal tasks are separated.',
                    'Account context lives with individual team members instead of the business.',
                    'Owners cannot quickly see which clients need attention.',
                ],
                'cost_title' => 'The hidden cost is slower delivery and weaker client confidence.',
                'cost_points' => [
                    'Teams spend time searching for context instead of producing work.',
                    'Clients repeat requests because the internal record is incomplete.',
                    'Small missed details affect retention and referrals.',
                ],
                'solution_title' => 'Ophyra gives client operations a shared source of truth.',
                'solution_body' => 'Use CRM, client records, orders and team coordination to move prospects into active work without losing the relationship context that won the deal.',
                'benefits' => [
                    'Track leads and client follow-ups with cleaner accountability.',
                    'Connect client promises to operational work.',
                    'Coordinate team responsibilities around active accounts.',
                    'Build a stronger foundation before adding advanced automation.',
                ],
                'ideal_for' => [
                    'Consultants managing multiple client relationships.',
                    'Small agencies coordinating sales and delivery.',
                    'Operators who need structure without heavy enterprise software.',
                ],
                'use_cases' => [
                    'Manage lead pipelines.',
                    'Track client service orders.',
                    'Coordinate internal account work.',
                ],
                'related' => [
                    ['label' => 'CRM', 'route' => 'modules/crm'],
                    ['label' => 'Orders / Operations', 'route' => 'modules/orders-operations'],
                    ['label' => 'Team / Payroll', 'route' => 'modules/team-payroll'],
                ],
                'faqs' => [
                    ['question' => 'Is Ophyra a project management tool?', 'answer' => 'It is an operational platform. It can support client work, orders and team coordination without being only a task board.'],
                    ['question' => 'Can agencies start with the free workspace?', 'answer' => 'Yes. Agencies can start with the core and add paid modules when their workflow requires them.'],
                ],
            ]),
            'venues-hospitality' => $this->page($appUrl, [
                'group' => 'industries',
                'slug' => 'venues-hospitality',
                'route' => 'business/venues-hospitality',
                'label' => 'Venues / Hospitality',
                'keyword' => 'venue hospitality operations software',
                'intent' => 'Venues, hospitality and production teams that manage events, clients, staff, inventory and execution.',
                'eyebrow' => 'Built for venue operations',
                'h1' => 'Keep clients, spaces, staff, inventory and event work aligned in one operating flow.',
                'title' => 'Ophyra for Venues and Hospitality Teams | Operations and Inventory',
                'description' => 'Ophyra helps venues, hospitality and production teams organize clients, orders, staff, inventory, events and operational follow-through.',
                'hero_image' => 'assets/public/ophyra/industries/venues-hospitality-hero.webp',
                'hero_alt' => 'Ophyra venue and hospitality operations workspace',
                'hero_subtitle' => 'Venue and hospitality work depends on timing, staff and details. Ophyra helps the business see more of the operation before the day gets busy.',
                'problem_title' => 'Venue operations have too many dependencies to run from scattered notes.',
                'problem_points' => [
                    'Bookings, client details, setup notes and team assignments are often separated.',
                    'Inventory and spaces need coordination with real event work.',
                    'Managers need visibility without asking every department for updates.',
                ],
                'cost_title' => 'When the operation is unclear, the guest experience absorbs the pressure.',
                'cost_points' => [
                    'Setup details are missed or corrected late.',
                    'Staff coordination depends on whoever remembers the plan.',
                    'Inventory issues appear at the worst possible moment.',
                ],
                'solution_title' => 'Ophyra gives venues and hospitality teams an operational backbone.',
                'solution_body' => 'Use Ophyra for clients, orders, team coordination and business profile basics, then add inventory or delivery modules when physical operations need stronger control.',
                'benefits' => [
                    'Connect client and event context to execution.',
                    'Coordinate staff and operational responsibilities.',
                    'Add inventory tracking for stored assets and supplies.',
                    'Maintain manual platform oversight when support is needed.',
                ],
                'ideal_for' => [
                    'Venues hosting events or service work.',
                    'Hospitality teams coordinating staff and inventory.',
                    'Production teams that need cleaner operational visibility.',
                ],
                'use_cases' => [
                    'Track clients and event orders.',
                    'Coordinate team execution.',
                    'Control inventory and storage around operations.',
                ],
                'related' => [
                    ['label' => 'Advanced Storage / QR Inventory', 'route' => 'modules/inventory-storage'],
                    ['label' => 'Team / Payroll', 'route' => 'modules/team-payroll'],
                    ['label' => 'Project-Based Businesses', 'route' => 'business/project-based-businesses'],
                ],
                'faqs' => [
                    ['question' => 'Can venues use Ophyra without custom domains?', 'answer' => 'Yes. Ophyra can operate through the current web platform and basic business profile structure.'],
                    ['question' => 'Can inventory be added later?', 'answer' => 'Yes. Advanced Storage / QR Inventory is a paid add-on that can be activated when the operation needs it.'],
                ],
            ]),
        ];

        $industries['event-planners'] = $this->page($appUrl, array_merge(
            $industries['project-based-businesses'],
            [
                'slug' => 'event-planners',
                'route' => 'industries/event-planners',
            ]
        ));
        $industries['service-providers'] = $this->page($appUrl, array_merge(
            $industries['service-businesses'],
            [
                'slug' => 'service-providers',
                'route' => 'industries/service-providers',
            ]
        ));

        return [
            'modules' => $modules,
            'industries' => $industries,
        ];
    }

    private function page(string $appUrl, array $data): array
    {
        $data = $this->localizedPageData($data);
        $data['hero_image'] = $this->safePublicImage((string)($data['hero_image'] ?? ''));
        $canonical = $appUrl . '/' . ltrim($data['route'], '/');
        $imageUrl = $appUrl . '/' . ltrim($data['hero_image'], '/');

        $data['signup_route'] = self::SIGNUP_ROUTE;
        $data['canonical'] = $canonical;
        $data['seo'] = [
            'title' => $data['title'],
            'description' => $data['description'],
            'keywords' => $data['keyword'] ?? null,
            'author' => 'Ophyra',
            'canonical' => $canonical,
            'og_title' => $data['title'],
            'og_description' => $data['description'],
            'og_image' => $imageUrl,
            'twitter_title' => $data['title'],
            'twitter_description' => $data['description'],
            'robots' => 'index, follow',
        ];
        $data['schemaJson'] = $this->schema($appUrl, $data, $canonical, $imageUrl);

        return $data;
    }

    private function safePublicImage(string $path): string
    {
        $path = trim($path, '/');
        $fallback = 'assets/public/ophyra/screens/dashboard-view.png';

        if ($path === '') {
            return $fallback;
        }

        $root = dirname(__DIR__, 2);
        if (is_file($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path))) {
            return $path;
        }

        return $fallback;
    }

    private function aliasPage(string $appUrl, array $page, string $slug, string $route, array $overrides = []): array
    {
        unset($page['canonical'], $page['seo'], $page['schemaJson']);

        return $this->page($appUrl, array_merge($page, [
            'slug' => $slug,
            'route' => $route,
        ], $overrides));
    }

    private function localizedPageData(array $data): array
    {
        $group = (string)($data['group'] ?? '');
        $slug = (string)($data['slug'] ?? '');

        if ($group !== 'industries' || !in_array($slug, self::BUSINESS_SLUGS, true)) {
            return $data;
        }

        $prefix = 'ophyra_landing.industries.' . str_replace('-', '_', $slug);
        $stringFields = [
            'label',
            'keyword',
            'intent',
            'eyebrow',
            'h1',
            'title',
            'description',
            'hero_alt',
            'hero_subtitle',
            'problem_title',
            'cost_title',
            'solution_title',
            'solution_body',
        ];

        foreach ($stringFields as $field) {
            $genericKey = in_array($field, ['title', 'description'], true)
                ? null
                : 'ophyra_landing.industries.generic.' . $field;
            $data[$field] = $this->translatedValue(
                $prefix . '.' . $field,
                (string)($data[$field] ?? ''),
                $genericKey
            );
        }

        foreach (['problem_points', 'cost_points', 'benefits', 'ideal_for', 'use_cases'] as $listField) {
            if (!isset($data[$listField]) || !is_array($data[$listField])) {
                continue;
            }

            foreach (array_keys($data[$listField]) as $index) {
                $data[$listField][$index] = $this->translatedValue(
                    $prefix . '.' . $listField . '.' . $index,
                    (string)$data[$listField][$index],
                    'ophyra_landing.industries.generic.' . $listField . '.' . $index
                );
            }
        }

        if (isset($data['related']) && is_array($data['related'])) {
            foreach (array_keys($data['related']) as $index) {
                $data['related'][$index]['label'] = $this->translatedValue(
                    $prefix . '.related.' . $index . '.label',
                    (string)($data['related'][$index]['label'] ?? ''),
                    'ophyra_landing.industries.generic.related.' . $index . '.label'
                );
            }
        }

        if (isset($data['faqs']) && is_array($data['faqs'])) {
            foreach (array_keys($data['faqs']) as $index) {
                $data['faqs'][$index]['question'] = $this->translatedValue(
                    $prefix . '.faqs.' . $index . '.question',
                    (string)($data['faqs'][$index]['question'] ?? ''),
                    'ophyra_landing.industries.generic.faqs.' . $index . '.question'
                );
                $data['faqs'][$index]['answer'] = $this->translatedValue(
                    $prefix . '.faqs.' . $index . '.answer',
                    (string)($data['faqs'][$index]['answer'] ?? ''),
                    'ophyra_landing.industries.generic.faqs.' . $index . '.answer'
                );
            }
        }

        $cardChips = [];
        for ($index = 0; $index < 3; $index++) {
            $chip = $this->translatedValue($prefix . '.card_chips.' . $index, '');
            if ($chip !== '') {
                $cardChips[] = $chip;
            }
        }
        if ($cardChips !== []) {
            $data['card_chips'] = $cardChips;
        }

        return $data;
    }

    private function translatedValue(string $key, string $fallback, ?string $genericKey = null): string
    {
        $value = TranslationService::trans($key);
        if ($value === $key && $genericKey !== null) {
            $genericValue = TranslationService::trans($genericKey);
            if ($genericValue !== $genericKey) {
                return $genericValue;
            }
        }

        return $value === $key ? $fallback : $value;
    }

    private function schema(string $appUrl, array $page, string $canonical, string $imageUrl): array
    {
        $breadcrumbItems = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Ophyra',
                'item' => $appUrl . '/planner-hub',
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $page['group'] === 'modules' ? 'Modules' : 'Industries',
                'item' => $appUrl . '/planner-hub#ophyra-' . $page['group'],
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $page['label'],
                'item' => $canonical,
            ],
        ];

        $graph = [
            [
                '@type' => 'WebPage',
                '@id' => $canonical . '#webpage',
                'url' => $canonical,
                'name' => $page['title'],
                'description' => $page['description'],
                'inLanguage' => 'en-US',
                'primaryImageOfPage' => [
                    '@type' => 'ImageObject',
                    'url' => $imageUrl,
                ],
                'isPartOf' => [
                    '@id' => $appUrl . '/#website',
                ],
                'about' => [
                    '@id' => $appUrl . '/#software',
                ],
                'mainEntity' => [
                    '@id' => $canonical . '#service',
                ],
                'breadcrumb' => [
                    '@id' => $canonical . '#breadcrumb',
                ],
            ],
            [
                '@type' => 'BreadcrumbList',
                '@id' => $canonical . '#breadcrumb',
                'itemListElement' => $breadcrumbItems,
            ],
            [
                '@type' => 'Organization',
                '@id' => $appUrl . '/#organization',
                'name' => 'Ophyra',
                'url' => $appUrl . '/',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $appUrl . '/assets/images/planner-hub-logo-positive.png',
                ],
                'description' => 'Ophyra builds modular operations software for service companies, project-based businesses, logistics teams, consultants, agencies, retailers and venues.',
            ],
            [
                '@type' => 'WebSite',
                '@id' => $appUrl . '/#website',
                'name' => 'Ophyra',
                'url' => $appUrl . '/',
                'publisher' => [
                    '@id' => $appUrl . '/#organization',
                ],
            ],
            [
                '@type' => 'SoftwareApplication',
                '@id' => $appUrl . '/#software',
                'name' => 'Ophyra',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'url' => $appUrl . '/planner-hub',
                'description' => 'Ophyra is service business operations software for CRM, clients, service orders, team coordination, inventory, store, delivery, payment and business workflows.',
                'applicationSubCategory' => 'Service business operations software',
                'featureList' => array_values(array_filter(array_merge(
                    [$page['solution_body'] ?? ''],
                    $page['benefits'] ?? [],
                    $page['use_cases'] ?? []
                ))),
                'audience' => [
                    '@type' => 'BusinessAudience',
                    'audienceType' => $page['intent'] ?? 'service businesses and operational teams',
                ],
            ],
            [
                '@type' => 'Service',
                '@id' => $canonical . '#service',
                'name' => $page['label'] . ' operations software with Ophyra',
                'serviceType' => $page['group'] === 'modules' ? 'Service business operations software module' : $page['keyword'],
                'provider' => [
                    '@id' => $appUrl . '/#organization',
                ],
                'audience' => [
                    '@type' => 'BusinessAudience',
                    'audienceType' => $page['intent'] ?? $page['keyword'],
                ],
                'description' => $page['description'],
                'url' => $canonical,
                'hasOfferCatalog' => [
                    '@type' => 'OfferCatalog',
                    'name' => $page['label'] . ' Ophyra capabilities',
                    'itemListElement' => array_values(array_map(static function (string $benefit, int $index): array {
                        return [
                            '@type' => 'Offer',
                            'position' => $index + 1,
                            'itemOffered' => [
                                '@type' => 'Service',
                                'name' => $benefit,
                            ],
                        ];
                    }, $page['benefits'] ?? [], array_keys($page['benefits'] ?? []))),
                ],
            ],
        ];

        if (!empty($page['faqs'])) {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $canonical . '#faq',
                'mainEntity' => array_map(static function (array $faq): array {
                    return [
                        '@type' => 'Question',
                        'name' => $faq['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $faq['answer'],
                        ],
                    ];
                }, $page['faqs']),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}
