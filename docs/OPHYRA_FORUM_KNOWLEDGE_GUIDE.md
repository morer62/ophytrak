# Ophyra Forum Knowledge Guide

This document explains the public forum guidance library added to `/forum`.

## Runtime Files

- Public forum route/controller: `src/views/public/forum/index.php`
- Public forum view: `src/views/public/forum/index.twig`
- Forum guidance data: `src/Data/OphyraForumKnowledge.php`

The `/forum` page renders the guidance library above the topic list. It is intentionally part of the forum experience, not the Support Center.

## Purpose

The guidance library turns common Ophyra questions into discussion-ready prompts. Users can search by topic, filter by category, and expand answers before opening or joining a community discussion.

## Categories

- System How-To
- Business Owner Support
- Consultant Advice
- System Issues & Improvement Reports

## Maintenance Rules

- Keep public wording role-based and business-friendly. Do not expose internal labels such as Level 1, Level 2, Level 4, or Level 5 in public forum copy.
- Keep the content aligned with the current business model: Base Profile is free, operational modules require activation or payment, and marketplace connectors are a separate paid module.
- Do not use the Support Center as the main home for this library. Support should remain a contact path; the community guidance belongs to `/forum`.
- If the forum later stores these prompts as database topics, migrate from `OphyraForumKnowledge` into approved forum topics/categories and remove the static data source.
