<?php

/**
 * Validate and clean request data.
 *
 *   $data = validate(input(), [
 *       'title'        => 'required|string|maxlen:255',
 *       'amount'       => 'required|numeric|min:0.01',
 *       'expense_date' => 'required|date',
 *       'category'     => 'string',
 *   ]);
 *
 * Returns every field named in $rules, cleaned (strings trimmed, emails lower-cased,
 * numbers cast). Optional fields that weren't sent are null.
 * Throws ValidationException with the first problem found.
 *
 * Rules: required, string, email, int, numeric, bool, date (Y-m-d), time (HH:MM[:SS]),
 *        array, in:a,b,c, min:n, max:n (numbers), maxlen:n (strings)
 */
function validate(array $data, array $rules)
{
    $clean = [];

    foreach ($rules as $field => $ruleString) {
        $fieldRules = explode("|", $ruleString);
        $label = preg_replace_callback('/\b(otp|id)\b/i', function ($m) {
            return strtoupper($m[1]);
        }, ucfirst(str_replace('_', ' ', $field)));
        $value = $data[$field] ?? null;

        if (is_string($value)) {
            $value = trim($value);
        }
        $isEmpty = $value === null || $value === '' || $value === [];

        if ($isEmpty) {
            if (in_array("required", $fieldRules, true)) {
                throw new ValidationException("$label is required");
            }
            $clean[$field] = null;
            continue;
        }

        foreach ($fieldRules as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);

            switch ($name) {
                case 'required':
                    break;
                case 'string':
                    if (!is_scalar($value)) {
                        throw new ValidationException("$label must be text");
                    }
                    $value = (string)$value;
                    break;
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new ValidationException("Invalid email format");
                    }
                    $value = strtolower($value);
                    break;
                case 'int':
                    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                        throw new ValidationException("$label must be a whole number");
                    }
                    $value = (int)$value;
                    break;
                case 'numeric':
                    if (!is_numeric($value)) {
                        throw new ValidationException("$label must be a number");
                    }
                    $value = $value + 0;
                    break;
                case 'bool':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'date':
                    $d = DateTime::createFromFormat('Y-m-d', (string)$value);
                    if (!$d || $d->format('Y-m-d') !== $value) {
                        throw new ValidationException("$label must be a date (YYYY-MM-DD)");
                    }
                    break;
                case 'time':
                    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string)$value)) {
                        throw new ValidationException("$label must be a time (HH:MM)");
                    }
                    break;
                case 'array':
                    if (!is_array($value)) {
                        throw new ValidationException("$label must be a list");
                    }
                    break;
                case 'in':
                    if (!in_array((string)$value, explode(',', $arg), true)) {
                        throw new ValidationException("$label must be one of: " . str_replace(',', ', ', $arg));
                    }
                    break;
                case 'min':
                    if ($value < $arg) {
                        throw new ValidationException("$label must be at least $arg");
                    }
                    break;
                case 'max':
                    if ($value > $arg) {
                        throw new ValidationException("$label must be at most $arg");
                    }
                    break;
                case 'maxlen':
                    if (mb_strlen((string)$value) > (int)$arg) {
                        throw new ValidationException("$label must be at most $arg characters");
                    }
                    break;
                default:
                    throw new LogicException("Unknown validation rule: $name");
            }
        }
        $clean[$field] = $value;
    }

    return $clean;
}
