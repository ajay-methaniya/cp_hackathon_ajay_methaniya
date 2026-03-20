<?php

declare(strict_types=1);

/**
 * Client-configured Common Sales Question Library (kitchen cabinet sales).
 * Stages: Discovery, Qualification, Sales, Objection Handling.
 *
 * @return list<array{id: string, stage: string, text: string}>
 */
return [
    ['id' => 'Q1', 'stage' => 'Discovery', 'text' => 'Are you remodeling the entire kitchen or just replacing cabinets?'],
    ['id' => 'Q2', 'stage' => 'Discovery', 'text' => 'Is this kitchen for your primary home or a rental property?'],
    ['id' => 'Q3', 'stage' => 'Discovery', 'text' => 'What is the approximate size or layout of your kitchen?'],
    ['id' => 'Q4', 'stage' => 'Discovery', 'text' => 'Are you planning to keep the same layout or redesign the kitchen?'],
    ['id' => 'Q5', 'stage' => 'Qualification', 'text' => 'Do you have a preferred cabinet style (Shaker, flat panel, traditional)?'],
    ['id' => 'Q6', 'stage' => 'Qualification', 'text' => 'Do you have a preferred cabinet color or finish?'],
    ['id' => 'Q7', 'stage' => 'Qualification', 'text' => 'What budget range are you targeting for your kitchen cabinets?'],
    ['id' => 'Q8', 'stage' => 'Qualification', 'text' => 'Would you like to schedule a design consultation or review designs?'],
    ['id' => 'Q9', 'stage' => 'Sales', 'text' => 'What are your thoughts about the design proposal we shared?'],
    ['id' => 'Q10', 'stage' => 'Sales', 'text' => 'Are you comparing quotes from other companies?'],
    ['id' => 'Q11', 'stage' => 'Sales', 'text' => 'What materials are the other companies offering?'],
    ['id' => 'Q12', 'stage' => 'Sales', 'text' => 'Would you like help reviewing the competitor quote?'],
    ['id' => 'Q13', 'stage' => 'Objection Handling', 'text' => 'Are there any concerns about pricing or materials?'],
    ['id' => 'Q14', 'stage' => 'Objection Handling', 'text' => 'Are you interested in additional features like soft-close drawers or organizers?'],
    ['id' => 'Q15', 'stage' => 'Objection Handling', 'text' => 'Do you have any concerns about delivery timelines or installation?'],
];
