<?php

namespace App\Support;

/**
 * Single source of truth for the ticket Type/Priority/Scale enums shared by
 * the Staging Ticket validation flow and the AI Ticket Analyzer:
 *   - StagingTicketController::approve() request validation
 *   - AiTicketAnalyzerService's response schema (both providers read it)
 *   - AnthropicTicketAnalysisDriver / OpenAiTicketAnalysisDriver structured-output schemas
 *   - the classification <select> options in resources/views/staging/index.blade.php
 *
 * Previously each of those hardcoded its own copy with no compiler or runtime
 * check keeping them in sync — a value added/renamed in one place would
 * silently drift from the others (AiTicketAnalyzerService::sanitizeEnum()
 * would just start returning null for it) instead of failing loudly.
 */
final class TicketClassification
{
    public const TYPES = ['Incident', 'Change Request', 'Service Request', 'EWA', 'RISE', 'Consult'];
    public const PRIORITIES = ['Very High', 'High', 'Medium', 'Low'];
    public const SCALES = ['Simple', 'Medium', 'Complex'];
}
