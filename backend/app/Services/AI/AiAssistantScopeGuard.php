<?php

namespace App\Services\AI;

use App\Models\User;

/**
 * Backend guardrails for JPC AIDE: scope restriction, role/data boundaries,
 * and human-decision safeguards. This cannot be bypassed by frontend input.
 */
class AiAssistantScopeGuard
{
    public const OUT_OF_SCOPE_MESSAGE = "I'm sorry, but JPC AIDE is limited to JPC Portal-related tasks, employee progress monitoring, and report assistance. Please ask a question related to your reports, tasks, or employee progress.";

    public const HUMAN_DECISION_MESSAGE = "I can help explain, summarize, or suggest improvements, but I cannot approve or reject reports, make final performance evaluations, or change report/task status. Please use the authorized JPC Portal actions for that.";

    public const UNAUTHORIZED_DATA_MESSAGE = "I can only help with information you are authorized to view in JPC Portal. I cannot access another employee's private reports or confidential records.";

    /**
     * Response when a user asks the AI to determine, assign, or override a
     * True/Personal Assessment — which is exclusively a Department function.
     */
    public const ASSESSMENT_EMPLOYEE_MESSAGE = "I can assist you in reviewing and summarizing your submitted report. However, the official True/Personal Assessment is exclusively handled by the authorized Department. Please refer to your Department for the official assessment.";

    public const ASSESSMENT_ADMIN_MESSAGE = "I can provide a summary of recorded task and report information. However, I cannot determine or assign an official True/Personal Assessment. This responsibility belongs exclusively to the authorized Department.";

    public function isRoleAllowed(User $user): bool
    {
        return $user->hasAnyRole((array) config('ai.assistant.allowed_roles', ['admin', 'employee']));
    }

    /**
     * Returns a refusal message when the request is outside allowed scope or
     * asks for prohibited actions. Returns null when the request may proceed
     * to normal (still scoped/authorized) processing.
     */
    public function check(User $user, string $message): ?string
    {
        $text = $this->normalize($message);

        if ($text === '') {
            return null;
        }

        if ($this->requestsProhibitedAction($text)) {
            return self::HUMAN_DECISION_MESSAGE;
        }

        if ($this->requestsTruePersonalAssessment($text)) {
            return $user->hasAnyRole(['admin'])
                ? self::ASSESSMENT_ADMIN_MESSAGE
                : self::ASSESSMENT_EMPLOYEE_MESSAGE;
        }

        if ($this->requestsUnauthorizedEmployeeData($user, $message, $text)) {
            return self::UNAUTHORIZED_DATA_MESSAGE;
        }

        if ($this->isClearlyOutOfScope($text)) {
            return self::OUT_OF_SCOPE_MESSAGE;
        }

        return null;
    }

