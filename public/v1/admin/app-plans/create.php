<?php
// POST { plan_name, duration_months, price, original_price?, description?, features?, highlight_color? }
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();
$d = validate(input(), [
    'plan_name' => 'required|string|maxlen:100',
    'duration_months' => 'required|int|min:1',
    'price' => 'required|numeric|min:0',
    'original_price' => 'numeric|min:0',
    'description' => 'string',
    'features' => 'string',
    'highlight_color' => 'string|maxlen:50',
]);

dbRun("INSERT INTO app_plans (plan_name, duration_months, price, original_price, description, features, highlight_color) VALUES (?, ?, ?, ?, ?, ?, ?)",
    [
        $d['plan_name'],
        $d['duration_months'],
        $d['price'],
        $d['original_price'] ?? null,
        $d['description'] ?? null,
        $d['features'] ?? null,
        $d['highlight_color'] ?? 'blue'
    ]
);
sendSuccess(['id' => (int)db()->lastInsertId()], "Plan created");
