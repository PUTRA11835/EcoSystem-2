<?php

namespace App\Services\Ai\Tools;

use App\Models\Employee;

/**
 * A single function the AI assistant may call to read EcoSystem data.
 *
 * Implementations must resolve the acting employee's permissions themselves
 * inside run() and must never trust an employee/user identifier coming from
 * the model's input — the $employee passed in is the only source of truth
 * for "who is asking".
 */
interface AiTool
{
    /**
     * Anthropic tool definition: name, description, input_schema.
     *
     * @return array<string, mixed>
     */
    public function definition(): array;

    /**
     * Execute the tool for the given employee and return a JSON-serializable
     * result. Throw to signal a hard failure (the caller turns it into an
     * is_error tool_result); return an array with an explanatory message for
     * "not allowed" / "nothing found" cases instead of throwing, so the
     * model can relay it to the user in plain language.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function run(Employee $employee, array $input): array;
}
