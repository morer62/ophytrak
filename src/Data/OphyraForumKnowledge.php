<?php

namespace App\Data;

final class OphyraForumKnowledge
{
    public static function all(): array
    {
        return [
            [
                'category' => 'System How-To',
                'question' => 'What is the first thing I should complete after creating my Ophyra account?',
                'answer' => 'The first step is to complete your Base Profile. This includes your business name, logo, contact information, location, business type, and public profile details. Your Base Profile is free and helps prepare your workspace before activating paid modules.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What is included in the free Base Profile?',
                'answer' => 'The Base Profile includes your basic public business profile, contact information, location, logo, business type, and limited workspace access. It does not unlock CRM, orders, contracts, team management, store tools, AI, ticketing, or advanced inventory.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Why are some modules locked in my dashboard?',
                'answer' => 'Modules are locked until they are activated through the Marketplace or manually enabled by an authorized Ophyra admin. The free Base Profile does not unlock paid operational tools.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What is the difference between Service Operations and Store + Logistics?',
                'answer' => 'Service Operations is for businesses that manage clients, services, contracts, teams, and service orders. Store + Logistics is for businesses that sell products, manage store orders, fulfillment, delivery, tracking, and basic inventory.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Why do CRM, Clients, Team, Chat, Payroll, and Reports appear as shared tools?',
                'answer' => 'These tools are shared operational tools. They are not sold as separate modules. They become available depending on whether Service Operations, Store + Logistics, or both are active.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Where do I activate paid modules?',
                'answer' => 'Paid modules should be activated from the Marketplace or Billing & Modules area. This area shows available modules, pricing, status, renewal dates, and activation options.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Does clicking Activate immediately turn on a module?',
                'answer' => 'No. A paid module should not activate immediately after clicking Activate. You should first see a review or checkout page, confirm payment details, complete payment, and only then should the module become active.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What happens if I cancel checkout?',
                'answer' => 'If you cancel checkout before completing payment, the module should remain locked. Your Base Profile remains active, but paid functionality should not be unlocked.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Where can I see my next renewal date?',
                'answer' => 'Renewal dates should appear inside the Marketplace, Billing & Modules, or module management area. Each paid module should show its own status and renewal date when active.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Why can I choose a currency before paying?',
                'answer' => 'Ophyra supports multiple billing currencies. Prices are based on fixed values configured by the platform and should be shown clearly before checkout. The selected currency is used to process payment through Stripe when supported.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Why is the Custom Domain + SEO Page Builder marked Coming Soon?',
                'answer' => 'That module is not ready for checkout yet. It may appear in the Marketplace so users know it is planned, but it should not show a price or allow activation until it is released.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What is the public business profile used for?',
                'answer' => 'The public business profile is your basic public-facing page. It can show your business information, contact details, location, and later connect with services, products, forms, SEO pages, or other public tools as modules become available.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'How do I know which operating core I need?',
                'answer' => 'If you sell services, manage contracts, assign tasks, and work with clients, start with Service Operations. If you sell products, manage fulfillment, delivery, tracking, or store orders, start with Store + Logistics.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Can I activate both Service Operations and Store + Logistics?',
                'answer' => 'Yes. If your business sells both services and products, you can activate both operating cores. Shared tools like CRM, Team, Tasks, Chat, and Reports should then support both contexts.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What is the Marketplace used for?',
                'answer' => 'The Marketplace is where you review, activate, renew, and manage modules. It should not be confused with your operational dashboard, which is where you actually work after modules are active.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'How should I decide which module to activate first?',
                'answer' => 'Start with the part of your business that creates the most operational pressure. If client follow-up, contracts, and service orders are the issue, activate Service Operations. If products, fulfillment, and delivery are the issue, activate Store + Logistics.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'Why should I complete my business profile before activating modules?',
                'answer' => 'A complete profile helps your business look more trustworthy and gives the system better context. It also prepares your public-facing presence before customers interact with forms, products, orders, or service requests.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'What is the best way to organize customer information?',
                'answer' => 'Keep customer information connected to real interactions: orders, service requests, purchases, contracts, chats, and payments. Avoid creating duplicate customer records when the same person interacts with your business in multiple ways.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'Why is it important to track tasks inside an order?',
                'answer' => 'Tasks help turn an order into a clear execution workflow. They show who is responsible, what must happen next, what is completed, and where the operation may be blocked.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'How can small businesses avoid losing track of orders?',
                'answer' => 'Use statuses, assigned team members, due dates, and clear internal notes. Every order should have a current status and a next action so it does not depend only on memory or chat messages.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'Why should delivery and preparation be separated into tasks?',
                'answer' => 'Preparation and delivery are different operational steps. Separating them helps the team know when an order is ready, who is responsible for each part, and whether delivery should wait until preparation is completed.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'How can I use reports without overcomplicating my business?',
                'answer' => 'Focus first on simple reports: sales, pending orders, completed orders, active clients, unpaid balances, and team activity. These reports help you make better decisions without creating unnecessary complexity.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'What should I review every morning in my dashboard?',
                'answer' => 'Review pending orders, today\'s tasks, unpaid balances, upcoming events or deliveries, assigned team members, and any customer messages that need a response.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'What is the biggest mistake businesses make with operations software?',
                'answer' => 'The biggest mistake is using the software only as a storage tool. Ophyra works best when orders, clients, tasks, payments, team assignments, and statuses are actively updated.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'How often should I update order status?',
                'answer' => 'Order status should be updated whenever a meaningful step changes: payment received, preparation started, preparation completed, delivery started, delivered, completed, cancelled, or refunded.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'How should I structure a service business inside Ophyra?',
                'answer' => 'Start with your client journey: lead, quote, order, contract, payment, task assignment, execution, completion, and follow-up. Then configure your workspace around those stages.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'How should I structure a product or food business inside Ophyra?',
                'answer' => 'Start with products, orders, preparation, fulfillment, delivery, tracking, and customer follow-up. Store + Logistics should help you manage the movement from purchase to delivery.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'When should a business use both operating cores?',
                'answer' => 'A business should use both operating cores when it sells products and services at the same time. For example, an event company may sell planning services and also sell products, food boxes, rentals, or delivery items.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'How should I think about CRM in Ophyra?',
                'answer' => 'CRM should not be a disconnected contact list. It should show customers in relation to orders, purchases, contracts, chats, payments, and tasks. A customer may have service history, store history, or both.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'How can team members be used correctly?',
                'answer' => 'Team members should be assigned to specific tasks, orders, deliveries, or service steps. Their work should be connected to business context, task status, and, when applicable, payroll or hours.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'What is the role of task evidence?',
                'answer' => 'Task evidence helps prove that work was completed. A photo of a prepared order, delivered package, installed setup, or completed service gives managers and customers more confidence.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'How should a business use public order status links?',
                'answer' => 'Public order status links reduce customer confusion. They allow customers to see whether an order is paid, preparing, packed, shipped, delivered, or completed without needing to ask repeatedly.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'What should I do before adding external marketplace orders?',
                'answer' => 'First make sure your internal order workflow is solid. Store orders, tasks, statuses, evidence, and public tracking should work properly before importing orders from outside platforms.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'Why should I avoid creating duplicate customers?',
                'answer' => 'Duplicate customers make reports, communication, and order history unreliable. It is better to connect one global user or customer record to multiple business interactions when possible.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'How can I improve follow-up with clients?',
                'answer' => 'Use CRM notes, order statuses, reminders, and chat context. Follow-up should be connected to what the customer actually requested, purchased, signed, or paid.',
            ],
            [
                'category' => 'System Issues & Improvement Reports',
                'question' => 'What should I do if a module appears active but I did not pay for it?',
                'answer' => 'Report it to the Ophyra team. Paid modules should only become active after confirmed payment or manual activation by an authorized Ophyra admin.',
            ],
            [
                'category' => 'System Issues & Improvement Reports',
                'question' => 'What should I do if checkout only shows USD?',
                'answer' => 'Report the issue. Ophyra billing should support the configured currencies. If only USD appears, the currency selector, pricing configuration, or Stripe checkout setup may need review.',
            ],
            [
                'category' => 'System Issues & Improvement Reports',
                'question' => 'What should I do if I click calendar view and the orders page goes blank?',
                'answer' => 'Report the issue with your user role and browser. The order calendar view should work without breaking the table view.',
            ],
            [
                'category' => 'System Issues & Improvement Reports',
                'question' => 'What should I report if I see VNV Events content inside Ophyra Platform?',
                'answer' => 'Report the page and content you saw. Ophyra Platform should not show private VNV Events content by default. Content must respect the active business or project context.',
            ],
            [
                'category' => 'System Issues & Improvement Reports',
                'question' => 'What should I do if a locked module opens anyway?',
                'answer' => 'Report it immediately. Locked modules should not allow access unless the correct operating core or add-on is active.',
            ],
            [
                'category' => 'System Issues & Improvement Reports',
                'question' => 'What should I do if my sidebar changes unexpectedly?',
                'answer' => 'Report your user role, active business context, and the page where it happened. Sidebar navigation should depend on your role, associations, and active business context.',
            ],
            [
                'category' => 'System Issues & Improvement Reports',
                'question' => 'What should I do if a payment succeeds but the module stays locked?',
                'answer' => 'Report the payment date, module, and currency. The team may need to verify the payment record, Stripe webhook, module activation, and renewal date.',
            ],
            [
                'category' => 'System Issues & Improvement Reports',
                'question' => 'What should I do if a payment fails but the module becomes active?',
                'answer' => 'Report it immediately. Modules should not activate after failed or cancelled payments. This may indicate a checkout or webhook issue.',
            ],
            [
                'category' => 'System Issues & Improvement Reports',
                'question' => 'What should I do if I see customers from another business?',
                'answer' => 'Report the page and business context. Customer and order data must be scoped by association, business, order, or active context. Users should not see data from unrelated businesses.',
            ],
            [
                'category' => 'System Issues & Improvement Reports',
                'question' => 'What should I do if search in the community does not find an answer?',
                'answer' => 'Try different keywords. If you still cannot find an answer, create a new thread. Your thread may require approval before being published.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'How does a client relate to multiple businesses?',
                'answer' => 'A client is a global user who can interact with multiple businesses. The relationship is created through orders, purchases, requests, contracts, chats, or associations. The first company that created the client does not permanently own that user.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'How does a team member relate to a business?',
                'answer' => 'A team member works inside a business context. Their tasks, orders, chats, payroll, and permissions depend on the company they are currently working for.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Can a client later create their own business?',
                'answer' => 'Yes. A user who started as a client can later create a business and become a business owner without losing their previous history as a client.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Can a team member later create their own business?',
                'answer' => 'Yes. A team member can later create a business and become a business owner while keeping their previous team member associations.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'Why does Ophyra use business context?',
                'answer' => 'Business context keeps data separated. It helps make sure orders, customers, products, tasks, chats, payroll, CMS pages, and private content belong to the correct business.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What is the difference between Ophyra Platform and a business workspace?',
                'answer' => 'Ophyra Platform manages the SaaS system, memberships, modules, billing, tenants, and global settings. A business workspace manages a specific business operation such as services, store orders, team, products, or customers.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What is the difference between a public profile and a public order link?',
                'answer' => 'A public profile shows business information. A public order link shows the status, payment, tracking, or progress of a specific order.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What should appear in a public order status link?',
                'answer' => 'A public order status link should show order summary, payment status, preparation or service progress, delivery or tracking status, and approved customer-visible evidence when available.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What is Advanced Storage / QR Inventory for?',
                'answer' => 'Advanced Storage / QR Inventory is for businesses that need deeper physical tracking, such as containers, equipment, rentals, QR labels, storage locations, or warehouse-style organization.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What is AI Advisor for?',
                'answer' => 'AI Advisor helps with ideas, summaries, operational suggestions, content support, and recommendations. It should work in context, such as platform, service operations, or store operations.',
            ],
            [
                'category' => 'System How-To',
                'question' => 'What is Ticket Sales + RSVP for?',
                'answer' => 'Ticket Sales + RSVP is for events, registrations, attendance, RSVP lists, and ticket workflows. It should only be active when the module is enabled.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'How can I know if my business is ready for automation?',
                'answer' => 'Your business is ready for automation when your process is already clear. First define orders, statuses, tasks, team roles, payment steps, and customer communication. Then automation can help reduce repetitive work.',
            ],
            [
                'category' => 'Business Owner Support',
                'question' => 'How can I reduce customer confusion?',
                'answer' => 'Keep customers informed with clear statuses, payment links, public order tracking, confirmations, and timely updates. Confusion usually happens when customers do not know what step comes next.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'What is the best way to onboard my team into Ophyra?',
                'answer' => 'Start with simple responsibilities. Assign team members to real tasks, teach them how to update status, require evidence when needed, and review completed work regularly.',
            ],
            [
                'category' => 'Consultant Advice',
                'question' => 'What should I document inside my business process?',
                'answer' => 'Document your customer journey, order stages, task responsibilities, payment process, delivery process, cancellation rules, and follow-up steps. The clearer your process is, the easier Ophyra is to configure.',
            ],
        ];
    }

    public static function categories(array $items): array
    {
        return array_values(array_unique(array_map(static fn (array $item): string => $item['category'], $items)));
    }
}