    private function normalize(string $message): string
    {
        $text = mb_strtolower($message);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * Detect requests for the AI to decide, assign, evaluate, or override a
     * True/Personal Assessment — which is exclusively a Department function.
     */
    private function requestsTruePersonalAssessment(string $text): bool
    {
        $patterns = [
            '/\b(true\s*(or|\/)\s*personal)\b/u',
            '/\b(personal\s*(or|\/)\s*true)\b/u',
            '/\b(true|personal)\b.{0,40}\b(assessment|evaluate|evaluation|rating|classify|classification|categorize|categorise|decide|decision)\b/u',
            '/\b(assess|evaluate|determine|classify|categorize|categorise)\b.{0,40}\b(true|personal)\b/u',
            '/\b(is|are|was|were)\b.{0,30}\b(true|personal)\b/u',
            '/\b(assign|set|mark|change|update)\b.{0,30}\b(true|personal)\b/u',
            '/\bofficial\b.{0,25}\b(assessment|evaluation)\b/u',
            '/\b(totoo\s*(o|\/)\s*personal)\b/u',
            '/\b(tasahin|suriin)\b.{0,30}\b(totoo|personal)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function requestsProhibitedAction(string $text): bool
    {
        $patterns = [
            '/\b(approve|reject|decline|accept)\b.{0,40}\b(report|submission|request)\b/u',
            '/\b(mark|set|change|update)\b.{0,40}\b(status|approval)\b.{0,40}\b(approved|rejected|returned|completed)\b/u',
            '/\bauto(matically)?\b.{0,30}\b(approve|reject|submit|evaluate)\b/u',
            '/\bsubmit\b.{0,30}\b(report|on behalf|for me)\b/u',
            '/\bfinal\b.{0,20}\b(performance evaluation|evaluation|rating|decision)\b/u',
            '/\bmake\b.{0,20}\bfinal\b.{0,30}\b(evaluation|decision|rating)\b/u',
            '/\bchange\b.{0,25}\btask status\b/u',
            '/\bcomplete\b.{0,20}\btask for\b/u',
            // Never make/execute automatic employment decisions.
            '/\b(decide|make|determine|recommend|should we)\b.{0,45}\b(fire|terminate|dismiss|suspend|promote|demote|hire|salary increase|pay raise|discipline)\b/u',
            '/\b(automatic|automatically)\b.{0,35}\b(employment decision|promotion|termination|discipline|hiring|firing)\b/u',
            // Never predict future employee performance.
            '/\b(predict|forecast|guarantee)\b.{0,45}\b(future|next month|next quarter|next year|will perform|will improve|will fail|performance)\b/u',
            '/\bhow will\b.{0,35}\b(perform|improve|do next|do in the future)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function requestsUnauthorizedEmployeeData(User $user, string $original, string $text): bool
    {
        if ($user->hasAnyRole(['admin'])) {
            return false; // Admin access remains subject to existing permissions/tools.
        }

        // Employee asking for another person's records/reports.
        $asksForOther = (bool) preg_match('/\b(other employee|another employee|someone else|coworker|co-worker|colleague|employee id|ni [A-Z][a-z]+|kay [A-Z][a-z]+)\b/u', $original)
            || (bool) preg_match('/\b(other|another|someone else\'s|coworker\'s|colleague\'s)\b.{0,30}\b(report|records|tasks|performance|progress|salary|confidential)\b/u', $text)
            || (bool) preg_match('/\b(show|view|access|get|give me)\b.{0,40}\b(all employees|everyone\'s|every employee)\b.{0,30}\b(private|confidential|report|record)\b/u', $text);

        if ($asksForOther) {
            return true;
        }

        $confidential = ['password', 'token', 'secret key', 'api key', 'salary', 'payroll', 'ssn', 'social security'];
        foreach ($confidential as $term) {
            if (str_contains($text, $term) && preg_match('/\b(show|give|reveal|tell me|what is|list)\b/u', $text)) {
                return true;
            }
        }

        return false;
    }

    private function isClearlyOutOfScope(string $text): bool
    {
        $jpcSignals = [
            'jpc', 'portal', 'report', 'task', 'progress', 'monitor', 'accomplishment', 'submission', 'feedback',
            'deadline', 'assigned', 'employee', 'admin', 'performance', 'weekly', 'daily', 'monthly', 'summary',
            'ulat', 'gawain', 'progreso', 'trabaho', 'natapos', 'hamon', 'plano',
        ];

        $hasJpcSignal = false;
        foreach ($jpcSignals as $signal) {
            if (str_contains($text, $signal)) {
                $hasJpcSignal = true;
                break;
            }
        }

        $outOfScopePatterns = [
            '/\b(business proposal|research paper|thesis|dissertation|term paper|essay|poem|story|short story|novel|song lyrics|script)\b/u',
            '/\b(write|generate|create|make|draft)\b.{0,25}\b(poem|story|essay|novel|song|lyrics|joke|recipe|business proposal|marketing plan|research paper)\b/u',
            '/\b(unrelated|about anything|anything else|general knowledge|trivia)\b/u',
            '/\b(weather|news|celebrity|sports score|stock price|crypto)\b/u',
            '/\bwho won\b.{0,40}\b(game|match|election)\b/u',
        ];

        foreach ($outOfScopePatterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                // Allow if clearly asking to improve/format a JPC report text (has signal and is improvement).
                if ($hasJpcSignal && preg_match('/\b(my report|this report|report text|improve|summarize|organize|grammar)\b/u', $text)) {
                    return false;
                }
                return true;
            }
        }

        // "Generate report" without JPC/task/accomplishment context is suspicious.
        if (preg_match('/\b(generate|create|write)\b.{0,20}\breport\b/u', $text) && ! $hasJpcSignal) {
            return true;
        }

        return false;
    }
}
