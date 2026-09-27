<?php
// POST { plan_id, plan_name, duration_months, price, original_price?, description?, features?, highlight_color? }
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();
$raw = input();
if (!isset($raw['plan_id']) && isset($raw['id'])) {
    $raw['plan_id'] = $raw['id'];
}
$d = validate($raw, ['plan_id' => 'required|int'] + [
    'plan_name' => 'required|string|maxlen:100',
    'duration_months' => 'required|int|min:1',
    'price' => 'required|numeric|min:0',
    'original_price' => 'numeric|min:0',
    'description' => 'string',
    'features' => 'string',
    'highlight_color' => 'string|maxlen:50',
]);

$existing = dbOne("SELECT * FROM app_plans WHERE id = ?", [$raw['plan_id']]);
if (!$existing) {
    throw new NotFoundException("Plan not found");
}

$planName = $d['plan_name'] ?? $existing['plan_name'];
$durationMonths = $d['duration_months'] ?? $existing['duration_months'];
$price = $d['price'] ?? $existing['price'];
$originalPrice = array_key_exists('original_price', $raw) ? ($d['original_price'] ?? null) : $existing['original_price'];
$description = array_key_exists('description', $raw) ? ($d['description'] ?? null) : $existing['description'];
$features = array_key_exists('features', $raw) ? ($d['features'] ?? null) : $existing['features'];
$highlightColor = array_key_exists('highlight_color', $raw) ? ($d['highlight_color'] ?? 'blue') : $existing['highlight_color'];

dbRun("UPDATE app_plans SET plan_name = ?, duration_months = ?, price = ?, original_price = ?, description = ?, features = ?, highlight_color = ? WHERE id = ?",
    [$planName, $durationMonths, $price, $originalPrice, $description, $features, $highlightColor, $existing['id']]);

sendSuccess(null, "Plan updated");
